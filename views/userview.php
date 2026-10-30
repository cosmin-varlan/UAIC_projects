<?php
class UserView 
{

    public function __construct(){

	}

    public function loginForm($message = '') {        
        ob_start();
        $template = new Template(DIRECTOR_SITE.SLASH."views".SLASH."TPL".SLASH."loginform.tpl");  
        $template->assign('URLSite', SITE_URL);
        $messageBlock = '';
        if (!empty($message)) {
            $messageBlock = '<div class="alert alert-info">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        $template->assign('MESSAGE_BLOCK', $messageBlock);
        echo $template->show();
        $this->output = ob_get_contents();
        ob_end_clean();        
        return $this->output;
    }

    public function registerForm($email = '', $firstname = '', $lastname = '', $faculty = '', $message = '') {
        ob_start();
        $template = new Template(DIRECTOR_SITE.SLASH."views".SLASH."TPL".SLASH."register.tpl");
        $template->assign('URLSite', SITE_URL);
        $template->assign('EMAIL', htmlspecialchars($email, ENT_QUOTES, 'UTF-8'));
        $template->assign('FIRSTNAME', htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8'));
        $template->assign('LASTNAME', htmlspecialchars($lastname, ENT_QUOTES, 'UTF-8'));
        $template->assign('FACULTY', htmlspecialchars($faculty, ENT_QUOTES, 'UTF-8'));
        $messageBlock = '';
        if (!empty($message)) {
            $messageBlock = '<div class="alert alert-warning">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        $template->assign('MESSAGE_BLOCK', $messageBlock);
        echo $template->show();
        $this->output = ob_get_contents();
        ob_end_clean();
        return $this->output;
    }
/*
    public function authorizedEmail() {        
        ob_start();
        include(DIRECTOR_SITE.SLASH."views".SLASH."TPL".SLASH."authorizedEmail.tpl");        
        $this->output = ob_get_contents();
        ob_end_clean();        
        return $this->output;
    }    

    public function unauthorizedEmail() {        
        ob_start();
        include(DIRECTOR_SITE.SLASH."views".SLASH."TPL".SLASH."unauthorizedEmail.tpl");        
        $this->output = ob_get_contents();
        ob_end_clean();        
        return $this->output;
    }      */
}