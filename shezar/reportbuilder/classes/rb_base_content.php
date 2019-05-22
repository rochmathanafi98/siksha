<?php // $Id$
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

/**
 * Abstract base content class to be extended to create report builder
 * content restrictions. This file also contains some core content restrictions
 * that can be used by any report builder source
 *
 * Defines the properties and methods required by content restrictions
 */
abstract class rb_base_content {

    public $reportfor;

    /*
     * @param integer $reportfor User ID to determine who the report is for
     *                           Typically this will be $USER->id, except
     *                           in the case of scheduled reports run by cron
     */
    public function __construct($reportfor=null) {
        $this->reportfor = $reportfor;
    }

    /*
     * All sub classes must define the following functions
     */
    abstract public function sql_restriction($fields, $reportid);
    abstract public function text_restriction($title, $reportid);
    abstract public function form_template(&$mform, $reportid, $title);
    abstract public function form_process($reportid, $fromform);

}

///////////////////////////////////////////////////////////////////////////


/**
 * Restrict content by a position ID
 *
 * Pass in an integer that represents the position ID
 */
class rb_current_pos_content extends rb_base_content {

    // Define some constants for the selector options.
    const CONTENT_POS_EQUAL = 0;
    const CONTENT_POS_EQUALANDBELOW = 1;
    const CONTENT_POS_BELOW = 2;

    /**
     * Generate the SQL to apply this content restriction
     *
     * @param string $field SQL field to apply the restriction against
     * @param integer $reportid ID of the report
     *
     * @return array containing SQL snippet to be used in a WHERE clause, as well as array of SQL params
     */
    public function sql_restriction($field, $reportid) {
        global $CFG, $DB;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);
        $restriction = $settings['recursive'];
        $userid = $this->reportfor;

        $viewparam = $DB->get_unique_param('viewid');
        $params = array($viewparam => $userid);

        // Set up the base joins and where clause for the restrictions.
        $joinsql = " SELECT 1
                       FROM {job_assignment} u1ja
                 INNER JOIN {pos} p1
                         ON u1ja.positionid = p1.id";
        $wheresql = " WHERE u1ja.userid = :{$viewparam}";

        switch ($restriction) {
            case self::CONTENT_POS_EQUAL:
                $joinsql .= " LEFT JOIN {job_assignment} u2ja
                                     ON u2ja.positionid = p1.id";

                $wheresql .= " AND u2ja.userid = {$field}";
                break;
            case self::CONTENT_POS_BELOW:
                $joinsql .= " LEFT JOIN {pos} p2
                                     ON p2.path LIKE " . $DB->sql_concat('p1.path', "'/%'") . "
                              LEFT JOIN {job_assignment} u3ja
                                     ON u3ja.positionid = p2.id";

                $wheresql .= " AND u3ja.userid = {$field} ";
                break;
            case self::CONTENT_POS_EQUALANDBELOW:
                $joinsql .= " LEFT JOIN {job_assignment} u2ja
                                     ON u2ja.positionid = p1.id
                              LEFT JOIN {pos} p2
                                     ON p2.path LIKE " . $DB->sql_concat('p1.path', "'/%'") . "
                              LEFT JOIN {job_assignment} u3ja
                                     ON u3ja.positionid = p2.id";

                $wheresql .= " AND (u2ja.userid = {$field} OR u3ja.userid = {$field})";
                break;
        }

        $sql = "EXISTS({$joinsql}{$wheresql})";
        return array($sql, $params);
    }

    /**
     * Generate a human-readable text string describing the restriction
     *
     * @param string $title Name of the field being restricted
     * @param integer $reportid ID of the report
     *
     * @return string Human readable description of the restriction
     */
    public function text_restriction($title, $reportid) {
        global $DB;

        $userid = $this->reportfor;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);

        switch ($settings['recursive']) {
            case self::CONTENT_POS_EQUAL:
                return get_string('contentdesc_posequal', 'shezar_reportbuilder');
            case self::CONTENT_POS_EQUALANDBELOW:
                return get_string('contentdesc_posboth', 'shezar_reportbuilder');
            case self::CONTENT_POS_BELOW:
                return get_string('contentdesc_posbelow', 'shezar_reportbuilder');
            default:
                return '';
        }
    }

    /**
     * Adds form elements required for this content restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     * @param string $title Name of the field the restriction is acting on
     */
    public function form_template(&$mform, $reportid, $title) {
        // get current settings
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');
        $recursive = reportbuilder::get_setting($reportid, $type, 'recursive');

        $mform->addElement('header', 'current_pos_header',
            get_string('showbyx', 'shezar_reportbuilder', lcfirst($title)));
        $mform->setExpanded('current_pos_header');
        $mform->addElement('checkbox', 'current_pos_enable', '',
            get_string('currentposenable', 'shezar_reportbuilder'));
        $mform->setDefault('current_pos_enable', $enable);
        $mform->disabledIf('current_pos_enable', 'contentenabled', 'eq', 0);
        $radiogroup = array();
        $radiogroup[] =& $mform->createElement('radio', 'current_pos_recursive',
            '', get_string('showrecordsinposandbelow', 'shezar_reportbuilder'), self::CONTENT_POS_EQUALANDBELOW);
        $radiogroup[] =& $mform->createElement('radio', 'current_pos_recursive',
            '', get_string('showrecordsinpos', 'shezar_reportbuilder'), self::CONTENT_POS_EQUAL);
        $radiogroup[] =& $mform->createElement('radio', 'current_pos_recursive',
            '', get_string('showrecordsbelowposonly', 'shezar_reportbuilder'), self::CONTENT_POS_BELOW);
        $mform->addGroup($radiogroup, 'current_pos_recursive_group',
            get_string('includechildpos', 'shezar_reportbuilder'), html_writer::empty_tag('br'), false);
        $mform->setDefault('current_pos_recursive', $recursive);
        $mform->disabledIf('current_pos_recursive_group', 'contentenabled', 'eq', 0);
        $mform->disabledIf('current_pos_recursive_group', 'current_pos_enable', 'notchecked');
        $mform->addHelpButton('current_pos_header', 'reportbuildercurrentpos', 'shezar_reportbuilder');
    }

    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    public function form_process($reportid, $fromform) {
        $status = true;
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);

        // enable checkbox option
        $enable = (isset($fromform->current_pos_enable) &&
            $fromform->current_pos_enable) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'enable', $enable);

        // recursive radio option
        $recursive = isset($fromform->current_pos_recursive) ?
            $fromform->current_pos_recursive : 0;

        $status = $status && reportbuilder::update_setting($reportid, $type,
            'recursive', $recursive);

        return $status;
    }
}


