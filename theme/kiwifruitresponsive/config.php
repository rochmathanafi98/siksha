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
 * This theme has been deprecated.
 * We strongly recommend basing all new themes on roots and basis.
 * This theme will be removed from core in a future release at which point
 * it will no longer receive updates from shezar.
 *
 * @deprecated since shezar 9
 * @author Paul Walker <paul.walker@catalyst-eu.net>
 * @author Brian Barnes <brian.barnes@shezarlms.com>
 * @package shezar
 * @subpackage theme
 */

$THEME->name = 'kiwifruitresponsive';
$THEME->parents = array('standardshezarresponsive', 'bootstrapbase', 'base');
$THEME->parents_exclude_sheets = array('standardshezarresponsive', 'admin');
$THEME->sheets = array(
    'fonts', 'core', 'blocks', 'navigation', 'course', 'dock', 'css3', 'ie7', 'custom'
);

$THEME->enable_dock = true;
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->csspostprocess = 'theme_kiwifruitresponsive_process_css';
$THEME->javascripts_footer = array(
    'core'
);
