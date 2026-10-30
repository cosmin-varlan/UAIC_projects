<?php
class ProjectsView
{
    public function __construct(){
	}

    // Lista apelurilor deschise; $userProjectIds = [id apel => id propunere] pentru apelurile la care utilizatorul are deja o propunere
    // $evaluatorCallIds = apelurile la care utilizatorul este evaluator (la ele nu poate depune proiecte)
    public function displayCalls($calls, $userProjectIds = [], $evaluatorCallIds = [])
    {
        $callsHtml = '';

        if (!empty($calls) && is_array($calls)) {
            foreach ($calls as $call) {
                $callUrl = SITE_URL . '/projectsController/viewCall/' . intval($call['id']);

                $callsHtml .= '<div class="card shadow-sm mb-4"><div class="card-body">';
                $callsHtml .= '<h4 class="card-title"><span class="call-title">' . $this->escape($call['title'] ?? '') . '</span> <small class="text-muted">(' . $this->escape($call['code'] ?? '') . ')</small></h4>';
                $callsHtml .= $this->textBlock('Obiective', $call['objectives'] ?? '');
                $callsHtml .= $this->textBlock('Reguli de eligibilitate', $call['eligibility_rules'] ?? '');
                $callsHtml .= $this->periodLine('Perioada de depunere', $call['submission_start_at'] ?? '', $call['submission_deadline_at'] ?? '');
                $callsHtml .= $this->budgetLine($call['budget_total'] ?? null);
                // Fiecare utilizator are o singură propunere per apel; dacă există, butonul duce la ea
                if (isset($userProjectIds[$call['id']])) {
                    $projectUrl = SITE_URL . '/projectsController/editProject/' . intval($userProjectIds[$call['id']]);
                    $callsHtml .= '<a href="' . $projectUrl . '" class="btn btn-secondary mt-3">Vezi propunerea dvs.</a>';
                } elseif (in_array($call['id'], $evaluatorCallIds)) {
                    $callsHtml .= '<p class="text-muted mt-3 mb-0">Sunteți evaluator la acest apel, deci nu puteți depune proiecte la el.</p>';
                } else {
                    $callsHtml .= '<a href="' . $callUrl . '" class="btn btn-primary mt-3">Detalii și depunere proiect</a>';
                }
                $callsHtml .= '</div></div>';
            }
        } else {
            $callsHtml = '<div class="alert alert-info">Nu există apeluri deschise în acest moment.</div>';
        }

        return $this->renderPage('Propuneri', 'Apeluri deschise', $callsHtml);
    }

