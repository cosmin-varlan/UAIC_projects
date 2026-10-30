<?php
class EvaluationView
{
    public function __construct(){
	}

    // Pagina de evaluare a unui proiect; $scores = notele de afișat [id câmp => notă], $errors = [id câmp => mesaj]
    public function evaluatePage($context, $scores, $errors, $message)
    {
        $project = $context['project'];
        $call = $context['call'];
        $evaluation = $context['evaluation'];
        $isFinal = $evaluation && !empty($evaluation['finalized_at']);

        $content = '<p><a href="' . SITE_URL . '/projectsController/toEvaluate/' . intval($call['id']) . '">&laquo; Înapoi la proiectele apelului</a></p>';
        if ($message !== '') {
            $content .= '<div class="alert alert-success">' . $this->escape($message) . '</div>';
        }

        // Datele proiectului și ale propunătorului
        $content .= '<div class="card shadow-sm mb-4"><div class="card-body">';
        $content .= '<h4 class="card-title">Proiect</h4>';
        $content .= $this->detailRow('Apel', $this->escape($call['title']) . ' <small class="text-muted">(' . $this->escape($call['code']) . ')</small>');
        $content .= $this->detailRow('Propunător', $this->escape(trim($project['lastname'] . ' ' . $project['firstname'])));
        $content .= $this->detailRow('Email', '<a href="mailto:' . $this->escape($project['email']) . '">' . $this->escape($project['email']) . '</a>');
        $content .= $this->detailRow('Facultate', $this->escape($project['faculty']));
        $content .= $this->detailRow('Depus la', $this->escape($project['submitted_at']));
        $content .= '</div></div>';

        // Răspunsurile propunătorului și notele
        $maxTotal = 0;
        foreach ($context['fields'] as $field) {
            $maxTotal += $this->maxScore($field);
        }

        $content .= '<div class="card shadow-sm"><div class="card-body">';
        $content .= '<h4 class="card-title">Evaluare</h4>';
        if ($isFinal) {
            $content .= '<div class="alert alert-info">Evaluarea a fost finalizată la ' . $this->escape($evaluation['finalized_at']) . ' și poate fi doar vizualizată.</div>';
        } elseif (count($errors) > 0) {
            $content .= '<div class="alert alert-danger">Evaluarea nu a fost salvată. Corectați notele marcate.</div>';
        } elseif ($evaluation) {
            $content .= '<p class="text-muted small">Evaluare în lucru, salvată ultima dată la ' . $this->escape($evaluation['updated_at']) . '. Notele pot fi modificate până la finalizare.</p>';
        } else {
            $content .= '<p class="text-muted small">Acordați câte o notă între 0 și punctajul maxim pentru fiecare câmp punctat. Puteți salva evaluarea și o puteți continua mai târziu; după finalizare notele nu mai pot fi modificate.</p>';
        }

        $content .= '<form method="post" action="' . SITE_URL . '/evaluationController/saveEvaluation" id="evaluationForm">';
        $content .= '<input type="hidden" name="project_id" value="' . intval($project['id']) . '">';
        $content .= '<div class="table-responsive"><table class="table table-bordered">';
        $content .= '<thead class="thead-light"><tr><th style="width: 25%">Câmp</th><th>Răspunsul propunătorului</th><th style="width: 22%">Notă</th></tr></thead><tbody>';

        foreach ($context['fields'] as $field) {
            $fieldId = $field['id'];
            $maxScore = $this->maxScore($field);
            $answer = isset($context['answers'][$fieldId]) ? $context['answers'][$fieldId] : null;
            $upload = isset($context['uploads'][$fieldId]) ? $context['uploads'][$fieldId] : null;
            $error = isset($errors[$fieldId]) ? $errors[$fieldId] : '';

            $content .= '<tr>';
            $content .= '<th>' . $this->escape($field['label'] ?? '') . '</th>';
            $content .= '<td>' . $this->answerHtml($field, $answer, $upload) . '</td>';
            $content .= '<td>' . $this->scoreCell($fieldId, $maxScore, isset($scores[$fieldId]) ? $scores[$fieldId] : '', $error, $isFinal) . '</td>';
            $content .= '</tr>';
        }

        $content .= '</tbody><tfoot><tr class="table-active">';
        $content .= '<th colspan="2" class="text-right">Punctaj total</th>';
        $content .= '<th><span id="totalScore">' . $this->formatScore($this->sumScores($scores)) . '</span> / ' . $this->formatScore($maxTotal) . '</th>';
        $content .= '</tr></tfoot></table></div>';

        if (!$isFinal) {
            $content .= '<div class="text-right">';
            // Salvarea acceptă și o evaluare incompletă; finalizarea cere toate notele (verificate și în browser)
            $content .= '<button type="submit" formnovalidate class="btn btn-primary">Salvează evaluarea</button>';
            $content .= ' <button type="submit" name="finalize" value="1" class="btn btn-success ml-2" onclick="return !this.form.checkValidity() || confirm(\'După finalizare notele nu mai pot fi modificate. Finalizați evaluarea?\');">Finalizează evaluarea</button>';
            $content .= '</div>';
        }
        $content .= '</form>';
        $content .= '</div></div>';

        if (!$isFinal) {
            // Punctajul total se actualizează pe măsură ce sunt introduse notele
            $content .= '<script>
              (function() {
                var inputs = document.querySelectorAll("#evaluationForm .score-input");
                function updateTotal() {
                  var total = 0;
                  for (var i = 0; i < inputs.length; i++) {
                    var value = parseFloat(String(inputs[i].value).replace(",", "."));
                    if (!isNaN(value)) {
                      total += value;
                    }
                  }
                  document.getElementById("totalScore").textContent = String(Math.round(total * 100) / 100);
                }
                for (var i = 0; i < inputs.length; i++) {
                  inputs[i].addEventListener("input", updateTotal);
                }
                updateTotal();
              })();
            </script>';
        }

        $heading = 'Evaluare: proiectul nr. ' . intval($project['id']);
        return $this->renderPage('Evaluare proiect', $heading, $content);
    }

