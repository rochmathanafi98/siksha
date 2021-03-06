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
 * @author David Curry <david.curry@shezarlms.com>
 * @package shezar
 * @subpackage shezar_hierarchy
 */

require_once($CFG->dirroot.'/shezar/core/lib/assign/lib.php');

class shezar_assign_goal extends shezar_assign_core {
    protected static $module = 'goal';

    public function __construct($module, $moduleinstance) {
        global $CFG;
        parent::__construct($module, $moduleinstance);

        $this->basepath = $CFG->dirroot . '/shezar/hierarchy/prefix/goal/assign/';
    }


    public static function get_assignable_grouptypes() {
        global $CFG;

        static $grouptypes = array();

        if (!empty($grouptypes)) {
            return $grouptypes;
        }

        // Loop through code folder to find group classes.
        $basepath = $CFG->dirroot . "/shezar/hierarchy/prefix/goal/assign/";

        if (is_dir($basepath . 'groups')) {
            $classfiles = glob($basepath . 'groups/*.class.php');

            if (is_array($classfiles)) {
                foreach ($classfiles as $filename) {
                    // Add them all to an array.
                    $grouptypes[] = str_replace('.class.php', '', basename($filename));
                }
            }
        }

        return $grouptypes;
    }

}

class pos_goal_assign_ui_picker_hierarchy extends shezar_assign_ui_picker_hierarchy {

    /**
     * Returns markup to be used in the selected pane of a multi-select dialog
     *
     * @param   $elements    array elements to be created in the pane
     * @return  $html
     */
    public function populate_selected_items_pane($elements, $overridden = false) {

        if (!$overridden) {
            $childmenu = array();
            $childmenu[0] = get_string('posincludechildrenno', 'shezar_hierarchy');
            $childmenu[1] = get_string('posincludechildrenyes', 'shezar_hierarchy');
            $selected = isset($this->params['includechildren']) ? $this->params['includechildren'] : '';
            $html = html_writer::select($childmenu, 'includechildren', $selected, array(),
                    array('id' => 'id_includechildren', 'class' => 'assigngrouptreeviewsubmitfield'));
        } else {
            $html = '';
        }

        return $html . parent::populate_selected_items_pane($elements, true);
    }
}

class org_goal_assign_ui_picker_hierarchy extends shezar_assign_ui_picker_hierarchy {

    /**
     * Returns markup to be used in the selected pane of a multi-select dialog
     *
     * @param   $elements    array elements to be created in the pane
     * @return  $html
     */
    public function populate_selected_items_pane($elements, $overridden = false) {

        if (!$overridden) {
            $childmenu = array();
            $childmenu[0] = get_string('orgincludechildrenno', 'shezar_hierarchy');
            $childmenu[1] = get_string('orgincludechildrenyes', 'shezar_hierarchy');
            $selected = isset($this->params['includechildren']) ? $this->params['includechildren'] : '';
            $html = html_writer::select($childmenu, 'includechildren', $selected, array(),
                    array('id' => 'id_includechildren', 'class' => 'assigngrouptreeviewsubmitfield'));
        } else {
            $html = '';
        }

        return $html . parent::populate_selected_items_pane($elements, true);
    }
}
