<?php
class MainController extends Controller
{
    private $user;

    public function __construct($action, $params)
    {
        parent::__construct();
        if ($action=="none"){
            if (!isset($_SESSION['user']) && isset($_COOKIE['login_token']) && !empty($_COOKIE['login_token'])) {
                $token = trim($_COOKIE['login_token']);
                $userModel = new UserModel();
                $user = $userModel->getUserByToken($token);
                if ($user !== null) {
                    $newToken = $userModel->createLoginToken($user['email']);
                    $user['login_token'] = $newToken;
                    $_SESSION['user'] = $user;
                    $this->refreshLoginTokenCookie($newToken);
                }
            }
            $currentUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;
            echo $this->view->displayMainPage($currentUser);
        }
    }

    private function refreshLoginTokenCookie($token)
    {
        $expires = time() + 3 * 24 * 60 * 60;
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('login_token', $token, $expires, '/', '', $secure, true);
    }
}
