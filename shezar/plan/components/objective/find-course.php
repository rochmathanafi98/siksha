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
 * @author Peter Bulmer <peterb@catalyst.net.nz>
 * @package shezar
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot.'/shezar/plan/components/course/dialog_content_linked_courses.class.php');
require_once($CFG->dirroot.'/shezar/plan/lib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

require_login();

///
/// Setup / loading data
///

$planid = required_param('planid', PARAM_INT);
$objectiveid = required_param('objectiveid', PARAM_INT);

///
/// Load plan
///
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('shezar/plan:accessplan', $context);

$plan = new development_plan($planid);
$component = $plan->get_component('objective');
$linkedcourses = $component->get_linked_components($objectiveid, 'course');
$selected = array();
if (!empty($linkedcourses)) {
    list($insql, $params) = $DB->get_in_or_equal($linkedcourses);
    $sql = "SELECT ca.id, c.fullname, c.sortorder
            FROM {dp_plan_course_assign} ca
            INNER JOIN {course} c ON ca.courseid = c.id
            WHERE ca.id $insql
            ORDER BY c.fullname, c.sortorder";
    $selected = $DB->get_records_sql($sql, $params);
}
// Access control check
if (!$permission = $component->can_update_items()) {
    print_error('error:cannotupdatecourses', 'shezar_plan');
}


///
/// Setup dialog
///

// Load dialog content generator
$dialog = new shezar_dialog_linked_courses_content_courses();

// Set type to multiple
$dialog->type = shezar_dialog_content::TYPE_CHOICE_MULTI;
$dialog->selected_title = 'itemstoadd';

// Add data
$dialog->load_courses_from_plan($planid);

// Set selected items
$dialog->selected_items = $selected;

// Display page
echo $dialog->generate_markup();
