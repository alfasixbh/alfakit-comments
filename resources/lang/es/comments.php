<?php

declare(strict_types=1);

return [
    'label' => 'Comentarios',
    'empty' => 'Aún no hay comentarios.',
    'edited' => '(editado)',
    'unknown_author' => 'Usuario eliminado',

    'editor' => [
        'placeholder' => 'Escriba un comentario… Escriba @ para mencionar a alguien.',
    ],

    'actions' => [
        'post' => 'Añadir comentario',
        'post_submit' => 'Comentar',
        'edit' => 'Editar',
        'edit_heading' => 'Editar comentario',
        'save' => 'Guardar',
        'delete' => 'Eliminar',
        'delete_heading' => 'Eliminar comentario',
        'delete_description' => 'Se eliminarán este comentario y sus reacciones. Esta acción no se puede deshacer.',
        'more' => 'Acciones del comentario',
        'load_more' => 'Cargar más comentarios',
        'close' => 'Cerrar',
    ],

    'columns' => [
        'avatar' => 'Avatar',
        'author' => 'Autor',
        'date' => 'Publicado',
        'body' => 'Comentario',
    ],

    'reactions' => [
        'add' => 'Añadir reacción',
        'react' => 'Reaccionar con :reaction',
    ],

    'notifications' => [
        'posted' => 'Comentario añadido',
        'updated' => 'Comentario actualizado',
        'deleted' => 'Comentario eliminado',
        'mentioned' => [
            'title' => ':name le mencionó en un comentario',
            'view' => 'Ver',
        ],
    ],

    'validation' => [
        'blank' => 'Escriba algo antes de publicar.',
    ],
];
