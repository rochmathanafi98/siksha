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
 * @author Simon Coggins <simon.coggins@shezarlms.com>
 * @package shezar
 * @subpackage shezar_customfield
 */

class customfield_text extends customfield_base {

    function edit_field_add(&$mform) {
        $size = $this->field->param1;
        $maxlength = $this->field->param2;
        $fieldtype = ($this->field->param3 == 1 ? 'password' : 'text');
        $fullname = format_string($this->field->fullname);
        $regex = '';
        if (!empty($this->field->param4) && $param4 = json_decode($this->field->param4, true)) {
            $regex = !empty($param4['regex']) ? $param4['regex'] : '';
        }

        /// Create the form field
        $mform->addElement($fieldtype, $this->inputname, $fullname, 'maxlength="'.$maxlength.'" size="'.$size.'" ');
        $mform->setType($this->inputname, PARAM_TEXT);

        if (!empty($regex)) {
            $mform->addRule($this->inputname, get_string('regexvalidationfailed', 'shezar_customfield', $fullname), 'regex', $regex);
            // Param5 is regex pattern validation help message.
            if (!empty($this->field->param5)) {
                $mform->addElement('static', null, null, $this->field->param5);
            }
        }
    }

}
