<?php
return [
    'cloudinary:copy' => [
        'class' => \Sinso\Cloudinary\Command\CloudinaryCopyCommand::class,
    ],
    'cloudinary:move' => [
        'class' => \Sinso\Cloudinary\Command\CloudinaryMoveCommand::class,
    ],
];
