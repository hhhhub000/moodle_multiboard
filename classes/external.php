<?php
namespace mod_multiboard;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_module;

class external extends external_api
{

    public static function add_note_parameters()
    {
        return new external_function_parameters(
            array(
                'multiboardid' => new external_value(PARAM_INT, 'Multiboard instance ID'),
                'columnid' => new external_value(PARAM_INT, 'Column ID'),
                'content' => new external_value(PARAM_RAW, 'Note content'),
                'color' => new external_value(PARAM_ALPHA, 'Note color', false, 'yellow'),
            )
        );
    }

    public static function add_note($multiboardid, $columnid, $content, $color = 'yellow')
    {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::add_note_parameters(),
            array(
                'multiboardid' => $multiboardid,
                'columnid' => $columnid,
                'content' => $content,
                'color' => $color,
            )
        );

        $context = context_module::instance(get_coursemodule_from_instance('multiboard', $params['multiboardid'])->id);
        self::validate_context($context);
        require_capability('mod/multiboard:addnote', $context);

        $note = new \stdClass();
        $note->multiboardid = $params['multiboardid'];
        $note->columnid = $params['columnid'];
        $note->userid = $USER->id;
        $note->content = clean_text($params['content'], FORMAT_HTML);
        $note->color = $params['color'];
        $note->timecreated = time();
        $note->timemodified = time();
        $note->format = 1; // FORMAT_HTML

        // Get max position in column
        $maxpos = $DB->get_field_sql("SELECT MAX(position) FROM {multiboard_notes} WHERE columnid = ?", array($params['columnid']));
        $note->position = $maxpos !== false ? $maxpos + 1 : 0;

        $id = $DB->insert_record('multiboard_notes', $note);
        $note->id = $id;