    // Detaliile apelului și formularul propunerii (gol, precompletat sau doar pentru citire)
    // $readOnlyMessage înlocuiește mesajul implicit afișat când formularul e doar pentru citire
    public function proposalPage($call, $fields, $project, $answers, $uploads, $errors, $readOnly, $errorMessage = '', $readOnlyMessage = '')
    {
        if ($errorMessage === '') {
            $errorMessage = 'Propunerea nu a fost salvată. Corectați câmpurile marcate; fișierele alese trebuie selectate din nou.';
        }
        $isEdit = !empty($project);

        $content = $isEdit
            ? '<p><a href="' . SITE_URL . '/projectsController/myProjects">&laquo; Înapoi la proiectele dvs.</a></p>'
            : '<p><a href="' . SITE_URL . '/projectsController/displayCalls">&laquo; Înapoi la apelurile deschise</a></p>';

        $content .= $this->callDetails($call);

        $content .= '<div class="card shadow-sm"><div class="card-body">';
        $content .= '<h4 class="card-title">' . ($isEdit ? 'Propunerea dvs.' : 'Propunere nouă') . '</h4>';

        $isSubmitted = $isEdit && !empty($project['submitted_at']);
        if ($isSubmitted) {
            $content .= '<div class="alert alert-success">Proiectul a fost depus la ' . $this->escape($project['submitted_at']) . ' și nu mai poate fi modificat.</div>';
        } elseif ($readOnly && $readOnlyMessage !== '') {
            $content .= '<div class="alert alert-warning">' . $this->escape($readOnlyMessage) . '</div>';
        } elseif ($readOnly && CallModel::isSubmissionPeriodOver($call)) {
            $content .= '<div class="alert alert-warning">Termenul limită de depunere a expirat, deci propunerea nu mai poate fi modificată sau depusă.</div>';
        } elseif ($readOnly) {
            $content .= '<div class="alert alert-warning">Apelul nu mai este deschis, deci propunerea nu mai poate fi modificată.</div>';
        }
        if (count($errors) > 0) {
            $content .= '<div class="alert alert-danger">' . $this->escape($errorMessage) . '</div>';
        }
        if (!$readOnly && count($fields) > 0) {
            $content .= '<p class="text-muted small">Câmpurile marcate cu * sunt obligatorii doar la depunere; până atunci puteți salva și un formular incomplet.</p>';
        }
        if (count($fields) === 0) {
            $content .= '<div class="alert alert-info">Acest apel nu are câmpuri de completat.</div>';
        }

        $content .= '<form method="post" enctype="multipart/form-data" action="' . SITE_URL . '/projectsController/saveProject">';
        $content .= '<input type="hidden" name="call_id" value="' . intval($call['id']) . '">';
        $content .= '<input type="hidden" name="project_id" value="' . ($isEdit ? intval($project['id']) : '') . '">';
        $content .= '<fieldset' . ($readOnly ? ' disabled' : '') . '>';

        foreach ($fields as $field) {
            $fieldId = $field['id'];
            $content .= $this->formField(
                $field,
                isset($answers[$fieldId]) ? $answers[$fieldId] : null,
                isset($uploads[$fieldId]) ? $uploads[$fieldId] : null,
                isset($errors[$fieldId]) ? $errors[$fieldId] : ''
            );
        }

        $content .= '</fieldset>';
        if (!$readOnly) {
            $content .= '<div class="text-right">';
            // Salvarea simplă sare peste validarea din browser, ca să accepte și un formular incomplet
            $content .= '<button type="submit" formnovalidate class="btn btn-primary">' . ($isEdit ? 'Salvează modificările' : 'Salvează propunerea') . '</button>';
            // Depunerea salvează întâi formularul, apoi blochează proiectul; confirmarea se cere doar dacă formularul e complet
            $content .= ' <button type="submit" name="submit_project" value="1" class="btn btn-success ml-2" onclick="return !this.form.checkValidity() || confirm(\'După depunere proiectul nu mai poate fi modificat, ci doar vizualizat. Depuneți proiectul?\');">Depunere proiect</button>';
            $content .= '</div>';
        }
        $content .= '</form>';
        $content .= '</div></div>';

        $heading = $this->escape($call['title'] ?? '') . ' <small class="text-muted">(' . $this->escape($call['code'] ?? '') . ')</small>';
        return $this->renderPage($isEdit ? 'Propunerea dvs.' : 'Propunere nouă', $heading, $content);
    }

    // Lista propunerilor utilizatorului
    public function myProjects($projects, $message = '')
    {
        $content = '';
        if (!empty($message)) {
            $content .= '<div class="alert alert-success">' . $this->escape($message) . '</div>';
        }

        if (!empty($projects) && is_array($projects)) {
            $content .= '<div class="card shadow-sm"><div class="card-body">';
            $content .= '<div class="table-responsive">';
            $content .= '<table class="table table-bordered table-hover">';
            $content .= '<thead class="thead-light"><tr>';
            $content .= '<th>Nr.</th><th>Apel</th><th>Stare apel</th><th>Stare proiect</th><th>Creată la</th><th>Modificată la</th><th>Acțiuni</th>';
            $content .= '</tr></thead><tbody>';
            foreach ($projects as $project) {
                $callStatus = $project['call_status'] ?? '';
                $callName = isset($project['call_title'])
                    ? $this->escape($project['call_title']) . ' <small class="text-muted">(' . $this->escape($project['call_code'] ?? '') . ')</small>'
                    : '<span class="text-muted">apel șters</span>';
                $editUrl = SITE_URL . '/projectsController/editProject/' . intval($project['id']);

                $content .= '<tr>';
                $content .= '<td>' . intval($project['id']) . '</td>';
                $content .= '<td>' . $callName . '</td>';
                $content .= '<td>' . $this->escape($this->statusLabel($callStatus)) . '</td>';
                $isSubmitted = !empty($project['submitted_at']);
                $deadlinePassed = CallModel::isSubmissionPeriodOver($project);
                $projectStatus = $isSubmitted ? 'Depus la ' . $this->escape($project['submitted_at']) : ($deadlinePassed ? 'Nedepus (termen expirat)' : 'Nedepus');
                $content .= '<td>' . $projectStatus . '</td>';
                $content .= '<td>' . $this->escape($project['created_at'] ?? '') . '</td>';
                $content .= '<td>' . $this->escape($project['updated_at'] ?? '') . '</td>';
                // Proiectele depuse, cele cu termen expirat și cele de la apeluri care nu mai sunt deschise pot fi doar consultate
                $isEditable = ($callStatus === 'open' && !$deadlinePassed && !$isSubmitted);
                $content .= '<td><a href="' . $editUrl . '" class="btn btn-sm btn-primary">' . ($isEditable ? 'Editează' : 'Vezi') . '</a></td>';
                $content .= '</tr>';
            }
            $content .= '</tbody></table></div></div></div>';
        } else {
            $content .= '<div class="alert alert-info">Nu aveți nicio propunere salvată. Puteți depune una din pagina <a href="' . SITE_URL . '/projectsController/displayCalls">Propuneri</a>.</div>';
        }

        return $this->renderPage('Proiectele dvs.', 'Proiectele dvs.', $content);
    }

