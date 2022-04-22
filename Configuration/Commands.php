<?php

use Visol\Cloudinary\Command\CloudinaryCopyCommand;
use Visol\Cloudinary\Command\CloudinaryMoveCommand;
use Visol\Cloudinary\Command\CloudinaryAcceptanceTestCommand;
use Visol\Cloudinary\Command\CloudinaryFixJpegCommand;
use Visol\Cloudinary\Command\CloudinaryScanCommand;
use Visol\Cloudinary\Command\CloudinaryQueryCommand;
return [
    'cloudinary:copy' => [
        'class' => CloudinaryCopyCommand::class,
    ],
    'cloudinary:move' => [
        'class' => CloudinaryMoveCommand::class,
    ],
    'cloudinary:tests' => [
        'class' => CloudinaryAcceptanceTestCommand::class,
    ],
    'cloudinary:fix' => [
        'class' => CloudinaryFixJpegCommand::class,
    ],
    'cloudinary:scan' => [
        'class' => CloudinaryScanCommand::class,
    ],
    'cloudinary:query' => [
        'class' => CloudinaryQueryCommand::class,
    ],
];
