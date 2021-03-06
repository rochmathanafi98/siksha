<?php
/*
 * This file is part of shezar LMS
 *
 * Copyright (C) 2015 onwards shezar Learning Solutions LTD
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
 * @author Petr Skoda <petr.skoda@shezarlms.com>
 * @package shezar_connect
 */

/**
 * shezar core feature enable/disable setting.
 *
 * @package   shezar core
 * @copyright 2015 shezar Learning Solutions Ltd {@link http://www.shezarlms.com/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Petr Skoda <petr.skoda@shezarlms.com>
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/adminlib.php');

/**
 * shezar core feature enable/disable setting.
 *
 * @package   shezar core
 * @copyright 2015 shezar Learning Solutions Ltd {@link http://www.shezarlms.com/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Petr Skoda <petr.skoda@shezarlms.com>
 */
class shezar_core_admin_setting_feature extends admin_setting_configselect {
    /** @var  array list of udpate callbacks */
    protected $updatecallbacks;
    /**
     * Constructor.
     *
     * @param string $name unique ascii name, usually 'enablexxxx'
     * @param string $visiblename localised
     * @param string $description long localised info
     * @param int $defaultsetting
     * @param array $updatecallbacks list of update callbacks, null defaults to array('shezar_menu_reset_cache')
     */
    public function __construct($name, $visiblename, $description, $defaultsetting, array $updatecallbacks = null) {
        parent::__construct($name, $visiblename, $description, $defaultsetting, null);
        if ($updatecallbacks === null) {
            // In majority of cases the shezar menu needs to be reset.
            $updatecallbacks = array('shezar_menu_reset_cache');
        }
        $this->updatecallbacks = $updatecallbacks;

        $this->set_updatedcallback(array($this, 'execute_update_callbacks'));

        if (debugging('', DEBUG_DEVELOPER)) {
            // Make sure developers did not forget to modify the list of core features.
            if (strpos($name, 'enable') !== 0) {
                debugging('Feature setting names must start with "enable"', DEBUG_DEVELOPER);
            } else {
                $shortname = preg_replace('/^enable/', '', $name);
                if (!in_array($shortname, shezar_advanced_features_list())) {
                    debugging('Feature setting name must be included in shezar_advanced_features_list()', DEBUG_DEVELOPER);
                }
            }
        }
    }

    /**
     * Called when this setting changes.
     * @param string $fullname
     */
    public function execute_update_callbacks($fullname) {
        foreach ($this->updatecallbacks as $callback) {
            if (is_callable($callback)) {
                call_user_func($callback);
            }
        }
    }

    /**
     * Lazy load the options.
     *
     * @return bool true if loaded, false if error
     */
    public function load_choices() {
        global $CFG;

        if (is_array($this->choices)) {
            return true;
        }

        if (isset($CFG->{$this->name}) and $CFG->{$this->name} == shezar_HIDEFEATURE) {
            // The shezar_HIDEFEATURE does note really work, keep it for existing sites only,
            // this should be removed completely in the trust release after we add upgrade code.
            $this->choices = array(
                shezar_SHOWFEATURE => new lang_string('showfeature', 'shezar_core'),
                shezar_HIDEFEATURE => new lang_string('hidefeature', 'shezar_core'),
                shezar_DISABLEFEATURE => new lang_string('disablefeature', 'shezar_core')
            );
        } else {
            $this->choices = array(
                shezar_SHOWFEATURE => new lang_string('showfeature', 'shezar_core'),
                shezar_DISABLEFEATURE => new lang_string('disablefeature', 'shezar_core')
            );
        }
        return true;
    }
}
