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
 * @author Ciaran Irvine <ciaran.irvine@shezarlms.com>
 * @package shezar
 * @subpackage shezar_core
 */

function xmldb_shezar_plan_install() {
    global $DB, $CFG;

    //create default priority & objective scales and default template
    /*default priority scale*/
    $ps = new stdClass();
    $ps->name = get_string('defaultpriorityscalename', 'shezar_plan');
    $ps->description = get_string('defaultpriorityscaledescription', 'shezar_plan');
    $ps->sortorder = 1;
    $ps->timemodified = time();
    $ps->usermodified = 0;
    $psid = $DB->insert_record('dp_priority_scale', $ps);

    // Add values to that default priority scale
    $psv = new stdClass();
    $psv->name = get_string('defaultscalevaluehigh', 'shezar_plan');
    $psv->priorityscaleid = $psid;
    $psv->sortorder = 1;
    $psv->timemodified = time();
    $psv->usermodified = 0;
    $DB->insert_record('dp_priority_scale_value', $psv);

    $psv = new stdClass();
    $psv->name = get_string('defaultscalevaluemedium', 'shezar_plan');
    $psv->priorityscaleid = $psid;
    $psv->sortorder = 2;
    $psv->timemodified = time();
    $psv->usermodified = 0;
    $DB->insert_record('dp_priority_scale_value', $psv);

    $psv = new stdClass();
    $psv->name = get_string('defaultscalevaluelow', 'shezar_plan');
    $psv->priorityscaleid = $psid;
    $psv->sortorder = 3;
    $psv->timemodified = time();
    $psv->usermodified = 0;
    $psvid = $DB->insert_record('dp_priority_scale_value', $psv);

    // Add the low value as the default to the priority scale
    $ps->id = $psid;
    $ps->defaultid = $psvid;
    $DB->update_record('dp_priority_scale', $ps);

    /*default objective scale*/
    $os = new stdClass();
    $os->name = get_string('defaultobjectivescalename', 'shezar_plan');
    $os->description = get_string('defaultobjectivescaledescription', 'shezar_plan');
    $os->timemodified = time();
    $os->usermodified = 0;
    $os->sortorder = 1;
    $osid = $DB->insert_record('dp_objective_scale', $os);

    // Add scale values
    $osv = new stdClass();
    $osv->name = get_string('defaultscalevaluecompleted', 'shezar_plan');
    $osv->objscaleid = $osid;
    $osv->achieved = 1;
    $osv->sortorder = 1;
    $osv->timemodified = time();
    $osv->usermodified = 0;
    $DB->insert_record('dp_objective_scale_value', $osv);

    $osv = new stdClass();
    $osv->name = get_string('defaultscalevalueinprogress', 'shezar_plan');
    $osv->objscaleid = $osid;
    $osv->achieved = 0;
    $osv->sortorder = 2;
    $osv->timemodified = time();
    $osv->usermodified = 0;
    $DB->insert_record('dp_objective_scale_value', $osv);

    $osv = new stdClass();
    $osv->name = get_string('defaultscalevaluenotstarted', 'shezar_plan');
    $osv->achieved = 0;
    $osv->objscaleid = $osid;
    $osv->sortorder = 3;
    $osv->timemodified = time();
    $osv->usermodified = 0;
    $osvid = $DB->insert_record('dp_objective_scale_value', $osv);

    // Add "not met" as the default for the objective scale
    $os->id = $osid;
    $os->defaultid = $osvid;
    $DB->update_record('dp_objective_scale', $os);

    // create a default template
    require_once($CFG->dirroot . '/shezar/plan/lib.php');
    $templatename = get_string('learningplan', 'shezar_plan');
    $enddate = time() + YEARSECS; // one year from now
    $error = '';
    if (!$templateid = dp_create_template($templatename, $enddate, $error)) {
        error_log($error);
    }

    // Update template to be the default
    $template_update = new stdClass();
    $template_update->id = $templateid;
    $template_update->isdefault = 1;
    $DB->update_record('dp_template', $template_update);

    // Create evidence description custom field.
    $data = new stdClass();
    $data->fullname = get_string('evidencedescription', 'shezar_plan');
    $data->shortname = str_replace(' ', '', get_string('evidencedescriptionshort', 'shezar_plan'));
    $data->datatype = 'textarea';
    $data->sortorder = 1;
    $data->hidden = 0;
    $data->locked = 0;
    $data->required = 0;
    $data->forceunique = 0;
    $data->param1 = 30;
    $data->param2 = 10;
    $DB->insert_record('dp_plan_evidence_info_field', $data);

    // Create evidence file attachments custom field.
    $data = new stdClass();
    $data->fullname = get_string('evidencefileattachments', 'shezar_plan');
    $data->shortname = str_replace(' ', '', get_string('evidencefileattachmentsshort', 'shezar_plan'));
    $data->datatype = 'file';
    $data->sortorder = 2;
    $data->hidden = 0;
    $data->locked = 0;
    $data->required = 0;
    $data->forceunique = 0;
    $data->defaultdata = 0;
    $DB->insert_record('dp_plan_evidence_info_field', $data);

    // Create evidence date completed custom field.
    $data = new stdClass();
    $data->fullname = get_string('evidencedatecompleted', 'shezar_plan');
    $data->shortname = str_replace(' ', '', get_string('evidencedatecompletedshort', 'shezar_plan'));
    $data->datatype = 'datetime';
    $data->sortorder = 3;
    $data->hidden = 0;
    $data->locked = 0;
    $data->required = 0;
    $data->forceunique = 0;
    $data->defaultdata = 0;
    $data->param1 = 1900;
    $data->param2 = 2050;
    $DB->insert_record('dp_plan_evidence_info_field', $data);

    return true;
}
