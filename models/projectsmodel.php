<?php
class ProjectsModel
{
    public function __construct()
    {

    }

    // Propunerile unui utilizator, împreună cu datele de bază ale apelului
    public function getProjectsByUser($userId)
    {
        $sql = "SELECT projects.id, projects.call_id, projects.submitted_at, projects.created_at, projects.updated_at,
                       calls.title AS call_title, calls.code AS call_code, calls.status AS call_status,
                       calls.submission_deadline_at
                FROM projects
                LEFT JOIN calls ON calls.id = projects.call_id
                WHERE projects.user_id = ?
                ORDER BY projects.updated_at DESC, projects.id DESC";
        return DB::getData($sql, [$userId]);
    }

    public function getProjectById($projectId)
    {
        if (empty($projectId)) {
            return false;
        }

        $result = DB::getData("SELECT id, propunere, call_id, user_id, submitted_at, created_at, updated_at FROM projects WHERE id = ? LIMIT 1", [$projectId]);
        return !empty($result) ? $result[0] : false;
    }

    // Propunerea unui utilizator la un apel (fiecare utilizator are cel mult una per apel)
    public function getProjectByCallAndUser($callId, $userId)
    {
        $result = DB::getData("SELECT id, call_id, user_id, submitted_at FROM projects WHERE call_id = ? AND user_id = ? ORDER BY id LIMIT 1", [$callId, $userId]);
        return !empty($result) ? $result[0] : false;
    }

    // Apelurile la care utilizatorul are deja o propunere: [id apel => id propunere]
    public function getProjectIdsByCall($userId)
    {
        $projectIds = [];
        foreach (DB::getData("SELECT id, call_id FROM projects WHERE user_id = ? ORDER BY id", [$userId]) as $row) {
            if (!isset($projectIds[$row['call_id']])) {
                $projectIds[$row['call_id']] = $row['id'];
            }
        }
        return $projectIds;
    }

    // Apelurile la care adresa de email a fost trecută ca evaluator, cu numărul de proiecte depuse la fiecare
    public function getEvaluatorCalls($evaluatorEmail)
    {
        $sql = "SELECT calls.id, calls.title, calls.code, calls.status, calls.submission_deadline_at,
                       COUNT(projects.id) AS submitted_count
                FROM call_evaluators
                JOIN calls ON calls.id = call_evaluators.call_id
                LEFT JOIN projects ON projects.call_id = calls.id AND projects.submitted_at IS NOT NULL
                WHERE call_evaluators.email = ?
                GROUP BY calls.id
                ORDER BY calls.created_at DESC, calls.id DESC";
        return DB::getData($sql, [strtolower(trim($evaluatorEmail))]);
    }

    // Proiectele depuse la un apel, cu datele propunătorului, în ordinea depunerii
    public function getSubmittedProjectsByCall($callId)
    {
        $sql = "SELECT projects.id, projects.submitted_at,
                       users.firstname, users.lastname, users.email
                FROM projects
                JOIN users ON users.id = projects.user_id
                WHERE projects.call_id = ? AND projects.submitted_at IS NOT NULL
                ORDER BY projects.submitted_at, projects.id";
        return DB::getData($sql, [$callId]);
    }

    // Adresa de email este trecută ca evaluator la apelul dat?
    public function isCallEvaluator($callId, $evaluatorEmail)
    {
        $result = DB::getData("SELECT COUNT(*) AS n FROM call_evaluators WHERE call_id = ? AND email = ?", [$callId, strtolower(trim($evaluatorEmail))]);
        return !empty($result) && $result[0]['n'] > 0;
    }

    // Răspunsurile din projects.propunere, ca tablou [id câmp => valoare]
    public function decodeAnswers($proposal)
    {
        $answers = json_decode((string)$proposal, true);
        return is_array($answers) ? $answers : [];
    }

    // Creează propunerea și îl marchează pe utilizator ca Owner al ei
    public function createProject($callId, $userId, $proposalJson)
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("INSERT INTO projects (propunere, call_id, user_id) VALUES (?, ?, ?)");
        $stmt->execute([$proposalJson, $callId, $userId]);
        $projectId = $db->lastInsertId();

        DB::insertData("INSERT INTO roles (user_id, project_id, role) VALUES (?, ?, ?)", [$userId, $projectId, 'Owner']);
        return $projectId;
    }

    public function updateProject($projectId, $proposalJson)
    {
        DB::insertData("UPDATE projects SET propunere = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$proposalJson, $projectId]);
    }

    // Depunerea este definitivă: un proiect cu submitted_at completat nu mai poate fi modificat
    public function markSubmitted($projectId)
    {
        DB::insertData("UPDATE projects SET submitted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$projectId]);
    }

    // Fișierele unei propuneri, indexate după id-ul câmpului din formular
    public function getUploadsByProject($projectId)
    {
        $rows = DB::getData("SELECT id, project_id, field_id, original_name, stored_name, mime_type, size_bytes FROM uploads WHERE project_id = ?", [$projectId]);
        $uploads = [];
        foreach ($rows as $row) {
            $uploads[$row['field_id']] = $row;
        }
        return $uploads;
    }

    // Un fișier, împreună cu propunerea de care ține (pentru verificarea accesului)
    public function getUploadById($uploadId)
    {
        if (empty($uploadId)) {
            return false;
        }

        $sql = "SELECT uploads.id, uploads.project_id, uploads.original_name, uploads.stored_name, uploads.mime_type,
                       projects.call_id, projects.user_id
                FROM uploads
                JOIN projects ON projects.id = uploads.project_id
                WHERE uploads.id = ? LIMIT 1";
        $result = DB::getData($sql, [$uploadId]);
        return !empty($result) ? $result[0] : false;
    }

    public function addUpload($projectId, $fieldId, $originalName, $storedName, $mimeType, $sizeBytes)
    {
        DB::insertData(
            "INSERT INTO uploads (project_id, field_id, original_name, stored_name, mime_type, size_bytes) VALUES (?, ?, ?, ?, ?, ?)",
            [$projectId, $fieldId, $originalName, $storedName, $mimeType, $sizeBytes]
        );
    }

    public function deleteUpload($uploadId)
    {
        DB::insertData("DELETE FROM uploads WHERE id = ?", [$uploadId]);
    }
}
