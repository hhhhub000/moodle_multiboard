<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/multiboard/lib.php');

$id = required_param('id', PARAM_INT); // Course Module ID

$cm = get_coursemodule_from_id('multiboard', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$multiboard = $DB->get_record('multiboard', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Check capability
require_capability('mod/multiboard:manageentries', $context);

// Set headers for CSV download
$filename = 'multiboard_export_' . date('YmdHis') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open output stream
$output = fopen('php://output', 'w');

// Output BOM for Excel compatibility with UTF-8
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Output headers
// 書き込みID、親スレッドID(自身が親の場合は空白)、カテゴリ、ユーザー名、ユーザーメールアドレス、書き込み内容
fputcsv($output, ['ID', 'Parent ID', 'Category', 'User Name', 'Email', 'Content']);

// Get all notes
$notes = $DB->get_records('multiboard_notes', array('multiboardid' => $multiboard->id), 'timecreated ASC');

foreach ($notes as $note) {
    $user = $DB->get_record('user', array('id' => $note->userid));
    $column = $DB->get_record('multiboard_columns', array('id' => $note->columnid));

    // Output note row
    fputcsv($output, [
        $note->id,
        '', // Parent ID is empty for notes
        $column ? $column->name : 'Unknown',
        fullname($user),
        $user ? $user->email : '',
        $note->content
    ]);

    // Get comments for this note
    $comments = $DB->get_records('multiboard_comments', array('noteid' => $note->id), 'timecreated ASC');
    foreach ($comments as $comment) {
        $commentuser = $DB->get_record('user', array('id' => $comment->userid));

        // Output comment row
        fputcsv($output, [
            $comment->id,
            $note->id, // Parent ID is the note ID
            $column ? $column->name : 'Unknown', // Inherit category from note
            fullname($commentuser),
            $commentuser ? $commentuser->email : '',
            $comment->content
        ]);
    }
}

fclose($output);
exit;
