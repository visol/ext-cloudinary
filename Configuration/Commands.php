<?php
return [
    'cloudinary:copy' => [
        'class' => \Visol\Cloudinary\Command\CloudinaryCopyCommand::class,
    ],
    'cloudinary:move' => [
        'class' => \Visol\Cloudinary\Command\CloudinaryMoveCommand::class,
    ],
    'cloudinary:run-tests' => [
        'class' => \Visol\Cloudinary\Command\CloudinaryAcceptanceTestCommand::class,
    ],
    'cloudinary:fix-jpeg' => [
        'class' => \Visol\Cloudinary\Command\CloudinaryFixJpegCommand::class,
    ],
];
