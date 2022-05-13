<?php

return [
    'cloudinary_add_files' => [
        'path' => '/cloudinary/add-files',
        'target' => \Visol\Cloudinary\Controller\CloudinaryAjaxController::class . '::addFilesAction',
    ],
];
