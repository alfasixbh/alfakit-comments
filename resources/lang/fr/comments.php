<?php

declare(strict_types=1);

return [
    'label' => 'Commentaires',
    'empty' => 'Aucun commentaire pour le moment.',
    'edited' => '(modifié)',
    'unknown_author' => 'Utilisateur supprimé',

    'editor' => [
        'placeholder' => 'Écrivez un commentaire… Tapez @ pour mentionner quelqu’un.',
    ],

    'actions' => [
        'post' => 'Ajouter un commentaire',
        'post_submit' => 'Commenter',
        'edit' => 'Modifier',
        'edit_heading' => 'Modifier le commentaire',
        'save' => 'Enregistrer',
        'delete' => 'Supprimer',
        'delete_heading' => 'Supprimer le commentaire',
        'delete_description' => 'Ce commentaire et ses réactions seront supprimés. Cette action est irréversible.',
        'more' => 'Actions du commentaire',
        'load_more' => 'Charger plus de commentaires',
        'close' => 'Fermer',
    ],

    'columns' => [
        'avatar' => 'Avatar',
        'author' => 'Auteur',
        'date' => 'Publié',
        'body' => 'Commentaire',
    ],

    'reactions' => [
        'add' => 'Ajouter une réaction',
        'react' => 'Réagir avec :reaction',
    ],

    'notifications' => [
        'posted' => 'Commentaire ajouté',
        'updated' => 'Commentaire mis à jour',
        'deleted' => 'Commentaire supprimé',
        'mentioned' => [
            'title' => ':name vous a mentionné dans un commentaire',
            'view' => 'Afficher',
        ],
    ],

    'validation' => [
        'blank' => 'Écrivez quelque chose avant de publier.',
    ],
];
