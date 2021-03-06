<?php
/*
 * This file is part of shezar LMS
 *
 * Copyright (C) 2015 onwards shezar Learning Solutions LTD
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
 * @author Petr Skoda <petr.skoda@shezarlms.com>
 * @package shezar_connect
 */

use \shezar_connect\util;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/shezar/reportbuilder/lib.php');

admin_externalpage_setup('shezarconnectclients');

$report = reportbuilder_get_embedded_report('connect_clients', array(), false, 0);

echo $OUTPUT->header();

$strheading = get_string('embeddedreportname', 'rb_source_connect_clients');
echo $OUTPUT->heading($strheading);
echo util::warn_if_not_https();

// No searching here, there are going to be very few servers registered.

$report->display_table();

$url = new moodle_url('/shezar/connect/client_add.php');
echo $OUTPUT->single_button($url, get_string('clientadd', 'shezar_connect'));

echo $OUTPUT->footer();
