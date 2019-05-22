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
 * @author Alastair Munro <alastair.munro@shezarlms.com>
 * @package shezar
 * @subpackage reportbuilder
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/shezar/core/lib.php');
require_once($CFG->dirroot . '/shezar/reportbuilder/lib.php');

require_login();

// Get params
$id = required_param('id', PARAM_INT); //ID
$confirm = optional_param('confirm', '', PARAM_INT); // Delete confirmation hash

$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url('/shezar/reportbuilder/deletescheduled.php', array('id' => $id));
$PAGE->set_shezar_menu_selected('myreports');

if (!$scheduledreport = $DB->get_record('report_builder_schedule', array('id' => $id))) {
    print_error('error:invalidreportscheduleid', 'shezar_reportbuilder');
}

if (!reportbuilder::is_capable($scheduledreport->reportid)) {
    print_error('nopermission', 'shezar_reportbuilder');
}
if ($scheduledreport->userid != $USER->id) {
    require_capability('shezar/reportbuilder:managereports', context_system::instance());
}

$reportname = $DB->get_field('report_builder', 'fullname', array('id' => $scheduledreport->reportid));

$returnurl = new moodle_url('/my/reports.php');
$deleteurl = new moodle_url('/shezar/reportbuilder/deletescheduled.php', array('id' => $scheduledreport->id, 'confirm' => '1', 'sesskey' => $USER->sesskey));

if ($confirm == 1) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    } else {
        $select = "scheduleid = ?";
        $DB->delete_records_select('report_builder_schedule_email_audience', $select, array($scheduledreport->id));
        $DB->delete_records_select('report_builder_schedule_email_systemuser', $select, array($scheduledreport->id));
        $DB->delete_records_select('report_builder_schedule_email_external', $select, array($scheduledreport->id));
        $DB->delete_records('report_builder_schedule', array('id' => $scheduledreport->id));
        $report = new reportbuilder($scheduledreport->reportid);
        \shezar_reportbuilder\event\report_updated::create_from_report($report, 'scheduled')->trigger();
        shezar_set_notification(get_string('deletedscheduledreport', 'shezar_reportbuilder', format_string($reportname)),
                                $returnurl, array('class' => 'notifysuccess'));
    }
}
/// Display page
$PAGE->set_title(get_string('deletescheduledreport', 'shezar_reportbuilder'));
$PAGE->set_heading(format_string($SITE->fullname));
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('deletescheduledreport', 'shezar_reportbuilder'));
if (!$confirm) {
    echo $OUTPUT->confirm(get_string('deletecheckschedulereport', 'shezar_reportbuilder', format_string($reportname)), $deleteurl, $returnurl);

    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->footer();