/**
 * Restrict content by an organisation ID
 *
 * Pass in an integer that represents the organisation ID
 */
class rb_current_org_content extends rb_base_content {

    // Define some constants for the selector options.
    const CONTENT_ORG_EQUAL = 0;
    const CONTENT_ORG_EQUALANDBELOW = 1;
    const CONTENT_ORG_BELOW = 2;

    /**
     * Generate the SQL to apply this content restriction
     *
     * @param string $field SQL field to apply the restriction against
     * @param integer $reportid ID of the report
     *
     * @return array containing SQL snippet to be used in a WHERE clause, as well as array of SQL params
     */
    public function sql_restriction($field, $reportid) {
        global $CFG, $DB;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);
        $restriction = $settings['recursive'];
        $userid = $this->reportfor;

        $viewparam = $DB->get_unique_param('viewid');
        $params = array($viewparam => $userid);

        // Set up the base joins and where clause for the restrictions.
        $joinsql = " SELECT 1
                       FROM {job_assignment} u1ja
                 INNER JOIN {org} o1
                         ON u1ja.organisationid = o1.id";
        $wheresql = " WHERE u1ja.userid = :{$viewparam}";
        switch ($restriction) {
            case self::CONTENT_ORG_EQUAL:
                $joinsql .= " LEFT JOIN {job_assignment} u2ja
                                ON u2ja.organisationid = o1.id";

                $wheresql .= " AND u2ja.userid = {$field}";
                break;
            case self::CONTENT_ORG_BELOW:
                $joinsql .= " LEFT JOIN {org} o2
                                ON o2.path LIKE " . $DB->sql_concat('o1.path', "'/%'") . "
                         LEFT JOIN {job_assignment} u3ja
                                ON u3ja.organisationid = o2.id";

                $wheresql .= " AND u3ja.userid = {$field} ";
                break;
            case self::CONTENT_ORG_EQUALANDBELOW:
                $joinsql .= " LEFT JOIN {job_assignment} u2ja
                                ON u2ja.organisationid = o1.id
                         LEFT JOIN {org} o2
                                ON o2.path LIKE " . $DB->sql_concat('o1.path', "'/%'") . "
                         LEFT JOIN {job_assignment} u3ja
                                ON u3ja.organisationid = o2.id";

                $wheresql .= " AND (u2ja.userid = {$field} OR u3ja.userid = {$field})";
                break;
        }

        $sql = "EXISTS({$joinsql}{$wheresql})";
        return array($sql, $params);
    }

    /**
     * Generate a human-readable text string describing the restriction
     *
     * @param string $title Name of the field being restricted
     * @param integer $reportid ID of the report
     *
     * @return string Human readable description of the restriction
     */
    public function text_restriction($title, $reportid) {
        global $DB;

        $userid = $this->reportfor;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);

        switch ($settings['recursive']) {
            case self::CONTENT_ORG_EQUAL:
                return get_string('contentdesc_orgequal', 'shezar_reportbuilder');
            case self::CONTENT_ORG_EQUALANDBELOW:
                return get_string('contentdesc_orgboth', 'shezar_reportbuilder');
            case self::CONTENT_ORG_BELOW:
                return get_string('contentdesc_orgbelow', 'shezar_reportbuilder');
            default:
                return '';
        }
    }


    /**
     * Adds form elements required for this content restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     * @param string $title Name of the field the restriction is acting on
     */
    public function form_template(&$mform, $reportid, $title) {
        // get current settings
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');
        $recursive = reportbuilder::get_setting($reportid, $type, 'recursive');

        $mform->addElement('header', 'current_org_header',
            get_string('showbyx', 'shezar_reportbuilder', lcfirst($title)));
        $mform->setExpanded('current_org_header');
        $mform->addElement('checkbox', 'current_org_enable', '',
            get_string('currentorgenable', 'shezar_reportbuilder'));
        $mform->setDefault('current_org_enable', $enable);
        $mform->disabledIf('current_org_enable', 'contentenabled', 'eq', 0);
        $radiogroup = array();
        $radiogroup[] =& $mform->createElement('radio', 'current_org_recursive',
            '', get_string('showrecordsinorgandbelow', 'shezar_reportbuilder'), self::CONTENT_ORG_EQUALANDBELOW);
        $radiogroup[] =& $mform->createElement('radio', 'current_org_recursive',
            '', get_string('showrecordsinorg', 'shezar_reportbuilder'), self::CONTENT_ORG_EQUAL);
        $radiogroup[] =& $mform->createElement('radio', 'current_org_recursive',
            '', get_string('showrecordsbeloworgonly', 'shezar_reportbuilder'), self::CONTENT_ORG_BELOW);
        $mform->addGroup($radiogroup, 'current_org_recursive_group',
            get_string('includechildorgs', 'shezar_reportbuilder'), html_writer::empty_tag('br'), false);
        $mform->setDefault('current_org_recursive', $recursive);
        $mform->disabledIf('current_org_recursive_group', 'contentenabled',
            'eq', 0);
        $mform->disabledIf('current_org_recursive_group', 'current_org_enable',
            'notchecked');
        $mform->addHelpButton('current_org_header', 'reportbuildercurrentorg', 'shezar_reportbuilder');
    }


    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    public function form_process($reportid, $fromform) {
        $status = true;
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);

        // enable checkbox option
        $enable = (isset($fromform->current_org_enable) &&
            $fromform->current_org_enable) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'enable', $enable);

        // recursive radio option
        $recursive = isset($fromform->current_org_recursive) ?
            $fromform->current_org_recursive : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'recursive', $recursive);

        return $status;
    }
}


