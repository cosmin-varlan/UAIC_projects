<?php
define('SLASH', DIRECTORY_SEPARATOR);
define('DIRECTOR_SITE', dirname(__DIR__));
require_once __DIR__ . '/../utils/autoloader.php';
require_once __DIR__ . '/../utils/config.php';

$model = new CallModel();
$data = [
    'id' => 18,
    'title' => 'Updated Title Test',
    'code' => 'C-TEST',
    'description' => json_encode([['label'=>'Nume','type'=>'text']]),
    'objectives' => 'Obj test',
    'eligibility_rules' => 'Elig test',
    'budget_total' => '1000',
    'status' => 'open',
    'submission_start_at' => '2026-08-01',
    'submission_deadline_at' => '2026-08-15',
    'evaluation_start_at' => '2026-08-16',
    'evaluation_end_at' => '2026-08-31',
    'implementation_start_date' => '2026-09-01',
    'implementation_end_date' => '2026-12-31'
];
$res = $model->saveCall($data);
if ($res) echo "OK: updated id=$res\n"; else echo "FAILED\n";
