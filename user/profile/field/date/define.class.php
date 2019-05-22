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
 * @package profilefield_date
 */

/**
 * Define date fields - this is intended mainly for birth dates.
 *
 * NOTE: this is not likely to work in Windows for dates < 1970
 *
 * @author Petr Skoda <petr.skoda@shezarlms.com>
 * @package profilefield_date
 */
class profile_define_date extends profile_define_base {
    /**
     * Define the setting for a date custom field.
     *
     * @param MoodleQuickForm $form the user form
     */
    public function define_form_specific($form) {
        // Defaultdata is required even if not used.
        $form->addElement('hidden', 'defaultdata', 0);
        $form->setType('defaultdata', PARAM_INT);
    }
}