/*
 * Restrict content by an organisation at time of completion
 *
 * Pass in an integer that represents an organisation ID
 */
class rb_completed_org_content extends rb_base_content {
    const CONTENT_ORGCOMP_EQUAL = 0;
    const CONTENT_ORGCOMP_EQUALANDBELOW = 1;
    const CONTENT_ORGCOMP_BELOW = 2;

    /**
     * Generate the SQL to apply this content restriction
     *
     * @param string $field SQL field to apply the restriction against
     * @param integer $reportid ID of the report
     *
     * @return array containing SQL snippet to be used in a WHERE clause, as well as array of SQL params
     */
    public function sql_restriction($field, $reportid) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/shezar/hierarchy/lib.php');
        require_once($CFG->dirroot . '/shezar/hierarchy/prefix/position/lib.php');

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);
        $restriction = $settings['recursive'];
        $userid = $this->reportfor;

        // get the user's primary organisation path
        $orgpaths = $DB->get_fieldset_sql(
            "SELECT o.path
               FROM {job_assignment} ja
               JOIN {org} o ON ja.organisationid = o.id
              WHERE ja.userid = ?",
              array($userid));

        // we need the user to have a valid organisation path
        if (empty($orgpaths)) {
            // using 1=0 instead of FALSE for MSSQL support
            return array('1=0', array());
        }

        $constraints = array();
        $params = array();
        switch ($restriction) {
            case self::CONTENT_ORGCOMP_EQUAL:
                foreach ($orgpaths as $orgpath) {
                    $paramname = rb_unique_param('ccor');
                    $constraints[] = "$field = :$paramname";
                    $params[$paramname] = $orgpath;
                }
                break;
            case self::CONTENT_ORGCOMP_BELOW:
                foreach ($orgpaths as $orgpath) {
                    $paramname = rb_unique_param('ccor');
                    $constraints[] = $DB->sql_like($field, ":{$paramname}");
                    $params[$paramname] = $DB->sql_like_escape($orgpath) . '/%';
                }
                break;
            case self::CONTENT_ORGCOMP_EQUALANDBELOW:
                foreach ($orgpaths as $orgpath) {
                    $paramname = rb_unique_param('ccor1');
                    $constraints[] = "$field = :{$paramname}";
                    $params[$paramname] = $orgpath;

                    $paramname = rb_unique_param('ccors');
                    $constraints[] = $DB->sql_like($field, ":$paramname");
                    $params[$paramname] = $DB->sql_like_escape($orgpath) . '/%';
                }
                break;
        }
        $sql = implode(' OR ', $constraints);

        return array("({$sql})", $params);
    }

    /**
     * Generate a human-readable text string describing the restriction
     *
     * @param string $title Name of the field being restricted
     * @param integer $reportid ID of the report
     *
     * @return string Human readable description of the restriction
     */
    public function text_restriction($title, $reportid) {
        global $DB;

        $userid = $this->reportfor;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);

        $orgid = $DB->get_field('job_assignment', 'organisationid', array('userid' => $userid, 'sortorder' => 1));
        if (empty($orgid)) {
            return $title . ' ' . get_string('is', 'shezar_reportbuilder') . ' "UNASSIGNED"';
        }
        $orgname = $DB->get_field('org', 'fullname', array('id' => $orgid));

        switch ($settings['recursive']) {
            case self::CONTENT_ORGCOMP_EQUAL:
                return $title . ' ' . get_string('is', 'shezar_reportbuilder') .
                    ': "' . $orgname . '"';
            case self::CONTENT_ORGCOMP_EQUALANDBELOW:
                return $title . ' ' . get_string('is', 'shezar_reportbuilder') .
                    ': "' . $orgname . '" ' . get_string('orsuborg', 'shezar_reportbuilder');
            case self::CONTENT_ORGCOMP_BELOW:
                return $title . ' ' . get_string('isbelow', 'shezar_reportbuilder') .
                    ': "' . $orgname . '"';
            default:
                return '';
        }
    }


    /**
     * Adds form elements required for this content restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     * @param string $title Name of the field the restriction is acting on
     */
    public function form_template(&$mform, $reportid, $title) {
        // get current settings
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');
        $recursive = reportbuilder::get_setting($reportid, $type, 'recursive');

        $mform->addElement('header', 'completed_org_header',
            get_string('showbyx', 'shezar_reportbuilder', lcfirst($title)));
        $mform->setExpanded('completed_org_header');
        $mform->addElement('checkbox', 'completed_org_enable', '',
            get_string('completedorgenable', 'shezar_reportbuilder'));
        $mform->setDefault('completed_org_enable', $enable);
        $mform->disabledIf('completed_org_enable', 'contentenabled', 'eq', 0);
        $radiogroup = array();
        $radiogroup[] =& $mform->createElement('radio', 'completed_org_recursive',
            '', get_string('showrecordsinorgandbelow', 'shezar_reportbuilder'), self::CONTENT_ORGCOMP_EQUALANDBELOW);
        $radiogroup[] =& $mform->createElement('radio', 'completed_org_recursive',
            '', get_string('showrecordsinorg', 'shezar_reportbuilder'), self::CONTENT_ORGCOMP_EQUAL);
        $radiogroup[] =& $mform->createElement('radio', 'completed_org_recursive',
            '', get_string('showrecordsbeloworgonly', 'shezar_reportbuilder'), self::CONTENT_ORGCOMP_BELOW);
        $mform->addGroup($radiogroup, 'completed_org_recursive_group',
            get_string('includechildorgs', 'shezar_reportbuilder'), html_writer::empty_tag('br'), false);
        $mform->setDefault('completed_org_recursive', $recursive);
        $mform->disabledIf('completed_org_recursive_group', 'contentenabled',
            'eq', 0);
        $mform->disabledIf('completed_org_recursive_group',
            'completed_org_enable', 'notchecked');
        $mform->addHelpButton('completed_org_header', 'reportbuildercompletedorg', 'shezar_reportbuilder');
    }


    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    public function form_process($reportid, $fromform) {
        $status = true;
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);

        // enable checkbox option
        $enable = (isset($fromform->completed_org_enable) &&
            $fromform->completed_org_enable) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'enable', $enable);

        // recursive radio option
        $recursive = isset($fromform->completed_org_recursive) ?
            $fromform->completed_org_recursive : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'recursive', $recursive);

        return $status;
    }
}


