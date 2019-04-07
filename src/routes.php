<?php

use Slim\Http\Request;
use Slim\Http\Response;
use \Firebase\JWT\JWT;

// Public Routes
$app->post('/auth/signin', function (Request $request, Response $response, array $args) use ($app) {
    
    $dataClient = $request->getParsedBody();

    $container = $app->getContainer();
    $createToken = $container['createToken'];


    try {

        $sql = "SELECT * 
            FROM users 
            WHERE email = :email";

        $sth = $this->db->prepare($sql);
        $sth->bindParam("email", $dataClient['email']);
        $sth->execute();
        $user = $sth->fetchObject();

        // verify email address.
        if(!$user) {
            return $this->response->withJson([
                'error' => true,
                'message' => 'incorrect user.',
                'type' => 'invalidUser'
            ], 406);
        }


        // verify password.
        if (!password_verify($dataClient['password'], $user->encript_pw)) {
            return $this->response->withJson([
                'error' => true,
                'message' => 'incorrect password.',
                'type' => 'invalidPassword'
            ], 406);
        }



        $token = $createToken($user);

        return $this->response->withJson([
            'token' => $token,
            'user' => $user->email
        ], 200);

    } catch (PDOException $e) {

        return $this->response->withJson([
            'error' => true,
            'status' => $e->getCode(),
            'message' => $e->getMessage()
        ], 401);
    }
});



$app->post('/auth/signup', function (Request $request, Response $response, array $args) {

    $dataClient = $request->getParsedBody();

    try {

        $sql = "INSERT INTO users (`name`, email, encript_pw, created_at)
                VALUES (:name, :email, :pw, :createdAt)";

        $sth = $this->db->prepare($sql);

        $sth->bindParam("name", $dataClient['name']);
        $sth->bindParam("email", $dataClient['email']);
        $sth->bindParam("pw", password_hash($dataClient['confirmPassword'], PASSWORD_BCRYPT) );
        $sth->bindParam("createdAt", date("Y-m-d H:i:s"));

        $user = $sth->execute();

        return $this->response->withJson([
            'error' => false,
            'user_created' => true,
            'status' => $response->getStatusCode(),
            'message' => "User created."
        ], 201);

    } catch(PDOException $e) {

        return $this->response->withJson([
            'error' => true,
            'status' => $e->getCode(),
            'message' => $e->getMessage()
        ]);
    }
});


$app->post('/validate_token', function (Request $request, Response $response) use ($app) {

    $tokenClient = $request->getParsedBody()['token'];

    $container = $app->getContainer();
    $validateToken = $container['validateToken'];


    try {

        $decoded = $validateToken($tokenClient);

        return $this->response->withJson($decoded, 200);

    } catch (Exception $e) {

        return $this->response->withJson([
            'error' => true,
            'status' => $e->getCode(),
            'message' => $e->getMessage()
        ], 401);
    }
});




// Private Routes
$app->group('/api', function(\Slim\App $app) {

    /* Profile Data User */
    $app->get('/profile',function(Request $request, Response $response, array $args) {

        $userId = $request->getAttribute('decoded_token_data')['id'];


        $sql = "SELECT id, `name`, email, created_at
                FROM users
                WHERE id = :id";

        $sth = $this->db->prepare($sql);

        $sth->bindParam("id", $userId);

        $sth->execute();

        $user = $sth->fetchObject();

        return $this->response->withJson($user);
    });


    /* Upload File */
    $app->post('/upload', function(Request $request, Response $response) use ($app) {

        $container = $app->getContainer();

        $moveUploadedFile = $container['moveUploadedFile'];


        $settings = $this->get('settings'); // get settings array.
        $directory = $settings['upload_directory']['dir'];

        $file = $request->getUploadedFiles()['img'];


        if ($file->getError() === UPLOAD_ERR_OK) {
            $filename = $moveUploadedFile($directory, $file);
            $response->write('uploaded ' . $filename);
        }
    });


});


