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
 * @author David Curry <david.curry@shezarlms.com>
 * @package shezar
 * @subpackage shezar_feedback360
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/shezar/feedback360/feedback360_forms.php');
require_once($CFG->dirroot . '/shezar/feedback360/lib.php');
require_once($CFG->dirroot . '/shezar/message/messagelib.php');

// Check if 360 Feedbacks are enabled.
feedback360::check_feature_enabled();

$userid = required_param('userid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

$systemcontext = context_system::instance();
$usercontext = context_user::instance($userid);
$strrequestfeedback = get_string('requestfeedback360', 'shezar_feedback360');

// Set up the page.
$PAGE->set_url(new moodle_url('/shezar/feedback360/index.php'));
$PAGE->set_context($systemcontext);
$PAGE->set_focuscontrol('');
$PAGE->set_cacheable(true);

// Check user has permission to request feedback, and set up the page.
$owner = $DB->get_record('user', array('id' => $userid));
if ($USER->id == $userid) {
    require_capability('shezar/feedback360:manageownfeedback360', $systemcontext);
    $asmanager = false;

    $strmyfeedback = get_string('myfeedback', 'shezar_feedback360');
    $PAGE->set_shezar_menu_selected('feedback360');
    $PAGE->navbar->add(get_string('feedback360', 'shezar_feedback360'), new moodle_url('/shezar/feedback360/index.php'));
    $PAGE->navbar->add($strmyfeedback);
    $PAGE->set_title($strrequestfeedback);
    $PAGE->set_heading($strrequestfeedback);
} else if (\shezar_job\job_assignment::is_managing($USER->id, $userid)) {
    require_capability('shezar/feedback360:managestafffeedback', $usercontext);
    $asmanager = true;

    $userxfeedback = get_string('userxfeedback360', 'shezar_feedback360', fullname($owner));
    if (shezar_feature_visible('myteam')) {
        $PAGE->set_shezar_menu_selected('myteam');
        $PAGE->navbar->add(get_string('team', 'shezar_core'), new moodle_url('/my/teammembers.php'));
    }
    $PAGE->navbar->add($userxfeedback);
    $PAGE->set_title($userxfeedback);
    $PAGE->set_heading($userxfeedback);
} else {
    print_error('error:accessdenied', 'shezar_feedback');
}

$PAGE->navbar->add($strrequestfeedback);

// Set up the javascript for the page.
require_once($CFG->dirroot.'/shezar/core/js/lib/setup.php');

// Setup lightbox.
local_js(array(
    shezar_JS_DIALOG,
    shezar_JS_TREEVIEW
));

$PAGE->requires->js('/shezar/feedback360/js/preview.js', false);

// Set up the forms based off of the action.
if ($action == 'form') {
    $available_forms = feedback360::get_available_forms($userid);

    if (count($available_forms) == 0) {
        print_error('noavailableforms', 'shezar_feedback360');
    } else if (count($available_forms) == 1) {
        $form = array_pop($available_forms);
        redirect("{$CFG->wwwroot}/shezar/feedback360/request.php?userid={$userid}&action=users&formid={$form->assigid}");
    } else {
        $mform = new request_select_form();
        $data = array();
        $data['userid'] = $userid;
        $mform->set_data($data);
    }
} else if ($action == 'users') {
    $formid = required_param('formid', PARAM_INT); // Note: formid is actually feedback360_user_assignment.id.
    $update = optional_param('update', 0, PARAM_INT);
    $selected = optional_param('selected', '', PARAM_SEQUENCE);
    $nojs = optional_param('nojs', false, PARAM_BOOL);

    $params = array('feedback360userassignmentid' => $formid);

    $userassignment = $DB->get_record('feedback360_user_assignment', array('id' => $formid));
    $respassignments = $DB->get_records('feedback360_resp_assignment', array('feedback360userassignmentid' => $formid));
    $feedback360 = $DB->get_record('feedback360', array('id' => $userassignment->feedback360id));

    if ($feedback360->status != feedback360::STATUS_ACTIVE) {
        print_error('error:feedbacknotactive', 'shezar_feedback360');
    }

    $data = array();
    $data['userid'] = $userid;
    $data['formid'] = $formid;
    $data['emailexisting'] = array();
    $data['systemexisting'] = array();
    $data['feedbackid'] = $feedback360->id;
    $data['feedbackname'] = format_string($feedback360->name);
    $data['anonymous'] = $feedback360->anonymous;
    $data['duedate'] = $userassignment->timedue;
    $data['update'] = $update;

    if ($nojs) {
        // If no js then populate systemnew so we can add/remove requests.
        $data['systemnew'] = $selected;
        $data['nojs'] = true;
    }

    // Incase we are editing get all existing user_resp.
    foreach ($respassignments as $respassignment) {
        if (!empty($respassignment->feedback360emailassignmentid)) {
            $email = $DB->get_field('feedback360_email_assignment', 'email',
                    array('id' => $respassignment->feedback360emailassignmentid));
            $data['emailexisting'][$respassignment->feedback360emailassignmentid] = $email;
        } else {
            $user = $DB->get_record('user', array('id' => $respassignment->userid));
            $data['systemexisting'][$respassignment->userid] = $user;
        }
    }

    $existing = !empty($data['systemexisting']) ? implode(',', array_keys($data['systemexisting'])) : '';

    $args = array('args'=>'{"userid":' . $userid . ','
                         . '"formid":' . $formid . ','
                         . '"existing":"{' . $existing .'}",'
                         . '"sesskey":"' . sesskey() . '"}'
                 );

    $PAGE->requires->strings_for_js(array('addsystemusers'), 'shezar_feedback360');

    // Include position user js modules.
    $jsmodule = array('name' => 'shezar_requestfeedback',
                      'fullpath' => '/shezar/feedback360/js/request.js',
                      'requires' => array('json')
                     );
    $PAGE->requires->js('/shezar/feedback360/js/delete.js', false);
    $PAGE->requires->js_init_call('M.shezar_requestfeedback.init', $args, false, $jsmodule);

    $mform = new request_select_users();
    $mform->set_data($data);
} else if ($action == 'confirm') {
    $systemnew = required_param('systemnew', PARAM_SEQUENCE);
    $systemcancel = required_param('systemcancel', PARAM_SEQUENCE);
    $systemkeep = required_param('systemkeep', PARAM_SEQUENCE);
    $emailnew = required_param('emailnew', PARAM_TEXT);
    $emailcancel = required_param('emailcancel', PARAM_TEXT);
    $emailkeep = required_param('emailkeep', PARAM_TEXT);
    $formid = required_param('formid', PARAM_INT);
    $newduedate = required_param('duedate', PARAM_INT);
    $oldduedate = required_param('oldduedate', PARAM_INT);
    $mform = new request_confirmation();

    $data = array();
    $data['userid'] = $userid;
    $data['systemnew'] = $systemnew;
    $data['systemkeep'] = $systemkeep;
    $data['systemcancel'] = $systemcancel;
    $data['emailnew'] = $emailnew;
    $data['emailcancel'] = $emailcancel;
    $data['emailkeep'] = $emailkeep;
    $data['formid'] = $formid;
    $data['oldduedate'] = $oldduedate;
    $data['newduedate'] = $newduedate;
    $data['strings'] = '';

    $mform->set_data($data);
} else {
    print_error('error:unrecognisedaction', 'shezar_feedback360', null, $action);
}

// Handle forms being submitted.
if ($mform->is_cancelled()) {
    $cancelurl = new moodle_url('/shezar/feedback360/index.php', array('userid' => $userid));
    redirect($cancelurl);
} else if ($data = $mform->get_data()) {
    if ($action == 'form') {
        $params = array('userid' => $userid, 'action' => 'users', 'formid' => $data->formid);
        $url = $CFG->wwwroot . '/shezar/feedback360/request.php';
        redirect(new moodle_url($url, $params));
    } else if ($action == 'users') {
        // Check for the nojs submit button and redirect to the find page, not elegant but looks like the only way.
        if (!empty($data->addsystemusers)) {
            $findparams = array('userid' => $userid, 'selected' => $data->systemnew, 'nojs' => true, 'formid' => $formid);
            $findurl = '/shezar/feedback360/request/find.php';
            redirect(new moodle_url($findurl, $findparams));
        }

        $newsystem = array();
        if (!empty($data->systemnew)) {
            $newsystem = explode(',', $data->systemnew);
        }

        $cancelsystem = array();
        $keepsystem = array();
        if (!empty($data->systemold)) {
            $oldsystem = explode(',', $data->systemold);
            foreach ($oldsystem as $olduser) {
                if (in_array($olduser, $newsystem)) {
                    $keepsystem[] = $olduser;
                    $newsystem = array_diff($newsystem, array($olduser));
                } else {
                    $cancelsystem[] = $olduser;
                }
            }
        }

        // Include the list of all external emails.
        $newemail = array();
        if (!empty($data->emailnew)) {
            $newemail = explode("\r\n", $data->emailnew);
        }

        // Show cancellations.
        $cancelemail = array();
        $keepemail = array();
        if (!empty($data->emailcancel)) {
            $cancelemail = explode(',', $data->emailcancel);
        }
        if (!empty($data->emailold)) {
            $oldemail = explode(',', $data->emailold);
            foreach ($oldemail as $email) {
                if (!in_array($email, $cancelemail)) {
                    $keepemail[] = $email;
                }
            }
        }

        $oldduedate = !empty($data->oldduedate) ? $data->oldduedate : 0;
        $newduedate = !empty($data->duedate) ? $data->duedate : 0;

        if (!empty($newsystem) || !empty($cancelsystem) || !empty($newemail) || !empty($cancelemail) ||
                $data->duedate != $data->oldduedate) {
            $params = array('userid' => $data->userid,
                'action' => 'confirm',
                'formid' => $data->formid,
                'systemnew' => implode(',', $newsystem),
                'systemkeep' => implode(',', $keepsystem),
                'systemcancel' => implode(',', $cancelsystem),
                'emailnew' => implode(',', $newemail),
                'emailkeep' => implode(',', $keepemail),
                'emailcancel' => implode(',', $cancelemail),
                'duedate' => $data->duedate,
                'oldduedate' => $data->oldduedate,
            );

            $url = new moodle_url('/shezar/feedback360/request.php', $params);
            redirect($url);
        } else {
            $params = array('userid' => $data->userid,
                'formid' => $data->formid,
                'action' => 'users'
            );

            $url = new moodle_url('/shezar/feedback360/request.php', $params);

            shezar_set_notification('No changes to be made', $url, array('class' => 'notifysuccess'));
        }
    } else if ($action == 'confirm') {
        // Update the timedue in the user_assignment.
        feedback360_responder::update_timedue($data->duedate, $data->formid);

        if ($data->duenotifications) {
            // Set up some variables for use lower down in the send update notification loops.
            $userfrom = $DB->get_record('user', array('id' => $USER->id));
            $user_assignment = $DB->get_record('feedback360_user_assignment', array('id' => $data->formid));
            $feedback360 = $DB->get_record('feedback360', array('id' => $user_assignment->feedback360id));

            $strvars = new stdClass();
            $strvars->userfrom = fullname($userfrom);
            $strvars->feedbackname = $feedback360->name;
            $strvars->timedue = userdate($data->duedate, get_string('strftimedatetime'));

            if ($asmanager) {
                $staffmember = $DB->get_record('user', array('id' => $data->userid));
                $strvars->staffname = fullname($staffmember);
            }
        } else {
            $strvars = $userfrom = null;
        }

        // Randomly select whether to handle system or email responses first.
        // This is necessary to ensure response ids can't be used to identify
        // anonymous users in the situation where there are multiple responders
        // but only one system or email user.
        if (mt_rand(0, 1) == 1) {
            feedback360_responder::update_and_notify_system($data, $asmanager, $userfrom, $strvars);
            feedback360_responder::update_and_notify_email($data, $asmanager, $userfrom, $strvars);
        } else {
            feedback360_responder::update_and_notify_email($data, $asmanager, $userfrom, $strvars);
            feedback360_responder::update_and_notify_system($data, $asmanager, $userfrom, $strvars);
        }

        // Redirect to the myfeedback360 page with a success notification.
        if (empty($emailkeep) && empty($emailcancel) && empty($systemkeep) && empty($systemcancel)) {
            $successstr = get_string('requestcreatedsuccessfully', 'shezar_feedback360');
        } else {
            $successstr = get_string('requestupdatedsuccessfully', 'shezar_feedback360');
        }
        $returnurl = new moodle_url('/shezar/feedback360/index.php', array('userid' => $userid));
        shezar_set_notification($successstr, $returnurl, array('class' => 'notifysuccess'));
    } else {
        print_error('error:unrecognisedaction', 'shezar_feedback360', null, $action);
    }
}

$renderer = $PAGE->get_renderer('shezar_feedback360');

echo $renderer->header();

echo $renderer->display_userview_header($owner);

$mform->display();

echo $renderer->footer();