/*
 * Restrict content by a particular user or group of users
 */
class rb_user_content extends rb_base_content {

    const USER_OWN = 1;
    const USER_DIRECT_REPORTS = 2;
    const USER_INDIRECT_REPORTS = 4;
    const USER_TEMP_REPORTS = 8;

    /**
     * Generate the SQL to apply this content restriction.
     *
     * @param array $field      SQL field to apply the restriction against
     * @param integer $reportid ID of the report
     *
     * @return array containing SQL snippet to be used in a WHERE clause, as well as array of SQL params
     */
    public function sql_restriction($field, $reportid) {
        global $CFG, $DB;

        $userid = $this->reportfor;

        // remove rb_ from start of classname.
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);
        $restriction = isset($settings['who']) ? $settings['who'] : null;
        $userid = $this->reportfor;


        if (empty($restriction)) {
            return array(' (1 = 1) ', array());
        }

        $conditions = array();
        $params = array();

        $viewownrecord = ($restriction & self::USER_OWN) == self::USER_OWN;
        if ($viewownrecord) {
            $conditions[] = "{$field} = :self";
            $params['self'] = $userid;
        }

        if (($restriction & self::USER_DIRECT_REPORTS) == self::USER_DIRECT_REPORTS) {
            $conditions[] = "EXISTS (SELECT 1
                                       FROM {user} u1
                                 INNER JOIN {job_assignment} u1ja
                                         ON u1ja.userid = u1.id
                                 INNER JOIN {job_assignment} d1ja
                                         ON d1ja.managerjaid = u1ja.id
                                      WHERE u1.id = :viewer1
                                        AND d1ja.userid = {$field}
                                        AND d1ja.userid != u1.id
                                     )";
            $params['viewer1'] = $userid;
        }

        if (($restriction & self::USER_INDIRECT_REPORTS) == self::USER_INDIRECT_REPORTS) {
            $ilikesql = $DB->sql_concat('u2ja.managerjapath', "'/%'");
            $conditions[] = "EXISTS (SELECT 1
                                       FROM {user} u2
                                 INNER JOIN {job_assignment} u2ja
                                         ON u2ja.userid = u2.id
                                 INNER JOIN {job_assignment} i2ja
                                         ON i2ja.managerjapath LIKE {$ilikesql}
                                      WHERE u2.id = :viewer2
                                        AND i2ja.userid = {$field}
                                        AND i2ja.userid != u2.id
                                        AND i2ja.managerjaid != u2ja.id
                                    )";
            $params['viewer2'] = $userid;
        }

        if (($restriction & self::USER_TEMP_REPORTS) == self::USER_TEMP_REPORTS) {
            $conditions[] = "EXISTS (SELECT 1
                                       FROM {user} u3
                                 INNER JOIN {job_assignment} u3ja
                                         ON u3ja.userid = u3.id
                                 INNER JOIN {job_assignment} t3ja
                                         ON t3ja.tempmanagerjaid = u3ja.id
                                      WHERE u3.id = :viewer3
                                        AND t3ja.userid = {$field}
                                        AND t3ja.userid != u3.id
                                    )";
            $params['viewer3'] = $userid;
        }

        $sql = implode(' OR ', $conditions);

        return array(" ($sql) ", $params);
    }

    /**
     * Generate a human-readable text string describing the restriction
     *
     * @param string $title Name of the field being restricted
     * @param integer $reportid ID of the report
     *
     * @return string Human readable description of the restriction
     */
    public function text_restriction($title, $reportid) {
        global $DB;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);
        $who = isset($settings['who']) ? $settings['who'] : 0;
        $userid = $this->reportfor;

        $user = $DB->get_record('user', array('id' => $userid));

        $strings = array();
        $strparams = array('field' => $title, 'user' => fullname($user));

        if (($who & self::USER_OWN) == self::USER_OWN) {
            $strings[] = get_string('contentdesc_userown', 'shezar_reportbuilder', $strparams);
        }

        if (($who & self::USER_DIRECT_REPORTS) == self::USER_DIRECT_REPORTS) {
            $strings[] = get_string('contentdesc_userdirect', 'shezar_reportbuilder', $strparams);
        }

        if (($who & self::USER_INDIRECT_REPORTS) == self::USER_INDIRECT_REPORTS) {
            $strings[] = get_string('contentdesc_userindirect', 'shezar_reportbuilder', $strparams);
        }

        if (($who & self::USER_TEMP_REPORTS) == self::USER_TEMP_REPORTS) {
            $strings[] = get_string('contentdesc_usertemp', 'shezar_reportbuilder', $strparams);
        }

        if (empty($strings)) {
            return $title . ' ' . get_string('isnotfound', 'shezar_reportbuilder');
        }

        return implode(get_string('or', 'shezar_reportbuilder'), $strings);
    }


    /**
     * Adds form elements required for this content restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     * @param string $title Name of the field the restriction is acting on
     */
    public function form_template(&$mform, $reportid, $title) {

        // get current settings
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');
        $who = reportbuilder::get_setting($reportid, $type, 'who');

        $mform->addElement('header', 'user_header', get_string('showbyx',
            'shezar_reportbuilder', lcfirst($title)));
        $mform->setExpanded('user_header');
        $mform->addElement('checkbox', 'user_enable', '',
            get_string('showbasedonx', 'shezar_reportbuilder', lcfirst($title)));
        $mform->disabledIf('user_enable', 'contentenabled', 'eq', 0);
        $mform->setDefault('user_enable', $enable);
        $checkgroup = array();
        $checkgroup[] =& $mform->createElement('advcheckbox', 'user_who['.self::USER_OWN.']', '',
            get_string('userownrecords', 'shezar_reportbuilder'), null, array(0, 1));
        $mform->setType('user_who['.self::USER_OWN.']', PARAM_INT);
        $checkgroup[] =& $mform->createElement('advcheckbox', 'user_who['.self::USER_DIRECT_REPORTS.']', '',
            get_string('userdirectreports', 'shezar_reportbuilder'), null, array(0, 1));
        $mform->setType('user_who['.self::USER_DIRECT_REPORTS.']', PARAM_INT);
        $checkgroup[] =& $mform->createElement('advcheckbox', 'user_who['.self::USER_INDIRECT_REPORTS.']', '',
            get_string('userindirectreports', 'shezar_reportbuilder'), null, array(0, 1));
        $mform->setType('user_who['.self::USER_INDIRECT_REPORTS.']', PARAM_INT);
        $checkgroup[] =& $mform->createElement('advcheckbox', 'user_who['.self::USER_TEMP_REPORTS.']', '',
            get_string('usertempreports', 'shezar_reportbuilder'), null, array(0, 1));
        $mform->setType('user_who['.self::USER_TEMP_REPORTS.']', PARAM_INT);

        $mform->addGroup($checkgroup, 'user_who_group',
            get_string('includeuserrecords', 'shezar_reportbuilder'), html_writer::empty_tag('br'), false);
        $usergroups = array(self::USER_OWN, self::USER_DIRECT_REPORTS, self::USER_INDIRECT_REPORTS, self::USER_TEMP_REPORTS);
        foreach ($usergroups as $usergroup) {
            // Bitwise comparison.
            if (($who & $usergroup) == $usergroup) {
                $mform->setDefault('user_who['.$usergroup.']', 1);
            }
        }
        $mform->disabledIf('user_who_group', 'contentenabled', 'eq', 0);
        $mform->disabledIf('user_who_group', 'user_enable', 'notchecked');
        $mform->addHelpButton('user_header', 'reportbuilderuser', 'shezar_reportbuilder');
    }


    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    public function form_process($reportid, $fromform) {
        $status = true;
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);

        // enable checkbox option
        $enable = (isset($fromform->user_enable) &&
            $fromform->user_enable) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'enable', $enable);

        // Who checkbox option.
        // Enabled options are stored as user_who[key] = 1 when enabled.
        // Key is a bitwise value to be summed and stored.
        $whovalue = 0;
        $who = isset($fromform->user_who) ?
            $fromform->user_who : array();
        foreach ($who as $key => $option) {
            if ($option) {
                $whovalue += $key;
            }
        }
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'who', $whovalue);

        return $status;
    }
}


