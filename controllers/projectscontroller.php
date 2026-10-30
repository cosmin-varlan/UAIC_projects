<?php
class ProjectsController extends Controller
{
    private $user;
    private $callModel;

    // Extensii refuzate indiferent de formatele acceptate de câmp
    private $blockedExtensions = ['php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'htaccess', 'cgi', 'pl', 'exe', 'bat', 'cmd', 'sh', 'js', 'html', 'htm', 'svg'];

    public function __construct($action, $params)
    {
		parent::__construct();
        $this->user = $_SESSION['user'];
        $this->callModel = new CallModel();

        if ($action=="displayCalls") $this->displayCalls();
        if ($action=="viewCall") $this->viewCall(isset($params[0]) ? $params[0] : null);
        if ($action=="saveProject") $this->saveProject();
        if ($action=="myProjects") $this->myProjects();
        if ($action=="editProject") $this->editProject(isset($params[0]) ? $params[0] : null);
        if ($action=="download") $this->download(isset($params[0]) ? $params[0] : null);
        if ($action=="toEvaluate") $this->toEvaluate(isset($params[0]) ? $params[0] : null);
	}

    // Pagina „Propuneri”: lista apelurilor deschise, pentru orice utilizator autentificat
    public function displayCalls()
    {
        $openCalls = $this->callModel->getOpenCalls();
        $userProjectIds = $this->model->getProjectIdsByCall($this->user['id']);
        $evaluatorCallIds = array_column($this->model->getEvaluatorCalls($this->userEmail()), 'id');
        echo $this->view->displayCalls($openCalls, $userProjectIds, $evaluatorCallIds);
    }

    private function userEmail()
    {
        return isset($this->user['email']) ? $this->user['email'] : '';
    }

    // Evaluatorii unui apel nu pot depune proiecte la el (și-ar evalua propriul proiect)
    private function isEvaluatorOf($callId)
    {
        return $this->userEmail() !== '' && $this->model->isCallEvaluator($callId, $this->userEmail());
    }

    // Detaliile unui apel deschis și formularul gol pentru o propunere nouă
    public function viewCall($callId)
    {
        $call = $this->callModel->getCallById($callId);
        if (!$call || $call['status'] !== 'open') {
            echo $this->view->messagePage('Apel indisponibil', 'Apelul căutat nu există sau nu este deschis.');
            return;
        }
        if (CallModel::isSubmissionPeriodOver($call)) {
            echo $this->view->messagePage('Termen expirat', 'Termenul limită pentru depunerea propunerilor la acest apel a expirat.');
            return;
        }
        if ($this->isEvaluatorOf($call['id'])) {
            echo $this->view->messagePage('Depunere indisponibilă', 'Sunteți evaluator la acest apel, deci nu puteți depune proiecte la el.');
            return;
        }

        // Un utilizator are o singură propunere per apel: dacă există, o deschidem pe aceea
        $existingProject = $this->model->getProjectByCallAndUser($call['id'], $this->user['id']);
        if ($existingProject) {
            header('Location: ' . SITE_URL . '/projectsController/editProject/' . intval($existingProject['id']));
            exit();
        }

        $fields = $this->callModel->decodeFields($call['description']);
        echo $this->view->proposalPage($call, $fields, null, [], [], [], false);
    }

    // „De evaluat”: propunerile depuse la toate apelurile la care utilizatorul este evaluator
    // Fără id de apel: lista apelurilor atribuite; cu id: proiectele depuse la acel apel
    public function toEvaluate($callId = null)
    {
        $email = isset($this->user['email']) ? $this->user['email'] : '';

        if (empty($callId)) {
            echo $this->view->toEvaluatePage($this->model->getEvaluatorCalls($email));
            return;
        }

        $call = $this->callModel->getCallById($callId);
        if (!$call || $email === '' || !$this->model->isCallEvaluator($call['id'], $email)) {
            echo $this->view->messagePage('Apel indisponibil', 'Apelul căutat nu există sau nu sunteți evaluator la el.');
            return;
        }
        // Starea evaluării mele la fiecare proiect și punctajul maxim al apelului (suma punctajelor câmpurilor)
        $evaluationModel = new EvaluationModel();
        $evaluations = $evaluationModel->getEvaluationsForCall($call['id'], $email);
        $maxTotal = 0;
        foreach ($this->callModel->decodeFields($call['description']) as $field) {
            $maxTotal += (isset($field['score']) && is_numeric($field['score'])) ? (float)$field['score'] : 0;
        }
        echo $this->view->toEvaluateCallPage($call, $this->model->getSubmittedProjectsByCall($call['id']), $evaluations, $maxTotal, $email);
    }

    // Lista propunerilor utilizatorului curent („Proiectele dvs.”)
    public function myProjects()
    {
        $message = '';
        if (isset($_SESSION['info_message'])) {
            $message = $_SESSION['info_message'];
            unset($_SESSION['info_message']);
        }

        $projects = $this->model->getProjectsByUser($this->user['id']);
        echo $this->view->myProjects($projects, $message);
    }

    // Formularul precompletat cu datele unei propuneri salvate
    public function editProject($projectId)
    {
        $project = $this->model->getProjectById($projectId);
        if (!$project || $project['user_id'] != $this->user['id']) {
            echo $this->view->messagePage('Propunere indisponibilă', 'Propunerea căutată nu există sau nu vă aparține.');
            return;
        }

        $call = $this->callModel->getCallById($project['call_id']);
        if (!$call) {
            echo $this->view->messagePage('Apel indisponibil', 'Apelul la care a fost depusă această propunere nu mai există.');
            return;
        }

        $fields = $this->callModel->decodeFields($call['description']);
        $answers = $this->model->decodeAnswers($project['propunere']);
        $uploads = $this->model->getUploadsByProject($project['id']);
        // Propunerea mai poate fi modificată doar cât timp apelul este deschis, termenul limită nu a trecut,
        // ea nu a fost depusă și utilizatorul nu este evaluator la apel
        $isEvaluator = $this->isEvaluatorOf($call['id']);
        $readOnly = ($call['status'] !== 'open' || CallModel::isSubmissionPeriodOver($call) || !empty($project['submitted_at']) || $isEvaluator);
        $readOnlyMessage = ($isEvaluator && empty($project['submitted_at']))
            ? 'Sunteți evaluator la acest apel, deci propunerea nu mai poate fi modificată sau depusă.'
            : '';
        echo $this->view->proposalPage($call, $fields, $project, $answers, $uploads, [], $readOnly, '', $readOnlyMessage);
    }

    // Salvează o propunere nouă sau modificările uneia existente
    public function saveProject()
    {
        // La depășirea limitei post_max_size, PHP lasă goale $_POST și $_FILES
        if (empty($_POST)) {
            echo $this->view->messagePage('Salvare eșuată', 'Nu am primit datele formularului. Este posibil ca fișierele trimise să depășească dimensiunea maximă permisă (' . ini_get('post_max_size') . ').');
            return;
        }

        $callId = isset($_POST['call_id']) ? $_POST['call_id'] : '';
        $projectId = isset($_POST['project_id']) ? $_POST['project_id'] : '';

        $project = null;
        if ($projectId !== '') {
            $project = $this->model->getProjectById($projectId);
            if (!$project || $project['user_id'] != $this->user['id']) {
                echo $this->view->messagePage('Propunere indisponibilă', 'Propunerea căutată nu există sau nu vă aparține.');
                return;
            }
            if (!empty($project['submitted_at'])) {
                echo $this->view->messagePage('Proiect depus', 'Proiectul a fost deja depus și nu mai poate fi modificat.');
                return;
            }
            $callId = $project['call_id'];
        }

        // Butonul „Depunere proiect” salvează formularul și apoi blochează proiectul
        $submitProject = !empty($_POST['submit_project']);

        $call = $this->callModel->getCallById($callId);
        if (!$call || $call['status'] !== 'open') {
            echo $this->view->messagePage('Apel indisponibil', 'Apelul nu există sau nu mai este deschis, deci propunerea nu poate fi salvată.');
            return;
        }
        if (CallModel::isSubmissionPeriodOver($call)) {
            echo $this->view->messagePage('Termen expirat', 'Termenul limită de depunere a expirat, deci propunerea nu mai poate fi salvată sau depusă.');
            return;
        }
        if ($this->isEvaluatorOf($call['id'])) {
            echo $this->view->messagePage('Depunere indisponibilă', 'Sunteți evaluator la acest apel, deci nu puteți salva sau depune proiecte la el.');
            return;
        }
        // O propunere nouă nu se acceptă dacă utilizatorul are deja una la acest apel
        if (!$project && $this->model->getProjectByCallAndUser($call['id'], $this->user['id'])) {
            echo $this->view->messagePage('Propunere existentă', 'Aveți deja o propunere la acest apel. O puteți continua din pagina „Proiectele dvs.”.');
            return;
        }

        $fields = $this->callModel->decodeFields($call['description']);
        $existingUploads = $project ? $this->model->getUploadsByProject($project['id']) : [];
        $postedValues = (isset($_POST['fields']) && is_array($_POST['fields'])) ? $_POST['fields'] : [];

        $answers = [];
        // Valorile greșite împiedică orice salvare; câmpurile obligatorii lipsă împiedică doar depunerea
        $errors = [];
        $requiredErrors = [];
        $newFiles = [];

        foreach ($fields as $field) {
            $fieldId = $field['id'];
            $type = $field['type'];
            $required = !empty($field['required']);
            $options = (isset($field['options']) && is_array($field['options'])) ? array_map('strval', $field['options']) : [];

            if ($type === 'file') {
                $file = $this->getUploadedFile($fieldId);
                if ($file === null) {
                    if ($required && !isset($existingUploads[$fieldId])) {
                        $requiredErrors[$fieldId] = 'Încărcați un fișier pentru acest câmp.';
                    }
                    continue;
                }
                $fileError = $this->validateUploadedFile($file, $field);
                if ($fileError !== '') {
                    $errors[$fieldId] = $fileError;
                    continue;
                }
                $newFiles[$fieldId] = $file;
                continue;
            }

            if ($type === 'checkbox') {
                $posted = (isset($postedValues[$fieldId]) && is_array($postedValues[$fieldId])) ? array_map('strval', $postedValues[$fieldId]) : [];
                $value = array_values(array_intersect($options, $posted));
                if ($required && count($value) === 0) {
                    $requiredErrors[$fieldId] = 'Alegeți cel puțin o opțiune.';
                }
                $answers[$fieldId] = $value;
                continue;
            }

            $value = (isset($postedValues[$fieldId]) && is_string($postedValues[$fieldId])) ? trim($postedValues[$fieldId]) : '';
            if ($value === '') {
                if ($required) {
                    $requiredErrors[$fieldId] = 'Acest câmp este obligatoriu.';
                }
            } elseif (($type === 'select' || $type === 'radio') && !in_array($value, $options, true)) {
                $errors[$fieldId] = 'Alegeți una dintre opțiunile disponibile.';
            } elseif ($type === 'number' && !is_numeric($value)) {
                $errors[$fieldId] = 'Introduceți un număr.';
            } elseif ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$fieldId] = 'Introduceți o adresă de email validă.';
            }
            $answers[$fieldId] = $value;
        }

