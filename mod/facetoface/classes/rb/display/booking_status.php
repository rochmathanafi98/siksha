<?php
/*
 * This file is part of shezar LMS
 *
 * Copyright (C) 2016 onwards shezar Learning Solutions LTD
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
 * @author Sam Hemelryk <sam.hemelryk@shezarlms.com>
 * @package mod_facetoface
 */

namespace mod_facetoface\rb\display;

/**
 * Display F2F booking status including highlighting.
 *
 * CSS styles for the highlighting live in mod/facetoface/styles.css
 *
 * @package mod_facetoface
 */
class booking_status extends \shezar_reportbuilder\rb\display\base {

    /**
     * Displays the booking status.
     *
     * @param string $value
     * @param string $format
     * @param \stdClass $row
     * @param \rb_column $column
     * @param \reportbuilder $report
     * @return string
     */
    public static function display($value, $format, \stdClass $row, \rb_column $column, \reportbuilder $report) {
        switch($value) {
            case 'underbooked':
                $str = get_string('status:underbooked', 'rb_source_facetoface_summary');
                $class = 'underbooked';
                break;
            case 'available':
                $str = get_string('status:available', 'rb_source_facetoface_summary');
                $class = 'available';
                break;
            case 'fullybooked':
                $str = get_string('status:fullybooked', 'rb_source_facetoface_summary');
                $class = 'fullybooked';
                break;
            case 'overbooked':
                $str = get_string('status:overbooked', 'rb_source_facetoface_summary');
                $class = 'overbooked';
                break;
            case 'cancelled':
            case 'ended':
            default:
                $str = get_string('status:notavailable', 'rb_source_facetoface_summary');
                $class = 'notavailable';
        }
        if ($format !== 'html') {
            return $str;
        }
        return \html_writer::div('<span>'.$str.'</span>', $class);
    }

    /**
     * Is this column graphable? No is the answer. You can't plot status strings.
     *
     * @param \rb_column $column
     * @param \rb_column_option $option
     * @param \reportbuilder $report
     * @return bool
     */
    public static function is_graphable(\rb_column $column, \rb_column_option $option, \reportbuilder $report) {
        // You can't plot strings on a graph - this display type is not graphable.
        return false;
    }
}