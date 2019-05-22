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
 * @author Maria Torres <maria.torres@shezarlms.com>
 * @package shezar_customfield
 */

namespace shezar_customfield\prefix;
defined('MOODLE_INTERNAL') || die();

class competency_type extends hierarchy_type {

    public function is_feature_type_disabled() {
        return shezar_feature_disabled('competencies');
    }

    public function get_capability_managefield() {
        return 'shezar/hierarchy:competencymanagecustomfield';
    }
}
