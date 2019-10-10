<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Cloudinary',
    'description' => 'Cloudinary integration in TYPO3. Use automatic breakpoint generation for images.',
    'category' => 'service',
    'version' => '1.0.0',
    'state' => 'stable',
    'author' => 'Jonas Renggli',
    'author_email' => 'jonas.renggli@visol.ch',
    'author_company' => 'visol - digitale Dienstleistungen GmbH',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-8.7.99',
            'php' => '7.0.0-0.0.0',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
