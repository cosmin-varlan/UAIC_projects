<?php
try {
    $dbFile = __DIR__ . DIRECTORY_SEPARATOR . 'database.db';
    if (!file_exists($dbFile)) {
        echo "ERROR: DB file not found: $dbFile";
        exit(1);
    }
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->query("SELECT * FROM calls ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($row);
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage();
}
