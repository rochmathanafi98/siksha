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

/**
 * Page for returning a block of html which will be inserted below the row that was clicked.
 */
define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/shezar/reportbuilder/lib.php');

// Send the correct headers.
send_headers('text/html; charset=utf-8', false);

require_sesskey();

$id = required_param('id', PARAM_INT);
$expandname = required_param('expandname', PARAM_TEXT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/shezar/reportbuilder/report.php', array('id' => $id));
$PAGE->set_shezar_menu_selected('myreports');
$PAGE->set_pagelayout('standard');

// Create the report object. Includes embedded report capability checks.
$report = new reportbuilder($id, null, false, 0);

// Decide if require_login should be executed.
if ($report->needs_require_login()) {
    require_login();
}

// Checks that the report is one that is returned by get_permitted_reports.
if (!reportbuilder::is_capable($id)) {
    print_error('nopermission', 'shezar_reportbuilder');
}

$output = $PAGE->get_renderer('shezar_reportbuilder');

echo $output->expand_container($report->get_expand_content($expandname));
