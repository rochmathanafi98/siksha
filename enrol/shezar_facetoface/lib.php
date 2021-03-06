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
 * @author Andrew Hancox <andrewdchancox@googlemail.com> on behalf of Synergy Learning
 * @package shezar
 * @subpackage enrol_shezar_facetoface
 */

/**
 * Face-to-Face Direct enrolment plugin.
 */
require_once($CFG->dirroot . '/mod/facetoface/lib.php');

class enrol_shezar_facetoface_plugin extends enrol_plugin {

    const SETTING_LONGTIMENOSEE = 'customint2';
    const SETTING_MAXENROLLED = 'customint3';
    const SETTING_COURSEWELCOME = 'customint4';
    const SETTING_COHORTONLY = 'customint5';
    const SETTING_NEWENROLS = 'customint6';
    const SETTING_UNENROLWHENREMOVED = 'customint7';
    const SETTING_AUTOSIGNUP = 'customint8';

    protected $lastenroller = null;
    protected $lastenrollerinstanceid = 0;
    protected $sessions = array();
    protected $removednomanager = false; // Indicates that sessions were removed from the list because user has no manager.

    /**
     * Returns optional enrolment information icons.
     *
     * This is used in course list for quick overview of enrolment options.
     *
     * We are not using single instance parameter because sometimes
     * we might want to prevent icon repetition when multiple instances
     * of one type exist. One instance may also produce several icons.
     *
     * @param array $instances all enrol instances of this type in one course
     * @return array of pix_icon
     */
    public function get_info_icons(array $instances) {
        $icons = array();
        $icons[] = new pix_icon('withoutkey', get_string('pluginname', 'enrol_shezar_facetoface'), 'enrol_shezar_facetoface');
        return $icons;
    }

    /**
     * Returns localised name of enrol instance
     *
     * @param stdClass $instance (null is accepted too)
     * @return string
     */
    public function get_instance_name($instance) {
        global $DB;

        if (empty($instance->name)) {
            if (!empty($instance->roleid) and $role = $DB->get_record('role', array('id' => $instance->roleid))) {
                $role = ' (' . role_get_name($role, context_course::instance($instance->courseid, IGNORE_MISSING)) . ')';
            } else {
                $role = '';
            }
            $enrol = $this->get_name();
            return get_string('pluginname', 'enrol_'.$enrol) . $role;
        } else {
            return format_string($instance->name);
        }
    }

    /**
     * Users enroled through this plugin can have their roles edited
     *
     * @return bool
     */
    public function roles_protected() {
        return false;
    }

    /**
     * Get the name of the enrolment plugin
     *
     * @return string
     */
    public function get_name() {
        return 'shezar_facetoface';
    }

    /**
     * Users enroled through this plugin are able to be un-enroled
     *
     * @return bool
     */
    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    /**
     * Users enroled through this plugin can be edited
     *
     * @return bool
     */
    public function allow_manage(stdClass $instance) {
        return true;
    }

    public function show_enrolme_link(stdClass $instance) {
        return ($this->can_self_enrol($instance, false) === true);
    }

    /**
     * Sets up navigation entries.
     *
     * @param stdClass $instancesnode
     * @param stdClass $instance
     * @return void
     */
    public function add_course_navigation($instancesnode, stdClass $instance) {
        if ($instance->enrol !== 'shezar_facetoface') {
             throw new coding_exception('Invalid enrol instance type!');
        }

        $context = context_course::instance($instance->courseid);
        if (has_capability('enrol/shezar_facetoface:config', $context)) {
            $managelink = new moodle_url('/enrol/shezar_facetoface/edit.php', array('courseid' => $instance->courseid, 'id' => $instance->id));
            $instancesnode->add($this->get_instance_name($instance), $managelink, navigation_node::TYPE_SETTING);
        }
    }

    /**
     * Returns edit icons for the page with list of instances
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'shezar_facetoface') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = array();

        if (has_capability('enrol/shezar_facetoface:config', $context)) {
            $editlink = new moodle_url("/enrol/shezar_facetoface/edit.php", array('courseid' => $instance->courseid, 'id' => $instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core',
                array('class' => 'iconsmall')));
        }

        return $icons;
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course
     * or null if user lacks correct capabilities.
     * @param int $courseid
     * @return null|moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/shezar_facetoface:config', $context)) {
            return null;
        }
        // Multiple instances supported - different roles with different password.
        return new moodle_url('/enrol/shezar_facetoface/edit.php', array('courseid'=>$courseid));
    }

    /**
     * Enrol user on to course using autosignup
     * @param stdClass $course course to enrol on.
     * @param array of stdClass fetched available facetofaces
     * @param array $signupparams
     * @return array joined sessions
     */
    protected function signup_shezar_facetoface_autosignup($course, $facetofaces, $signupparams) {
        global $USER;

        $sessionstojoin = enrol_shezar_facetoface_get_sessions_to_autoenrol($this, $course, $facetofaces);

        $joinedsessions = 0;
        $needapproval = false;
        foreach ($sessionstojoin as $session) {
            $facetoface = $facetofaces[$session->facetoface];

            $result = facetoface_user_import($course, $facetoface, $session, $USER->id, $signupparams);
            if ($result['result'] === true) {
                $joinedsessions++;
                if (facetoface_approval_required($facetoface)) {
                    $needapproval = true;
                }
            }
        }
       return array($joinedsessions, $needapproval);
    }

    /**
     * Check that there no errors that blocks further enroling proces
     * (not just blocks signup, but makes further signups inconsistent)
     *
     * @param stdClass $facetoface fetched face to face activity
     * @param stdClass $session fetched session
     * @param bool $selfapprovaltc Terms and Conditions agreed
     * @return boolean|string true or error message
     */
    protected function validate_shezar_facetoface_sid($facetoface, $session, $selfapprovaltc) {
        $hasselfapproval = $facetoface->approvaltype == APPROVAL_SELF;
        if ($hasselfapproval && !$selfapprovaltc) {
            return get_string('selfapprovalrequired', 'enrol_shezar_facetoface');
        }
        return true;
    }

