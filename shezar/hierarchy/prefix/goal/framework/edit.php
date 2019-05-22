<?php
/*
 * This file is part of shezar LMS
 *
 * Copyright (C) 2010 onwards shezar Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Simon Coggins <simon.coggins@shezarlms.com>
 * @package shezar
 * @subpackage shezar_hierarchy
 */

require_once($CFG->dirroot.'/shezar/hierarchy/lib.php');
require_once($CFG->dirroot.'/shezar/hierarchy/prefix/goal/scale/lib.php');

// This page is being included from another, which is why $prefix and $id are already defined.
$shortprefix = hierarchy::get_short_prefix($prefix);
// Make this page appear under the manage 'hierarchy' admin menu.
$adminurl = $CFG->wwwroot.'/shezar/hierarchy/framework/edit.php?prefix='.$prefix.'&id='.$id;
admin_externalpage_setup($prefix.'manage', '', array(), $adminurl);
$context = context_system::instance();

if ($id == 0) {
    // Creating new framework.
    require_capability('shezar/hierarchy:create'.$prefix.'frameworks', $context);

    // Don't show the page if there are no scales.
    if (!goal_scales_available()) {

        // Display page header.
        echo $OUTPUT->header();
        notice(get_string('nogoalscales', 'shezar_hierarchy'), "{$CFG->wwwroot}/shezar/hierarchy/framework/index.php?prefix=goal" );
        echo $OUTPUT->footer();
        die(); // Agh.
    }

    $framework = new stdClass();
    $framework->id = 0;
    $framework->visible = 1;
    $framework->description = '';
    $framework->sortorder = $DB->get_field($shortprefix.'_framework', 'MAX(sortorder) + 1', array());
    $framework->hidecustomfields = 0;
    if (!$framework->sortorder) {
        $framework->sortorder = 1;
    }
    $framework->scale = array();

} else {
    // Editing existing framework.
    require_capability('shezar/hierarchy:update'.$prefix.'frameworks', $context);

    if (!$framework = $DB->get_record($shortprefix.'_framework', array('id' => $id))) {
        print_error('incorrectframework', 'shezar_hierarchy', $prefix);
    }

    // Load scale assignments.
    $scales = $DB->get_records($shortprefix.'_scale_assignments', array('frameworkid' => $framework->id));
    $framework->scale = array();
    if ($scales) {
        foreach ($scales as $scale) {
            $framework->scale[] = $scale->scaleid;
        }
    }
}

// Create form.
$framework->descriptionformat = FORMAT_HTML;
$framework = file_prepare_standard_editor($framework, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
             'shezar_hierarchy', $shortprefix.'_framework', $framework->id);
$frameworkform = new framework_edit_form(null, array('frameworkid' => $id));
$frameworkform->set_data($framework);

if ($frameworkform->is_cancelled()) {
    // Cancelled.
    redirect("$CFG->wwwroot/shezar/hierarchy/framework/index.php?prefix=$prefix");

} else if ($frameworknew = $frameworkform->get_data()) {
    // Update data.
    // Validate that the selected framework contains at least one framework value.
    if (!isset($frameworknew->scale) || 0 == $DB->count_records('goal_scale_values', array('scaleid' => $frameworknew->scale))) {
        print_error('goalframeworknotfound', 'shezar_hierarchy');
    }

    $time = time();

    $frameworknew->timemodified = $time;
    $frameworknew->usermodified = $USER->id;

    // Save.
    if ($frameworknew->id == 0) {
        $new = true;
        // New framework.
        unset($frameworknew->id);

        $frameworknew->timecreated = $time;

        $frameworknew->id = $DB->insert_record($shortprefix.'_framework', $frameworknew);
        $frameworknew = file_postupdate_standard_editor($frameworknew, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
            'shezar_hierarchy', $shortprefix.'_framework', $frameworknew->id);
        $DB->set_field($shortprefix.'_framework', 'description', $frameworknew->description, array('id' => $frameworknew->id));

    } else {
        $new = false;
        // Existing framework.
        $frameworknew = file_postupdate_standard_editor($frameworknew, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
            'shezar_hierarchy', $shortprefix.'_framework', $frameworknew->id);
        $DB->update_record($shortprefix.'_framework', $frameworknew);
    }
    // Handle scale assignments.
    // Get new assignments.
    if (isset($frameworknew->scale)) {
        $scales_new = array_diff(array($frameworknew->scale), $framework->scale);
        foreach ($scales_new as $key) {
            $assignment = new stdClass();
            $assignment->scaleid = $key;
            $assignment->frameworkid = $frameworknew->id;
            $assignment->timemodified = $time;
            $assignment->usermodified = $USER->id;
            $DB->insert_record($shortprefix.'_scale_assignments', $assignment);
        }

        // Get removed assignments.
        $scales_removed = array_diff($framework->scale, array($frameworknew->scale));
    } else {
        $scales_removed = $framework->scale;
    }

    foreach ($scales_removed as $key) {
        $DB->delete_records($shortprefix.'_scale_assignments', array('scaleid' => $key, 'frameworkid' => $frameworknew->id));
    }

    // Reload from db.
    $frameworknew = $DB->get_record($shortprefix.'_framework', array('id' => $frameworknew->id));

    if ($new) {
        \hierarchy_goal\event\framework_created::create_from_instance($frameworknew)->trigger();
    } else {
        \hierarchy_goal\event\framework_updated::create_from_instance($frameworknew)->trigger();
    }

    redirect("$CFG->wwwroot/shezar/hierarchy/framework/index.php?prefix=$prefix&id=" . $frameworknew->id);
    // Never reached.
}

// Display page header.
$PAGE->navbar->add(get_string("{$prefix}frameworks", 'shezar_hierarchy'),
                    new moodle_url('/shezar/hierarchy/framework/index.php', array('prefix' => $prefix)));
if ($id == 0) {
    $PAGE->navbar->add(get_string($prefix.'addnewframework', 'shezar_hierarchy'));
} else {
    $PAGE->navbar->add(format_string($framework->fullname));
}

echo $OUTPUT->header();

if ($framework->id == 0) {
    echo $OUTPUT->heading(get_string($prefix.'addnewframework', 'shezar_hierarchy'));
} else {
    echo $OUTPUT->heading(format_string($framework->fullname), 1);
}

// Finally display THE form.
$frameworkform->display();

// And proper footer.
echo $OUTPUT->footer();
