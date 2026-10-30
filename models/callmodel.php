<?php
class CallModel 
{
    public function __construct()
    {

    }

    public function createCall($title = '-', $code = '-', $description = null, $objectives = null, $eligibility_rules = null, $budget_total = null, $status = 'draft'       )
    {
        $db = DB::getConnection();
        $db->beginTransaction();

        // Inserați datele pentru a obține ID-ul
        $sql = "INSERT INTO calls (title, code, description, objectives, eligibility_rules, budget_total, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$title, $code, $description, $objectives, $eligibility_rules, $budget_total, $status]);
        
        $sql = "SELECT last_insert_rowid() AS id";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $callId = $stmt->fetch(PDO::FETCH_ASSOC)['id'];

        $db->commit();
        return $callId;
    }

    // Calendarul apelului în ordine cronologică: coloana din tabela calls => etichetă
    public static function getTimelineFields()
    {
        return [
            'submission_start_at' => 'Începere depuneri',
            'submission_deadline_at' => 'Termen limită depuneri',
            'eligibility_check_at' => 'Verificare eligibilitate',
            'eligibility_results_at' => 'Afișarea eligibilității',
            'eligibility_appeals_at' => 'Contestații eligibilitate',
            'eligibility_appeals_results_at' => 'Rezolvarea contestațiilor pentru eligibilitate',
            'evaluation_start_at' => 'Evaluare',
            'evaluation_end_at' => 'Postarea rezultatelor evaluării',
            'appeals_at' => 'Contestații',
            'appeals_results_at' => 'Postarea rezultatelor contestațiilor',
            'beca_approval_at' => 'Aprobare în BECA',
            'contracting_at' => 'Contractare',
            'implementation_start_date' => 'Implementare (început)',
            'implementation_end_date' => 'Implementare (sfârșit)',
        ];
    }

    // Ora propusă în editor pentru etapele care au implicit o oră
    const DEFAULT_TIMELINE_TIME = '16:00';

    // Etapele care primesc implicit ora 16:00; la celelalte ora rămâne nedefinită până o alege adminul
    public static function getTimelineFieldsWithDefaultTime()
    {
        return ['submission_deadline_at', 'eligibility_appeals_at', 'appeals_at'];
    }

