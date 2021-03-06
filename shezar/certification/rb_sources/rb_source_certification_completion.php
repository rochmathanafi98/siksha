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
 * @author David Curry <david.curry@shezarlms.com>
 * @package shezar
 * @subpackage reportbuilder
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/shezar/program/rb_sources/rb_source_program_completion.php');
require_once($CFG->dirroot . '/shezar/certification/lib.php');

class rb_source_certification_completion extends rb_source_program_completion {

    /**
     * Overwrite instance type value of shezar_visibility_where() in rb_source_program->post_config().
     */
    protected $instancetype = 'certification';

    public function __construct($groupid, rb_global_restriction_set $globalrestrictionset = null) {
        parent::__construct($groupid, $globalrestrictionset);

        // Global Report Restrictions are applied in rb_source_program_completion and work for rb_source_certification_completion
        // as well.

        $this->sourcetitle = get_string('sourcetitle', 'rb_source_certification_completion');
        $this->sourcewhere = $this->define_sourcewhere();
    }

    /**
     * Global report restrictions are implemented in this source.
     * @return boolean
     */
    public function global_restrictions_supported() {
        return true;
    }

    protected function define_sourcewhere() {
        // Only consider whole certifications - not courseset completion.
        $sourcewhere = 'base.coursesetid = 0';

        // Exclude programs (they have their own source).
        $sourcewhere .= ' AND (program.certifid IS NOT NULL)';

        return $sourcewhere;
    }

    protected function define_joinlist() {
        $joinlist = parent::define_joinlist();

        $this->add_certification_table_to_joinlist($joinlist, 'program', 'certifid');

        $joinlist[] = new rb_join(
            'certif_completion',
            'INNER',
            '{certif_completion}',
            "certif_completion.userid = base.userid AND certif_completion.certifid = program.certifid",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            array('base', 'program')
        );

        $joinlist[] = new rb_join(
            'prog_courseset',
            'LEFT',
            '{certif_completion}',
            "certif_completion.userid = base.userid AND certif_completion.certifid = program.certifid",
            REPORT_BUILDER_RELATION_ONE_TO_MANY,
            array('base', 'program')
        );

        return $joinlist;
    }

    protected function get_source_joins() {
        $parentjoins = parent::get_source_joins();
        return array_merge(array('certif_completion'), $parentjoins);
    }