    /**
     * Enrol user on to course using choosen session id
     *
     * @param stdClass $course course to enrol on.
     * @param array $signupparams Sign up parameters
     * @param stdClass $session fetched session
     * @param stdClass $facetoface fetched face to face activity
     * @param integer $notificationtype Notification type code
     * @param string $signupnote Session sign up note
     * @return array('result' => bool, 'message' => string)
     */
    protected function signup_shezar_facetoface_sid($course, $signupparams, $session, $facetoface, $notificationtype, $signupnote = '') {
        global $USER;

        // If multiple sessions are allowed then just check against this session.
        // Otherwise check against all sessions.
        $multisessionid = ($facetoface->multiplesessions ? $session->id : null);
        $context = context_course::instance($course->id);

        $hasselfapproval = $facetoface->approvaltype == APPROVAL_SELF;
        $managers = \shezar_job\job_assignment::get_all_manager_userids($USER->id);

        if (!facetoface_session_has_capacity($session, $context) && (!$session->allowoverbook)) {
            return array('result' => false, 'message' => get_string('sessionisfull', 'facetoface'));
        } else if (facetoface_get_user_submissions(
            $facetoface->id,
            $USER->id,
            MDL_F2F_STATUS_REQUESTED,
            MDL_F2F_STATUS_FULLY_ATTENDED,
            $multisessionid)
        ) {
            return array('result' => true, 'message' => get_string('alreadysignedup', 'facetoface'));
        } else if (facetoface_manager_needed($facetoface) && empty($managers) && !$hasselfapproval) {
            return array('result' => false, 'message' => get_string('error:manageremailaddressmissing', 'facetoface'));
        }

        $result = facetoface_user_import($course, $facetoface, $session, $USER->id, $signupparams);

        if ($result['result'] === true) {
            // Add signup note.
            if ($signupnote) {
                $signupstatus = facetoface_get_attendee($session->id, $USER->id);
                $signupdata = new stdClass();
                $signupdata->id = $signupstatus->submissionid;
                $signupdata->customfield_signupnote = $signupnote;
                customfield_save_data($signupdata, 'facetofacesignup', 'facetoface_signup');
            }

            $needapproval = false;
            if (facetoface_approval_required($facetoface)) {
                $message = get_string('bookingcompleted_approvalrequired', 'facetoface');
                $needapproval = true;
            } else {
                $message = get_string('bookingcompleted', 'facetoface');
            }

            if ($session->cntdates
                && isset($facetoface->confirmationinstrmngr)
                && !empty($facetoface->confirmationstrmngr)) {
                $message .= html_writer::start_tag('p');
                $message .= get_string('confirmationsentmgr', 'facetoface');
                $message .= html_writer::end_tag('p');
            } else if ($notificationtype != MDL_F2F_NONE) {
                $message .= html_writer::start_tag('p');
                $message .= get_string('confirmationsent', 'facetoface');
                $message .= html_writer::end_tag('p');
            }

            return array('result' => true, 'needapproval' => $needapproval, 'message' => $message);

        } else {
            if ((isset($result['conflict']) && $result['conflict']) || isset($result['result'])) {
                return array('result' => false, 'message' => $result['result']);
            } else {
                return array('result' => false, 'message' => get_string('error:problemsigningup', 'facetoface'));
            }
        }
    }

    /**
     * Enrol user on to course
     *
     * @param enrol_shezar_facetoface_plugin $instance enrolment instance
     * @param stdClass $fromform data needed for enrolment.
     * @param stdClass $course course to enrol on.
     * @param stdClass $returnurl url to redirect to on completion.
     * @return bool|array true if enrolled else error code and message
     */
    public function enrol_shezar_facetoface($instance, $fromform, $course, $returnurl) {
        global $DB, $USER;

        // Load facetofaces.
        $sessions = $this->get_enrolable_sessions($course->id);
        $f2fids = array();
        foreach ($sessions as $session) {
            $f2fids[$session->facetoface] = $session->facetoface;
        }

        if (count($f2fids) === 0) {
            if ($this->removednomanager) {
                print_error('managermissingallsessions', 'enrol_shezar_facetoface', $returnurl);
            }
            print_error('cannotenrolnosessions', 'enrol_shezar_facetoface', $returnurl);
        }

        list($idin, $params) = $DB->get_in_or_equal($f2fids);
        $facetofaces = $DB->get_records_select('facetoface', "id $idin", $params);

        $enrol = false;
        $notificationtype = $fromform->notificationtype;
        $timestart = time();
        if ($instance->enrolperiod) {
            $timeend = $timestart + $instance->enrolperiod;
        } else {
            $timeend = 0;
        }

        $needapproval = null;
        // Check autosignup.
        $autosignup = $instance->{self::SETTING_AUTOSIGNUP};
        if (!empty($autosignup)) {
            $signupparams = array();
            $signupparams['notificationtype'] = $notificationtype;
            $signupparams['autoenrol'] = false;

            list($joinedsessions, $needapproval) = $this->signup_shezar_facetoface_autosignup($course, $facetofaces, $signupparams);

            // Initial code ignored if user didn't join any session. Maintain this behaviour.
            if ($needapproval) {
                $message = get_string('bookingcompleted_approvalrequired', 'facetoface');
            } else {
                $enrol = true;
                $message = get_string('autobookingcompleted', 'enrol_shezar_facetoface', $joinedsessions);
            }

        } else {
            // No autosignup, use user submitted session ids.
            $sids = empty($fromform->sid) ? array() : $fromform->sid;
            $sids = array_filter($sids);

            // Check for enrol blockers (for example not signed t&c).
            foreach ($sids as $sid) {
                $selfapprovaltc = empty($fromform->authorisation) ? false : $fromform->authorisation;
                $result = $this->validate_shezar_facetoface_sid($facetofaces[$session->facetoface], $sessions[$sid], $selfapprovaltc);
                if ($result !== true) {
                    // Show error and redirect.
                    shezar_set_notification($result, $returnurl);
                }
            }

            // Try to signup to all sessions (we need at least one to enrol).
            $message = '';
            foreach ($sids as $sid) {
                $signupparams = array();
                $signupparams['discountcode'] = empty($fromform->{'discountcode' . $sid}) ? '' : $fromform->{'discountcode' . $sid};
                $signupparams['notificationtype'] = $notificationtype;
                $signupparams['autoenrol'] = false;
                $signupnote = empty($fromform->{'signupnote' . $sid}) ? '' : $fromform->{'signupnote' . $sid};

                if (empty($signupnote)) {
                    // If this method was called by a signup for an individual session, the signup note may not have an attached $sid.
                    $signupnote = empty($fromform->customfield_signupnote) ? '' : $fromform->customfield_signupnote;
                }

                // Selected job assignment choice.
                if (!empty($fromform->{'selectedjobassignment_' . $session->facetoface})) {
                    $signupparams['jobassignmentid'] = $fromform->{'selectedjobassignment_' . $session->facetoface};
                }

                $session = $sessions[$sid];
                $facetoface = $facetofaces[$session->facetoface];

                $result = $this->signup_shezar_facetoface_sid($course, $signupparams, $session, $facetoface, $notificationtype,
                        $signupnote);

                // Need approval has priority to enrol in one signup.
                // However, if other signup allow enrolment without approval then they take a lead.
                if (isset($result['needapproval']) && $result['needapproval'] && is_null($needapproval)) {
                    $needapproval = true;
                } else {
                    $needapproval = false;
                    $enrol |= ($result['result'] === true);
                }

                $message .= html_writer::div(get_string('signuppersessionresult', 'enrol_shezar_facetoface', (object)array(
                    'facetoface' => $facetoface->name,
                    'message' => $result['message']
                )), 'enrolfacetofacesignupresult');
            }
        }

        // Enrol or add pending enrolent.
        if ($needapproval) {
            $toinsert = (object)array(
                'enrolid' => $instance->id,
                'userid' => $USER->id,
                'timecreated' => time(),
            );
            $DB->insert_record('enrol_shezar_f2f_pending', $toinsert);
            $returnurl = new moodle_url('/');
        }

        $cssclass = 'notifymessage';
        if ($enrol) {
            $this->enrol_user($instance, $USER->id, $instance->roleid, $timestart, $timeend);
            $cssclass = 'notifysuccess';
            // Send welcome message.
            if ($instance->customint4) {
                $this->email_welcome_message($instance, $USER);
            }
        }

        shezar_set_notification($message, $returnurl, array('class' => $cssclass), false);
        return $enrol;
    }

