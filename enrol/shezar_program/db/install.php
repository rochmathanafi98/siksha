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
 * @author Ciaran Irvine <ciaran.irvine@shezarlms.com>
 * @package enrol
 * @subpackage shezar_program
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_enrol_shezar_program_install() {
    global $CFG, $DB;

    if (!shezar_feature_visible('programs')) {
        return;
    }

    //enable program enrolment plugin
    $enabledplugins = explode(',', $CFG->enrol_plugins_enabled);
    $enabledplugins[] = 'shezar_program';
    $enabledplugins = array_unique($enabledplugins);
    set_config('enrol_plugins_enabled', implode(',', $enabledplugins));
}
