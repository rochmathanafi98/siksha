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

require_once(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/shezar/core/dialogs/dialog_content_hierarchy.class.php');

require_once($CFG->dirroot.'/shezar/hierarchy/prefix/competency/lib.php');
require_once($CFG->dirroot.'/shezar/hierarchy/prefix/position/lib.php');
require_once($CFG->dirroot.'/shezar/core/js/lib/setup.php');

position::check_feature_enabled();

// Page title
$pagetitle = 'assigncompetencies';

///
/// Params
///

// Assign to id
$assignto = required_param('assignto', PARAM_INT);

// Parent id
$parentid = optional_param('parentid', 0, PARAM_INT);

// Framework id
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);

// Only return generated tree html
$treeonly = optional_param('treeonly', false, PARAM_BOOL);

// No javascript parameters
$nojs = optional_param('nojs', false, PARAM_BOOL);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$s = optional_param('s', '', PARAM_TEXT);

// Check if Competencies are enabled.
if (shezar_feature_disabled('competencies')) {
    echo html_writer::tag('div', get_string('competenciesdisabled', 'shezar_hierarchy'), array('class' => 'notifyproblem'));
    die();
}

// string of params needed in non-js url strings
$urlparams = array('assignto' => $assignto, 'frameworkid' => $frameworkid, 'nojs' => $nojs, 'returnurl' => $returnurl, 's' => $s);

///
/// Permissions checks
///

// Setup page
admin_externalpage_setup('positionmanage');


// Load currently assigned competencies
$position = new position();     // Used to determine the currently-assigned competencies
$currentlyassigned = $position->get_assigned_competencies($assignto, $frameworkid);
if (!is_array($currentlyassigned)) {
    $currentlyassigned = array();
}

///
/// Display page
///

if (!$nojs) {
    // Load dialog content generator
    $dialog = new shezar_dialog_content_hierarchy_multi('competency', $frameworkid);

    // Toggle treeview only display
    $dialog->show_treeview_only = $treeonly;

    // Load items to display
    $dialog->load_items($parentid);

    // Set disabled items
    $dialog->disabled_items = $currentlyassigned;

    // Set title
    $dialog->selected_title = 'itemstoadd';
    $dialog->selected_items = $currentlyassigned;

    // Additional url parameters
    $dialog->urlparams = array('assignto' => $assignto);

    // Display
    echo $dialog->generate_markup();

} else {
    // non JS version of page
    // Check permissions
    $sitecontext = context_system::instance();
    require_capability('shezar/hierarchy:updateposition', $sitecontext);

    // Setup hierarchy objects
    $hierarchy = new competency();

    // Load framework
    if (!$framework = $hierarchy->get_framework($frameworkid)) {
        print_error('competencyframeworknotfound', 'shezar_hierarchy');
    }

    // Load competencies to display
    $competencies = $hierarchy->get_items_by_parent($parentid);

    echo $OUTPUT->header();
    $out = html_writer::tag('h2', get_string('assigncompetency', 'shezar_hierarchy'));
    $link = html_writer::link($returnurl, get_string('cancelwithoutassigning','shezar_hierarchy'));
    $out .= html_writer::tag('p', $link);

    if (empty($frameworkid) || $frameworkid == 0) {

        echo build_nojs_frameworkpicker(
            $hierarchy,
            '/shezar/hierarchy/prefix/position/assigncompetency/find.php',
            array(
                'returnurl' => $returnurl,
                's' => $s,
                'nojs' => 1,
                'assignto' => $assignto,
                'frameworkid' => $frameworkid,
            )
        );

    } else {
        $out .= html_writer::start_tag('div', array('id' => 'nojsinstructions'));
        $out .= build_nojs_breadcrumbs($hierarchy,
            $parentid,
            '/shezar/hierarchy/prefix/position/assigncompetency/find.php',
            array(
                'assignto' => $assignto,
                'returnurl' => $returnurl,
                's' => $s,
                'nojs' => $nojs,
                'frameworkid' => $frameworkid,
            )
        );
        $out .= html_writer::tag('p', get_string('clicktoassign', 'shezar_hierarchy') . ' ' . get_string('clicktoviewchildren', 'shezar_hierarchy'));
        $out .= html_writer::end_tag('div');

        $out .= html_writer::start_tag('div', array('class' => 'nojsselect'));
        $out .= build_nojs_treeview(
            $competencies,
            get_string('nochildcompetenciesfound', 'shezar_hierarchy'),
            '/shezar/hierarchy/prefix/position/assigncompetency/assign.php',
            array(
                's' => $s,
                'returnurl' => $returnurl,
                'nojs' => 1,
                'frameworkid' => $frameworkid,
                'assignto' => $assignto,
            ),
            '/shezar/hierarchy/prefix/position/assigncompetency/find.php',
            $urlparams,
            $hierarchy->get_all_parents()
        );
        $out .= html_writer::end_tag('div');
    }
    echo $out;
    echo $OUTPUT->footer();
}
