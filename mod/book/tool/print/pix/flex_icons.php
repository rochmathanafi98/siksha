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
 * @author  Joby Harding <joby.harding@shezarlms.com>
 * @author  Petr Skoda <petr.skoda@shezarlms.com>
 * @package booktool_print
 */

/* Developer documentation is in /pix/flex_icons.php file. */

$icons = array(
    'booktool_print|book' =>
        array(
            'template' => 'core/flex_icon_stack',
            'data' =>
                array(
                    'classes' =>
                        array(
                            'fa-book ft-stack-main',
                            'fa-print ft-stack-suffix',
                        ),
                ),
        ),
    'booktool_print|chapter' =>
        array(
            'template' => 'core/flex_icon_stack',
            'data' =>
                array(
                    'classes' =>
                        array(
                            'ft-book-open ft-stack-main',
                            'fa-print ft-stack-suffix',
                        ),
                ),
        ),
);
