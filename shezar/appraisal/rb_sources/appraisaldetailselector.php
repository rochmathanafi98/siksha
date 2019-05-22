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
 * @author Nathan Lewis <nathan.lewis@shezarlms.com>
 * @package shezar
 * @subpackage reportbuilder
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/shezar/appraisal/lib.php');
require_once($CFG->dirroot . '/shezar/appraisal/renderer.php');
require_once($CFG->dirroot . '/shezar/reportbuilder/lib.php');

$detailreportid = required_param('detailreportid', PARAM_INT);

// Set page context.
$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);

$output = $PAGE->get_renderer('shezar_appraisal');

$report = new reportbuilder($detailreportid, null, false, null, 'setup');

// Check that the user has permission to view the report. Uses report builder access settings.
if (!$report->is_capable($detailreportid)) {
    print_error('nopermission', 'shezar_reportbuilder');
}

$fullname = $report->fullname;

// Start page output.
$PAGE->set_url('/shezar/appraisal/rb_source/appraisaldetailselector.php', array('detailreportid' => $detailreportid));
$PAGE->set_shezar_menu_selected('myreports');
$PAGE->set_pagelayout('standard');
$heading = get_string('myappraisals', 'shezar_appraisal');
$PAGE->set_title($heading);
$PAGE->set_heading($heading);
$PAGE->navbar->add(get_string('reports', 'shezar_core'), new moodle_url('/my/reports.php'));
$PAGE->navbar->add($fullname);

echo $output->header();

echo $output->heading($fullname);
$appraisals = appraisal::get_manage_list();
echo $output->detail_report_table($detailreportid, $appraisals);

echo $output->footer();
