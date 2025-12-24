<?php
defined('MOODLE_INTERNAL') || die();

$functions = array(
    'mod_multiboard_add_note' => array(
        'classname' => 'mod_multiboard\external',
        'methodname' => 'add_note',
        'description' => 'Adds a new note',
        'type' => 'write',
        'ajax' => true,
    ),
    'mod_multiboard_update_note_position' => array(
        'classname' => 'mod_multiboard\external',
        'methodname' => 'update_note_position',
        'description' => 'Updates note position',
        'type' => 'write',
        'ajax' => true,
    ),
    'mod_multiboard_add_comment' => array(
        'classname' => 'mod_multiboard\external',
        'methodname' => 'add_comment',
        'description' => 'Adds a comment to a note',
        'type' => 'write',
        'ajax' => true,
    ),
    'mod_multiboard_get_updates' => array(
        'classname' => 'mod_multiboard\external',
        'methodname' => 'get_updates',
        'description' => 'Gets updates for the board',
        'type' => 'read',
        'ajax' => true,
    ),
    'mod_multiboard_delete_note' => array(
        'classname' => 'mod_multiboard\external',
        'methodname' => 'delete_note',
        'classpath' => 'mod/multiboard/classes/external.php',
        'description' => 'Deletes a note',
        'type' => 'write',
        'ajax' => true,
    ),
    'mod_multiboard_update_column' => array(
        'classname' => 'mod_multiboard\external',
        'methodname' => 'update_column',
        'classpath' => 'mod/multiboard/classes/external.php',
        'description' => 'Updates a column',
        'type' => 'write',
        'ajax' => true,
    ),
    'mod_multiboard_update_note' => array(
        'classname' => 'mod_multiboard\external',
        'methodname' => 'update_note',
        'classpath' => 'mod/multiboard/classes/external.php',
        'description' => 'Updates a note',
        'type' => 'write',
        'ajax' => true,
    ),
);