        if (count($errors) > 0) {
            // Formularul se reafișează cu valorile trimise; fișierele trebuie alese din nou
            if ($submitProject) {
                $errors = $errors + $requiredErrors;
            }
            echo $this->view->proposalPage($call, $fields, $project, $answers, $existingUploads, $errors, false);
            return;
        }

        // La depunere cu câmpuri obligatorii lipsă, datele se salvează, dar proiectul rămâne nedepus
        $canSubmit = ($submitProject && count($requiredErrors) === 0);

        $proposalJson = json_encode($answers, JSON_UNESCAPED_UNICODE);
        $movedFiles = [];
        $obsoleteFiles = [];

        $db = DB::getConnection();
        $db->beginTransaction();
        try {
            if ($project) {
                $projectId = $project['id'];
                $this->model->updateProject($projectId, $proposalJson);
            } else {
                $projectId = $this->model->createProject($call['id'], $this->user['id'], $proposalJson);
            }

            foreach ($newFiles as $fieldId => $file) {
                $projectDir = $this->getProjectUploadDir($call['id'], $projectId);
                if (!is_dir($projectDir) && !@mkdir($projectDir, 0755, true)) {
                    throw new Exception('Directorul pentru fișiere nu a putut fi creat.');
                }

                // Pe disc fișierul primește un nume aleator; numele original rămâne doar în baza de date
                $storedName = bin2hex(random_bytes(16)) . '.' . $this->getExtension($file['name']);
                $targetPath = $projectDir . SLASH . $storedName;
                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    throw new Exception('Fișierul nu a putut fi salvat.');
                }
                $movedFiles[] = $targetPath;

                // Un fișier nou îl înlocuiește pe cel încărcat anterior la același câmp
                if (isset($existingUploads[$fieldId])) {
                    $this->model->deleteUpload($existingUploads[$fieldId]['id']);
                    $obsoleteFiles[] = $projectDir . SLASH . $existingUploads[$fieldId]['stored_name'];
                }

                $this->model->addUpload($projectId, $fieldId, basename($file['name']), $storedName, $this->detectMimeType($targetPath), filesize($targetPath));
            }

