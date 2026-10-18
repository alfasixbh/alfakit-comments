<?php

declare(strict_types=1);

return [
    'label' => 'Comments',
    'empty' => 'No comments yet.',
    'edited' => '(edited)',
    'unknown_author' => 'Deleted user',

    'editor' => [
        'placeholder' => 'Write a comment… Type @ to mention someone.',
    ],

    'actions' => [
        'post' => 'Add comment',
        'post_submit' => 'Comment',
        'edit' => 'Edit',
        'edit_heading' => 'Edit comment',
        'save' => 'Save',
        'delete' => 'Delete',
        'delete_heading' => 'Delete comment',
        'delete_description' => 'This comment and its reactions will be deleted. This cannot be undone.',
        'more' => 'Comment actions',
        'load_more' => 'Load more comments',
        'close' => 'Close',
    ],

    'columns' => [
        'avatar' => 'Avatar',
        'author' => 'Author',
        'date' => 'Posted',
        'body' => 'Comment',
    ],

    'reactions' => [
        'add' => 'Add reaction',
        'react' => 'React with :reaction',
    ],

    'notifications' => [
        'posted' => 'Comment added',
        'updated' => 'Comment updated',
        'deleted' => 'Comment deleted',
        'mentioned' => [
            'title' => ':name mentioned you in a comment',
            'view' => 'View',
        ],
    ],

    'validation' => [
        'blank' => 'Write something before posting.',
    ],
];
