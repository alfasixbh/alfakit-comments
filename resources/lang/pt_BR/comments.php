<?php

declare(strict_types=1);

return [
    'label' => 'Comentários',
    'empty' => 'Nenhum comentário ainda.',
    'edited' => '(editado)',
    'unknown_author' => 'Usuário excluído',

    'editor' => [
        'placeholder' => 'Escreva um comentário… Digite @ para mencionar alguém.',
    ],

    'actions' => [
        'post' => 'Adicionar comentário',
        'post_submit' => 'Comentar',
        'edit' => 'Editar',
        'edit_heading' => 'Editar comentário',
        'save' => 'Salvar',
        'delete' => 'Excluir',
        'delete_heading' => 'Excluir comentário',
        'delete_description' => 'Este comentário e suas reações serão excluídos. Esta ação não pode ser desfeita.',
        'more' => 'Ações do comentário',
        'load_more' => 'Carregar mais comentários',
        'close' => 'Fechar',
    ],

    'columns' => [
        'avatar' => 'Avatar',
        'author' => 'Autor',
        'date' => 'Publicado',
        'body' => 'Comentário',
    ],

    'reactions' => [
        'add' => 'Adicionar reação',
        'react' => 'Reagir com :reaction',
    ],

    'notifications' => [
        'posted' => 'Comentário adicionado',
        'updated' => 'Comentário atualizado',
        'deleted' => 'Comentário excluído',
        'mentioned' => [
            'title' => ':name mencionou você em um comentário',
            'view' => 'Visualizar',
        ],
    ],

    'validation' => [
        'blank' => 'Escreva algo antes de publicar.',
    ],
];
