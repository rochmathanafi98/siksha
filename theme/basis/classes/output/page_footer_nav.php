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
 * @copyright 2016 onwards shezar Learning Solutions LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Joby Harding <joby.harding@shezarlearning.com>
 * @package   theme_basis
 */

namespace theme_basis\output;

defined('MOODLE_INTERNAL') || die();

class page_footer_nav implements \renderable, \templatable {

    protected $menudata;

    /**
     * Constructor.
     */
    public function __construct($menudata) {
        $this->menudata = $menudata;
    }

    /**
     * Implements export_for_template().
     */
    public function export_for_template(\renderer_base $output) {
        // Renderer dynamically looks for a method called render_<classname>
        // so we have to wrap the menu data in a footer nav specific
        // object in order to change the way it is output without
        // altering the main header.
        $templatecontext = new \shezar_core\output\shezar_menu($this->menudata);
        $templatecontext->menuitems_has_items = !empty($templatecontext->menuitems) ? true : false;

        return $templatecontext;
    }

}