    /**
     * Creates course enrol form, checks if form submitted
     * and enrols user if necessary. It can also redirect.
     *
     * @param stdClass $instance
     * @return string html text, usually a form in a text box
     */
    public function enrol_page_hook(stdClass $instance) {
        global $OUTPUT, $CFG, $DB, $PAGE;

        require_once($CFG->dirroot . '/enrol/shezar_facetoface/signup_form.php');

        $enrolstatus = $this->can_self_enrol($instance);

        // Don't show enrolment instance form, if user can't enrol using it.
        if ($enrolstatus === true) {
            $settingautosignup = self::SETTING_AUTOSIGNUP;
            if ($instance->$settingautosignup) {
                $form = new enrol_shezar_facetoface_signup_form(null, $instance);

                $instanceid = optional_param('instance', 0, PARAM_INT);
                if ($instance->id == $instanceid) {
                    if ($data = $form->get_data()) {
                        $course = $DB->get_record('course', array('id' => $instance->courseid), '*', MUST_EXIST);
                        $returnurl = new moodle_url('/enrol/index.php', array('id' => $course->id));
                        $this->enrol_shezar_facetoface($instance, $data, $course, $returnurl);
                    }
                }

                ob_start();
                $form->display();
                $output = ob_get_clean();
                return $OUTPUT->box($output);
            } else {
                /** @var mod_facetoface_renderer $f2frenderer */
                $f2frenderer = $PAGE->get_renderer('mod_facetoface');

                $viewattendees = false;

                $sessions = $this->get_enrolable_sessions($instance->courseid);

                // Sort sessions into face-to-face activities.
                $f2fsessionarrays = array();
                foreach ($sessions as $session) {
                    if (!isset($f2fsessionarrays[$session->facetoface])) {
                        $f2fsessionarrays[$session->facetoface] = array();
                    }
                    $f2fsessionarrays[$session->facetoface][] = $session;
                }

                $output = '';

                foreach ($f2fsessionarrays as $id => $f2fsessionarray) {
                    if (!empty($f2fsessionarray)) {
                        $f2f = $DB->get_record('facetoface', array('id' => $id));

                        $cm = get_coursemodule_from_instance("facetoface", $f2f->id, $f2f->course);
                        $contextmodule = context_module::instance($cm->id);
                        $viewattendees = has_capability('mod/facetoface:viewattendees', $contextmodule);
                        $editevents = has_capability('mod/facetoface:editevents', $contextmodule);
                        $displaytimezones = get_config(null, 'facetoface_displaysessiontimezones');
                        $reserveinfo = array();
                        if (!empty($f2f->managerreserve)) {
                            // Include information about reservations when drawing the list of sessions.
                            $reserveinfo = facetoface_can_reserve_or_allocate($f2f, $f2fsessionarray, $contextmodule);
                        }

                        $f2fsessionarray = array_slice($f2fsessionarray, 0, $f2f->display, true);
                        $output .= html_writer::tag('h4', format_string($f2f->name));
                        $f2frenderer->setcontext($contextmodule);
                        $output .= $f2frenderer->print_session_list_table($f2fsessionarray, $viewattendees, $editevents,
                            $displaytimezones, $reserveinfo, null, true);
                    }
                }
            }

            return $output;
        }

        // This is a hack, unfortunately can_self_enrol returns error strings, an in this case it returns a string wrapped
        // in rich HTML content.
        if (strpos($enrolstatus, get_string('cannotenrolalreadyrequested', 'enrol_shezar_facetoface')) !== false) {
            $output = html_writer::start_tag('p');
            $output .= $enrolstatus;
            $output .= html_writer::end_tag('p');

            $islink = strpos($enrolstatus, '/enrol/shezar_facetoface/withdraw.php');
            if ($islink === false) {
                $url = new moodle_url('/enrol/shezar_facetoface/withdraw.php', array('eid' => $instance->id));
                $output .= html_writer::start_tag('p');
                $output .= html_writer::link($url, get_string('withdrawpending', 'enrol_shezar_facetoface'),
                        array('class' => 'link-as-button'));
                $output .= html_writer::end_tag('p');
            }

            return $output;
        }
    }