    public function messagePage($heading, $message, $callId)
    {
        $content = '<div class="alert alert-warning">' . $this->escape($message) . '</div>';
        $backUrl = $callId ? SITE_URL . '/projectsController/toEvaluate/' . intval($callId) : SITE_URL . '/projectsController/toEvaluate';
        $content .= '<p><a href="' . $backUrl . '">&laquo; Înapoi la „De evaluat”</a></p>';
        return $this->renderPage($heading, $this->escape($heading), $content);
    }

    // Celula cu nota: câmp numeric (0..maxim) sau, după finalizare, doar valoarea; câmpurile fără punctaj nu se notează
    private function scoreCell($fieldId, $maxScore, $value, $error, $isFinal)
    {
        if ($maxScore <= 0) {
            return '<span class="text-muted">nu se punctează</span>';
        }
        if ($isFinal) {
            return '<strong>' . ($value !== '' ? $this->formatScore($value) : '&mdash;') . '</strong> / ' . $this->formatScore($maxScore);
        }

        $html = '<div class="input-group">';
        $html .= '<input type="number" class="form-control score-input' . ($error !== '' ? ' is-invalid' : '') . '" name="scores[' . $this->escape($fieldId) . ']"'
            . ' value="' . $this->escape(is_numeric($value) ? $this->formatScore($value) : $value) . '" min="0" max="' . $this->formatScore($maxScore) . '" step="0.01" required>';
        $html .= '<div class="input-group-append"><span class="input-group-text">/ ' . $this->formatScore($maxScore) . '</span></div>';
        $html .= '</div>';
        if ($error !== '') {
            $html .= '<div class="text-danger small mt-1">' . $this->escape($error) . '</div>';
        }
        return $html;
    }

    // Răspunsul propunătorului; fișierele apar ca link de descărcare, valorile lipsă ca liniuță
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

    private function maxScore($field)
    {
        return (isset($field['score']) && is_numeric($field['score'])) ? (float)$field['score'] : 0;
    }

    private function sumScores($scores)
    {
        $total = 0;
        foreach ($scores as $score) {
            if (is_numeric($score)) {
                $total += (float)$score;
            }
        }
        return $total;
    }

    private function formatScore($value)
    {
        return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
    }

    private function detailRow($label, $valueHtml)
    {
        return '<p class="mb-1"><strong>' . $label . ':</strong> ' . $valueHtml . '</p>';
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
