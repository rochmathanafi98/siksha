<?php
/*
 * This file is part of shezar LMS
 *
 * Copyright (C) 2014 onwards shezar Learning Solutions LTD
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
 * @package shezar_reportbuilder
 */

namespace shezar_reportbuilder\rb\aggregate;

/**
 * Class describing column aggregation options.
 */
class groupconcat extends base {
    protected static function get_field_aggregate($field) {
        global $DB;

        $dbfamily = $DB->get_dbfamily();
        if ($dbfamily === 'mysql') {
            $field = "GROUP_CONCAT($field SEPARATOR ', ')";
        } else if ($dbfamily === 'mssql') {
            $field = "dbo.GROUP_CONCAT_D($field, ', ')";
        } else {
            $field = "string_agg(CAST($field AS text), ', ')";
        }

        return $field;
    }

    public static function is_column_option_compatible(\rb_column_option $option) {
        return ($option->dbdatatype !== 'timestamp');
    }

    public static function is_graphable(\rb_column $column, \rb_column_option $option, \reportbuilder $report) {
        return false;
    }
}
