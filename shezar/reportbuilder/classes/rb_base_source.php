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

require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * Abstract base class to be extended to create report builder sources
 *
 * @property string $base
 * @property rb_join[] $joinlist
 * @property rb_column_option[] $columnoptions
 * @property rb_filter_option[] $filteroptions
 */
abstract class rb_base_source {

    /*
     * Used in default pre_display_actions function.
     */
    public $needsredirect, $redirecturl, $redirectmessage;

    /** @var array of component used for lookup of classes */
    protected $usedcomponents = array();

    /** @var rb_column[] */
    public $requiredcolumns;

    /** @var rb_global_restriction_set with active restrictions, ignore if null */
    protected $globalrestrictionset = null;

    /** @var rb_join[] list of global report restriction joins  */
    public $globalrestrictionjoins = array();

    /** @var array named query params used in global restriction joins */
    public $globalrestrictionparams = array();

    /**
     * TODO - it would be nice to make this definable in the config or something.
     * @var string $uniqueseperator - A string unique enough to use as a seperator for textareas
     */
    protected $uniquedelimiter = '\.|./';

    /**
     * Class constructor
     *
     * Call from the constructor of all child classes with:
     *
     *  parent::__construct()
     *
     * to ensure child class has implemented everything necessary to work.
     */
    public function __construct() {
        // Extending classes should add own component to this array before calling parent constructor,
        // this allows us to lookup display classes at more locations.
        $this->usedcomponents[] = 'shezar_reportbuilder';

        // check that child classes implement required properties
        $properties = array(
            'base',
            'joinlist',
            'columnoptions',
            'filteroptions',
        );
        foreach ($properties as $property) {
            if (!property_exists($this, $property)) {
                $a = new stdClass();
                $a->property = $property;
                $a->class = get_class($this);
                throw new ReportBuilderException(get_string('error:propertyxmustbesetiny', 'shezar_reportbuilder', $a));
            }
        }

        // set sensible defaults for optional properties
        $defaults = array(
            'paramoptions' => array(),
            'requiredcolumns' => array(),
            'contentoptions' => array(),
            'preproc' => null,
            'grouptype' => 'none',
            'groupid' => null,
            'selectable' => true,
            'scheduleable' => true,
            'cacheable' => true,
            'hierarchymap' => array()
        );
        foreach ($defaults as $property => $default) {
            if (!property_exists($this, $property)) {
                $this->$property = $default;
            } else if ($this->$property === null) {
                $this->$property = $default;
            }
        }

        // basic sanity checking of joinlist
        $this->validate_joinlist();
        //create array to store the join functions and join table
        $joindata = array();
        $base = $this->base;
        //if any of the join tables are customfield-related, ensure the customfields are added
        foreach ($this->joinlist as $join) {
            //tables can be joined multiple times so we set elements of an associative array as joinfunction => jointable
            $table = $join->table;
            switch ($table) {
                case '{user}':
                    $joindata['add_custom_user_fields'] = 'auser';
                    break;
                case '{course}':
                    $joindata['add_custom_course_fields'] = 'course';
                    break;
                case '{prog}':
                    $joindata['add_custom_prog_fields'] = 'prog';
                    break;
                case '{comp}':
                    $joindata['add_custom_competency_fields'] = 'comp';
                    break;
                case '{goal}':
                    $joindata['add_custom_goal_fields'] = 'goal';
                    break;
                case '{goal_personal}':
                    $joindata['add_custom_personal_goal_fields'] = 'goal_personal';
                    break;
                case '{dp_plan_evidence}':
                    $joindata['add_custom_evidence_fields'] = 'dp_plan_evidence';
                    break;
            }
        }
        //now ensure customfields fields are added if there are no joins but the base table is customfield-related
        switch ($base) {
            case '{user}':
                $joindata['add_custom_user_fields'] = 'base';
                break;
            case '{course}':
                $joindata['add_custom_course_fields'] = 'base';
                break;
            case '{prog}':
                $joindata['add_custom_prog_fields'] = 'base';
                break;
            case '{org}':
                $joindata['add_custom_organisation_fields'] = 'base';
                break;
            case '{pos}':
                $joindata['add_custom_position_fields'] = 'base';
                break;
            case '{comp}':
                $joindata['add_custom_competency_fields'] = 'base';
                break;
            case '{goal}':
                $joindata['add_custom_goal_fields'] = 'base';
                break;
            case '{goal_personal}':
                $joindata['add_custom_personal_goal_fields'] = 'base';
                break;
            case '{dp_plan_evidence}':
                $joindata['add_custom_evidence_fields'] = 'base';
                break;
        }
        //and then use the flags to call the appropriate add functions
        foreach ($joindata as $joinfunction => $jointable) {
            $this->$joinfunction($this->joinlist,
                                 $this->columnoptions,
                                 $this->filteroptions,
                                 $jointable
                                );

        }
    }

    /**
     * Is this report source usable?
     *
     * Override and return true if the source should be hidden
     * in all user interfaces. For example when the source
     * requires some subsystem to be enabled.
     *
     * @return bool
     */
    public function is_ignored() {
        return false;
    }

    /**
     * Are the global report restrictions implemented in the source?
     *
     * Return values mean:
     *   - true: this report source supports global report restrictions.
     *   - false: this report source does NOT support global report restrictions.
     *   - null: this report source has not been converted to use global report restrictions yet.
     *
     * @return null|bool
     */
    public function global_restrictions_supported() {
        // Null means not converted yet, override in sources with true or false.
        return null;
    }

    /**
     * Set redirect url and (optionally) message for use in default pre_display_actions function.
     *
     * When pre_display_actions is call it will redirect to the specified url (unless pre_display_actions
     * is overridden, in which case it performs those actions instead).
     *
     * @param mixed $url moodle_url or url string
     * @param string $message
     */
    protected function set_redirect($url, $message = null) {
        $this->redirecturl = $url;
        $this->redirectmessage = $message;
    }


    /**
     * Set whether redirect needs to happen in pre_display_actions.
     *
     * @param bool $truth true if redirect is needed
     */
    protected function needs_redirect($truth = true) {
        $this->needsredirect = $truth;
    }


    /**
     * Default pre_display_actions - if needsredirect is true then redirect to the specified
     * page, otherwise do nothing.
     *
     * This function is called after post_config and before report data is generated. This function is
     * not called when report data is not generated, such as on report setup pages.
     * If you want to perform a different action after post_config then override this function and
     * set your own private variables (e.g. to signal a result from post_config) in your report source.
     */
    public function pre_display_actions() {
        if ($this->needsredirect && isset($this->redirecturl)) {
            if (isset($this->redirectmessage)) {
                shezar_set_notification($this->redirectmessage, $this->redirecturl, array('class' => 'notifymessage'));
            } else {
                redirect($this->redirecturl);
            }
        }
    }


    /**
     * Create a link that when clicked will display additional information inserted in a box below the clicked row.
     *
     * @param string|stringable $columnvalue the value to display in the column
     * @param string $expandname the name of the function (prepended with 'rb_expand_') that will generate the contents
     * @param array $params any parameters that the content generator needs
     * @param string|moodle_url $alternateurl url to link to in case js is not available
     * @param array $attributes
     * @return type
     */
    protected function create_expand_link($columnvalue, $expandname, $params, $alternateurl = '', $attributes = array()) {
        global $OUTPUT;

        // Serialize the data so that it can be passed as a single value.
        $paramstring = http_build_query($params, '', '&');

        $class_link = 'rb-display-expand-link ';
        if (array_key_exists('class', $attributes)) {
            $class_link .=  $attributes['class'];
        }

        $attributes['class'] = 'rb-display-expand';
        $attributes['data-name'] = $expandname;
        $attributes['data-param'] = $paramstring;
        $infoicon = $OUTPUT->flex_icon('info-circle', ['classes' => 'ft-state-info']);

        // Create the result.
        $link = html_writer::link($alternateurl, format_string($columnvalue), array('class' => $class_link));
        return html_writer::div($infoicon . $link, 'rb-display-expand', $attributes);
    }


    /**
     * Check the joinlist for invalid dependencies and duplicate names
     *
     * @return True or throws exception if problem found
     */
    private function validate_joinlist() {
        $joinlist = $this->joinlist;
        $joins_used = array();

        // don't let source define join with same name as an SQL
        // reserved word
        // from http://docs.moodle.org/en/XMLDB_reserved_words
        $reserved_words = explode(', ', 'access, accessible, add, all, alter, analyse, analyze, and, any, array, as, asc, asensitive, asymmetric, audit, authorization, autoincrement, avg, backup, before, begin, between, bigint, binary, blob, both, break, browse, bulk, by, call, cascade, case, cast, change, char, character, check, checkpoint, close, cluster, clustered, coalesce, collate, column, comment, commit, committed, compress, compute, condition, confirm, connect, connection, constraint, contains, containstable, continue, controlrow, convert, count, create, cross, current, current_date, current_role, current_time, current_timestamp, current_user, cursor, database, databases, date, day_hour, day_microsecond, day_minute, day_second, dbcc, deallocate, dec, decimal, declare, default, deferrable, delayed, delete, deny, desc, describe, deterministic, disk, distinct, distinctrow, distributed, div, do, double, drop, dual, dummy, dump, each, else, elseif, enclosed, end, errlvl, errorexit, escape, escaped, except, exclusive, exec, execute, exists, exit, explain, external, false, fetch, file, fillfactor, float, float4, float8, floppy, for, force, foreign, freetext, freetexttable, freeze, from, full, fulltext, function, goto, grant, group, having, high_priority, holdlock, hour_microsecond, hour_minute, hour_second, identified, identity, identity_insert, identitycol, if, ignore, ilike, immediate, in, increment, index, infile, initial, initially, inner, inout, insensitive, insert, int, int1, int2, int3, int4, int8, integer, intersect, interval, into, is, isnull, isolation, iterate, join, key, keys, kill, leading, leave, left, level, like, limit, linear, lineno, lines, load, localtime, localtimestamp, lock, long, longblob, longtext, loop, low_priority, master_heartbeat_period, master_ssl_verify_server_cert, match, max, maxextents, mediumblob, mediumint, mediumtext, middleint, min, minus, minute_microsecond, minute_second, mirrorexit, mlslabel, mod, mode, modifies, modify, national, natural, new,' .
            ' no_write_to_binlog, noaudit, nocheck, nocompress, nonclustered, not, notnull, nowait, null, nullif, number, numeric, of, off, offline, offset, offsets, old, on, once, online, only, open, opendatasource, openquery, openrowset, openxml, optimize, option, optionally, or, order, out, outer, outfile, over, overlaps, overwrite, pctfree, percent, perm, permanent, pipe, pivot, placing, plan, precision, prepare, primary, print, prior, privileges, proc, procedure, processexit, public, purge, raid0, raiserror, range, raw, read, read_only, read_write, reads, readtext, real, reconfigure, references, regexp, release, rename, repeat, repeatable, replace, replication, require, resource, restore, restrict, return, returning, revoke, right, rlike, rollback, row, rowcount, rowguidcol, rowid, rownum, rows, rule, save, schema, schemas, second_microsecond, select, sensitive, separator, serializable, session, session_user, set, setuser, share, show, shutdown, similar, size, smallint, some, soname, spatial, specific, sql, sql_big_result, sql_calc_found_rows, sql_small_result, sqlexception, sqlstate, sqlwarning, ssl, start, starting, statistics, straight_join, successful, sum, symmetric, synonym, sysdate, system_user, table, tape, temp, temporary, terminated, textsize, then, tinyblob, tinyint, tinytext, to, top, trailing, tran, transaction, trigger, true, truncate, tsequal, uid, uncommitted, undo, union, unique, unlock, unsigned, update, updatetext, upgrade, usage, use, user, using, utc_date, utc_time, utc_timestamp, validate, values, varbinary, varchar, varchar2, varcharacter, varying, verbose, view, waitfor, when, whenever, where, while, with, work, write, writetext, x509, xor, year_month, zerofill');

        foreach ($joinlist as $item) {
            // check join list for duplicate names
            if (in_array($item->name, $joins_used)) {
                $a = new stdClass();
                $a->join = $item->name;
                $a->source = get_class($this);
                throw new ReportBuilderException(get_string('error:joinxusedmorethanonceiny', 'shezar_reportbuilder', $a));
            } else {
                $joins_used[] = $item->name;
            }

            if (in_array($item->name, $reserved_words)) {
                $a = new stdClass();
                $a->join = $item->name;
                $a->source = get_class($this);
                throw new ReportBuilderException(get_string('error:joinxisreservediny', 'shezar_reportbuilder', $a));
            }
        }

        foreach ($joinlist as $item) {
            // check that dependencies exist
            if (isset($item->dependencies) &&
                is_array($item->dependencies)) {

                foreach ($item->dependencies as $dep) {
                    if ($dep == 'base') {
                        continue;
                    }
                    if (!in_array($dep, $joins_used)) {
                        $a = new stdClass();
                        $a->join = $item->name;
                        $a->source = get_class($this);
                        $a->dependency = $dep;
                        throw new ReportBuilderException(get_string('error:joinxhasdependencyyinz', 'shezar_reportbuilder', $a));
                    }
                }
            } else if (isset($item->dependencies) &&
                $item->dependencies != 'base') {

                if (!in_array($item->dependencies, $joins_used)) {
                    $a = new stdClass();
                    $a->join = $item->name;
                    $a->source = get_class($this);
                    $a->dependency = $item->dependencies;
                    throw new ReportBuilderException(get_string('error:joinxhasdependencyyinz', 'shezar_reportbuilder', $a));
                }
            }
        }
        return true;
    }


    //
    //
    // General purpose source specific methods
    //
    //

    /**
     * Returns a new rb_column object based on a column option from this source
     *
     * If $heading is given use it for the heading property, otherwise use
     * the default heading property from the column option
     *
     * @param string $type The type of the column option to use
     * @param string $value The value of the column option to use
     * @param int $transform
     * @param int $aggregate
     * @param string $heading Heading for the new column
     * @param boolean $customheading True if the heading has been customised
     * @return rb_column A new rb_column object with details copied from this rb_column_option
     */
    public function new_column_from_option($type, $value, $transform, $aggregate, $heading=null, $customheading = true, $hidden=0) {
        $columnoptions = $this->columnoptions;
        $joinlist = $this->joinlist;
        if ($coloption =
            reportbuilder::get_single_item($columnoptions, $type, $value)) {

            // make sure joins are defined before adding column
            if (!reportbuilder::check_joins($joinlist, $coloption->joins)) {
                $a = new stdClass();
                $a->type = $coloption->type;
                $a->value = $coloption->value;
                $a->source = get_class($this);
                throw new ReportBuilderException(get_string('error:joinsfortypexandvalueynotfoundinz', 'shezar_reportbuilder', $a));
            }

            if ($heading === null) {
                $heading = ($coloption->defaultheading !== null) ?
                    $coloption->defaultheading : $coloption->name;
            }

            return new rb_column(
                $type,
                $value,
                $heading,
                $coloption->field,
                array(
                    'joins' => $coloption->joins,
                    'displayfunc' => $coloption->displayfunc,
                    'extrafields' => $coloption->extrafields,
                    'required' => false,
                    'capability' => $coloption->capability,
                    'noexport' => $coloption->noexport,
                    'grouping' => $coloption->grouping,
                    'grouporder' => $coloption->grouporder,
                    'nosort' => $coloption->nosort,
                    'style' => $coloption->style,
                    'class' => $coloption->class,
                    'hidden' => $hidden,
                    'customheading' => $customheading,
                    'transform' => $transform,
                    'aggregate' => $aggregate,
                    'extracontext' => $coloption->extracontext
                )
            );
        } else {
            $a = new stdClass();
            $a->type = $type;
            $a->value = $value;
            $a->source = get_class($this);
            throw new ReportBuilderException(get_string('error:columnoptiontypexandvalueynotfoundinz', 'shezar_reportbuilder', $a));
        }
    }

    /**
     * Returns list of used components.
     *
     * The list includes frankenstyle component names of the
     * current source and all parents.
     *
     * @return string[]
     */
    public function get_used_components() {
        return $this->usedcomponents;
    }

    //
    //
    // Generic column display methods
    //
    //

    /**
     * Format row record data for display.
     *
     * @param stdClass $row
     * @param string $format
     * @param reportbuilder $report
     * @return array of strings usually, values may be arrays for Excel format for example.
     */
    public function process_data_row(stdClass $row, $format, reportbuilder $report) {
        $results = array();
        $isexport = ($format !== 'html');

        foreach ($report->columns as $column) {
            if (!$column->display_column($isexport)) {
                continue;
            }

            $type = $column->type;
            $value = $column->value;
            $field = strtolower("{$type}_{$value}");

            if (!property_exists($row, $field)) {
                $results[] = get_string('unknown', 'shezar_reportbuilder');
                continue;
            }

            $classname = $column->get_display_class($report);
            $results[] = $classname::display($row->$field, $format, $row, $column, $report);
        }

        return $results;
    }

    /**
     * Reformat a timestamp into a time, showing nothing if invalid or null
     *
     * @param integer $date Unix timestamp
     * @param object $row Object containing all other fields for this row
     *
     * @return string Time in a nice format
     */
    function rb_display_nice_time($date, $row) {
        if ($date && is_numeric($date)) {
            return userdate($date, get_string('strftimeshort', 'langconfig'));
        } else {
            return '';
        }
    }

    /**
     * Reformat a timestamp and timezone into a datetime, showing nothing if invalid or null
     *
     * @param integer $date Unix timestamp
     * @param object $row Object containing all other fields for this row (which should include a timezone field)
     *
     * @return string Date and time in a nice format
     */
    function rb_display_nice_datetime_in_timezone($date, $row) {
        if ($date && is_numeric($date)) {
            if (empty($row->timezone)) {
                $targetTZ = core_date::get_user_timezone();
                $tzstring = get_string('nice_time_unknown_timezone', 'shezar_reportbuilder');
            } else {
                $targetTZ = core_date::normalise_timezone($row->timezone);
                $tzstring = core_date::get_localised_timezone($targetTZ);
            }
            $date = userdate($date, get_string('strftimedatetime', 'langconfig'), $targetTZ) . ' ';
            return $date . $tzstring;
        } else {
            return '';
        }
    }

    function rb_display_delimitedlist_date_in_timezone($data, $row) {
        $format = get_string('strftimedate', 'langconfig');
        return $this->format_delimitedlist_datetime_in_timezone($data, $row, $format);
    }

    function rb_display_delimitedlist_datetime_in_timezone($data, $row) {
        $format = get_string('strftimedatetime', 'langconfig');
        return $this->format_delimitedlist_datetime_in_timezone($data, $row, $format);
    }

    // Assumes you used a custom grouping with the $this->uniquedelimiter to concatenate the fields.
    function format_delimitedlist_datetime_in_timezone($data, $row, $format) {
        $delimiter = $this->uniquedelimiter;
        $items = explode($delimiter, $data);
        $output = array();
        foreach ($items as $date) {
            if ($date && is_numeric($date)) {
                if (empty($row->timezone)) {
                    $targetTZ = core_date::get_user_timezone();
                    $tzstring = get_string('nice_time_unknown_timezone', 'shezar_reportbuilder');
                } else {
                    $targetTZ = core_date::normalise_timezone($row->timezone);
                    $tzstring = core_date::get_localised_timezone($targetTZ);
                }
                $date = userdate($date, get_string('strftimedatetime', 'langconfig'), $targetTZ) . ' ';
                $output[] = $date . $tzstring;
            } else {
                $output[] = '-';
            }
        }

        return implode($output, "\n");
    }

    /**
     * Reformat two timestamps and timezones into a datetime, showing only one date if only one is present and
     * nothing if invalid or null.
     *
     * @param integer $date Unix timestamp
     * @param object $row Object containing all other fields for this row (which should include a timezone field)
     *
     * @return string Date and time in a nice format
     */
    function rb_display_nice_two_datetime_in_timezone($startdate, $row) {

        $finishdate = $row->finishdate;
        $startdatetext = $finishdatetext = $returntext = '';

        if (empty($row->timezone)) {
            $targetTZ = core_date::get_user_timezone();
            $tzstring = get_string('nice_time_unknown_timezone', 'shezar_reportbuilder');
        } else {
            $targetTZ = core_date::normalise_timezone($row->timezone);
            $tzstring = core_date::get_localised_timezone($targetTZ);
        }

        if ($startdate && is_numeric($startdate)) {
            $startdatetext = userdate($startdate, get_string('strftimedatetime', 'langconfig'), $targetTZ) . ' ' . $targetTZ;
        }

        if ($finishdate && is_numeric($finishdate)) {
            $finishdatetext = userdate($finishdate, get_string('strftimedatetime', 'langconfig'), $targetTZ) . ' ' . $targetTZ;
        }

        if ($startdatetext && $finishdatetext) {
            $returntext = get_string('datebetween', 'shezar_reportbuilder', array('from' => $startdatetext, 'to' => $finishdatetext));
        } else if ($startdatetext) {
            $returntext = get_string('dateafter', 'shezar_reportbuilder', $startdatetext);
        } else if ($finishdatetext) {
            $returntext = get_string('datebefore', 'shezar_reportbuilder', $finishdatetext);
        }

        return $returntext;
    }


    /**
     * Reformat a timestamp into a date and time (including seconds), showing nothing if invalid or null
     *
     * @param integer $date Unix timestamp
     * @param object $row Object containing all other fields for this row
     *
     * @return string Date and time (including seconds) in a nice format
     */
    function rb_display_nice_datetime_seconds($date, $row) {
        if ($date && is_numeric($date)) {
            return userdate($date, get_string('strftimedateseconds', 'langconfig'));
        } else {
            return '';
        }
    }