    // „De evaluat”: apelurile atribuite evaluatorului, fiecare cu numărul de proiecte depuse
    public function toEvaluatePage($calls)
    {
        if (empty($calls)) {
            $content = '<div class="alert alert-info">Nu ați fost desemnat evaluator la niciun apel.</div>';
            return $this->renderPage('De evaluat', 'De evaluat', $content);
        }

        $content = '<p>Alegeți un apel pentru a vedea proiectele depuse la el.</p>';
        $content .= '<div class="card shadow-sm"><div class="card-body">';
        $content .= '<div class="table-responsive">';
        $content .= '<table class="table table-bordered table-hover">';
        $content .= '<thead class="thead-light"><tr><th>Apel</th><th>Stare apel</th><th>Termen limită depuneri</th><th>Proiecte depuse</th><th>Acțiuni</th></tr></thead><tbody>';
        foreach ($calls as $call) {
            $callUrl = SITE_URL . '/projectsController/toEvaluate/' . intval($call['id']);
            $deadline = $this->formatDateTime($call['submission_deadline_at'] ?? '');
            $content .= '<tr>';
            $content .= '<td><a href="' . $callUrl . '">' . $this->escape($call['title']) . '</a> <small class="text-muted">(' . $this->escape($call['code']) . ')</small></td>';
            $content .= '<td>' . $this->escape($this->statusLabel($call['status'] ?? '')) . '</td>';
            $content .= '<td>' . ($deadline !== '' ? $deadline : '<span class="text-muted">&mdash;</span>') . '</td>';
            $content .= '<td>' . intval($call['submitted_count']) . '</td>';
            $content .= '<td class="text-nowrap"><a href="' . $callUrl . '" class="btn btn-sm btn-primary">Vezi proiectele</a></td>';
            $content .= '</tr>';
        }
        $content .= '</tbody></table></div></div></div>';

        return $this->renderPage('De evaluat', 'De evaluat', $content);
    }

