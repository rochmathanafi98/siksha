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

class rb_source_facetoface_rooms extends rb_facetoface_base_source
{
    /**
     * Url of embedded report required for certain actions
     * @var string
     */
    protected $embeddedurl = '';

    /**
     * Report url params to pass through during actions
     * @var array
     */
    protected $urlparams = array();

    public function __construct(rb_global_restriction_set $globalrestrictionset = null) {

        $this->base = '{facetoface_room}';
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_facetoface_rooms');
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->paramoptions = $this->define_paramoptions();
        $this->add_customfields();

    parent::__construct();
    }

    protected function define_joinlist() {
        $joinlist = array();

        $joinlist[] = new rb_join(
            'assigned',
            'LEFT',
            '(SELECT roomid, COUNT(*) AS cntdates FROM {facetoface_sessions_dates} GROUP BY roomid)',
            'assigned.roomid = base.id',
            REPORT_BUILDER_RELATION_ONE_TO_MANY
        );

        // Required by room availability.
        $joinlist[] = new rb_join(
            'sessions',
            'INNER',
            '{facetoface_sessions}',
            'base.id = sessiondates.sessionid',
            REPORT_BUILDER_RELATION_ONE_TO_MANY,
            'sessiondates'
        );
        $joinlist[] = new rb_join(
            'sessiondates',
            'INNER',
            '{facetoface_sessions_dates}',
            'base.id = sessiondates.roomid',
            REPORT_BUILDER_RELATION_ONE_TO_MANY
        );

        return $joinlist;
    }

    protected function define_columnoptions() {
        $columnoptions = array();

        $this->add_rooms_fields_to_columns($columnoptions, 'base');

        $columnoptions[] = new rb_column_option(
            'room',
            'actions',
            get_string('actions'),
            'base.id',
            array(
                'noexport' => true,
                'nosort' => true,
                'joins' => 'assigned',
                'capability' => 'shezar/core:modconfig',
                'extrafields' => array('hidden' => 'base.hidden', 'cntdates' => 'assigned.cntdates'),
                'displayfunc' => 'actions',
                'hidden' => false
            )
        );

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = array();

        $this->add_rooms_fields_to_filters($filteroptions);

        $filteroptions[] = new rb_filter_option(
            'room',
            'roomavailable',
            get_string('roomavailable', 'rb_source_facetoface_rooms'),
            'f2f_roomavailable',
            array(),
            'base.id'
        );

        return $filteroptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'room',
                'value' => 'namelink'
            ),
            array(
                'type' => 'room',
                'value' => 'description'
            ),
            array(
                'type' => 'room',
                'value' => 'visible'
            ),
            array(
                'type' => 'room',
                'value' => 'allowconflicts'
            )
        );

        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'room',
                'value' => 'name'
            )
        );

        return $defaultfilters;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option('custom', 'base.custom')
        );

        return $paramoptions;
    }

    protected function add_customfields() {
        $this->add_custom_fields_for(
            'facetoface_room',
            'base',
            'facetofaceroomid',
            $this->joinlist,
            $this->columnoptions,
            $this->filteroptions
        );
    }

    public function post_params(reportbuilder $report) {
        $this->embeddedurl = $report->embeddedurl;
        $this->urlparams = $report->get_current_url_params();
    }

    /**
     * Room name
     *
     * @param int $roomid
     * @param stdClass $row
     * @param bool $isexport
     */
    public function rb_display_actions($roomid, $row, $isexport = false) {
        global $OUTPUT;

        if ($isexport) {
            return null;
        }

        $output = array();

        $output[] = $OUTPUT->action_icon(
            new moodle_url('/mod/facetoface/room.php', array('roomid' => $roomid)),
            new pix_icon('t/calendar', get_string('details', 'mod_facetoface'))
        );

        $output[] = $OUTPUT->action_icon(
            new moodle_url('/mod/facetoface/room/edit.php', array('id' => $roomid)),
            new pix_icon('t/edit', get_string('edit'))
        );

        if ($row->hidden && $this->embeddedurl) {
            $params = array_merge($this->urlparams, array('show' => $roomid, 'sesskey' => sesskey()));
            $output[] = $OUTPUT->action_icon(
                new moodle_url($this->embeddedurl, $params),
                new pix_icon('t/show', get_string('roomshow', 'mod_facetoface'))
            );
        } else if ($this->embeddedurl) {
            $params = array_merge($this->urlparams, array('hide' => $roomid, 'sesskey' => sesskey()));
            $output[] = $OUTPUT->action_icon(
                new moodle_url($this->embeddedurl, $params),
                new pix_icon('t/hide', get_string('roomhide', 'mod_facetoface'))
            );

        }
        if ($row->cntdates) {
            $output[] = $OUTPUT->pix_icon('t/delete_gray', get_string('currentlyassigned', 'mod_facetoface'), 'moodle', array('class' => 'disabled iconsmall'));
        } else {
            $output[] = $OUTPUT->action_icon(
                new moodle_url('/mod/facetoface/room/manage.php', array('delete' => $roomid)),
                new pix_icon('t/delete', get_string('delete'))
            );
        }

        return implode('', $output);
    }

    public function global_restrictions_supported() {
        return true;
    }
}