    // convert floats to 2 decimal places
    function rb_display_round2($item, $row) {
        return ($item === null or $item === '') ? '-' : sprintf('%.2f', $item);
    }

    // converts number to percentage with 1 decimal place
    function rb_display_percent($item, $row) {
        return ($item === null or $item === '') ? '-' : sprintf('%.1f%%', $item);
    }

    // Displays a comma separated list of strings as one string per line.
    // Assumes you used "'grouping' => 'comma_list'", which concatenates with ', ', to construct the string.
    function rb_display_list_to_newline($list, $row) {
        $items = explode(', ', $list);
        foreach ($items as $key => $item) {
            if (empty($item)) {
                $items[$key] = '-';
            }
        }
        return implode($items, "\n");
    }

    // Displays a delimited list of strings as one string per line.
    // Assumes you used "'grouping' => 'sql_aggregate'", which concatenates with $uniquedelimiter to construct a pre-ordered string.
    function rb_display_orderedlist_to_newline($list, $row) {
        $output = array();
        $items = explode($this->uniquedelimiter, $list);
        foreach ($items as $item) {
            $item = trim($item);
            if (empty($item) || $item === '-') {
                $output[] = '-';
            } else {
                $output[] = format_string($item);
            }
        }
        return implode($output, "\n");
    }

    // Assumes you used a custom grouping with the $this->uniquedelimiter to concatenate the fields.
    function rb_display_delimitedlist_to_newline($list, $row) {
        $delimiter = $this->uniquedelimiter;
        $items = explode($delimiter, $list);
        $output = array();
        foreach ($items as $item) {
            $item = trim($item);
            if (empty($item) || $item === '-') {
                $output[] = '-';
            } else {
                $output[] = format_string($item);
            }
        }
        return implode($output, "\n");
    }

    // Assumes you used a custom grouping with the $this->uniquedelimiter to concatenate the fields.
    function rb_display_delimitedlist_multi_to_newline($list, $row) {
        $delimiter = $this->uniquedelimiter;
        $items = explode($delimiter, $list);
        $output = array();
        foreach ($items as $item) {
            $inline = array();
            $item = (array)json_decode($item);
            if ($item === '-' || empty($item)) {
                $output[] = '-';
            } else {
                foreach ($item as $option) {
                    $inline[] = format_string($option->option);
                }
                $output[] = implode($inline, ', ');
            }
        }
        return implode($output, "\n");
    }

    // Assumes you used a custom grouping with the $this->uniquedelimiter to concatenate the fields.
    function rb_display_delimitedlist_url_to_newline($list, $row) {
        $delimiter = $this->uniquedelimiter;
        $items = explode($delimiter, $list);
        $output = array();
        foreach ($items as $item) {
            $item = json_decode($item);
            if ($item === '-' || empty($item)) {
                $output[] = '-';
            } else {
                $text = s(empty($item->text) ? $item->url : format_string($item->text));
                $target = isset($item->target) ? array('target' => '_blank', 'rel' => 'noreferrer') : null;
                $output[] = html_writer::link($item->url, $text, $target);
            }
        }
        return implode($output, "\n");
    }

    function rb_display_delimitedlist_posfiles_to_newline($data, $row, $isexport = false) {
        return $this->delimitedlist_files_to_newline($data, $row, 'position', $isexport);
    }

    function rb_display_delimitedlist_orgfiles_to_newline($data, $row, $isexport = false) {
        return $this->delimitedlist_files_to_newline($data, $row, 'organisation', $isexport);
    }

    // Assumes you used a custom grouping with the $this->uniquedelimiter to concatenate the fields.
    function delimitedlist_files_to_newline($data, $row, $type, $isexport) {
        global $CFG;
        require_once($CFG->dirroot . '/shezar/customfield/field/file/field.class.php');

        $delimiter = $this->uniquedelimiter;
        $items = explode($delimiter, $data);
        $extradata = array(
            'prefix' => $type,
            'isexport' => $isexport
        );

        $output = array();
        foreach ($items as $item) {
            if ($item === '-' || empty($item)) {
                $output[] = '-';
            } else {
                $output[] = customfield_file::display_item_data($item, $extradata);
            }
        }
        return implode($output, "\n");
    }

    // Assumes you used a custom grouping with the $this->uniquedelimiter to concatenate the fields.
    function rb_display_delimitedlist_location_to_newline($list, $row) {
        $delimiter = $this->uniquedelimiter;
        $items = explode($delimiter, $list);
        $output = array();
        foreach ($items as $item) {
            $item = json_decode($item);
            if ($item === '-' || empty($item)) {
                $output[] = '-';
            } else {
                $location = trim(str_replace("\r\n", " ", $item->address));
                $output[] = $location;
            }
        }
        return implode($output, "\n");
    }

    // Displays a comma separated list of ints as one nice_date per line.
    // Assumes you used "'grouping' => 'comma_list'", which concatenates with ', ', to construct the string.
    function rb_display_list_to_newline_date($datelist, $row) {
        $items = explode(', ', $datelist);
        foreach ($items as $key => $item) {
            if (empty($item) || $item === '-') {
                $items[$key] = '-';
            } else {
                $items[$key] = $this->rb_display_nice_date($item, $row);
            }
        }
        return implode($items, "\n");
    }

    // Displays a delimited list of ints as one nice_date per line, based off nice_date_list.
    // Assumes you used "'grouping' => 'sql_aggregate'", which concatenates with $uniquedelimiter to construct a pre-ordered string.
    function rb_display_orderedlist_to_newline_date($datelist, $row) {
        $output = array();
        $items = explode($this->uniquedelimiter, $datelist);
        foreach ($items as $item) {
            if (empty($item) || $item === '-') {
                $output[] = '-';
            } else {
                $output[] = userdate($item, get_string('strfdateshortmonth', 'langconfig'));
            }
        }
        return implode($output, "\n");
    }

    // Assumes you used a custom grouping with the $this->uniquedelimiter to concatenate the fields.
    function rb_display_delimitedlist_to_newline_date($datelist, $row) {
        $delimiter = $this->uniquedelimiter;
        $items = explode($delimiter, $datelist);
        $output = array();
        foreach ($items as $item) {
            if (empty($item) || $item === '-') {
                $output[] = '-';
            } else {
                $output[] = userdate($item, get_string('strfdateshortmonth', 'langconfig'));
            }
        }
        return implode($output, "\n");
    }

    /**
     * Display address from location stored as json object
     * @param string $location
     * @param stdClass $row
     * @param bool $isexport
     */
    public function rb_display_location($location, $row, $isexport = false) {
        global $CFG;
        require_once($CFG->dirroot . '/shezar/customfield/field/location/define.class.php');
        $output = array();

        $location = customfield_define_location::convert_location_json_to_object($location);

        if (is_null($location)){
            return get_string('notapplicable', 'facetoface');
        }

        $output[] = $location->address;

        return implode('', $output);
    }

    /**
     * Display correct course grade via grade or RPL as a percentage string
     *
     * @param string $item A number to convert
     * @param object $row Object containing all other fields for this row
     *
     * @return string The percentage with 1 decimal place
     */
    function rb_display_course_grade_percent($item, $row) {
        return ($item === null or $item === '') ? '-' : sprintf('%.1f%%', $item);
    }

    /**
     * A rb_column_options->displayfunc helper function for showing a user's name and links to their profile.
     * To pass the correct data, first:
     *      $usednamefields = shezar_get_all_user_name_fields_join($base, null, true);
     *      $allnamefields = shezar_get_all_user_name_fields_join($base);
     * then your "field" param should be:
     *      $DB->sql_concat_join("' '", $usednamefields)
     * to allow sorting and filtering, and finally your extrafields should be:
     *      array_merge(array('id' => $base . '.id'),
     *                  $allnamefields)
     * When exporting, only the user's full name is displayed (without link).
     *
     * @param string $user Unused
     * @param object $row All the data required to display a user's name
     * @param boolean $isexport If the report is being exported or viewed
     * @return string
     */
    function rb_display_link_user($user, $row, $isexport = false) {

        // Process obsolete calls to this display function.
        if (isset($row->user_id)) {
            $fullname = $user;
        } else {
            $fullname = fullname($row);
        }

        // Don't show links in spreadsheet.
        if ($isexport) {
            return $fullname;
        }

        $url = new moodle_url('/user/view.php', array('id' => $row->id));
        if ($fullname === '') {
            return '';
        } else {
            return html_writer::link($url, $fullname);
        }
    }

    /**
     * A rb_column_options->displayfunc helper function for showing a user's profile picture, name and links to their profile.
     * To pass the correct data, first:
     *      $usednamefields = shezar_get_all_user_name_fields_join($base, null, true);
     *      $allnamefields = shezar_get_all_user_name_fields_join($base);
     * then your "field" param should be:
     *      $DB->sql_concat_join("' '", $usednamefields)
     * to allow sorting and filtering, and finally your extrafields should be:
     *      array_merge(array('id' => $base . '.id',
     *                        'picture' => $base . '.picture',
     *                        'imagealt' => $base . '.imagealt',
     *                        'email' => $base . '.email'),
     *                  $allnamefields)
     * When exporting, only the user's full name is displayed (without icon or link).
     *
     * @param string $user Unused
     * @param object $row All the data required to display a user's name, icon and link
     * @param boolean $isexport If the report is being exported or viewed
     * @return string
     */
    function rb_display_link_user_icon($user, $row, $isexport = false) {
        global $OUTPUT;

        // Process obsolete calls to this display function.
        if (isset($row->userpic_picture)) {
            $picuser = new stdClass();
            $picuser->id = $row->user_id;
            $picuser->picture = $row->userpic_picture;
            $picuser->imagealt = $row->userpic_imagealt;
            $picuser->firstname = $row->userpic_firstname;
            $picuser->firstnamephonetic = $row->userpic_firstnamephonetic;
            $picuser->middlename = $row->userpic_middlename;
            $picuser->lastname = $row->userpic_lastname;
            $picuser->lastnamephonetic = $row->userpic_lastnamephonetic;
            $picuser->alternatename = $row->userpic_alternatename;
            $picuser->email = $row->userpic_email;
            $row = $picuser;
        }

        if ($row->id == 0) {
            return '';
        }

        // Don't show picture in spreadsheet.
        if ($isexport) {
            return fullname($row);
        }

        $url = new moodle_url('/user/view.php', array('id' => $row->id));
        return $OUTPUT->user_picture($row, array('courseid' => 1)) . "&nbsp;" . html_writer::link($url, $user);
    }

    /**
     * A rb_column_options->displayfunc helper function for showing a user's profile picture.
     * To pass the correct data, first:
     *      $usernamefields = shezar_get_all_user_name_fields_join($base, null, true);
     *      $allnamefields = shezar_get_all_user_name_fields_join($base);
     * then your "field" param should be:
     *      $DB->sql_concat_join("' '", $usednamefields)
     * to allow sorting and filtering, and finally your extrafields should be:
     *      array_merge(array('id' => $base . '.id',
     *                        'picture' => $base . '.picture',
     *                        'imagealt' => $base . '.imagealt',
     *                        'email' => $base . '.email'),
     *                  $allnamefields)
     * When exporting, only the user's full name is displayed (instead of picture).
     *
     * @param string $user Unused
     * @param object $row All the data required to display a user's name and icon
     * @param boolean $isexport If the report is being exported or viewed
     * @return string
     */
    function rb_display_user_picture($user, $row, $isexport = false) {
        global $OUTPUT;

        // Process obsolete calls to this display function.
        if (isset($row->userpic_picture)) {
            $picuser = new stdClass();
            $picuser->id = $user;
            $picuser->picture = $row->userpic_picture;
            $picuser->imagealt = $row->userpic_imagealt;
            $picuser->firstname = $row->userpic_firstname;
            $picuser->firstnamephonetic = $row->userpic_firstnamephonetic;
            $picuser->middlename = $row->userpic_middlename;
            $picuser->lastname = $row->userpic_lastname;
            $picuser->lastnamephonetic = $row->userpic_lastnamephonetic;
            $picuser->alternatename = $row->userpic_alternatename;
            $picuser->email = $row->userpic_email;
            $row = $picuser;
        }

        // Don't show picture in spreadsheet.
        if ($isexport) {
            return fullname($row);
        } else {
            return $OUTPUT->user_picture($row, array('courseid' => 1));
        }
    }

    /**
     * A rb_column_options->displayfunc helper function for showing a user's name.
     * To pass the correct data, first:
     *      $usednamefields = shezar_get_all_user_name_fields_join($base, null, true);
     *      $allnamefields = shezar_get_all_user_name_fields_join($base);
     * then your "field" param should be:
     *      $DB->sql_concat_join("' '", $usednamefields)
     * to allow sorting and filtering, and finally your extrafields should be:
     *      $allnamefields
     *
     * @param string $user Unused
     * @param object $row All the data required to display a user's name
     * @param boolean $isexport If the report is being exported or viewed
     * @return string
     */
    function rb_display_user($user, $row, $isexport = false) {
        return fullname($row);
    }

    /**
     * Convert a course name into an expanding link.
     *
     * @param string $course
     * @param array $row
     * @param bool $isexport
     * @return html|string
     */
    public function rb_display_course_expand($course, $row, $isexport = false) {
        if ($isexport) {
            return format_string($course);
        }

        $attr = array('class' => shezar_get_style_visibility($row, 'course_visible', 'course_audiencevisible'));
        $alturl = new moodle_url('/course/view.php', array('id' => $row->course_id));
        return $this->create_expand_link($course, 'course_details', array('expandcourseid' => $row->course_id), $alturl, $attr);
    }

    /**
     * Convert a program/certification name into an expanding link.
     *
     * @param string $program
     * @param array $row
     * @param bool $isexport
     * @return html|string
     */
    public function rb_display_program_expand($program, $row, $isexport = false) {
        if ($isexport) {
            return format_string($program);
        }

        $attr = array('class' => shezar_get_style_visibility($row, 'prog_visible', 'prog_audiencevisible'));
        $alturl = new moodle_url('/shezar/program/view.php', array('id' => $row->prog_id));
        return $this->create_expand_link($program, 'prog_details',
                array('expandprogid' => $row->prog_id), $alturl, $attr);
    }

    /**
     * Certification display the certification path as string.
     *
     * @param string $certifpath    CERTIFPATH_X constant to describe cert or recert coursesets
     * @param array $row            The record used to generate the table row
     * @return string
     */
    function rb_display_certif_certifpath($certifpath, $row) {
        global $CERTIFPATH;
        if ($certifpath && isset($CERTIFPATH[$certifpath])) {
            return get_string($CERTIFPATH[$certifpath], 'shezar_certification');
        }
    }

    /**
     * Certification display the certification renewal status as string.
     *
     * @param string $renewalstatus CERTIFRENEWALSTATUS_X constant to describe current renewal status
     * @param array $row            The record used to generate the table row
     * @return string
     */
    function rb_display_certif_renewalstatus($renewalstatus, $row) {
        global $CERTIFRENEWALSTATUS;

        if (!empty($row->unassigned)) {
            return '';
        } else if (!empty($row->status) && $row->status == CERTIFSTATUS_ASSIGNED) {
            // Just assigned.
            return '';
        } else if (!empty($row->status) && $row->status == CERTIFSTATUS_INPROGRESS && $renewalstatus == CERTIFRENEWALSTATUS_NOTDUE) {
            // First assignment and have made some progress.
            return '';
        } else {
            return get_string($CERTIFRENEWALSTATUS[$renewalstatus], 'shezar_certification');
        }
    }

    /**
     * Expanding content to display when clicking a course.
     * Will be placed inside a table cell which is the width of the table.
     * Call required_param to get any param data that is needed.
     * Make sure to check that the data requested is permitted for the viewer.
     *
     * @return string
     */
    public function rb_expand_course_details() {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/shezar/reportbuilder/report_forms.php');
        require_once($CFG->dirroot . '/course/renderer.php');
        require_once($CFG->dirroot . '/lib/coursecatlib.php');

        $formdata = array();

        $courseid = required_param('expandcourseid', PARAM_INT);
        $userid = $USER->id;

        if (!shezar_course_is_viewable($courseid)) {
            ajax_result(false, get_string('coursehidden'));
            exit();
        }

        $course = $DB->get_record('course', array('id' => $courseid));

        $chelper = new coursecat_helper();
        $formdata['summary'] = $chelper->get_course_formatted_summary(new course_in_list($course));

        $coursecontext = context_course::instance($course->id, MUST_EXIST);
        $enrolled = is_enrolled($coursecontext);
        $formdata['url'] = new moodle_url('/course/view.php', array('id' => $courseid));

        if ($enrolled) {
            $ccompl = new completion_completion(array('userid' => $userid, 'course' => $courseid));
            $complete = $ccompl->is_complete();
            if ($complete) {
                $sql = 'SELECT gg.*
                          FROM {grade_grades} gg
                          JOIN {grade_items} gi
                            ON gg.itemid = gi.id
                         WHERE gg.userid = ?
                           AND gi.courseid = ?';
                $grade = $DB->get_record_sql($sql, array($userid, $courseid));
                $coursecompletion = $DB->get_record('course_completions', array('userid' => $userid, 'course' => $courseid));
                $coursecompletedon = userdate($coursecompletion->timecompleted, get_string('strfdateshortmonth', 'langconfig'));

                $formdata['status'] = get_string('coursestatuscomplete', 'shezar_reportbuilder');
                $formdata['progress'] = get_string('coursecompletedon', 'shezar_reportbuilder', $coursecompletedon);
                if ($grade) {
                    if (!isset($grade->finalgrade)) {
                        $formdata['grade'] = '-';
                    } else {
                        $formdata['grade'] = get_string('xpercent', 'shezar_core', $grade->finalgrade);
                    }
                }
            } else {
                $formdata['status'] = get_string('coursestatusenrolled', 'shezar_reportbuilder');

                list($statusdpsql, $statusdpparams) = $this->get_dp_status_sql($userid, $courseid);
                $statusdp = $DB->get_record_sql($statusdpsql, $statusdpparams);
                $progress = shezar_display_course_progress_icon($userid, $courseid,
                    $statusdp->course_completion_statusandapproval);
                // Highlight if the item has not yet been approved.
                if ($statusdp->approved == DP_APPROVAL_UNAPPROVED
                        || $statusdp->approved == DP_APPROVAL_REQUESTED) {
                    $progress .= $this->rb_display_plan_item_status($statusdp->approved);
                }
                $formdata['progress'] = $progress;

                // Course not finished, so no end date for course.
                $formdata['enddate'] = '';
            }
            $formdata['action'] =  get_string('launchcourse', 'shezar_program');
        } else {
            $formdata['status'] = get_string('coursestatusnotenrolled', 'shezar_reportbuilder');

            $instances = enrol_get_instances($courseid, true);
            $plugins = enrol_get_plugins(true);

            $cansignup = false;
            $enrolmethodlist = array();
            $inlineenrolments = array();
            foreach ($instances as $instance) {
                if (!isset($plugins[$instance->enrol])) {
                    continue;
                }
                $plugin = $plugins[$instance->enrol];
                if (enrol_is_enabled($instance->enrol)) {
                    $enrolmethodlist[] = $plugin->get_instance_name($instance);
                    // If the enrolment plugin has a course_expand_hook then add to a list to process.
                    if (method_exists($plugin, 'course_expand_get_form_hook')
                        && method_exists($plugin, 'course_expand_enrol_hook')) {
                        $enrolment = array ('plugin' => $plugin, 'instance' => $instance);
                        $inlineenrolments[$instance->id] = (object) $enrolment;
                    }
                }
            }
            $enrolmethodstr = implode(', ', $enrolmethodlist);
            $realuser = \core\session\manager::get_realuser();

            $inlineenrolmentelements = $this->get_inline_enrolment_elements($inlineenrolments);
            $formdata['inlineenrolmentelements'] = $inlineenrolmentelements;
            $formdata['courseid'] = $course->id;

            // Enrolling methods.

            if ($cansignup) {
                $formdata['enroltype'] = get_string('courseenrolavailable', 'shezar_reportbuilder');
                $formdata['action'] = get_string('enrol', 'enrol');
                $formdata['url'] = new moodle_url('/enrol/index.php', array('id' => $courseid));
            } else if (is_viewing($coursecontext, $realuser->id) || is_siteadmin($realuser->id)) {
                $formdata['enroltype'] = $enrolmethodstr;
                $formdata['action'] = get_string('viewcourse', 'shezar_program');
                $formdata['url'] = new moodle_url('/course/view.php', array('id' => $courseid));
            } else {
                $formdata['enroltype'] = $enrolmethodstr;
                $formdata['action'] = get_string('notenrollable', 'enrol');
                $formdata['url'] = '';
            }
        }

        $mform = new report_builder_course_expand_form(null, $formdata);

        $this->process_enrolments($mform, $inlineenrolments);

        return $mform->render();
    }

