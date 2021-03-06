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
 * @package shezar
 * @subpackage shezar_core
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot .'/shezar/core/utils.php');

/**
 * Form to upload icons.
 *
 */
class upload_icon_form extends moodleform {

    /**
     * Defines the form
     */
    public function definition() {
        $mform = $this->_form;
        $data = $this->_customdata['data'];
        $options = $this->_customdata['filemanageroptions'];

        $mform->addElement('hidden', 'id', $data->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('filemanager', 'course_filemanager', get_string('courseicon', 'shezar_core'), null, $options);
        $mform->addElement('filemanager', 'program_filemanager', get_string('programicon', 'shezar_core'), null, $options);

        $this->add_action_buttons();
        $this->set_data($data);
    }
}

