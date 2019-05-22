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
 * @author Valerii Kuznetsov <valerii.kuznetsov@shezarlms.com>
 * @package shezar_core
 */

/**
 * Send reminder messages
 */
namespace shezar_core\task;

class send_reminder_messages_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('sendremindermessagestask', 'shezar_core');
    }

    /**
     *
     * Loops through reminders, checking if the trigger event has required period
     * fore each of the messages has passed, then sends emails out recording
     * success in the reminder_sent table
     *
     */
    public function execute() {
        global $DB, $CFG;
        require_once($CFG->libdir.'/reminderlib.php');
        require_once($CFG->libdir.'/completionlib.php');
        require_once($CFG->dirroot.'/shezar/message/messagelib.php');

        // Get reminders.
        $reminders = \reminder::fetch_all(
            array(
                'deleted'   => 0
            )
        );

        // Check if any reminders found.
        if (empty($reminders)) {
            return;
        }

        // Loop through reminders.
        foreach ($reminders as $reminder) {

            // Get messages.
            $messages = $reminder->get_messages();

            switch ($reminder->type) {
                case 'completion':

                    // Check completion is still enabled in this course.
                    $course = $DB->get_record('course', array('id' => $reminder->courseid));
                    $completion = new \completion_info($course);

                    if (!$completion->is_enabled()) {
                        mtrace('Completion no longer enabled in course: '.$course->id.', skipping');
                        continue;
                    }

                    mtrace('Processing reminder "'.$reminder->title.'" for course "'.$course->fullname.'" ('.$course->id.')');

                    // Get the tracked activity/course.
                    $config = unserialize($reminder->config);

                    // Get the required feedback's id.
                    $requirementid = $DB->get_field(
                        'course_modules',
                        'instance',
                        array('id' => $config['requirement'])
                    );

                    if (empty($requirementid)) {
                        mtrace('ERROR: No feedback requirement found for this reminder... SKIPPING');
                        continue;
                    }

                    // Check if we are tracking the course.
                    if ($config['tracking'] == 0) {
                        $tsql = "
                            INNER JOIN {course_completions} cc
                                    ON cc.course = ?
                                   AND cc.userid = u.id
                            ";
                        $tparams = array($course->id);
                    } else {
                        // Otherwise get the activity.
                        // Load moduleinstance.
                        $cm = $DB->get_record('course_modules', array('id' => $config['tracking']));
                        $module = $DB->get_field('modules', 'name', array('id' => $cm->module));

                        $tsql = "
                            INNER JOIN {course_completion_criteria} cr
                                    ON cr.course = ?
                                   AND cr.criteriatype = ?
                                   AND cr.module = ?
                                   AND cr.moduleinstance = ?
                            INNER JOIN {course_completion_crit_compl} cc
                                    ON cc.course = ?
                                   AND cc.userid = u.id
                                   AND cc.criteriaid = cr.id
                            ";
                        $tparams = array($course->id, COMPLETION_CRITERIA_TYPE_ACTIVITY, $module, $config['tracking'], $course->id);
                    }

                    // Process each message.
                    foreach ($messages as $message) {

                        // If it's a weekend, send no reminders except "Same day" ones.
                        if ($message->period && !reminder_is_businessday(time())) {
                            continue;
                        }
                        // Number of seconds after completion (for timestamp comparison).
                        if ($message->period) {
                            $periodsecs = (int) $message->period * 24 * 60 * 60;
                        } else {
                            $periodsecs = 0;
                        }

                        $now = time();

                        // Get anyone that needs a reminder sent that hasn't had one already
                        // and has yet to complete the required feedback.
                        $sql = "
                            SELECT u.*, cc.timecompleted
                              FROM {user} u
                                  {$tsql}
                         LEFT JOIN {reminder_sent} rs
                                ON rs.userid = u.id
                               AND rs.reminderid = ?
                               AND rs.messageid = ?
                         LEFT JOIN {feedback_completed} fc
                                ON fc.feedback = ?
                               AND fc.userid = u.id
                             WHERE fc.id IS NULL
                               AND rs.id IS NULL
                               AND (cc.timecompleted + ?) >= ?
                               AND (cc.timecompleted + ?) < ?
                        ";
                        $params = array_merge($tparams, array($reminder->id, $message->id, $requirementid, $periodsecs,
                            $reminder->timecreated, $periodsecs, $now));

                        // If this is an escalation and we have a timestamp of when escalations were enabled/disabled then
                        // we need to limit returned users to those who completed since this was last changed otherwise
                        // people who completed in the past may receive the notification.
                        if ($message->type === 'escalation' && isset($config['escalationmodified'])) {
                            $sql .= " AND cc.timecompleted >= ?";
                            $params = array_merge($params, array($config['escalationmodified']));
                        }

                        // Check if any users found.
                        $rs = $DB->get_recordset_sql($sql, $params);
                        if (!$rs->valid()) {
                            mtrace("WARNING: no users to send {$message->type} message to (message id {$message->id})... SKIPPING");
                            continue;
                        }

                        // Get deadline.
                        $escalationtime = $DB->get_field(
                            'reminder_message',
                            'period',
                            array('reminderid' => $reminder->id,
                                  'type' => 'escalation',
                                  'deleted' => 0)
                        );

                        // Calculate days from now.
                        $message->deadline = $escalationtime - $message->period;

                        // Message sent counts.
                        $msent = 0;
                        $mfail = 0;

                        // Loop through results and send emails.
                        foreach ($rs as $user) {

                            // Check that even with weekends accounted for the period has still passed.
                            if (!reminder_check_businessdays($user->timecompleted, $message->period)) {
                                continue;
                            }

                            // Get the manager on the users first job assignment - if there is a manager there.
                            $jobassignment = \shezar_job\job_assignment::get_first($user->id);
                            if (!empty($jobassignment->managerid)) {
                                $manager = $DB->get_record('user', ['id' => $jobassignment->managerid], '*', MUST_EXIST);
                            } else {
                                $manager = false;
                            }

                            // Generate email content.
                            $user->manager = $manager;
                            $content = reminder_email_substitutions($message->message, $user, $course, $message, $reminder);
                            $subject = reminder_email_substitutions($message->subject, $user, $course, $message, $reminder);

                            // Get course contact.
                            $rusers = array();
                            if (!empty($CFG->coursecontact)) {
                                $context = \context_course::instance($course->id);
                                $croles = explode(',', $CFG->coursecontact);
                                list($sort, $sortparams) = users_order_by_sql('u');
                                // shezar: we only use the first user - ignore hacks from MDL-22309.
                                $rusers = get_role_users($croles, $context, true, '', 'r.sortorder ASC, ' . $sort, null,
                                        '', 0, 1, '', $sortparams);
                            }
                            if ($rusers) {
                                $contact = reset($rusers);
                            } else {
                                $contact = \core_user::get_support_user();
                            }

                            // Prepare message object.
                            $eventdata = new \stdClass();
                            $eventdata->userfrom          = $contact;
                            $eventdata->userto            = $user;
                            $eventdata->subject           = $subject;
                            $eventdata->fullmessage       = $content;
                            $eventdata->fullmessageformat = FORMAT_PLAIN;
                            $eventdata->fullmessagehtml   = text_to_html($content, null, false, true);
                            $eventdata->smallmessage      = '';
                            $eventdata->sendmail          = shezar_MSG_EMAIL_YES;

                            // Send user email.
                            if (tm_alert_send($eventdata)) {
                                $sent = new \stdClass();
                                $sent->reminderid = $reminder->id;
                                $sent->messageid = $message->id;
                                $sent->userid = $user->id;
                                $sent->timesent = time();

                                // Record in database.
                                if (!$DB->insert_record('reminder_sent', $sent)) {
                                    mtrace('ERROR: Failed to insert reminder_sent record for userid '.$user->id);
                                    ++$mfail;
                                } else {
                                    ++$msent;
                                }
                            } else {
                                ++$mfail;
                                mtrace('Could not send email to ' . $user->email);
                            }

                            // Check if we need to send to their manager also.
                            if ($message->type === 'escalation' && empty($message->copyto)) {

                                if ($manager !== false) {
                                    // Send manager email.
                                    $eventdata->userto = $manager;
                                    if (message_send($eventdata)) {
                                        ++$msent;
                                    } else {
                                        ++$mfail;
                                        mtrace('Could not send email to ' . fullname($user) . '\'s manager at ' . $manager->email);
                                    }
                                } else {
                                    ++$mfail;
                                    mtrace(fullname($user) . ' does not have a manager... Skipping manager email.');
                                }
                            }
                        }
                        $rs->close();
                        // Show stats for message.
                        mtrace($msent.' "'.$message->type.'" type messages sent');
                        if ($mfail) {
                            mtrace($mfail.' "'.$message->type.'" type messages failed');
                        }
                    }

                    break;

                default:
                    mtrace('Unsupported reminder type: '.$reminder->type);
            }
        }
    }
}
