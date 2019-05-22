<?php
/*
 * This file is part of shezar LMS
 *
 * Copyright (C) 2014 onwards shezar Learning Solutions LTD
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
 * @package shezar_appraisal
 */

namespace shezar_appraisal\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when an appraisal_stage is deleted.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - appraisalid   The id of the associated appraisal
 * }
 *
 * @author David Curry <david.curry@shezarlms.com>
 * @package shezar_appraisal
 */
class stage_deleted extends \core\event\base {

    /**
     * Flag for prevention of direct create() call.
     * @var bool
     */
    protected static $preventcreatecall = true;

    /**
     * The instance used to create the event.
     * @var \stdClass
     */
    protected $stage;

    /**
     * Create instance of event.
     *
     * @param   \stdClass $instance An appraisal_stage record.
     * @return  stage_deleted
     */
    public static function create_from_instance(\stdClass $instance) {
        $data = array(
            'objectid' => $instance->id,
            'context' => \context_system::instance(),
            'other' => array(
                'appraisalid' => $instance->appraisalid,
            ),
        );

        self::$preventcreatecall = false;
        $event = self::create($data);
        $event->stage = $instance;
        $event->add_record_snapshot('appraisal_stage', $instance);
        self::$preventcreatecall = true;

        return $event;
    }

    /**
     * Get appraisal_stage instance.
     *
     * NOTE: to be used from observers only.
     *
     * @return \stdClass
     */
    public function get_stage() {
        if ($this->is_restored()) {
            throw new \coding_exception('get_stage() is intended for event observers only');
        }
        return $this->stage;
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'appraisal_stage';
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventdeletedstage', 'shezar_appraisal');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The appraisal stage {$this->objectid} was deleted";
    }

    /**
     * Returns relevant url.
     *
     * @return \moodle_url
     */
    public function get_url() {
        $urlparams = array('appraisalid' => $this->data['other']['appraisalid']);
        return new \moodle_url('/shezar/appraisal/stage.php', $urlparams);
    }

    /**
     * Custom validation
     *
     * @throws \coding_exception
     * @return void
     */
    public function validate_data() {
        if (self::$preventcreatecall) {
            throw new \coding_exception('cannot call create() directly, use create_from_instance() instead.');
        }

        parent::validate_data();

        if (!isset($this->other['appraisalid'])) {
            throw new \coding_exception('appraisalid must be set in $other');
        }
    }
}
