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
 * @author Simon Coggins <simon.coggins@shezarlms.com>
 * @package shezar
 * @subpackage shezar_hierarchy
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/shezar/hierarchy/item/bulkactions_form.php');
require_once($CFG->dirroot.'/shezar/hierarchy/lib.php');
require_once($CFG->dirroot.'/shezar/core/searchlib.php');
require_once($CFG->dirroot.'/shezar/core/utils.php');
require_once($CFG->dirroot.'/shezar/core/js/lib/setup.php');

local_js(array(shezar_JS_PLACEHOLDER));

///
/// Setup / loading data
///

$prefix = required_param('prefix', PARAM_ALPHA);
$shortprefix = hierarchy::get_short_prefix($prefix);

$frameworkid = required_param('frameworkid', PARAM_INT);
$action      = required_param('action', PARAM_ALPHA);
$apage       = optional_param('apage', 0, PARAM_INT);
$spage       = optional_param('spage', 0, PARAM_INT);
$confirmdelete = optional_param('confirmdelete', 0, PARAM_INT);
$confirmmove = optional_param('confirmmove', 0, PARAM_INT);
$newparent   = optional_param('newparent', false, PARAM_INT);

// Check hierarchy item is enabled.
hierarchy::check_enable_hierarchy($prefix);

$hierarchy = hierarchy::load_hierarchy($prefix);

define('HIERARCHY_BULK_SELECTED_PER_PAGE', 1000);
define('HIERARCHY_BULK_AVAILABLE_PER_PAGE', 1000);

$soffset = $spage * HIERARCHY_BULK_SELECTED_PER_PAGE;
$aoffset = $apage * HIERARCHY_BULK_AVAILABLE_PER_PAGE;

// Make this page appear under the manage competencies admin item
admin_externalpage_setup($prefix.'manage', '', array('prefix' => $prefix));

$context = context_system::instance();

if ($action == 'delete') {
    require_capability('shezar/hierarchy:delete'.$prefix, $context);
} else {
    require_capability('shezar/hierarchy:update'.$prefix, $context);
}

// Load framework
$framework = $hierarchy->get_framework($frameworkid);

// Load selected data from the session for this form
$all_selected_item_ids =
    isset($SESSION->hierarchy_bulk_items[$action][$prefix][$frameworkid]) ?
    $SESSION->hierarchy_bulk_items[$action][$prefix][$frameworkid] : array();

// same as selected, plus all their children

list($selecteditemssql, $selecteditemsparams) = sql_sequence('id', $all_selected_item_ids);
$paths = $DB->get_fieldset_select($shortprefix, 'path', $selecteditemssql, $selecteditemsparams);
$where = array();
$whereparams = array();
foreach ($paths as $path) {
    $where[] = 'path = ?';
    $whereparams[] = $path;
    $where[] = $DB->sql_like('path', '?');
    $whereparams[] = "$path/%";
}

if (count($paths)) {
    $all_disabled_item_ids = $DB->get_fieldset_select($shortprefix, 'id', implode(' OR ', $where), $whereparams);
} else {
    $all_disabled_item_ids = array();
}

$count_selected_items = count($all_selected_item_ids);

// Load current search from the session
$searchterm = isset($SESSION->hierarchy_bulk_search[$action][$prefix][$frameworkid]) ?
    $SESSION->hierarchy_bulk_search[$action][$prefix][$frameworkid] : '';

$searchquery = '';
$searchqueryparams = array();
if ($searchterm) {
    $keywords = shezar_search_parse_keywords($searchterm);
    list($searchquery, $searchqueryparams) =
        shezar_search_get_keyword_where_clause($keywords, array('fullname'), SQL_PARAMS_QM, 'search');
    $searchquery = ' AND '.$searchquery;
}

$count_available_items = $DB->count_records_select($shortprefix, 'frameworkid = ?' . $searchquery, array_merge(array($frameworkid), $searchqueryparams));

