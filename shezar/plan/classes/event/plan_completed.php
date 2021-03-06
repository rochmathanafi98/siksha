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
 * @author Ciaran Irvine <ciaran.irvine@shezarlms.com>
 * @package shezar_plan
 */

namespace shezar_plan\event;
defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a plan is completed
 *
 * @property-read array $other {
 * Extra information about the event.
 *
 * - componentid The id of the component added
 * - component The component type (course, competency, evidence etc)
 * - componentname The freetext fullname of the component
 * - name The freetext fullname of the plan
 * }
 *
 */
class plan_completed extends \core\event\base {
    /**
     * Flag for prevention of direct create() call.
     * @var bool
     */
    protected static $preventcreatecall = true;
    /** @var \development_plan */
    protected $plan;

    /**
     * Create event for plan.
     * @param \development_plan $plan
     * @return plan_completed
     */
    public static function create_from_plan(\development_plan $plan) {
        return self::create_from_component($plan, 'plan', null, $plan->name);
    }

    /**
     * Create event for component.
     * @param \development_plan $plan
     * @param string $component
     * @param int $componentid
     * @param string $componentname
     * @return plan_completed
     */
    public static function create_from_component(\development_plan $plan, $component, $componentid, $componentname) {
        $data = array(
            'objectid' => $plan->id,
            'context' => \context_system::instance(),
            'relateduserid' => $plan->userid,
            'other' => array(
                'name' => $plan->name,
                'component' => $component,
                'componentid' => $componentid,
                'componentname' => $componentname)
        );

        self::$preventcreatecall = false;
        /** @var plan_completed $event */
        $event = self::create($data);
        self::$preventcreatecall = true;

        return $event;
    }

    /**
     * Get plan instance.
     *
     * NOTE: to be used from observers only.
     *
     * @return \development_plan
     */
    public function get_plan() {
        if ($this->is_restored()) {
            throw new \coding_exception('get_plan() is intended for event observers only');
        }
        return $this->plan;
    }

    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'dp_plan';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventplancompleted', 'shezar_plan');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        // Is this completion of the entire plan, or a component within a plan?
        if ($this->other['component'] === 'plan') {
            $desc = "plan";
        } else {
            $desc = "{$this->other['component']} {$this->other['componentid']}:{$this->other['componentname']} in plan";
        }
        return "The user with id '{$this->userid}' completed the {$desc} {$this->objectid}:{$this->other['name']}";
    }

    /**
     * Return the legacy event log data.
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        $logurl = $this->get_url()->out_as_local_url(false);
        if ($this->other['component'] === 'course') {
            $info = "{$this->other['component']} {$this->other['componentid']}:{$this->other['componentname']}";
        } else {
            $info = $this->other['name'];
        }
        return array(SITEID, 'plan', "completed {$this->other['component']}", $logurl, $info);
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        if ($this->other['component'] === 'course') {
            $logurl = new \moodle_url('/shezar/plan/component.php', array('id' => $this->objectid, 'c' => $this->other['component']));
        } else {
            $logurl = new \moodle_url('/shezar/plan/view.php', array('id' => $this->objectid));
        }
        return $logurl;
    }

    /**
     * Custom validation
     *
     * @throws \coding_exception
     * @return void
     */
    public function validate_data() {
        if (self::$preventcreatecall) {
            throw new \coding_exception('cannot call create() directly');
        }
        parent::validate_data();

        if (!isset($this->other['component'])) {
            throw new \coding_exception('component must be set in $other');
        }
        if ($this->other['component'] != 'plan') {
            if (!isset($this->other['componentid'])) {
                throw new \coding_exception('componentid must be set in $other');
            }
        }
        if (!isset($this->other['componentname'])) {
            throw new \coding_exception('componentname must be set in $other');
        }
        if (!isset($this->other['name'])) {
            throw new \coding_exception('name must be set in $other');
        }
    }
}