    // Proiectele depuse la un apel pe care utilizatorul îl evaluează, cu starea evaluării lui la fiecare
    // ($evaluations = [id proiect => evaluare], $maxTotal = punctajul maxim al apelului)
    public function toEvaluateCallPage($call, $projects, $evaluations, $maxTotal, $evaluatorEmail)
    {
        $content = '<p><a href="' . SITE_URL . '/projectsController/toEvaluate">&laquo; Înapoi la apelurile de evaluat</a></p>';

        if (empty($projects)) {
            $content .= '<div class="alert alert-info">Nu există încă proiecte depuse la acest apel.</div>';
        } else {
            $content .= '<p>' . count($projects) . ' ' . (count($projects) === 1 ? 'proiect depus' : 'proiecte depuse') . '.</p>';
            $content .= '<div class="card shadow-sm"><div class="card-body">';
            $content .= '<div class="table-responsive">';
            $content .= '<table class="table table-bordered table-hover">';
            $content .= '<thead class="thead-light"><tr><th>Nr.</th><th>Propunător</th><th>Email</th><th>Depus la</th><th>Evaluarea mea</th><th>Acțiuni</th></tr></thead><tbody>';
            foreach ($projects as $project) {
                $projectId = intval($project['id']);
                $evaluateUrl = SITE_URL . '/evaluationController/evaluate/' . $projectId;
                $evaluation = isset($evaluations[$project['id']]) ? $evaluations[$project['id']] : null;

                // Starea: neîncepută, punctaj + „de continuat” sau punctaj + „final”;
                // propriul proiect (posibil doar în date mai vechi decât regula) nu se evaluează
                $isOwnProject = (strtolower(trim($project['email'])) === strtolower(trim($evaluatorEmail)));
                if ($isOwnProject) {
                    $status = '<span class="text-muted">Propriul proiect &ndash; nu poate fi evaluat</span>';
                    $buttonLabel = '';
                } elseif (!$evaluation) {
                    $status = '<span class="text-muted">Neîncepută</span>';
                    $buttonLabel = 'Evaluează';
                } elseif (empty($evaluation['finalized_at'])) {
                    $status = $this->formatScore($evaluation['total']) . ' / ' . $this->formatScore($maxTotal) . ' &ndash; <span class="text-warning font-weight-bold">de continuat</span>';
                    $buttonLabel = 'Continuă evaluarea';
                } else {
                    $status = $this->formatScore($evaluation['total']) . ' / ' . $this->formatScore($maxTotal) . ' &ndash; <span class="text-success font-weight-bold">final</span>';
                    $buttonLabel = 'Vezi evaluarea';
                }

                $content .= '<tr>';
                $content .= '<td>' . $projectId . '</td>';
                $content .= '<td>' . $this->escape(trim($project['lastname'] . ' ' . $project['firstname'])) . '</td>';
                $content .= '<td><a href="mailto:' . $this->escape($project['email']) . '">' . $this->escape($project['email']) . '</a></td>';
                $content .= '<td>' . $this->escape($project['submitted_at']) . '</td>';
                $content .= '<td>' . $status . '</td>';
                $content .= '<td class="text-nowrap">' . ($buttonLabel !== '' ? '<a href="' . $evaluateUrl . '" class="btn btn-sm btn-primary">' . $buttonLabel . '</a>' : '') . '</td>';
                $content .= '</tr>';
            }
            $content .= '</tbody></table></div></div></div>';
        }

        $heading = 'De evaluat: ' . $this->escape($call['title'] ?? '') . ' <small class="text-muted">(' . $this->escape($call['code'] ?? '') . ')</small>';
        return $this->renderPage('De evaluat', $heading, $content);
    }

    // Pagină simplă cu un mesaj (apel inexistent, acces refuzat, eroare la salvare)
    public function messagePage($heading, $message)
    {
        $content = '<div class="alert alert-warning">' . $this->escape($message) . '</div>';
        $content .= '<p><a href="' . SITE_URL . '/projectsController/displayCalls">Apeluri deschise</a> | <a href="' . SITE_URL . '/projectsController/myProjects">Proiectele dvs.</a></p>';
        return $this->renderPage($heading, $this->escape($heading), $content);
    }

    private function renderPage($pageTitle, $headingHtml, $contentHtml)
    {
        $template = new Template(DIRECTOR_SITE.SLASH."views".SLASH."TPL".SLASH."projectpage.tpl");
        $template->assign('URLSite', SITE_URL);
        $template->assign('MENU', Menu::render());
        $template->assign('PAGE_TITLE', $this->escape($pageTitle));
        // Conținutul venit de la utilizatori se pune ultimul, ca să nu mai fie căutate marcaje în el
        $template->assign('HEADING', $headingHtml);
        $template->assign('CONTENT', $contentHtml);
        return $template->show();
    }