// if page has no results, show last page that did have results
// this is required in the case where a user removes all items
// from the last page of selected list
// without this, it will show the empty page with no pagination
if ($count_available_items > 0 && $aoffset >= $count_available_items) {
    $apage = (int) floor($count_available_items / HIERARCHY_BULK_AVAILABLE_PER_PAGE) - 1;
    $aoffset = $apage * HIERARCHY_BULK_AVAILABLE_PER_PAGE;
}
if ($count_selected_items > 0 && $soffset >= $count_selected_items) {
    $spage = (int) floor($count_selected_items / HIERARCHY_BULK_SELECTED_PER_PAGE) - 1;
    $soffset = $spage * HIERARCHY_BULK_SELECTED_PER_PAGE;
}

// display the selected items, including any children they have
list($selectedsql, $selectedparams) = sql_sequence('h.id', $all_selected_item_ids);
// add the parameter for the like at the start
array_unshift($selectedparams, $DB->sql_concat('h.path', "'/%'"));
$sql = "SELECT h.id, h.fullname, count(hh.id) AS children
    FROM {{$shortprefix}} h
    LEFT JOIN {{$shortprefix}} hh
    ON " . $DB->sql_like('hh.path', '?') . "
    WHERE " . $selectedsql . "
    GROUP BY h.id, h.fullname, h.sortthread
    ORDER BY h.sortthread";
if ($selected_items = $DB->get_records_sql($sql, $selectedparams, $soffset, HIERARCHY_BULK_SELECTED_PER_PAGE)) {

    $displayed_selected_items = array();
    foreach ($selected_items as $id => $item) {
        if ($item->children == 0) {
            $displayed_selected_items[$id] = $item->fullname;
        } else {
            $a = new stdClass();
            $a->item = $item->fullname;
            $a->num = $item->children;
            $langstr = $item->children == 1 ? 'xandychild' : 'xandychildren';
            $displayed_selected_items[$id] = get_string($langstr, 'shezar_hierarchy', $a);
        }
    }
} else {
    $displayed_selected_items = array();
}

$available_items = $DB->get_records_select($shortprefix,
    'frameworkid = ?'. $searchquery, array_merge(array($frameworkid), $searchqueryparams), 'sortthread', 'id,fullname,depthlevel',
    $aoffset, HIERARCHY_BULK_AVAILABLE_PER_PAGE);
$available_items = ($available_items) ?
    $available_items : array();

// indent based on item depthlevel
$displayed_available_items = array();
foreach ($available_items as $item) {
    $displayed_available_items[$item->id] = (strlen(trim($searchterm)) == 0) ?
        str_repeat('&nbsp;', 4 * ($item->depthlevel - 1)) . $item->fullname : $item->fullname;
}


///
/// Display page
///

// create form
$mform = new item_bulkaction_form(null, compact('prefix', 'action', 'frameworkid',
    'apage', 'spage', 'displayed_available_items', 'displayed_selected_items',
    'all_selected_item_ids', 'count_available_items', 'count_selected_items',
    'searchterm', 'framework', 'all_disabled_item_ids'));

// return to the bulk actions form (when still working on form)
$formparams = array('prefix' => $prefix, 'action' => $action, 'frameworkid' => $frameworkid, 'page' => $apage, 'spage' => $spage);
$formurl = new moodle_url('/shezar/hierarchy/item/bulkactions.php', $formparams);
// return to the hierarchy index page (when form is done)
$returnurl = new moodle_url('/shezar/hierarchy/index.php', array('prefix' => $prefix, 'frameworkid' => $frameworkid));


