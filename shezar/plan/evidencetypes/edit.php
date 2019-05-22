<?php
/*
 * This file is part of shezar LMS
 *
 * Copyright (C) 2010 onwards shezar Learning Solutions LTD
 * Copyright (C) 1999 onwards Martin Dougiamas
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
 * @author Alastair Munro <alastair@catalyst.net.nz>
 * @author Simon Coggins <simonc@catalyst.net.nz>
 * @author Russell England <russell.england@shezarlms.com>
 * @package shezar
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('edit_form.php');
require_once($CFG->dirroot.'/shezar/plan/lib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

$id = optional_param('id', 0, PARAM_INT); // evidencetype id; 0 if creating a new evidencetype

// Page setup and check permissions
admin_externalpage_setup('evidencetypes');
$context = context_system::instance();
require_capability('shezar/plan:manageevidencetypes', $context);

if (empty($id)) {
    // creating new evidencetype
    $item = new stdClass();
    $item->id = 0;
    $item->name = '';
    $item->description = '';
} else {
    // editing existing evidencetype
    if (!$item = $DB->get_record('dp_evidence_type', array('id' => $id))) {
        print_error('error:evidencetypedidincorrect', 'shezar_plan');
    }
}

$item->descriptionformat = FORMAT_HTML;
$item = file_prepare_standard_editor($item, 'description',
        $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'shezar_plan', 'dp_evidence_type', $item->id);

$mform = new edit_evidencetype_form(null, array('id' => $id));
$mform->set_data($item);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/shezar/plan/evidencetypes/index.php'));
} else if ($data = $mform->get_data()) {

    $data->timemodified = time();
    $data->usermodified = $USER->id;

    // Settings for postupdate
    $data->description       = '';

    if (empty($data->id)) {
        // New type, so add to the end of the list
        $action = 'added';
        $data->sortorder = 1 + $DB->get_field_sql("SELECT MAX(sortorder) FROM {dp_evidence_type}");
        $data->id = $DB->insert_record('dp_evidence_type', $data);

        // save and relink embedded images
        $data = file_postupdate_standard_editor($data, 'description',
            $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'shezar_plan', 'dp_evidence_type', $data->id);
        $DB->update_record('dp_evidence_type', $data);
        $data = $DB->get_record('dp_evidence_type', array('id' => $data->id));

        \shezar_plan\event\evidence_type_created::create_from_type($data)->trigger();

    } else {
        $action = 'updated';
        $data = file_postupdate_standard_editor($data, 'description',
            $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'shezar_plan', 'dp_evidence_type', $data->id);
        $DB->update_record('dp_evidence_type', $data);
        $data = $DB->get_record('dp_evidence_type', array('id' => $data->id));

        \shezar_plan\event\evidence_type_updated::create_from_type($data)->trigger();
    }

    shezar_set_notification(get_string('evidencetype'.$action, 'shezar_plan',
            format_string(stripslashes($data->name))),
        new moodle_url('/shezar/plan/evidencetypes/view.php', array('id' => $data->id)),
        array('class' => 'notifysuccess'));

}

// Print Page
if (empty($id)) { // Add
    $heading = get_string('evidencetypecreate', 'shezar_plan');
} else {    // Edit
    $heading = get_string('editevidencetype', 'shezar_plan', format_string($item->name));
}
$PAGE->navbar->add($heading);

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

$mform->display();

echo $OUTPUT->footer();
