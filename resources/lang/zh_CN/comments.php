<?php

declare(strict_types=1);

return [
    'label' => '评论',
    'empty' => '暂无评论。',
    'edited' => '（已编辑）',
    'unknown_author' => '已删除的用户',

    'editor' => [
        'placeholder' => '写下评论… 输入 @ 可提及他人。',
    ],

    'actions' => [
        'post' => '添加评论',
        'post_submit' => '评论',
        'edit' => '编辑',
        'edit_heading' => '编辑评论',
        'save' => '保存',
        'delete' => '删除',
        'delete_heading' => '删除评论',
        'delete_description' => '此评论及其回应将被删除。此操作无法撤销。',
        'more' => '评论操作',
        'load_more' => '加载更多评论',
        'close' => '关闭',
    ],

    'columns' => [
        'avatar' => '头像',
        'author' => '作者',
        'date' => '发布时间',
        'body' => '评论',
    ],

    'reactions' => [
        'add' => '添加回应',
        'react' => '用 :reaction 回应',
    ],

    'notifications' => [
        'posted' => '评论已添加',
        'updated' => '评论已更新',
        'deleted' => '评论已删除',
        'mentioned' => [
            'title' => ':name 在评论中提到了您',
            'view' => '查看',
        ],
    ],

    'validation' => [
        'blank' => '发布前请先输入内容。',
    ],
];
