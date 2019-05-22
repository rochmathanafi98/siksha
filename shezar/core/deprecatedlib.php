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
 * @author David Curry <david.curry@shezarlms.com>>
 * @package shezar
 * @subpackage shezar_core
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @deprecated since shezar 2.9.0
 * @param int $length
 * @return string
 */
function shezar_random_bytes($length) {
    debugging('shezar_random_bytes() is deprecated, use shezar_random_bytes() instead', DEBUG_DEVELOPER);
    return random_bytes_emulate($length);
}

function shezar_generate_email_user($email) {
    debugging('shezar_generate_email_user($email) is deprecated, use \shezar_core\shezar_user::get_external_user($email) instead', DEBUG_DEVELOPER);
    return \shezar_core\shezar_user::get_external_user($email);
}

/**
 * Human-readable version of the duration field used to display it to
 * users
 *
 * @param   integer $duration duration in hours
 * @return  string
 */
function format_duration($duration) {
    debugging('format_duration() is deprecated, use format_time() instead', DEBUG_DEVELOPER);
    return format_time($duration);
}

/**
 * Converts minutes to hours
 */
function facetoface_minutes_to_hours($minutes) {
    debugging('facetoface_minutes_to_hours() is deprecated, use format_time() instead', DEBUG_DEVELOPER);
    return format_time($minutes * MINSECS);
}

/**
 * Converts hours to minutes
 */
function facetoface_hours_to_minutes($hours) {
    debugging('facetoface_hours_to_minutes() is deprecated, use format_time() instead', DEBUG_DEVELOPER);
    return format_time($hours * HOURSECS);
}
