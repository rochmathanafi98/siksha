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
 * @author Alastair Munro <alastair.munro@shezarlms.com>
 * @package mod_facetoface
 */

defined('MOODLE_INTERNAL') || die();

class rb_source_facetoface_interest extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $sourcetitle;

    public function __construct($groupid, rb_global_restriction_set $globalrestrictionset = null) {
        if ($groupid instanceof rb_global_restriction_set) {
            throw new coding_exception('Wrong parameter orders detected during report source instantiation.');
        }
        // Remember the active global restriction set.
        $this->globalrestrictionset = $globalrestrictionset;

        // Apply global user restrictions.
        $this->add_global_report_restriction_join('base', 'userid');

        $this->base = '{facetoface_interest}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_facetoface_interest');
        parent::__construct();
    }

    /**
     * Global report restrictions are implemented in this source.
     * @return boolean
     */
    public function global_restrictions_supported() {
        return true;
    }

    //
    //
    // Methods for defining contents of source.
    //
    //

    protected function define_joinlist() {
        global $CFG;
        require_once($CFG->dirroot .'/mod/facetoface/lib.php');

        // Joinlist for this source.
        $joinlist = array(
            new rb_join(
                'sessions',
                'LEFT',
                '{facetoface_sessions}',
                'sessions.facetoface = facetoface.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'sessiondate',
                'INNER',
                '{facetoface_sessions_dates}',
                '(sessiondate.sessionid = sessions.id)',
                REPORT_BUILDER_RELATION_ONE_TO_MANY,
                'sessions'
            ),
            new rb_join(
                'facetoface',
                'INNER',
                '{facetoface}',
                'facetoface.id = base.facetoface',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
        );

        // Include some standard joins.
        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_course_table_to_joinlist($joinlist, 'facetoface', 'course');
        $this->add_course_category_table_to_joinlist($joinlist, 'course', 'category');
        $this->add_job_assignment_tables_to_joinlist($joinlist, 'base', 'userid');

        return $joinlist;
    }

    protected function define_columnoptions() {
        $columnoptions = array(
            new rb_column_option(
                'facetoface',
                'name',
                get_string('ftfname', 'rb_source_facetoface_sessions'),
                'facetoface.name',
                array('joins' => 'facetoface',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'facetoface',
                'namelink',
                get_string('ftfnamelink', 'rb_source_facetoface_sessions'),
                "facetoface.name",
                array(
                    'joins' => array('facetoface'),
                    'displayfunc' => 'link_f2f',
                    'defaultheading' => get_string('ftfname', 'rb_source_facetoface_sessions'),
                    'extrafields' => array('activity_id' => 'facetoface.id'),
                )
            ),
            new rb_column_option(
                'facetoface',
                'timedeclared',
                get_string('declareinterestreportdate', 'rb_source_facetoface_interest'),
                'base.timedeclared',
                array(
                    'displayfunc' => 'nice_date', // Do not mess with timezones here.
                )
            ),
            new rb_column_option(
                'facetoface',
                'reason',
                get_string('declareinterestreportreason', 'rb_source_facetoface_interest'),
                'base.reason',
                array(
                    'dbdatatype' => 'char',
                    'outputformat' => 'text',
                )
            ),
        );

        // Include some standard columns.
        $this->add_user_fields_to_columns($columnoptions);
        $this->add_job_assignment_fields_to_columns($columnoptions);
        $this->add_course_fields_to_columns($columnoptions);
        $this->add_course_category_fields_to_columns($columnoptions);
        // Redirect the display of 'user' columns (to insert 'unassigned' when needed).
        foreach ($columnoptions as $key => $columnoption) {
            if (!($columnoption->type == 'user' && $columnoption->value == 'fullname')) {
                continue;
            }
            $columnoptions[$key]->extrafields = array('user_id' => 'auser.id');
            $columnoptions[$key]->displayfunc = 'user';
        }

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = array(
            new rb_filter_option(
                'facetoface',
                'name',
                get_string('ftfname', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'facetoface',
                'reason',
                get_string('declareinterestreportreason', 'rb_source_facetoface_interest'),
                'text'
            ),
            new rb_filter_option(
                'facetoface',
                'timedeclared',
                get_string('declareinterestreportdate', 'rb_source_facetoface_interest'),
                'date',
                array('includetime' => true)
            ),
        );

        // Include some standard filters.
        $this->add_user_fields_to_filters($filteroptions);
        $this->add_job_assignment_fields_to_filters($filteroptions);
        $this->add_course_fields_to_filters($filteroptions);
        $this->add_course_category_fields_to_filters($filteroptions);

        return $filteroptions;
    }

    protected function define_contentoptions() {
        $contentoptions = array();

        // Add the manager/position/organisation content options.
        $this->add_basic_user_content_options($contentoptions);

        $contentoptions[] = new rb_content_option(
            'date',
            get_string('thedate', 'rb_source_facetoface_interest'),
            'sessiondate.timestart',
            'sessiondate'
        );

        return $contentoptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
                new rb_param_option(
                    'facetofaceid',
                    'base.facetoface'
                ),
        );

        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'facetoface',
                'value' => 'namelink',
            ),
            array(
                'type' => 'user',
                'value' => 'namelink',
            ),
            array(
                'type' => 'user',
                'value' => 'email',
            ),
            array(
                'type' => 'facetoface',
                'value' => 'timedeclared',
            ),
            array(
                'type' => 'facetoface',
                'value' => 'reason',
            ),
        );

        return $defaultcolumns;
    }

    // Convert a f2f activity name into a link to that activity.
    public function rb_display_link_f2f($name, $row) {
        global $OUTPUT;
        $activityid = $row->activity_id;
        return $OUTPUT->action_link(new moodle_url('/mod/facetoface/view.php', array('f' => $activityid)), $name);
    }
}