    protected function define_columnoptions() {
        $columnoptions = parent::define_columnoptions();

        $this->add_certification_fields_to_columns($columnoptions, 'certif', 'shezar_certification');

        // Remove the columns that we are going to replace with certification versions.
        foreach ($columnoptions as $key => $columnoption) {
            if ($columnoption->type == 'progcompletion' &&
                in_array($columnoption->value, array('status', 'iscomplete', 'isnotcomplete', 'isinprogress', 'isnotstarted'))) {
                unset($columnoptions[$key]);
            }
        }

        // Add back the columns that were just removed, but suitable for certifications.
        $columnoptions[] = new rb_column_option(
            'certcompletion',
            'status',
            get_string('status', 'rb_source_dp_certification'),
            'certif_completion.status',
            array(
                'joins' => 'certif_completion',
                'displayfunc' => 'certif_status',
            )
        );
        $columnoptions[] = new rb_column_option(
            'certcompletion',
            'iscertified',
            get_string('iscertified', 'rb_source_certification_completion'),
            'CASE WHEN certif_completion.certifpath = ' . CERTIFPATH_RECERT . ' THEN 1 ELSE 0 END',
            array(
                'joins' => 'certif_completion',
                'displayfunc' => 'yes_or_no',
                'dbdatatype' => 'boolean',
                'defaultheading' => get_string('iscertified', 'rb_source_certification_completion'),
            )
        );
        $columnoptions[] = new rb_column_option(
            'certcompletion',
            'isnotcertified',
            get_string('isnotcertified', 'rb_source_certification_completion'),
            'CASE WHEN certif_completion.certifpath <> ' . CERTIFPATH_RECERT . ' THEN 1 ELSE 0 END',
            array(
                'joins' => 'certif_completion',
                'displayfunc' => 'yes_or_no',
                'dbdatatype' => 'boolean',
                'defaultheading' => get_string('isnotcertified', 'rb_source_certification_completion'),
            )
        );
        $columnoptions[] = new rb_column_option(
            'certcompletion',
            'isinprogress',
            get_string('isinprogress', 'rb_source_program_completion'),
            'CASE WHEN certif_completion.status = ' . CERTIFSTATUS_INPROGRESS . ' THEN 1 ELSE 0 END',
            array(
                'joins' => 'certif_completion',
                'displayfunc' => 'yes_or_no',
                'dbdatatype' => 'boolean',
                'defaultheading' => get_string('isinprogress', 'rb_source_program_completion'),
            )
        );
        $columnoptions[] = new rb_column_option(
            'certcompletion',
            'isnotstarted',
            get_string('isnotstarted', 'rb_source_program_completion'),
            'CASE WHEN certif_completion.status = ' . CERTIFSTATUS_ASSIGNED . ' THEN 1 ELSE 0 END',
            array(
                'joins' => 'certif_completion',
                'displayfunc' => 'yes_or_no',
                'dbdatatype' => 'boolean',
                'defaultheading' => get_string('isnotstarted', 'rb_source_program_completion'),
            )
        );
        $columnoptions[] = new rb_column_option(
            'certcompletion',
            'hasnevercertified',
            get_string('hasnevercertified', 'rb_source_certification_completion'),
            'CASE WHEN certif_completion.status = ' . CERTIFSTATUS_ASSIGNED . ' OR
                       (certif_completion.status = ' . CERTIFSTATUS_INPROGRESS . ' AND
                        certif_completion.renewalstatus = ' . CERTIFRENEWALSTATUS_NOTDUE . ') THEN 1 ELSE 0 END',
            array(
                'joins' => 'certif_completion',
                'displayfunc' => 'yes_or_no',
                'dbdatatype' => 'boolean',
                'defaultheading' => get_string('hasnevercertified', 'rb_source_certification_completion'),
            )
        );

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = parent::define_filteroptions();

        $this->add_certification_fields_to_filters($filteroptions, 'shezar_certification');

        // Remove the filters that we are going to replace with certification versions.
        foreach ($filteroptions as $key => $filteroption) {
            if ($filteroption->type == 'progcompletion' &&
                in_array($filteroption->value, array('status', 'iscomplete', 'isnotcomplete', 'isinprogress', 'isnotstarted'))) {
                unset($filteroptions[$key]);
            }
        }

        // Add back the filters that were just removed, but suitable for certifications.
        $filteroptions[] = new rb_filter_option(
            'certcompletion',
            'status',
            get_string('status', 'rb_source_dp_certification'),
            'select',
            array(
                'selectfunc' => 'status',
                'attributes' => rb_filter_option::select_width_limiter(),
            )
        );
        $filteroptions[] = new rb_filter_option(
            'certcompletion',
            'iscertified',
            get_string('iscertified', 'rb_source_certification_completion'),
            'select',
            array(
                'selectfunc' => 'yesno_list',
                'simplemode' => true,
            )
        );
        $filteroptions[] = new rb_filter_option(
            'certcompletion',
            'isnotcertified',
            get_string('isnotcertified', 'rb_source_certification_completion'),
            'select',
            array(
                'selectfunc' => 'yesno_list',
                'simplemode' => true,
            )
        );
        $filteroptions[] = new rb_filter_option(
            'certcompletion',
            'isinprogress',
            get_string('isinprogress', 'rb_source_program_completion'),
            'select',
            array(
                'selectfunc' => 'yesno_list',
                'simplemode' => true,
            )
        );
        $filteroptions[] = new rb_filter_option(
            'certcompletion',
            'isnotstarted',
            get_string('isnotstarted', 'rb_source_program_completion'),
            'select',
            array(
                'selectfunc' => 'yesno_list',
                'simplemode' => true,
            )
        );

        return $filteroptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'user',
                'value' => 'namelink',
            ),
            array(
                'type' => 'prog',
                'value' => 'proglinkicon',
            ),
            array(
                'type' => 'certcompletion',
                'value' => 'status',
            ),
            array(
                'type' => 'progcompletion',
                'value' => 'duedate',
            ),
        );
        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'prog',
                'value' => 'fullname',
                'advanced' => 0,
            ),
            array(
                'type' => 'user',
                'value' => 'fullname',
                'advanced' => 0,
            ),
            array(
                'type' => 'certcompletion',
                'value' => 'status',
                'advanced' => 0,
            ),
        );
        return $defaultfilters;
    }

    public function rb_filter_status() {
        global $CERTIFSTATUS;

        $out = array();
        foreach ($CERTIFSTATUS as $code => $statusstring) {
            $out[$code] = get_string($statusstring, 'shezar_certification');
        }
        return $out;
    }

    /**
     * Certification display the certification status as string.
     *
     * @param string $status    CERTIFSTATUS_X constant to describe the status of the certification.
     * @param array $row        The record used to generate the table row
     * @return string
     */
    public function rb_display_certif_status($status, $row) {
        global $CERTIFSTATUS;

        $strstatus = '';
        if ($status && isset($CERTIFSTATUS[$status])) {
            $strstatus = get_string($CERTIFSTATUS[$status], 'shezar_certification');
        }
        return $strstatus;
    }
}