            if ($canSubmit) {
                $this->model->markSubmitted($projectId);
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            foreach ($movedFiles as $movedFile) {
                @unlink($movedFile);
            }
            echo $this->view->messagePage('Salvare eșuată', 'Propunerea nu a putut fi salvată. Încercați din nou.');
            return;
        }

        foreach ($obsoleteFiles as $obsoleteFile) {
            @unlink($obsoleteFile);
        }

        if ($submitProject && !$canSubmit) {
            $savedProject = $this->model->getProjectById($projectId);
            $savedUploads = $this->model->getUploadsByProject($projectId);
            echo $this->view->proposalPage($call, $fields, $savedProject, $answers, $savedUploads, $requiredErrors, false,
                'Datele au fost salvate, dar proiectul nu a fost depus. Completați câmpurile obligatorii marcate și apăsați din nou „Depunere proiect”.');
            return;
        }

        if ($canSubmit) {
            $_SESSION['info_message'] = 'Proiectul a fost depus. De acum poate fi doar vizualizat.';
        } else {
            $_SESSION['info_message'] = $project ? 'Modificările propunerii au fost salvate.' : 'Propunerea a fost salvată.';
        }
        header('Location: ' . SITE_URL . '/projectsController/myProjects');
        exit();
    }

    // Trimite un fișier încărcat, doar proprietarului propunerii, evaluatorilor apelului sau unui admin
    public function download($uploadId)
    {
        $upload = $this->model->getUploadById($uploadId);
        if (!$upload || !$this->canAccessProject($upload['call_id'], $upload['user_id'])) {
            http_response_code(404);
            echo $this->view->messagePage('Fișier indisponibil', 'Fișierul căutat nu există sau nu aveți acces la el.');
            return;
        }

        $filePath = $this->getProjectUploadDir($upload['call_id'], $upload['project_id']) . SLASH . $upload['stored_name'];
        if (!is_file($filePath)) {
            http_response_code(404);
            echo $this->view->messagePage('Fișier indisponibil', 'Fișierul nu mai există pe server.');
            return;
        }

        $safeName = str_replace(['"', '\\', "\r", "\n"], '_', $upload['original_name']);
        header('Content-Type: ' . (!empty($upload['mime_type']) ? $upload['mime_type'] : 'application/octet-stream'));
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: attachment; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($upload['original_name']));
        header('X-Content-Type-Options: nosniff');
        readfile($filePath);
        exit();
    }

    // Acces la un proiect: proprietarul lui, un admin sau un evaluator al apelului (call_evaluators),
    // care are acces la toate proiectele din acel apel
    private function canAccessProject($callId, $ownerId)
    {
        if ($ownerId == $this->user['id']) {
            return true;
        }

        $roles = (isset($this->user['roles']) && is_array($this->user['roles'])) ? $this->user['roles'] : [];
        foreach ($roles as $role) {
            if (isset($role['role']) && $role['role'] === 'Admin') {
                return true;
            }
        }

        $email = isset($this->user['email']) ? $this->user['email'] : '';
        return $email !== '' && $this->model->isCallEvaluator($callId, $email);
    }

    private function getProjectUploadDir($callId, $projectId)
    {
        return DIRECTOR_SITE . SLASH . 'uploads' . SLASH . intval($callId) . SLASH . intval($projectId);
    }

    // Fișierul trimis pentru un câmp (files[id câmp]) sau null dacă nu s-a ales niciunul
    private function getUploadedFile($fieldId)
    {
        if (!isset($_FILES['files']['name'][$fieldId]) || !is_string($_FILES['files']['name'][$fieldId])) {
            return null;
        }
        if ($_FILES['files']['error'][$fieldId] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return [
            'name' => $_FILES['files']['name'][$fieldId],
            'tmp_name' => $_FILES['files']['tmp_name'][$fieldId],
            'error' => $_FILES['files']['error'][$fieldId],
            'size' => $_FILES['files']['size'][$fieldId],
        ];
    }

    // Întoarce mesajul de eroare sau șir gol dacă fișierul este acceptat
    private function validateUploadedFile($file, $field)
    {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            return 'Fișierul depășește dimensiunea maximă permisă (' . ini_get('upload_max_filesize') . ').';
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'Fișierul nu a putut fi încărcat. Încercați din nou.';
        }

        $extension = $this->getExtension($file['name']);
        if ($extension === '' || in_array($extension, $this->blockedExtensions, true)) {
            return 'Acest tip de fișier nu este acceptat.';
        }

        // Formatele acceptate sunt definite de admin în editor, de forma „.pdf, .docx”
        $allowedExtensions = [];
        $fileFormats = isset($field['fileFormats']) ? (string)$field['fileFormats'] : '';
        foreach (explode(',', $fileFormats) as $format) {
            $format = strtolower(ltrim(trim($format), '.'));
            if ($format !== '') {
                $allowedExtensions[] = $format;
            }
        }
        if (count($allowedExtensions) > 0 && !in_array($extension, $allowedExtensions, true)) {
            return 'Formate acceptate: ' . $fileFormats . '.';
        }

        return '';
    }

    private function getExtension($fileName)
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        return preg_match('/^[a-z0-9]{1,10}$/', $extension) ? $extension : '';
    }

    private function detectMimeType($filePath)
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                if (!empty($mimeType)) {
                    return $mimeType;
                }
            }
        }
        return 'application/octet-stream';
    }

}
