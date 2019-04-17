<?php
// DIC configuration

use Firebase\JWT\JWT;

$container = $app->getContainer();

// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};


// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};


// PDO database library
$container['db'] = function ($c) {
    $settings = $c->get('settings')['db'];
    $pdo = new PDO("mysql:host=" . $settings['host'] . ";dbname=" . $settings['dbname'], $settings['user'], $settings['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};


// create token
$container['createToken'] = function ($c) {

    return function ($user) use ($c) {

        $now = new DateTime();
        $future = new DateTime("+360 hours");

        $settings = $c->get('settings');


        $key = $settings['jwt']['secret']; //secret key

        $payload = array(
            'iat' => $now->getTimestamp(),
            'exp' => $future->getTimestamp(),
            'id' => $user->id,
            'email' => $user->email
        );

        $token = JWT::encode($payload, $key, "HS256");


        return $token;
    };

};


// validate token
$container['validateToken'] = function($c) {
    return function ($token) use ($c) {

        $settings = $c->get('settings');

        $key = $settings['jwt']['secret']; // secret key
        $decoded = JWT::decode($token, $key, array('HS256'));
        return $decoded;
    };
};


// move file upload to directory
$container['moveUploadedFile'] = function () {
    return function ($directory, $uploadedFile) {
        $extension = 'png';
        $basename = 'profilePicture';
        $filename = sprintf('%s.%0.8s', $basename, $extension);

        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return $filename;
    };
};
