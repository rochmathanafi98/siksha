<?php
/*
 * This file is part of shezar LMS
 *
 * Copyright (C) 2016 onwards shezar Learning Solutions LTD
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
 * @author Alastair Munro <alastair.munro@shezarlearning.com>
 * @package shezar_program
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/shezar/plan/development_plan.class.php');

class shezar_plan_user_learning_item_testcase extends advanced_testcase {

    /**
     * @var testing_data_generator
     */
    private $generator;

    /**
     * @var shezar_plan_generator
     */
    private $plan_generator;

    private $course1;

    private $course2;

    private $plan1;

    private $plan2;

    private $user1;

    public function setUp() {
        $this->resetAfterTest(true);
        parent::setUp();

        $this->generator = $this->getDataGenerator();
        $this->plan_generator = $this->generator->get_plugin_generator('shezar_plan');

        $this->course1 = $this->generator->create_course();
        $this->course2 = $this->generator->create_course();

        $this->user1 = $this->getDataGenerator()->create_user(array('fullname' => 'user1'));
    }

    /**
     * Test the "one" static method for plans.
     */
    public function test_one () {
        $this->plan_generator = $this->getDataGenerator()->get_plugin_generator('shezar_plan');
        $planrecord = $this->plan_generator->create_learning_plan(array('userid' => $this->user1->id));

        $plan = new development_plan($planrecord->id);

        $this->setAdminUser();

        $result = $this->plan_generator->add_learning_plan_course($plan->id, $this->course1->id);
        $result = $this->plan_generator->add_learning_plan_course($plan->id, $this->course2->id);

        $item = \shezar_plan\user_learning\item::one($this->user1, $plan->id);

        $this->assertEquals($plan->name, $item->fullname);
        $this->assertEquals($this->user1->id, $plan->userid);
    }
}
