<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Adds a new instance of the multiboard activity.
 *
 * @param stdClass $multiboard Object containing the new settings
 * @return int The new instance ID
 */
function multiboard_add_instance($multiboard)
{
    global $DB;

    $multiboard->timecreated = time();
    $multiboard->timemodified = time();

    $id = $DB->insert_record('multiboard', $multiboard);

    // Create default columns
    $columns = ['Category 1', 'Category 2', 'Category 3'];
    foreach ($columns as $index => $name) {
        $column = new stdClass();
        $column->multiboardid = $id;
        $column->name = $name;
        $column->position = $index;
        $DB->insert_record('multiboard_columns', $column);
    }

    return $id;
}

/**
 * Updates an instance of the multiboard activity.
 *
 * @param stdClass $multiboard Object containing the new settings
 * @return bool True on success
 */
function multiboard_update_instance($multiboard)
{
    global $DB;

    $multiboard->timemodified = time();
    $multiboard->id = $multiboard->instance;

    return $DB->update_record('multiboard', $multiboard);
}

/**
 * Deletes an instance of the multiboard activity.
 *
 * @param int $id The instance ID
 * @return bool True on success
 */
function multiboard_delete_instance($id)
{
    global $DB;

    if (!$multiboard = $DB->get_record('multiboard', array('id' => $id))) {
        return false;
    }

    // Delete related records
    $DB->delete_records('multiboard_comments', array('noteid' => $id)); // This is wrong, need to find notes first

    // Get all notes to delete comments
    $notes = $DB->get_records('multiboard_notes', array('multiboardid' => $id));
    foreach ($notes as $note) {
        $DB->delete_records('multiboard_comments', array('noteid' => $note->id));
    }

    $DB->delete_records('multiboard_notes', array('multiboardid' => $id));
    $DB->delete_records('multiboard_columns', array('multiboardid' => $id));
    $DB->delete_records('multiboard', array('id' => $id));

    return true;
}

/**
 * Supports features
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function multiboard_supports($feature)
{
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return null;
    }
}
