<?php
class UserModel 
{
    public function __construct()
    {

    }

    public function auth($email){
        $data = DB::getData("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
        if (count($data) > 0) {
            $data[0]['roles'] = $this->getUserRoles($email);
            return $data[0];
        }
        return null;
    }

    public function createLoginToken($email){
        $token = bin2hex(random_bytes(16));
        $timestamp = date('Y-m-d H:i:s');
        DB::insertData(
            "UPDATE users SET login_token = ?, token_created_at = ? WHERE email = ?",
            [$token, $timestamp, $email]
        );
        return $token;
    }

    public function ensureLoginToken($email){
        $data = DB::getData("SELECT login_token FROM users WHERE email = ? LIMIT 1", [$email]);
        if (count($data) > 0) {
            $row = $data[0];
            if (!empty($row['login_token'])) {
                return $row['login_token'];
            }
        }
        return $this->createLoginToken($email);
    }

    public function getUserByToken($token){
        $data = DB::getData("SELECT * FROM users WHERE login_token = ? LIMIT 1", [$token]);
        if (count($data) > 0) {
            $data[0]['roles'] = $this->getUserRoles($data[0]['email']);
            return $data[0];
        }
        return null;
    }

    public function createUser($firstname, $lastname, $email, $faculty){
        $data = DB::getData("SELECT * FROM users WHERE email = ? LIMIT 1", [$_SESSION['user']['email']]);
        if (count($data) > 0) {
            DB::insertData(
                "UPDATE users SET firstname = ?, lastname = ?, faculty = ? WHERE email = ?",
                [$firstname, $lastname, $faculty, $email]
            );
        }
        else
        {
            DB::insertData(
                "INSERT INTO users (firstname, lastname, email, faculty) VALUES (?, ?, ?, ?)",
                [$firstname, $lastname, $email, $faculty]
            );
        }
    }

    public function clearLoginToken($email){
        DB::insertData(
            "UPDATE users SET login_token = '', token_created_at = NULL WHERE email = ?",
            [$email]
        );
    }

    public function getUserRoles($email){
        $data = DB::getData("SELECT project_id, role FROM roles JOIN users ON roles.user_id = users.id WHERE users.email = ?", [$email]);
        return $data;
    }

      
}