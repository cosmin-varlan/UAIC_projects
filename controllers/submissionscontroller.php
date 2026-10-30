<?php
// Depunerile la apeluri, vizibile doar pentru administratori
class SubmissionsController extends Controller
{
    private $callModel;
    private $projectsModel;

    public function __construct($action, $params)
    {
        if (!$this->isAdmin()) {
            header('Location: ' . SITE_URL);
            exit();
        }

		parent::__construct();
        $this->callModel = new CallModel();
        $this->projectsModel = new ProjectsModel();

        if ($action=="listSubmissions") $this->listSubmissions(isset($params[0]) ? $params[0] : null);
        if ($action=="viewProject") $this->viewProject(isset($params[0]) ? $params[0] : null);
        if ($action=="returnProject") $this->returnProject();
	}

    // Lista propunerilor la un apel, cu adresele de email ale propunătorilor
    public function listSubmissions($callId)
    {
        $call = $this->callModel->getCallById($callId);
        if (!$call) {
            echo $this->view->messagePage('Apel indisponibil', 'Apelul căutat nu există.');
            return;
        }

        $message = '';
        if (isset($_SESSION['admin_message'])) {
            $message = $_SESSION['admin_message'];
            unset($_SESSION['admin_message']);
        }

        $submissions = $this->model->getCallSubmissions($call['id']);
        echo $this->view->listPage($call, $submissions, $message);
    }

    // Tot ce a completat propunătorul, doar pentru citire, cu fișierele încărcate
    public function viewProject($projectId)
    {
        $project = $this->model->getProjectWithProposer($projectId);
        if (!$project) {
            echo $this->view->messagePage('Propunere indisponibilă', 'Propunerea căutată nu există.');
            return;
        }

        $call = $this->callModel->getCallById($project['call_id']);
        $fields = $call ? $this->callModel->decodeFields($call['description']) : [];
        $answers = $this->projectsModel->decodeAnswers($project['propunere']);
        $uploads = $this->projectsModel->getUploadsByProject($project['id']);
        echo $this->view->projectPage($call, $project, $fields, $answers, $uploads);
    }

    // „Întoarce la propunător”: proiectul depus redevine nedepus și poate fi modificat
    public function returnProject()
    {
        $projectId = isset($_POST['project_id']) ? $_POST['project_id'] : '';
        $project = $this->model->getProjectWithProposer($projectId);
        if (!$project) {
            echo $this->view->messagePage('Propunere indisponibilă', 'Propunerea căutată nu există.');
            return;
        }

        if (empty($project['submitted_at'])) {
            $_SESSION['admin_message'] = 'Proiectul nr. ' . intval($project['id']) . ' nu este depus, deci nu avea ce să fie întors.';
        } else {
            $this->model->returnToProposer($project['id']);
            $call = $this->callModel->getCallById($project['call_id']);
            $this->notifyProposer($project, $call);
            $_SESSION['admin_message'] = 'Proiectul nr. ' . intval($project['id']) . ' a fost întors la propunător (' . $project['email'] . ') și poate fi din nou modificat. Notificarea pentru propunător a fost scrisă în linkuri.txt.';

            // După termenul limită propunătorul tot nu poate modifica proiectul
            if ($call && CallModel::isSubmissionPeriodOver($call)) {
                $_SESSION['admin_message'] .= ' Atenție: termenul limită de depunere a expirat, deci propunătorul îl poate modifica doar dacă prelungiți termenul.';
            }
        }

        header('Location: ' . SITE_URL . '/submissionsController/listSubmissions/' . intval($project['call_id']));
        exit();
    }

    // Anunță propunătorul că proiectul i-a fost întors. Deocamdată emailul nu se trimite efectiv:
    // notificarea se scrie în linkuri.txt, la fel ca linkurile de autentificare.
    private function notifyProposer($project, $call)
    {
        $projectName = 'nr. ' . intval($project['id']);
        if ($call) {
            // Titlul apelului pe un singur rând, ca notificarea să rămână o singură linie în fișier
            $callTitle = trim(preg_replace('/\s+/', ' ', $call['title']));
            $projectName .= ' de la apelul „' . $callTitle . '” (' . trim($call['code']) . ')';
        }
        $editLink = SITE_URL . '/projectsController/editProject/' . intval($project['id']);

        $line = $project['email'] . ': "Deoarece proiectul ' . $projectName . ' a fost modificat, este nevoie să completați câteva date adiționale. '
            . 'Proiectul poate fi completat aici: ' . $editLink . '"' . PHP_EOL;
        file_put_contents(DIRECTOR_SITE . SLASH . 'linkuri.txt', $line, FILE_APPEND | LOCK_EX);
    }

    private function isAdmin()
    {
        if (empty($_SESSION['user']['roles']) || !is_array($_SESSION['user']['roles'])) {
            return false;
        }
        foreach ($_SESSION['user']['roles'] as $role) {
            if (isset($role['role']) && $role['role'] === 'Admin') {
                return true;
            }
        }
        return false;
    }
}
