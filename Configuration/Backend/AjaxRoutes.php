<?php

use Visol\Cloudinary\Controller\CloudinaryAjaxController;

return [
    'cloudinary_add_files' => [
        'path' => '/cloudinary/add-files',
        'target' => CloudinaryAjaxController::class . '::addFilesAction',
    ],
];