// confirm item deletion
if ($confirmdelete) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    $unique_ids = $hierarchy->get_items_excluding_children($all_selected_item_ids);
    $status = true;
    $deleted = array();
    foreach ($unique_ids as $item_to_delete) {
        if ($hierarchy->delete_hierarchy_item($item_to_delete)) {
            $deleted[] = $item_to_delete;
        } else {
            $status = false;
        }
    }
    $deletecount = count($deleted);

    // empty form SESSION data
    $SESSION->hierarchy_bulk_items[$action][$prefix][$frameworkid] = array();
    $SESSION->hierarchy_bulk_search[$action][$prefix][$frameworkid] = '';

    $items = (count($unique_ids) == 1) ?
        strtolower(get_string($prefix, 'shezar_hierarchy')) :
        strtolower(get_string($prefix . 'plural', 'shezar_hierarchy'));
    if ($status) {
        $a = new stdClass();
        $a->num = count($unique_ids);
        $a->items = $items;
        $message = get_string('xitemsdeleted', 'shezar_hierarchy', $a);
        shezar_set_notification($message, $returnurl,
            array('class' => 'notifysuccess'));
    } else if ($deletecount == 0) {
        $message = get_string('error:nonedeleted', 'shezar_hierarchy', $items);
        shezar_set_notification($message, $formurl);
    } else {
        $a = new stdClass();
        $a->actually_deleted = $deletecount;
        $a->marked_for_deletion = count($unique_ids);
        $a->items = $items;
        $message = get_string('error:somedeleted', 'shezar_hierarchy', $a);
        shezar_set_notification($message, $returnurl);
    }

}


// confirm item move
if ($confirmmove && $newparent !== false) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }
    $unique_ids = $hierarchy->get_items_excluding_children($all_selected_item_ids);

    $status = true;
        $transaction = $DB->start_delegated_transaction();

        list($itemssql, $itemsparams) = sql_sequence('id', $unique_ids);
        $items_to_move = $DB->get_records_select($shortprefix, $itemssql, $itemsparams);
        foreach ($items_to_move as $item_to_move) {
            $status = $status && $hierarchy->move_hierarchy_item($item_to_move, $frameworkid, $newparent);
        }
        if (!$status) {
            shezar_set_notification(get_string('error:failedbulkmove', 'shezar_hierarchy'), $formurl);
        }
        $transaction->allow_commit();

    // empty form SESSION data
    $SESSION->hierarchy_bulk_items[$action][$prefix][$frameworkid] = array();
    $SESSION->hierarchy_bulk_search[$action][$prefix][$frameworkid] = '';

    $a = new stdClass();
    $a->num = count($unique_ids);
    $a->items = ($a->num == 1) ? strtolower(get_string($prefix, 'shezar_hierarchy')) :
        strtolower(get_string($prefix . 'plural', 'shezar_hierarchy'));

    shezar_set_notification(get_string('xitemsmoved', 'shezar_hierarchy', $a),
        $returnurl, array('class' => 'notifysuccess'));
}

$PAGE->navbar->add(get_string("{$prefix}frameworks", 'shezar_hierarchy'), new moodle_url('/shezar/hierarchy/framework/index.php', array('prefix' => $prefix)));
$PAGE->navbar->add(format_string($framework->fullname), new moodle_url('/shezar/hierarchy/index.php', array('prefix' => $prefix, 'frameworkid' => $framework->id)));
$PAGE->navbar->add(get_string('bulk'.$action.$prefix, 'shezar_hierarchy'));

// Handling actions from the main form

