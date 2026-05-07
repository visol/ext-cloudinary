<?php

return [
    'dependencies' => [
        'backend',
        'core',
    ],
    'tags' => [
        'backend.form',
    ],
    'imports' => [
        '@visol/cloudinary/cloudinary-media-library.js' => 'EXT:cloudinary/Resources/Public/JavaScript/CloudinaryMediaLibrary.js',
    ],
];
