<?php
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/multiboard/classes/external.php');

class mod_multiboard_external_testcase extends externallib_advanced_testcase
{

    protected function setUp(): void
    {
        $this->resetAfterTest(true);
    }

    /**
     * Test adding a note
     */
    public function test_add_note()
    {
        global $DB;

        // Create course and multiboard
        $course = $this->getDataGenerator()->create_course();
        $multiboard = $this->getDataGenerator()->create_module('multiboard', array('course' => $course->id));

        // Create columns
        $column1 = $DB->insert_record('multiboard_columns', array('multiboardid' => $multiboard->id, 'name' => 'Col 1', 'position' => 1));

        // Create user
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Add note
        $result = mod_multiboard\external::add_note($multiboard->id, $column1, 'Test Note', 'yellow');
        $result = \external_api::clean_returnvalue(mod_multiboard\external::add_note_returns(), $result);

        // Verify result
        $this->assertEquals('Test Note', $result['content']);
        $this->assertEquals('yellow', $result['color']);
        $this->assertEquals($user->id, $result['userid']);

        // Verify DB
        $note = $DB->get_record('multiboard_notes', array('id' => $result['id']));
        $this->assertEquals('Test Note', $note->content);
        $this->assertEquals($column1, $note->columnid);
    }

    /**
     * Test updating a note
     */
    public function test_update_note()
    {
        global $DB;

        // Create course and multiboard
        $course = $this->getDataGenerator()->create_course();
        $multiboard = $this->getDataGenerator()->create_module('multiboard', array('course' => $course->id));

        // Create columns
        $column1 = $DB->insert_record('multiboard_columns', array('multiboardid' => $multiboard->id, 'name' => 'Col 1', 'position' => 1));

        // Create user
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create initial note
        $noteid = $DB->insert_record('multiboard_notes', array(
            'multiboardid' => $multiboard->id,
            'columnid' => $column1,
            'userid' => $user->id,
            'content' => 'Original Content',
            'color' => 'yellow',
            'timecreated' => time(),
            'timemodified' => time()
        ));

        // Update note
        $result = mod_multiboard\external::update_note($noteid, 'Updated Content', 'blue');
        $result = \external_api::clean_returnvalue(mod_multiboard\external::update_note_returns(), $result);

        // Verify result
        $this->assertEquals('Updated Content', $result['content']);
        $this->assertEquals('blue', $result['color']);

        // Verify DB
        $note = $DB->get_record('multiboard_notes', array('id' => $noteid));
        $this->assertEquals('Updated Content', $note->content);
        $this->assertEquals('blue', $note->color);
    }

    /**
     * Test deleting a note
     */
    public function test_delete_note()
    {
        global $DB;

        // Create course and multiboard
        $course = $this->getDataGenerator()->create_course();
        $multiboard = $this->getDataGenerator()->create_module('multiboard', array('course' => $course->id));

        // Create columns
        $column1 = $DB->insert_record('multiboard_columns', array('multiboardid' => $multiboard->id, 'name' => 'Col 1', 'position' => 1));

        // Create user
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create note
        $noteid = $DB->insert_record('multiboard_notes', array(
            'multiboardid' => $multiboard->id,
            'columnid' => $column1,
            'userid' => $user->id,
            'content' => 'To Delete',
            'color' => 'yellow',
            'timecreated' => time(),
            'timemodified' => time()
        ));

        // Delete note
        mod_multiboard\external::delete_note($noteid);

        // Verify DB
        $this->assertFalse($DB->record_exists('multiboard_notes', array('id' => $noteid)));
    }

    /**
     * Test updating a column
     */
    public function test_update_column()
    {
        global $DB;

        // Create course and multiboard
        $course = $this->getDataGenerator()->create_course();
        $multiboard = $this->getDataGenerator()->create_module('multiboard', array('course' => $course->id));

        // Create columns
        $column1 = $DB->insert_record('multiboard_columns', array('multiboardid' => $multiboard->id, 'name' => 'Old Name', 'position' => 1));

        // Create teacher (manager)
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        // Update column
        mod_multiboard\external::update_column($column1, 'New Name');

        // Verify DB
        $column = $DB->get_record('multiboard_columns', array('id' => $column1));
        $this->assertEquals('New Name', $column->name);
    }

    /**
     * Test permission checks (Student cannot update other's note)
     */
    public function test_update_note_permission()
    {
        global $DB;

        // Create course and multiboard
        $course = $this->getDataGenerator()->create_course();
        $multiboard = $this->getDataGenerator()->create_module('multiboard', array('course' => $course->id));
        $column1 = $DB->insert_record('multiboard_columns', array('multiboardid' => $multiboard->id, 'name' => 'Col 1', 'position' => 1));

        // Create two users
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');

        // User 1 creates a note
        $noteid = $DB->insert_record('multiboard_notes', array(
            'multiboardid' => $multiboard->id,
            'columnid' => $column1,
            'userid' => $user1->id,
            'content' => 'User 1 Note',
            'color' => 'yellow',
            'timecreated' => time(),
            'timemodified' => time()
        ));

        // User 2 tries to update it
        $this->setUser($user2);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('nopermissiontoupdate');

        mod_multiboard\external::update_note($noteid, 'Hacked Content', 'red');
    }
}
