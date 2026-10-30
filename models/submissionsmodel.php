<?php
class SubmissionsModel
{
    public function __construct()
    {

    }

    // Propunerile la un apel, cu datele propunătorului; cele depuse primele, în ordinea depunerii
    public function getCallSubmissions($callId)
    {
        $sql = "SELECT projects.id, projects.submitted_at, projects.created_at, projects.updated_at,
                       users.email, users.firstname, users.lastname, users.faculty
                FROM projects
                JOIN users ON users.id = projects.user_id
                WHERE projects.call_id = ?
                ORDER BY (projects.submitted_at IS NULL), projects.submitted_at, projects.id";
        return DB::getData($sql, [$callId]);
    }

    // O propunere, împreună cu datele propunătorului
    public function getProjectWithProposer($projectId)
    {
        if (empty($projectId)) {
            return false;
        }

        $sql = "SELECT projects.id, projects.call_id, projects.user_id, projects.propunere, projects.submitted_at,
                       projects.created_at, projects.updated_at,
                       users.email, users.firstname, users.lastname, users.faculty
                FROM projects
                JOIN users ON users.id = projects.user_id
                WHERE projects.id = ? LIMIT 1";
        $result = DB::getData($sql, [$projectId]);
        return !empty($result) ? $result[0] : false;
    }

    // Întoarce proiectul la propunător: nu mai este depus, deci poate fi din nou modificat
    public function returnToProposer($projectId)
    {
        DB::insertData("UPDATE projects SET submitted_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$projectId]);
    }
}