    /**
     * @param $inlineenrolments array of objects containing matching instance/plugin pairs
     * @return array of form elements
     */
    private function get_inline_enrolment_elements(array $inlineenrolments) {
        global $CFG;

        require_once($CFG->dirroot . '/lib/pear/HTML/QuickForm/button.php');

        $retval = array();
        foreach ($inlineenrolments as $inlineenrolment) {
            $instance = $inlineenrolment->instance;
            $plugin = $inlineenrolment->plugin;
            $enrolform = $plugin->course_expand_get_form_hook($instance);

            $nameprefix = 'instanceid_' . $instance->id . '_';

            if (is_string($enrolform)) {
                $retval[] = new HTML_QuickForm_static(null, null, $enrolform);
                continue;
            }

            if ($enrolform instanceof moodleform) {
                foreach ($enrolform->_form->_elements as $element) {
                    if ($element->_type == 'button' || $element->_type == 'submit') {
                        continue;
                    } else if ($element->_type == 'group') {
                        $newelements = array();
                        foreach ($element->getElements() as $subelement) {
                            if ($subelement->_type == 'button' || $subelement->_type == 'submit') {
                                continue;
                            }
                            $subelement->setName($nameprefix . $element->getName());
                            $newelements[] = $subelement;
                        }
                        if (count($newelements)>0) {
                            $element->setElements($newelements);
                            $retval[] = $element;
                        }
                    } else {
                        $element->setName($nameprefix . $element->getName());
                        $retval[] = $element;
                    }
                }
            }

            if (count($inlineenrolments) > 1) {
                $enrollabel = get_string('enrolusing', 'shezar_reportbuilder', $plugin->get_instance_name($instance->id));
            } else {
                $enrollabel = get_string('enrol', 'shezar_reportbuilder');
            }
            $name = $instance->id;

            $retval[] = new HTML_QuickForm_button($name, $enrollabel, array('class' => 'expandenrol'));
        }
        return $retval;
    }

    /**
     * Expanding content to display when clicking a program.
     * Will be placed inside a table cell which is the width of the table.
     * Call required_param to get any param data that is needed.
     * Make sure to check that the data requested is permitted for the viewer.
     *
     * @return string
     */
    public function rb_expand_prog_details() {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/shezar/reportbuilder/report_forms.php');
        require_once($CFG->dirroot . '/shezar/program/renderer.php');

        $progid = required_param('expandprogid', PARAM_INT);
        $userid = $USER->id;

        if (!$program = new program($progid)) {
            ajax_result(false, get_string('error:programid', 'shezar_program'));
            exit();
        }

        if (!$program->is_viewable()) {
            ajax_result(false, get_string('error:inaccessible', 'shezar_program'));
            exit();
        }

        $formdata = $DB->get_record('prog', array('id' => $progid));

        $phelper = new programcat_helper();
        $formdata->summary = $phelper->get_program_formatted_summary(new program_in_list($formdata));

        $formdata->assigned = $DB->record_exists('prog_user_assignment', array('userid' => $userid, 'programid' => $progid));

        $mform = new report_builder_program_expand_form(null, (array)$formdata);

        return $mform->render();
    }

    /**
     * Get course progress status for user according his record of learning
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     */
    public function get_dp_status_sql($userid, $courseid) {
        global $CFG;
        require_once($CFG->dirroot.'/shezar/plan/rb_sources/rb_source_dp_course.php');
        // Use base query from rb_source_dp_course, and column/joins of statusandapproval.
        $base_sql = $this->get_dp_status_base_sql();
        $sql = "SELECT CASE WHEN dp_course.planstatus = " . DP_PLAN_STATUS_COMPLETE . "
                            THEN dp_course.completionstatus
                            ELSE course_completion.status
                            END AS course_completion_statusandapproval,
                       dp_course.approved AS approved
                 FROM ".$base_sql. " base
                 LEFT JOIN {course_completions} course_completion
                   ON (base.courseid = course_completion.course
                  AND base.userid = course_completion.userid)
                 LEFT JOIN (SELECT p.userid AS userid, p.status AS planstatus,
                                   pc.courseid AS courseid, pc.approved AS approved,
                                   pc.completionstatus AS completionstatus
                              FROM {dp_plan} p
                             INNER JOIN {dp_plan_course_assign} pc ON p.id = pc.planid) dp_course
                   ON dp_course.userid = base.userid AND dp_course.courseid = base.courseid
                WHERE base.userid = ? AND base.courseid = ?";
        return array($sql, array($userid, $courseid));
    }

