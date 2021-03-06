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
 * @author Piers Harding <piers@catalyst.net.nz>
 * @author Brian Barnes <brian.barnes@shezarlms.com>
 * @package shezar
 * @subpackage message
 */

/**
 * Page containing column display options, displayed inside show/hide popup dialog
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot.'/message/lib.php');
require_once($CFG->dirroot.'/shezar/message/lib.php');
require_once($CFG->dirroot.'/shezar/core/lib.php');

$PAGE->set_context(context_system::instance());
require_login();

if (isguestuser()) {
    redirect($CFG->wwwroot);
}

$id = required_param('id', PARAM_INT);
$msg = $DB->get_record('message', array('id' => $id));
if (!$msg || $msg->useridto != $USER->id || !confirm_sesskey()) {
    print_error('notyours', 'shezar_message', $id);
}

$canbook = false;
$isfacetoface = false;
$isprogextensionallowed = true;
$metadata = $DB->get_record('message_metadata', array('messageid' => $id));

$eventdata = shezar_message_eventdata($id, 'onaccept', $metadata);
if ($eventdata && isset($eventdata->action)) {
    switch ($eventdata->action) {
        case 'facetoface':
            require_once($CFG->dirroot . '/mod/facetoface/lib.php');
            $isfacetoface = true;
            $canbook = facetoface_task_check_capacity($eventdata->data);
            break;
        case 'prog_extension':
            require_once($CFG->dirroot . '/shezar/program/lib.php');
            $isprogextensionallowed = !empty($CFG->enableprogramextensionrequests) && shezar_prog_extension_allowed($eventdata->data['programid']);
            break;
    }
}

$from     = shezar_get_sender_from_user_by_id($msg->useridfrom);
$fromname = fullname($from) . " ({$from->email})";
$subject  = format_string($msg->subject);

if ($isfacetoface && !$DB->record_exists('facetoface_sessions', array('id' => $eventdata->data['session']->id))) {
        $subject .= ' (' . html_writer::tag('strong', get_string('f2fsessiondeleted', 'block_shezar_tasks')) . ')';
} else if ($isfacetoface && !$canbook) {
        $subject .= ' (' . html_writer::tag('strong', get_string('f2fsessionfull', 'block_shezar_tasks')) . ')';
}

// Add info to the subject to explain that program extension is not allowed.
if (!$isprogextensionallowed) {
    $subject .= ' (' . html_writer::tag('strong', get_string('allowextensionrequestsdisabled', 'shezar_program')) . ')';
}

global $shezar_MESSAGE_TYPES;
$msgtype = get_string($shezar_MESSAGE_TYPES[$metadata->msgtype], 'shezar_message');
$icon = $OUTPUT->pix_icon('msgicons/' . $metadata->icon, format_string($msgtype), 'shezar_core', array('class' => 'msgicon',  'alt' => format_string($msgtype)));

echo html_writer::start_tag('div', array('id' => 'shezar-msgs-dismiss'));
echo html_writer::start_tag('dl', array('class' => 'list'));

if (!empty($msg->subject)) {
    echo html_writer::tag('dt', get_string('subject', 'forum'));
    echo html_writer::tag('dd', $subject);
}
echo html_writer::tag('dt', get_string('type', 'block_shezar_alerts'));
echo html_writer::tag('dd', $icon);

echo html_writer::tag('dt', get_string('from', 'block_shezar_alerts'));
echo html_writer::tag('dd', $fromname);

echo html_writer::tag('dt', get_string('statement', 'block_shezar_alerts'));
echo html_writer::tag('dd', $msg->fullmessagehtml);

if ($msg->contexturl && $msg->contexturlname) {
    echo html_writer::tag('dt', get_string('statement', 'block_shezar_alerts'));
    echo html_writer::tag('dd', html_writer::tag('a', $msg->contexturlname, array('href' => $msg->contexturl)));
}
echo html_writer::end_tag('dl');

// Create input reason for declining/approving the request.
if ($eventdata && ($eventdata->action === 'plan' || ($eventdata->action === 'prog_extension' && $isprogextensionallowed))) {
    echo html_writer::tag('label', get_string('reasonfordecision', 'shezar_message'), array('for' => 'dismiss-reasonfordecision'));
    echo html_writer::empty_tag('input', array('class' => 'reasonfordecision', 'type' => 'text', 'name' => 'reasonfordecision', 'id' => 'reasonfordecision', 'size' => '80', 'maxlength' => '255'));
}
echo html_writer::end_tag('div');
