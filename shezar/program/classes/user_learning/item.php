<?php
/*
 * This file is part of shezar LMS
 *
 * Copyright (C) 2016 onwards shezar Learning Solutions LTD
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
 * @author Sam Hemelryk <sam.hemelryk@shezarlearning.com>
 * @author Alastair Munro <alastair.munro@shezarlearning.com>
 * @package shezar_program
 */

namespace shezar_program\user_learning;

defined('MOODLE_INTERNAL') || die();

use \shezar_core\user_learning\item_base;
use \shezar_core\user_learning\item_has_progress;
use \shezar_core\user_learning\item_has_dueinfo;
use \shezar_core\user_learning\designation_primary;
use \shezar_program\user_learning\program_common;

class item extends item_base implements item_has_progress, item_has_dueinfo {

    use designation_primary;
    use program_common;

    /**
     * @var \program
     */
    protected $program;
    /**
     * @var courseset[]
     */
    protected $coursesets;

    // Set the path to be CERTIFPATH_STD for programs.
    protected $certification_path = CERTIFPATH_STD;

    protected $progress_canbecompleted = null;
    protected $progress_percentage;
    protected $progress_summary;

    /**
     * Gets all program learning items for the given user.
     *
     * @param \stdClass|int $userorid A user object or user ID
     *
     * @return array An arrray of learning object of type item
     */
    public static function all($userorid) {
        global $CFG;
        require_once($CFG->dirroot . '/shezar/program/lib.php');

        // Check programs are enabled.
        if (shezar_feature_disabled('programs')) {
            return [];
        }
        $items = [];
        $user = self::resolve_user($userorid);
        $programs = \prog_get_all_programs($user->id, '', '', '', false, false, true, true, false);
        foreach ($programs as $program) {
            $prog = new self($user, $program);
            $items = array_merge($items, [$prog], $prog->get_courseset_courses());
        }
        return $items;
    }

    /**
     * Gets a single course learning item for a give user.
     *
     * @param \stdClass|int $userorid A user object of ID
     * @param \stdClass|int $itemorid A program object or ID
     *
     * @return item_base A learning item object for the program
     */
    public static function one($userorid, $itemorid) {
        // Check programs are enabled.
        if (shezar_feature_disabled('programs')) {
            return false;
        }

        $user = self::resolve_user($userorid);

        if (is_object($itemorid)) {
            $itemid = $itemorid->id;
        } else {
            $itemid = $itemorid;
        }

        $program = new \program($itemid);

        if ($program->get_completion_data($userorid)) {
            $data = new \stdClass();
            $data->id = $program->id;
            $data->fullname = $program->fullname;
            $data->shortname = $program->shortname;
            $data->summary = $program->summary;

            $item = new self($user, $data);
            $item->get_courseset_courses();

            return $item;
        } else {
            return false;
        }
    }

    /**
     * Get the context for the course item
     *
     * @return integer The program context level.
     */
    public static function get_context_level() {
        return CONTEXT_PROGRAM;
    }

    /**
     * Maps data from the program properties to the item object
     *
     * @param stdClass $data A course object
     */
    protected function map_learning_item_record_data(\stdClass $data) {
        $this->id = $data->id;
        $this->fullname = $data->fullname;
        $this->shortname = $data->shortname;
        $this->description = $data->summary;
        $this->description_format = FORMAT_HTML; // Programs do not store a format we can use here.
        $this->url_view = new \moodle_url('/shezar/program/view.php', array('id' => $this->id));
    }

    /**
     * Ensure the program record has been loaded and
     * if not load it.
     */
    protected function ensure_program_loaded() {
        if ($this->program === null) {
            $this->program = new \program($this->id);
        }
    }

    /**
     * Ensure coursesets for the program have been loaded
     * and if not then load them.
     */
    protected function ensure_course_sets_loaded() {
        if ($this->coursesets !== null) {
            return;
        }
        $this->ensure_program_loaded();
        $this->coursesets = [];
        foreach ($this->program->content->get_course_sets() as $set) {
            /** @var \course_set $set */
            $courseset = courseset::from_course_set($this, $set, $this->user);
            $this->coursesets[] = $courseset;
        }
    }