        return array(
            'id' => $id,
            'content' => $note->content,
            'color' => $note->color,
            'userid' => $note->userid,
            'userfullname' => fullname($USER),
            'timecreated' => $note->timecreated,
        );
    }

    public static function add_note_returns()
    {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'Note ID'),
                'content' => new external_value(PARAM_RAW, 'Note content'),
                'color' => new external_value(PARAM_ALPHA, 'Note color'),
                'userid' => new external_value(PARAM_INT, 'User ID'),
                'userfullname' => new external_value(PARAM_TEXT, 'User full name'),
                'timecreated' => new external_value(PARAM_INT, 'Time created'),
            )
        );
    }

    public static function update_note_position_parameters()
    {
        return new external_function_parameters(
            array(
                'noteid' => new external_value(PARAM_INT, 'Note ID'),
                'columnid' => new external_value(PARAM_INT, 'New Column ID'),
                'position' => new external_value(PARAM_INT, 'New Position'),
            )
        );
    }

    public static function update_note_position($noteid, $columnid, $position)
    {
        global $DB;

        $params = self::validate_parameters(
            self::update_note_position_parameters(),
            array(
                'noteid' => $noteid,
                'columnid' => $columnid,
                'position' => $position,
            )
        );

        $note = $DB->get_record('multiboard_notes', array('id' => $params['noteid']), '*', MUST_EXIST);
        $context = context_module::instance(get_coursemodule_from_instance('multiboard', $note->multiboardid)->id);
        self::validate_context($context);
        require_capability('mod/multiboard:addnote', $context);

        $note->columnid = $params['columnid'];
        $note->position = $params['position'];
        $note->timemodified = time();

        $DB->update_record('multiboard_notes', $note);

        return true;
    }

    public static function update_note_position_returns()
    {
        return new external_value(PARAM_BOOL, 'Success');
    }

    public static function add_comment_parameters()
    {
        return new external_function_parameters(
            array(
                'noteid' => new external_value(PARAM_INT, 'Note ID'),
                'content' => new external_value(PARAM_RAW, 'Comment content'),
            )
        );
    }

    public static function add_comment($noteid, $content)
    {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::add_comment_parameters(),
            array(
                'noteid' => $noteid,
                'content' => $content,
            )
        );

        $note = $DB->get_record('multiboard_notes', array('id' => $params['noteid']), '*', MUST_EXIST);
        $context = context_module::instance(get_coursemodule_from_instance('multiboard', $note->multiboardid)->id);
        self::validate_context($context);
        require_capability('mod/multiboard:addnote', $context);

        $comment = new \stdClass();
        $comment->noteid = $params['noteid'];
        $comment->userid = $USER->id;
        $comment->content = clean_text($params['content'], FORMAT_HTML);
        $comment->timecreated = time();
        $comment->format = 1; // FORMAT_HTML

        $id = $DB->insert_record('multiboard_comments', $comment);

        return array(
            'id' => $id,
            'content' => $comment->content,
            'userid' => $comment->userid,
            'userfullname' => fullname($USER),
            'timecreated' => $comment->timecreated,
        );
    }

    public static function add_comment_returns()
    {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'Comment ID'),
                'content' => new external_value(PARAM_RAW, 'Comment content'),
                'userid' => new external_value(PARAM_INT, 'User ID'),
                'userfullname' => new external_value(PARAM_TEXT, 'User full name'),
                'timecreated' => new external_value(PARAM_INT, 'Time created'),
            )
        );
    }

    public static function get_updates_parameters()
    {
        return new external_function_parameters(
            array(
                'multiboardid' => new external_value(PARAM_INT, 'Multiboard instance ID'),
                'since' => new external_value(PARAM_INT, 'Timestamp to get updates since'),
            )
        );
    }

    public static function get_updates($multiboardid, $since)
    {
        global $DB, $OUTPUT;

        $params = self::validate_parameters(
            self::get_updates_parameters(),
            array(
                'multiboardid' => $multiboardid,
                'since' => $since,
            )
        );

        $context = context_module::instance(get_coursemodule_from_instance('multiboard', $params['multiboardid'])->id);
        self::validate_context($context);

        // Get new/updated notes
        $notes = $DB->get_records_select(
            'multiboard_notes',
            'multiboardid = ? AND timemodified > ?',
            array($params['multiboardid'], $params['since'])
        );

        $notesdata = [];
        foreach ($notes as $note) {
            $user = $DB->get_record('user', array('id' => $note->userid));
            $notesdata[] = array(
                'id' => $note->id,
                'columnid' => $note->columnid,
                'content' => $note->content,
                'color' => $note->color,
                'position' => $note->position,
                'userid' => $note->userid,
                'userfullname' => fullname($user),
                'userpicture' => $OUTPUT->user_picture($user, array('size' => 35)),
                'timecreated' => $note->timecreated,
            );
        }

        // Get new comments (need to join with notes to check multiboardid)
        $sql = "SELECT c.* FROM {multiboard_comments} c 
                JOIN {multiboard_notes} n ON c.noteid = n.id 
                WHERE n.multiboardid = ? AND c.timecreated > ?";
        $comments = $DB->get_records_sql($sql, array($params['multiboardid'], $params['since']));

        $commentsdata = [];
        foreach ($comments as $comment) {
            $user = $DB->get_record('user', array('id' => $comment->userid));
            $commentsdata[] = array(
                'id' => $comment->id,
                'noteid' => $comment->noteid,
                'content' => $comment->content,
                'userid' => $comment->userid,
                'userfullname' => fullname($user),
                'userpicture' => $OUTPUT->user_picture($user, array('size' => 20)),
                'timecreated' => $comment->timecreated,
            );
        }

        return array(
            'notes' => $notesdata,
            'comments' => $commentsdata,
        );
    }

    public static function get_updates_returns()
    {
        return new external_single_structure(
            array(
                'notes' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Note ID'),
                            'columnid' => new external_value(PARAM_INT, 'Column ID'),
                            'content' => new external_value(PARAM_RAW, 'Note content'),
                            'color' => new external_value(PARAM_ALPHA, 'Note color'),
                            'position' => new external_value(PARAM_INT, 'Position'),
                            'userid' => new external_value(PARAM_INT, 'User ID'),
                            'userfullname' => new external_value(PARAM_TEXT, 'User full name'),
                            'userpicture' => new external_value(PARAM_RAW, 'User picture HTML'),
                            'timecreated' => new external_value(PARAM_INT, 'Time created'),
                        )
                    )
                ),
                'comments' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Comment ID'),
                            'noteid' => new external_value(PARAM_INT, 'Note ID'),
                            'content' => new external_value(PARAM_RAW, 'Comment content'),
                            'userid' => new external_value(PARAM_INT, 'User ID'),
                            'userfullname' => new external_value(PARAM_TEXT, 'User full name'),
                            'userpicture' => new external_value(PARAM_RAW, 'User picture HTML'),
                            'timecreated' => new external_value(PARAM_INT, 'Time created'),
                        )
                    )
                ),
            )
        );
    }

    public static function delete_note_parameters()
    {
        return new external_function_parameters(
            array(
                'noteid' => new external_value(PARAM_INT, 'Note ID'),
            )
        );
    }

    public static function delete_note($noteid)
    {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::delete_note_parameters(),
            array(
                'noteid' => $noteid,
            )
        );

        $note = $DB->get_record('multiboard_notes', array('id' => $params['noteid']), '*', MUST_EXIST);
        $context = context_module::instance(get_coursemodule_from_instance('multiboard', $note->multiboardid)->id);
        self::validate_context($context);

        // Check permission (only owner or teacher can delete)
        if ($note->userid != $USER->id && !has_capability('mod/multiboard:manageentries', $context)) {
            // For now, just allow owner to delete. 
            // Ideally we should check capabilities properly.
            // Assuming if not owner, throw exception if strict.
            // But for simplicity in this prototype, let's just allow owner.
            if ($note->userid != $USER->id) {
                throw new \moodle_exception('nopermissiontodelete', 'mod_multiboard');
            }
        }

        $DB->delete_records('multiboard_comments', array('noteid' => $note->id));
        $DB->delete_records('multiboard_notes', array('id' => $note->id));

        return true;
    }

    public static function delete_note_returns()
    {
        return new external_value(PARAM_BOOL, 'Success');
    }

    public static function update_column_parameters()
    {
        return new external_function_parameters(
            array(
                'columnid' => new external_value(PARAM_INT, 'Column ID'),
                'name' => new external_value(PARAM_TEXT, 'Column Name'),
            )
        );
    }

    public static function update_column($columnid, $name)
    {
        global $DB;

        $params = self::validate_parameters(
            self::update_column_parameters(),
            array(
                'columnid' => $columnid,
                'name' => $name,
            )
        );

        $column = $DB->get_record('multiboard_columns', array('id' => $params['columnid']), '*', MUST_EXIST);
        $context = context_module::instance(get_coursemodule_from_instance('multiboard', $column->multiboardid)->id);
        self::validate_context($context);
        require_capability('mod/multiboard:manageentries', $context);

        $column->name = $params['name'];
        $DB->update_record('multiboard_columns', $column);

        return true;
    }

    public static function update_column_returns()
    {
        return new external_value(PARAM_BOOL, 'Success');
    }

    public static function update_note_parameters()
    {
        return new external_function_parameters(
            array(
                'noteid' => new external_value(PARAM_INT, 'Note ID'),
                'content' => new external_value(PARAM_RAW, 'Note content'),
                'color' => new external_value(PARAM_ALPHA, 'Note color', false, 'yellow'),
            )
        );
    }

    public static function update_note($noteid, $content, $color = 'yellow')
    {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::update_note_parameters(),
            array(
                'noteid' => $noteid,
                'content' => $content,
                'color' => $color,
            )
        );

        $note = $DB->get_record('multiboard_notes', array('id' => $params['noteid']), '*', MUST_EXIST);
        $context = context_module::instance(get_coursemodule_from_instance('multiboard', $note->multiboardid)->id);
        self::validate_context($context);

        // Check permission (only owner or teacher can update)
        if ($note->userid != $USER->id && !has_capability('mod/multiboard:manageentries', $context)) {
            throw new \moodle_exception('nopermissiontoupdate', 'mod_multiboard');
        }

        $note->content = clean_text($params['content'], FORMAT_HTML);
        $note->color = $params['color'];
        $note->timemodified = time();

        $DB->update_record('multiboard_notes', $note);

        return array(
            'id' => $note->id,
            'content' => $note->content,
            'color' => $note->color,
            'userid' => $note->userid,
            'userfullname' => fullname($USER), // In case we want to update this too, though unlikely to change
            'timecreated' => $note->timecreated,
        );
    }

    public static function update_note_returns()
    {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'Note ID'),
                'content' => new external_value(PARAM_RAW, 'Note content'),
                'color' => new external_value(PARAM_ALPHA, 'Note color'),
                'userid' => new external_value(PARAM_INT, 'User ID'),
                'userfullname' => new external_value(PARAM_TEXT, 'User full name'),
                'timecreated' => new external_value(PARAM_INT, 'Time created'),
            )
        );
    }
}
