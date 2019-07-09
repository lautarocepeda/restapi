<?php
return [
    'settings' => [
        'displayErrorDetails' => false, // set to false in production
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
            "host" => 'ec2-174-129-209-212.compute-1.amazonaws.com',
            "dbname" => 'd6r16lib2pq33',
            "user" => 'xxntdscidsllmp',
            "pass" => 'b6f619f63260b9e504c9d8a565c43c9e69838a151d56521805a5154a48dd556a',
            "port" => "5432"
        ],
        // jwt settings
        "jwt" => [
            'secret' => '3d524a53c110e4c22463b10ed32cef9d'
        ],
        // directory uploads
        "upload_directory" => [
            'users' => '../src/users/',
            'benefits' => '../public/benefits/'
        ]
    ],
];