    /**
     * If completion is enable for the site and course then
     * load the completion and progress info
     *
     * progress_canbecompleted is set the first time this is run
     * so if it is not null then we already have the data we need.
     */
    public function ensure_completion_loaded() {

        if ($this->progress_canbecompleted == null) {

            if (!\completion_info::is_enabled_for_site()) {
                // Completion is disabled at the site level.
                $this->progress_canbecompleted = false;
                return;
            }

            // The user can complete this program.
            $programprogress = round($this->program->get_progress($this->user->id));

            $this->progress_canbecompleted = true;
            $this->progress_percentage = $programprogress;

            if ($programprogress > 0) {
                $this->progress_summary = new \lang_string('xpercentcomplete', 'shezar_core', $programprogress);
            } else {
                $this->progress_summary = new \lang_string('notyetstarted', 'completion');

                // Hack to set item progress as 'In Progress' if any course within it has a progress percentage above zero.
                // We need this as the program api is not retrieving the progress correctly.
                // We are only doing this if $programprogress is 0.
                // TODO: Remove this once the program api is returning the correct progress.
                foreach ($this->coursesets as $set) {
                    foreach ($set->get_courses() as $course) {
                        if ($course->get_progress_percentage() > 0) {
                            $this->progress_summary = new \lang_string('inprogress', 'completion');
                        }
                    }
                }
            }
        }
    }

    public function ensure_duedate_loaded() {
        if ($this->duedate === null) {
            $completiondata = $this->program->get_completion_data($this->user->id);

            $this->duedate = $completiondata->timedue;
        }
    }

    /**
     * Export progress data to display in template
     *
     * @return stdClass Object containing progress info
     */
    public function export_progress_for_template() {
        $this->ensure_completion_loaded();

        $record = new \stdClass;
        $record->summary = (string)$this->progress_summary;
        $record->percentage = $this->progress_percentage;
        return $record;
    }

    /**
     * Export due date data to display in template
     *
     * @return stdClass Object containing due date info
     */
    public function export_dueinfo_for_template() {
        $this->ensure_duedate_loaded();

        // If there is not duedate then we can't create the
        // date for display.
        if ($this->duedate < 0) {
            return;
        }

        $now = time();

        $dueinfo = new \stdClass();

        // Date for tooltip.
        $duedateformat = get_string('strftimedatetimeon', 'langconfig');
        $duedateformattedtooltip = userdate($this->duedate, $duedateformat);

        $duedateformatted = userdate($this->duedate, get_string('strftimedateshortshezar', 'langconfig'));
        if ($now > $this->duedate) {
            // Overdue.
            $dueinfo->duetext = get_string('userlearningoverduesincex', 'shezar_core', $duedateformatted);
            $dueinfo->tooltip = get_string('userlearningoverduesincextooltip', 'shezar_core', $duedateformattedtooltip);
        } else {
            // Due.
            $dueinfo->duetext = get_string('userlearningdueonx', 'shezar_core', $duedateformatted);
            $dueinfo->tooltip = get_string('programduex', 'shezar_program', $duedateformattedtooltip);
        }

        return $dueinfo;
    }

    /**
     * Does this item have a duedate
     *
     * @return bool True
     */
    public function item_has_duedate() {
        return true;
    }

    /**
     * Exports program item data for the template
     *
     * @return stdClass Object containing data about this item
     */
    public function export_for_template() {
        $this->ensure_course_sets_loaded();
        $record = parent::export_for_template();

        $coursesetinfo = $this->process_coursesets($this->coursesets);

        // Set string for coursesets header for completed and optional sets.
        $record->coursesets_header_text = $this->get_coursesets_header_text($coursesetinfo->completecount, $coursesetinfo->optionalcount);

        // Set string for unavailable sets.
        if ($coursesetinfo->unavailablecount == 1) {
            $record->unavailable_coursesets = get_string('unavailablecoursesets', 'block_current_learning', $coursesetinfo->unavailablecount);
        } else if ($coursesetinfo->unavailablecount > 1) {
            $record->unavailable_coursesets = get_string('unavailablecoursesetsplural', 'block_current_learning', $coursesetinfo->unavailablecount);
        }

        foreach ($coursesetinfo->sets as $set) {
            $record->coursesets[] = $set->export_for_template();
        }

        // Remove the next operator for the last courseset.
        if (isset($record->coursesets)) {
            unset(end($record->coursesets)->nextseticon);
            unset(end($record->coursesets)->nextsetoperator);
        }

        return $record;
    }

    /**
     * Returns the component that owns this user learning instance.
     *
     * @return string
     */
    public function get_component() {
        return 'shezar_program';
    }

    /**
     * Returns the type of this user learning instance.
     *
     * @return string
     */
    public function get_type() {
        return 'program';
    }
}
