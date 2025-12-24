define(['jquery', 'core/ajax', 'core/notification', 'core/modal_factory', 'core/modal_events'], function ($, Ajax, Notification, ModalFactory, ModalEvents) {

    var Board = {
        multiboardId: 0,
        cmId: 0,
        lastUpdate: 0,
        timer: null,
        strings: {},

        init: function (params) {
            this.multiboardId = params.multiboardid;
            this.cmId = params.cmid;
            this.strings = params.strings || {};
            this.lastUpdate = params.now || Math.floor(Date.now() / 1000);

            this.registerEvents();
            this.startPolling();
        },

        registerEvents: function () {
            var that = this;

            // Add Note Button
            $('#add-note-btn').on('click', function () {
                $('#note-id').val(''); // Clear note ID
                $('#note-content').val('');
                $('#note-color').val('yellow');
                $('#addNoteModalLabel').text(that.strings.addnote || 'Add Note');
                $('#addNoteModal').modal('show');
            });

            // Close Modal (X button and Close button)
            $('.close, .btn-secondary[data-dismiss="modal"]').on('click', function () {
                $('#addNoteModal').modal('hide');
            });

            // Open Modal on Column Click (excluding notes and header)
            $(document).on('click', '.multiboard-column', function (e) {
                if ($(e.target).closest('.multiboard-note').length === 0 &&
                    $(e.target).closest('.column-header').length === 0) {
                    var columnId = $(this).data('column-id');
                    $('#note-id').val(''); // Clear note ID
                    $('#note-content').val('');
                    $('#note-color').val('yellow');
                    $('#note-column').val(columnId);
                    $('#addNoteModalLabel').text(that.strings.addnote || 'Add Note');
                    $('#addNoteModal').modal('show');
                }
            });

            // Edit Note Button
            $(document).on('click', '.edit-note', function () {
                var note = $(this).closest('.multiboard-note');
                var noteId = note.data('note-id');
                var content = note.find('.note-content').text();

                // Get color class
                var colorClass = note.attr('class').match(/color-(\w+)/);
                var color = colorClass ? colorClass[1] : 'yellow';

                var columnId = note.closest('.multiboard-column').data('column-id');

                $('#note-id').val(noteId);
                $('#note-content').val(content);
                $('#note-color').val(color);
                $('#note-column').val(columnId);
                $('#addNoteModalLabel').text(that.strings.edit || 'Edit Note');
                $('#addNoteModal').modal('show');
            });

            // Column Name Editing
            $(document).on('click', '.column-header', function () {
                var $h3 = $(this);
                var currentName = $h3.text();
                var columnId = $h3.closest('.multiboard-column').data('column-id');

                var $input = $('<input type="text" class="form-control column-name-input">').val(currentName);
                $h3.replaceWith($input);
                $input.focus();

                $input.on('blur keypress', function (e) {
                    if (e.type === 'keypress' && e.which !== 13) return;

                    var newName = $input.val();
                    if (newName && newName !== currentName) {
                        Ajax.call([{
                            methodname: 'mod_multiboard_update_column',
                            args: {
                                columnid: columnId,
                                name: newName
                            }
                        }])[0].done(function () {
                            $input.replaceWith('<h3 class="column-header">' + newName + '</h3>');
                        }).fail(function (ex) {
                            Notification.exception(ex);
                            $input.replaceWith('<h3 class="column-header">' + currentName + '</h3>');
                        });
                    } else {
                        $input.replaceWith('<h3 class="column-header">' + currentName + '</h3>');
                    }
                });
            });

            // Save Note (Add or Update)
            $(document).on('click', '#save-note', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var noteId = $('#note-id').val();
                var content = $('#note-content').val();
                var color = $('#note-color').val();
                var columnId = $('#note-column').val();

                if (!content) {
                    alert('Content is required');
                    return;
                }

                // Disable button to prevent double submission
                var btn = $(this);
                btn.prop('disabled', true);

                if (noteId) {
                    // Update existing note
                    Ajax.call([{
                        methodname: 'mod_multiboard_update_note',
                        args: {
                            noteid: noteId,
                            content: content,
                            color: color
                        }
                    }])[0].done(function (response) {
                        $('#addNoteModal').modal('hide');
                        $('#note-content').val('');
                        $('#note-id').val('');

                        // Update DOM
                        var noteEl = $(`.multiboard-note[data-note-id="${noteId}"]`);
                        noteEl.removeClass(function (index, className) {
                            return (className.match(/(^|\s)color-\S+/g) || []).join(' ');
                        }).addClass('color-' + response.color);
                        noteEl.find('.note-content').html(response.content);

                        // If column changed (though modal doesn't allow changing column for edit yet, but good to be safe)
                        var currentColumnId = noteEl.closest('.multiboard-column').data('column-id');
                        if (currentColumnId != columnId) {
                            // Move note
                            noteEl.detach().appendTo($(`.multiboard-column[data-column-id="${columnId}"] .column-content`));
                        }

                    }).fail(Notification.exception)
                        .always(function () { btn.prop('disabled', false); });
                } else {
                    // Add new note
                    Ajax.call([{
                        methodname: 'mod_multiboard_add_note',
                        args: {
                            multiboardid: that.multiboardId,
                            columnid: columnId,
                            content: content,
                            color: color
                        }
                    }])[0].done(function (response) {
                        $('#addNoteModal').modal('hide');
                        $('#note-content').val('');
                        that.renderNote(response, columnId);
                    }).fail(Notification.exception)
                        .always(function () { btn.prop('disabled', false); });
                }
            });

            // Reply Button
            $(document).on('click', '.reply-btn', function () {
                $(this).closest('.multiboard-note').find('.comment-form').toggleClass('hidden');
            });

            // Submit Comment
            $(document).on('click', '.submit-comment', function () {
                var btn = $(this);
                var container = btn.closest('.multiboard-note');
                var noteId = container.data('note-id');
                var input = container.find('.comment-input');
                var content = input.val();

                if (!content) return;

                Ajax.call([{
                    methodname: 'mod_multiboard_add_comment',
                    args: {
                        noteid: noteId,
                        content: content
                    }
                }])[0].done(function (response) {
                    input.val('');
                    container.find('.comment-form').addClass('hidden');
                    that.renderComment(response, noteId);
                }).fail(Notification.exception);
            });

            // Delete Note
            $(document).on('click', '.delete-note', function () {
                if (!confirm('Are you sure you want to delete this note?')) return;

                var note = $(this).closest('.multiboard-note');
                var column = note.closest('.multiboard-column');
                var noteId = note.data('note-id');

                Ajax.call([{
                    methodname: 'mod_multiboard_delete_note',
                    args: {
                        noteid: noteId
                    }
                }])[0].done(function () {
                    note.remove();
                    // Show placeholder if column is empty
                    if (column.find('.multiboard-note').length === 0) {
                        column.find('.column-content').append('<div class="empty-placeholder">' + that.strings.clicktoadd + '</div>');
                    }
                }).fail(Notification.exception);
            });

            // Simple Drag and Drop (HTML5)
            var draggedItem = null;
            var sourceColumn = null;

            $(document).on('dragstart', '.multiboard-note', function (e) {
                draggedItem = $(this);
                sourceColumn = $(this).closest('.multiboard-column');
                e.originalEvent.dataTransfer.effectAllowed = 'move';
                e.originalEvent.dataTransfer.setData('text/html', this.innerHTML);
                $(this).addClass('dragging');
            });

            $(document).on('dragend', '.multiboard-note', function (e) {
                $(this).removeClass('dragging');
                draggedItem = null;
                sourceColumn = null;
            });

            $(document).on('dragover', '.multiboard-column', function (e) {
                e.preventDefault();
                e.originalEvent.dataTransfer.dropEffect = 'move';
                $(this).addClass('drag-over');
            });

            $(document).on('dragleave', '.multiboard-column', function (e) {
                $(this).removeClass('drag-over');
            });

            $(document).on('drop', '.multiboard-column', function (e) {
                e.preventDefault();
                $(this).removeClass('drag-over');

                if (draggedItem) {
                    var columnId = $(this).data('column-id');
                    var noteId = draggedItem.data('note-id');

                    // Remove placeholder from target column if exists
                    $(this).find('.empty-placeholder').remove();

                    // Move in DOM
                    $(this).find('.column-content').append(draggedItem);

                    // Show placeholder in source column if empty
                    if (sourceColumn && sourceColumn.find('.multiboard-note').length === 0) {
                        sourceColumn.find('.column-content').append('<div class="empty-placeholder">' + that.strings.clicktoadd + '</div>');
                    }

                    // Save position
                    Ajax.call([{
                        methodname: 'mod_multiboard_update_note_position',
                        args: {
                            noteid: noteId,
                            columnid: columnId,
                            position: 0 // We are just appending for now, not handling precise ordering in this MVP
                        }
                    }])[0].fail(Notification.exception);
                }
            });

            // Enable draggable on notes
            $('.multiboard-note').attr('draggable', true);
        },

        renderNote: function (note, columnId) {
            // Check if note already exists
            if ($(`.multiboard-note[data-note-id="${note.id}"]`).length > 0) {
                return;
            }

            // Remove placeholder if exists
            var column = $(`.multiboard-column[data-column-id="${columnId}"]`);
            column.find('.empty-placeholder').remove();

            var html = `
                <div class="multiboard-note color-${note.color}" data-note-id="${note.id}" draggable="true">
                    <div class="note-header">
                        <div class="user-info">
                            <span class="user-name">${note.userfullname}</span>
                        </div>
                        <div class="note-actions">
                            <button class="btn btn-sm btn-icon edit-note" title="Edit"><i class="fa fa-pencil"></i></button>
                            <button class="btn btn-sm btn-icon delete-note" title="Delete"><i class="fa fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="note-content">${note.content}</div>
                    <div class="note-footer">
                        <span class="time">Just now</span>
                        <button class="btn btn-sm btn-link reply-btn">Reply</button>
                    </div>
                    <div class="note-comments"></div>
                    <div class="comment-form hidden">
                        <textarea class="form-control comment-input" rows="2"></textarea>
                        <button class="btn btn-sm btn-primary submit-comment">Reply</button>
                    </div>
                </div>
            `;

            column.find('.column-content').append(html);
        },

        renderComment: function (comment, noteId) {
            // Check if comment already exists
            if ($(`.comment[data-comment-id="${comment.id}"]`).length > 0) {
                return;
            }

            var html = `
                <div class="comment" data-comment-id="${comment.id}">
                    <div class="comment-header">
                        <span class="user-name">${comment.userfullname}</span>
                        <span class="time">Just now</span>
                    </div>
                    <div class="comment-content">${comment.content}</div>
                </div>
            `;

            $(`.multiboard-note[data-note-id="${noteId}"] .note-comments`).append(html);
        },

        startPolling: function () {
            var that = this;
            this.timer = setInterval(function () {
                that.poll();
            }, 3000);
        },

        poll: function () {
            var that = this;
            Ajax.call([{
                methodname: 'mod_multiboard_get_updates',
                args: {
                    multiboardid: that.multiboardId,
                    since: that.lastUpdate
                }
            }])[0].done(function (response) {
                if (response.notes.length > 0 || response.comments.length > 0) {
                    that.lastUpdate = Math.floor(Date.now() / 1000);

                    // Process new notes
                    response.notes.forEach(function (note) {
                        if ($(`.multiboard-note[data-note-id="${note.id}"]`).length === 0) {
                            that.renderNote(note, note.columnid);
                        } else {
                            var noteEl = $(`.multiboard-note[data-note-id="${note.id}"]`);

                            // Update content and color
                            noteEl.find('.note-content').html(note.content);
                            noteEl.removeClass(function (index, className) {
                                return (className.match(/(^|\s)color-\S+/g) || []).join(' ');
                            }).addClass('color-' + note.color);

                            var currentColumn = noteEl.closest('.multiboard-column').data('column-id');
                            if (currentColumn != note.columnid) {
                                var el = noteEl.detach();
                                $(`.multiboard-column[data-column-id="${note.columnid}"] .column-content`).append(el);
                            }
                        }
                    });

                    // Process new comments
                    response.comments.forEach(function (comment) {
                        var noteEl = $(`.multiboard-note[data-note-id="${comment.noteid}"]`);
                        if (noteEl.length) {
                            that.renderComment(comment, comment.noteid);
                        }
                    });
                }
            }).fail(function () {
                // Silently fail on poll error
            });
        }
    };

    return Board;
});
