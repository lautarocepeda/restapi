<?php
return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
        // database connection details
        "db" => [
            "host" => '127.0.0.1',
            "dbname" => 'restapi-slim',
            "user" => 'root',
            "pass" => ''
        ],
        // jwt settings
        "jwt" => [
            'secret' => '3d524a53c110e4c22463b10ed32cef9d'
        ],
        // directory uploads
        "upload_directory" => [
            'dir' => '../profilePictures/'
        ]
    ],
];
