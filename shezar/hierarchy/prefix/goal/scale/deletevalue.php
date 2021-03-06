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

require_once(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/shezar/hierarchy/lib.php');
require_once('lib.php');
require_once($CFG->dirroot . '/shezar/hierarchy/prefix/goal/lib.php');

// Check if Goals are enabled.
goal::check_feature_enabled();

//
// Setup / loading data.
//

// Get params.
$id = required_param('id', PARAM_INT);
$prefix = required_param('prefix', PARAM_ALPHA);
// Delete confirmation hash.
$delete = optional_param('delete', '', PARAM_ALPHANUM);

// Cache user capabilities.
$sitecontext = context_system::instance();

// Permissions.
require_capability('shezar/hierarchy:delete'.$prefix.'scale', $sitecontext);

// Set up the page.
admin_externalpage_setup($prefix.'manage');

if (!$value = $DB->get_record('goal_scale_values', array('id' => $id))) {
    print_error('incorrectgoalscalevalueid', 'shezar_hierarchy');
}

$scale = $DB->get_record('goal_scale', array('id' => $value->scaleid));

//
// Display page.
//

$returnparams = array('id' => $value->scaleid, 'prefix' => 'goal');
$returnurl = new moodle_url('/shezar/hierarchy/prefix/goal/scale/view.php', $returnparams);
$deleteparams = array('id' => $value->id, 'delete' => md5($value->timemodified), 'sesskey' => $USER->sesskey, 'prefix' => 'goal');
$deleteurl = new moodle_url('/shezar/hierarchy/prefix/goal/scale/deletevalue.php', $deleteparams);

// Can't delete if the scale is in use.
if (goal_scale_is_used($value->scaleid)) {
    shezar_set_notification(get_string('error:nodeletescalevalueinuse', 'shezar_hierarchy'), $returnurl);
}

if ($value->id == $scale->defaultid) {
    shezar_set_notification(get_string('error:nodeletegoalscalevaluedefault', 'shezar_hierarchy'), $returnurl);
}

if (!$delete) {
    echo $OUTPUT->header();
    $strdelete = get_string('deletecheckscalevalue', 'shezar_hierarchy');

    echo $OUTPUT->confirm($strdelete . html_writer::empty_tag('br') . html_writer::empty_tag('br')
        . format_string($value->name), $deleteurl, $returnurl);

    echo $OUTPUT->footer();
    exit;
}


//
// Delete goal scale.
//

if ($delete != md5($value->timemodified)) {
    shezar_set_notification(get_string('error:checkvariable', 'shezar_hierarchy'), $returnurl);
}

if (!confirm_sesskey()) {
    shezar_set_notification(get_string('confirmsesskeybad', 'error'), $returnurl);
}

$DB->delete_records('goal_scale_values', array('id' => $value->id));

\hierarchy_goal\event\scale_value_deleted::create_from_instance($value)->trigger();

shezar_set_notification(get_string('deletedgoalscalevalue', 'shezar_hierarchy', format_string($value->name)),
    $returnurl, array('class' => 'notifysuccess'));