/*
 * Restrict content by a particular date
 *
 * Pass in an integer that contains a unix timestamp
 */
class rb_date_content extends rb_base_content {
    /**
     * Generate the SQL to apply this content restriction
     *
     * @param string $field SQL field to apply the restriction against
     * @param integer $reportid ID of the report
     *
     * @return array containing SQL snippet to be used in a WHERE clause, as well as array of SQL params
     */
    public function sql_restriction($field, $reportid) {
        global $DB;
        $now = time();
        $financialyear = get_config('reportbuilder', 'financialyear');
        $month = substr($financialyear, 2, 2);
        $day = substr($financialyear, 0, 2);

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);

        // option to include empty date fields
        $includenulls = (isset($settings['incnulls']) &&
            $settings['incnulls']) ?
            " OR {$field} IS NULL OR {$field} = 0 " : " AND {$field} != 0 ";

        switch ($settings['when']) {
        case 'past':
            return array("({$field} < {$now} {$includenulls})", array());
        case 'future':
            return array("({$field} > {$now} {$includenulls})", array());
        case 'last30days':
            $sql = "( ({$field} < {$now}  AND {$field}  >
                ({$now} - 60*60*24*30)) {$includenulls})";
            return array($sql, array());
        case 'next30days':
            $sql = "( ({$field} > {$now} AND {$field} <
                ({$now} + 60*60*24*30)) {$includenulls})";
            return array($sql, array());
        case 'currentfinancial':
            $required_year = date('Y', $now);
            $year_before = $required_year - 1;
            $year_after = $required_year + 1;
            if (date('z', $now) >= date('z', mktime(0, 0, 0, $month, $day, $required_year))) {
                $start = mktime(0, 0, 0, $month, $day, $required_year);
                $end = mktime(0, 0, 0, $month, $day, $year_after);
            } else {
                $start = mktime(0, 0, 0, $month, $day, $year_before);
                $end = mktime(0, 0, 0, $month, $day, $required_year);
            }
            $sql = "( ({$field} >= {$start} AND {$field} <
                {$end}) {$includenulls})";
            return array($sql, array());
        case 'lastfinancial':
            $required_year = date('Y', $now) - 1;
            $year_before = $required_year - 1;
            $year_after = $required_year + 1;
            if (date('z', $now) >= date('z', mktime(0, 0, 0, $month, $day, $required_year))) {
                $start = mktime(0, 0, 0, $month, $day, $required_year);
                $end = mktime(0, 0, 0, $month, $day, $year_after);
            } else {
                $start = mktime(0, 0, 0, $month, $day, $year_before);
                $end = mktime(0, 0, 0, $month, $day, $required_year);
            }
            $sql = "( ({$field} >= {$start} AND {$field} <
                {$end}) {$includenulls})";
            return array($sql, array());
        default:
            // no match
            // using 1=0 instead of FALSE for MSSQL support
            return array("(1=0 {$includenulls})", array());
        }

    }

    /**
     * Generate a human-readable text string describing the restriction
     *
     * @param string $title Name of the field being restricted
     * @param integer $reportid ID of the report
     *
     * @return string Human readable description of the restriction
     */
    public function text_restriction($title, $reportid) {

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);

        // option to include empty date fields
        $includenulls = (isset($settings['incnulls']) &&
                         $settings['incnulls']) ? " (or $title is empty)" : '';

        switch ($settings['when']) {
        case 'past':
            return $title . ' ' . get_string('occurredbefore', 'shezar_reportbuilder') . ' ' .
                userdate(time(), '%c'). $includenulls;
        case 'future':
            return $title . ' ' . get_string('occurredafter', 'shezar_reportbuilder') . ' ' .
                userdate(time(), '%c'). $includenulls;
        case 'last30days':
            return $title . ' ' . get_string('occurredafter', 'shezar_reportbuilder') . ' ' .
                userdate(time() - 60*60*24*30, '%c') . get_string('and', 'shezar_reportbuilder') .
                get_string('occurredbefore', 'shezar_reportbuilder') . userdate(time(), '%c') .
                $includenulls;

        case 'next30days':
            return $title . ' ' . get_string('occurredafter', 'shezar_reportbuilder') . ' ' .
                userdate(time(), '%c') . get_string('and', 'shezar_reportbuilder') .
                get_string('occurredbefore', 'shezar_reportbuilder') .
                userdate(time() + 60*60*24*30, '%c') . $includenulls;
        case 'currentfinancial':
            return $title . ' ' . get_string('occurredthisfinancialyear', 'shezar_reportbuilder') .
                $includenulls;
        case 'lastfinancial':
            return $title . ' ' . get_string('occurredprevfinancialyear', 'shezar_reportbuilder') .
                $includenulls;
        default:
            return 'Error with date content restriction';
        }
    }


    /**
     * Adds form elements required for this content restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     * @param string $title Name of the field the restriction is acting on
     */
    public function form_template(&$mform, $reportid, $title) {
        // get current settings
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');
        $when = reportbuilder::get_setting($reportid, $type, 'when');
        $incnulls = reportbuilder::get_setting($reportid, $type, 'incnulls');

        $mform->addElement('header', 'date_header', get_string('showbyx',
            'shezar_reportbuilder', lcfirst($title)));
        $mform->setExpanded('date_header');
        $mform->addElement('checkbox', 'date_enable', '',
            get_string('showbasedonx', 'shezar_reportbuilder',
            lcfirst($title)));
        $mform->setDefault('date_enable', $enable);
        $mform->disabledIf('date_enable', 'contentenabled', 'eq', 0);
        $radiogroup = array();
        $radiogroup[] =& $mform->createElement('radio', 'date_when', '',
            get_string('thepast', 'shezar_reportbuilder'), 'past');
        $radiogroup[] =& $mform->createElement('radio', 'date_when', '',
            get_string('thefuture', 'shezar_reportbuilder'), 'future');
        $radiogroup[] =& $mform->createElement('radio', 'date_when', '',
            get_string('last30days', 'shezar_reportbuilder'), 'last30days');
        $radiogroup[] =& $mform->createElement('radio', 'date_when', '',
            get_string('next30days', 'shezar_reportbuilder'), 'next30days');
        $radiogroup[] =& $mform->createElement('radio', 'date_when', '',
            get_string('currentfinancial', 'shezar_reportbuilder'), 'currentfinancial');
        $radiogroup[] =& $mform->createElement('radio', 'date_when', '',
            get_string('lastfinancial', 'shezar_reportbuilder'), 'lastfinancial');
        $mform->addGroup($radiogroup, 'date_when_group',
            get_string('includerecordsfrom', 'shezar_reportbuilder'), html_writer::empty_tag('br'), false);
        $mform->setDefault('date_when', $when);
        $mform->disabledIf('date_when_group', 'contentenabled', 'eq', 0);
        $mform->disabledIf('date_when_group', 'date_enable', 'notchecked');
        $mform->addHelpButton('date_header', 'reportbuilderdate', 'shezar_reportbuilder');

        $mform->addElement('checkbox', 'date_incnulls',
            get_string('includeemptydates', 'shezar_reportbuilder'));
        $mform->setDefault('date_incnulls', $incnulls);
        $mform->disabledIf('date_incnulls', 'date_enable', 'notchecked');
        $mform->disabledIf('date_incnulls', 'contentenabled', 'eq', 0);
    }


    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    public function form_process($reportid, $fromform) {
        $status = true;
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);

        // enable checkbox option
        $enable = (isset($fromform->date_enable) &&
            $fromform->date_enable) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'enable', $enable);

        // when radio option
        $when = isset($fromform->date_when) ?
            $fromform->date_when : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'when', $when);

        // include nulls checkbox option
        $incnulls = (isset($fromform->date_incnulls) &&
            $fromform->date_incnulls) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'incnulls', $incnulls);

        return $status;
    }
}