// cancelled
if ($mform->is_cancelled()) {

    redirect($returnurl);

// Update data
} else if ($formdata = $mform->get_data()) {

    // items added
    if (isset($formdata->add_items)) {
        if (!isset($formdata->available)) {

            shezar_set_notification(get_string('error:noitemsselected', 'shezar_hierarchy'), $formurl);
        }
        // add selected items to the SESSION, and redirect back to page
        // only include the parent as all children are automatically included
        $to_be_added = $hierarchy->get_items_excluding_children($formdata->available);
        foreach ($to_be_added as $added_item) {
            if (!in_array($added_item, $all_selected_item_ids)) {
                $SESSION->hierarchy_bulk_items[$action][$prefix][$frameworkid][] = $added_item;
            }
        }
    }

    // items removed
    if (isset($formdata->remove_items)) {
        if (!isset($formdata->selected)) {
            shezar_set_notification(get_string('error:noitemsselected', 'shezar_hierarchy'), $formurl);
        }
        // remove selected items to the SESSION, and redirect back to page
        foreach ($formdata->selected as $removed_item) {
            if (($key = array_search($removed_item, $all_selected_item_ids)) !== false) {
                unset($SESSION->hierarchy_bulk_items[$action][$prefix][$frameworkid][$key]);
            }
        }
    }

    // remove all
    if (isset($formdata->remove_all_items)) {
        $SESSION->hierarchy_bulk_items[$action][$prefix][$frameworkid] = array();
    }

    // add all
    if (isset($formdata->add_all_items)) {
        if ($all_records = $DB->get_records($shortprefix, array('frameworkid' => $frameworkid), 'sortthread', 'id')) {

            $SESSION->hierarchy_bulk_items[$action][$prefix][$frameworkid] =
                array_keys($all_records);
        }

    }

    // search
    if (isset($formdata->search)) {
        $SESSION->hierarchy_bulk_search[$action][$prefix][$frameworkid] = $formdata->search;
    }

    // clear search (show all button)
    if (isset($formdata->clearsearch)) {
        $SESSION->hierarchy_bulk_search[$action][$prefix][$frameworkid] = '';
    }

    // delete button - confirm step
    if (isset($formdata->deletebutton)) {
        $unique_ids = $hierarchy->get_items_excluding_children($all_selected_item_ids);

        if ((count($unique_ids) > 0)) {
            echo $OUTPUT->header();
            $strdelete = $hierarchy->get_delete_message($unique_ids);
            $formparams['confirmdelete'] = 1;
            $formparams['sesskey'] = $USER->sesskey;
            echo $OUTPUT->confirm($strdelete, new moodle_url('bulkactions.php', $formparams), $formurl);
        } else {
            shezar_set_notification(get_string('error:noitemsselected', 'shezar_hierarchy'), $formurl);
        }

        echo $OUTPUT->footer();
        exit;
    }

    // move button - confirm step
    if (isset($formdata->movebutton) && isset($formdata->newparent)) {
        $unique_ids = $hierarchy->get_items_excluding_children($all_selected_item_ids);

        if (count($unique_ids) <= 0) {
            shezar_set_notification(get_string('error:noitemsselected', 'shezar_hierarchy'), $formurl);
        }

        $invalidmove = false;

        if ($formdata->newparent != 0) {
            // make sure parent is valid
            if (!$parentitem = $DB->get_record($shortprefix, array('id' => $formdata->newparent))) {
                $invalidmove = true;
                $message = get_string('error:invalidparentformove', 'shezar_hierarchy');
            }

            // check that the new parent isn't a child of any of the items being
            // moved. {@link is_child_of()} accepts an array, but we'll loop
            // through so we know which one failed
            foreach ($unique_ids as $itemid) {
                if ($hierarchy->is_child_of($parentitem, $itemid)) {
                    $invalidmove = true;
                    $a = new stdClass();
                    $a->item = format_string($DB->get_field($shortprefix, 'fullname', array('id' => $itemid)));
                    $a->newparent = format_string($parentitem->fullname);
                    $message = get_string('error:cannotmoveparentintochild', 'shezar_hierarchy', $a);
                }
            }
        }

        if ($invalidmove) {
            shezar_set_notification($message, $formurl);
        } else {
            echo $OUTPUT->header();
            $strmove = $hierarchy->get_move_message($unique_ids, $newparent);
            $formparams['newparent'] = $newparent;
            $formparams['confirmmove'] = 1;
            $formparams['sesskey'] = $USER->sesskey;
            echo $OUTPUT->confirm($strmove, new moodle_url('bulkactions.php', $formparams), $formurl);
            echo $OUTPUT->footer();
        }
        exit;
    }

    redirect($formurl);
}


/// Display page header
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('bulk'.$action.$prefix, 'shezar_hierarchy'));

/// Finally display the form
$mform->display();

echo $OUTPUT->footer();