    // Toate informațiile apelului, mai puțin id-ul și definiția formularului
    private function callDetails($call)
    {
        $html = '<div class="card shadow-sm mb-4"><div class="card-body">';
        $html .= '<h4 class="card-title">Despre apel</h4>';
        $html .= '<p class="mb-2"><strong>Stare:</strong> ' . $this->escape($this->statusLabel($call['status'] ?? '')) . '</p>';
        $html .= $this->textBlock('Obiective', $call['objectives'] ?? '');
        $html .= $this->textBlock('Reguli de eligibilitate', $call['eligibility_rules'] ?? '');
        $html .= $this->budgetLine($call['budget_total'] ?? null);

        // Calendarul apelului, doar etapele care au o dată stabilită
        $timelineItems = '';
        foreach (CallModel::getTimelineFields() as $column => $label) {
            $date = $this->formatDateTime($call[$column] ?? '');
            if ($date !== '') {
                $timelineItems .= '<li><strong>' . $this->escape($label) . ':</strong> ' . $date . '</li>';
            }
        }
        if ($timelineItems !== '') {
            $html .= '<p class="mb-1 mt-2"><strong>Calendarul apelului:</strong></p><ul class="mb-2">' . $timelineItems . '</ul>';
        }

        $createdAt = $this->formatDate($call['created_at'] ?? '');
        $updatedAt = $this->formatDate($call['updated_at'] ?? '');
        if ($createdAt !== '') {
            $html .= '<p class="mb-0 text-muted small">Apel creat la ' . $createdAt . ($updatedAt !== '' ? ', actualizat la ' . $updatedAt : '') . '</p>';
        }
        $html .= '</div></div>';
        return $html;
    }

    // Un câmp al formularului, construit din definiția lui JSON
    private function formField($field, $answer, $upload, $error)
    {
        $fieldId = $field['id'];
        $type = $field['type'];
        $required = !empty($field['required']);
        $options = (isset($field['options']) && is_array($field['options'])) ? $field['options'] : [];
        $inputId = 'input_' . $fieldId;
        $inputName = 'fields[' . $fieldId . ']';
        $invalidClass = ($error !== '') ? ' is-invalid' : '';
        $requiredAttr = $required ? ' required' : '';
        $value = is_string($answer) ? $answer : '';

        $html = '<div class="form-group">';
        $html .= '<label for="' . $inputId . '"><strong>' . $this->escape($field['label'] ?? '') . ($required ? ' *' : '') . '</strong></label>';
        if (!empty($field['description'])) {
            $html .= '<small class="form-text text-muted mt-0 mb-2">' . $this->escape($field['description']) . '</small>';
        }

        if ($type === 'textarea') {
            $html .= '<textarea class="form-control' . $invalidClass . '" rows="4" id="' . $inputId . '" name="' . $inputName . '"' . $requiredAttr . '>' . $this->escape($value) . '</textarea>';
        } elseif ($type === 'select') {
            $html .= '<select class="form-control' . $invalidClass . '" id="' . $inputId . '" name="' . $inputName . '"' . $requiredAttr . '>';
            $html .= '<option value="">-- Selectați --</option>';
            foreach ($options as $option) {
                $option = (string)$option;
                $html .= '<option value="' . $this->escape($option) . '"' . ($option === $value ? ' selected' : '') . '>' . $this->escape($option) . '</option>';
            }
            $html .= '</select>';
        } elseif ($type === 'radio' || $type === 'checkbox') {
            $checkedValues = is_array($answer) ? array_map('strval', $answer) : [$value];
            foreach ($options as $optionIndex => $option) {
                $option = (string)$option;
                $optionId = $inputId . '_' . $optionIndex;
                // La checkbox obligatoriul se verifică doar pe server (în browser ar cere toate opțiunile bifate)
                $html .= '<div class="form-check">';
                $html .= '<input class="form-check-input" type="' . $type . '" id="' . $optionId . '" name="' . $inputName . ($type === 'checkbox' ? '[]' : '') . '" value="' . $this->escape($option) . '"'
                    . (in_array($option, $checkedValues, true) ? ' checked' : '')
                    . ($type === 'radio' ? $requiredAttr : '') . '>';
                $html .= '<label class="form-check-label" for="' . $optionId . '">' . $this->escape($option) . '</label>';
                $html .= '</div>';
            }
        } elseif ($type === 'file') {
            if (!empty($upload)) {
                $downloadUrl = SITE_URL . '/projectsController/download/' . intval($upload['id']);
                $html .= '<p class="mb-1">Fișier încărcat: <a href="' . $downloadUrl . '">' . $this->escape($upload['original_name']) . '</a> (' . $this->formatSize($upload['size_bytes']) . ')</p>';
            }
            $accept = !empty($field['fileFormats']) ? ' accept="' . $this->escape(str_replace(' ', '', $field['fileFormats'])) . '"' : '';
            // Dacă există deja un fișier, alegerea unuia nou este opțională
            $html .= '<input type="file" class="form-control-file" id="' . $inputId . '" name="files[' . $fieldId . ']"' . $accept . (empty($upload) ? $requiredAttr : '') . '>';
            if (!empty($field['fileFormats'])) {
                $html .= '<small class="form-text text-muted">Formate acceptate: ' . $this->escape($field['fileFormats']) . '.'
                    . (!empty($upload) ? ' Alegeți un fișier doar dacă vreți să îl înlocuiți pe cel încărcat.' : '') . '</small>';
            }
        } else {
            $inputType = in_array($type, ['text', 'number', 'date', 'email', 'datetime-local'], true) ? $type : 'text';
            $html .= '<input type="' . $inputType . '" class="form-control' . $invalidClass . '" id="' . $inputId . '" name="' . $inputName . '" value="' . $this->escape($value) . '"'
                . ($inputType === 'number' ? ' step="any"' : '') . $requiredAttr . '>';
        }

        if ($error !== '') {
            $html .= '<div class="text-danger small mt-1">' . $this->escape($error) . '</div>';
        }
        if (!empty($field['score'])) {
            $html .= '<small class="form-text text-muted">Punctaj maxim la evaluare: ' . $this->escape($field['score']) . '</small>';
        }
        $html .= '</div>';
        return $html;
    }

