<?php

declare(strict_types=1);

return [
    'label' => 'コメント',
    'empty' => 'コメントはまだありません。',
    'edited' => '(編集済み)',
    'unknown_author' => '削除されたユーザー',

    'editor' => [
        'placeholder' => 'コメントを入力… @ でメンションできます。',
    ],

    'actions' => [
        'post' => 'コメントを追加',
        'post_submit' => 'コメントする',
        'edit' => '編集',
        'edit_heading' => 'コメントを編集',
        'save' => '保存',
        'delete' => '削除',
        'delete_heading' => 'コメントを削除',
        'delete_description' => 'このコメントとそのリアクションが削除されます。この操作は元に戻せません。',
        'more' => 'コメントの操作',
        'load_more' => 'さらにコメントを読み込む',
        'close' => '閉じる',
    ],

    'columns' => [
        'avatar' => 'アバター',
        'author' => '投稿者',
        'date' => '投稿日時',
        'body' => 'コメント',
    ],

    'reactions' => [
        'add' => 'リアクションを追加',
        'react' => ':reaction でリアクション',
    ],

    'notifications' => [
        'posted' => 'コメントを追加しました',
        'updated' => 'コメントを更新しました',
        'deleted' => 'コメントを削除しました',
        'mentioned' => [
            'title' => ':name さんがコメントであなたをメンションしました',
            'view' => '表示',
        ],
    ],

    'validation' => [
        'blank' => '投稿する前に内容を入力してください。',
    ],
];