    // Aduce o valoare din calendar la forma „AAAA-LL-ZZ” (oră nedefinită) sau „AAAA-LL-ZZ HH:MM”.
    // Valorile nerecunoscute rămân neschimbate, ca să fie respinse de validateTimeline.
    public static function normalizeTimelineValue($value)
    {
        $value = trim((string)$value);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}:\d{2})(?::\d{2})?)?$/', $value, $matches)) {
            return $matches[1] . (isset($matches[2]) ? ' ' . $matches[2] : '');
        }
        return $value;
    }

    // Împarte o valoare din calendar în [dată, oră]; ora este '' când nu e definită
    public static function splitTimelineValue($value)
    {
        $value = self::normalizeTimelineValue($value);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})(?: (\d{2}:\d{2}))?$/', $value, $matches)) {
            return [$matches[1], isset($matches[2]) ? $matches[2] : ''];
        }
        return ['', ''];
    }

    // Verifică datele calendarului (format și ordine cronologică); etapele goale sunt ignorate.
    // Ordinea se verifică pe zile; ora contează doar între etape din aceeași zi care au amândouă oră.
    // Întoarce mesajul de eroare sau șir gol dacă totul e în regulă.
    public function validateTimeline($data)
    {
        $previousLabel = '';
        $previousDate = '';
        $previousTime = '';
        foreach (self::getTimelineFields() as $column => $label) {
            $value = isset($data[$column]) ? (string)$data[$column] : '';
            if ($value === '') {
                continue;
            }

            list($date, $time) = self::splitTimelineValue($value);
            $parsedDate = DateTime::createFromFormat('Y-m-d', $date);
            if ($date === '' || !$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
                return 'Data pentru „' . $label . '” nu este validă.';
            }
            if ($time !== '') {
                $parsedTime = DateTime::createFromFormat('H:i', $time);
                if (!$parsedTime || $parsedTime->format('H:i') !== $time) {
                    return 'Ora pentru „' . $label . '” nu este validă.';
                }
            }

            if ($previousDate !== '') {
                $sameDayEarlier = ($date === $previousDate && $time !== '' && $previousTime !== '' && $time < $previousTime);
                if ($date < $previousDate || $sameDayEarlier) {
                    return 'Data și ora pentru „' . $label . '” nu pot fi înaintea celor pentru „' . $previousLabel . '”.';
                }
            }

            // Limita pentru etapele următoare: cea mai târzie zi și, în ea, cea mai târzie oră
            if ($date > $previousDate) {
                $previousDate = $date;
                $previousTime = $time;
                $previousLabel = $label;
            } elseif ($time !== '' && $time > $previousTime) {
                $previousTime = $time;
                $previousLabel = $label;
            }
        }
        return '';
    }

    public function getCallById($callId)
    {
        if (empty($callId)) {
            return false;
        }

        $sql = "SELECT id, title, code, description, objectives, eligibility_rules, budget_total, status, "
            . implode(', ', array_keys(self::getTimelineFields()))
            . ", created_at, updated_at FROM calls WHERE id = ? LIMIT 1";
        $result = DB::getData($sql, [$callId]);

        return !empty($result) ? $result[0] : false;
    }

    public function getCalls($year = null)
    {
        if ($year !== null && $year !== '') {
            $sql = "SELECT id, title, code, status, created_at FROM calls WHERE strftime('%Y', created_at) = ? ORDER BY created_at DESC, id DESC";
            return DB::getData($sql, [strval($year)]);
        }

        $sql = "SELECT id, title, code, status, created_at FROM calls ORDER BY created_at DESC, id DESC";
        return DB::getData($sql, []);
    }

    // Apelurile deschise la care încă se pot depune propuneri, vizibile oricărui utilizator autentificat
    public function getOpenCalls()
    {
        $sql = "SELECT id, title, code, objectives, eligibility_rules, budget_total, submission_start_at, submission_deadline_at FROM calls WHERE status = ? ORDER BY created_at DESC, id DESC";
        $calls = DB::getData($sql, ['open']);
        return array_values(array_filter($calls, function ($call) {
            return !CallModel::isSubmissionPeriodOver($call);
        }));
    }

    // Termenul limită de depunere a trecut? Un apel fără termen limită rămâne deschis,
    // iar un termen fără oră ține până la sfârșitul acelei zile.
    public static function isSubmissionPeriodOver($call)
    {
        list($date, $time) = self::splitTimelineValue(isset($call['submission_deadline_at']) ? $call['submission_deadline_at'] : '');
        if ($date === '') {
            return false;
        }
        if ($time === '') {
            return date('Y-m-d') > $date;
        }
        return date('Y-m-d H:i') >= $date . ' ' . $time;
    }

    // Câmpurile formularului (calls.description) ca listă; fiecare câmp primește sigur un id.
    // Apelurile salvate înainte de introducerea id-urilor primesc unul după poziție.
    public function decodeFields($description)
    {
        $decoded = json_decode((string)$description, true);
        if (!is_array($decoded)) {
            return $this->withFixedFields([]);
        }

        $fields = [];
        foreach ($decoded as $index => $field) {
            if (!is_array($field) || empty($field['type'])) {
                continue;
            }
            if (empty($field['id']) || !preg_match('/^[A-Za-z0-9_]+$/', (string)$field['id'])) {
                $field['id'] = 'field_' . $index;
            }
            $fields[] = $field;
        }
        return $this->withFixedFields($fields);
    }

    // Câmpurile pe care le are orice formular de apel, mereu primele; adminul nu le poate șterge sau muta
    public static function getFixedFields()
    {
        return [
            [
                'id' => 'brainmap_code',
                'label' => 'Codul Brainmap',
                'type' => 'text',
                'description' => '',
                'required' => false,
                'score' => 0,
                'fixed' => true,
            ],
        ];
    }

    // Pune câmpurile fixe la începutul listei, mereu cu definiția din cod; copiile lor din listă sunt eliminate
    public function withFixedFields($fields)
    {
        $fixedFields = self::getFixedFields();
        $fixedIds = array_column($fixedFields, 'id');
        $otherFields = [];
        foreach ($fields as $field) {
            $fieldId = (is_array($field) && isset($field['id'])) ? $field['id'] : '';
            if (is_array($field) && !in_array($fieldId, $fixedIds, true)) {
                $otherFields[] = $field;
            }
        }
        return array_merge($fixedFields, $otherFields);
    }

    // Dă un id stabil câmpurilor care nu au unul, pentru ca răspunsurile și fișierele
    // din propuneri să rămână legate de câmp chiar dacă ordinea se schimbă.
    public function assignFieldIds($fields)
    {
        if (!is_array($fields)) {
            return [];
        }

        $usedIds = [];
        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }
            $fieldId = isset($field['id']) ? (string)$field['id'] : '';
            if (!preg_match('/^[A-Za-z0-9_]+$/', $fieldId) || isset($usedIds[$fieldId])) {
                do {
                    $fieldId = 'f' . bin2hex(random_bytes(4));
                } while (isset($usedIds[$fieldId]));
            }
            $usedIds[$fieldId] = true;
            $fields[$index]['id'] = $fieldId;
        }
        return $fields;
    }

    public function saveCall($data)
    {
        $db = DB::getConnection();
        $db->beginTransaction();
        try {
            $id = null;
            // Coloanele salvate: datele de bază, apoi toate datele din calendar
            $columns = array_merge(
                ['title', 'code', 'description', 'objectives', 'eligibility_rules', 'budget_total', 'status'],
                array_keys(self::getTimelineFields())
            );
            $values = [];
            foreach ($columns as $column) {
                $values[] = isset($data[$column]) ? $data[$column] : null;
            }

            if (!empty($data['id'])) {
                // Update existing
                $sql = "UPDATE calls SET " . implode(' = ?, ', $columns) . " = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $values[] = $data['id'];
                $stmt = $db->prepare($sql);
                $stmt->execute($values);
                $id = $data['id'];
            } else {
                // Insert new
                $sql = "INSERT INTO calls (" . implode(', ', $columns) . ") VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")";
                $stmt = $db->prepare($sql);
                $stmt->execute($values);
                $stmt2 = $db->prepare("SELECT last_insert_rowid() AS id");
                $stmt2->execute();
                $id = $stmt2->fetch(PDO::FETCH_ASSOC)['id'];
            }

            // Evaluatorii se înlocuiesc doar dacă au fost trimiși (lista deja validată de normalizeEvaluators)
            if (isset($data['evaluators']) && is_array($data['evaluators'])) {
                $db->prepare("DELETE FROM call_evaluators WHERE call_id = ?")->execute([$id]);
                $insert = $db->prepare("INSERT INTO call_evaluators (call_id, email, position) VALUES (?, ?, ?)");
                foreach (array_values($data['evaluators']) as $position => $email) {
                    $insert->execute([$id, $email, $position + 1]);
                }
            }

            $db->commit();
            return $id;
        } catch (Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    // Numărul maxim de evaluatori pe care îi poate avea un apel
    const MAX_EVALUATORS = 5;

    // Adresele de email ale evaluatorilor unui apel, în ordinea în care au fost introduse
    public function getEvaluators($callId)
    {
        $rows = DB::getData("SELECT email FROM call_evaluators WHERE call_id = ? ORDER BY position, id", [$callId]);
        return array_column($rows, 'email');
    }

    // Curăță lista de evaluatori trimisă din editor: fără câmpuri goale, fără dubluri, cu litere mici.
    // Întoarce [lista curățată, mesaj de eroare sau șir gol].
    public function normalizeEvaluators($emails)
    {
        if (!is_array($emails)) {
            return [[], ''];
        }

        $evaluators = [];
        foreach ($emails as $email) {
            $email = strtolower(trim((string)$email));
            if ($email === '') {
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [[], 'Adresa de email a evaluatorului „' . $email . '” nu este validă.'];
            }
            if (!in_array($email, $evaluators, true)) {
                $evaluators[] = $email;
            }
        }

        if (count($evaluators) > self::MAX_EVALUATORS) {
            return [[], 'Un apel poate avea cel mult ' . self::MAX_EVALUATORS . ' evaluatori.'];
        }
        return [$evaluators, ''];
    }

    // Dintre adresele date, cele ale utilizatorilor care au deja o propunere (depusă sau nu) la apel;
    // aceștia nu pot fi evaluatori la el, pentru că și-ar evalua propriul proiect
    public function getEmailsWithProposals($callId, $emails)
    {
        if (empty($callId) || empty($emails)) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($emails), '?'));
        $sql = "SELECT DISTINCT lower(users.email) AS email
                FROM projects
                JOIN users ON users.id = projects.user_id
                WHERE projects.call_id = ? AND lower(users.email) IN ($placeholders)";
        $rows = DB::getData($sql, array_merge([$callId], array_values($emails)));
        return array_column($rows, 'email');
    }

    public function deleteCall($callId)
    {
        $db = DB::getConnection();
        DB::insertData("DELETE FROM call_evaluators WHERE call_id = ?", [$callId]);
        $sql = "DELETE FROM calls WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        return $stmt->execute([$callId]);
    }


}
