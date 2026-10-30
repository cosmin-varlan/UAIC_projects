<?php
// Simulate a saveCall request by instantiating CallController with params
define('SLASH', DIRECTORY_SEPARATOR);
define('DIRECTOR_SITE', dirname(__DIR__));
require_once DIRECTOR_SITE . SLASH . 'utils' . SLASH . 'autoloader.php';
require_once DIRECTOR_SITE . SLASH . 'utils' . SLASH . 'config.php';

// start session and set admin user
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user'] = ['id' => 1, 'roles' => [['role' => 'Admin']]];

// prepare params to be forwarded by front controller
$params = [
    'call_id' => 18,
    'title' => 'Simulated Update From CLI',
    'code' => 'CLI-UPDATE',
    'objectives' => 'CLI objectives',
    'eligibility' => 'CLI eligibility',
    'budget' => '5000',
    'status' => 'open',
    'submission_start_at' => '2026-10-01',
    'submission_deadline_at' => '2026-10-15'
];

// instantiate controller which will call saveCall() in constructor
$controller = new CallController('saveCall', $params);

echo "Simulation complete\n";
