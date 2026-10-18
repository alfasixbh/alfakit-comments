<?php

declare(strict_types=1);

return [
    'label' => 'Commenti',
    'empty' => 'Ancora nessun commento.',
    'edited' => '(modificato)',
    'unknown_author' => 'Utente eliminato',

    'editor' => [
        'placeholder' => 'Scrivi un commento… Digita @ per menzionare qualcuno.',
    ],

    'actions' => [
        'post' => 'Aggiungi commento',
        'post_submit' => 'Commenta',
        'edit' => 'Modifica',
        'edit_heading' => 'Modifica commento',
        'save' => 'Salva',
        'delete' => 'Elimina',
        'delete_heading' => 'Elimina commento',
        'delete_description' => 'Questo commento e le sue reazioni verranno eliminati. L’operazione non può essere annullata.',
        'more' => 'Azioni del commento',
        'load_more' => 'Carica altri commenti',
        'close' => 'Chiudi',
    ],

    'columns' => [
        'avatar' => 'Avatar',
        'author' => 'Autore',
        'date' => 'Pubblicato',
        'body' => 'Commento',
    ],

    'reactions' => [
        'add' => 'Aggiungi reazione',
        'react' => 'Reagisci con :reaction',
    ],

    'notifications' => [
        'posted' => 'Commento aggiunto',
        'updated' => 'Commento aggiornato',
        'deleted' => 'Commento eliminato',
        'mentioned' => [
            'title' => ':name ti ha menzionato in un commento',
            'view' => 'Visualizza',
        ],
    ],

    'validation' => [
        'blank' => 'Scrivi qualcosa prima di pubblicare.',
    ],
];
