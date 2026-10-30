<?php
// Meniul comun tuturor paginilor:
// - „Propuneri” și „Proiectele dvs.” pentru toți utilizatorii;
// - „De evaluat” pentru cine a fost trecut ca evaluator la cel puțin un apel (tabela call_evaluators);
// - „Apeluri” pentru administratori (rolul Admin din tabela roles).
class Menu
{
    public static function render($user = null)
    {
        if ($user === null && isset($_SESSION['user'])) {
            $user = $_SESSION['user'];
        }

        $isAdmin = false;
        if (!empty($user) && isset($user['roles']) && is_array($user['roles'])) {
            foreach ($user['roles'] as $role) {
                if (isset($role['role']) && $role['role'] === 'Admin') {
                    $isAdmin = true;
                }
            }
        }

        $isEvaluator = false;
        if (!empty($user['email'])) {
            $assigned = DB::getData("SELECT COUNT(*) AS n FROM call_evaluators WHERE email = ?", [strtolower(trim($user['email']))]);
            $isEvaluator = !empty($assigned) && $assigned[0]['n'] > 0;
        }

        $menu = '<li class="nav-item"><a class="nav-link" href="' . SITE_URL . '/projectsController/displayCalls">Propuneri</a></li>';
        $menu .= '<li class="nav-item"><a class="nav-link" href="' . SITE_URL . '/projectsController/myProjects">Proiectele dvs.</a></li>';
        if ($isEvaluator) {
            $menu .= '<li class="nav-item"><a class="nav-link" href="' . SITE_URL . '/projectsController/toEvaluate">De evaluat</a></li>';
        }
        if ($isAdmin) {
            $menu .= '<li class="nav-item"><a class="nav-link" href="' . SITE_URL . '/callController/listCalls" style="color: #fffb00;">Apeluri</a></li>';
        }
        return $menu;
    }
}
