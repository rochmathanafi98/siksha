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
 * @package shezar_core
 * @category tests
 */

defined('MOODLE_INTERNAL') || die();

class shezar_core_event_course_completion_unlocked_testcase extends advanced_testcase {
    public function test_event() {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $event = \shezar_core\event\course_completion_unlocked::create_from_course($course);
        $event->trigger();

        $this->assertSame('course', $event->objecttable);
        $this->assertSame($course->id, $event->objectid);
        $this->assertSame('u', $event->crud);
        $this->assertSame($event::LEVEL_OTHER, $event->edulevel);
        $this->assertSame(CONTEXT_COURSE, $event->contextlevel);
        $this->assertSame($course->id, $event->contextinstanceid);
        $this->assertSame(null, $event->other);
        $this->assertEquals(new moodle_url('/course/completion.php', array('id' => $course->id)), $event->get_url());

        $this->assertEventContextNotUsed($event);
        $this->assertEventLegacyLogData(array($course->id, 'course', 'completion unlocked without reset', 'completion.php?id=' . $course->id), $event);
    }
}