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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package shezar
 * @subpackage reportbuilder
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/shezar/core/utils.php');
require_once($CFG->dirroot . '/shezar/reportbuilder/filters/badge.php');

$ids = required_param('ids', PARAM_SEQUENCE);
$ids = explode(',', $ids);
$filtername = required_param('filtername', PARAM_ALPHANUMEXT);

require_login();
require_sesskey();

// Legacy shezar HTML ajax, this should be converted to json + AJAX_SCRIPT.
send_headers('text/html; charset=utf-8', false);

$PAGE->set_context(context_system::instance());

echo html_writer::start_tag('div', array('class' => "list-{$filtername}"));
if (!empty($ids)) {
    list($insql, $params) = $DB->get_in_or_equal($ids);
    $badges = $DB->get_records_select('badge', "id {$insql}", $params);
    foreach ($badges as $badge) {
        echo display_selected_badge_item($badge, $filtername);
    }
}
echo html_writer::end_tag('div');
