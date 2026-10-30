<?php
class EvaluationModel
{
    public function __construct()
    {

    }

    // Evaluarea unui proiect făcută de un evaluator (identificat prin email); scores = [id câmp => notă]
    public function getEvaluation($projectId, $evaluatorEmail)
    {
        $result = DB::getData(
            "SELECT id, project_id, evaluator_email, scores, total, finalized_at, created_at, updated_at FROM evaluations WHERE project_id = ? AND evaluator_email = ? LIMIT 1",
            [$projectId, strtolower(trim($evaluatorEmail))]
        );
        if (empty($result)) {
            return false;
        }
        $evaluation = $result[0];
        $scores = json_decode((string)$evaluation['scores'], true);
        $evaluation['scores'] = is_array($scores) ? $scores : [];
        return $evaluation;
    }

    // Evaluările unui evaluator pentru proiectele unui apel: [id proiect => evaluare]
    public function getEvaluationsForCall($callId, $evaluatorEmail)
    {
        $sql = "SELECT evaluations.project_id, evaluations.total, evaluations.finalized_at
                FROM evaluations
                JOIN projects ON projects.id = evaluations.project_id
                WHERE projects.call_id = ? AND evaluations.evaluator_email = ?";
        $evaluations = [];
        foreach (DB::getData($sql, [$callId, strtolower(trim($evaluatorEmail))]) as $row) {
            $evaluations[$row['project_id']] = $row;
        }
        return $evaluations;
    }

    // Salvează evaluarea (o creează la prima salvare); cu $finalize evaluarea devine definitivă
    public function saveEvaluation($projectId, $evaluatorEmail, $scores, $total, $finalize)
    {
        $email = strtolower(trim($evaluatorEmail));
        $scoresJson = json_encode($scores);
        $existing = $this->getEvaluation($projectId, $email);

        if ($existing) {
            DB::insertData(
                "UPDATE evaluations SET scores = ?, total = ?, finalized_at = " . ($finalize ? "CURRENT_TIMESTAMP" : "NULL") . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$scoresJson, $total, $existing['id']]
            );
        } else {
            DB::insertData(
                "INSERT INTO evaluations (project_id, evaluator_email, scores, total, finalized_at) VALUES (?, ?, ?, ?, " . ($finalize ? "CURRENT_TIMESTAMP" : "NULL") . ")",
                [$projectId, $email, $scoresJson, $total]
            );
        }
    }
}
