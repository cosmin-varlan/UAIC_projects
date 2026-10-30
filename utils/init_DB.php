<?php
// Creează tabelele bazei de date SQLite și o populează cu date de test:
// 5 utilizatori (user1..user5@gmail.com), 3 apeluri din mediul universitar, propuneri, fișiere și evaluări.
//
// Rulare (o singură dată, pe o bază goală):  php utils/init_DB.php
// sau din browser: http://localhost/UAIC_projects/utils/init_DB.php
// Pentru a reface baza de la zero: ștergeți utils/database.db și conținutul directorului uploads/
// (fără uploads/.htaccess), apoi rulați din nou scriptul.

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'callmodel.php';

// Datele calendarului sunt calculate față de ziua rulării, în ora României
date_default_timezone_set('Europe/Bucharest');

DB::getConnection();

$sql = "CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    firstname TEXT NOT NULL,
    lastname TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    faculty TEXT NOT NULL,
    login_token TEXT,
    token_created_at TEXT
)";

DB::justExec($sql);

echo "Tabela 'users' a fost creată cu succes în baza de date SQLite.\n";




// Etapele din calendar (de la submission_start_at la implementation_end_date) se salvează ca text
// „AAAA-LL-ZZ HH:MM” sau doar „AAAA-LL-ZZ” când ora nu e definită
$sql = "CREATE TABLE IF NOT EXISTS calls (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    code TEXT NOT NULL,
    description TEXT,
    objectives TEXT,
    eligibility_rules TEXT,
    budget_total REAL,
    status TEXT,
    submission_start_at TEXT,
    submission_deadline_at TEXT,
    eligibility_check_at TEXT,
    eligibility_results_at TEXT,
    eligibility_appeals_at TEXT,
    eligibility_appeals_results_at TEXT,
    evaluation_start_at TEXT,
    evaluation_end_at TEXT,
    appeals_at TEXT,
    appeals_results_at TEXT,
    beca_approval_at TEXT,
    contracting_at TEXT,
    implementation_start_date TEXT,
    implementation_end_date TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)";

DB::justExec($sql);

echo "Tabela 'calls' a fost creată cu succes în baza de date SQLite.\n";




// submitted_at completat = proiect depus, care nu mai poate fi modificat de propunător
$sql = "CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    propunere TEXT,
    call_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    submitted_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)";

DB::justExec($sql);

echo "Tabela 'projects' a fost creată cu succes în baza de date SQLite.\n";




// Rolurile folosite: Admin (project_id = 0) și Owner (proprietarul unei propuneri).
// Evaluatorii nu au rol aici; ei sunt atribuiți pe apeluri, în tabela call_evaluators.
$sql = "CREATE TABLE IF NOT EXISTS roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    project_id INTEGER NOT NULL,
    role TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";

DB::justExec($sql);

echo "Tabela 'roles' a fost creată cu succes în baza de date SQLite.\n";




// Fișierele încărcate în propuneri: pe disc stă fișierul (stored_name), aici doar metadatele.
// field_id = identificatorul câmpului de tip fișier din formularul apelului (calls.description).
$sql = "CREATE TABLE IF NOT EXISTS uploads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    field_id TEXT NOT NULL,
    original_name TEXT NOT NULL,
    stored_name TEXT NOT NULL UNIQUE,
    mime_type TEXT,
    size_bytes INTEGER,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id)
)";

DB::justExec($sql);

echo "Tabela 'uploads' a fost creată cu succes în baza de date SQLite.\n";




// Evaluatorii unui apel, identificați prin adresa de email (cel mult 5 per apel)
$sql = "CREATE TABLE IF NOT EXISTS call_evaluators (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    call_id INTEGER NOT NULL,
    email TEXT NOT NULL,
    position INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (call_id, email),
    FOREIGN KEY (call_id) REFERENCES calls(id)
)";

DB::justExec($sql);

echo "Tabela 'call_evaluators' a fost creată cu succes în baza de date SQLite.\n";




// Evaluarea unui proiect de către un evaluator: notele pe câmpuri (JSON [id câmp => notă]) și totalul.
// finalized_at completat = evaluare finalizată, care nu mai poate fi modificată.
$sql = "CREATE TABLE IF NOT EXISTS evaluations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    evaluator_email TEXT NOT NULL,
    scores TEXT,
    total REAL,
    finalized_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (project_id, evaluator_email),
    FOREIGN KEY (project_id) REFERENCES projects(id)
)";

DB::justExec($sql);

