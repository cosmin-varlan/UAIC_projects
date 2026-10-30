<?php
class CallController extends Controller
{
    private $params = [];
    public function __construct($action, $params)
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Early debug log to capture incoming params and session (helps detect non-admin redirects)
        try {
            $dbgFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utils' . DIRECTORY_SEPARATOR . 'savecall_debug.log';
            $entry = date('Y-m-d H:i:s') . " | ctor_attempt action=" . $action . " params=" . json_encode($params) . " session_user=" . json_encode($_SESSION['user'] ?? null) . PHP_EOL;
            @file_put_contents($dbgFile, $entry, FILE_APPEND);
        } catch (Exception $e) {
            // ignore
        }

        $isAdmin = false;
        if (!empty($_SESSION['user']) && isset($_SESSION['user']['roles']) && is_array($_SESSION['user']['roles'])) {
            foreach ($_SESSION['user']['roles'] as $role) {
                if (isset($role['role']) && $role['role'] === 'Admin') {
                    $isAdmin = true;
                    break;
                }
            }
        }

        if (!$isAdmin) {
            header('Location: ' . SITE_URL);
            exit();
        }

        parent::__construct();
        // store params passed from front controller (may include parsed POST data)
        $this->params = is_array($params) ? $params : [];

        // Normalize: sometimes the front controller puts the action inside params (e.g. params[0] = 'saveCall')
        $actionDetected = $action;
        if (empty($actionDetected) && !empty($this->params) && isset($this->params[0]) && is_string($this->params[0])) {
            $actionDetected = $this->params[0];
        }

        // Also check if any param string equals the action we expect
        $hasSaveCallInParams = false;
        if (is_array($this->params)) {
            foreach ($this->params as $p) {
                if ($p === 'saveCall') { $hasSaveCallInParams = true; break; }
            }
        }

