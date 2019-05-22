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
 * @subpackage reportbuilder
 */

global $CFG;
require_once($CFG->dirroot . '/shezar/reportbuilder/classes/rb_base_content.php');

class rb_team_members_embedded extends rb_base_embedded {

    public $url, $source, $fullname, $filters, $columns;
    public $contentmode, $contentsettings, $embeddedparams;
    public $hidden, $accessmode, $accesssettings, $shortname;
    public $defaultsortcolumn, $defaultsortorder;

    public function __construct($data) {
        $this->url = '/my/teammembers.php';
        $this->source = 'user';
        $this->defaultsortcolumn = 'user_namewithlinks';
        $this->shortname = 'team_members';
        $this->fullname = get_string('teammembers', 'shezar_core');
        $this->columns = array(
            array(
                'type' => 'user',
                'value' => 'namewithlinks',
                'heading' => get_string('name', 'rb_source_user'),
            ),
            array(
                'type' => 'user',
                'value' => 'lastlogin',
                'heading' => get_string('lastlogin', 'rb_source_user'),
            ),
            array(
                'type' => 'statistics',
                'value' => 'coursesstarted',
                'heading' => get_string('coursesstarted', 'rb_source_user'),
            ),
            array(
                'type' => 'statistics',
                'value' => 'coursescompleted',
                'heading' => get_string('coursescompleted', 'rb_source_user'),
            ),
            array(
                'type' => 'statistics',
                'value' => 'competenciesachieved',
                'heading' => get_string('competenciesachieved', 'rb_source_user'),
            ),
            array(
                'type' => 'user',
                'value' => 'extensionswithlink',
                'heading' => get_string('extensions', 'shezar_program'),
            ),
        );

        // no filters
        $this->filters = array();

        // only show future bookings
        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_ALL;
        $this->contentsettings = array(
            'user' => array(
                'enable' => 1,
                'who' => rb_user_content::USER_DIRECT_REPORTS + rb_user_content::USER_TEMP_REPORTS,
            )
        );

        // only show non-deleted users
        $this->embeddedparams = array(
            'deleted' => '0',
        );

        parent::__construct();
    }

    /**
     * Clarify if current embedded report support global report restrictions.
     * Override to true for reports that support GRR
     * @return boolean
     */
    public function embedded_global_restrictions_supported() {
        return true;
    }

    /**
     * Hide this source if feature disabled or hidden.
     * @return bool
     */
    public function is_ignored() {
        return !shezar_feature_visible('myteam');
    }

    /**
     * Check if the user is capable of accessing this report.
     * We use $reportfor instead of $USER->id and $report->get_param_value() instead of getting params
     * some other way so that the embedded report will be compatible with the scheduler (in the future).
     *
     * @param int $reportfor userid of the user that this report is being generated for
     * @param reportbuilder $report the report object - can use get_param_value to get params
     * @return boolean true if the user can access this report
     */
    public function is_capable($reportfor, $report) {
        return true;
    }
}
