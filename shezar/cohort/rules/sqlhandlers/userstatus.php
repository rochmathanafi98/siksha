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
 * @author Maria Torres <maria.torres@shezarlms.com>
 * @package shezar_cohort
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

/**
 * A rule for determining whether or not a user is suspended.
 */
class cohort_rule_sqlhandler_suspended_user_account extends cohort_rule_sqlhandler {

    public $params = array(
        'equal' => 0,
        'listofvalues' => 1
    );

    public function get_sql_snippet() {
        $sqlhandler = new stdClass();
        $suspended = array_pop($this->listofvalues);
        $sqlhandler->sql = "u.suspended = :suspended{$this->ruleid}";
        $sqlhandler->params = array("suspended{$this->ruleid}" => $suspended);
        return $sqlhandler;
    }
}