/*
 * Restrict content by offical tags
 *
 * Pass in a column that contains a pipe '|' separated list of official tag ids
 */
class rb_tag_content extends rb_base_content {
    /**
     * Generate the SQL to apply this content restriction
     *
     * @param string $field SQL field to apply the restriction against
     * @param integer $reportid ID of the report
     *
     * @return array containing SQL snippet to be used in a WHERE clause, as well as array of SQL params
     */
    public function sql_restriction($field, $reportid) {
        global $DB;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);

        $include_sql = array();
        $exclude_sql = array();

        // get arrays of included and excluded tags
        $settings = reportbuilder::get_all_settings($reportid, $type);
        $itags = ($settings['included']) ?
            explode('|', $settings['included']) : array();
        $etags = ($settings['excluded']) ?
            explode('|', $settings['excluded']) : array();
        $include_logic = (isset($settings['include_logic']) &&
            $settings['include_logic'] == 0) ? ' AND ' : ' OR ';
        $exclude_logic = (isset($settings['exclude_logic']) &&
            $settings['exclude_logic'] == 0) ? ' OR ' : ' AND ';

        // loop through current official tags
        $tags = $DB->get_records('tag', array('tagtype' => 'official'), 'name');
        $params = array();
        $count = 1;
        foreach ($tags as $tag) {
            // if found, add the SQL
            // we can't just use LIKE '%tag%' because we might get
            // partial number matches
            if (in_array($tag->id, $itags)) {
                $uniqueparam = rb_unique_param("cctre_{$count}_");
                $elike = $DB->sql_like($field, ":{$uniqueparam}");
                $params[$uniqueparam] = $DB->sql_like_escape($tag->id);

                $uniqueparam = rb_unique_param("cctrew_{$count}_");
                $ewlike = $DB->sql_like($field, ":{$uniqueparam}");
                $params[$uniqueparam] = $DB->sql_like_escape($tag->id).'|%';

                $uniqueparam = rb_unique_param("cctrsw_{$count}_");
                $swlike = $DB->sql_like($field, ":{$uniqueparam}");
                $params[$uniqueparam] = '%|'.$DB->sql_like_escape($tag->id);

                $uniqueparam = rb_unique_param("cctrsc_{$count}_");
                $clike = $DB->sql_like($field, ":{$uniqueparam}");
                $params[$uniqueparam] = '%|'.$DB->sql_like_escape($tag->id).'|%';

                $include_sql[] = "({$elike} OR
                {$ewlike} OR
                {$swlike} OR
                {$clike})\n";

                $count++;
            }
            if (in_array($tag->id, $etags)) {
                $uniqueparam = rb_unique_param("cctre_{$count}_");
                $enotlike = $DB->sql_like($field, ":{$uniqueparam}", true, true, true);
                $params[$uniqueparam] = $DB->sql_like_escape($tag->id);

                $uniqueparam = rb_unique_param("cctrew_{$count}_");
                $ewnotlike = $DB->sql_like($field, ":{$uniqueparam}", true, true, true);
                $params[$uniqueparam] = $DB->sql_like_escape($tag->id).'|%';

                $uniqueparam = rb_unique_param("cctrsw_{$count}_");
                $swnotlike = $DB->sql_like($field, ":{$uniqueparam}", true, true, true);
                $params[$uniqueparam] = '%|'.$DB->sql_like_escape($tag->id);

                $uniqueparam = rb_unique_param("cctrsc_{$count}_");
                $cnotlike = $DB->sql_like($field, ":{$uniqueparam}", true, true, true);
                $params[$uniqueparam] = '%|'.$DB->sql_like_escape($tag->id).'|%';

                $include_sql[] = "({$enotlike} AND
                {$ewnotlike} AND
                {$swnotlike} AND
                {$cnotlike})\n";

