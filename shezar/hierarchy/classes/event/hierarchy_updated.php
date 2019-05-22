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
 * @package shezar_hierarchy
 */

namespace shezar_hierarchy\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract Event used as the base by each hierarchy,
 * triggered when a hierarchy item is updated.
 *
 * @property-read array $other {
 *      Extra information about the event.
 * }
 *
 * @author David Curry <david.curry@shezarlms.com>
 * @package shezar_hierarchy
 */
abstract class hierarchy_updated extends \core\event\base {

    /**
     * Flag for prevention of direct create() call.
     * @var bool
     */
    protected static $preventcreatecall = true;

    /**
     * Returns hierarchy prefix.
     * @return string
     */
    abstract public function get_prefix();

    /**
     * Create instance of event.
     *
     * @param   \stdClass $instance A hierarchy item record.
     * @return  hierarchy_updated
     */
    public static function create_from_instance(\stdClass $instance) {
        $data = array(
            'objectid' => $instance->id,
            'context' => \context_system::instance(),
        );

        self::$preventcreatecall = false;
        $event = self::create($data);
        $event->add_record_snapshot($event->objecttable, $instance);
        self::$preventcreatecall = true;

        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $prefix = $this->get_prefix();
        return "The {$prefix}: {$this->objectid} was updated";
    }

    public function get_url() {
        $urlparams = array('prefix' => $this->get_prefix(), 'id' => $this->objectid);
        return new \moodle_url('/shezar/hierarchy/item/view.php', $urlparams);
    }

    protected function validate_data() {
        if (self::$preventcreatecall) {
            throw new \coding_exception('cannot call create() directly, use create_from_instance() instead.');
        }

        parent::validate_data();
    }
}
