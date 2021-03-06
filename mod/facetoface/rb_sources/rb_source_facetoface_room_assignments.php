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
 * @author Valerii Kuznetsov <valerii.kuznetsov@shezarlms.com>
 * @package mod_facetoface
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/facetoface/rb_sources/rb_facetoface_base_source.php');
require_once($CFG->dirroot . '/shezar/customfield/field/location/define.class.php');

class rb_source_facetoface_room_assignments extends rb_facetoface_base_source
{
    public function __construct($groupid, rb_global_restriction_set $globalrestrictionset = null) {
        if ($groupid instanceof rb_global_restriction_set) {
            throw new coding_exception('Wrong parameter orders detected during report source instantiation.');
        }
        // Remember the active global restriction set.
        $this->globalrestrictionset = $globalrestrictionset;

        // Global report restrictions are applied in define_joinlist() method.

        $this->base = '{facetoface_sessions_dates}';
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_facetoface_room_assignments');
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->add_customfields();

        parent::__construct();
    }

    protected function define_joinlist() {
        $joinlist = array();

        $namesconactsql = sql_group_concat(sql_cast2char('fa.name'), ',');
        $joinlist[] = new rb_join(
            'asset',
            'LEFT',
            "(SELECT fad.sessionsdateid AS sessionsdateid, $namesconactsql AS names
              FROM {facetoface_asset_dates} fad
              INNER JOIN {facetoface_asset} fa ON (fad.assetid = fa.id)
              GROUP BY fad.sessionsdateid
             )",
            '(base.id = asset.sessionsdateid)',
            REPORT_BUILDER_RELATION_ONE_TO_MANY
        );

        $this->add_session_common_to_joinlist($joinlist);
        $this->add_session_status_to_joinlist($joinlist);
        $this->add_course_table_to_joinlist($joinlist, 'facetoface', 'course');

        return $joinlist;
    }

    protected function define_columnoptions() {
        $columnoptions = array();

        $this->add_course_fields_to_columns($columnoptions);
        $this->add_facetoface_common_to_columns($columnoptions);

        $columnoptions[] = new rb_column_option(
            'asset',
            'assetnamelist',
            get_string('assetnames', 'rb_source_facetoface_room_assignments'),
            "asset.names",
            array(
                'joins' => 'asset',
            )
        );

        $this->add_session_common_to_columns($columnoptions);
        $this->add_session_status_to_columns($columnoptions);
        $this->add_rooms_fields_to_columns($columnoptions, 'room');

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = array();

        $this->add_course_fields_to_filters($filteroptions);

        $filteroptions[] = new rb_filter_option(
            'facetoface',
            'facetofaceid',
            get_string('ftfid', 'rb_source_facetoface_room_assignments'),
            'number'
        );

        $filteroptions[] = new rb_filter_option(
            'facetoface',
            'name',
            get_string('ftfname', 'rb_source_facetoface_sessions'),
            'text'
        );

        $filteroptions[] = new rb_filter_option(
            'asset',
            'assetnamelist',
            get_string('assetnames', 'rb_source_facetoface_room_assignments'),
            'text'
        );

        $filteroptions[] = new rb_filter_option(
            'date',
            'sessionstartdate',
            get_string('sessstartdatetime', 'rb_source_facetoface_room_assignments'),
            'date'
        );

        $filteroptions[] = new rb_filter_option(
            'date',
            'sessionfinishdate',
            get_string('sessfinishdatetime', 'rb_source_facetoface_room_assignments'),
            'date'
        );

        $filteroptions[] = new rb_filter_option(
            'room',
            'roomavailable',
            get_string('roomavailable', 'rb_source_facetoface_rooms'),
            'f2f_roomavailable',
            array(),
            'room.id',
            array('room')
        );
        $this->add_rooms_fields_to_filters($filteroptions);

        return $filteroptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option(
                'roomid',
                'base.roomid',
                'room'
            )
        );

        return $paramoptions;
    }

    protected function define_contentoptions() {
        $contentoptions = array(
            new rb_content_option(
                'date',
                get_string('thedate', 'rb_source_facetoface_sessions'),
                'base.timestart'
            )
        );
        return $contentoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'course',
                'value' => 'courselink'
            ),
            array(
                'type' => 'facetoface',
                'value' => 'namelink'
            ),
            array(
                'type' => 'room',
                'value' => 'name'
            ),
            array(
                'type' => 'room',
                'value' => 'capacity'
            ),
            array(
                'type' => 'date',
                'value' => 'sessionstartdate'
            )
        );

        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'course',
                'value' => 'name'
            ),
            array(
                'type' => 'facetoface',
                'value' => 'name'
            ),
            array(
                'type' => 'date',
                'value' => 'sessionstartdate'
            ),
            array(
                'type' => 'date',
                'value' => 'sessionfinishdate'
            ),
            array(
                'type' => 'room',
                'value' => 'name'
            ),
            array(
                'type' => 'room',
                'value' => 'visible'
            )
        );

        return $defaultfilters;
    }

    protected function add_customfields() {
        $this->add_custom_fields_for(
            'facetoface_room',
            'room',
            'facetofaceroomid',
            $this->joinlist,
            $this->columnoptions,
            $this->filteroptions
        );

        $this->add_custom_fields_for(
            'facetoface_session',
            'sessions',
            'facetofacesessionid',
            $this->joinlist,
            $this->columnoptions,
            $this->filteroptions);
    }

    public function global_restrictions_supported() {
        return true;
    }
}