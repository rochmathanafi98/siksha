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
 * @author David Curry <david.curry@shezarlms.com>
 * @package shezar
 * @subpackage shezar_hierarchy
 */

/**
 * base org grouping assignment class
 * will mostly be extended by child classes in each shezar module, but is generic and functional
 * enough to still be useful for simple assignment cases
 */
global $CFG;
require_once($CFG->dirroot.'/shezar/core/lib/assign/lib.php');
require_once($CFG->dirroot.'/shezar/core/lib/assign/groups/org.class.php');
require_once($CFG->dirroot.'/shezar/hierarchy/prefix/position/lib.php');
require_once($CFG->dirroot.'/shezar/hierarchy/prefix/goal/lib.php');


class shezar_assign_goal_grouptype_org extends shezar_assign_core_grouptype_org {

    // Code to accept data from generate_item_selector().
    public function handle_item_selector($data) {
        global $DB, $USER;

        $goal = new goal();

        // Check target table exists!
        $dbman = $DB->get_manager();
        $table = new xmldb_table($this->tablename);
        if (!$dbman->table_exists($table)) {
            print_error('error:assigntablenotexist', 'shezar_core', $this->tablename);
        }

        $modulekeyfield = "{$this->module}id";
        $grouptypekeyfield = "{$this->grouptype}id";
        // Add only the new records.
        $existingassignments = $DB->get_fieldset_select($this->tablename, $grouptypekeyfield, "{$modulekeyfield} = ?",
                array($modulekeyfield => $this->moduleinstanceid));

        // Define a few of these things outside of the loop to stop repeating ourselves.
        $todb = new stdClass();
        $todb->timemodified = time();
        $todb->usermodified = $USER->id;
        $todb->includechildren = $data['includechildren'];

        foreach ($data['listofvalues'] as $assignedgroupid) {
            if (!in_array($assignedgroupid, $existingassignments)) {
                // Create the assignment.
                $todb->$modulekeyfield = $this->moduleinstanceid;
                $todb->$grouptypekeyfield = $assignedgroupid;
                $todb->id = $DB->insert_record($this->tablename, $todb);

                // Create all the user assignments to go along with it.
                $goal->create_user_assignments(GOAL_ASSIGNMENT_ORGANISATION, $todb, $todb->includechildren);

                // So it won't affect the next insert.
                unset($todb->id);
            }
        }

        return true;
    }

    // Code to validate data from generate_item_selector().
    public function validate_item_selector() {
        return true;
    }


}
