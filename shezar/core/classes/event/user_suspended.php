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
 * @package shezar_core
 */

namespace shezar_core\event;
defined('MOODLE_INTERNAL') || die();

/**
 * User suspended event.
 *
 * Note: this event is triggered right after
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - string username: username of the user.
 * }
 *
 * @since   shezar 2.6
 * @package shezar_core
 * @author  David Curry <david.curry@shezarlms.com>
 */
class user_suspended extends \core\event\base {
    /**
     * Create instance of event.
     *
     * @param \stdClass $user
     * @return user_suspended
     */
    public static function create_from_user(\stdClass $user) {
        $data = array(
            'objectid' => $user->id,
            'context' => \context_user::instance($user->id),
            'other' => array(
                'username' => $user->username,
            )
        );
        $event = self::create($data);
        $event->add_record_snapshot('user', $user);
        return $event;
    }

    /**
     * Initialise required event data properties.
     */
    protected function init() {
        $this->data['objecttable'] = 'user';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventusersuspended', 'shezar_core');
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description() {
        return 'User ' . $this->other['username'] . ' suspended';
    }

    /**
     * Return name of the legacy event, which is replaced by this event.
     *
     * @return string legacy event name
     */
    public static function get_legacy_eventname() {
        return 'user_suspended';
    }

    /**
     * Return user_suspended legacy event data.
     *
     * @return \stdClass user data.
     */
    protected function get_legacy_eventdata() {
        $user = $this->get_record_snapshot('user', $this->data['objectid']);
        return $user;
    }

    /**
     * Returns array of parameters to be passed to legacy add_to_log() function.
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        $user = $this->get_record_snapshot('user', $this->data['objectid']);
        return array(SITEID, 'user', 'suspended', "view.php?id=".$user->id, $user->firstname.' '.$user->lastname);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['username'])) {
            throw new \coding_exception('username must be set in $other.');
        }
    }
}
