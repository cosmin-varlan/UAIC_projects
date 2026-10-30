<?php
// Evaluarea proiectelor de către evaluatorii apelului (tabela call_evaluators)
class EvaluationController extends Controller
{
    private $user;
    private $callModel;
    private $projectsModel;
    private $submissionsModel;

    public function __construct($action, $params)
    {
		parent::__construct();
        $this->user = $_SESSION['user'];
        $this->callModel = new CallModel();
        $this->projectsModel = new ProjectsModel();
        $this->submissionsModel = new SubmissionsModel();

        if ($action=="evaluate") $this->evaluate(isset($params[0]) ? $params[0] : null);
        if ($action=="saveEvaluation") $this->saveEvaluation();
	}

    // Pagina de evaluare: datele proiectului și notele (editabile până la finalizare)
    public function evaluate($projectId)
    {
        $context = $this->loadContext($projectId);
        if ($context === null) {
            return;
        }

        $message = '';
        if (isset($_SESSION['evaluation_message'])) {
            $message = $_SESSION['evaluation_message'];
            unset($_SESSION['evaluation_message']);
        }

        $scores = $context['evaluation'] ? $context['evaluation']['scores'] : [];
        echo $this->view->evaluatePage($context, $scores, [], $message);
    }

    // „Salvează evaluarea” (se poate continua) sau „Finalizează evaluarea” (devine doar pentru citire)
    public function saveEvaluation()
    {
        $projectId = isset($_POST['project_id']) ? $_POST['project_id'] : '';
        $context = $this->loadContext($projectId);
        if ($context === null) {
            return;
        }
        if ($context['evaluation'] && !empty($context['evaluation']['finalized_at'])) {
            echo $this->view->messagePage('Evaluare finalizată', 'Evaluarea acestui proiect a fost deja finalizată și nu mai poate fi modificată.', $context['call']['id']);
            return;
        }

        $finalize = !empty($_POST['finalize']);
        $postedScores = (isset($_POST['scores']) && is_array($_POST['scores'])) ? $_POST['scores'] : [];

        // Notele se verifică doar pentru câmpurile cu punctaj; la salvare simplă pot lipsi, la finalizare nu
        $scores = [];
        $enteredScores = [];
        $errors = [];
        foreach ($context['fields'] as $field) {
            $maxScore = $this->maxScore($field);
            if ($maxScore <= 0) {
                continue;
            }
            $fieldId = $field['id'];
            $value = (isset($postedScores[$fieldId]) && is_string($postedScores[$fieldId])) ? trim(str_replace(',', '.', $postedScores[$fieldId])) : '';
            $enteredScores[$fieldId] = $value;

            if ($value === '') {
                if ($finalize) {
                    $errors[$fieldId] = 'Acordați o notă pentru a putea finaliza evaluarea.';
                }
                continue;
            }
            if (!is_numeric($value) || (float)$value < 0 || (float)$value > $maxScore) {
                $errors[$fieldId] = 'Nota trebuie să fie un număr între 0 și ' . $this->formatScore($maxScore) . '.';
                continue;
            }
            $scores[$fieldId] = round((float)$value, 2);
        }

        if (count($errors) > 0) {
            echo $this->view->evaluatePage($context, $enteredScores, $errors, '');
            return;
        }

        $total = round(array_sum($scores), 2);
        $this->model->saveEvaluation($context['project']['id'], $this->userEmail(), $scores, $total, $finalize);

        $_SESSION['evaluation_message'] = $finalize
            ? 'Evaluarea a fost finalizată. Proiectul poate fi de acum doar vizualizat.'
            : 'Evaluarea a fost salvată. O puteți continua oricând de unde ați rămas.';
        header('Location: ' . SITE_URL . '/evaluationController/evaluate/' . intval($context['project']['id']));
        exit();
    }

    // Proiectul, apelul, câmpurile, răspunsurile, fișierele și evaluarea curentă; null (cu mesaj afișat)
    // dacă proiectul nu există, nu este depus sau utilizatorul nu este evaluator la apelul lui
    private function loadContext($projectId)
    {
        $project = $this->submissionsModel->getProjectWithProposer($projectId);
        $call = $project ? $this->callModel->getCallById($project['call_id']) : false;
        if (!$project || !$call || !$this->projectsModel->isCallEvaluator($call['id'], $this->userEmail())) {
            echo $this->view->messagePage('Proiect indisponibil', 'Proiectul căutat nu există sau nu sunteți evaluator la apelul lui.', null);
            return null;
        }
        if (empty($project['submitted_at'])) {
            echo $this->view->messagePage('Proiect nedepus', 'Proiectul nu este depus în acest moment, deci nu poate fi evaluat.', $call['id']);
            return null;
        }
        // Nimeni nu își evaluează propriul proiect (protecție și pentru date mai vechi decât această regulă)
        if (strtolower(trim($project['email'])) === strtolower(trim($this->userEmail()))) {
            echo $this->view->messagePage('Propriul proiect', 'Acesta este propriul dumneavoastră proiect, deci nu îl puteți evalua.', $call['id']);
            return null;
        }

        return [
            'project' => $project,
            'call' => $call,
            'fields' => $this->callModel->decodeFields($call['description']),
            'answers' => $this->projectsModel->decodeAnswers($project['propunere']),
            'uploads' => $this->projectsModel->getUploadsByProject($project['id']),
            'evaluation' => $this->model->getEvaluation($project['id'], $this->userEmail()),
        ];
    }

    private function userEmail()
    {
        return isset($this->user['email']) ? $this->user['email'] : '';
    }

    private function maxScore($field)
    {
        return (isset($field['score']) && is_numeric($field['score'])) ? (float)$field['score'] : 0;
    }

    private function formatScore($value)
    {
        return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
    }
}
