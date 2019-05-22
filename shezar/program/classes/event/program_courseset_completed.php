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
 * @subpackage shezar_program
 */


namespace shezar_program\event;
defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a courseset of the program is completed.
 *
 * @property-read array $other {
 * Extra information about the event.
 *
 * - coursesetid The Courseset ID
 * }
 *
 */
class program_courseset_completed extends \core\event\base {

    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['objecttable'] = 'prog';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventcoursesetcompletion', 'shezar_program');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "This program courseset {$this->other['coursesetid']} was completed by user {$this->userid}";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/shezar/program/required.php', array('id' => $this->objectid, 'userid' => $this->userid));
    }

    /**
     * Returns the name of the legacy event.
     *
     * @return string legacy event name
     */
    public static function get_legacy_eventname() {
        return 'program_courseset_completion';
    }

    /**
     * Returns the legacy event data.
     *
     * @return \stdClass
     */
    protected function get_legacy_eventdata() {
        $data = new \stdClass();
        $data->userid = $this->userid;
        $data->programid = $this->objectid;
        $data->coursesetid = $this->other['coursesetid'];
        return $data;
    }


    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        return array(SITEID, 'program_courseset', 'completed', 'view.php?id=' . $this->objectid, 'ID: ' . $this->objectid);
    }

    protected function validate_data() {
        global $CFG;

        if ($CFG->debugdeveloper) {
            parent::validate_data();

            if (!isset($this->other['coursesetid'])) {
                throw new \coding_exception('coursesetid must be set in $other.');
            }
        }
    }

}
