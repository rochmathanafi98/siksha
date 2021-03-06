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
 * @author Ben Lobo <ben.lobo@kineo.com>
 * @package shezar
 * @subpackage program
 */

/**
 * Program view/edit page
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');
require_once($CFG->dirroot . '/shezar/core/js/lib/setup.php');
require_once('edit_form.php');
require_once($CFG->dirroot . '/shezar/certification/lib.php');
require_once($CFG->dirroot . '/shezar/customfield/fieldlib.php');

$id = required_param('id', PARAM_INT); // program id
$action = optional_param('action', 'view', PARAM_TEXT);
$category = optional_param('category', '', PARAM_INT);
$nojs = optional_param('nojs', 0, PARAM_INT);

require_login();

$program = new program($id);
$iscertif = $program->is_certif();
$program->check_enabled();
$programcontext = $program->get_context();
require_capability('shezar/program:configuredetails', $programcontext);

$PAGE->set_context($programcontext);

customfield_load_data($program, 'program', 'prog');

// Redirect to delete page if deleting.
if ($action == 'delete') {
    redirect(new moodle_url('/shezar/program/delete.php', array('id' => $id, 'category' => $category)));
}

// Set type.
$instancetype = COHORT_ASSN_ITEMTYPE_PROGRAM;
if ($iscertif) {
    $instancetype = COHORT_ASSN_ITEMTYPE_CERTIF;
}

$PAGE->set_url(new moodle_url('/shezar/program/edit.php', array('id' => $id, 'action' => $action)));
$PAGE->set_title(format_string($program->fullname));
$PAGE->set_heading(format_string($program->fullname));

if ($action == 'edit') {
    // Javascript include.
    local_js(array(
        shezar_JS_DIALOG,
        shezar_JS_UI,
        shezar_JS_ICON_PREVIEW,
        shezar_JS_TREEVIEW
    ));

    $PAGE->requires->string_for_js('youhaveunsavedchanges', 'shezar_program');
    $args = array('args'=>'{"id":'.$id.'}');
    $jsmodule = array(
            'name' => 'shezar_programedit',
            'fullpath' => '/shezar/program/program_edit.js',
            'requires' => array('json'));
    $PAGE->requires->js_init_call('M.shezar_programedit.init',$args, false, $jsmodule);

    // Visible audiences.
    if (!empty($CFG->audiencevisibility)) {
        if (empty($program->id)) {
            $visibleselected = '';
        } else {
            $visibleselected = shezar_cohort_get_visible_learning($program->id, $instancetype);
            $visibleselected = !empty($visibleselected) ? implode(',', array_keys($visibleselected)) : '';
        }
        $PAGE->requires->strings_for_js(array('programcohortsvisible'), 'shezar_cohort');
        $jsmodule = array(
                        'name' => 'shezar_visiblecohort',
                        'fullpath' => '/shezar/cohort/dialog/visiblecohort.js',
                        'requires' => array('json'));
        $args = array('args'=>'{"visibleselected":"' . $visibleselected .
            '", "type":"program", "instancetype": "' . $instancetype .
            '", "instanceid": "' . $id . '"}');
        $PAGE->requires->js_init_call('M.shezar_visiblecohort.init', $args, true, $jsmodule);
        unset($visibleselected);
    }

    // Icon picker.
    $PAGE->requires->string_for_js('chooseicon', 'shezar_program');
    $iconjsmodule = array(
            'name' => 'shezar_iconpicker',
            'fullpath' => '/shezar/core/js/icon.picker.js',
            'requires' => array('json'));

    $iconargs = array('args' => '{"selected_icon":"' . $program->icon . '", "type":"program"}');

    $PAGE->requires->js_init_call('M.shezar_iconpicker.init', $iconargs, false, $iconjsmodule);
}

if (!$progcategory = $DB->get_record('course_categories', array('id' => $program->category))) {
    print_error('error:determineprogcat', 'shezar_program');
}

$currenturl = qualified_me();
$currenturl_noquerystring = strip_querystring($currenturl);
$viewurl = $currenturl_noquerystring."?id={$id}&action=view";
$editurl = $currenturl_noquerystring."?id={$id}&action=edit";

$editcontenturl = "{$CFG->wwwroot}/shezar/program/edit_content.php?id={$program->id}";
$editassignmentsurl = "{$CFG->wwwroot}/shezar/program/edit_assignments.php?id={$program->id}";
$editmessagesurl = "{$CFG->wwwroot}/shezar/program/edit_messages.php?id={$program->id}";
$editcertificationsurl = "{$CFG->wwwroot}/shezar/certification/edit_certification.php?id={$program->id}";

//set up textareas
$program->endnoteformat = FORMAT_HTML;
$program->summaryformat = FORMAT_HTML;

$editoroptions = $TEXTAREA_OPTIONS;
$editoroptions['context'] = context_program::instance($program->id);
$program = file_prepare_standard_editor($program, 'summary', $editoroptions, $editoroptions['context'],
                                          'shezar_program', 'summary', 0);

$program = file_prepare_standard_editor($program, 'endnote', $editoroptions, $editoroptions['context'],
    'shezar_program', 'endnote', 0);

$programinlist = new program_in_list($DB->get_record('prog', array('id' => $program->id)));
$overviewfiles = $programinlist->get_program_overviewfiles();

$overviewfilesoptions = prog_program_overviewfiles_options($program);
if ($overviewfilesoptions) {
    file_prepare_standard_filemanager($program, 'overviewfiles', $overviewfilesoptions, $programcontext, 'shezar_program', 'overviewfiles', 0);
}
$detailsform = new program_edit_form($currenturl,
                array('program' => $program, 'overviewfiles' => $overviewfiles, 'action' => $action, 'category' => $progcategory,
                        'editoroptions' => $TEXTAREA_OPTIONS, 'nojs' => $nojs, 'iscertif' =>  $iscertif),
                        'post', '', array('name'=>'form_prog_details'));

if ($detailsform->is_cancelled()) {
    shezar_set_notification(get_string('programupdatecancelled', 'shezar_program'), $viewurl, array('class' => 'notifysuccess'));
}



// Handle form submits
if ($data = $detailsform->get_data()) {
    if (isset($data->edit)) {
        redirect($editurl);
    } else if (isset($data->savechanges)) {
        $data->timemodified = time();
        $data->usermodified = $USER->id;

        $data->availablefrom = ($data->availablefrom) ? $data->availablefrom : 0;
        $data->availableuntil = ($data->availableuntil) ? $data->availableuntil + (DAYSECS - 1) : 0;

        $data->available = prog_check_availability($data->availablefrom, $data->availableuntil);

        // Program has moved categories.
        if ($data->category != $program->category) {
            prog_move_programs(array($program->id), $data->category);
        }

        // Save program data.
        $DB->update_record('prog', $data);

        // Program availability has changed to unavailable, we need to update the enrolments as well.
        if ($program->available == AVAILABILITY_TO_STUDENTS && $data->available == AVAILABILITY_NOT_TO_STUDENTS) {
            $program_plugin = enrol_get_plugin('shezar_program');
            prog_update_available_enrolments($program_plugin, $program->id);
        }

        $data->id = $program->id;
        customfield_save_data($data, 'program', 'prog');

        if (isset($data->savechanges)) {
            $nexturl = $viewurl;
        }

        $programcontext = context_program::instance($program->id);
        file_postupdate_standard_editor($data, 'summary', $TEXTAREA_OPTIONS, $programcontext, 'shezar_program', 'summary', 0);
        $DB->set_field('prog', 'summary', $data->summary, array('id' => $data->id));

        if ($overviewfilesoptions = prog_program_overviewfiles_options($data->id)) {
            file_postupdate_standard_filemanager($data, 'overviewfiles', $overviewfilesoptions, $programcontext, 'shezar_program', 'overviewfiles', 0);
        }

        file_postupdate_standard_editor($data, 'endnote', $TEXTAREA_OPTIONS, $programcontext, 'shezar_program', 'endnote', 0);
        $DB->set_field('prog', 'endnote', $data->endnote, array('id' => $data->id));

        // Visible audiences.
        if (!empty($CFG->audiencevisibility) && has_capability('shezar/coursecatalog:manageaudiencevisibility', $programcontext)) {
            $visiblecohorts = shezar_cohort_get_visible_learning($program->id, $instancetype);
            $visiblecohorts = !empty($visiblecohorts) ? $visiblecohorts : array();
            $newvisible = !empty($data->cohortsvisible) ? explode(',', $data->cohortsvisible) : array();
            if ($todelete = array_diff(array_keys($visiblecohorts), $newvisible)) {
                // Delete removed cohorts.
                foreach ($todelete as $cohortid) {
                    shezar_cohort_delete_association($cohortid, $visiblecohorts[$cohortid]->associd,
                                                     $instancetype, COHORT_ASSN_VALUE_VISIBLE);
                }
            }

            if ($newvisible = array_diff($newvisible, array_keys($visiblecohorts))) {
                // Add new cohort associations.
                foreach ($newvisible as $cohortid) {
                    shezar_cohort_add_association($cohortid, $program->id, $instancetype, COHORT_ASSN_VALUE_VISIBLE);
                }
            }
        }

        $other = array('certifid' => empty($program->certifid) ? 0 : $program->certifid);
        $dataevent = array('id' => $program->id, 'other' => $other);
        $event = \shezar_program\event\program_updated::create_from_data($dataevent)->trigger();

        shezar_set_notification(get_string('programdetailssaved', 'shezar_program'), $nexturl, array('class' => 'notifysuccess'));
    }

    // Reload program to reflect any changes.
    $program = new program($id);
}

// Trigger event.
$dataevent = array('id' => $program->id, 'other' => array('section' => 'general'));
$event = \shezar_program\event\program_viewed::create_from_data($dataevent)->trigger();

// Display.

$programpagelinks = '';
$pageid = 'program-overview';

if ($action == 'edit') {
    $currenttab = 'details';
    $pageid = 'program-overview-details';
} else {
    $currenttab = 'overview';
}

echo $OUTPUT->header();

echo $OUTPUT->container_start('program overview', $pageid);

echo $OUTPUT->heading(format_string($program->fullname));

$renderer = $PAGE->get_renderer('shezar_program');
// Display the current status
echo $program->display_current_status();
$exceptions = $program->get_exception_count();
require('tabs.php');

$detailsform->set_data($program);
$detailsform->display();

if ($action == 'view' && $program && has_capability('shezar/program:configuredetails', $program->get_context())) {
    $editbuttonform = new program_edit_details_button_form($editurl, array('program' => $program), 'get');
    $editbuttonform->display();
}

// Display content, assignments and messages if in view mode.
if ($action == 'view') {

    // Display the content form.
    $contentform = new program_content_nonedit_form($editcontenturl, array('program' => $program), 'get');
    $contentform->set_data($program);
    $contentform->display();

    // Display the assignments form.
    $assignmentform = new program_assignments_nonedit_form($editassignmentsurl, array('program' => $program), 'get');
    $assignmentform->set_data($program);
    $assignmentform->display();

    // Display the messages form.
    $messagesform = new program_messages_nonedit_form($editmessagesurl, array('program' => $program), 'get');
    $messagesform->set_data($program);
    $messagesform->display();

    if ($iscertif) {
        // Display the certifications form.
        $certificationsform = new program_certifications_nonedit_form($editcertificationsurl, array('program' => $program), 'get');
        $certificationsform->set_data($program);
        $certificationsform->display();
    }

    // Display the delete button form.
    if (has_capability('shezar/program:deleteprogram', $program->get_context())) {
        $deleteform = new program_delete_form($currenturl, array('program' => $program));
        $deleteform->set_data($program);
        $deleteform->display();
    }

}

if ($action == 'edit') {
    echo $renderer->get_cancel_button(array('id' => $program->id));
}

echo $OUTPUT->container_end();
echo $OUTPUT->footer();