echo "Tabela 'evaluations' a fost creată cu succes în baza de date SQLite.\n";




// ---------------------------------------------------------------------------------------------
// Date de test. Se inserează doar într-o bază goală, ca rularea repetată să nu dubleze nimic.
// ---------------------------------------------------------------------------------------------

$existingUsers = DB::getData("SELECT COUNT(*) AS n FROM users", []);
if ($existingUsers[0]['n'] > 0) {
    echo "\nBaza de date conține deja utilizatori, deci datele de test NU au fost inserate.\n";
    echo "Pentru o bază nouă: ștergeți utils/database.db și conținutul directorului uploads/ (fără .htaccess), apoi rulați din nou scriptul.\n";
    exit();
}

// O zi din calendar față de ziua de azi, cu oră opțională: day(30, '16:00') = „AAAA-LL-ZZ 16:00” peste 30 de zile
function day($offsetDays, $time = '')
{
    return date('Y-m-d', strtotime(($offsetDays >= 0 ? '+' : '') . $offsetDays . ' days')) . ($time !== '' ? ' ' . $time : '');
}

// Un moment trecut, în formatul folosit de submitted_at / finalized_at
function moment($offsetDays, $time)
{
    return day($offsetDays) . ' ' . $time . ':00';
}

// Calendarul unui apel pornind de la ziua începerii depunerilor; ora 16:00 doar la termenul limită
// și la cele două etape de contestații (ca în editorul de apeluri)
function timeline($start)
{
    return [
        'submission_start_at'            => day($start),
        'submission_deadline_at'         => day($start + 40, '16:00'),
        'eligibility_check_at'           => day($start + 42),
        'eligibility_results_at'         => day($start + 45),
        'eligibility_appeals_at'         => day($start + 47, '16:00'),
        'eligibility_appeals_results_at' => day($start + 50),
        'evaluation_start_at'            => day($start + 52),
        'evaluation_end_at'              => day($start + 75),
        'appeals_at'                     => day($start + 77, '16:00'),
        'appeals_results_at'             => day($start + 82),
        'beca_approval_at'               => day($start + 85),
        'contracting_at'                 => day($start + 90),
        'implementation_start_date'      => day($start + 105),
        'implementation_end_date'        => day($start + 470),
    ];
}

