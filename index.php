<?php
    session_start();


    define ('SLASH', DIRECTORY_SEPARATOR);
    define ('DIRECTOR_SITE', dirname(__FILE__));

    require_once DIRECTOR_SITE.SLASH."utils".SLASH."autoloader.php";
    require_once DIRECTOR_SITE.SLASH."utils".SLASH."config.php";
    //require_once DIRECTOR_SITE . SLASH . 'utils' . SLASH .  'db.php'

    // setting some defaults values.....
    $controller = "MainController";
    $actiune = "none";
    $parametri = [];

    // http://localhost/formularAthos/UserController/loginForm

    // ***********   GETTING DATA FROM FROM THROUGH POST *************
    $post = file_get_contents('php://input');
        // If the request body is JSON (AJAX), decode into params; otherwise parse as query string
        $contentType = '';
        if (!empty($_SERVER['CONTENT_TYPE'])) $contentType = $_SERVER['CONTENT_TYPE'];
        elseif (!empty($_SERVER['HTTP_CONTENT_TYPE'])) $contentType = $_SERVER['HTTP_CONTENT_TYPE'];

        if (stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode($post, true);
            if (is_array($decoded)) {
                $parametri = $decoded;
            } else {
                // fallback to empty
                $parametri = [];
            }
        } else {
            parse_str($post, $parametri);
        }
    // -----------  GOT DATA FROM POST INTO $parametri ARRAY ---------





    // ************   PARSING THE URL TO GET CONTROLLER AND ACTION **************    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $domain = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER["REQUEST_URI"];
    $siteAddress = $protocol. "://" .$domain."/";

    $_SESSION["domain"] = $domain;
    
    
    $infos = explode('/', $uri);

    $controllerPosition = -1;    
    for($i=0; $i<count($infos); $i++)
    {
        if (strpos($infos[$i], 'Controller')){
            $controllerPosition = $i;
            break;
        }
    }
    if ($controllerPosition>1) $siteAddress.= ($infos[1]."/");
    $_SESSION["siteAddress"] = $siteAddress;

    if ($controllerPosition != -1)  {
        $controller = $infos[$controllerPosition];
        if ($controllerPosition + 1 < count($infos)) $actiune = $infos[$controllerPosition + 1];
        else $actiune="";
        if ($controllerPosition + 2 < count($infos)) 
        {
            for($i=$controllerPosition+2; $i<count($infos); $i++)
                array_push($parametri, $infos[$i]);
        }                    
    }
    // ------------ DONE PARSING ---------------

    // some of the routes are allowed only if we are authenticated


    $cookieToken = isset($_COOKIE['login_token']) ? $_COOKIE['login_token'] : null;
    $userModel = new UserModel();
    $user = $userModel->getUserByToken($cookieToken);
    $_SESSION['user'] = $user;

    if (isset($_SESSION["user"]) && $_SESSION["user"] != NULL)
    {
        $control = new $controller($actiune, $parametri);
    }
    else
    {
        if (($controller == "UserController" && ($actiune=="loginForm" || $actiune=="auth" || $actiune=="register")))
            $control = new $controller($actiune, $parametri);
        else {            
            header('Location: ' . SITE_URL.'/UserController/loginForm');
            exit();
        }
    }

    //print_rr($_SESSION);

?>