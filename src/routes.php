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



/* Facebook Login */
$app->post('/fb_signin', function (Request $request, Response $response) use ($app) {

    $containers = $app->getContainer();

    $settings = $this->get('settings'); // get settings array.
    $directory = $settings['upload_directory']['users'];


    // functions
    $createToken = $containers['createToken'];
    $verifyFacebUserOnDB = $containers['verifyFacebUserOnDB'];
    $createFacebookUser = $containers['createFacebookUser'];

    // facebook Token
    $fb_client_token = $request->getParsedBody()['fb_token'];

    // initialize facebook sdk
    $fb = new Facebook\Facebook([
        'app_id' => '',
        'app_secret' => '',
        'default_graph_version' => 'v2.2',
    ]);


    if (! isset($fb_client_token)) {
        echo 'No OAuth data could be obtained from the signed request. User has not authorized your app yet.';
        exit;
    }


    try {
        $fbresponse = $fb->get('/me?fields=id,name,email,birthday,gender,picture.width(400).height(400)', $fb_client_token);

        $fbresponse = $fbresponse->getGraphUser();

        // facebook data user
        $fb_data_user = [
            'uid' => $fbresponse->getId(),
            'name' => $fbresponse->getName(),
            'gender' => $fbresponse->getGender(),
            'email' => $fbresponse->getEmail(),
            'birthday' => $fbresponse->getBirthday()->format('Y-m-d'),
            'urlImg' => $fbresponse->getPicture()->getUrl(),
            'role' => 'User', // default Role
            'provider' => 'facebook'
        ];


        $user_in_db = $verifyFacebUserOnDB($fb_data_user);

        if($user_in_db) {
            $token = $createToken($user_in_db);
        } else {
            // create new user
            $newUser_created = $createFacebookUser($fb_data_user);
            $userId = $newUser_created->id;


            // dir to upload the user image
            $directory = $directory . $userId . '/';

            if (!is_dir($directory)) {
                mkdir($directory, 0700); //create directory with user id
            }

            $new = $directory . 'profilePicture.jpg';
            $image = file_get_contents($fb_data_user['urlImg']);
            file_put_contents($new, $image);


            // create new user token
            $token = $createToken($newUser_created);
        }


        return $this->response->withJson([
            'token' => $token,
            'user' => $fb_data_user['email'],
            'role' => $fb_data_user['role']
        ], 200);

    } catch (PDOException $e) {
        return $this->response->withJson([
            'error' => true,
            'user_created' => false,
            'status' => $e->getCode(),
            'message' => $e->getMessage()
        ], 400);
    } catch(Facebook\Exceptions\FacebookResponseException $e) {
        // When Graph returns an error
        echo 'Graph returned an error: ' . $e->getMessage();
        exit;
    } catch(Facebook\Exceptions\FacebookSDKException $e) {
        // When validation fails or other local issues
        echo 'Facebook SDK returned an error: ' . $e->getMessage();
        exit;
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
        ], 200);

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

        $settings = $this->get('settings'); // get settings array.
        $directory = $settings['upload_directory']['users'];


        $userId = $request->getAttribute('decoded_token_data')['id'];

        $imgProfile = file_get_contents($directory . $userId . '/profilePicture.jpg');
        $imgBase64 = base64_encode($imgProfile);


        $sql = "SELECT id, name, oauth_provider, email, birthday, role, gender, created_at
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



    $app->get('/users', function(Request $request, Response $response, array $args) {


        $userId = $request->getAttribute('decoded_token_data')['id'];


        try {

            $sql = "SELECT role FROM users WHERE id = :id";

            $sth = $this->db->prepare($sql);

            $sth->bindParam('id', $userId);

            $sth->execute();

            $user_role = $sth->fetch()['role'];

            //verify role
            if ($user_role === 'Admin') {

                $sql = "SELECT id, name, email, gender, oauth_provider FROM users ORDER BY created_at DESC";

                $sth = $this->db->prepare($sql);

                $sth->execute();

                $users = $sth->fetchAll();


                if ($users) {
                    return $response->withJson($users, 200);
                } else {
                    throw new PDOException('No hay usuarios.');
                }

            } else {
                throw new PDOException('No tienes permisos para realizar esta acción.', 401);
            }


        } catch (PDOException $e) {

            return $this->response->withJson([
                'error' => true,
                'status' => $e->getCode(),
                'message' => $e->getMessage()
            ], 400);

        }


    });




    /* Update User Profile */
    $app->put('/profile/update', function(Request $request, Response $respose) use ($app) {

        $dataUser = $request->getParsedBody();
        $container = $app->getContainer();
        $validateToken = $container['validateToken'];


        if(strcmp($dataUser['email'], '') === 0) {
            return $this->response->withJson([
                'message' => 'invalid email.'
            ], 400); //bad request change
        }


        $token = $request->getHeader('authorization')[0];
        $token = explode(' ', $token)[1];


        try {

            $decoded = $validateToken($token);

            $userId = $decoded->id;


            if (strcmp($dataUser['password'], '') === 0) {


                $sql = "UPDATE users 
                    SET `name` = :name, gender = :gender, email = :email, birthday = :birthday, updated_at = :dateNow
                    WHERE id = :id" ;

                $sth = $this->db->prepare($sql);

            } else {


                $sql = "UPDATE users 
                    SET `name` = :name, gender = :gender, email = :email, birthday = :birthday, encript_pw = :encriptPw, updated_at = :dateNow
                    WHERE id = :id " ;

                $sth = $this->db->prepare($sql);

                $sth->bindParam("encriptPw", password_hash($dataUser['password'], PASSWORD_BCRYPT));


            }

            $sth->bindParam("name", $dataUser['name']);
            $sth->bindParam("gender", $dataUser['gender']);
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



    /* Upload Profile Image File */
    $app->post('/profile/img/upload', function(Request $request, Response $response) use ($app) {

        $container = $app->getContainer();
        $settings = $this->get('settings'); // get settings array.
        $token = $request->getParsedBody()['token'];

        $allowed_extension = array('image/png', 'image/jpg', 'image/jpeg');

        // function containers
        $moveUploadedFile = $container['moveUploadedFile'];
        $validateToken = $container['validateToken'];

        // Get dir to upload files in server
        $directory = $settings['upload_directory']['users'];

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
                    $nameFile = 'profilePicture';
                    $filename = $moveUploadedFile($directory, $file, $nameFile);

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



    /* New Benefit */
    $app->post('/benefits/create', function(Request $request, Response $response) use ($app) {

        $clientData = $request->getParsedBody();

        $containers = $app->getContainer();
        // function
        $saveBenefitImg = $containers['saveBenefitImg'];


        try {
            $sql = "INSERT INTO benefit (title, description, price) 
                    VALUES (:title, :description, :price)";

            $sth = $this->db->prepare($sql);


            $sth->bindParam('title', $clientData['title']);
            $sth->bindParam('description', $clientData['description']);
            $sth->bindParam('price', $clientData['price']);

            $sth->execute();
            $last_id = $this->db->lastInsertId();


            $savedImgData = $saveBenefitImg($clientData['baseImg'], $last_id);
            $path = $savedImgData['path'];


            $sql = "UPDATE benefit SET imgPath = :path WHERE id = :lastId";

            $sth = $this->db->prepare($sql);

            $sth->bindParam('path', $path);
            $sth->bindParam('lastId', $last_id);

            $sth->execute();


            return $this->response->withJson([
                'message' => 'benefit added success'
            ], 200);

        } catch (PDOException $e) {

            return $this->response->withJson([
                'error' => true,
                'status' => $e->getCode(),
                'message' => $e->getMessage()
            ], 400);

        }

    });


    /* Get All Benefits */
    $app->get('/benefits', function(Request $request, Response $response) use ($app) {

        $userId = $request->getAttribute('decoded_token_data')['id'];
        $benefits = [];


        try{
            $sql = "SELECT role FROM users WHERE id = :id";

            $sth = $this->db->prepare($sql);

            $sth->bindParam('id', $userId);

            $sth->execute();

            $user_role = $sth->fetch()['role'];

            //verify role
            if($user_role === 'Admin' || $user_role === 'User') {
                $sql = "SELECT * FROM benefit 
                    ORDER BY id DESC";

                $sth = $this->db->prepare($sql);

                $sth->execute();
                $benefits = $sth->fetchAll();


                if ($benefits) {
                    return $response->withJson($benefits, 200);
                } else {
                    throw new PDOException('No hay beneficios.');
                }

            } else {
                throw new PDOException('No tienes permisos para realizar esta acción.', 401);
            }


        } catch(PDOException $e) {
            return $this->response->withJson([
                'error' => true,
                'status' => $e->getCode(),
                'message' => $e->getMessage()
            ], 400);
        }

    });


    $app->get('/benefit/verify', function(Request $request, Response $response) use ($app) {

        $userId = $request->getAttribute('decoded_token_data')['id'];
        $codeBenefit = trim($request->getParam("code"));

        $fechaActual =  strtotime(date("Y-m-d"));


        try{
            $sql = "SELECT role FROM users WHERE id = :id";

            $sth = $this->db->prepare($sql);

            $sth->bindParam('id', $userId);

            $sth->execute();

            $user_role = $sth->fetch()['role'];

            //verify role
            if ($user_role === 'Admin') {
                $sql = "SELECT benefit_id, expiration, code FROM users_benefit WHERE code = :codeBenefit";

                $sth = $this->db->prepare($sql);

                $sth->bindParam('codeBenefit', $codeBenefit);

                $sth->execute();
                $benefit = $sth->fetch();

                $benefitExpirate = strtotime($benefit['expiration']);


                if ($benefit) {
                    //validate expiration date
                    if ($fechaActual > $benefitExpirate) {
                        throw new Exception('El beneficio ya caduco...');
                    } else {
                        return $response->withJson($benefit, 200);
                    }

                } else {
                    throw new Exception('No existe beneficios asociado a ese codigo.');
                }


            } else {
                throw new Exception('No tienes permisos para realizar esta acción.', 401);
            }


        } catch(Exception $e) {
            return $this->response->withJson([
                'error' => true,
                'status' => $e->getCode(),
                'message' => $e->getMessage()
            ], 400);
        }

    });


    $app->get('/benefit/{id}', function (Request $request, Response $response) {

        $route = $request->getAttribute('route');
        $benefitId = $route->getArgument('id');


        try {
            $sql = "SELECT * from benefit WHERE id = :id";

            $sth = $this->db->prepare($sql);

            $sth->bindParam('id', $benefitId);

            $sth->execute();

            $benefit = $sth->fetch();

            if ($benefit) {

                return $response->withJson($benefit, 200);

            } else {
                throw new PDOException('No existe beneficio con ese identificador.');
            }


        } catch(PDOException $e) {
            return $this->response->withJson([
                'error' => true,
                'status' => $e->getCode(),
                'message' => $e->getMessage()
            ], 400);
        }

    });



    /* Get Users Coupons */
    $app->get('/coupons', function(Request $request, Response $response) use ($app) {

        $userId = $request->getAttribute('decoded_token_data')['id'];
        $result = [];


        try{
            $sql = "SELECT * FROM users_benefit WHERE user_id = :id ORDER BY id DESC";

            $sth = $this->db->prepare($sql);

            $sth->bindParam('id', $userId);

            $sth->execute();

            $coupons = $sth->fetchAll();


            if ($coupons) {

                foreach ($coupons as $coupon) {
                    $coupon['qrImg'] = "data:image/png;base64, " . base64_encode($coupon['qrImg']);
                    array_push($result, $coupon);
                }

                return $response->withJson($result, 200);
            } else {
                throw new PDOException('No tienes ningún cupón canjeado!');
            }

        } catch(PDOException $e) {
            return $this->response->withJson([
                'error' => true,
                'status' => $e->getCode(),
                'message' => $e->getMessage()
            ], 400);
        }

    });


    /* Exchange a benefit */
    $app->post('/benefits/exchange', function(Request $request, Response $response) use ($app) {

        $container = $app->getContainer();

        // data
        $benefitId = $request->getParsedBody()['benefit_id'];
        $userId = $request->getAttribute('decoded_token_data')['id'];

        $generateQr = $container['generateQr'];
        $generateUniqueId = $container['generateUniqueId'];


        // 14 days to benefit expirate
        $date = date('Y-m-d');
        $newDate = strtotime ( '+14 day' , strtotime ( $date ) ) ;
        $expiration = date ( 'Y-m-d' , $newDate );


        $codeBenefit = $generateUniqueId();
        $qrCode = $generateQr($codeBenefit);

        try {

            $sql = "INSERT INTO users_benefit (user_id, benefit_id, expiration, qrImg, code)
                    VALUES (:userid, :benefitid, :expiration, :qr, :code)";


            $sth = $this->db->prepare($sql);

            $sth->bindParam('userid', $userId);
            $sth->bindParam('benefitid', $benefitId);
            $sth->bindParam('expiration',  $expiration);
            $sth->bindParam('qr', $qrCode);
            $sth->bindParam('code', $codeBenefit);

            $sth->execute();


            return $this->response->withJson([
                'code' => $codeBenefit,
                'BaseQrCode' => base64_encode($qrCode),
                'message' => "success exchange"
            ]);

        } catch(Exception $e) {
            return $this->response->withJson([
                'error' => true,
                'status' => $e->getCode(),
                'message' => $e->getMessage()
            ], 400);
        }
    });


});


