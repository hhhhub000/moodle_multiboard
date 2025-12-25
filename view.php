<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/multiboard/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID
$n = optional_param('n', 0, PARAM_INT);  // Multiboard instance ID

if ($id) {
    $cm = get_coursemodule_from_id('multiboard', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $multiboard = $DB->get_record('multiboard', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $multiboard = $DB->get_record('multiboard', array('id' => $n), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $multiboard->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('multiboard', $multiboard->id, $course->id, false, MUST_EXIST);
} else {
    print_error('missingidandcmid');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

$PAGE->set_url('/mod/multiboard/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($multiboard->name));
$PAGE->set_heading(format_string($course->fullname));

// Output starts here
echo $OUTPUT->header();

// echo $OUTPUT->heading(format_string($multiboard->name)); // Removed to prevent duplicate title

// Export button for teachers
if (has_capability('mod/multiboard:manageentries', $context)) {
    $exporturl = new moodle_url('/mod/multiboard/export.php', array('id' => $cm->id));
    echo $OUTPUT->single_button($exporturl, get_string('exportcsv', 'mod_multiboard'));
}

// Inline CSS to force cursor style (bypass cache)
echo '<style>
.multiboard-column { cursor: pointer !important; }
.column-header { cursor: pointer !important; }
.empty-placeholder {
    color: #6b778c;
    text-align: center;
    padding: 20px;
    border: 2px dashed #dfe1e6;
    border-radius: 3px;
    margin-top: 10px;
    pointer-events: none;
}
</style>';

if ($multiboard->intro) {
    echo $OUTPUT->box(format_module_intro('multiboard', $multiboard, $cm->id), 'generalbox mod_introbox', 'multiboardintro');
}

// Get columns
$columns = $DB->get_records('multiboard_columns', array('multiboardid' => $multiboard->id), 'position ASC');

// Get notes
$notes = $DB->get_records('multiboard_notes', array('multiboardid' => $multiboard->id), 'timecreated ASC');

// Prepare data for template
$canmanagecolumns = has_capability('mod/multiboard:manageentries', $context);
$data = [
    'id' => $multiboard->id,
    'cmid' => $cm->id,
    'columns' => array_values($columns),
    'userid' => $USER->id,
    'canmanagecolumns' => $canmanagecolumns,
];

// Map notes to columns
$notesbycolumn = [];
foreach ($columns as $column) {
    $notesbycolumn[$column->id] = [];
}

foreach ($notes as $note) {
    $note->user = $DB->get_record('user', array('id' => $note->userid));
    if (!$note->user) {
        $note->user = new stdClass();
        $note->user->id = 0;
        $note->user->firstname = 'Deleted';
        $note->user->lastname = 'User';
        $note->user->picture = 0;
        $note->user->email = '';
    }
    $note->userfullname = fullname($note->user);
    $note->userpicture = $OUTPUT->user_picture($note->user, array('size' => 35));
    $note->timecreatedstr = userdate($note->timecreated);

    // Get comments for this note
    $comments = $DB->get_records('multiboard_comments', array('noteid' => $note->id), 'timecreated ASC');
    $note->comments = [];
    foreach ($comments as $comment) {
        $comment->user = $DB->get_record('user', array('id' => $comment->userid));
        if (!$comment->user) {
            $comment->user = new stdClass();
            $comment->user->id = 0;
            $comment->user->firstname = 'Deleted';
            $comment->user->lastname = 'User';
            $comment->user->picture = 0;
            $comment->user->email = '';
        }
        $comment->userfullname = fullname($comment->user);
        $comment->userpicture = $OUTPUT->user_picture($comment->user, array('size' => 20));
        $comment->timecreatedstr = userdate($comment->timecreated);
        $note->comments[] = $comment;
    }

    $note->canmanage = ($note->userid == $USER->id) || has_capability('mod/multiboard:manageentries', $context);
    $notesbycolumn[$note->columnid][] = $note;
}

// Add notes to columns in data
foreach ($data['columns'] as &$column) {
    $column->notes = isset($notesbycolumn[$column->id]) ? $notesbycolumn[$column->id] : [];
    $column->canmanagecolumns = $canmanagecolumns;
}

echo $OUTPUT->render_from_template('mod_multiboard/main', $data);

// Initialize JS
$clicktoadd = get_string('clicktoadd', 'mod_multiboard');
$addnote = get_string('addnote', 'mod_multiboard');
$edit = get_string('edit', 'mod_multiboard');
$now = time();

$PAGE->requires->js_call_amd('mod_multiboard/board', 'init', array(
    array(
        'multiboardid' => $multiboard->id,
        'cmid' => $cm->id,
        'strings' => [
            'clicktoadd' => $clicktoadd,
            'addnote' => $addnote,
            'edit' => $edit
        ],
        'now' => $now,
        'canmanagecolumns' => $canmanagecolumns
    )
));

echo $OUTPUT->footer();
