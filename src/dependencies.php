<?php
// DIC configuration

use Firebase\JWT\JWT;
use Endroid\QrCode\QrCode;


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
    $pdo = new PDO("mysql:host=" . $settings['host'] . ";dbname=" . $settings['dbname']. ";charset=utf8", $settings['user'], $settings['pass']);
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


// move img profile upload to directory
$container['moveUploadedFile'] = function () {
    return function ($directory, $uploadedFile, $basename) {
        $extension = 'jpg';
        $filename = sprintf('%s.%0.8s', $basename, $extension);

        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return $filename;
    };
};


// save benefit img in directory
$container['saveBenefitImg'] = function ($c) {
    return function ($imgBase64, $idBenefit) use ($c) {
        $settings = $c->get('settings');
        $directory = $settings['upload_directory']['benefits'];

        $data = base64_decode(explode(',', $imgBase64)[1]);

        $folderName = $idBenefit;


        // create folder to content an unique benefit.
        mkdir($directory . DIRECTORY_SEPARATOR . $idBenefit);


        $filename = '/benefit.jpg';

        $filepath = $directory . $folderName .  $filename;


        file_put_contents($filepath, $data);

        return [
            'path' => $folderName . $filename,
            'folder' => $folderName,
            'img' => $filename
        ];
    };
};


// generate code qr
$container['generateQr'] = function ($c) {
    return function ($data) use ($c) {

        $qrCode = new QrCode(json_encode($data));

        return $qrCode->writeString();
    };
};


// generate unique id to benefits codes
$container['generateUniqueId'] = function () {
  return function () {

      //Hacelo bien hdp

      $code = strtoupper(uniqid());

      $dig = substr($code, 0, 4);
      $dig2 = substr($code, 4, 4);
      $dig3 = substr($code, 8, 4);

      $code = $dig . '-' . $dig2 . '-' . $dig3;

      return $code;
  };

};


// verfy if user exist in database
$container['verifyFacebUserOnDB'] = function ($c) {
    return function ($fb_user) use ($c) {

        $provider = 'facebook';

        $sql = "SELECT * FROM users WHERE oauth_uid = :user_uid AND oauth_provider = :provider";

        $sth = $c->db->prepare($sql);

        $sth->bindParam('user_uid', $fb_user['uid']);
        $sth->bindParam('provider', $provider);

        $sth->execute();
        $userDb = $sth->fetchObject();

        // if user not exist.
        if(!$userDb) {
            return false;
        }

        return $userDb;
    };
};


// create new user from facebook data
$container['createFacebookUser'] = function ($c) {
    return function ($fb_user) use ($c) {

        $provider = 'facebook';
        $createdAt = date("Y-m-d H:i:s");

        try {
            $sql = "INSERT INTO users (oauth_provider, oauth_uid, name, gender, email, birthday, created_at)
                VALUES (:provider, :uid, :name, :gender, :email, :birthday, :createdAt)";

            $sth = $c->db->prepare($sql);


            $sth->bindParam('provider', $fb_user['provider']);
            $sth->bindParam('uid', $fb_user['uid']);
            $sth->bindParam("name", $fb_user ['name']);
            $sth->bindParam("gender", $fb_user['gender']);
            $sth->bindParam("email", $fb_user ['email']);
            $sth->bindParam("birthday",  $fb_user['birthday']);
            $sth->bindParam("createdAt", $createdAt);

            $sth->execute();

            $id_user = $c->db->lastInsertId();


            $user_to_token = (object) array (
                'id' => $id_user,
                'email' => $fb_user['email']
            );


        } catch (PDOException $e) {
            throw new PDOException(($e->getMessage()), 400);
        }


        return $user_to_token;
    };
};