        if ($actionDetected=="createCall") $this->createCall();
        if ($actionDetected=="deleteCall") $this->deleteCall($params);
        if ($actionDetected=="listCalls") $this->listCalls();
        if ($actionDetected=="saveCall" || $hasSaveCallInParams) $this->saveCall();
        if ($actionDetected=="editCall") $this->editCall($params[0]);
	}

    public function createCall()
    {
        $callId = $this->model->createCall();
        $this->editCall($callId);
        //$createCallPage = $this->view->createCallPage();
        //echo $createCallPage;        
    }

    function editCall($callId)
    {
        $callData = $this->model->getCallById($callId);
        if ($callData) {
            // Câmpurile fără id (apeluri mai vechi) ajung în editor cu id-ul dedus, ca să rămână același după salvare
            $callData['description'] = json_encode($this->model->decodeFields($callData['description']));
            $callData['evaluators'] = $this->model->getEvaluators($callData['id']);
            $editCallPage = $this->view->editCallPage($callData);
            echo $editCallPage;
        } else {
            // Handle the case when the call data is not found
            echo "Call not found.";
        }
    }

    public function saveCall()
    {
        // Ensure no accidental output breaks JSON response
        if (ob_get_level() === 0) ob_start();

        // Read JSON body
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        // If JSON not provided or empty, fallback to $_POST
        if (empty($data) && !empty($_POST)) {
            $data = $_POST;
        }

        // If still empty, fallback to params passed by front controller (index.php parse_str)
        if (empty($data) && !empty($this->params)) {
            // $this->params may include numeric-indexed segments; extract string keys
            $stringKeys = array_filter(array_keys($this->params), 'is_string');
            if (!empty($stringKeys)) {
                $data = [];
                foreach ($stringKeys as $k) $data[$k] = $this->params[$k];
            } else {
                // fallback: use all params
                $data = $this->params;
            }
        }

        // Also merge any string-keyed constructor params into data (do not overwrite existing keys)
        if (!empty($this->params) && is_array($this->params)) {
            foreach ($this->params as $k => $v) {
                if (is_string($k) && (!isset($data[$k]) || $data[$k] === '')) {
                    $data[$k] = $v;
                }
            }
        }

        // accept either 'id' or 'call_id' from payload
        $incomingId = null;
        if (!empty($data['id'])) $incomingId = $data['id'];
        elseif (!empty($data['call_id'])) $incomingId = $data['call_id'];

        // Fiecare câmp al formularului primește un id stabil înainte de salvare,
        // iar câmpurile fixe (Codul Brainmap) rămân primele, chiar dacă lipsesc din ce a trimis browserul
        if (isset($data['fields'])) {
            $data['fields'] = $this->model->withFixedFields($this->model->assignFieldIds($data['fields']));
        }

        $callData = [
            'id' => !empty($incomingId) ? $incomingId : null,
            'title' => $data['title'] ?? '',
            'code' => $data['code'] ?? '',
            // description will store fields JSON as requested
            'description' => isset($data['fields']) ? json_encode($data['fields']) : ($data['fields_json'] ?? ''),
            'objectives' => $data['objectives'] ?? null,
            'eligibility_rules' => $data['eligibility'] ?? null,
            'budget_total' => ($data['budget'] ?? null) !== '' ? $data['budget'] : null,
            'status' => $data['status'] ?? 'draft',
        ];

        // Datele din calendar (dată și oră); cele necompletate se salvează ca NULL
        foreach (CallModel::getTimelineFields() as $column => $label) {
            $value = CallModel::normalizeTimelineValue(isset($data[$column]) ? $data[$column] : '');
            $callData[$column] = ($value !== '') ? $value : null;
        }

        // Calendarul trebuie să fie în ordine cronologică; altfel apelul nu se salvează
        $timelineError = $this->model->validateTimeline($callData);

        // Evaluatorii (adrese de email, cel mult 5); dacă nu au fost trimiși deloc, rămân cei salvați
        if ($timelineError === '' && isset($data['evaluators'])) {
            list($callData['evaluators'], $timelineError) = $this->model->normalizeEvaluators($data['evaluators']);

            // Nu poate fi evaluator cine are deja o propunere la acest apel
            $proposers = $this->model->getEmailsWithProposals($callData['id'], $callData['evaluators']);
            if ($timelineError === '' && count($proposers) > 0) {
                $timelineError = (count($proposers) === 1
                    ? 'Utilizatorul ' . $proposers[0] . ' are deja o propunere la acest apel, deci nu poate fi evaluator la el.'
                    : 'Utilizatorii ' . implode(', ', $proposers) . ' au deja propuneri la acest apel, deci nu pot fi evaluatori la el.');
            }
        }

        if ($timelineError !== '') {
            if (ob_get_length() !== false) {
                ob_clean();
            }
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $timelineError]);
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            exit();
        }

        $savedId = $this->model->saveCall($callData);

        // Debug: log incoming payload and result
        try {
            $logFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utils' . DIRECTORY_SEPARATOR . 'savecall_debug.log';
            $entry = date('Y-m-d H:i:s') . " | incoming:" . json_encode($data) . " | savedId:" . json_encode($savedId) . PHP_EOL;
            @file_put_contents($logFile, $entry, FILE_APPEND);
        } catch (Exception $e) {
            // ignore logging errors
        }

        // Clean any buffered output and return clean JSON
        if (ob_get_length() !== false) {
            ob_clean();
        }
        header('Content-Type: application/json');
        if ($savedId) {
            echo json_encode(['success' => true, 'id' => $savedId]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false]);
        }
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        exit();
    }

    public function deleteCall($params)
    {
        $callId = null;

        if (!empty($params) && isset($params[0]) && $params[0] !== '') {
            $callId = $params[0];
        } else {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (is_array($data) && count($data) > 0) {
                $callId = $data[0];
            } elseif (!empty($_POST)) {
                if (isset($_POST['call_id'])) {
                    $callId = $_POST['call_id'];
                } elseif (isset($_POST['id'])) {
                    $callId = $_POST['id'];
                } else {
                    $first = reset($_POST);
                    if ($first !== false) {
                        $callId = $first;
                    }
                }
            }
        }

        if (empty($callId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing call ID']);
            return false;
        }

        $result = $this->model->deleteCall($callId);
        header('Content-Type: application/json');
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false]);
        }
        return $result;
    }

    public function listCalls()
    {
        $year = null;
        if (!empty($this->params) && is_array($this->params)) {
            if (isset($this->params['year']) && $this->params['year'] !== '') {
                $year = trim($this->params['year']);
            } elseif (isset($this->params[0]) && $this->params[0] !== '') {
                $year = trim($this->params[0]);
            }
        }

        // the view shows its own "no calls" message, keeping the create button available
        $calls = $this->model->getCalls($year);
        echo $this->view->listCalls($calls);
    }

}