    /**
     * Get base sql for course record of learning.
     * @return string
     */
    public function get_dp_status_base_sql() {
        global $DB;

        // Apply global user restrictions.
        $global_restriction_join_ue = $this->get_global_report_restriction_join('ue', 'userid');
        $global_restriction_join_cc = $this->get_global_report_restriction_join('cc', 'userid');
        $global_restriction_join_p1 = $this->get_global_report_restriction_join('p1', 'userid');

        $uniqueid = $DB->sql_concat_join("','", array(sql_cast2char('userid'), sql_cast2char('courseid')));
        return  "(SELECT " . $uniqueid . " AS id, userid, courseid
                    FROM (SELECT ue.userid AS userid, e.courseid AS courseid
                           FROM {user_enrolments} ue
                           JOIN {enrol} e ON ue.enrolid = e.id
                           {$global_restriction_join_ue}
                          UNION
                         SELECT cc.userid AS userid, cc.course AS courseid
                           FROM {course_completions} cc
                           {$global_restriction_join_cc}
                          WHERE cc.status > " . COMPLETION_STATUS_NOTYETSTARTED . "
                          UNION
                         SELECT p1.userid AS userid, pca1.courseid AS courseid
                           FROM {dp_plan_course_assign} pca1
                           JOIN {dp_plan} p1 ON pca1.planid = p1.id
                           {$global_restriction_join_p1}
                    )
                basesub)";
    }

    // convert a course name into a link to that course
    function rb_display_link_course($course, $row, $isexport = false) {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        if ($isexport) {
            return format_string($course);
        }

        $courseid = $row->course_id;
        $attr = array('class' => shezar_get_style_visibility($row, 'course_visible', 'course_audiencevisible'));
        $url = new moodle_url('/course/view.php', array('id' => $courseid));
        return html_writer::link($url, $course, $attr);
    }

    // convert a course name into a link to that course and shows
    // the course icon next to it
    function rb_display_link_course_icon($course, $row, $isexport = false) {
        global $CFG, $OUTPUT;
        require_once($CFG->dirroot . '/cohort/lib.php');

        if ($isexport) {
            return format_string($course);
        }

        $courseid = $row->course_id;
        $courseicon = !empty($row->course_icon) ? $row->course_icon : 'default';
        $cssclass = shezar_get_style_visibility($row, 'course_visible', 'course_audiencevisible');
        $icon = html_writer::empty_tag('img', array('src' => shezar_get_icon($courseid, shezar_ICON_TYPE_COURSE),
            'class' => 'course_icon', 'alt' => ''));
        $link = $OUTPUT->action_link(
            new moodle_url('/course/view.php', array('id' => $courseid)),
            $icon . $course, null, array('class' => $cssclass)
        );
        return $link;
    }

    // display an icon based on the course icon field
    function rb_display_course_icon($icon, $row, $isexport = false) {
        if ($isexport) {
            return format_string($row->course_name);
        }

        $coursename = format_string($row->course_name);
        $courseicon = html_writer::empty_tag('img', array('src' => shezar_get_icon($row->course_id, shezar_ICON_TYPE_COURSE),
            'class' => 'course_icon', 'alt' => $coursename));
        return $courseicon;
    }

    // display an icon for the course type
    function rb_display_course_type_icon($type, $row, $isexport = false) {
        global $OUTPUT;

        if ($isexport) {
            switch ($type) {
                case shezar_COURSE_TYPE_ELEARNING:
                    return get_string('elearning', 'rb_source_dp_course');
                case shezar_COURSE_TYPE_BLENDED:
                    return get_string('blended', 'rb_source_dp_course');
                case shezar_COURSE_TYPE_FACETOFACE:
                    return get_string('facetoface', 'rb_source_dp_course');
            }
            return '';
        }

        switch ($type) {
        case null:
            return null;
            break;
        case 0:
            $image = 'elearning';
            break;
        case 1:
            $image = 'blended';
            break;
        case 2:
            $image = 'facetoface';
            break;
        }
        $alt = get_string($image, 'rb_source_dp_course');
        $icon = $OUTPUT->pix_icon('/msgicons/' . $image . '-regular', $alt, 'shezar_core', array('title' => $alt));

        return $icon;
    }

    /**
     * Display course type text
     * @param string $type
     * @param array $row
     * @param bool $isexport
     * @return string
     */
    public function rb_display_course_type($type, $row, $isexport = false) {
        $types = $this->rb_filter_course_types();
        if (isset($types[$type])) {
            return $types[$type];
        }
        return '';
    }

    // convert a course category name into a link to that category's page
    function rb_display_link_course_category($category, $row, $isexport = false) {
        if ($isexport) {
            return format_string($category);
        }

        $catid = $row->cat_id;
        $category = format_string($category);
        if ($catid == 0 || !$catid) {
            return '';
        }
        $attr = (isset($row->cat_visible) && $row->cat_visible == 0) ? array('class' => 'dimmed') : array();
        $columns = array('coursecount' => 'course', 'programcount' => 'program', 'certifcount' => 'certification');
        foreach ($columns as $field => $viewtype) {
            if (isset($row->{$field})) {
                break;
            }
        }
        switch ($viewtype) {
            case 'program':
            case 'certification':
                $url = new moodle_url('/shezar/program/index.php', array('categoryid' => $catid, 'viewtype' => $viewtype));
                break;
            default:
                $url = new moodle_url('/course/index.php', array('categoryid' => $catid));
                break;
        }
        return html_writer::link($url, $category, $attr);
    }


    public function rb_display_audience_visibility($visibility, $row, $isexport = false) {
        global $COHORT_VISIBILITY;

        return $COHORT_VISIBILITY[$visibility];
    }


    /**
     * Generate the plan title with a link to the plan
     * @param string $planname
     * @param object $row
     * @param boolean $isexport If the report is being exported or viewed
     * @return string
     */
    public function rb_display_planlink($planname, $row, $isexport = false) {

        // no text
        if (strlen($planname) == 0) {
            return '';
        }

        // invalid id - show without a link
        if (empty($row->plan_id)) {
            return $planname;
        }

        if ($isexport) {
            return $planname;
        }
        $url = new moodle_url('/shezar/plan/view.php', array('id' => $row->plan_id));
        return html_writer::link($url, $planname);
    }


    /**
     * Display the plan's status (for use as a column displayfunc)
     *
     * @global object $CFG
     * @param int $status
     * @param object $row
     * @return string
     */
    public function rb_display_plan_status($status, $row) {
        global $CFG;
        require_once($CFG->dirroot . '/shezar/plan/lib.php');

        switch ($status) {
            case DP_PLAN_STATUS_UNAPPROVED:
                return get_string('unapproved', 'shezar_plan');
                break;
            case DP_PLAN_STATUS_APPROVED:
                return get_string('approved', 'shezar_plan');
                break;
            case DP_PLAN_STATUS_COMPLETE:
                return get_string('complete', 'shezar_plan');
                break;
        }
    }


    /**
     * Column displayfunc to convert a plan item's status to a
     * human-readable string
     *
     * @param int $status
     * @return string
     */
    public function rb_display_plan_item_status($status) {
        global $CFG;
        require_once($CFG->dirroot . '/shezar/plan/lib.php');

        switch($status) {
        case DP_APPROVAL_DECLINED:
            return get_string('declined', 'shezar_plan');
        case DP_APPROVAL_UNAPPROVED:
            return get_string('unapproved', 'shezar_plan');
        case DP_APPROVAL_REQUESTED:
            return get_string('pendingapproval', 'shezar_plan');
        case DP_APPROVAL_APPROVED:
            return get_string('approved', 'shezar_plan');
        default:
            return '';
        }
    }

    function rb_display_yes_no($item, $row) {
        if ($item === null or $item === '') {
            return '';
        } else if ($item) {
            return get_string('yes');
        } else {
            return get_string('no');
        }
    }

    function rb_display_delimitedlist_yes_no($data, $row) {
        $delimiter = $this->uniquedelimiter;
        $items = explode($delimiter, $data);
        $output = array();
        foreach ($items as $item) {
            if (!isset($item) || $item === '' || $item === '-') {
                $output[] = '-';
            } else if ($item) {
                $output[] = get_string('yes');
            } else {
                $output[] = get_string('no');
            }
        }
        return implode($output, "\n");
    }

    /**
     * Display duration in human readable format
     * @param integer $seconds
     * @param stdClass $row
     */
    public function rb_display_duration($seconds, $row) {
        if (empty($seconds)) {
            return '';
        }
        return format_time($seconds);
    }

    /**
     * Convert an integer number of minutes into a
     * formatted duration (e.g. 90 mins => 1h 30m).
     *
     * @deprecated Since 9.0
     * @param $mins
     * @param $row
     * @return mixed
     */
    function rb_display_hours_minutes($mins, $row) {
        debugging('The hours_minutes report builder display function has been deprecated and replaced by shezar_reportbuilder\rb\display\duration_hours_minutes', DEBUG_DEVELOPER);
        return $mins;
    }

    // convert a 2 digit country code into the country name
    function rb_display_country_code($code, $row) {
        $countries = get_string_manager()->get_list_of_countries();

        if (isset($countries[$code])) {
            return $countries[$code];
        }
        return $code;
    }

    // indicates if the user is deleted or not
    function rb_display_deleted_status($status, $row) {
        switch($status) {
            case 1:
                return get_string('deleteduser', 'shezar_reportbuilder');
            case 2:
                return get_string('suspendeduser', 'shezar_reportbuilder');
            default:
                return get_string('activeuser', 'shezar_reportbuilder');
        }
    }

    /**
     * Column displayfunc to show a hierarchy path as a human-readable string
     * @param $path the path string of delimited ids e.g. 1/3/7
     * @param $row data row
     */
    function rb_display_nice_hierarchy_path($path, $row) {
        global $DB;
        if (empty($path)) {
            return '';
        }
        $displaypath = '';
        $parentid = 0;
        // Make sure we know what we are looking for, and that the private var is populated (in source constructor).
        if (isset($row->hierarchytype) && isset($this->hierarchymap[$row->hierarchytype])) {
            $paths = explode('/', substr($path, 1));
            $map = $this->hierarchymap[$row->hierarchytype];
            foreach ($paths as $path) {
                if ($parentid !== 0) {
                    // Include ' > ' before name except on top element.
                    $displaypath .= ' &gt; ';
                }
                if (isset($map[$path])) {
                    $displaypath .= $map[$path];
                } else {
                    // Should not happen if paths are correct!
                    $displaypath .= get_string('unknown', 'shezar_reportbuilder');
                }
                $parentid = $path;
            }
        }

        return $displaypath;
    }

    /**
     * Column displayfunc to convert a language code to a human-readable string
     * @param $code Language code
     * @param $row data row - unused in this function
     * @return string
     */
    function rb_display_language_code($code, $row) {
            global $CFG;
        static $languages = array();
        $strmgr = get_string_manager();
        // Populate the static variable if empty
        if (count($languages) == 0) {
            // Return all languages available in system (adapted from stringmanager->get_list_of_translations()).
            $langdirs = get_list_of_plugins('', '', $CFG->langotherroot);
            $langdirs = array_merge($langdirs, array("{$CFG->dirroot}/lang/en"=>'en'));
            $curlang = current_language();
            // Loop through all langs and get info.
            foreach ($langdirs as $lang) {
                if (isset($languages[$lang])){
                    continue;
                }
                if (strstr($lang, '_local') !== false) {
                    continue;
                }
                if (strstr($lang, '_utf8') !== false) {
                    continue;
                }
                $string = $strmgr->load_component_strings('langconfig', $lang);
                if (!empty($string['thislanguage'])) {
                    $languages[$lang] = $string['thislanguage'];
                    // If not the current language, provide the English translation also.
                    if(strpos($lang, $curlang) === false) {
                        $languages[$lang] .= ' ('. $string['thislanguageint'] .')';
                    }
                }
                unset($string);
            }
        }

        if (empty($code)) {
            return get_string('notspecified', 'shezar_reportbuilder');
        }
        if (strpos($code, '_') !== false) {
            list($langcode, $langvariant) = explode('_', $code);
        } else {
            $langcode = $code;
        }

        // Now see if we have a match in "localname (English)" format.
        if (isset($languages[$code])) {
            return $languages[$code];
        } else {
            // Not an installed language - may have been uninstalled, as last resort try the get_list_of_languages silly function.
            $langcodes = $strmgr->get_list_of_languages();
            if (isset($langcodes[$langcode])) {
                $a = new stdClass();
                $a->code = $langcode;
                $a->name = $langcodes[$langcode];
                return get_string('uninstalledlanguage', 'shezar_reportbuilder', $a);
            } else {
                return get_string('unknownlanguage', 'shezar_reportbuilder', $code);
            }
        }
    }

    function rb_display_user_email($email, $row, $isexport = false) {
        if (empty($email)) {
            return '';
        }
        $maildisplay = $row->maildisplay;
        $emaildisabled = $row->emailstop;

        // respect users email privacy setting
        // at some point we may want to allow admins to view anyway
        if ($maildisplay != 1) {
            return get_string('useremailprivate', 'shezar_reportbuilder');
        }

        if ($isexport) {
            return $email;
        } else {
            // obfuscate email to avoid spam if printing to page
            return obfuscate_mailto($email, '', (bool) $emaildisabled);
        }
    }

    public function rb_display_user_email_unobscured($email, $row, $isexport = false) {
        if ($isexport) {
            return $email;
        } else {
            // Obfuscate email to avoid spam if printing to page.
            return obfuscate_mailto($email);
        }
    }

    public function rb_display_orderedlist_to_newline_email($list, $row, $isexport = false) {
        $output = array();
        $emails = explode($this->uniquedelimiter, $list);
        foreach ($emails as $email) {
            if ($isexport) {
                $output[] = $email;
            } else if ($email === '!private!') {
                $output[] = get_string('useremailprivate', 'shezar_reportbuilder');
            } else if ($email !== '-') {
                // Obfuscate email to avoid spam if printing to page.
                $output[] = obfuscate_mailto($email);
            } else {
                $output[] = '-';
            }
        }

        return implode($output, "\n");
    }

    function rb_display_link_program_icon($program, $row) {
        global $OUTPUT;
        $programid = $row->program_id;
        $programicon = !empty($row->program_icon) ? $row->program_icon : 'default';
        $programobj = (object) $row;
        $class = 'course_icon ' . shezar_get_style_visibility($programobj, 'program_visible', 'program_audiencevisible');
        $icon = html_writer::empty_tag('img', array('src' => shezar_get_icon($programid, shezar_ICON_TYPE_PROGRAM),
            'class' => $class, 'alt' => ''));
        $link = $OUTPUT->action_link(
            new moodle_url('/shezar/program/view.php', array('id' => $programid)),
            $icon . $program, null, array('class' => $class)
        );
        return $link;
    }

    /**
     * Generates the HTML to display the due/expiry date of a program/certification.
     *
     * @deprecated since 2.7 - use $this->usedcomponents[] = 'shezar_program' and 'displayfunc' => 'programduedate' instead
     * @param int $time     The duedate of the program
     * @param record $row   The whole row, including some required fields
     * @return html
     */
    public function rb_display_program_duedate($time, $row, $isexport = false) {
        // Get the necessary fields out of the row.
        $duedate = $time;
        $userid = $row->userid;
        $progid = $row->programid;
        $status = $row->status;
        $certifpath = isset($row->certifpath) ? $row->certifpath : null;
        $certifstatus = isset($row->certifstatus) ? $row->certifstatus : null;

        return prog_display_duedate($duedate, $progid, $userid, $certifpath, $certifstatus, $status, $isexport);
    }

    /**
     * Generates the HTML to display the due/expiry date of a certification.
     *
     * @deprecated since 2.7 - use $this->usedcomponents[] = 'shezar_program' and 'displayfunc' => 'programduedate' instead
     * @param int $time     The duedate of the program
     * @param record $row   The whole row, including some required fields
     * @return html
     */
    public function rb_display_certification_duedate($time, $row) {
        global $OUTPUT, $CFG;

        if (empty($row->timeexpires)) {
            if (empty($row->timedue) || $row->timedue == COMPLETION_TIME_NOT_SET) {
                // There is no time due set.
                return get_string('duedatenotset', 'shezar_program');
            } else if ($row->timedue > time() && $row->certifpath == CERTIFPATH_CERT) {
                // User is still in the first stage of certification, not overdue yet.
                return $this->rb_display_program_duedate($time, $row);
            } else {
                // Looks like the certification has expired, overdue!
                $out = '';
                $out .= userdate($row->timedue, get_string('strfdateshortmonth', 'langconfig'), 99, false);
                $out .= html_writer::empty_tag('br');
                $out .= $OUTPUT->error_text(get_string('overdue', 'shezar_program'));
                return $out;
            }
        } else {
            return $this->rb_display_program_duedate($time, $row);
        }

        return '';
    }

    // Display grade along with passing grade if it is known.
    function rb_display_grade_string($item, $row) {
        $passgrade = isset($row->gradepass) ? sprintf('%d', $row->gradepass) : null;
        $usergrade = sprintf('%d', $item);

        if ($item === null or $item === '') {
            return '';
        } else if ($passgrade === null) {
            return "{$usergrade}%";
        } else {
            $a = new stdClass();
            $a->grade = $usergrade;
            $a->pass = $passgrade;
            return get_string('gradeandgradetocomplete', 'shezar_reportbuilder', $a);
        }
    }

    //
    //
    // Generic select filter methods
    //
    //

    function rb_filter_yesno_list() {
        $yn = array();
        $yn[1] = get_string('yes');
        $yn[0] = get_string('no');
        return $yn;
    }

    function rb_filter_modules_list() {
        global $DB, $OUTPUT, $CFG;

        $out = array();
        $mods = $DB->get_records('modules', array('visible' => 1), 'id', 'id, name');
        foreach ($mods as $mod) {
            if (get_string_manager()->string_exists('pluginname', $mod->name)) {
                $modname = get_string('pluginname', $mod->name);
            } else {
                continue;
            }
            if (file_exists($CFG->dirroot . '/mod/' . $mod->name . '/pix/icon.gif') ||
                file_exists($CFG->dirroot . '/mod/' . $mod->name . '/pix/icon.png')) {
                $icon = $OUTPUT->pix_icon('icon', $modname, $mod->name) . '&nbsp;';
            } else {
                $icon = '';
            }

            $out[$mod->name] = $icon . $modname;
        }
        return $out;
    }

    function rb_filter_tags_list() {
        global $DB, $OUTPUT, $CFG;

        return $DB->get_records_menu('tag', array('tagtype' => 'official'), 'name', 'id, name');
    }

    function rb_filter_organisations_list($report) {
        global $CFG, $USER, $DB;

        require_once($CFG->dirroot . '/shezar/hierarchy/lib.php');
        require_once($CFG->dirroot . '/shezar/hierarchy/prefix/organisation/lib.php');

        $contentmode = $report->contentmode;
        $contentoptions = $report->contentoptions;
        $reportid = $report->_id;

        // show all options if no content restrictions set
        if ($contentmode == REPORT_BUILDER_CONTENT_MODE_NONE) {
            $hierarchy = new organisation();
            $hierarchy->make_hierarchy_list($orgs, null, true, false);
            return $orgs;
        }

        $baseorg = null; // default to top of tree

        $localset = false;
        $nonlocal = false;
        // are enabled content restrictions local or not?
        if (isset($contentoptions) && is_array($contentoptions)) {
            foreach ($contentoptions as $option) {
                $name = $option->classname;
                $classname = 'rb_' . $name . '_content';
                $settingname = $name . '_content';
                if (class_exists($classname)) {
                    if ($name == 'completed_org' || $name == 'current_org') {
                        if (reportbuilder::get_setting($reportid, $settingname, 'enable')) {
                            $localset = true;
                        }
                    } else {
                        if (reportbuilder::get_setting($reportid, $settingname, 'enable')) {
                            $nonlocal = true;
                        }
                    }
                }
            }
        }

        if ($contentmode == REPORT_BUILDER_CONTENT_MODE_ANY) {
            if ($localset && !$nonlocal) {
                // only restrict the org list if all content restrictions are local ones
                if ($orgid = $DB->get_field('job_assignment', 'organisationid', array('userid' => $USER->id))) {
                    $baseorg = $orgid;
                }
            }
        } else if ($contentmode == REPORT_BUILDER_CONTENT_MODE_ALL) {
            if ($localset) {
                // restrict the org list if any content restrictions are local ones
                if ($orgid = $DB->get_field('job_assignment', 'organisationid', array('userid' => $USER->id))) {
                    $baseorg = $orgid;
                }
            }
        }

        $hierarchy = new organisation();
        $hierarchy->make_hierarchy_list($orgs, $baseorg, true, false);

        return $orgs;

    }

    function rb_filter_positions_list() {
        global $CFG;
        require_once($CFG->dirroot . '/shezar/hierarchy/lib.php');
        require_once($CFG->dirroot . '/shezar/hierarchy/prefix/position/lib.php');

        $hierarchy = new position();
        $hierarchy->make_hierarchy_list($positions, null, true, false);

        return $positions;

    }

    function rb_filter_course_categories_list() {
        global $CFG;
        require_once($CFG->libdir . '/coursecatlib.php');
        $cats = coursecat::make_categories_list();

        return $cats;
    }


    function rb_filter_competency_type_list() {
        global $CFG;
        require_once($CFG->dirroot . '/shezar/hierarchy/prefix/competency/lib.php');

        $competencyhierarchy = new competency();
        $unclassified_option = array(0 => get_string('unclassified', 'shezar_hierarchy'));
        $typelist = $unclassified_option + $competencyhierarchy->get_types_list();

        return $typelist;
    }


    function rb_filter_position_type_list() {
        global $CFG;
        require_once($CFG->dirroot . '/shezar/hierarchy/prefix/position/lib.php');

        $positionhierarchy = new position();
        $unclassified_option = array(0 => get_string('unclassified', 'shezar_hierarchy'));
        $typelist = $unclassified_option + $positionhierarchy->get_types_list();

        return $typelist;
    }


    function rb_filter_organisation_type_list() {
        global $CFG;
        require_once($CFG->dirroot . '/shezar/hierarchy/prefix/organisation/lib.php');

        $organisationhierarchy = new organisation();
        $unclassified_option = array(0 => get_string('unclassified', 'shezar_hierarchy'));
        $typelist = $unclassified_option + $organisationhierarchy->get_types_list();

        return $typelist;
    }

    function rb_filter_course_languages() {
        global $DB;
        $out = array();
        $langs = $DB->get_records_sql("SELECT DISTINCT lang
            FROM {course} ORDER BY lang");
        foreach ($langs as $row) {
            $out[$row->lang] = $this->rb_display_language_code($row->lang, array());
        }

        return $out;
    }

    /**
     *
     * @return array possible course types
     */
    public function rb_filter_course_types() {
        global $shezar_COURSE_TYPES;
        $coursetypeoptions = array();
        foreach ($shezar_COURSE_TYPES as $k => $v) {
            $coursetypeoptions[$v] = get_string($k, 'shezar_core');
        }
        return $coursetypeoptions;
    }

    //
    //
    // Generic grouping methods for aggregation
    //
    //

    function rb_group_count($field) {
        return "COUNT($field)";
    }

    function rb_group_unique_count($field) {
        return "COUNT(DISTINCT $field)";
    }

    function rb_group_sum($field) {
        return "SUM($field)";
    }

    function rb_group_average($field) {
        return "AVG($field)";
    }

    function rb_group_max($field) {
        return "MAX($field)";
    }

    function rb_group_min($field) {
        return "MIN($field)";
    }

    function rb_group_stddev($field) {
        return "STDDEV($field)";
    }

    // can be used to 'fake' a percentage, if matching values return 1 and
    // all other values return 0 or null
    function rb_group_percent($field) {
        global $DB;

        return $DB->sql_round("AVG($field*100.0)", 0);
    }

    /**
     * This function calls the databases native implementations of
     * group_concat where possible and requires an additional $orderby
     * variable. If you create another one you should add it to the
     * $sql_functions array() in the get_fields() function in the rb_columns class.
     *
     * @param string $field         The expression to use as the select
     * @param string $orderby       The comma deliminated fields to order by
     * @return string               The native sql for a group concat
     */
    function rb_group_sql_aggregate($field, $orderby) {
        global $DB;

        return $DB->sql_group_concat($field, $this->uniquedelimiter, $orderby);
    }

    // return list as single field, separated by commas
    function rb_group_comma_list($field) {
        return sql_group_concat($field);
    }

    // Return list as single field, without a separator delimiter.
    function rb_group_list_nodelimiter($field) {
        return sql_group_concat($field, '');
    }

    // return unique list items as single field, separated by commas
    function rb_group_comma_list_unique($field) {
        return sql_group_concat($field, ', ', true);
    }

    // return list as single field, one per line
    function rb_group_list($field) {
        return sql_group_concat($field, html_writer::empty_tag('br'));
    }

    // return unique list items as single field, one per line
    function rb_group_list_unique($field) {
        return sql_group_concat($field, html_writer::empty_tag('br'), true);
    }

    // return list as single field, separated by a line with - on (in HTML)
    function rb_group_list_dash($field) {
        return sql_group_concat($field, html_writer::empty_tag('br') . '-' . html_writer::empty_tag('br'));
    }

    //
    //
    // Methods for adding commonly used data to source definitions
    //
    //

    //
    // Wrapper functions to add columns/fields/joins in one go
    //
    //

    /**
     * Populate the hierarchymap private variable to look up Hierarchy names from ids
     * e.g. when converting a hierarchy path from ids to human-readable form
     *
     * @param array $hierarchies array of all the hierarchy types we want to populate (pos, org, comp, goal etc)
     *
     * @return boolean True
     */
    function populate_hierarchy_name_map($hierarchies) {
        global $DB;
        foreach ($hierarchies as $hierarchy) {
            $this->hierarchymap["{$hierarchy}"] = $DB->get_records_menu($hierarchy, null, 'id', 'id, fullname');
        }
        return true;
    }

    /**
     * Returns true if global report restrictions can be used with this source.
     *
     * @return bool
     */
    protected function can_global_report_restrictions_be_used() {
        global $CFG;
        return (!empty($CFG->enableglobalrestrictions) && $this->global_restrictions_supported()
                && $this->globalrestrictionset);
    }

    /**
     * Returns global restriction SQL fragment that can be used in complex joins for example.
     *
     * @return string SQL fragment
     */
    protected function get_global_report_restriction_query() {
        // First ensure that global report restrictions can be used with this source.
        if (!$this->can_global_report_restrictions_be_used()) {
            return '';
        }

        list($query, $parameters) = $this->globalrestrictionset->get_join_query();

        if ($parameters) {
            $this->globalrestrictionparams = array_merge($this->globalrestrictionparams, $parameters);
        }

        return $query;
    }

    /**
     * Adds global restriction join to the report.
     *
     * @param string $join Name of the join that provides the 'user id' field
     * @param string $field Name of user id field to join on
     * @param mixed $dependencies join dependencies
     * @return bool
     */
    protected function add_global_report_restriction_join($join, $field, $dependencies = 'base') {
        // First ensure that global report restrictions can be used with this source.
        if (!$this->can_global_report_restrictions_be_used()) {
            return false;
        }

        list($query, $parameters) = $this->globalrestrictionset->get_join_query();

        if ($query === '') {
            return false;
        }

        static $counter = 0;
        $counter++;
        $joinname = 'globalrestrjoin_' . $counter;

        $this->globalrestrictionjoins[] = new rb_join(
            $joinname,
            'INNER',
            "($query)",
            "$joinname.id = $join.$field",
            REPORT_BUILDER_RELATION_ONE_TO_MANY,
            $dependencies
        );

        if ($parameters) {
            $this->globalrestrictionparams = array_merge($this->globalrestrictionparams, $parameters);
        }

        return true;
    }

    /**
     * Get global restriction join SQL to the report. All parameters will be inline.
     *
     * @param string $join Name of the join that provides the 'user id' field
     * @param string $field Name of user id field to join on
     * @return string
     */
    protected function get_global_report_restriction_join($join, $field) {
        // First ensure that global report restrictions can be used with this source.
        if (!$this->can_global_report_restrictions_be_used()) {
            return  '';
        }

        list($query, $parameters) = $this->globalrestrictionset->get_join_query();

        if (empty($query)) {
            return '';
        }

        if ($parameters) {
            $this->globalrestrictionparams = array_merge($this->globalrestrictionparams, $parameters);
        }

        static $counter = 0;
        $counter++;
        $joinname = 'globalinlinerestrjoin_' . $counter;

        $joinsql = " INNER JOIN ($query) $joinname ON ($joinname.id = $join.$field) ";
        return $joinsql;
    }

    /**
     * Adds the user table to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     'user id' field
     * @param string $field Name of user id field to join on
     * @param string $alias Use custom user table alias
     * @return boolean True
     */
    protected function add_user_table_to_joinlist(&$joinlist, $join, $field, $alias = 'auser') {
        // join uses 'auser' as name because 'user' is a reserved keyword
        $joinlist[] = new rb_join(
            $alias,
            'LEFT',
            '{user}',
            "{$alias}.id = $join.$field",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );

        return true;
    }


    /**
     * Adds some common user field to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $join Name of the join that provides the 'user' table
     * @param string $groupname The group to add fields to. If you are defining
     *                          a custom group name, you must define a language
     *                          string with the key "type_{$groupname}" in your
     *                          report source language file.
     * @param boolean $$addtypetoheading Add the column type to the column heading
     *                          to differentiate between fields with the same name.
     *
     * @return True
     */
    protected function add_user_fields_to_columns(&$columnoptions,
        $join='auser', $groupname = 'user', $addtypetoheading = false) {
        global $DB, $CFG;

        $usednamefields = shezar_get_all_user_name_fields_join($join, null, true);
        $allnamefields = shezar_get_all_user_name_fields_join($join);

        $columnoptions[] = new rb_column_option(
            $groupname,
            'fullname',
            get_string('userfullname', 'shezar_reportbuilder'),
            $DB->sql_concat_join("' '", $usednamefields),
            array('joins' => $join,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text',
                  'extrafields' => $allnamefields,
                  'displayfunc' => 'user',
                  'addtypetoheading' => $addtypetoheading
            )
        );
        $columnoptions[] = new rb_column_option(
            $groupname,
            'namelink',
            get_string('usernamelink', 'shezar_reportbuilder'),
            $DB->sql_concat_join("' '", $usednamefields),
            array(
                'joins' => $join,
                'displayfunc' => 'link_user',
                'defaultheading' => get_string('userfullname', 'shezar_reportbuilder'),
                'extrafields' => array_merge(array('id' => "$join.id"), $allnamefields),
                'addtypetoheading' => $addtypetoheading
            )
        );
        $columnoptions[] = new rb_column_option(
            $groupname,
            'namelinkicon',
            get_string('usernamelinkicon', 'shezar_reportbuilder'),
            $DB->sql_concat_join("' '", $usednamefields),
            array(
                'joins' => $join,
                'displayfunc' => 'link_user_icon',
                'defaultheading' => get_string('userfullname', 'shezar_reportbuilder'),
                'extrafields' => array_merge(array('id' => "$join.id",
                                                   'picture' => "$join.picture",
                                                   'imagealt' => "$join.imagealt",
                                                   'email' => "$join.email"),
                                             $allnamefields),
                'style' => array('white-space' => 'nowrap'),
                'addtypetoheading' => $addtypetoheading
            )
        );
        $columnoptions[] = new rb_column_option(
            $groupname,
            'email',
            get_string('useremail', 'shezar_reportbuilder'),
            // use CASE to include/exclude email in SQL
            // so search won't reveal hidden results
            "CASE WHEN $join.maildisplay <> 1 THEN '-' ELSE $join.email END",
            array(
                'joins' => $join,
                'displayfunc' => 'user_email',
                'extrafields' => array(
                    'emailstop' => "$join.emailstop",
                    'maildisplay' => "$join.maildisplay",
                ),
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'addtypetoheading' => $addtypetoheading
            )
        );
        // Only include this column if email is among fields allowed by showuseridentity setting or
        // if the current user has the 'moodle/site:config' capability.
        $canview = !empty($CFG->showuseridentity) && in_array('email', explode(',', $CFG->showuseridentity));
        $canview |= has_capability('moodle/site:config', context_system::instance());
        if ($canview) {
            $columnoptions[] = new rb_column_option(
                $groupname,
                'emailunobscured',
                get_string('useremailunobscured', 'shezar_reportbuilder'),
                "$join.email",
                array(
                    'joins' => $join,
                    'displayfunc' => 'user_email_unobscured',
                    // Users must have viewuseridentity to see the
                    // unobscured email address.
                    'capability' => 'moodle/site:viewuseridentity',
                    'dbdatatype' => 'char',
                    'outputformat' => 'text',
                    'addtypetoheading' => $addtypetoheading
                )
            );
        }
        $columnoptions[] = new rb_column_option(
            $groupname,
            'lastlogin',
            get_string('userlastlogin', 'shezar_reportbuilder'),
            // See MDL-22481 for why currentlogin is used instead of lastlogin
            "$join.currentlogin",
            array(
                'joins' => $join,
                'displayfunc' => 'nice_date',
                'dbdatatype' => 'timestamp',
                'addtypetoheading' => $addtypetoheading
            )
        );
        $columnoptions[] = new rb_column_option(
            $groupname,
            'firstaccess',
            get_string('userfirstaccess', 'shezar_reportbuilder'),
            "$join.firstaccess",
            array(
                'joins' => $join,
                'displayfunc' => 'nice_datetime',
                'dbdatatype' => 'timestamp',
                'addtypetoheading' => $addtypetoheading
            )
        );
        $columnoptions[] = new rb_column_option(
            $groupname,
            'lang',
            get_string('userlang', 'shezar_reportbuilder'),
            "$join.lang",
            array(
                'joins' => $join,
                'displayfunc' => 'language_code',
                'addtypetoheading' => $addtypetoheading
            )
        );
        // auto-generate columns for user fields
        $fields = array(
            'firstname' => get_string('userfirstname', 'shezar_reportbuilder'),
            'firstnamephonetic' => get_string('userfirstnamephonetic', 'shezar_reportbuilder'),
            'middlename' => get_string('usermiddlename', 'shezar_reportbuilder'),
            'lastname' => get_string('userlastname', 'shezar_reportbuilder'),
            'lastnamephonetic' => get_string('userlastnamephonetic', 'shezar_reportbuilder'),
            'alternatename' => get_string('useralternatename', 'shezar_reportbuilder'),
            'username' => get_string('username', 'shezar_reportbuilder'),
            'idnumber' => get_string('useridnumber', 'shezar_reportbuilder'),
            'phone1' => get_string('userphone', 'shezar_reportbuilder'),
            'institution' => get_string('userinstitution', 'shezar_reportbuilder'),
            'department' => get_string('userdepartment', 'shezar_reportbuilder'),
            'address' => get_string('useraddress', 'shezar_reportbuilder'),
            'city' => get_string('usercity', 'shezar_reportbuilder'),
        );
        foreach ($fields as $field => $name) {
            $columnoptions[] = new rb_column_option(
                $groupname,
                $field,
                $name,
                "$join.$field",
                array('joins' => $join,
                      'dbdatatype' => 'char',
                      'outputformat' => 'text',
                      'addtypetoheading' => $addtypetoheading
                )
            );
        }
        $columnoptions[] = new rb_column_option(
            $groupname,
            'id',
            get_string('userid', 'shezar_reportbuilder'),
            "$join.id",
            array('joins' => $join,
                  'addtypetoheading' => $addtypetoheading
            )
        );

        // add country option
        $columnoptions[] = new rb_column_option(
            $groupname,
            'country',
            get_string('usercountry', 'shezar_reportbuilder'),
            "$join.country",
            array(
                'joins' => $join,
                'displayfunc' => 'country_code',
                'addtypetoheading' => $addtypetoheading
            )
        );

        // add deleted option
        $columnoptions[] = new rb_column_option(
            $groupname,
            'deleted',
            get_string('userstatus', 'shezar_reportbuilder'),
            "CASE WHEN $join.deleted = 0 and $join.suspended = 1 THEN 2 ELSE $join.deleted END",
            array(
                'joins' => $join,
                'displayfunc' => 'deleted_status',
                'addtypetoheading' => $addtypetoheading
            )
        );
        $columnoptions[] = new rb_column_option(
            $groupname,
            'timecreated',
            get_string('usertimecreated', 'shezar_reportbuilder'),
            "$join.timecreated",
            array(
                'joins' => $join,
                'displayfunc' => 'nice_datetime',
                'dbdatatype' => 'timestamp',
                'addtypetoheading' => $addtypetoheading
            )
        );
        $columnoptions[] = new rb_column_option(
            $groupname,
            'timemodified',
            get_string('usertimemodified', 'shezar_reportbuilder'),
            "$join.timemodified",
            array(
                'joins' => $join,
                'displayfunc' => 'nice_datetime',
                'dbdatatype' => 'timestamp',
                'addtypetoheading' => $addtypetoheading
            )
        );

        return true;
    }


    /**
     * Adds some common user field to the $filteroptions array
     *
     * @param array &$filteroptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $groupname Name of group to filter. If you are defining
     *                          a custom group name, you must define a language
     *                          string with the key "type_{$groupname}" in your
     *                          report source language file.
     * @return True
     */
    protected function add_user_fields_to_filters(&$filteroptions, $groupname = 'user', $addtypetoheading = false) {
        global $CFG;
        // auto-generate filters for user fields
        $fields = array(
            'fullname' => get_string('userfullname', 'shezar_reportbuilder'),
            'firstname' => get_string('userfirstname', 'shezar_reportbuilder'),
            'firstnamephonetic' => get_string('userfirstnamephonetic', 'shezar_reportbuilder'),
            'middlename' => get_string('usermiddlename', 'shezar_reportbuilder'),
            'lastname' => get_string('userlastname', 'shezar_reportbuilder'),
            'lastnamephonetic' => get_string('userlastnamephonetic', 'shezar_reportbuilder'),
            'alternatename' => get_string('useralternatename', 'shezar_reportbuilder'),
            'username' => get_string('username'),
            'idnumber' => get_string('useridnumber', 'shezar_reportbuilder'),
            'phone1' => get_string('userphone', 'shezar_reportbuilder'),
            'institution' => get_string('userinstitution', 'shezar_reportbuilder'),
            'department' => get_string('userdepartment', 'shezar_reportbuilder'),
            'address' => get_string('useraddress', 'shezar_reportbuilder'),
            'city' => get_string('usercity', 'shezar_reportbuilder'),
            'email' => get_string('useremail', 'shezar_reportbuilder'),
        );
        // Only include this filter if email is among fields allowed by showuseridentity setting or
        // if the current user has the 'moodle/site:config' capability.
        $canview = !empty($CFG->showuseridentity) && in_array('email', explode(',', $CFG->showuseridentity));
        $canview |= has_capability('moodle/site:config', context_system::instance());
        if ($canview) {
            $fields['emailunobscured'] = get_string('useremailunobscured', 'shezar_reportbuilder');
        }

        foreach ($fields as $field => $name) {
            $filteroptions[] = new rb_filter_option(
                $groupname,
                $field,
                $name,
                'text',
                array('addtypetoheading' => $addtypetoheading)
            );
        }

        // pulldown with list of countries
        $select_width_options = rb_filter_option::select_width_limiter();
        $filteroptions[] = new rb_filter_option(
            $groupname,
            'country',
            get_string('usercountry', 'shezar_reportbuilder'),
            'select',
            array(
                'selectchoices' => get_string_manager()->get_list_of_countries(),
                'attributes' => $select_width_options,
                'simplemode' => true,
                'addtypetoheading' => $addtypetoheading
            )
        );
        $filteroptions[] = new rb_filter_option(
            $groupname,
            'deleted',
            get_string('userstatus', 'shezar_reportbuilder'),
            'select',
            array(
                'selectchoices' => array(0 => get_string('activeonly', 'shezar_reportbuilder'),
                                         1 => get_string('deletedonly', 'shezar_reportbuilder'),
                                         2 => get_string('suspendedonly', 'shezar_reportbuilder')),
                'attributes' => $select_width_options,
                'simplemode' => true,
                'addtypetoheading' => $addtypetoheading
            )
        );

        $filteroptions[] = new rb_filter_option(
            $groupname,
            'lastlogin',
            get_string('userlastlogin', 'shezar_reportbuilder'),
            'date',
            array(
                'includetime' => true,
                'addtypetoheading' => $addtypetoheading
            )
        );

        $filteroptions[] = new rb_filter_option(
            $groupname,
            'firstaccess',
            get_string('userfirstaccess', 'shezar_reportbuilder'),
            'date',
            array(
                'includetime' => true,
                'addtypetoheading' => $addtypetoheading
            )
        );

        $filteroptions[] = new rb_filter_option(
            $groupname,
            'timecreated',
            get_string('usertimecreated', 'shezar_reportbuilder'),
            'date',
            array(
                'includetime' => true,
                'addtypetoheading' => $addtypetoheading
            )
        );

        $filteroptions[] = new rb_filter_option(
            $groupname,
            'timemodified',
            get_string('usertimemodified', 'shezar_reportbuilder'),
            'date',
            array(
                'includetime' => true,
                'addtypetoheading' => $addtypetoheading
            )
        );

        return true;
    }

    /**
     * Adds the basic user based content options
     *      - Manager
     *      - Position
     *      - Organisation
     *
     * @param array $contentoptions     The sources content options array
     * @param string $join              The name of the user table in the report
     * @return boolean
     */
    protected function add_basic_user_content_options(&$contentoptions, $join = 'auser') {
        // Add the manager/staff content options.
        $contentoptions[] = new rb_content_option(
                                    'user',
                                    get_string('user', 'rb_source_user'),
                                    "{$join}.id",
                                    "{$join}"
                                );
        // Add the position content options.
        $contentoptions[] = new rb_content_option(
                                    'current_pos',
                                    get_string('currentpos', 'shezar_reportbuilder'),
                                    "{$join}.id",
                                    "{$join}"
                                );
        // Add the organisation content options.
        $contentoptions[] = new rb_content_option(
                                    'current_org',
                                    get_string('currentorg', 'shezar_reportbuilder'),
                                    "{$join}.id",
                                    "{$join}"
        );

        return true;
    }

    /**
     * Adds the course table to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     'course id' field
     * @param string $field Name of course id field to join on
     * @param string $jointype Type of Join (INNER, LEFT, RIGHT)
     * @return boolean True
     */
    protected function add_course_table_to_joinlist(&$joinlist, $join, $field, $jointype = 'LEFT') {

        $joinlist[] = new rb_join(
            'course',
            $jointype,
            '{course}',
            "course.id = $join.$field",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );
    }

    /**
     * Adds the course table to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     'course id' field
     * @param string $field Name of course id field to join on
     * @param int $contextlevel Name of course id field to join on
     * @param string $jointype Type of join (INNER, LEFT, RIGHT)
     * @return boolean True
     */
    protected function add_context_table_to_joinlist(&$joinlist, $join, $field, $contextlevel, $jointype = 'LEFT') {

        $joinlist[] = new rb_join(
            'ctx',
            $jointype,
            '{context}',
            "ctx.instanceid = $join.$field AND ctx.contextlevel = $contextlevel",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );
    }


    /**
     * Adds some common course info to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $join Name of the join that provides the 'course' table
     *
     * @return True
     */
    protected function add_course_fields_to_columns(&$columnoptions, $join='course') {
        global $DB;

        $columnoptions[] = new rb_column_option(
            'course',
            'fullname',
            get_string('coursename', 'shezar_reportbuilder'),
            "$join.fullname",
            array('joins' => $join,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'courselink',
            get_string('coursenamelinked', 'shezar_reportbuilder'),
            "$join.fullname",
            array(
                'joins' => $join,
                'displayfunc' => 'link_course',
                'defaultheading' => get_string('coursename', 'shezar_reportbuilder'),
                'extrafields' => array('course_id' => "$join.id",
                                       'course_visible' => "$join.visible",
                                       'course_audiencevisible' => "$join.audiencevisible")
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'courseexpandlink',
            get_string('courseexpandlink', 'shezar_reportbuilder'),
            "$join.fullname",
            array(
                'joins' => $join,
                'displayfunc' => 'course_expand',
                'defaultheading' => get_string('coursename', 'shezar_reportbuilder'),
                'extrafields' => array(
                    'course_id' => "$join.id",
                    'course_visible' => "$join.visible",
                    'course_audiencevisible' => "$join.audiencevisible"
                )
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'courselinkicon',
            get_string('coursenamelinkedicon', 'shezar_reportbuilder'),
            "$join.fullname",
            array(
                'joins' => $join,
                'displayfunc' => 'link_course_icon',
                'defaultheading' => get_string('coursename', 'shezar_reportbuilder'),
                'extrafields' => array(
                    'course_id' => "$join.id",
                    'course_icon' => "$join.icon",
                    'course_visible' => "$join.visible",
                    'course_audiencevisible' => "$join.audiencevisible"
                )
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'visible',
            get_string('coursevisible', 'shezar_reportbuilder'),
            "$join.visible",
            array(
                'joins' => $join,
                'displayfunc' => 'yes_no'
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'audvis',
            get_string('audiencevisibility', 'shezar_reportbuilder'),
            "$join.audiencevisible",
            array(
                'joins' => $join,
                'displayfunc' => 'audience_visibility'
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'icon',
            get_string('courseicon', 'shezar_reportbuilder'),
            "$join.icon",
            array(
                'joins' => $join,
                'displayfunc' => 'course_icon',
                'defaultheading' => get_string('courseicon', 'shezar_reportbuilder'),
                'extrafields' => array(
                    'course_name' => "$join.fullname",
                    'course_id' => "$join.id",
                )
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'shortname',
            get_string('courseshortname', 'shezar_reportbuilder'),
            "$join.shortname",
            array('joins' => $join,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'idnumber',
            get_string('courseidnumber', 'shezar_reportbuilder'),
            "$join.idnumber",
            array('joins' => $join,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'id',
            get_string('courseid', 'shezar_reportbuilder'),
            "$join.id",
            array('joins' => $join)
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'startdate',
            get_string('coursestartdate', 'shezar_reportbuilder'),
            "$join.startdate",
            array(
                'joins' => $join,
                'displayfunc' => 'nice_date',
                'dbdatatype' => 'timestamp'
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'name_and_summary',
            get_string('coursenameandsummary', 'shezar_reportbuilder'),
            // Case used to merge even if one value is null.
            "CASE WHEN $join.fullname IS NULL THEN $join.summary
                WHEN $join.summary IS NULL THEN $join.fullname
                ELSE " . $DB->sql_concat("$join.fullname", "'" . html_writer::empty_tag('br') . "'",
                    "$join.summary") . ' END',
            array(
                'joins' => $join,
                'displayfunc' => 'tinymce_textarea',
                'extrafields' => array(
                    'filearea' => '\'summary\'',
                    'component' => '\'course\'',
                    'context' => '\'context_course\'',
                    'recordid' => "$join.id"
                )
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'summary',
            get_string('coursesummary', 'shezar_reportbuilder'),
            "$join.summary",
            array(
                'joins' => $join,
                'displayfunc' => 'tinymce_textarea',
                'extrafields' => array(
                    'format' => "$join.summaryformat",
                    'filearea' => '\'summary\'',
                    'component' => '\'course\'',
                    'context' => '\'context_course\'',
                    'recordid' => "$join.id"
                ),
                'dbdatatype' => 'text',
                'outputformat' => 'text'
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'coursetypeicon',
            get_string('coursetypeicon', 'shezar_reportbuilder'),
            "$join.coursetype",
            array(
                'joins' => $join,
                'displayfunc' => 'course_type_icon',
                'defaultheading' => get_string('coursetypeicon', 'shezar_reportbuilder'),
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'coursetype',
            get_string('coursetype', 'shezar_reportbuilder'),
            "$join.coursetype",
            array(
                'joins' => $join,
                'displayfunc' => 'course_type',
                'defaultheading' => get_string('coursetype', 'shezar_reportbuilder'),
            )
        );
        // add language option
        $columnoptions[] = new rb_column_option(
            'course',
            'language',
            get_string('courselanguage', 'shezar_reportbuilder'),
            "$join.lang",
            array(
                'joins' => $join,
                'displayfunc' => 'language_code'
            )
        );

        return true;
    }


    /**
     * Adds some common course filters to the $filteroptions array
     *
     * @param array &$filteroptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True
     */
    protected function add_course_fields_to_filters(&$filteroptions) {
        $filteroptions[] = new rb_filter_option(
            'course',
            'fullname',
            get_string('coursename', 'shezar_reportbuilder'),
            'text'
        );
        $filteroptions[] = new rb_filter_option(
            'course',
            'shortname',
            get_string('courseshortname', 'shezar_reportbuilder'),
            'text'
        );
        $filteroptions[] = new rb_filter_option(
            'course',
            'idnumber',
            get_string('courseidnumber', 'shezar_reportbuilder'),
            'text'
        );
        $audvisibility = get_config(null, 'audiencevisibility');
        if (empty($audvisibility)) {
            $coursevisiblestring = get_string('coursevisible', 'shezar_reportbuilder');
            $audvisiblilitystring = get_string('audiencevisibilitydisabled', 'shezar_reportbuilder');
        } else {
            $coursevisiblestring = get_string('coursevisibledisabled', 'shezar_reportbuilder');
            $audvisiblilitystring = get_string('audiencevisibility', 'shezar_reportbuilder');
        }
        $filteroptions[] = new rb_filter_option(
            'course',
            'visible',
            $coursevisiblestring,
            'select',
            array(
                'selectchoices' => array(0 => get_string('no'), 1 => get_string('yes')),
                'simplemode' => true
            )
        );
        $filteroptions[] = new rb_filter_option(
            'course',
            'audvis',
            $audvisiblilitystring,
            'select',
            array(
                'selectchoices' => array(
                    COHORT_VISIBLE_NOUSERS => get_string('visiblenousers', 'shezar_cohort'),
                    COHORT_VISIBLE_ENROLLED => get_string('visibleenrolled', 'shezar_cohort'),
                    COHORT_VISIBLE_AUDIENCE => get_string('visibleaudience', 'shezar_cohort'),
                    COHORT_VISIBLE_ALL => get_string('visibleall', 'shezar_cohort')),
                'simplemode' => true
            )
        );
        $filteroptions[] = new rb_filter_option(
            'course',
            'startdate',
            get_string('coursestartdate', 'shezar_reportbuilder'),
            'date'
        );
        $filteroptions[] = new rb_filter_option(
            'course',
            'name_and_summary',
            get_string('coursenameandsummary', 'shezar_reportbuilder'),
            'textarea'
        );
        $filteroptions[] = new rb_filter_option(
            'course',
            'coursetype',
            get_string('coursetype', 'shezar_reportbuilder'),
            'multicheck',
            array(
                'selectfunc' => 'course_types',
                'simplemode' => true,
                'showcounts' => array(
                        'joins' => array("LEFT JOIN {course} coursetype_filter ON base.id = coursetype_filter.id"),
                        'dataalias' => 'coursetype_filter',
                        'datafield' => 'coursetype')
            )
        );
        $filteroptions[] = new rb_filter_option(
            'course',
            'language',
            get_string('courselanguage', 'shezar_reportbuilder'),
            'select',
            array(
                'selectfunc' => 'course_languages',
                'attributes' => rb_filter_option::select_width_limiter(),
            )
        );
        $filteroptions[] = new rb_filter_option(
            'course',
            'id',
            get_string('coursemultiitem', 'shezar_reportbuilder'),
            'course_multi'
        );
        return true;
    }

    /**
     * Adds the program table to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     'program id' field
     * @param string $field Name of table containing program id field to join on
     * @return boolean True
     */
    protected function add_program_table_to_joinlist(&$joinlist, $join, $field) {

        $joinlist[] = new rb_join(
            'program',
            'LEFT',
            '{prog}',
            "program.id = $join.$field",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );
    }


    /**
     * Adds some common program info to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $join Name of the join that provides the 'program' table
     * @param string $langfile Source for translation, shezar_program or shezar_certification
     *
     * @return True
     */
    protected function add_program_fields_to_columns(&$columnoptions, $join = 'program', $langfile = 'shezar_program') {
        global $DB;

        $columnoptions[] = new rb_column_option(
            'prog',
            'fullname',
            get_string('programname', $langfile),
            "$join.fullname",
            array('joins' => $join,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'shortname',
            get_string('programshortname', $langfile),
            "$join.shortname",
            array('joins' => $join,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'idnumber',
            get_string('programidnumber', $langfile),
            "$join.idnumber",
            array('joins' => $join,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'id',
            get_string('programid', $langfile),
            "$join.id",
            array('joins' => $join)
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'summary',
            get_string('programsummary', $langfile),
            "$join.summary",
            array(
                'joins' => $join,
                'displayfunc' => 'tinymce_textarea',
                'extrafields' => array(
                    'filearea' => '\'summary\'',
                    'component' => '\'shezar_program\'',
                    'context' => '\'context_program\'',
                    'recordid' => "$join.id",
                    'fileid' => 0
                ),
                'dbdatatype' => 'text',
                'outputformat' => 'text'
            )
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'availablefrom',
            get_string('availablefrom', $langfile),
            "$join.availablefrom",
            array(
                'joins' => $join,
                'displayfunc' => 'nice_date',
                'dbdatatype' => 'timestamp'
            )
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'availableuntil',
            get_string('availableuntil', $langfile),
            "$join.availableuntil",
            array(
                'joins' => $join,
                'displayfunc' => 'nice_date',
                'dbdatatype' => 'timestamp'
            )
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'proglinkicon',
            get_string('prognamelinkedicon', $langfile),
            "$join.fullname",
            array(
                'joins' => $join,
                'displayfunc' => 'link_program_icon',
                'defaultheading' => get_string('programname', $langfile),
                'extrafields' => array(
                    'program_id' => "$join.id",
                    'program_icon' => "$join.icon",
                    'program_visible' => "$join.visible",
                    'program_audiencevisible' => "$join.audiencevisible",
                )
            )
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'progexpandlink',
            get_string('programexpandlink', $langfile),
            "$join.fullname",
            array(
                'joins' => $join,
                'displayfunc' => 'program_expand',
                'defaultheading' => get_string('programname', $langfile),
                'extrafields' => array(
                    'prog_id' => "$join.id",
                    'prog_visible' => "$join.visible",
                    'prog_audiencevisible' => "$join.audiencevisible",
                    'prog_certifid' => "$join.certifid")
            )
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'visible',
            get_string('programvisible', $langfile),
            "$join.visible",
            array(
                'joins' => $join,
                'displayfunc' => 'yes_no'
            )
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'audvis',
            get_string('audiencevisibility', 'shezar_reportbuilder'),
            "$join.audiencevisible",
            array(
                'joins' => $join,
                'displayfunc' => 'audience_visibility'
            )
        );
        return true;
    }

    /**
     * Adds some common program filters to the $filteroptions array
     *
     * @param array &$filteroptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $langfile Source for translation, shezar_program or shezar_certification
     * @return True
     */
    protected function add_program_fields_to_filters(&$filteroptions, $langfile = 'shezar_program') {
        $filteroptions[] = new rb_filter_option(
            'prog',
            'fullname',
            get_string('programname', $langfile),
            'text'
        );
        $filteroptions[] = new rb_filter_option(
            'prog',
            'shortname',
            get_string('programshortname', $langfile),
            'text'
        );
        $filteroptions[] = new rb_filter_option(
            'prog',
            'idnumber',
            get_string('programidnumber', $langfile),
            'text'
        );
        $filteroptions[] = new rb_filter_option(
            'prog',
            'summary',
            get_string('programsummary', $langfile),
            'textarea'
        );
        $filteroptions[] = new rb_filter_option(
            'prog',
            'availablefrom',
            get_string('availablefrom', $langfile),
            'date'
        );
        $filteroptions[] = new rb_filter_option(
            'prog',
            'availableuntil',
            get_string('availableuntil', $langfile),
            'date'
        );
        return true;
    }

    /**
     * Adds the certification table to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     'certif id' field
     * @param string $field Name of table containing program id field to join on
     */
    protected function add_certification_table_to_joinlist(&$joinlist, $join, $field) {

        $joinlist[] = new rb_join(
            'certif',
            'inner',
            '{certif}',
            "certif.id = $join.$field",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );
    }

    /**
     * Adds some common certification info to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $join Name of the join that provides the 'program' table
     * @param string $langfile Source for translation, shezar_program or shezar_certification
     *
     * @return Boolean
     */
    protected function add_certification_fields_to_columns(&$columnoptions, $join = 'certif', $langfile = 'shezar_certification') {
        $columnoptions[] = new rb_column_option(
            'certif',
            'recertifydatetype',
            get_string('recertdatetype', 'shezar_certification'),
            "$join.recertifydatetype",
            array(
                'joins' => $join,
                'displayfunc' => 'recertifydatetype',
            )
        );

        $columnoptions[] = new rb_column_option(
            'certif',
            'activeperiod',
            get_string('activeperiod', 'shezar_certification'),
            "$join.activeperiod",
            array('joins' => $join,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );

        $columnoptions[] = new rb_column_option(
            'certif',
            'windowperiod',
            get_string('windowperiod', 'shezar_certification'),
            "$join.windowperiod",
            array('joins' => $join,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );

        return true;
    }

    public function rb_display_recertifydatetype($recertifydatetype, $row) {
        switch ($recertifydatetype) {
            case CERTIFRECERT_COMPLETION:
                return get_string('editdetailsrccmpl', 'shezar_certification');
            case CERTIFRECERT_EXPIRY:
                return get_string('editdetailsrcexp', 'shezar_certification');
            case CERTIFRECERT_FIXED:
                return get_string('editdetailsrcfixed', 'shezar_certification');
        }
        return "Error - Recertification method not found";
    }

    /**
     * Adds some common certification filters to the $filteroptions array
     *
     * @param array &$filteroptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $langfile Source for translation, shezar_program or shezar_certification
     * @return boolean
     */
    protected function add_certification_fields_to_filters(&$filteroptions, $langfile = 'shezar_certification') {

        $filteroptions[] = new rb_filter_option(
            'certif',
            'recertifydatetype',
            get_string('recertdatetype', 'shezar_certification'),
            'select',
            array(
                'selectfunc' => 'recertifydatetype',
            )
        );

        $filteroptions[] = new rb_filter_option(
            'certif',
            'activeperiod',
            get_string('activeperiod', 'shezar_certification'),
            'text'
        );

        $filteroptions[] = new rb_filter_option(
            'certif',
            'windowperiod',
            get_string('windowperiod', 'shezar_certification'),
            'text'
        );

        return true;
    }

    public function rb_filter_recertifydatetype() {
        return array(
            CERTIFRECERT_COMPLETION => get_string('editdetailsrccmpl', 'shezar_certification'),
            CERTIFRECERT_EXPIRY => get_string('editdetailsrcexp', 'shezar_certification'),
            CERTIFRECERT_FIXED => get_string('editdetailsrcfixed', 'shezar_certification')
        );
    }

    /**
     * Adds the course_category table to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include course_category
     * @param string $join Name of the join that provides the 'course' table
     * @param string $field Name of category id field to join on
     * @return boolean True
     */
    protected function add_course_category_table_to_joinlist(&$joinlist,
        $join, $field) {

        $joinlist[] = new rb_join(
            'course_category',
            'LEFT',
            '{course_categories}',
            "course_category.id = $join.$field",
            REPORT_BUILDER_RELATION_MANY_TO_ONE,
            $join
        );

        return true;
    }


    /**
     * Adds some common course category info to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $catjoin Name of the join that provides the
     *                        'course_categories' table
     * @param string $coursejoin Name of the join that provides the
     *                           'course' table
     * @return True
     */
    protected function add_course_category_fields_to_columns(&$columnoptions,
        $catjoin='course_category', $coursejoin='course', $column='coursecount') {
        $columnoptions[] = new rb_column_option(
            'course_category',
            'name',
            get_string('coursecategory', 'shezar_reportbuilder'),
            "$catjoin.name",
            array('joins' => $catjoin,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'course_category',
            'namelink',
            get_string('coursecategorylinked', 'shezar_reportbuilder'),
            "$catjoin.name",
            array(
                'joins' => $catjoin,
                'displayfunc' => 'link_course_category',
                'defaultheading' => get_string('category', 'shezar_reportbuilder'),
                'extrafields' => array('cat_id' => "$catjoin.id",
                                        'cat_visible' => "$catjoin.visible",
                                        $column => "{$catjoin}.{$column}")
            )
        );
        $columnoptions[] = new rb_column_option(
            'course_category',
            'idnumber',
            get_string('coursecategoryidnumber', 'shezar_reportbuilder'),
            "$catjoin.idnumber",
            array(
                'joins' => $catjoin,
                'dbdatatype' => 'char',
                'outputformat' => 'text'
            )
        );
        $columnoptions[] = new rb_column_option(
            'course_category',
            'id',
            get_string('coursecategoryid', 'shezar_reportbuilder'),
            "$coursejoin.category",
            array('joins' => $coursejoin)
        );
        return true;
    }


    /**
     * Adds some common course category filters to the $filteroptions array
     *
     * @param array &$columnoptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True
     */
    protected function add_course_category_fields_to_filters(&$filteroptions) {
        $filteroptions[] = new rb_filter_option(
            'course_category',
            'id',
            get_string('coursecategory', 'shezar_reportbuilder'),
            'select',
            array(
                'selectfunc' => 'course_categories_list',
                'attributes' => rb_filter_option::select_width_limiter(),
            )
        );
        $filteroptions[] = new rb_filter_option(
            'course_category',
            'path',
            get_string('coursecategorymultichoice', 'shezar_reportbuilder'),
            'category',
            array(),
            'course_category.path',
            'course_category'
        );
        return true;
    }

    /**
     * Adds position assignment tables to the joinlist.
     *
     * @deprecated since 9.0 - use $this->add_job_assignment_tables_to_joinlist() instead.
     * @param array  $joinlist
     * @param string $join
     * @param string $field
     * @return boolean
     */
    protected function add_position_tables_to_joinlist(&$joinlist, $join, $field) {
        debugging('The rb_base_source function add_position_tables_to_joinlist() has been replaced and is now deprecated.
                   Please use the add_job_assignment_tables_to_joinlist() instead', DEBUG_DEVELOPER);
        $this->add_job_assignment_tables_to_joinlist($joinlist, $join, $field);

        return false;
    }

    /**
     * Adds position assignment columns to the columnoptions list.
     *
     * @deprecated since 9.0 - use $this->add_job_assignment_fields_to_columns() instead.
     * @param array  $columnoptions
     * @param string $posassign
     * @param string $org
     * @param string $pos
     * @return boolean
     */
    protected function add_position_fields_to_columns(&$columnoptions, $posassign='', $org='', $pos='') {
        debugging('The rb_base_source function add_position_fields_to_columns() has been replaced and is now deprecated.
                   Please use the add_job_assignment_fields_to_columns() instead', DEBUG_DEVELOPER);
        $this->add_job_assignment_fields_to_columns($columnoptions);

        return false;
    }

    /**
     * Adds position assignment filters to the filteroptions list.
     *
     * @deprecated since 9.0 - use $this->add_job_assignment_fields_to_filteroptions() instead.
     * @param array $filteroptions
     * @return boolean
     */
    protected function add_position_fields_to_filters(&$filteroptions) {
        debugging('The rb_base_source function add_position_fields_to_filters() has been replaced and is now deprecated.
                   Please use the add_job_assignment_fields_to_filters() instead', DEBUG_DEVELOPER);
        $this->add_job_assignment_fields_to_filters($filteroptions);

        return false;
    }

    /**
     * Adds the job_assignment, pos and org tables to the $joinlist array. All job assignments belonging to the user are returned.
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the 'user' table
     * @param string $field Name of user id field to join on
     * @return boolean True
     */
    protected function add_job_assignment_tables_to_joinlist(&$joinlist, $join, $field) {
        global $DB;

        // All job fields listed by sortorder.
        $jobfieldlistsubsql = "
            (SELECT u.id AS jfid,
            " . $DB->sql_group_concat('COALESCE(uja.fullname, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS titlenamelist,
            " . $DB->sql_group_concat('COALESCE(uja.startdate, \'0\')', $this->uniquedelimiter, 'uja.sortorder') . " AS jobstartdatelist,
            " . $DB->sql_group_concat('COALESCE(uja.enddate, \'0\')', $this->uniquedelimiter, 'uja.sortorder') . " AS jobenddatelist
               FROM {user} u
          LEFT JOIN {job_assignment} uja
                 ON uja.userid = u.id
           GROUP BY u.id)";

        $joinlist[] = new rb_join(
            'alljobfields',
            'LEFT',
            $jobfieldlistsubsql,
            "alljobfields.jfid = {$join}.{$field}",
            REPORT_BUILDER_RELATION_ONE_TO_MANY,
            $join
        );

        // All manager fields listed by job sortorder.
        $usednamefields = shezar_get_all_user_name_fields_join('manager', null, true);
        $manlistsubsql = "
            (SELECT u.id AS manlistid,
            " . $DB->sql_group_concat($DB->sql_concat_join("' '", $usednamefields), $this->uniquedelimiter, 'uja.sortorder') . " AS manfullnamelist,
            " . $DB->sql_group_concat('COALESCE(manager.firstname, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS manfirstnamelist,
            " . $DB->sql_group_concat('COALESCE(manager.lastname, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS manlastnamelist,
            " . $DB->sql_group_concat('COALESCE(manager.idnumber, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS manidnumberlist,
            " . $DB->sql_group_concat('COALESCE(manager.id, \'0\')', $this->uniquedelimiter, 'uja.sortorder') . " AS manidlist,
            " . $DB->sql_group_concat('COALESCE(CASE WHEN manager.maildisplay <> 1 THEN \'!private!\' ELSE manager.email END, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS manemailobslist,
            " . $DB->sql_group_concat('COALESCE(manager.email, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS manemailunobslist

                FROM {user} u
           LEFT JOIN {job_assignment} uja
                  ON uja.userid = u.id
           LEFT JOIN {job_assignment} mja
                  ON mja.id = uja.managerjaid
           LEFT JOIN {user} manager
                  ON mja.userid = manager.id
            GROUP BY u.id)";

        $joinlist[] = new rb_join(
            'manallfields',
            'LEFT',
            $manlistsubsql,
            "manallfields.manlistid = {$join}.{$field}",
            REPORT_BUILDER_RELATION_ONE_TO_MANY,
            $join
        );

        // All position fields listed by job sortorder.
        $poslistsubsql = "
            (SELECT u.id AS poslistid,
            " . $DB->sql_group_concat('COALESCE(position.id, \'0\')', $this->uniquedelimiter, 'uja.sortorder') . " AS posidlist,
            " . $DB->sql_group_concat('COALESCE(position.idnumber, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS posidnumberlist,
            " . $DB->sql_group_concat('COALESCE(position.fullname, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS posnamelist,
            " . $DB->sql_group_concat('COALESCE(ptype.fullname, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS ptypenamelist,
            " . $DB->sql_group_concat('COALESCE(pframe.id, \'0\')', $this->uniquedelimiter, 'uja.sortorder') . " AS pframeidlist,
            " . $DB->sql_group_concat('COALESCE(pframe.idnumber, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS pframeidnumberlist,
            " . $DB->sql_group_concat('COALESCE(pframe.fullname, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS pframenamelist
                FROM {user} u
           LEFT JOIN {job_assignment} uja
                  ON uja.userid = u.id
           LEFT JOIN {pos} position
                  ON uja.positionid = position.id
           LEFT JOIN {pos_type} ptype
                  ON position.typeid = ptype.id
           LEFT JOIN {pos_framework} pframe
                  ON position.frameworkid = pframe.id
            GROUP BY u.id)";

        $joinlist[] = new rb_join(
            'posallfields',
            'LEFT',
            $poslistsubsql,
            "posallfields.poslistid = {$join}.{$field}",
            REPORT_BUILDER_RELATION_ONE_TO_MANY,
            $join
        );

        // List of all assigned organisation names.
        $orglistsubsql = "
            (SELECT u.id AS orglistid,
            " . $DB->sql_group_concat('COALESCE(organisation.id, \'0\')', $this->uniquedelimiter, 'uja.sortorder') . " AS orgidlist,
            " . $DB->sql_group_concat('COALESCE(organisation.idnumber, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS orgidnumberlist,
            " . $DB->sql_group_concat('COALESCE(organisation.fullname, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS orgnamelist,
            " . $DB->sql_group_concat('COALESCE(otype.fullname, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS otypenamelist,
            " . $DB->sql_group_concat('COALESCE(oframe.id, \'0\')', $this->uniquedelimiter, 'uja.sortorder') . " AS oframeidlist,
            " . $DB->sql_group_concat('COALESCE(oframe.idnumber, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS oframeidnumberlist,
            " . $DB->sql_group_concat('COALESCE(oframe.fullname, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS oframenamelist
                FROM {user} u
           LEFT JOIN {job_assignment} uja
                  ON uja.userid = u.id
           LEFT JOIN {org} organisation
                  ON uja.organisationid = organisation.id
           LEFT JOIN {org_type} otype
                  ON organisation.typeid = otype.id
           LEFT JOIN {org_framework} oframe
                  ON organisation.frameworkid = oframe.id
            GROUP BY u.id)";

        $joinlist[] = new rb_join(
            'orgallfields',
            'LEFT',
            $orglistsubsql,
            "orgallfields.orglistid = {$join}.{$field}",
            REPORT_BUILDER_RELATION_ONE_TO_MANY,
            $join
        );

        // List of all assigned appraiser names.
        $usednamefields = shezar_get_all_user_name_fields_join('appraiser', null, true);
        $applistsubsql = "
            (SELECT u.id AS applistid,
            " . $DB->sql_group_concat($DB->sql_concat_join("' '", $usednamefields), $this->uniquedelimiter, 'uja.sortorder') . " AS appfullnamelist
                FROM {user} u
           LEFT JOIN {job_assignment} uja
                  ON uja.userid = u.id
           LEFT JOIN {user} appraiser
                  ON uja.appraiserid = appraiser.id
            GROUP BY u.id)";

        $joinlist[] = new rb_join(
            'appallfields',
            'LEFT',
            $applistsubsql,
            "appallfields.applistid = {$join}.{$field}",
            REPORT_BUILDER_RELATION_ONE_TO_MANY,
            $join
        );

        // Set up the position and organisation custom field joins.
        $posfields = $DB->get_records('pos_type_info_field', array('hidden' => '0'));
        $this->add_job_custom_field_joins('pos', $posfields, $join, $field, $joinlist);

        $orgfields = $DB->get_records('org_type_info_field', array('hidden' => '0'));
        $this->add_job_custom_field_joins('org', $orgfields, $join, $field, $joinlist);

        return true;
    }

    /**
     * Adds some common user manager info to the $columnoptions array,
     * assumes that the joins from add_job_assignment_tables_to_joinlist
     * have been added to the source.
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @return True
     */
    protected function add_job_assignment_fields_to_columns(&$columnoptions) {
        global $CFG, $DB;

        // Job assignment field columns.
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'alltitlenames',
            get_string('usersjobtitlenameall', 'shezar_reportbuilder'),
            "alljobfields.titlenamelist",
            array(
                'joins' => 'alljobfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );

        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allstartdates',
            get_string('usersjobstartdateall', 'shezar_reportbuilder'),
            "alljobfields.jobstartdatelist",
            array(
                'joins' => 'alljobfields',
                'displayfunc' => 'orderedlist_to_newline_date',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );

        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allenddates',
            get_string('usersjobenddateall', 'shezar_reportbuilder'),
            "alljobfields.jobenddatelist",
            array(
                'joins' => 'alljobfields',
                'displayfunc' => 'orderedlist_to_newline_date',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );

        // Position field columns.
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allpositionnames',
            get_string('usersposnameall', 'shezar_reportbuilder'),
            "posallfields.posnamelist",
            array(
                'joins' => 'posallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allpositionids',
            get_string('usersposidall', 'shezar_reportbuilder'),
            "posallfields.posidlist",
            array(
                'joins' => 'posallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allpositionidnumbers',
            get_string('usersposidnumberall', 'shezar_reportbuilder'),
            "posallfields.posidnumberlist",
            array(
                'joins' => 'posallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allpositiontypes',
            get_string('userspostypeall', 'shezar_reportbuilder'),
            "posallfields.ptypenamelist",
            array(
                'joins' => 'posallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allposframenames',
            get_string('usersposframenameall', 'shezar_reportbuilder'),
            "posallfields.pframenamelist",
            array(
                'joins' => 'posallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allposframeids',
            get_string('usersposframeidall', 'shezar_reportbuilder'),
            "posallfields.pframeidlist",
            array(
                'joins' => 'posallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allposframeidnumbers',
            get_string('usersposframeidnumberall', 'shezar_reportbuilder'),
            "posallfields.pframeidnumberlist",
            array(
                'joins' => 'posallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );

        // Organisation field columns.
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allorganisationnames',
            get_string('usersorgnameall', 'shezar_reportbuilder'),
            "orgallfields.orgnamelist",
            array(
                'joins' => 'orgallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allorganisationids',
            get_string('usersorgidall', 'shezar_reportbuilder'),
            "orgallfields.orgidlist",
            array(
                'joins' => 'orgallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allorganisationidnumbers',
            get_string('usersorgidnumberall', 'shezar_reportbuilder'),
            "orgallfields.orgidnumberlist",
            array(
                'joins' => 'orgallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allorganisationtypes',
            get_string('usersorgtypeall', 'shezar_reportbuilder'),
            "orgallfields.otypenamelist",
            array(
                'joins' => 'orgallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allorgframenames',
            get_string('usersorgframenameall', 'shezar_reportbuilder'),
            "orgallfields.oframenamelist",
            array(
                'joins' => 'orgallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allorgframeids',
            get_string('usersorgframeidall', 'shezar_reportbuilder'),
            "orgallfields.oframeidlist",
            array(
                'joins' => 'orgallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allorgframeidnumbers',
            get_string('usersorgframeidnumberall', 'shezar_reportbuilder'),
            "orgallfields.oframeidnumberlist",
            array(
                'joins' => 'orgallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );

        // Manager field columns.
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allmanagernames',
            get_string('usersmanagernameall', 'shezar_reportbuilder'),
            "manallfields.manfullnamelist",
            array(
                'joins' => 'manallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allmanagerfirstnames',
            get_string('usersmanagerfirstnameall', 'shezar_reportbuilder'),
            "manallfields.manfirstnamelist",
            array(
                'joins' => 'manallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allmanagerlastnames',
            get_string('usersmanagerlastnameall', 'shezar_reportbuilder'),
            "manallfields.manlastnamelist",
            array(
                'joins' => 'manallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allmanagerids',
            get_string('usersmanageridall', 'shezar_reportbuilder'),
            "manallfields.manidlist",
            array(
                'joins' => 'manallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allmanageridnumbers',
            get_string('usersmanageridnumberall', 'shezar_reportbuilder'),
            "manallfields.manidnumberlist",
            array(
                'joins' => 'manallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );

        // Managers unobscured emails.
        $canview = !empty($CFG->showuseridentity) && in_array('email', explode(',', $CFG->showuseridentity));
        $canview |= has_capability('moodle/site:config', context_system::instance());
        if ($canview) {
            $columnoptions[] = new rb_column_option(
                'job_assignment',
                'allmanagerunobsemails',
                get_string('usersmanagerunobsemailall', 'shezar_reportbuilder'),
                "manallfields.manemailunobslist",
                array(
                    'joins' => 'manallfields',
                    'displayfunc' => 'orderedlist_to_newline_email',
                    'dbdatatype' => 'char',
                    'outputformat' => 'text',
                    'nosort' => true,
                    'style' => array('white-space' => 'pre'),
                    // Users must have viewuseridentity.
                    'capability' => 'moodle/site:viewuseridentity',
                )
            );
        }
        // Managers obscured emails.
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allmanagerobsemails',
            get_string('usersmanagerobsemailall', 'shezar_reportbuilder'),
            "manallfields.manemailobslist",
            array(
                'joins' => 'manallfields',
                'displayfunc' => 'orderedlist_to_newline_email',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );

        // Appraiser field columns.
        $columnoptions[] = new rb_column_option(
            'job_assignment',
            'allappraisernames',
            get_string('usersappraisernameall', 'shezar_reportbuilder'),
            "appallfields.appfullnamelist",
            array(
                'joins' => 'appallfields',
                'displayfunc' => 'orderedlist_to_newline',
                'dbdatatype' => 'char',
                'outputformat' => 'text',
                'nosort' => true,
                'style' => array('white-space' => 'pre')
            )
        );

        // Set up the position and organisation custom field columns.
        $posfields = $DB->get_records('pos_type_info_field', array('hidden' => '0'));
        $this->add_job_custom_field_columns('pos', $posfields, $columnoptions);

        $orgfields = $DB->get_records('org_type_info_field', array('hidden' => '0'));
        $this->add_job_custom_field_columns('org', $orgfields, $columnoptions);

        return true;
    }

    /**
     * Adds some common user position filters to the $filteroptions array
     *
     * @param array &$columnoptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True
     */
    protected function add_job_assignment_fields_to_filters(&$filteroptions, $users='auser') {
        global $DB;

        // Job assignment field filters.
        $filteroptions[] = new rb_filter_option(
            'job_assignment',                                           // type
            'alltitlenames',                                            // value
            get_string('jobassign_jobtitle', 'shezar_reportbuilder'),   // label
            'text'                                                      // filtertype
        );

        $filteroptions[] = new rb_filter_option(
            'job_assignment',
            'allstartdates',
            get_string('jobassign_jobstart', 'shezar_reportbuilder'),
            'grpconcat_date',
            array(
                'prefix' => 'job',
                'datefield' => 'startdate',
            ),
            'startdate',
            $users
        );

        $filteroptions[] = new rb_filter_option(
            'job_assignment',
            'allenddates',
            get_string('jobassign_jobend', 'shezar_reportbuilder'),
            'grpconcat_date',
            array(
                'prefix' => 'job',
                'datefield' => 'enddate',
            ),
            'enddate',
            $users
        );

        // Position field filters.
        $filteroptions[] = new rb_filter_option(
            'job_assignment',
            'allpositions',
            get_string('usersposall', 'shezar_reportbuilder'),
            'grpconcat_jobassignment',
            array(
                'jobfield' => 'positionid',                                 // Jobfield, map to the column in the job_assignments table.
                'jobjoin' => 'pos',                                         // The table that the job join information can be found in.
            ),
            'id',                                                           // $field
            $users                                                          // $joins string | array
        );
        $filteroptions[] = new rb_filter_option(
            'job_assignment',                                               // type
            'allpositionidnumbers',                                         // value
            get_string('usersposidnumberall', 'shezar_reportbuilder'),      // label
            'text'                                                          // filtertype
        );
        $filteroptions[] = new rb_filter_option(
            'job_assignment',                                               // type
            'allpositiontypes',                                             // value
            get_string('userspostypeall', 'shezar_reportbuilder'),          // label
            'text'                                                          // filtertype
        );
        $filteroptions[] = new rb_filter_option(
            'job_assignment',                                               // type
            'allposframeids',                                               // value
            get_string('usersposframeidall', 'shezar_reportbuilder'),       // label
            'text'                                                          // filtertype
        );
        $filteroptions[] = new rb_filter_option(
            'job_assignment',                                               // type
            'allposframenames',                                             // value
            get_string('usersposframenameall', 'shezar_reportbuilder'),     // label
            'text'                                                          // filtertype
        );
        $filteroptions[] = new rb_filter_option(
            'job_assignment',                                               // type
            'allposframeidnumbers',                                         // value
            get_string('usersposframeidnumberall', 'shezar_reportbuilder'), // label
            'text'                                                          // filtertype
        );

        // Organisation field filters.
        $filteroptions[] = new rb_filter_option(
            'job_assignment',
            'allorganisations',
            get_string('usersorgall', 'shezar_reportbuilder'),
            'grpconcat_jobassignment',
            array(
                'jobfield' => 'organisationid',                             // Jobfield, map to the column in the job_assignments table.
                'jobjoin' => 'org',                                         // The table that the job join information can be found in.
            ),
            'id',                                                           // $field
            $users                                                          // $joins string | array
        );
        $filteroptions[] = new rb_filter_option(
            'job_assignment',                                               // type
            'allorganisationidnumbers',                                     // value
            get_string('usersorgidnumberall', 'shezar_reportbuilder'),      // label
            'text'                                                          // filtertype
        );
        $filteroptions[] = new rb_filter_option(
            'job_assignment',                                               // type
            'allorganisationtypes',                                         // value
            get_string('usersorgtypeall', 'shezar_reportbuilder'),          // label
            'text'                                                          // filtertype
        );
        $filteroptions[] = new rb_filter_option(
            'job_assignment',                                               // type
            'allorgframeids',                                               // value
            get_string('usersorgframeidall', 'shezar_reportbuilder'),       // label
            'text'                                                          // filtertype
        );
        $filteroptions[] = new rb_filter_option(
            'job_assignment',                                               // type
            'allorgframenames',                                             // value
            get_string('usersorgframenameall', 'shezar_reportbuilder'),     // label
            'text'                                                          // filtertype
        );
        $filteroptions[] = new rb_filter_option(
            'job_assignment',                                               // type
            'allorgframeidnumbers',                                         // value
            get_string('usersorgframeidnumberall', 'shezar_reportbuilder'), // label
            'text'                                                          // filtertype
        );

        // Manager field filters.
        $filteroptions[] = new rb_filter_option(
            'job_assignment',
            'allmanagers',
            get_string('usersmanagerall', 'shezar_reportbuilder'),
            'grpconcat_jobassignment',
            array(
                'jobfield' => 'managerjaid',                                // Jobfield, map to the column in the job_assignments table.
                'jobjoin' => 'user',                                        // The table that the job join information can be found in.
                'extfield' => 'userid',                                     // Extfield, this overrides the jobfield as the select after joining.
                'extjoin' => 'job_assignment',                              // Extjoin, whether an additional join is required.
            ),
            'id',                                                           // $field
            $users                                                          // $joins string | array
        );
        $filteroptions[] = new rb_filter_option(
            'job_assignment',                                               // type
            'allmanageridnumbers',                                          // value
            get_string('usersmanageridnumberall', 'shezar_reportbuilder'),  // label
            'text'                                                          // filtertype
        );
        $canview = !empty($CFG->showuseridentity) && in_array('email', explode(',', $CFG->showuseridentity));
        $canview |= has_capability('moodle/site:config', context_system::instance());
        if ($canview) {
            $filteroptions[] = new rb_filter_option(
                'job_assignment',                                                   // type
                'allmanagerunobsemails',                                            // value
                get_string('usersmanagerunobsemailall', 'shezar_reportbuilder'),    // label
                'text'                                                              // filtertype
            );
        }
        $filteroptions[] = new rb_filter_option(
            'job_assignment',                                               // type
            'allmanagerobsemails',                                          // value
            get_string('usersmanagerobsemailall', 'shezar_reportbuilder'),  // label
            'text'                                                          // filtertype
        );

        // Appraiser field filters.
        $filteroptions[] = new rb_filter_option(
            'job_assignment',
            'allappraisers',
            get_string('jobassign_appraiser', 'shezar_reportbuilder'),
            'grpconcat_jobassignment',
            array(
                'jobfield' => 'appraiserid',                                // Jobfield, map to the column in the job_assignments table.
                'jobjoin' => 'user',                                        // The table that the job join information can be found in.
            ),
            'id',                                                           // $field
            $users                                                          // $joins string | array
        );

        // Set up the position and organisation custom field filters.
        $posfields = $DB->get_records('pos_type_info_field', array('hidden' => '0'));
        $this->add_job_custom_field_filters('pos', $posfields, $filteroptions, $users);

        $orgfields = $DB->get_records('org_type_info_field', array('hidden' => '0'));
        $this->add_job_custom_field_filters('org', $orgfields, $filteroptions, $users);

        return $filteroptions;
    }

    /**
     * Converts a list to an array given a list and a separator
     * duplicate values are ignored
     *
     * Example;
     * list_to_array('some-thing-some', '-'); =>
     * array('some' => 'some', 'thing' => 'thing');
     *
     * @param string $list List of items
     * @param string $sep Symbol or string that separates list items
     * @return array $result array of list items
     */
    function list_to_array($list, $sep) {
        $base = explode($sep, $list);
        return array_combine($base, $base);
    }

    /**
     * Generic function for adding custom fields to the reports
     * Intentionally optimized into one function to reduce number of db queries
     *
     * @param string $cf_prefix - prefix for custom field table e.g. everything before '_info_field' or '_info_data'
     * @param string $join - join table in joinlist used as a link to main query
     * @param string $joinfield - joinfield in data table used to link with main table
     * @param array $joinlist - array of joins passed by reference
     * @param array $columnoptions - array of columnoptions, passed by reference
     * @param array $filteroptions - array of filters, passed by reference
     * @param string $suffix - instead of custom_field_{$id}, column name will be custom_field_{$id}{$suffix}. Use short prefixes
     *                         to avoid hiting column size limitations
     * @param bool $nofilter - do not create filter for custom fields. It is useful when customfields are dynamically added by
     *                         column generator
     */
    protected function add_custom_fields_for($cf_prefix, $join, $joinfield,
        array &$joinlist, array &$columnoptions, array &$filteroptions, $suffix = '', $nofilter = false) {

        global $CFG, $DB;

        if (strlen($suffix)) {
            if (!preg_match('/^[a-zA-Z]{1,5}$/', $suffix)) {
                throw new coding_exception('Suffix for add_custom_fields_for must be letters only up to 5 chars.');
            }
        }

        $seek = false;
        foreach ($joinlist as $object) {
            $seek = ($object->name == $join);
            if ($seek) {
                break;
            }
        }

        if ($join == 'base') {
            $seek = 'base';
        }

        if (!$seek) {
            $a = new stdClass();
            $a->join = $join;
            $a->source = get_class($this);
            throw new ReportBuilderException(get_string('error:missingdependencytable', 'shezar_reportbuilder', $a));
        }

        // Build the table names for this sort of custom field data.
        $fieldtable = $cf_prefix.'_info_field';
        $datatable = $cf_prefix.'_info_data';

        // Check if there are any visible custom fields of this type.
        if ($cf_prefix == 'user') {
            // For user fields include them all - below we require moodle/user:update to actually display the column.
            $items = $DB->get_recordset($fieldtable);
        } else {
            $items = $DB->get_recordset($fieldtable, array('hidden' => '0'));
        }

        if (empty($items)) {
            $items->close();
            return false;
        }
        foreach ($items as $record) {
            $id = $record->id;
            $joinname = "{$cf_prefix}_{$id}{$suffix}";
            $value = "custom_field_{$id}{$suffix}";
            $name = isset($record->fullname) ? $record->fullname : $record->name;
            $column_options = array('joins' => $joinname);
            // If profile field isn't available to everyone require a capability to display the column.
            if ($cf_prefix == 'user' && $record->visible === PROFILE_VISIBLE_NONE) {
                $column_options['capability'] = 'moodle/user:viewalldetails';
            }
            $filtertype = 'text'; // default filter type
            $filter_options = array();

            $columnsql = "{$joinname}.data";

            if ($record->datatype == 'multiselect') {
                $filtertype = 'multicheck';

                require_once($CFG->dirroot . '/shezar/customfield/definelib.php');
                require_once($CFG->dirroot . '/shezar/customfield/field/multiselect/field.class.php');
                require_once($CFG->dirroot . '/shezar/customfield/field/multiselect/define.class.php');

                $cfield = new customfield_define_multiselect();
                $cfield->define_load_preprocess($record);
                $filter_options['concat'] = true;
                $filter_options['simplemode'] = true;

                $joinlist[] = new rb_join(
                        $joinname,
                        'LEFT',
                        '(SELECT '.sql_group_concat(sql_cast2char('cfidp.value'), '|', true).' AS data,
                                 cfid.'.$joinfield.' AS joinid, '.sql_cast2char('cfid.data').' AS jsondata
                            FROM {'.$datatable.'} cfid
                            LEFT JOIN {'.$datatable.'_param} cfidp ON (cfidp.dataid = cfid.id)
                           WHERE cfid.fieldid = '.$id.'
                           GROUP BY cfid.'.$joinfield.', '.sql_cast2char('cfid.data').')',
                        "$joinname.joinid = {$join}.id ",
                        REPORT_BUILDER_RELATION_ONE_TO_ONE,
                        $join
                    );

                $columnoptions[] = new rb_column_option(
                        $cf_prefix,
                        $value.'_icon',
                        get_string('multiselectcolumnicon', 'shezar_customfield', $name),
                        "$joinname.data",
                        array('joins' => $joinname,
                              'displayfunc' => 'customfield_multiselect_icon',
                              'extrafields' => array(
                                  "{$cf_prefix}_{$value}_icon_json" => "{$joinname}.jsondata"
                              ),
                              'defaultheading' => $name
                        )
                    );

                $columnoptions[] = new rb_column_option(
                        $cf_prefix,
                        $value.'_text',
                        get_string('multiselectcolumntext', 'shezar_customfield', $name),
                        "$joinname.data",
                        array('joins' => $joinname,
                              'displayfunc' => 'customfield_multiselect_text',
                              'extrafields' => array(
                                  "{$cf_prefix}_{$value}_text_json" => "{$joinname}.jsondata"
                              ),
                              'defaultheading' => $name
                        )
                    );

                $selectchoices = array();
                foreach ($record->multiselectitem as $selectchoice) {
                    $selectchoices[md5($selectchoice['option'])] = format_string($selectchoice['option']);
                }
                $filter_options['selectchoices'] = $selectchoices;
                $filter_options['showcounts'] = array(
                        'joins' => array(
                                "LEFT JOIN (SELECT id, {$joinfield} FROM {{$cf_prefix}_info_data} " .
                                            "WHERE fieldid = {$id}) {$cf_prefix}_idt_{$id} " .
                                       "ON base_{$cf_prefix}_idt_{$id} = {$cf_prefix}_idt_{$id}.{$joinfield}",
                                "LEFT JOIN {{$cf_prefix}_info_data_param} {$cf_prefix}_idpt_{$id} " .
                                       "ON {$cf_prefix}_idt_{$id}.id = {$cf_prefix}_idpt_{$id}.dataid"),
                        'basefields' => array("{$join}.id AS base_{$cf_prefix}_idt_{$id}"),
                        'dependency' => $join,
                        'dataalias' => "{$cf_prefix}_idpt_{$id}",
                        'datafield' => "value");
                if (!$nofilter) {
                    $filteroptions[] = new rb_filter_option(
                        $cf_prefix,
                        $value.'_text',
                        get_string('multiselectcolumntext', 'shezar_customfield', $name),
                        $filtertype,
                        $filter_options
                    );
                }

                $iconselectchoices = array();
                foreach ($record->multiselectitem as $selectchoice) {
                    $iconselectchoices[md5($selectchoice['option'])] =
                            customfield_multiselect::get_item_string(format_string($selectchoice['option']), $selectchoice['icon'], 'list-icon');
                }
                $filter_options['selectchoices'] = $iconselectchoices;
                $filter_options['showcounts'] = array(
                        'joins' => array(
                                "LEFT JOIN (SELECT id, {$joinfield} FROM {{$cf_prefix}_info_data} " .
                                            "WHERE fieldid = {$id}) {$cf_prefix}_idi_{$id} " .
                                       "ON base_{$cf_prefix}_idi_{$id} = {$cf_prefix}_idi_{$id}.{$joinfield}",
                                "LEFT JOIN {{$cf_prefix}_info_data_param} {$cf_prefix}_idpi_{$id} " .
                                       "ON {$cf_prefix}_idi_{$id}.id = {$cf_prefix}_idpi_{$id}.dataid"),
                        'basefields' => array("{$join}.id AS base_{$cf_prefix}_idi_{$id}"),
                        'dependency' => $join,
                        'dataalias' => "{$cf_prefix}_idpi_{$id}",
                        'datafield' => "value");
                if (!$nofilter) {
                    $filteroptions[] = new rb_filter_option(
                        $cf_prefix,
                        $value.'_icon',
                        get_string('multiselectcolumnicon', 'shezar_customfield', $name),
                        $filtertype,
                        $filter_options
                    );
                }
                continue;
            }

            switch ($record->datatype) {
                case 'file':
                    $column_options['displayfunc'] = 'customfield_file';
                    $column_options['extrafields'] = array(
                            "itemid" => "{$joinname}.id"
                    );
                    break;

                case 'textarea':
                    $filtertype = 'textarea';
                    if ($cf_prefix == 'user') {
                        $column_options['displayfunc'] = 'userfield_textarea';
                    } else {
                        $column_options['displayfunc'] = 'customfield_textarea';
                    }
                    $column_options['extrafields'] = array(
                        "itemid" => "{$joinname}.id"
                    );
                    if ($cf_prefix === 'user') {
                        $column_options['extrafields']["{$cf_prefix}_custom_field_{$id}_format"] = "{$joinname}.dataformat";
                    }
                    $column_options['dbdatatype'] = 'text';
                    $column_options['outputformat'] = 'text';
                    break;

                case 'menu':
                    $default = $record->defaultdata;
                    if ($default !== '' and $default !== null) {
                        // Note: there is no safe way to inject the default value into the query, use extra join instead.
                        $fieldjoin = $joinname . '_fielddefault';
                        $joinlist[] = new rb_join(
                            $fieldjoin,
                            'INNER',
                            "{{$fieldtable}}",
                            "{$fieldjoin}.id = {$id}",
                            REPORT_BUILDER_RELATION_MANY_TO_ONE
                        );
                        $columnsql = "COALESCE({$columnsql}, {$fieldjoin}.defaultdata)";
                        $column_options['joins'] = (array)$column_options['joins'];
                        $column_options['joins'][] = $fieldjoin;
                    }
                    $filtertype = 'menuofchoices';
                    $filter_options['selectchoices'] = $this->list_to_array($record->param1,"\n");
                    $filter_options['simplemode'] = true;
                    $column_options['dbdatatype'] = 'text';
                    $column_options['outputformat'] = 'text';
                    break;

                case 'checkbox':
                    $default = $record->defaultdata;
                    $columnsql = "CASE WHEN ( {$columnsql} IS NULL OR {$columnsql} = '' ) THEN {$default} ELSE " . $DB->sql_cast_char2int($columnsql, true) . " END";
                    $filtertype = 'select';
                    $filter_options['selectchoices'] = array(0 => get_string('no'), 1 => get_string('yes'));
                    $filter_options['simplemode'] = true;
                    $column_options['displayfunc'] = 'yes_no';
                    break;

                case 'datetime':
                    $filtertype = 'date';
                    $columnsql = "CASE WHEN {$columnsql} = '' THEN NULL ELSE " . $DB->sql_cast_char2int($columnsql, true) . " END";
                    if ($record->param3) {
                        $column_options['displayfunc'] = 'nice_datetime';
                        $column_options['dbdatatype'] = 'timestamp';
                        $filter_options['includetime'] = true;
                    } else {
                        $column_options['displayfunc'] = 'nice_date';
                        $column_options['dbdatatype'] = 'timestamp';
                    }
                    break;

                case 'date': // Midday in UTC, date without timezone.
                    $filtertype = 'date';
                    $columnsql = "CASE WHEN {$columnsql} = '' THEN NULL ELSE " . $DB->sql_cast_char2int($columnsql, true) . " END";
                    $column_options['displayfunc'] = 'nice_date_no_timezone';
                    $column_options['dbdatatype'] = 'timestamp';
                    break;

                case 'text':
                    $default = $record->defaultdata;
                    if ($default !== '' and $default !== null) {
                        // Note: there is no safe way to inject the default value into the query, use extra join instead.
                        $fieldjoin = $joinname . '_fielddefault';
                        $joinlist[] = new rb_join(
                            $fieldjoin,
                            'INNER',
                            "{{$fieldtable}}",
                            "{$fieldjoin}.id = {$id}",
                            REPORT_BUILDER_RELATION_MANY_TO_ONE
                        );
                        $columnsql = "COALESCE({$columnsql}, {$fieldjoin}.defaultdata)";
                        $column_options['joins'] = (array)$column_options['joins'];
                        $column_options['joins'][] = $fieldjoin;
                    }
                    $column_options['dbdatatype'] = 'text';
                    $column_options['outputformat'] = 'text';
                    break;

                case 'url':
                    $filtertype = 'url';
                    $column_options['dbdatatype'] = 'text';
                    $column_options['outputformat'] = 'text';
                    $column_options['displayfunc'] = 'customfield_url';
                    break;

                case 'location':
                    $column_options['displayfunc'] = 'location';
                    $column_options['outputformat'] = 'text';
                    break;

                default:
                    // Unsupported customfields.
                    continue 2;
            }

            if ($cf_prefix === 'user') {
                $column_options['displayfunc'] = 'user_customfield';
                $column_options['extracontext']['visible'] = $record->visible;
                $column_options['extracontext']['datatype'] = $record->datatype;
            }

            $joinlist[] = new rb_join(
                    $joinname,
                    'LEFT',
                    "{{$datatable}}",
                    "{$joinname}.{$joinfield} = {$join}.id AND {$joinname}.fieldid = {$id}",
                    REPORT_BUILDER_RELATION_ONE_TO_ONE,
                    $join
                );
            $columnoptions[] = new rb_column_option(
                    $cf_prefix,
                    $value,
                    $name,
                    $columnsql,
                    $column_options
                );

            if ($record->datatype == 'file') {
                // No filter options for files yet.
                continue;
            } else {
                if (!$nofilter) {
                    $filteroptions[] = new rb_filter_option(
                        $cf_prefix,
                        $value,
                        $name,
                        $filtertype,
                        $filter_options
                    );
                }
            }
        }

        $items->close();

        return true;

    }

    /**
     * Dynamically add all customfields to columns
     * It uses additional suffix 'all' for column names generation . This means, that if some customfield column was generated using
     * the same suffix it will be shadowed by this method.
     * @param rb_column_option $columnoption should have public string property "type" which value is the type of customfields to show
     * @param bool $hidden should all these columns be hidden
     * @return array
     */
    public function rb_cols_generator_allcustomfields(rb_column_option $columnoption, $hidden) {
        $result = array();
        $columnoptions = array();

        // add_custom_fields_for requires only one join.
        if (!empty($columnoption->joins) && !is_string($columnoption->joins)) {
            throw new coding_exception('allcustomfields column generator requires none or only one join as string');
        }

        $join = empty($columnoption->joins) ? 'base' : $columnoption->joins;

        $this->add_custom_fields_for($columnoption->type, $join, $columnoption->field, $this->joinlist,
                $columnoptions, $this->filteroptions, 'all', true);
        foreach($columnoptions as $option) {
            $result[] = new rb_column(
                    $option->type,
                    $option->value,
                    $option->name,
                    $option->field,
                    (array)$option
            );
        }

        return $result;
    }

    /**
     * Adds user custom fields to the report
     *
     * @param array $joinlist
     * @param array $columnoptions
     * @param array $filteroptions
     * @param string $basetable
     * @return boolean
     */
    protected function add_custom_user_fields(array &$joinlist, array &$columnoptions,
        array &$filteroptions, $basetable = 'auser') {
        return $this->add_custom_fields_for('user',
                                            $basetable,
                                            'userid',
                                            $joinlist,
                                            $columnoptions,
                                            $filteroptions);
    }



    protected function add_custom_evidence_fields(array &$joinlist, array &$columnoptions,
        array &$filteroptions, $basetable = 'dp_plan_evidence') {
        return $this->add_custom_fields_for('dp_plan_evidence',
                                            $basetable,
                                            'evidenceid',
                                            $joinlist,
                                            $columnoptions,
                                            $filteroptions);
    }

    /**
     * Adds course custom fields to the report
     *
     * @param array $joinlist
     * @param array $columnoptions
     * @param array $filteroptions
     * @param string $basetable
     * @return boolean
     */
    protected function add_custom_course_fields(array &$joinlist, array &$columnoptions,
        array &$filteroptions, $basetable = 'course') {
        return $this->add_custom_fields_for('course',
                                            $basetable,
                                            'courseid',
                                            $joinlist,
                                            $columnoptions,
                                            $filteroptions);
    }

    /**
     * Adds course custom fields to the report
     *
     * @param array $joinlist
     * @param array $columnoptions
     * @param array $filteroptions
     * @param string $basetable
     * @return boolean
     */
    protected function add_custom_prog_fields(array &$joinlist, array &$columnoptions,
        array &$filteroptions, $basetable = 'prog') {
        return $this->add_custom_fields_for('prog',
                                            $basetable,
                                            'programid',
                                            $joinlist,
                                            $columnoptions,
                                            $filteroptions);
    }

    /**
     * Adds custom organisation fields to the report
     *
     * Note: this wont work for users job assignments since they're all grouped.
     * but this would still be good for other base tables like the organisation source.
     *
     * @param array $joinlist
     * @param array $columnoptions
     * @param array $filteroptions
     * @return boolean
     */
    protected function add_custom_organisation_fields(array &$joinlist, array &$columnoptions,
        array &$filteroptions) {
        return $this->add_custom_fields_for('org_type',
                                            'organisation',
                                            'organisationid',
                                            $joinlist,
                                            $columnoptions,
                                            $filteroptions);
    }

    /**
     * Adds the joins for pos/org custom fields to the $joinlist.
     *
     * @param string $prefix        Whether this is a pos/org
     * @param string $join          The table to take the userid from
     * @param string $joinfield     The field to take the userid from
     * @param array  $joinlist
     */
    private function add_job_custom_field_joins($prefix, $fields, $join, $joinfield, &$joinlist) {
        global $DB;

        // We need a join for each custom field to get them concatenating.
        foreach ($fields as $field) {
            $uniquename = "{$prefix}_custom_{$field->id}";
            $idfield = $prefix == 'pos' ? 'positionid' : 'organisationid';

            switch ($field->datatype) {
                case 'date' :
                case 'datetime' :
                case 'checkbox' :
                case 'text' :
                case 'menu' :
                case 'url' :
                case 'location' :
                case 'file' :
                    break;
                case 'textarea' :
                    // Not yet supported
                    continue(2);
            }

            $customsubsql = "
                (SELECT uja.userid AS customlistid,
                " . $DB->sql_group_concat('COALESCE(otdata.data, \'-\')', $this->uniquedelimiter, 'uja.sortorder') . " AS {$uniquename}
                    FROM {job_assignment} uja
               LEFT JOIN {{$prefix}} item
                      ON uja.{$idfield} = item.id
               LEFT JOIN {{$prefix}_type_info_field} otfield
                      ON item.typeid = otfield.typeid
                     AND otfield.id = {$field->id}
               LEFT JOIN {{$prefix}_type_info_data} otdata
                      ON otdata.fieldid = otfield.id
                     AND otdata.{$idfield} = item.id
                GROUP BY uja.userid)";

            $joinlist[] = new rb_join(
                $uniquename,
                'LEFT',
                $customsubsql,
                "{$uniquename}.customlistid = {$join}.{$joinfield}",
                REPORT_BUILDER_RELATION_ONE_TO_MANY,
                $join
            );
        }

        return true;
    }

    private function add_job_custom_field_columns($prefix, $fields, &$columnoptions) {

        foreach ($fields as $field) {
            $uniquename = "{$prefix}_custom_{$field->id}";

            switch ($field->datatype) {
                case 'datetime' :
                    $displayfunc = $field->param3 ? 'delimitedlist_datetime_in_timezone' : 'delimitedlist_date_in_timezone';
                    break;
                case 'checkbox' :
                    $displayfunc = 'delimitedlist_yes_no';
                    break;
                case 'text' :
                    $displayfunc = 'delimitedlist_to_newline';
                    break;
                case 'menu' :
                    $displayfunc = 'delimitedlist_to_newline';
                    break;
                case 'multiselect' :
                    $displayfunc = 'delimitedlist_multi_to_newline';
                    break;
                case 'url' :
                    $displayfunc = 'delimitedlist_url_to_newline';
                    break;
                case 'location' :
                    $displayfunc = 'delimitedlist_location_to_newline';
                    break;
                case 'file' :
                    $displayfunc = "delimitedlist_{$prefix}files_to_newline";
                    break;
                case 'textarea' :
                    // Text areas severly break the formatting of concatenated columns, so they are unsupported.
                    continue(2);
            }

            // Job assignment field columns.
            $columnoptions[] = new rb_column_option(
                'job_assignment',
                $uniquename,
                s($field->fullname),
                "{$uniquename}.{$uniquename}",
                array(
                    'joins' => $uniquename,
                    'displayfunc' => $displayfunc,
                    'dbdatatype' => 'char',
                    'outputformat' => 'text',
                    'nosort' => true,
                    'style' => array('white-space' => 'pre')
                )
            );
        }
    }

    private function add_job_custom_field_filters($prefix, $fields, &$filteroptions, $userjoin = 'auser') {
        global $CFG;

        foreach ($fields as $field) {
            $uniquename = "{$prefix}_custom_{$field->id}";

            switch ($field->datatype) {
                case 'datetime' :
                    $filteroptions[] = new rb_filter_option(
                        'job_assignment',
                        $uniquename,
                        s($field->fullname),
                        'grpconcat_date',
                        array(
                            'datefield' => $field->shortname,
                            'prefix' => $prefix,
                        ),
                        $field->shortname,
                        $userjoin
                    );
                    break;
                case 'checkbox' :
                    $filteroptions[] = new rb_filter_option(
                        'job_assignment',
                        $uniquename,
                        s($field->fullname),
                        'grpconcat_checkbox',
                        array(
                            'simplemode' => true,
                            'selectchoices' => array(
                                0 => get_string('filtercheckboxallno', 'shezar_reportbuilder'),
                                1 => get_string('filtercheckboxallyes', 'shezar_reportbuilder'),
                                2 => get_string('filtercheckboxanyno', 'shezar_reportbuilder'),
                                3 => get_string('filtercheckboxanyyes', 'shezar_reportbuilder'),
                            ),
                        )
                    );
                    break;
                case 'text' :
                    $filteroptions[] = new rb_filter_option(
                        'job_assignment',
                        $uniquename,
                        s($field->fullname),
                        'text'
                    );
                    break;
                case 'menu' :
                    $filteroptions[] = new rb_filter_option(
                        'job_assignment',
                        $uniquename,
                        s($field->fullname),
                        'grpconcat_menu',
                        array(
                            'selectchoices' => $this->list_to_array($field->param1, "\n"),
                            'simplemode' => true,
                        )
                    );
                    break;
                case 'multiselect' :
                    require_once($CFG->dirroot . '/shezar/customfield/field/multiselect/define.class.php');

                    $cfield = new customfield_define_multiselect();
                    $cfield->define_load_preprocess($field);

                    $selectchoices = array();
                    foreach ($field->multiselectitem as $selectchoice) {
                        $selectchoices[$selectchoice['option']] = format_string($selectchoice['option']);
                    }
                    // TODO - it would be nice to display the icon here as well.
                    $filter_options['selectchoices'] = $selectchoices;
                    $filteroptions[] = new rb_filter_option(
                        'job_assignment',
                        $uniquename,
                        s($field->fullname),
                        'grpconcat_multi',
                        $filter_options
                    );

                    break;
                case 'url' :
                case 'location' :
                    // TODO - not yet supported filter types.
                    break;
                case 'textarea' :
                case 'file' :
                    // Unsupported filter types.
                    continue(2);
            }
        }

        return true;
    }

    /**
     * Adds custom position fields to the report.
     *
     * Note: this wont work for users job assignments since they're all grouped.
     * but this would still be good for other base tables like the organisation source.
     *
     * @param array $joinlist
     * @param array $columnoptions
     * @param array $filteroptions
     * @return boolean
     */
    protected function add_custom_position_fields(array &$joinlist, array &$columnoptions,
        array &$filteroptions) {
        return $this->add_custom_fields_for('pos_type',
                                            'position',
                                            'positionid',
                                            $joinlist,
                                            $columnoptions,
                                            $filteroptions);

    }


    /**
     * Adds custom goal fields to the report
     *
     * @param array $joinlist
     * @param array $columnoptions
     * @param array $filteroptions
     * @return boolean
     */
    protected function add_custom_goal_fields(array &$joinlist, array &$columnoptions,
        array &$filteroptions) {
        return $this->add_custom_fields_for('goal_type',
                                            'goal',
                                            'goalid',
                                            $joinlist,
                                            $columnoptions,
                                            $filteroptions);
    }


    /**
     * Adds custom personal goal fields to the report
     *
     * @param array $joinlist
     * @param array $columnoptions
     * @param array $filteroptions
     * @return boolean
     */
    protected function add_custom_personal_goal_fields(array &$joinlist, array &$columnoptions,
        array &$filteroptions) {
        return $this->add_custom_fields_for('goal_user',
                                            'goal_personal',
                                            'goal_userid',
                                            $joinlist,
                                            $columnoptions,
                                            $filteroptions);
    }


    /**
     * Adds custom competency fields to the report
     *
     * @param array $joinlist
     * @param array $columnoptions
     * @param array $filteroptions
     * @return boolean
     */
    protected function add_custom_competency_fields(array &$joinlist, array &$columnoptions,
        array &$filteroptions) {
        return $this->add_custom_fields_for('comp_type',
                                            'competency',
                                            'competencyid',
                                            $joinlist,
                                            $columnoptions,
                                            $filteroptions);

    }

    /**
     * Adds the tag tables to the $joinlist array
     *
     * @param string $type tag itemtype
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     $type table
     * @param string $field Name of course id field to join on
     * @return boolean True
     */
    protected function add_tag_tables_to_joinlist($type, &$joinlist, $join, $field) {

        global $DB;

        $joinlist[] = new rb_join(
            'tagids',
            'LEFT',
            // subquery as table name
            "(SELECT til.id AS tilid, " .
                sql_group_concat(sql_cast2char('t.id'), '|') .
                " AS idlist FROM {{$type}} til
                LEFT JOIN {tag_instance} ti
                    ON til.id = ti.itemid AND ti.itemtype = '{$type}'
                LEFT JOIN {tag} t
                    ON ti.tagid = t.id AND t.tagtype = 'official'
                GROUP BY til.id)",
            "tagids.tilid = {$join}.{$field}",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );

        $joinlist[] = new rb_join(
            'tagnames',
            'LEFT',
            // subquery as table name
            "(SELECT tnl.id AS tnlid, " .
                sql_group_concat(sql_cast2char('t.name'), ', ') .
                " AS namelist FROM {{$type}} tnl
                LEFT JOIN {tag_instance} ti
                    ON tnl.id = ti.itemid AND ti.itemtype = '{$type}'
                LEFT JOIN {tag} t
                    ON ti.tagid = t.id AND t.tagtype = 'official'
                GROUP BY tnl.id)",
            "tagnames.tnlid = {$join}.{$field}",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );

        // create a join for each official tag
        $tags = $DB->get_records('tag', array('tagtype' => 'official'));
        foreach ($tags as $tag) {
            $tagid = $tag->id;
            $name = "{$type}_tag_$tagid";
            $joinlist[] = new rb_join(
                $name,
                'LEFT',
                '{tag_instance}',
                "($name.itemid = $join.$field AND $name.tagid = $tagid " .
                    "AND $name.itemtype = '{$type}')",
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                $join
            );
        }

        return true;
    }


    /**
     * Adds some common tag info to the $columnoptions array
     *
     * @param string $type tag itemtype
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $tagids name of the join that provides the 'tagids' table.
     * @param string $tagnames name of the join that provides the 'tagnames' table.
     *
     * @return True
     */
    protected function add_tag_fields_to_columns($type, &$columnoptions, $tagids='tagids', $tagnames='tagnames') {
        global $DB;

        $columnoptions[] = new rb_column_option(
            'tags',
            'tagids',
            get_string('tagids', 'shezar_reportbuilder'),
            "$tagids.idlist",
            array('joins' => $tagids, 'selectable' => false)
        );
        $columnoptions[] = new rb_column_option(
            'tags',
            'tagnames',
            get_string('tags', 'shezar_reportbuilder'),
            "$tagnames.namelist",
            array('joins' => $tagnames,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );

        // create a on/off field for every official tag
        $tags = $DB->get_records('tag', array('tagtype' => 'official'));
        foreach ($tags as $tag) {
            $tagid = $tag->id;
            $name = $tag->name;
            $join = "{$type}_tag_$tagid";
            $columnoptions[] = new rb_column_option(
                'tags',
                $join,
                get_string('taggedx', 'shezar_reportbuilder', $name),
                "CASE WHEN $join.id IS NOT NULL THEN 1 ELSE 0 END",
                array(
                    'joins' => $join,
                    'displayfunc' => 'yes_no',
                )
            );
        }
        return true;
    }


    /**
     * Adds some common tag filters to the $filteroptions array
     *
     * @param string $type tag itemtype
     * @param array &$filteroptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True
     */
    protected function add_tag_fields_to_filters($type, &$filteroptions) {
        global $DB;

        // create a yes/no filter for every official tag
        $tags = $DB->get_records('tag', array('tagtype' => 'official'));
        foreach ($tags as $tag) {
            $tagid = $tag->id;
            $name = $tag->name;
            $join = "{$type}_tag_{$tagid}";
            $filteroptions[] = new rb_filter_option(
                'tags',
                $join,
                get_string('taggedx', 'shezar_reportbuilder', $name),
                'select',
                array(
                    'selectchoices' => array(1 => get_string('yes'), 0 => get_string('no')),
                    'simplemode' => true,
                )
            );
        }

        // create a tag list selection filter
        $filteroptions[] = new rb_filter_option(
            'tags',         // type
            'tagids',           // value
            get_string('tags', 'shezar_reportbuilder'), // label
            'multicheck',     // filtertype
            array(            // options
                'selectchoices' => $this->rb_filter_tags_list(),
                'concat' => true, // Multicheck filter needs to know that we are working with concatenated values
                'showcounts' => array(
                        'joins' => array("LEFT JOIN (SELECT ti.itemid, ti.tagid FROM {{$type}} base " .
                                                      "LEFT JOIN {tag_instance} ti ON base.id = ti.itemid " .
                                                            "AND ti.itemtype = '{$type}'" .
                                                      "LEFT JOIN {tag} tag ON ti.tagid = tag.id " .
                                                            "AND tag.tagtype = 'official')\n {$type}_tagids_filter " .
                                                "ON base.id = {$type}_tagids_filter.itemid"),
                        'dataalias' => $type.'_tagids_filter',
                        'datafield' => 'tagid')
            )
        );
        return true;
    }


    /**
     * Adds the cohort user tables to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     'user' table
     * @param string $field Name of user id field to join on
     * @return boolean True
     */
    protected function add_cohort_user_tables_to_joinlist(&$joinlist,
                                                          $join, $field) {

        $joinlist[] = new rb_join(
            'cohortuser',
            'LEFT',
            // subquery as table name
            "(SELECT cm.userid AS userid, " .
                sql_group_concat(sql_cast2char('cm.cohortid'),'|', true) .
                " AS idlist FROM {cohort_members} cm
                GROUP BY cm.userid)",
            "cohortuser.userid = $join.$field",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );

        return true;
    }

    /**
     * Adds the cohort course tables to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     'course' table
     * @param string $field Name of course id field to join on
     * @return boolean True
     */
    protected function add_cohort_course_tables_to_joinlist(&$joinlist,
                                                            $join, $field) {

        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $joinlist[] = new rb_join(
            'cohortenrolledcourse',
            'LEFT',
            // subquery as table name
            "(SELECT courseid AS course, " .
                sql_group_concat(sql_cast2char('customint1'), '|', true) .
                " AS idlist FROM {enrol} e
                WHERE e.enrol = 'cohort'
                GROUP BY courseid)",
            "cohortenrolledcourse.course = $join.$field",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );

        return true;
    }


    /**
     * Adds the cohort program tables to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     table containing the program id
     * @param string $field Name of program id field to join on
     * @return boolean True
     */
    protected function add_cohort_program_tables_to_joinlist(&$joinlist,
                                                             $join, $field) {

        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $joinlist[] = new rb_join(
            'cohortenrolledprogram',
            'LEFT',
            // subquery as table name
            "(SELECT programid AS program, " .
                sql_group_concat(sql_cast2char('assignmenttypeid'), '|', true) .
                " AS idlist FROM {prog_assignment} pa
                WHERE assignmenttype = " . ASSIGNTYPE_COHORT . "
                GROUP BY programid)",
            "cohortenrolledprogram.program = $join.$field",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );

        return true;
    }


    /**
     * Adds some common cohort user info to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $cohortids Name of the join that provides the
     *                          'cohortuser' table.
     *
     * @return True
     */
    protected function add_cohort_user_fields_to_columns(&$columnoptions,
                                                         $cohortids='cohortuser') {

        $columnoptions[] = new rb_column_option(
            'cohort',
            'usercohortids',
            get_string('usercohortids', 'shezar_reportbuilder'),
            "$cohortids.idlist",
            array('joins' => $cohortids, 'selectable' => false)
        );

        return true;
    }


    /**
     * Adds some common cohort course info to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $cohortenrolledids Name of the join that provides the
     *                          'cohortenrolledcourse' table.
     *
     * @return True
     */
    protected function add_cohort_course_fields_to_columns(&$columnoptions, $cohortenrolledids='cohortenrolledcourse') {
        $columnoptions[] = new rb_column_option(
            'cohort',
            'enrolledcoursecohortids',
            get_string('enrolledcoursecohortids', 'shezar_reportbuilder'),
            "$cohortenrolledids.idlist",
            array('joins' => $cohortenrolledids, 'selectable' => false)
        );

        return true;
    }


    /**
     * Adds some common cohort program info to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $cohortenrolledids Name of the join that provides the
     *                          'cohortenrolledprogram' table.
     *
     * @return True
     */
    protected function add_cohort_program_fields_to_columns(&$columnoptions, $cohortenrolledids='cohortenrolledprogram') {
        $columnoptions[] = new rb_column_option(
            'cohort',
            'enrolledprogramcohortids',
            get_string('enrolledprogramcohortids', 'shezar_reportbuilder'),
            "$cohortenrolledids.idlist",
            array('joins' => $cohortenrolledids, 'selectable' => false)
        );

        return true;
    }

    /**
     * Adds some common user cohort filters to the $filteroptions array
     *
     * @param array &$columnoptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True
     */
    protected function add_cohort_user_fields_to_filters(&$filteroptions) {

        $filteroptions[] = new rb_filter_option(
            'cohort',
            'usercohortids',
            get_string('userincohort', 'shezar_reportbuilder'),
            'cohort'
        );
        return true;
    }

    /**
     * Adds some common course cohort filters to the $filteroptions array
     *
     * @param array &$columnoptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True
     */
    protected function add_cohort_course_fields_to_filters(&$filteroptions) {

        $filteroptions[] = new rb_filter_option(
            'cohort',
            'enrolledcoursecohortids',
            get_string('courseenrolledincohort', 'shezar_reportbuilder'),
            'cohort'
        );

        return true;
    }


    /**
     * Adds some common program cohort filters to the $filteroptions array
     *
     * @param array &$columnoptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $langfile Source for translation, shezar_program or shezar_certification
     *
     * @return True
     */
    protected function add_cohort_program_fields_to_filters(&$filteroptions, $langfile) {

        $filteroptions[] = new rb_filter_option(
            'cohort',
            'enrolledprogramcohortids',
            get_string('programenrolledincohort', $langfile),
            'cohort'
        );

        return true;
    }

    /**
     * @return array
     */
    protected function define_columnoptions() {
        return array();
    }

    /**
     * @return array
     */
    protected function define_filteroptions() {
        return array();
    }

    /**
     * @return array
     */
    protected function define_defaultcolumns() {
        return array();
    }

    /**
     * @return array
     */
    protected function define_defaultfilters() {
        return array();
    }

    /**
     * @return array
     */
    protected function define_contentoptions() {
        return array();
    }

    /**
     * @return array
     */
    protected function define_paramoptions() {
        return array();
    }

    /**
     * @return array
     */
    protected function define_requiredcolumns() {
        return array();
    }

    /**
     * Called after parameters have been read, allows the source to configure itself,
     * such as source title, additional tables, column definitions, etc.
     *
     * If post_params fails it needs to set redirect.
     *
     * @param reportbuilder $report
     */
    public function post_params(reportbuilder $report) {
    }

    /**
     * This method is called at the very end of reportbuilder class constructor
     * right before marking it ready.
     *
     * This method allows sources to add extra restrictions by calling
     * the following method on the $report object:
     *  {@link $report->set_post_config_restrictions()}    Extra WHERE clause
     *
     * If post_config fails it needs to set redirect.
     *
     * NOTE: do NOT modify the list of columns here.
     *
     * @param reportbuilder $report
     */
    public function post_config(reportbuilder $report) {
    }

    /**
     * Returns an array of js objects that need to be included with this report.
     *
     * @return array(object)
     */
    public function get_required_jss() {
        return array();
    }

    protected function get_advanced_aggregation_classes($type) {
        global $CFG;

        $classes = array();

        foreach (scandir("{$CFG->dirroot}/shezar/reportbuilder/classes/rb/{$type}") as $filename) {
            if (substr($filename, -4) !== '.php') {
                continue;
            }
            if ($filename === 'base.php') {
                continue;
            }
            $name = str_replace('.php', '', $filename);
            $classname = "\\shezar_reportbuilder\\rb\\{$type}\\$name";
            if (!class_exists($classname)) {
                debugging("Invalid aggregation class $name found", DEBUG_DEVELOPER);
                continue;
            }
            $classes[$name] = $classname;
        }

        return $classes;
    }

    /**
     * Get list of allowed advanced options for each column option.
     *
     * @return array of group select column values that are grouped
     */
    public function get_allowed_advanced_column_options() {
        $allowed = array();

        foreach ($this->columnoptions as $option) {
            $key = $option->type . '-' . $option->value;
            $allowed[$key] = array('');

            $classes = $this->get_advanced_aggregation_classes('transform');
            foreach ($classes as $name => $classname) {
                if ($classname::is_column_option_compatible($option)) {
                    $allowed[$key][] = 'transform_'.$name;
                }
            }

            $classes = $this->get_advanced_aggregation_classes('aggregate');
            foreach ($classes as $name => $classname) {
                if ($classname::is_column_option_compatible($option)) {
                    $allowed[$key][] = 'aggregate_'.$name;
                }
            }
        }
        return $allowed;
    }

    /**
     * Get list of grouped columns.
     *
     * @return array of group select column values that are grouped
     */
    public function get_grouped_column_options() {
        $grouped = array();
        foreach ($this->columnoptions as $option) {
            if ($option->grouping !== 'none') {
                $grouped[] = $option->type . '-' . $option->value;
            }
        }
        return $grouped;
    }

    /**
     * Returns list of advanced aggregation/transformation options.
     *
     * @return array nested array suitable for groupselect forms element
     */
    public function get_all_advanced_column_options() {
        $advoptions = array();
        $advoptions[get_string('none')][''] = '-';

        foreach (array('transform', 'aggregate') as $type) {
            $classes = $this->get_advanced_aggregation_classes($type);
            foreach ($classes as $name => $classname) {
                $advoptions[$classname::get_typename()][$type . '_' . $name] = get_string("{$type}type{$name}_name",
                            'shezar_reportbuilder');
            }
        }

        foreach ($advoptions as $k => $unused) {
            \core_collator::asort($advoptions[$k]);
        }

        return $advoptions;
    }

    /**
     * Set up necessary $PAGE stuff for columns.php page.
     */
    public function columns_page_requires() {
        \shezar_reportbuilder\rb\aggregate\base::require_column_heading_strings();
        \shezar_reportbuilder\rb\transform\base::require_column_heading_strings();
    }

    /**
     * @param $mform
     * @param $inlineenrolments
     */
    private function process_enrolments($mform, $inlineenrolments) {
        global $CFG;

        if ($formdata = $mform->get_data()) {
            $submittedinstance = required_param('instancesubmitted', PARAM_INT);
            $inlineenrolment = $inlineenrolments[$submittedinstance];
            $instance = $inlineenrolment->instance;
            $plugin = $inlineenrolment->plugin;
            $nameprefix = 'instanceid_' . $instance->id . '_';
            $nameprefixlength = strlen($nameprefix);

            $valuesforenrolform = array();
            foreach ($formdata as $name => $value) {
                if (substr($name, 0, $nameprefixlength) === $nameprefix) {
                    $name = substr($name, $nameprefixlength);
                    $valuesforenrolform[$name] = $value;
                }
            }
            $enrolform = $plugin->course_expand_get_form_hook($instance);

            $enrolform->_form->updateSubmission($valuesforenrolform, null);

            $enrolled = $plugin->course_expand_enrol_hook($enrolform, $instance);
            if ($enrolled) {
                $mform->_form->addElement('hidden', 'redirect', $CFG->wwwroot . '/course/view.php?id=' . $instance->courseid);
            }

            foreach ($enrolform->_form->_errors as $errorname => $error) {
                $mform->_form->_errors[$nameprefix . $errorname] = $error;
            }
        }
    }

    /**
     * Allows report source to override page header in reportbuilder exports.
     *
     * @param reportbuilder $report
     * @param string $format 'html', 'text', 'excel', 'ods', 'csv' or 'pdf'
     * @return mixed|null must be possible to cast to string[][]
     */
    public function get_custom_export_header(reportbuilder $report, $format) {
        return null;
    }
}
