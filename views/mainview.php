<?php
class MainView
{

    public function __construct(){

	}

    public function displayMainPage($user = null) {
        $userName = '';
        if (!empty($user) && isset($user['firstname']) && isset($user['lastname'])) {
            $userName = trim($user['lastname'] . ' ' . $user['firstname']);
        }

        $userEmail = '';
        if (!empty($user) && isset($user['email'])) {
            $userEmail = $user['email'];
        }

        $userFaculty = '';
        if (!empty($user) && isset($user['faculty'])) {
            $userFaculty = $user['faculty'];
        }

        ob_start();
        $template = new Template(DIRECTOR_SITE.SLASH."views".SLASH."TPL".SLASH."main.tpl");
        $template->assign('URLSite', SITE_URL);
        $template->assign('MENU', Menu::render($user));
        $template->assign('userName', $userName);
        $template->assign('userEmail', $userEmail);
        $template->assign('userFaculty', $userFaculty);

        echo $template->show();
        $this->output = ob_get_contents();
        ob_end_clean();
        return $this->output;
    }
}
