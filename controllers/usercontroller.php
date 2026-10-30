<?php
class UserController extends Controller
{
    public function __construct($action, $params)
    {
		parent::__construct();
        if ($action=="loginForm") $this->loginForm();
        if ($action=="register") $this->register($params);
        if ($action=="auth") $this->auth($params);
        if ($action=="logout") $this->logout();
        if ($action=="globalLogout") $this->globalLogout();
	}

    public function loginForm()
    {
        $message = '';
        if (isset($_SESSION['info_message'])) {
            $message = $_SESSION['info_message'];
            unset($_SESSION['info_message']);
        }
        $pagina = $this->view->loginForm($message);
        echo $pagina;
    }

    public function register($params)
    {
        $email = '';
        $firstname = '';
        $lastname = '';
        $faculty = '';
        $message = '';

        if (is_array($params)) {
            if (isset($params['email'])) {
                $email = trim($params['email']);
            } elseif (isset($params[0])) {
                // decode URL-encoded email (e.g. %40 -> @)
                $email = rawurldecode(trim($params[0]));
            }

            if (isset($params['firstname'])) {
                $firstname = trim($params['firstname']);
            }
            if (isset($params['lastname'])) {
                $lastname = trim($params['lastname']);
            }
            if (isset($params['faculty'])) {
                $faculty = trim($params['faculty']);
            }
        }

        if (!empty($firstname) || !empty($lastname) || !empty($faculty)) {
            if (empty($email) || empty($firstname) || empty($lastname) || empty($faculty)) {
                $message = 'Completați toate câmpurile pentru a continua.';
            } else {
               /* $existing = $this->model->auth($email);
                if ($existing !== null) {
                    header('Location: ' . SITE_URL . '/UserController/loginForm');
                    exit;
                }*/
                $this->model->createUser($firstname, $lastname, $email, $faculty);
                header('Location: ' . SITE_URL . '/UserController/loginForm');
                exit;
            }
        }

        $pagina = $this->view->registerForm($email, $firstname, $lastname, $faculty, $message);
        echo $pagina;
    }

    public function auth($params)
    {
        $email = '';
        if (is_array($params)) {
            if (isset($params['email'])) {
                $email = trim($params['email']);
            } elseif (isset($params[0]) && filter_var($params[0], FILTER_VALIDATE_EMAIL)) {
                $email = trim($params[0]);
            }
        }

        // If email is empty, check if a token is provided in the URL parameters
        if (empty($email) && is_array($params) && isset($params[0])) {
            $token = trim($params[0]);
            $user = $this->model->getUserByToken($token);
            if ($user !== null) {
                $this->setLoginTokenCookie($token);
                $_SESSION['user'] = $user;
                header('Location: ' . SITE_URL);
                exit;
            }
            header('Location: ' . SITE_URL . '/UserController/loginForm');
            exit;
        }

        // If email is still empty, redirect to login form
        if (empty($email)) {
            header('Location: ' . SITE_URL . '/UserController/loginForm');
            exit;
        }

        // Check if the user exists in the database
        $user = $this->model->auth($email);
        if ($user === null) {
            header('Location: ' . SITE_URL . '/UserController/register/' . rawurlencode($email));
            exit;
        }
        $user['roles'] = $this->model->getUserRoles($email);

        $cookieHash = isset($_COOKIE['login_token']) ? $_COOKIE['login_token'] : null;
        if (!empty($cookieHash) && $cookieHash === $user['login_token']) {
            $this->setLoginTokenCookie($cookieHash);
            $_SESSION['user'] = $user;
            header('Location: ' . SITE_URL);
            exit;
        }

        $token = $this->model->ensureLoginToken($email);
        $this->writeAuthLink($email, $token);
        $this->sendAuthenticationEmail($email, $token);
        $_SESSION['info_message'] = 'Am trimis linkul de autentificare pe email. Verifică căsuța de email.';
        header('Location: ' . SITE_URL . '/UserController/loginForm');
        exit;
    }


    private function writeAuthLink($email, $token)
    {
        $link = SITE_URL . '/UserController/auth/' . rawurlencode($token);
        //$line = date('Y-m-d H:i:s') . ' - ' . $email . ' - ' . $token . ' - ' . $link . PHP_EOL;
        $line =  $link . ' - ' . $email . PHP_EOL;
        file_put_contents(DIRECTOR_SITE . SLASH . 'linkuri.txt', $line, FILE_APPEND | LOCK_EX);
    }

    private function sendAuthenticationEmail($email, $token)
    {
        $link = SITE_URL . '/UserController/auth/' . rawurlencode($token);
        $subject = 'Link de autentificare ';
        $message = "Salut,

Accesează linkul de mai jos pentru a te autentifica:
$link

Linkul este valabil 3 zile. ";
        $headers = 'From: no-reply@' . $_SERVER['HTTP_HOST'] . "\r\n" .
                   'Reply-To: no-reply@' . $_SERVER['HTTP_HOST'] . "\r\n" .
                   'X-Mailer: PHP/' . phpversion();
        @mail($email, $subject, $message, $headers);
    }

    private function setLoginTokenCookie($token)
    {
        $expires = time() + 3 * 24 * 60 * 60;
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('login_token', $token, $expires, '/', '', $secure, true);
    }

    public function logout()
    {
        // clear session
        if (session_status() === PHP_SESSION_NONE) @session_start();
        if (isset($_SESSION['user'])) unset($_SESSION['user']);

        // expire the cookie on the server side
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('login_token', '', time() - 3600, '/', '', $secure, true);

        // Return a small HTML page that ensures the cookie is removed in the browser,
        // replaces the current history entry (so the auth link is removed) and redirects to login.
        $loginUrl = SITE_URL . '/UserController/loginForm';
        echo "<!doctype html><html><head><meta charset=\"utf-8\"><title>Logout</title></head><body>";
        echo "<script>";
        // also remove cookie via JS to be extra sure
        echo "document.cookie = 'login_token=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT;';";
        // replace current history entry so token link is not in history
        echo "try { history.replaceState(null, document.title, '" . addslashes($loginUrl) . "'); } catch(e) {}";
        // redirect to login page without creating a new history entry
        echo "setTimeout(function(){ location.replace('" . addslashes($loginUrl) . "'); }, 200);";
        echo "</script>";
        echo "</body></html>";
        exit;
    }

    public function globalLogout()
    {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $email = null;
        if (isset($_SESSION['user']) && isset($_SESSION['user']['email'])) {
            $email = $_SESSION['user']['email'];
        } else {
            // try to get user by cookie token
            $token = isset($_COOKIE['login_token']) ? $_COOKIE['login_token'] : null;
            if (!empty($token)) {
                $user = $this->model->getUserByToken($token);
                if ($user !== null && isset($user['email'])) $email = $user['email'];
            }
        }

        if (!empty($email)) {
            $this->model->clearLoginToken($email);
        }

        // clear session and cookie locally as well
        if (isset($_SESSION['user'])) unset($_SESSION['user']);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('login_token', '', time() - 3600, '/', '', $secure, true);

        header('Location: ' . SITE_URL . '/UserController/loginForm');
        exit;
    }

    
}