// Un PDF minimal, valid, cu un singur rând de text (pentru fișierele încărcate în propuneri)
function makePdf($text)
{
    $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    $stream = "BT /F1 14 Tf 72 760 Td ($text) Tj ET";
    $objects = [
        "<< /Type /Catalog /Pages 2 0 R >>",
        "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
        "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream",
        "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xrefPosition = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPosition . "\n%%EOF\n";
    return $pdf;
}

$db = DB::getConnection();
$db->beginTransaction();

function insertRow($table, $row)
{
    $columns = array_keys($row);
    $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")";
    DB::insertData($sql, array_values($row));
    return DB::getConnection()->lastInsertId();
}

// --- Utilizatori: user1 este administrator, user2 și user3 evaluatori, user4 și user5 propunători ---

$users = [
    'user1' => ['firstname' => 'Andrei', 'lastname' => 'Popa',      'email' => 'user1@gmail.com', 'faculty' => 'Facultatea de Informatică'],
    'user2' => ['firstname' => 'Elena',  'lastname' => 'Ionescu',   'email' => 'user2@gmail.com', 'faculty' => 'Facultatea de Chimie'],
    'user3' => ['firstname' => 'Mihai',  'lastname' => 'Rusu',      'email' => 'user3@gmail.com', 'faculty' => 'Facultatea de Economie și Administrarea Afacerilor'],
    'user4' => ['firstname' => 'Ioana',  'lastname' => 'Munteanu',  'email' => 'user4@gmail.com', 'faculty' => 'Facultatea de Biologie'],
    'user5' => ['firstname' => 'Radu',   'lastname' => 'Ciobanu',   'email' => 'user5@gmail.com', 'faculty' => 'Facultatea de Istorie'],
];
$userIds = [];
foreach ($users as $key => $user) {
    $userIds[$key] = insertRow('users', $user);
}
insertRow('roles', ['user_id' => $userIds['user1'], 'project_id' => 0, 'role' => 'Admin']);

echo "\nAu fost creați 5 utilizatori (user1@gmail.com este administrator).\n";

// --- Apeluri: câmpurile cu punctaj însumează 100 de puncte la fiecare apel ---

$brainmapField = CallModel::getFixedFields();

$calls = [
    // Deschis: depunerile sunt în curs (termenul limită peste aproximativ o lună)
    'research' => [
        'title' => 'Granturi interne pentru tineri cercetători 2027',
        'code' => 'UAIC-TC-2027',
        'objectives' => "Sprijinirea tinerilor cercetători din universitate în obținerea primelor rezultate independente.\nCreșterea numărului de publicații în reviste indexate și pregătirea unor propuneri pentru competiții naționale.",
        'eligibility_rules' => "Directorul de proiect este cadru didactic sau cercetător al universității, cu doctoratul obținut în ultimii 7 ani.\nO persoană poate depune o singură propunere la acest apel.",
        'budget_total' => 300000,
        'status' => 'open',
        'start' => -10,
        'fields' => [
            ['id' => 'project_title', 'label' => 'Titlul proiectului', 'type' => 'text', 'description' => '', 'required' => true, 'score' => 0],
            ['id' => 'research_field', 'label' => 'Domeniul de cercetare', 'type' => 'select', 'description' => '', 'required' => true, 'score' => 0,
                'options' => ['Științe exacte', 'Științe ale vieții', 'Științe sociale', 'Științe umaniste']],
            ['id' => 'abstract', 'label' => 'Rezumatul proiectului', 'type' => 'textarea', 'description' => 'Cel mult 300 de cuvinte.', 'required' => true, 'score' => 20],
            ['id' => 'objectives_activities', 'label' => 'Obiective și activități', 'type' => 'textarea', 'description' => 'Obiectivele proiectului și activitățile prin care vor fi atinse.', 'required' => true, 'score' => 30],
            ['id' => 'team', 'label' => 'Echipa de cercetare', 'type' => 'textarea', 'description' => 'Membrii echipei și rolul fiecăruia.', 'required' => true, 'score' => 20],
            ['id' => 'impact', 'label' => 'Impact și diseminare', 'type' => 'textarea', 'description' => 'Rezultatele așteptate și modul în care vor fi făcute publice.', 'required' => true, 'score' => 20],
            ['id' => 'requested_budget', 'label' => 'Buget solicitat (lei)', 'type' => 'number', 'description' => 'Cel mult 30.000 lei.', 'required' => true, 'score' => 0],
            ['id' => 'work_plan', 'label' => 'Plan de lucru (PDF)', 'type' => 'file', 'description' => 'Graficul activităților pe luni.', 'required' => true, 'score' => 10, 'fileFormats' => '.pdf'],
        ],
        'evaluators' => ['user2@gmail.com', 'user3@gmail.com'],
    ],
    // Închis: depunerile s-au încheiat, evaluarea este în desfășurare
    'mobility' => [
        'title' => 'Mobilități academice internaționale 2026',
        'code' => 'UAIC-MOB-2026',
        'objectives' => "Finanțarea stagiilor de predare și cercetare ale cadrelor didactice la universități partenere din străinătate.",
        'eligibility_rules' => "Cadre didactice titulare ale universității.\nStagiul durează între 5 și 30 de zile și se desfășoară în perioada de implementare a apelului.",
        'budget_total' => 120000,
        'status' => 'closed',
        'start' => -60,
        'fields' => [
            ['id' => 'host_university', 'label' => 'Universitatea gazdă', 'type' => 'text', 'description' => '', 'required' => true, 'score' => 0],
            ['id' => 'mobility_type', 'label' => 'Tipul mobilității', 'type' => 'radio', 'description' => '', 'required' => true, 'score' => 0,
                'options' => ['Predare', 'Cercetare', 'Formare']],
            ['id' => 'purpose', 'label' => 'Scopul mobilității', 'type' => 'textarea', 'description' => '', 'required' => true, 'score' => 40],
            ['id' => 'teaching_relevance', 'label' => 'Relevanța pentru activitatea didactică', 'type' => 'textarea', 'description' => '', 'required' => true, 'score' => 30],
            ['id' => 'dissemination', 'label' => 'Plan de diseminare', 'type' => 'textarea', 'description' => '', 'required' => true, 'score' => 20],
            ['id' => 'acceptance_letter', 'label' => 'Scrisoarea de acceptare (PDF)', 'type' => 'file', 'description' => 'Emisă de universitatea gazdă.', 'required' => true, 'score' => 10, 'fileFormats' => '.pdf'],
        ],
        'evaluators' => ['user2@gmail.com', 'user3@gmail.com'],
    ],
    // Draft: apel în pregătire, încă nepublicat
    'labs' => [
        'title' => 'Modernizarea laboratoarelor didactice 2027',
        'code' => 'UAIC-LAB-2027',
        'objectives' => "Dotarea laboratoarelor folosite la activitățile didactice de licență și masterat cu echipamente noi.",
        'eligibility_rules' => "Propunerile sunt depuse de directorii de departament.",
        'budget_total' => 500000,
        'status' => 'draft',
        'start' => 30,
        'fields' => [
            ['id' => 'laboratory', 'label' => 'Laboratorul vizat', 'type' => 'text', 'description' => '', 'required' => true, 'score' => 0],
            ['id' => 'current_state', 'label' => 'Starea actuală a laboratorului', 'type' => 'textarea', 'description' => '', 'required' => true, 'score' => 30],
            ['id' => 'equipment', 'label' => 'Echipamente solicitate', 'type' => 'textarea', 'description' => '', 'required' => true, 'score' => 40],
            ['id' => 'beneficiaries', 'label' => 'Beneficiari', 'type' => 'checkbox', 'description' => 'Programele de studii care vor folosi laboratorul.', 'required' => true, 'score' => 0,
                'options' => ['Licență', 'Masterat', 'Doctorat']],
            ['id' => 'usage_plan', 'label' => 'Planul de utilizare', 'type' => 'textarea', 'description' => '', 'required' => true, 'score' => 30],
        ],
        'evaluators' => ['user2@gmail.com'],
    ],
];

$callIds = [];
foreach ($calls as $key => $call) {
    $row = [
        'title' => $call['title'],
        'code' => $call['code'],
        // Câmpul fix „Codul Brainmap” este primul, ca la apelurile salvate din editor
        'description' => json_encode(array_merge($brainmapField, $call['fields']), JSON_UNESCAPED_UNICODE),
        'objectives' => $call['objectives'],
        'eligibility_rules' => $call['eligibility_rules'],
        'budget_total' => $call['budget_total'],
        'status' => $call['status'],
    ];
    $callIds[$key] = insertRow('calls', array_merge($row, timeline($call['start'])));

    foreach ($call['evaluators'] as $position => $email) {
        insertRow('call_evaluators', ['call_id' => $callIds[$key], 'email' => $email, 'position' => $position + 1]);
    }
}

echo "Au fost create 3 apeluri: unul deschis, unul închis (în evaluare) și unul în pregătire (draft).\n";

// --- Propuneri: user4 și user5 depun proiecte (evaluatorii nu depun la apelurile lor) ---

$projects = [
    [
        'call' => 'research', 'user' => 'user5', 'submitted_at' => moment(-3, '11:20'),
        'answers' => [
            'brainmap_code' => 'U-1700-039R-5521',
            'project_title' => 'Arhivele familiilor boierești din Moldova (1750-1850): digitizare și analiză',
            'research_field' => 'Științe umaniste',
            'abstract' => "Proiectul propune digitizarea și catalogarea a peste 2.000 de documente din arhivele unor familii boierești moldovene, aflate în fondurile Bibliotecii Centrale Universitare.\nDocumentele vor fi transcrise, indexate și publicate într-o bază de date accesibilă cercetătorilor.",
            'objectives_activities' => "O1. Digitizarea documentelor (lunile 1-5).\nO2. Transcrierea și indexarea lor (lunile 4-10).\nO3. Publicarea bazei de date și a unui studiu introductiv (lunile 10-12).",
            'team' => "Dr. Radu Ciobanu - director de proiect\nDrd. Ana Lungu - transcriere și indexare\nMasterand Victor Toma - digitizare",
            'impact' => "Baza de date va fi deschisă publicului. Rezultatele vor fi prezentate la conferința anuală a facultății și publicate într-un articol în „Historia Universitatis Iassiensis”.",
            'requested_budget' => '28500',
        ],
        'files' => ['work_plan' => 'Plan de lucru - Arhive boierești.pdf'],
    ],
    [
        'call' => 'research', 'user' => 'user4', 'submitted_at' => null,
        'answers' => [
            'brainmap_code' => 'U-1700-041B-7310',
            'project_title' => 'Microplasticele din râul Bahlui: surse și efecte asupra nevertebratelor acvatice',
            'research_field' => 'Științe ale vieții',
            'abstract' => "Proiectul va măsura concentrația de microplastice în 10 puncte de pe cursul râului Bahlui și va evalua efectele acestora asupra a trei specii de nevertebrate.",
            'objectives_activities' => '',
            'team' => '',
            'impact' => '',
            'requested_budget' => '30000',
        ],
        'files' => [],
    ],
    [
        'call' => 'mobility', 'user' => 'user4', 'submitted_at' => moment(-32, '14:05'),
        'answers' => [
            'brainmap_code' => 'U-1700-041B-7310',
            'host_university' => 'Universität Wien',
            'mobility_type' => 'Cercetare',
            'purpose' => "Stagiu de 14 zile în laboratorul de ecologie acvatică, pentru însușirea metodelor de identificare a microplasticelor prin spectroscopie FTIR.",
            'teaching_relevance' => "Metodele învățate vor fi introduse în lucrările practice ale cursului „Ecologie acvatică” de la masterat.",
            'dissemination' => "Seminar deschis pentru studenți și cadre didactice la întoarcere; raport publicat pe site-ul facultății.",
        ],
        'files' => ['acceptance_letter' => 'Scrisoare de acceptare - Universitat Wien.pdf'],
    ],
    [
        'call' => 'mobility', 'user' => 'user5', 'submitted_at' => moment(-31, '09:40'),
        'answers' => [
            'brainmap_code' => 'U-1700-039R-5521',
            'host_university' => 'Università di Bologna',
            'mobility_type' => 'Predare',
            'purpose' => "Predarea unui modul de 8 ore despre istoria universităților din Europa de Est, în cadrul programului de master „Storia d'Europa”.",
            'teaching_relevance' => "Schimbul de experiență va contribui la actualizarea cursului opțional de istorie culturală.",
            'dissemination' => "Prezentare la ședința departamentului și articol de popularizare în revista universității.",
        ],
        'files' => ['acceptance_letter' => 'Lettera di accettazione - Bologna.pdf'],
    ],
];

$projectIds = [];
foreach ($projects as $index => $project) {
    $projectId = insertRow('projects', [
        'propunere' => json_encode($project['answers'], JSON_UNESCAPED_UNICODE),
        'call_id' => $callIds[$project['call']],
        'user_id' => $userIds[$project['user']],
        'submitted_at' => $project['submitted_at'],
    ]);
    $projectIds[$index] = $projectId;
    insertRow('roles', ['user_id' => $userIds[$project['user']], 'project_id' => $projectId, 'role' => 'Owner']);

    // Fișierele: pe disc în uploads/<id apel>/<id proiect>/, cu nume aleator, ca la încărcarea din aplicație
    foreach ($project['files'] as $fieldId => $originalName) {
        $directory = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $callIds[$project['call']] . DIRECTORY_SEPARATOR . $projectId;
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $storedName = bin2hex(random_bytes(16)) . '.pdf';
        $content = makePdf(pathinfo($originalName, PATHINFO_FILENAME) . ' (document de test)');
        file_put_contents($directory . DIRECTORY_SEPARATOR . $storedName, $content);
        insertRow('uploads', [
            'project_id' => $projectId,
            'field_id' => $fieldId,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
        ]);
    }
}

echo "Au fost create 4 propuneri (3 depuse, 1 ciornă), cu fișierele lor în uploads/.\n";

// --- Evaluări la apelul închis: una finalizată, una în lucru ---

$evaluations = [
    // user2 a finalizat evaluarea proiectului lui user4 (mobilitate)
    ['project' => 2, 'evaluator' => 'user2@gmail.com', 'finalized_at' => moment(-5, '17:30'),
        'scores' => ['purpose' => 36, 'teaching_relevance' => 25, 'dissemination' => 17, 'acceptance_letter' => 10]],
    // user3 a început evaluarea aceluiași proiect și a salvat-o pentru a o continua
    ['project' => 2, 'evaluator' => 'user3@gmail.com', 'finalized_at' => null,
        'scores' => ['purpose' => 32.5, 'teaching_relevance' => 27]],
];
foreach ($evaluations as $evaluation) {
    insertRow('evaluations', [
        'project_id' => $projectIds[$evaluation['project']],
        'evaluator_email' => $evaluation['evaluator'],
        'scores' => json_encode($evaluation['scores']),
        'total' => array_sum($evaluation['scores']),
        'finalized_at' => $evaluation['finalized_at'],
    ]);
}

$db->commit();

echo "Au fost create 2 evaluări (una finalizată, una în lucru).\n";
echo "\nAutentificare: introduceți adresa (de ex. user1@gmail.com) pe pagina de conectare; linkul apare în linkuri.txt.\n";
