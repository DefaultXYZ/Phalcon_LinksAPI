<?php

    use Phalcon\Loader;
    use Phalcon\Mvc\Micro;
    use Phalcon\Http\Response;
    use Phalcon\Di\FactoryDefault;
    use Phalcon\Db\Adapter\Pdo\Mysql as PdoMysql;
    use Phalcon\Logger;
    use Phalcon\Logger\Adapter\File as FileAdapter;
    
    $loader = new Loader();
    $loader->registerDirs(
        array(
            __DIR__ . '/models/'
        )
    )->register();
    
    $di = new FactoryDefault();
    $di->set('db', function() {
        return new PdoMysql(
            array(
                "host"      =>  "localhost",
                "username"  =>  "root",
                "password"  =>  "1",
                "dbname"    =>  "db_links"
            )
        );
    });
    
    $STATUSES = array(
        200 => "OK",
        201 => "Created",
        409 => "Conflict"
    );

    $app = new Micro($di);

    $logger = new FileAdapter("logs/test.log");
    
    $app->get('/hello', function() use ($app, $logger) {
        $data = array('result' => 'Hello#_#World');
        $logger->info('Hello');
        return getResponse($data, 200);
    });

    /*
    LINKS
    */
    
    $app->get('/links/getLinks/{username}', function ($username) use ($app) {
        $phql = "SELECT id, link, sourceName, visibility, position FROM links 
            WHERE username = '$username'";       
        $links = $app->modelsManager->executeQuery($phql);
        $data = array();
        foreach ($links as $link) {
            array_push($data, array(
                'id' => $link->id,
                'link' => $link->link,
                'sourceName' => $link->sourceName,
                'visibility' => $link->visibility,
                'position' => $link->position
            ));
        }
        return getResponse($data, 200);
    });
    
    $app->get('/links/getUserLinks/{username}', function ($username) use ($app) {
        $phql = "SELECT id, link, sourceName, visibility, position FROM links 
            WHERE username = '$username' AND visibility = '1'";       
        $links = $app->modelsManager->executeQuery($phql);
        $data = array();
        foreach ($links as $link) {
            array_push($data, array(
                'id' => $link->id,
                'link' => $link->link,
                'sourceName' => $link->sourceName,
                'visibility' => $link->visibility,
                'position' => $link->position
            ));
        }
        return getResponse($data, 200);
    });
    
    $app->post('/links/new', function () use ($app) {
        
        $json = $app->request->getJsonRawBody();
        
        $username = $json->username;
        $link = $json->link;
        $sourceName = $json->sourceName;
        $visibility = $json->visibility;
        $position = $json->position;
        
        $phql = "INSERT INTO links (link, sourceName, username, visibility, position) 
            VALUES ('$link', '$sourceName', '$username', '$visibility', '$position')";
        $status = $app->modelsManager->executeQuery($phql);
        
        $response = new Response();
        if ($status->success()) {
            $response = getResponse(array($json), 201);
        } else {
            $errors = array();
            foreach ($status->getMessages() as $message) {
                $errors[] = $message->getMessage();
            }
            $response = getResponse($errors, 409);
        }
        return $response;
    });
    
    $app->put('/links/change', function () use ($app) {
        
        $json = $app->request->getJsonRawBody();
        
        $old_id= $json->oldId;     
        $new_link = $json->newLink;
        $new_source = $json->newSourceName;
        $visibility = $json->visibility;
        
        $phql = "UPDATE links SET link = '$new_link', 
            sourceName = '$new_source', 
            visibility = '$visibility'
            WHERE id = '$old_id'";
        $status = $app->modelsManager->executeQuery($phql);
        
        $response = new Response(); 
        if ($status->success()) {
            $response = getResponse(array($json), 200);
        } else {
            $errors = array();
            foreach ($status->getMessages() as $message) {
                $errors[] = $message->getMessage();
            }
            $response = getResponse($errors, 409);
        }
        return $response;
    });

    $app->put('/links/change/position', function () use ($app) {
        
        $json = $app->request->getJsonRawBody();
        $id = $json->id;
        $position = $json->position;

        $phql = "SELECT username FROM links WHERE id = '$id'";
        $user = $app->modelsManager->executeQuery($phql)->getFirst();
        $username = $user->username;
        $phql = "SELECT id FROM links WHERE username = '$username' ORDER BY position";
        $linkIds = $app->modelsManager->executeQuery($phql);

        $phql = "UPDATE links SET position = '$position' WHERE id = '$id'";
        $app->modelsManager->executeQuery($phql);
        $counter = 1;
        foreach ($linkIds as $linkId) {
            $currentId = $linkId->id;
            if($counter == $position) {
                $counter++;
            }
            if($currentId != $id) {
                $phql = "UPDATE links SET position = '$counter' WHERE id = '$currentId'";
                $app->modelsManager->executeQuery($phql);
                $counter++;
            }
        }
        $response = new Response();
        $response = getResponse(array($linkIds), 200);
        return $response;
    });
    
    $app->post('/links/delete/{id}', function ($id) use ($app) {
        $phql = "DELETE FROM links WHERE id = '$id'";
        $status = $app->modelsManager->executeQuery($phql);
        
        $response = new Response();
        if ($status->success()) {
            $response = getResponse(array(), 200);
        } else {
            $errors = array();
            foreach ($status->getMessages() as $message) {
                $errors[] = $message->getMessage();
            }
            $response = getResponse($errors, 409);
        }
        return $response;
    });
    
    $app->post('/links/deleteAll', function () use ($app) {
        $json = $app->request->getJsonRawBody();
        $username = $json->username;
        $phql = "DELETE FROM links WHERE username = '$username'";
        $status = $app->modelsManager->executeQuery($phql);
        
        $response = new Response();
        if ($status->success() == true) {
            $response = getResponse(array($json), 200);
        } else {
            $errors = array();
            foreach ($status->getMessages() as $message) {
                $errors[] = $message->getMessage();
            }
            $response = getResponse($errors, 409);
        }
        return $response;
    });
    
    /*
    USERS
    */
    
    $app->get('/users/{username}', function ($username) use ($app) {
        $phql = "SELECT id, username FROM users WHERE visibility = '1' 
            AND NOT username = '$username'";
        $users = $app->modelsManager->executeQuery($phql);
        
        $data = array();
        foreach ($users as $user) {
            array_push($data, array(
                'id'   => $user->id,
                'username' => $user->username
            ));
        }
        
        return getResponse($data, 200);
    });
    
    $app->post('/users/new', function () use ($app) {
        
        $json = $app->request->getJsonRawBody();
        
        $username = $json->username;
        $credentials = $json->credentials;
        
        $phql = "SELECT id, username, credentials FROM users 
            WHERE username = '$username'";
        $users = $app->modelsManager->executeQuery($phql);
        $data = array();
        foreach ($users as $user) {
            array_push($data, array(
                'id' => $user->id,
                'username' => $user->username,
                'credentaials' => $user->credentials
            ));
        }
        $response = new Response();
        if(empty($data)) {
            $phql = "INSERT INTO users (username, credentials) 
                VALUES ('$username', '$credentials')";
            $status = $app->modelsManager->executeQuery($phql);
            if ($status->success()) {
                $response = getResponse(array($json), 200);
            } else {
                $errors = array();
                foreach ($status->getMessages() as $message) {
                    $errors[] = $message->getMessage();
                }
                $response = getResponse($errors, 409);
            }
            
        } else {
            $response = getResponse(array(), 409);
        }
        return $response;
    });
    
    
    $app->post('/users/check', function () use ($app, $logger) {
        $logger->info("Starts /USERS/CHECK");
        $json = $app->request->getJsonRawBody();
        
        $username = $json->username;
        $credentials = $json->credentials;

        $logger->info("Username: " . $username);
        $logger->info("Credentials: " . $credentials);
        
        $phql = "SELECT username, credentials FROM users 
            WHERE username = '$username'";
        $users = $app->modelsManager->executeQuery($phql);
        $data = array();
        foreach ($users as $user) {
            array_push($data, array(
                'username' => $user->username,
                'credentials' => $user->credentials
            ));
            $logger->info("Pushed to array: " . $user->username . " | " . $user->credentials);
        }        

        $response = new Response();
        if(!empty($data) && $data[0]['username'] == $username 
            && $data[0]['credentials'] == $credentials) {
            $response = getResponse(array($json), 200);
            $logger->info("Success!");
        } else {
            $error = "Error";
            $response = getResponse(array(), 409);
            $logger->info("Failed");
        }
        return $response;
    });

    $app->post('/users/delete/{username}', function ($username) use ($app) {
        $response = new Response();
        $deleteUserQuery = "DELETE FROM users WHERE username = '$username'";
        $deleteLinksQuery = "DELETE FROM links WHERE username = '$username'";
        $userDeletingStatus = $app->modelsManager->executeQuery($deleteUserQuery);
        $linksDeletingStatus = $app->modelsManager->executeQuery($deleteLinksQuery);
        if($userDeletingStatus->success() && $linksDeletingStatus->success()) {
            $response = getResponse(array(), 200);
        } else {
            $errors = array();
            foreach ($status->getMessages() as $message) {
                $errors[] = $message->getMessage();
            }
            $response = getResponse($errors, 409);
        }
        return $response;
    });
    
    function getResponse($data, $status_code) {
        global $STATUSES;

        $response = new Response();
        $status = $STATUSES[$status_code];
        $response->setJsonContent(
            array(
                'status' => $status,
                'data' => $data
            )
        );
        return $response;
    }

    $app->handle();
?>
