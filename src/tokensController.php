<?php

use \Firebase\JWT\JWT;


function validateToken($token) {

    $settings = $this->get('settings');


    $key = $settings['jwt']['secret']; // secret key

    $decoded = JWT::decode($token, $key, array('HS256'));


    return $decoded;
}


function createToken($user) {

    $now = new DateTime();
    $future = new DateTime("+1 minutes");

    $settings = $this->get('settings');


    $key = $settings['jwt']['secret']; //secret key

    //data user
    $payload = array(
        'iat' => $now->getTimestamp(),
        'exp' => $future->getTimestamp(),
        'id' => $user->id,
        'email' => $user->email
    );

    $token = JWT::encode($payload, $key, "HS256");


    return $token;
}