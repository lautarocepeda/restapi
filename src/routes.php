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
            'user' => $user->email,
            'role' => $user->role
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

        $imgProfile = file_get_contents('../src/profilePictures/' . $userId . '/profilePicture.png');
        $imgBase64 = base64_encode($imgProfile);


        $sql = "SELECT id, `name`, email, birthday, role, created_at
                FROM users
                WHERE id = :id";

        $sth = $this->db->prepare($sql);

        $sth->bindParam("id", $userId);

        $sth->execute();

        $user = $sth->fetchObject();

        return $this->response->withJson([
            'user' => $user,
            'baseImg' => $imgBase64
        ]);
    });


    /* Upload Image File */
    $app->post('/upload', function(Request $request, Response $response) use ($app) {

        $container = $app->getContainer();
        $settings = $this->get('settings'); // get settings array.
        $token = $request->getParsedBody()['token'];

        $allowed_extension = array('image/png', 'image/jpg', 'image/jpeg');

        // function containers
        $moveUploadedFile = $container['moveUploadedFile'];
        $validateToken = $container['validateToken'];

        // Get dir to upload files in server
        $directory = $settings['upload_directory']['dir'];

        // user id from token
        $userId = $validateToken($token)->id;

        // dir to upload the user file
        $directory = $directory . $userId . '/';


        // user file uploaded
        $file = $request->getUploadedFiles()['imageInput'];

        $fileSize = $file->getSize();


        // imagen file extension
        $file_extesion = $file->getClientMediaType();

        try {

            if(empty($file)) {
                throw new RuntimeException('Image file is empty');
            } else if($fileSize > 2000000) {
                throw new RuntimeException('File is too large. Max 2 MB');
            } else if (!in_array($file_extesion, $allowed_extension)) {
                throw new RuntimeException('Invalid image format. Only png, jpg, jpeg');
            } else {

                if (!is_dir($directory)) {
                    mkdir($directory, 0700); //create directory with user id
                }

                if ($file->getError() === UPLOAD_ERR_OK) {
                    $filename = $moveUploadedFile($directory, $file);

                    return $this->response->withJson([
                        'uploaded' => $filename
                    ]);
                }

            }

        } catch (RuntimeException $e) {
            return $this->response->withJson([
                'error' => true,
                'status' => $e->getCode(),
                'message' => $e->getMessage(),
                'size' => $fileSize
            ], 406);
        }

    });



    $app->get('/imgProfile', function(Request $request, Response $response) {

        $imgProfile = file_get_contents('../src/profilePictures/18/profilePicture.png');

        $data = base64_encode($imgProfile);

        return $response->write($data);
    });



    // update data user
    $app->post('/updateProfile', function(Request $request, Response $respose) use ($app) {

        $dataUser = $request->getParsedBody();
        $container = $app->getContainer();
        $validateToken = $container['validateToken'];


        if(strcmp($dataUser['email'], '') === 0) {
            return $this->response->withJson([
                'message' => 'invalid email.'
            ], 402); //bad request change
        }


        $token = $request->getHeader('authorization')[0];
        $token = explode(' ', $token)[1];


        try {

            $decoded = $validateToken($token);

            $userId = $decoded->id;


            if (strcmp($dataUser['password'], '') === 0) {


                $sql = "UPDATE users 
                    SET `name` = :name, email = :email, birthday = :birthday, updated_at = :dateNow
                    WHERE id = :id" ;

                $sth = $this->db->prepare($sql);

            } else {


                $sql = "UPDATE users 
                    SET `name` = :name, email = :email, birthday = :birthday, encript_pw = :encriptPw, updated_at = :dateNow
                    WHERE id = :id " ;

                $sth = $this->db->prepare($sql);

                $sth->bindParam("encriptPw", password_hash($dataUser['password'], PASSWORD_BCRYPT));


            }

                $sth->bindParam("name", $dataUser['name']);
                $sth->bindParam("email", $dataUser['email']);
                $sth->bindParam("birthday", $dataUser['birthday']);
                $sth->bindParam("dateNow", date('Y-m-d H:i:s'));
                $sth->bindParam("id", $userId);

                $sth->execute();


            return $this->response->withJson([
                'message' => 'user updated success',
                'data sended' => $dataUser
            ], 200);

        } catch (PDOException $e) {

            return $this->response->withJson([
                'error' => true,
                'status' => $e->getCode(),
                'message' => $e->getMessage()

            ], 406);

        }

    });


});


