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
 * @package shezar
 * @subpackage program
 */

function xmldb_shezar_program_install() {
    global $CFG, $DB;
    $dbman = $DB->get_manager();
    // Conditionally add 'programcount' field to 'course_categories'.
    $table = new xmldb_table('course_categories');
    $field = new xmldb_field('programcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

    if (!$dbman->field_exists($table, $field)) {
        // Launch add field programcount.
        $dbman->add_field($table, $field);
    }

    // Conditionally add 'certifcount' field to 'course_categories'.
    $table = new xmldb_table('course_categories');
    $field = new xmldb_field('certifcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

    if (!$dbman->field_exists($table, $field)) {
        // Launch add field certifcount.
        $dbman->add_field($table, $field);
    }

    // Update category counts.
    $sql = 'SELECT cat.id,
                    SUM(CASE WHEN p.certifid IS NULL THEN 1 ELSE 0 END) AS programcount,
                    SUM(CASE WHEN p.certifid IS NULL THEN 0 ELSE 1 END) AS certifcount
            FROM {prog} p
            JOIN {course_categories} cat ON cat.id = p.category
            GROUP BY cat.id';
    $cats = $DB->get_records_sql($sql);
    foreach ($cats as $cat) {
        $DB->update_record('course_categories', $cat, true);
    }

    prog_setup_initial_plan_settings();
}

/**
* This function is called both when Moodle/shezar is first installed or when
* the program module is installed into an existing shezar instance.
*
* The function adds default settings for the program component of the learning
* plans framework.
*/
function prog_setup_initial_plan_settings() {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/shezar/plan/priorityscales/lib.php');

    // retrieve all the existing templates (if any exist)
    $templates = $DB->get_records('dp_template', null, 'id', 'id');

    // Create program settings for existing templates so they don't break
    foreach ($templates as $t) {
        $transaction = $DB->start_delegated_transaction();
        if ($settings = $DB->get_record('dp_component_settings', array('templateid' => $t->id, 'component' => 'program'))) {
            $settings->enabled = 1;
            $settings->sortorder = 1 + $DB->count_records('dp_component_settings', array('templateid' => $t->id));
            $DB->update_record('dp_component_settings', $settings);
        } else {
            $settings = new stdClass();
            $settings->templateid = $t->id;
            $settings->component = 'program';
            $settings->enabled = 1;
            $settings->sortorder = 1 + $DB->count_records('dp_component_settings', array('templateid' => $t->id));
            $DB->insert_record('dp_component_settings', $settings);
        }
        $transaction->allow_commit();
    }

    // Fill in permissions and settings for programs in existing templates
    if (is_array($templates)) {
        $roles = array('learner','manager');
        $actions=array('updateprogram','setpriority','setduedate');

        $defaultduedatemode = DP_DUEDATES_OPTIONAL;
        $defaultprioritymode = DP_PRIORITY_NONE;
        if (!$defaultpriorityscaleid = dp_priority_default_scale_id()) {
            $defaultpriorityscaleid = 0;
        }

        $action_values = array(
            'learner' => array(
                'updateprogram' => DP_PERMISSION_REQUEST,
                'setpriority' => DP_PERMISSION_DENY,
                'setduedate' => DP_PERMISSION_DENY),
            'manager' => array(
                'updateprogram' => DP_PERMISSION_APPROVE,
                'setpriority' => DP_PERMISSION_ALLOW,
                'setduedate' => DP_PERMISSION_ALLOW));

        foreach ($templates as $t) {
            $transaction = $DB->start_delegated_transaction();

            $perm = new stdClass();
            $perm->templateid = $t->id;
            foreach ($action_values as $role => $actions) {
                foreach ($actions as $action => $permissionvalue) {
                    if ($rec = $DB->get_record_select('dp_permissions',
                                                         "templateid = ? AND role = ? AND component = 'program' AND action = ?",
                    array($perm->templateid, $role, $action))) {
                        $rec->value=$permissionvalue;
                        $DB->update_record('dp_permissions', $rec);
                    } else {
                        $perm->role = $role;
                        $perm->action = $action;
                        $perm->value = $permissionvalue;
                        $perm->component = 'program';
                        $DB->insert_record('dp_permissions', $perm);
                    }
                }
            }
            if ($progset = $DB->get_record_select('dp_program_settings', "templateid = ?", array($t->id))) {
                $progset->duedatemode = $defaultduedatemode;
                $progset->prioritymode = $defaultprioritymode;
                $progset->priorityscale = $defaultpriorityscaleid;
                $DB->update_record('dp_program_settings', $progset);
            } else {
                $progset = new stdClass();
                $progset->templateid = $t->id;
                $progset->duedatemode = $defaultduedatemode;
                $progset->prioritymode = $defaultprioritymode;
                $progset->priorityscale = $defaultpriorityscaleid;
                $DB->insert_record('dp_program_settings', $progset);
            }
            $transaction->allow_commit();
        }
    }
}