    /**
     * Enrols the user in course through the facetoface enrolment instance.
     *
     * @param moodleform $form
     * @param stdClass $instance
     * @return bool
     */
    public function course_expand_enrol_hook($form, $instance) {
        global $DB;

        $course = $DB->get_record('course', array('id'=>$instance->courseid), '*', MUST_EXIST);

        if ($data = $form->get_data()) {
            if ($this->enrol_shezar_facetoface($instance, $data, $course, null)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Creates course enrol form, checks if form submitted
     * and enrols user if necessary. It can also redirect.
     *
     * @param stdClass $instance
     * @return moodleform Instance of the enrolment form if successful, else false.
     */
    public function course_expand_get_form_hook($instance) {
        global $CFG, $PAGE, $DB;

        require_once("$CFG->dirroot/enrol/shezar_facetoface/signup_form.php");

        $enrolstatus = $this->can_self_enrol($instance);

        // Don't show enrolment instance form, if user can't enrol using it.
        if ($enrolstatus === true) {

            $settingautosignup = self::SETTING_AUTOSIGNUP;
            if ($instance->$settingautosignup) {
                require_once("$CFG->dirroot/enrol/shezar_facetoface/signup_form.php");

                $enrolstatus = $this->can_self_enrol($instance);

                // Don't show enrolment instance form, if user can't enrol using it.
                if ($enrolstatus === true) {
                    return new enrol_shezar_facetoface_signup_form(null, $instance);
                }

                return $enrolstatus;
            } else {
                /** @var mod_facetoface_renderer $f2frenderer */
                $f2frenderer = $PAGE->get_renderer('mod_facetoface');

                $sessions = $this->get_enrolable_sessions($instance->courseid);

                // Sort sessions into face-to-face activities.
                $f2fsessionarrays = array();
                foreach ($sessions as $session) {
                    if (!isset($f2fsessionarrays[$session->facetoface])) {
                        $f2fsessionarrays[$session->facetoface] = array();
                    }
                    $f2fsessionarrays[$session->facetoface][] = $session;
                }

                $output = '';

                foreach ($f2fsessionarrays as $id => $f2fsessionarray) {
                    if (!empty($f2fsessionarray)) {
                        $f2f = $DB->get_record('facetoface', array('id' => $id));

                        $cm = get_coursemodule_from_instance("facetoface", $f2f->id, $f2f->course);
                        $contextmodule = context_module::instance($cm->id);
                        $viewattendees = has_capability('mod/facetoface:viewattendees', $contextmodule);
                        $editevents = has_capability('mod/facetoface:editevents', $contextmodule);
                        $displaytimezones = get_config(null, 'facetoface_displaysessiontimezones');
                        $reserveinfo = array();
                        if (!empty($f2f->managerreserve)) {
                            // Include information about reservations when drawing the list of sessions.
                            $reserveinfo = facetoface_can_reserve_or_allocate($f2f, $f2fsessionarray, $contextmodule);
                        }

                        $f2fsessionarray = array_slice($f2fsessionarray, 0, $f2f->display, true);
                        $output .= html_writer::tag('h4', format_string($f2f->name));
                        $f2frenderer->setcontext($contextmodule);
                        $output .= $f2frenderer->print_session_list_table($f2fsessionarray, $viewattendees, $editevents,
                            $displaytimezones, $reserveinfo, null, true);
                    }
                }

                return $output;
            }
        }

        return $enrolstatus;
    }

    /**
     * Checks if user can self enrol.
     *
     * @param stdClass $instance enrolment instance
     * @param bool $checkuserenrolment if true will check if user enrolment is inactive.
     *             used by navigation to improve performance.
     * @return bool|string true if successful, else error message or false.
     */
    public function can_self_enrol(stdClass $instance, $checkuserenrolment = true) {
        global $DB, $USER, $CFG;

        $time = time();

        if ($checkuserenrolment) {
            if (isguestuser()) {
                // Can not enrol guest.
                return get_string('cannotenrol', 'enrol_shezar_facetoface');
            }
            // Check if user is already enroled.
            if ($DB->get_record('user_enrolments', array('userid' => $USER->id, 'enrolid' => $instance->id))) {
                return get_string('cannotenrol', 'enrol_shezar_facetoface');
            }
        }

        if ($instance->status != ENROL_INSTANCE_ENABLED) {
            return get_string('cannotenrol', 'enrol_shezar_facetoface');
        }

        if ($instance->enrolstartdate != 0 and $instance->enrolstartdate > $time) {
            return get_string('cannotenrol', 'enrol_shezar_facetoface');
        }

        if ($instance->enrolenddate != 0 and $instance->enrolenddate < $time) {
            return get_string('cannotenrol', 'enrol_shezar_facetoface');
        }

        if (!$instance->customint6) {
            // New enrols not allowed.
            return get_string('cannotenrol', 'enrol_shezar_facetoface');
        }

        if ($DB->record_exists('user_enrolments', array('userid' => $USER->id, 'enrolid' => $instance->id))) {
            return get_string('cannotenrol', 'enrol_shezar_facetoface');
        }

        if ($instance->customint3 > 0) {
            // Max enrol limit specified.
            $count = $DB->count_records('user_enrolments', array('enrolid' => $instance->id));
            if ($count >= $instance->customint3) {
                // Bad luck, no more shezar_facetoface enrolments here.
                return get_string('maxenrolledreached', 'enrol_shezar_facetoface');
            }
        }

        if ($instance->customint5) {
            require_once($CFG->dirroot . '/cohort/lib.php');
            if (!cohort_is_member($instance->customint5, $USER->id)) {
                $cohort = $DB->get_record('cohort', array('id' => $instance->customint5));
                if (!$cohort) {
                    return null;
                }
                $a = format_string($cohort->name, true, array('context' => context::instance_by_id($cohort->contextid)));
                return markdown_to_html(get_string('cohortnonmemberinfo', 'enrol_shezar_facetoface', $a));
            }
        }

        // Face-to-face-related condition checks.

        // Get sessions.
        $sessions = $this->get_enrolable_sessions($instance->courseid);
        if (empty($sessions)) {
            if ($this->sessions_require_manager()) {
                return get_string('cannotenrol', 'enrol_shezar_facetoface');
            }
            return get_string('cannotenrolnosessions', 'enrol_shezar_facetoface');
        }

        // If I already have a pending request, cannot ask again.
        if ($DB->record_exists('enrol_shezar_f2f_pending', array('enrolid' => $instance->id, 'userid' => $USER->id))) {
            $url = new moodle_url('/enrol/shezar_facetoface/withdraw.php', array('eid' => $instance->id));

            $output = html_writer::start_tag('p');
            $output .= get_string('cannotenrolalreadyrequested', 'enrol_shezar_facetoface');
            $output .= html_writer::end_tag('p');
            $output .= html_writer::start_tag('p');
            $output .= html_writer::link($url, get_string('withdrawpending', 'enrol_shezar_facetoface'), array('class' => 'link-as-button'));
            $output .= html_writer::end_tag('p');
            return $output;
        }

        return true;
    }

    /**
     * Return information for enrolment instance containing list of parameters required
     * for enrolment, name of enrolment plugin etc.
     *
     * @param stdClass $instance enrolment instance
     * @return stdClass instance info.
     */
    public function get_enrol_info(stdClass $instance) {

        $instanceinfo = new stdClass();
        $instanceinfo->id = $instance->id;
        $instanceinfo->courseid = $instance->courseid;
        $instanceinfo->type = $this->get_name();
        $instanceinfo->name = $this->get_instance_name($instance);
        $instanceinfo->status = $this->can_self_enrol($instance);

        return $instanceinfo;
    }

    /**
     * Add new instance of enrol plugin with default settings.
     * Returns id of instance or null if creation failed.
     * @param stdClass $course
     * @return int|null id of new instance
     */
    public function add_default_instance($course) {
        $fields = $this->get_instance_defaults();

        return $this->add_instance($course, $fields);
    }

    /**
     * Returns defaults for new instances.
     * @return array
     */
    public function get_instance_defaults() {
        $expirynotify = $this->get_config('expirynotify');
        if ($expirynotify == 2) {
            $expirynotify = 1;
            $notifyall = 1;
        } else {
            $notifyall = 0;
        }

        $fields = array();
        $fields['status']          = $this->get_config('status');
        $fields['roleid']          = $this->get_config('roleid');
        $fields['enrolperiod']     = $this->get_config('enrolperiod');
        $fields['expirynotify']    = $expirynotify;
        $fields['notifyall']       = $notifyall;
        $fields['expirythreshold'] = $this->get_config('expirythreshold');
        $fields['customint2']      = $this->get_config('longtimenosee');
        $fields['customint3']      = $this->get_config('maxenrolled');
        $fields['customint4']      = $this->get_config('sendcoursewelcomemessage');
        $fields['customint5']      = 0;
        $fields['customint6']      = $this->get_config('newenrols');
        $fields[self::SETTING_UNENROLWHENREMOVED] = $this->get_config('unenrolwhenremoved');

        return $fields;
    }

    /**
     * Send welcome email to specified user.
     *
     * @param stdClass $instance
     * @param stdClass $user user record
     * @return void
     */
    protected function email_welcome_message($instance, $user) {
        global $CFG, $DB;

        $course = $DB->get_record('course', array('id' => $instance->courseid), '*', MUST_EXIST);
        $context = context_course::instance($course->id);

        $a = new stdClass();
        $a->coursename = format_string($course->fullname, true, array('context' => $context));
        $a->profileurl = "$CFG->wwwroot/user/view.php?id={$user->id}&course={$course->id}";
        $strmgr = get_string_manager();

        if (trim($instance->customtext1) !== '') {
            $message = $instance->customtext1;
            $message = str_replace('{$a->coursename}', $a->coursename, $message);
            $message = str_replace('{$a->profileurl}', $a->profileurl, $message);
            if (strpos($message, '<') === false) {
                // Plain text only.
                $messagetext = $message;
                $messagehtml = text_to_html($messagetext, null, false, true);
            } else {
                // This is most probably the tag/newline soup known as FORMAT_MOODLE.
                $messagehtml = format_text(
                    $message,
                    FORMAT_MOODLE,
                    array('context' => $context, 'para' => false, 'newlines' => true, 'filter' => true)
                );
                $messagetext = html_to_text($messagehtml);
            }
        } else {
            $messagetext = $strmgr->get_string('welcometocoursetext', 'enrol_shezar_facetoface', $a, $user->lang);
            $messagehtml = text_to_html($messagetext, null, false, true);
        }

        $subject = $strmgr->get_string(
            'welcometocourse',
            'enrol_shezar_facetoface',
            format_string($course->fullname, true, array('context' => $context)),
            $user->lang
        );
        $subject =  str_replace('&amp;', '&', $subject);

        $rusers = array();
        if (!empty($CFG->coursecontact)) {
            $croles = explode(',', $CFG->coursecontact);
            list($sort, $sortparams) = users_order_by_sql('u');
            // shezar: we only use the first user - ignore hacks from MDL-22309.
            $rusers = get_role_users($croles, $context, true, '', 'r.sortorder ASC, ' . $sort, null, '', 0, 1, '', $sortparams);
        }
        if ($rusers) {
            $contact = reset($rusers);
        } else {
            $contact = core_user::get_support_user();
        }

        // Directly emailing welcome message rather than using messaging.
        email_to_user($user, $contact, $subject, $messagetext, $messagehtml);
    }

    /**
     * Enrol shezar_facetoface cron support.
     * @return void
     */
    public function cron() {
        $trace = new text_progress_trace();
        $this->sync($trace, null);
        $this->send_expiry_notifications($trace);
    }

    /**
     * Sync all meta course links.
     *
     * @param progress_trace $trace
     * @param int $courseid one course, empty mean all
     * @return int 0 means ok, 1 means error, 2 means plugin disabled
     */
    public function sync(progress_trace $trace, $courseid = null) {
        global $DB;

        if (!enrol_is_enabled('shezar_facetoface')) {
            $trace->finished();
            return 2;
        }

        // Unfortunately this may take a long time, execution can be interrupted safely here.
        core_php_time_limit::raise(0);
        raise_memory_limit(MEMORY_HUGE);

        $trace->output('Verifying shezar_facetoface-enrolments...');

        $params = array('now' => time(), 'useractive' => ENROL_USER_ACTIVE, 'courselevel' => CONTEXT_COURSE);
        $coursesql = "";
        if ($courseid) {
            $coursesql = "AND e.courseid = :courseid";
            $params['courseid'] = $courseid;
        }

        // Note: the logic of shezar_facetoface enrolment guarantees that user logged in at least once (=== u.lastaccess set)
        //       and that user accessed course at least once too (=== user_lastaccess record exists).

        // First deal with users that did not log in for a really long time - they do not have user_lastaccess records.
        $sql = "SELECT e.*, ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'shezar_facetoface' AND e.customint2 > 0)
                  JOIN {user} u ON u.id = ue.userid
                 WHERE :now - u.lastaccess > e.customint2
                       $coursesql";
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $instance) {
            $userid = $instance->userid;
            unset($instance->userid);
            $this->unenrol_user($instance, $userid);
            $days = $instance->customint2 / DAYSECS;
            $msg = "unenrolling user $userid from course $instance->courseid as they have did not log in for at least $days days";
            $trace->output($msg, 1);
        }
        $rs->close();

        // Now unenrol from course user did not visit for a long time.
        $sql = "SELECT e.*, ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'shezar_facetoface' AND e.customint2 > 0)
                  JOIN {user_lastaccess} ul ON (ul.userid = ue.userid AND ul.courseid = e.courseid)
                 WHERE :now - ul.timeaccess > e.customint2
                       $coursesql";
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $instance) {
            $userid = $instance->userid;
            unset($instance->userid);
            $this->unenrol_user($instance, $userid);
            $days = $instance->customint2 / DAYSECS;
            $msg = "unenrolling user $userid from course $instance->courseid as they did not access course for at least $days days";
            $trace->output($msg, 1);
        }
        $rs->close();

        $trace->output('...user shezar_facetoface-enrolment updates finished.');
        $trace->finished();

        $this->process_expirations($trace, $courseid);

        return 0;
    }

    /**
     * Returns the user who is responsible for shezar_facetoface enrolments in given instance.
     *
     * Usually it is the first editing teacher - the person with "highest authority"
     * as defined by sort_by_roleassignment_authority() having 'enrol/shezar_facetoface:manage'
     * capability.
     *
     * @param int $instanceid enrolment instance id
     * @return stdClass user record
     */
    protected function get_enroller($instanceid) {
        global $DB;

        if ($this->lastenrollerinstanceid == $instanceid and $this->lastenroller) {
            return $this->lastenroller;
        }

        $instance = $DB->get_record('enrol', array('id' => $instanceid, 'enrol' => $this->get_name()), '*', MUST_EXIST);
        $context = context_course::instance($instance->courseid);

        if ($users = get_enrolled_users($context, 'enrol/shezar_facetoface:manage')) {
            $users = sort_by_roleassignment_authority($users, $context);
            $this->lastenroller = reset($users);
            unset($users);
        } else {
            $this->lastenroller = parent::get_enroller($instanceid);
        }

        $this->lastenrollerinstanceid = $instanceid;

        return $this->lastenroller;
    }

    /**
     * Gets an array of the user enrolment actions.
     *
     * @param course_enrolment_manager $manager
     * @param stdClass $ue A user enrolment object
     * @return array An array of user_enrolment_actions
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol($instance) && has_capability("enrol/shezar_facetoface:unenrol", $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(
                new pix_icon('t/delete', get_string('enroldelete', 'enrol_shezar_facetoface')),
                get_string('unenrol', 'enrol'),
                $url,
                array('class' => 'unenrollink', 'rel' => $ue->id)
            );
        }
        if ($this->allow_manage($instance) && has_capability("enrol/shezar_facetoface:manage", $context)) {
            $url = new moodle_url('/enrol/editenrolment.php', $params);
            $actions[] = new user_enrolment_action(
                new pix_icon('t/edit', get_string('enroledit', 'enrol_shezar_facetoface')),
                get_string('edit'),
                $url,
                array('class' => 'editenrollink', 'rel' => $ue->id));
        }
        return $actions;
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;
        if ($step->get_task()->get_target() == backup::TARGET_NEW_COURSE) {
            $merge = false;
        } else {
            $merge = array(
                'courseid'   => $data->courseid,
                'enrol'      => $this->get_name(),
                'roleid'     => $data->roleid,
            );
        }
        if ($merge and $instances = $DB->get_records('enrol', $merge, 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            if (!empty($data->customint5) && !$step->get_task()->is_samesite()) {
                // Use some id that can not exist in order to prevent shezar_facetoface enrolment,
                // because we do not know what cohort it is in this site.
                $data->customint5 = -1;
            }
            $instanceid = $this->add_instance($course, (array)$data);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $oldinstancestatus
     * @param int $userid
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
    }

    /**
     * Restore role assignment.
     *
     * @param stdClass $instance
     * @param int $roleid
     * @param int $userid
     * @param int $contextid
     */
    public function restore_role_assignment($instance, $roleid, $userid, $contextid) {
        // This is necessary only because we may migrate other types to this instance,
        // we do not use component in manual or shezar_facetoface enrol.
        role_assign($roleid, $userid, $contextid, '', 0);
    }

    /*
     * Get list of enrolable sessions in the course of a given instance.
     * @param object $instance
     * @return array
     */
    public function get_enrolable_sessions($courseid, $user = null, $facetofaceid = null, $ignoreapprovals = false) {
        global $DB, $USER;

        if ($user === null) {
            $user = $USER;
        }
        if ($courseid !== null) {
            $cachekey = 'c' . $courseid;
        }
        if ($facetofaceid !== null) {
            $cachekey = 'f' . $facetofaceid;
        }

        if (!empty($this->sessions[$cachekey])) {
            return $this->sessions[$cachekey];
        }

        $params = array(
            'modulename' => 'facetoface',
            'courseid' => $courseid,
            'visible' => 1,
        );

        $sql = "
        SELECT ssn.*, f2f.id AS f2fid, f2f.approvaltype, dates.cntdates, dates.mintimestart, dates.maxtimefinish
        FROM {course_modules} cm
        JOIN {modules} m ON (m.name = :modulename AND m.id = cm.module)
        JOIN {facetoface} f2f ON (f2f.id = cm.instance)
        JOIN {facetoface_sessions} ssn ON (ssn.facetoface = f2f.id)
        LEFT JOIN (
            SELECT fsd.sessionid, COUNT(*) AS cntdates, MIN(timestart) AS mintimestart, MAX(timefinish) AS maxtimefinish
            FROM {facetoface_sessions_dates} fsd
            GROUP BY fsd.sessionid
        ) dates ON (dates.sessionid = ssn.id)
        WHERE cm.visible = :visible
        AND ssn.cancelledstatus = 0
        AND (ssn.registrationtimestart = 0 OR ssn.registrationtimestart <= :now1)
        AND (ssn.registrationtimefinish = 0 OR ssn.registrationtimefinish >= :now2)
        ";

        $params['now1'] = time();
        $params['now2'] = time();

        if ($courseid != null) {
            $sql .= " AND cm.course = :courseid";
            $params['courseid'] = $courseid;
        }

        if ($facetofaceid != null) {
            $sql .= " AND f2f.id = :facetofaceid";
            $params['facetofaceid'] = $facetofaceid;
        }

        $sql .= " ORDER BY f2f.id, ssn.id";

        $sessions = $DB->get_records_sql($sql, $params);
        $this->sessions[$cachekey] = array();
        if (empty($sessions)) {
            return $this->sessions[$cachekey];
        }

        $timenow = time();

        // Add dates.
        $sessids = array();
        foreach ($sessions as $sessid => $session) {
            $session->sessiondates = array();
            $sessids[] = $sessid;
        }
        list($idin, $params) = $DB->get_in_or_equal($sessids);
        $sessiondates = $DB->get_records_select('facetoface_sessions_dates', "sessionid $idin", $params, 'timestart ASC');
        foreach ($sessiondates as $sessiondate) {
            $sessions[$sessiondate->sessionid]->sessiondates[] = $sessiondate;
            if ($sessiondate->roomid) {
                $room = $DB->get_record('facetoface_room', array('id' => $sessiondate->roomid));
                $sessiondate->room = $room;
            }
        }

        $managers = \shezar_job\job_assignment::get_all_manager_userids($USER->id);

        foreach ($sessions as $session) {
            $session->signupcount = facetoface_get_num_attendees($session->id, MDL_F2F_STATUS_REQUESTED);

            if (!empty($session->sessiondates) && facetoface_has_session_started($session, $timenow)) {
                continue;
            }

            $hascapacity = $session->signupcount < $session->capacity;

            $cm = get_coursemodule_from_instance('facetoface', $session->facetoface);
            $context = context_module::instance($cm->id);
            $capabilitiesthatcanoverbook = array('mod/facetoface:signupwaitlist', 'mod/facetoface:addattendees');
            $canforceoverbook = has_any_capability($capabilitiesthatcanoverbook, $context, $user);

            // If there is no capacity, waitlist and user can't override capacity continue.
            if (!$hascapacity && !$session->allowoverbook && !$canforceoverbook) {
                continue;
            }
            $session->hasselfapproval = $session->approvaltype == APPROVAL_SELF;
            if (!$ignoreapprovals && facetoface_manager_needed($session) && empty($managers) && !$session->hasselfapproval) {
                $this->removednomanager = true;
                continue;
            }

            if (!empty($session->sessiondates)) {
                $session->timestartsort = $session->sessiondates[0]->timestart;
            } else {
                // If datetime is unknown make timestartsort in the future and store at the end of the records.
                $session->timestartsort = PHP_INT_MAX;
            }
            $this->sessions[$cachekey][$session->id] = $session;
        }
        core_collator::asort_objects_by_property($this->sessions[$cachekey], 'timestartsort', core_collator::SORT_NUMERIC);
        return $this->sessions[$cachekey];
    }

    /*
     * Indicates whether some sessions were not returned because user has no mamager.
     * @return bool;
     */
    public function sessions_require_manager() {
        return $this->removednomanager;
    }

    /**
     * Can current user disable face to face enrolments in a course?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('moodle/course:enrolconfig', $context);
    }
}

/*
 * Handles change in signup status if relevant for enrolment
 * @param object $newstatus
 */
function enrol_shezar_facetoface_statushandler($newstatus) {
    $status = enrol_shezar_facetoface_enrol_on_approval($newstatus);
    $status = enrol_shezar_facetoface_unenrol_on_removal($newstatus) && $status;

    return $status;
}

function enrol_shezar_facetoface_deletedhandler($info) {
    global $DB;

    $status = true;
    if ($info->modulename == 'facetoface') { // Facetoface activity deleted.
        // Find all enrolment instances in this course of type shezar_facetoface with 'unenrol when removed' enabled.
        $enrols = $DB->get_records('enrol', array('enrol' => 'shezar_facetoface', 'courseid' => $info->courseid,
            enrol_shezar_facetoface_plugin::SETTING_UNENROLWHENREMOVED => 1));
        foreach ($enrols as $enrolinst) {
            if (!$userids = $DB->get_fieldset_select('user_enrolments', 'userid', 'enrolid = ?', array($enrolinst->id))) {
                continue;
            }
            $status = enrol_shezar_facetoface_unenrol_if_no_signups($enrolinst, $userids) && $status;
        }
    }
    return $status;
}

function enrol_shezar_facetoface_enrol_on_approval($newstatus) {
    global $DB;

    $sql = "
    SELECT efd.*
    FROM {facetoface_signups} snp
    JOIN {facetoface_sessions} ssn ON (ssn.id = snp.sessionid)
    JOIN {facetoface} f2f ON (f2f.id = ssn.facetoface)
    JOIN {enrol} enr ON (enr.courseid = f2f.course)
    JOIN {enrol_shezar_f2f_pending} efd ON (efd.enrolid = enr.id)
    WHERE snp.id = :signupid
    AND enr.enrol = :shezar_facetoface
    AND efd.userid = snp.userid
    ";
    $params = array(
        'signupid' => $newstatus->signupid,
        'shezar_facetoface' => 'shezar_facetoface',
    );
    if (!$efdrec = $DB->get_record_sql($sql, $params)) {
        return true;
    }

    $DB->delete_records('enrol_shezar_f2f_pending', array('id' => $efdrec->id));

    if ($newstatus->statuscode < MDL_F2F_STATUS_APPROVED) {
        return true;
    }

    // Enrol.
    if (!$enrol = $DB->get_record('enrol', array('id' => $efdrec->enrolid, 'enrol' => 'shezar_facetoface'))) {
        return false;
    }

    $timestart = time();
    if ($enrol->enrolperiod) {
        $timeend = $timestart + $enrol->enrolperiod;
    } else {
        $timeend = 0;
    }

    $shezar_facetoface = new enrol_shezar_facetoface_plugin();
    $shezar_facetoface->enrol_user($enrol, $efdrec->userid, $enrol->roleid, $timestart, $timeend);

    return true;
}

/**
 * If the enrol_shezar_facetoface instance is set to unenrol users on removal:
 * Check if the user has been removed from the f2f session.
 * If they have, and are now removed from all sessions in the course, then unenrol them.
 *
 * @param object $newstatus
 * @return bool
 */
function enrol_shezar_facetoface_unenrol_on_removal($newstatus) {
    global $DB;

    if ($newstatus->statuscode >= MDL_F2F_STATUS_REQUESTED) {
        return true; // Only interested in cancellations in this function.
    }

    // Look to see if the user is enroled via 'shezar_facetoface' and 'unenrol when removed' is enabled.
    $sql = "SELECT e.*, su.userid
              FROM {facetoface_signups} su
              JOIN {facetoface_sessions} s ON s.id = su.sessionid
              JOIN {facetoface} f ON f.id = s.facetoface
              JOIN {enrol} e ON e.courseid = f.course AND e.enrol = :enrol
                   AND e.".enrol_shezar_facetoface_plugin::SETTING_UNENROLWHENREMOVED." = 1
              JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = su.userid
             WHERE su.id = :signupid";
    $params = array('signupid' => $newstatus->signupid, 'enrol' => 'shezar_facetoface');
    $enrolinst = $DB->get_record_sql($sql, $params);

    if (!$enrolinst) {
        return true; // User not enroled via 'shezar_facetoface' OR 'unenrol when removed' not enabled for this instance.
    }

    // Check to see if the user is still signed up for any sessions in this course.
    enrol_shezar_facetoface_unenrol_if_no_signups($enrolinst, $enrolinst->userid);

    return true;
}

/**
 * Check the userid(s) against f2f session sign ups + unenrol if none found.
 * Note: users who have pending session requests (but no confirmed sign-ups) will be unenroled.
 *
 * @param object $enrolinst
 * @param object[]|object $userids user(s) who are enroled in the course via shezar_facetoface plugin
 * @return bool
 */
function enrol_shezar_facetoface_unenrol_if_no_signups($enrolinst, $userids) {
    global $DB;

    if (!is_array($userids)) {
        $userids = array($userids);
    }

    list($usql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

    $sql = "SELECT DISTINCT su.userid
              FROM {facetoface} f
              JOIN {facetoface_sessions} s ON s.facetoface = f.id
              JOIN {facetoface_signups} su ON su.sessionid = s.id
              JOIN {facetoface_signups_status} sus ON sus.signupid = su.id AND sus.superceded = 0
                                                  AND sus.statuscode >= :approved
             WHERE f.course = :courseid AND su.userid $usql";
    $params['approved'] = MDL_F2F_STATUS_APPROVED;
    $params['courseid'] = $enrolinst->courseid;
    $signedup = $DB->get_fieldset_sql($sql, $params);

    $tounenrol = array_diff($userids, $signedup); // Remove any users who are still signed up to a f2f session.
    if (!$tounenrol) {
        return true; // No users to unenrol.
    }

    // Unenrol all users still in the list.
    $enrol = enrol_get_plugin('shezar_facetoface');
    foreach ($tounenrol as $userid) {
        $enrol->unenrol_user($enrolinst, $userid);
    }

    return true;
}

/**
 * Get the 'best' session from a face to face for a user to be signed up to.
 * @param enrol_shezar_facetoface_plugin $shezar_facetoface
 * @param int $facetofaceid
 * @return object|null
 */
function enrol_shezar_facetoface_find_best_session($shezar_facetoface, $facetofaceid) {
    $facetofacesessions = $shezar_facetoface->get_enrolable_sessions(null, null, $facetofaceid, true);

    $best = null;
    foreach ($facetofacesessions as $session) {

        $session->hascapacity = ($session->capacity - $session->signupcount) > 0;

        if ($session->hascapacity) {
            $session->waitlistcount = 0;
            $session->spaces = $session->capacity - $session->signupcount;
        } else {
            $session->waitlistcount = $session->signupcount - $session->capacity;
            $session->spaces = 0;
        }

        if ($best === null) { // If we dont have a best yet then this will do.
            $best = $session;
            continue;
        }

        if (!$best->hascapacity && $session->hascapacity) { // If best has no capacity and contender does then it wins.
            $best = $session;
            continue;
        } else if ($best->hascapacity && !$session->hascapacity) { // If best has capacity and contender doesn't we don't want it.
            continue;
        } else if (!$best->hascapacity && !$session->hascapacity) { // If neither have capacity take the shortest wait list.
            if ($best->waitlistcount > $session->waitlistcount) {
                $best = $session;
                continue;
            }
        } // If they both have capacity then we carry on to look at dates.

        if (!$best->cntdates && $session->cntdates) { // If best has no date and contender does then it wins.
            $best = $session;
            continue;
        } else if ($best->cntdates && !$session->cntdates) { // If best has date and contender doesn't we don't want it.
            continue;
        } else if (!$best->cntdates && !$session->cntdates) { // If neither have date go for most capacity.
            if ($best->spaces < $session->spaces) {
                $best = $session;
            }
            continue;
        } else if ($best->cntdates && $session->cntdates) { // If session is before best then it wins.
            $bestearliestsession = null;
            $sessionearliestsession = null;

            foreach ($best->sessiondates as $date) {
                if ($bestearliestsession === null || $bestearliestsession > $date->timestart) {
                    $bestearliestsession = $date->timestart;
                }
            }
            foreach ($session->sessiondates as $date) {
                if ($sessionearliestsession === null || $sessionearliestsession > $date->timestart) {
                    $sessionearliestsession = $date->timestart;
                }
            }

            if ($sessionearliestsession < $bestearliestsession) {
                $best = $session;
                continue;
            }
        }
    }

    return $best;
}

/**
 * Gets an array of sessions that the user should be signed up for when autoenrolling.
 * @param enrol_shezar_facetoface_plugin $shezar_facetoface
 * @param object $course
 * @param array $facetofaces
 * @param object|null $user
 * @return array
 */
function enrol_shezar_facetoface_get_sessions_to_autoenrol($shezar_facetoface, $course, $facetofaces, $user = null) {
    global $USER;
    $sessions = array();

    if ($user == null) {
        $user = $USER;
    }

    $autosessions = $shezar_facetoface->get_enrolable_sessions($course->id, $user, null, true);
    $sessionstochoosefrom = array();

    // Move the sessions into an array grouped by face to face id.
    foreach ($autosessions as $session) {
        $sessionstochoosefrom[$session->facetoface][] = $session;
    }

    foreach ($sessionstochoosefrom as $facetofaceid => $facetofacesessions) {
        $facetoface = $facetofaces[$facetofaceid];

        $submissions = facetoface_get_user_submissions($facetofaceid, $user->id, MDL_F2F_STATUS_REQUESTED);

        // Signup to all sessions from a f2f with multiplesessions true that they haven't signed up to.
        if ($facetofaces[$facetofaceid]->multiplesessions) {
            $submissionsbysession = array();
            foreach ($submissions as $submission) {
                $submissionsbysession[$submission->sessionid] = $submission;
            }

            foreach ($facetofacesessions as $session) {
                if (!array_key_exists($session->id, $submissionsbysession)) {
                    $sessions[$session->id] = $session;
                }
            }
            continue;
        }

        if ($submissions) { // If the user has already signed for a session on this f2f then skip it.
            continue;
        }

        $best = enrol_shezar_facetoface_find_best_session($shezar_facetoface, $facetofaceid);

        if ($best != null) {
            $sessions[$best->id] = $best;
        }
    }

    return $sessions;
}
