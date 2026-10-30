<?php

class DB{
    private static $db = NULL;
    public static function getConnection(){        
        if (is_null(self::$db)){
            /*
            $servername = 'localhost';
            $username = '...';
            $password = '...';
            $dbname = '...';
                            
            self::$db   = new PDO("mysql:host=".$servername.";dbname=".$dbname, $username, $password);
            if (!self::$db ) {
                $e = oci_error();
                trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
            }
            */

            $dbFile = __DIR__ . DIRECTORY_SEPARATOR . 'database.db';
            $dsn = 'sqlite:' . $dbFile;

            self::$db = new PDO($dsn);
            self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }

        return self::$db;
    }
    public static function getData($sql, $params){

        if (is_null(self::$db)) self::getConnection();

        $stmt = self::$db->prepare($sql);
        $stmt->execute($params); 

        //$rows = $stmt->fetchAll(PDO::FETCH_NUM);    
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);    
  
        return $rows;
    }

    public static function insertData($sql, $params){
        if (is_null(self::$db)) self::getConnection();

        $stm = self::$db->prepare($sql);

        if (count($params)>0){
            for($i=0; $i<count($params); $i++)
            {
                 $stm->bindValue($i+1,  $params[$i]);
            }
        }
        $res = $stm->execute();
    }    

    public static function justExec($sql){
        if (is_null(self::$db)) self::getConnection();
        $stm = self::$db->prepare($sql);       
        $stm->execute();             
    }
}

//  $data = DB::getData("SELECT id, nume FROM persoane WHERE varsta = ? ", [30]);
//  DB::insertData("INSERT INTO persoane(`nume`) values (?)", ["gigel"]);
//  DB::insertData("UPDATE persoane SET nume = ? WHERE id = ?", ["vasile",1]);
//  DB::insertData("DELETE FROM persoane WHERE id = ?", [1]);