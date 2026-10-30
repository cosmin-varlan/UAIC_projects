<?php
class SubmissionsView
{
    public function __construct(){
	}

    // Tabelul propunerilor la un apel
    public function listPage($call, $submissions, $message = '')
    {
        $content = '<p><a href="' . SITE_URL . '/callController/listCalls">&laquo; Înapoi la apeluri</a></p>';
        if ($message !== '') {
            $content .= '<div class="alert alert-info">' . $this->escape($message) . '</div>';
        }

        if (!empty($submissions)) {
            $submittedCount = 0;
            foreach ($submissions as $submission) {
                if (!empty($submission['submitted_at'])) {
                    $submittedCount++;
                }
            }
            $content .= '<p>' . $submittedCount . ' proiecte depuse, ' . (count($submissions) - $submittedCount) . ' nedepuse (în lucru sau întoarse la propunător).</p>';

            $content .= '<div class="card shadow-sm"><div class="card-body">';
            $content .= '<div class="table-responsive">';
            $content .= '<table class="table table-bordered table-hover">';
            $content .= '<thead class="thead-light"><tr>';
            $content .= '<th>Nr.</th><th>Email</th><th>Propunător</th><th>Facultate</th><th>Stare</th><th>Acțiuni</th>';
            $content .= '</tr></thead><tbody>';
            foreach ($submissions as $submission) {
                $projectId = intval($submission['id']);
                $isSubmitted = !empty($submission['submitted_at']);
                $viewUrl = SITE_URL . '/submissionsController/viewProject/' . $projectId;

                $content .= '<tr' . ($isSubmitted ? '' : ' class="text-muted"') . '>';
                $content .= '<td>' . $projectId . '</td>';
                $content .= '<td><a href="mailto:' . $this->escape($submission['email']) . '">' . $this->escape($submission['email']) . '</a></td>';
                $content .= '<td>' . $this->escape(trim($submission['lastname'] . ' ' . $submission['firstname'])) . '</td>';
                $content .= '<td>' . $this->escape($submission['faculty']) . '</td>';
                $content .= '<td>' . ($isSubmitted ? 'Depus la ' . $this->escape($submission['submitted_at']) : 'Nedepus') . '</td>';

                $content .= '<td class="text-nowrap">';
                $content .= '<a href="' . $viewUrl . '" class="btn btn-sm btn-primary">Vizualizează</a>';
                if ($isSubmitted) {
                    // Formular POST cu confirmare, ca proiectul să nu fie întors printr-un simplu link
                    $content .= ' <form method="post" action="' . SITE_URL . '/submissionsController/returnProject" class="d-inline ml-1" onsubmit="return confirm(\'Proiectul nr. ' . $projectId . ' va redeveni nedepus și propunătorul îl va putea modifica. Continuați?\');">';
                    $content .= '<input type="hidden" name="project_id" value="' . $projectId . '">';
                    $content .= '<button type="submit" class="btn btn-sm btn-warning">Întoarce la propunător</button>';
                    $content .= '</form>';
                }
                $content .= '</td>';
                $content .= '</tr>';
            }
            $content .= '</tbody></table></div></div></div>';
        } else {
            $content .= '<div class="alert alert-info">Nu există încă nicio propunere la acest apel.</div>';
        }

        return $this->renderPage('Depuneri', 'Depuneri: ' . $this->callName($call), $content);
    }

    // Toate datele introduse de propunător, doar pentru citire
    public function projectPage($call, $project, $fields, $answers, $uploads)
    {
        $backUrl = SITE_URL . '/submissionsController/listSubmissions/' . intval($project['call_id']);
        $content = '<p><a href="' . $backUrl . '">&laquo; Înapoi la depuneri</a></p>';

        $content .= '<div class="card shadow-sm mb-4"><div class="card-body">';
        $content .= '<h4 class="card-title">Propunător</h4>';
        $content .= $this->detailRow('Nume', $this->escape(trim($project['lastname'] . ' ' . $project['firstname'])));
        $content .= $this->detailRow('Email', '<a href="mailto:' . $this->escape($project['email']) . '">' . $this->escape($project['email']) . '</a>');
        $content .= $this->detailRow('Facultate', $this->escape($project['faculty']));
        $content .= $this->detailRow('Stare', !empty($project['submitted_at']) ? 'Depus la ' . $this->escape($project['submitted_at']) : 'Nedepus');
        $content .= $this->detailRow('Creat la', $this->escape($project['created_at']));
        $content .= $this->detailRow('Modificat la', $this->escape($project['updated_at']));
        $content .= '</div></div>';

        $content .= '<div class="card shadow-sm"><div class="card-body">';
        $content .= '<h4 class="card-title">Datele propunerii</h4>';
        if (count($fields) === 0) {
            $content .= '<div class="alert alert-info">Formularul apelului nu are câmpuri.</div>';
        } else {
            $content .= '<table class="table table-bordered mb-0"><tbody>';
            foreach ($fields as $field) {
                $fieldId = $field['id'];
                $content .= '<tr>';
                $content .= '<th style="width: 35%">' . $this->escape($field['label'] ?? '') . (!empty($field['required']) ? ' *' : '') . '</th>';
                $content .= '<td>' . $this->answerHtml($field, isset($answers[$fieldId]) ? $answers[$fieldId] : null, isset($uploads[$fieldId]) ? $uploads[$fieldId] : null) . '</td>';
                $content .= '</tr>';
            }
            $content .= '</tbody></table>';
        }
        $content .= '</div></div>';

        $heading = 'Proiectul nr. ' . intval($project['id']) . ($call ? ' &ndash; ' . $this->callName($call) : '');
        return $this->renderPage('Vizualizare proiect', $heading, $content);
    }

    public function messagePage($heading, $message)
    {
        $content = '<div class="alert alert-warning">' . $this->escape($message) . '</div>';
        $content .= '<p><a href="' . SITE_URL . '/callController/listCalls">&laquo; Înapoi la apeluri</a></p>';
        return $this->renderPage($heading, $this->escape($heading), $content);
    }

    // Răspunsul la un câmp; fișierele apar ca link de descărcare, valorile lipsă ca liniuță
    private function answerHtml($field, $answer, $upload)
    {
        $empty = '<span class="text-muted">&mdash;</span>';
        if (($field['type'] ?? '') === 'file') {
            if (empty($upload)) {
                return $empty;
            }
            $downloadUrl = SITE_URL . '/projectsController/download/' . intval($upload['id']);
            return '<a href="' . $downloadUrl . '">' . $this->escape($upload['original_name']) . '</a> <small class="text-muted">(' . $this->formatSize($upload['size_bytes']) . ')</small>';
        }
        if (is_array($answer)) {
            return count($answer) > 0 ? $this->escape(implode(', ', $answer)) : $empty;
        }
        $answer = trim((string)$answer);
        return $answer !== '' ? nl2br($this->escape($answer)) : $empty;
    }

    private function detailRow($label, $valueHtml)
    {
        return '<p class="mb-1"><strong>' . $label . ':</strong> ' . $valueHtml . '</p>';
    }

    private function callName($call)
    {
        return $this->escape($call['title'] ?? '') . ' <small class="text-muted">(' . $this->escape($call['code'] ?? '') . ')</small>';
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

    private function escape($text)
    {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
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