    private function escape($text)
    {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }

    private function statusLabel($status)
    {
        $labels = ['draft' => 'Draft', 'open' => 'Deschis', 'closed' => 'Închis'];
        return isset($labels[$status]) ? $labels[$status] : (string)$status;
    }

    // Paragraf cu etichetă pentru un text liber; întoarce șir gol dacă textul lipsește
    private function textBlock($label, $text)
    {
        $text = trim((string)$text);
        if ($text === '') {
            return '';
        }
        return '<p class="mb-2"><strong>' . $label . ':</strong><br>' . nl2br($this->escape($text)) . '</p>';
    }

    // Rând de tip „Perioada ...: început – sfârșit”; lipsește dacă nu e completată nicio dată
    private function periodLine($label, $start, $end)
    {
        $start = $this->formatDateTime($start);
        $end = $this->formatDateTime($end);
        if ($start === '' && $end === '') {
            return '';
        }
        return '<p class="mb-1"><strong>' . $label . ':</strong> ' . ($start !== '' ? $start : '-') . ' &ndash; ' . ($end !== '' ? $end : '-') . '</p>';
    }

    // Bugetul lipsă (NULL sau șir gol) nu se afișează
    private function budgetLine($budget)
    {
        if ($budget === null || $budget === '') {
            return '';
        }
        return '<p class="mb-1"><strong>Buget total:</strong> ' . number_format((float)$budget, 2, ',', '.') . '</p>';
    }

    // Datele sunt salvate ca text (YYYY-MM-DD) sau șir gol
    private function formatDate($value)
    {
        if (empty($value)) {
            return '';
        }
        $timestamp = strtotime($value);
        return $timestamp !== false ? date('d.m.Y', $timestamp) : $this->escape($value);
    }

    // Etapele din calendar sunt salvate ca „AAAA-LL-ZZ” (oră nedefinită) sau „AAAA-LL-ZZ HH:MM”
    private function formatDateTime($value)
    {
        list($date, $time) = CallModel::splitTimelineValue($value);
        if ($date === '') {
            return $this->escape(trim((string)$value));
        }
        $timestamp = strtotime($date);
        return date('d.m.Y', $timestamp) . ($time !== '' ? ', ora ' . $time : '');
    }

    // Punctaj fără zecimale inutile (12.5, 30)
    private function formatScore($value)
    {
        return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
    }

    private function formatSize($bytes)
    {
        $bytes = (int)$bytes;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        }
        return number_format(max(1, $bytes / 1024), 0, ',', '.') . ' KB';
    }

}