                $count++;
            }
        }

        // merge the include and exclude strings separately
        $includestr = implode($include_logic, $include_sql);
        $excludestr = implode($exclude_logic, $exclude_sql);

        // now merge together
        if ($includestr && $excludestr) {
            return array(" ($includestr AND $excludestr) ", $params);
        } else if ($includestr) {
            return array(" $includestr ", $params);
        } else if ($excludestr) {
            return array(" $excludestr ", $params);
        } else {
            // using 1=0 instead of FALSE for MSSQL support
            return array('1=0', $params);
        }
    }

    /**
     * Generate a human-readable text string describing the restriction
     *
     * @param string $title Name of the field being restricted
     * @param integer $reportid ID of the report
     *
     * @return string Human readable description of the restriction
     */
    public function text_restriction($title, $reportid) {
        global $DB;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);

        $include_text = array();
        $exclude_text = array();

        $itags = ($settings['included']) ?
            explode('|', $settings['included']) : array();
        $etags = ($settings['excluded']) ?
            explode('|', $settings['excluded']) : array();
        $include_logic = (isset($settings['include_logic']) &&
            $settings['include_logic'] == 0) ? 'and' : 'or';
        $exclude_logic = (isset($settings['exclude_logic']) &&
            $settings['exclude_logic'] == 0) ? 'and' : 'or';

        $tags = $DB->get_records('tag', array('tagtype' => 'official'), 'name');
        foreach ($tags as $tag) {
            if (in_array($tag->id, $itags)) {
                $include_text[] = '"' . $tag->name . '"';
            }
            if (in_array($tag->id, $etags)) {
                $exclude_text[] = '"' . $tag->name . '"';
            }
        }

        if (count($include_text) > 0) {
            $includestr = $title . ' ' . get_string('istaggedwith', 'shezar_reportbuilder') .
                ' ' . implode(get_string($include_logic, 'shezar_reportbuilder'), $include_text);
        } else {
            $includestr = '';
        }
        if (count($exclude_text) > 0) {
            $excludestr = $title . ' ' . get_string('isnttaggedwith', 'shezar_reportbuilder') .
                ' ' . implode(get_string($exclude_logic, 'shezar_reportbuilder'), $exclude_text);
        } else {
            $excludestr = '';
        }

        if ($includestr && $excludestr) {
            return $includestr . get_string('and', 'shezar_reportbuilder') . $excludestr;
        } else if ($includestr) {
            return $includestr;
        } else if ($excludestr) {
            return $excludestr;
        } else {
            return '';
        }

    }


    /**
     * Adds form elements required for this content restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     * @param string $title Name of the field the restriction is acting on
     */
    public function form_template(&$mform, $reportid, $title) {
        global $DB;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');
        $include_logic = reportbuilder::get_setting($reportid, $type, 'include_logic');
        $exclude_logic = reportbuilder::get_setting($reportid, $type, 'exclude_logic');
        $activeincludes = explode('|',
            reportbuilder::get_setting($reportid, $type, 'included'));
        $activeexcludes = explode('|',
            reportbuilder::get_setting($reportid, $type, 'excluded'));

        $mform->addElement('header', 'tag_header',
            get_string('showbytag', 'shezar_reportbuilder'));
        $mform->setExpanded('tag_header');
        $mform->addHelpButton('tag_header', 'reportbuildertag', 'shezar_reportbuilder');

        $mform->addElement('checkbox', 'tag_enable', '',
            get_string('tagenable', 'shezar_reportbuilder'));
        $mform->setDefault('tag_enable', $enable);
        $mform->disabledIf('tag_enable', 'contentenabled', 'eq', 0);

        $mform->addElement('html', html_writer::empty_tag('br'));

        // include the following tags
        $tags = $DB->get_records('tag', array('tagtype' => 'official'), 'name');
        if (!empty($tags)) {
            $checkgroup = array();
            $opts = array(1 => get_string('anyofthefollowing', 'shezar_reportbuilder'),
                          0 => get_string('allofthefollowing', 'shezar_reportbuilder'));
            $mform->addElement('select', 'tag_include_logic', get_string('includetags', 'shezar_reportbuilder'), $opts);
            $mform->setDefault('tag_include_logic', $include_logic);
            $mform->disabledIf('tag_enable', 'contentenabled', 'eq', 0);
            foreach ($tags as $tag) {
                $checkgroup[] =& $mform->createElement('checkbox',
                    'tag_include_option_' . $tag->id, '', $tag->name, 1);
                $mform->disabledIf('tag_include_option_' . $tag->id,
                    'tag_exclude_option_' . $tag->id, 'checked');
                if (in_array($tag->id, $activeincludes)) {
                    $mform->setDefault('tag_include_option_' . $tag->id, 1);
                }
            }
            $mform->addGroup($checkgroup, 'tag_include_group', '', html_writer::empty_tag('br'), false);
        }
        $mform->disabledIf('tag_include_group', 'contentenabled', 'eq', 0);
        $mform->disabledIf('tag_include_group', 'tag_enable',
            'notchecked');

        $mform->addElement('html', str_repeat(html_writer::empty_tag('br'), 2));

        // exclude the following tags
        if (!empty($tags)) {
            $checkgroup = array();
            $opts = array(1 => get_string('anyofthefollowing', 'shezar_reportbuilder'),
                          0 => get_string('allofthefollowing', 'shezar_reportbuilder'));
            $mform->addElement('select', 'tag_exclude_logic', get_string('excludetags', 'shezar_reportbuilder'), $opts);
            $mform->setDefault('tag_exclude_logic', $exclude_logic);
            $mform->disabledIf('tag_enable', 'contentenabled', 'eq', 0);
            foreach ($tags as $tag) {
                $checkgroup[] =& $mform->createElement('checkbox',
                    'tag_exclude_option_' . $tag->id, '', $tag->name, 1);
                $mform->disabledIf('tag_exclude_option_' . $tag->id,
                    'tag_include_option_' . $tag->id, 'checked');
                if (in_array($tag->id, $activeexcludes)) {
                    $mform->setDefault('tag_exclude_option_' . $tag->id, 1);
                }
            }
            $mform->addGroup($checkgroup, 'tag_exclude_group', '', html_writer::empty_tag('br'), false);
        }
        $mform->disabledIf('tag_exclude_group', 'contentenabled', 'eq', 0);
        $mform->disabledIf('tag_exclude_group', 'tag_enable',
            'notchecked');

    }


    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    public function form_process($reportid, $fromform) {
        global $DB;

        $status = true;
        // remove the rb_ from class
        $type = substr(get_class($this), 3);

        // enable checkbox option
        $enable = (isset($fromform->tag_enable) &&
            $fromform->tag_enable) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'enable', $enable);

        // include with any or all
        $includelogic = (isset($fromform->tag_include_logic) &&
            $fromform->tag_include_logic) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'include_logic', $includelogic);

        // exclude with any or all
        $excludelogic = (isset($fromform->tag_exclude_logic) &&
            $fromform->tag_exclude_logic) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'exclude_logic', $excludelogic);

        // tag settings
        $tags = $DB->get_records('tag', array('tagtype' => 'official'));
        if (!empty($tags)) {
            $activeincludes = array();
            $activeexcludes = array();
            foreach ($tags as $tag) {
                $includename = 'tag_include_option_' . $tag->id;
                $excludename = 'tag_exclude_option_' . $tag->id;

                // included tags
                if (isset($fromform->$includename)) {
                    if ($fromform->$includename == 1) {
                        $activeincludes[] = $tag->id;
                    }
                }

                // excluded tags
                if (isset($fromform->$excludename)) {
                    if ($fromform->$excludename == 1) {
                        $activeexcludes[] = $tag->id;
                    }
                }

            }

            // implode into string and update setting
            $status = $status && reportbuilder::update_setting($reportid,
                $type, 'included', implode('|', $activeincludes));

            // implode into string and update setting
            $status = $status && reportbuilder::update_setting($reportid,
                $type, 'excluded', implode('|', $activeexcludes));
        }
        return $status;
    }
}

/*
 * Restrict content by availability
 *
 * Pass in a column that contains a pipe '|' separated list of official tag ids
 */
class rb_prog_availability_content extends rb_base_content {
    /**
     * Generate the SQL to apply this content restriction
     *
     * @param string $field SQL field to apply the restriction against
     * @param integer $reportid ID of the report
     *
     * @return array containing SQL snippet to be used in a WHERE clause, as well as array of SQL params
     */
    public function sql_restriction($field, $reportid) {
        // The restriction snippet based on the available fields was moved to shezar_visibility_where.
        // So no restriction for programs or certifications.
        $restriction = " 1=1 ";

        return array($restriction, array());
    }

    /**
     * Generate a human-readable text string describing the restriction
     *
     * @param string $title Name of the field being restricted
     * @param integer $reportid ID of the report
     *
     * @return string Human readable description of the restriction
     */
    public function text_restriction($title, $reportid) {
        return get_string('contentavailability', 'shezar_program');
    }


    /**
     * Adds form elements required for this content restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     * @param string $title Name of the field the restriction is acting on
     */
    public function form_template(&$mform, $reportid, $title) {
        global $DB;

        // Get current settings and
        // remove rb_ from start of classname.
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');

        $mform->addElement('header', 'prog_availability_header',
            get_string('showbyx', 'shezar_reportbuilder', lcfirst($title)));
        $mform->setExpanded('prog_availability_header');
        $mform->addElement('checkbox', 'prog_availability_enable', '',
            get_string('contentavailability', 'shezar_program'));
        $mform->setDefault('prog_availability_enable', $enable);
        $mform->disabledIf('prog_availability_enable', 'contentenabled', 'eq', 0);
        $mform->addHelpButton('prog_availability_header', 'contentavailability', 'shezar_program');

    }


    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    public function form_process($reportid, $fromform) {
        global $DB;

        $status = true;
        // Remove rb_ from start of classname.
        $type = substr(get_class($this), 3);

        // Enable checkbox option.
        $enable = (isset($fromform->prog_availability_enable) &&
            $fromform->prog_availability_enable) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'enable', $enable);

        return $status;

    }
}

// Include trainer content restriction
include_once($CFG->dirroot . '/shezar/reportbuilder/classes/rb_trainer_content.php');
