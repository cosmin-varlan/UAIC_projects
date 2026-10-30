<?php
class CallView
{

    public function __construct(){

	}

    public function createCallPage()
    {
         ob_start();
        $template = new Template(DIRECTOR_SITE.SLASH."views".SLASH."TPL".SLASH."callcreator.tpl");  
        $template->assign('URLSite', SITE_URL);
        $template->assign('MENU', Menu::render());
        $template->assign('TITLE', '');
        $template->assign('CODE', '');
        $template->assign('OBJECTIVES', '');
        $template->assign('ELIGIBILITY', '');
        $template->assign('BUDGET', '');
        $template->assign('CALL_ID', '');
        $template->assign('FIELDS_JSON', '[]');
        $template->assign('EVALUATORS_JSON', '[]');
        $template->assign('TIMELINE_FIELDS', $this->timelineFields([]));
        $template->assign('STATUS_DRAFT_SELECTED', 'selected');
        $template->assign('STATUS_OPEN_SELECTED', '');
        $template->assign('STATUS_CLOSED_SELECTED', '');

        echo $template->show();
        $this->output = ob_get_contents();
        ob_end_clean();
        return $this->output;        
    }

    public function editCallPage($callData)
    {
         ob_start();
        $template = new Template(DIRECTOR_SITE.SLASH."views".SLASH."TPL".SLASH."callcreator.tpl");  
        $template->assign('URLSite', SITE_URL);
        $template->assign('MENU', Menu::render());
        $template->assign('TITLE', htmlspecialchars($callData['title'] ?? '', ENT_QUOTES, 'UTF-8'));
        $template->assign('CODE', htmlspecialchars($callData['code'] ?? '', ENT_QUOTES, 'UTF-8'));
        $template->assign('OBJECTIVES', htmlspecialchars($callData['objectives'] ?? '', ENT_QUOTES, 'UTF-8'));
        $template->assign('ELIGIBILITY', htmlspecialchars($callData['eligibility_rules'] ?? '', ENT_QUOTES, 'UTF-8'));
        $template->assign('BUDGET', htmlspecialchars($callData['budget_total'] ?? '', ENT_QUOTES, 'UTF-8'));
        $template->assign('CALL_ID', htmlspecialchars($callData['id'] ?? '', ENT_QUOTES, 'UTF-8'));
        $fieldsJson = '[]';
        if (!empty($callData['description'])) {
            $decoded = json_decode($callData['description'], true);
            if (is_array($decoded)) {
                $fieldsJson = json_encode($decoded);
            }
        }
        $template->assign('FIELDS_JSON', htmlspecialchars($fieldsJson, ENT_QUOTES, 'UTF-8'));
        $evaluators = (isset($callData['evaluators']) && is_array($callData['evaluators'])) ? $callData['evaluators'] : [];
        $template->assign('EVALUATORS_JSON', htmlspecialchars(json_encode(array_values($evaluators)), ENT_QUOTES, 'UTF-8'));
        $template->assign('TIMELINE_FIELDS', $this->timelineFields($callData));

        $status = $callData['status'] ?? '';
        $template->assign('STATUS_DRAFT_SELECTED', $status === 'draft' ? 'selected' : '');
        $template->assign('STATUS_OPEN_SELECTED', $status === 'open' ? 'selected' : '');
        $template->assign('STATUS_CLOSED_SELECTED', $status === 'closed' ? 'selected' : '');

        echo $template->show();
        $this->output = ob_get_contents();
        ob_end_clean();
        return $this->output;        
    }



    // Câmpurile calendarului, unul sub altul, în ordine cronologică: data și, alături, ora.
    // Ora e implicit 16:00 doar la etapele din CallModel::getTimelineFieldsWithDefaultTime(); la celelalte rămâne goală.
    private function timelineFields($callData)
    {
        $html = '';
        $position = 1;
        foreach (CallModel::getTimelineFields() as $column => $label) {
            list($dateValue, $timeValue) = CallModel::splitTimelineValue(isset($callData[$column]) ? $callData[$column] : '');
            if ($timeValue === '' && in_array($column, CallModel::getTimelineFieldsWithDefaultTime(), true)) {
                $timeValue = CallModel::DEFAULT_TIMELINE_TIME;
            }
            $html .= '<div class="form-group row mb-2">';
            $html .= '<label for="' . $column . '" class="col-md-5 col-form-label">' . $position . '. ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
            $html .= '<div class="col-md-4"><input type="date" class="form-control timeline-date" id="' . $column . '" name="' . $column . '" value="' . $dateValue . '"></div>';
            $html .= '<div class="col-md-3"><input type="time" class="form-control timeline-time" id="' . $column . '_time" name="' . $column . '_time" value="' . $timeValue . '" aria-label="Ora pentru ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"></div>';
            $html .= '</div>';
            $position++;
        }
        return $html;
    }

    public function listCalls($calls)
    {
        ob_start();
        $template = new Template(DIRECTOR_SITE.SLASH."views".SLASH."TPL".SLASH."callslist.tpl");  
        $template->assign('URLSite', SITE_URL);
        $template->assign('MENU', Menu::render());
        $tableCalls = '';

        if (!empty($calls) && is_array($calls)) {
            $tableCalls .= '<div class="card shadow-sm"><div class="card-body">';
            $tableCalls .= '<h4 class="card-title mb-3">Lista apelurilor</h4>';
            $tableCalls .= '<div class="table-responsive">';
            $tableCalls .= '<table class="table table-bordered table-hover">';
            $tableCalls .= '<thead class="thead-light"><tr>';
            $tableCalls .= '<th>ID</th><th>Titlu</th><th>Cod</th><th>Status</th><th>Creat la</th><th>Acțiuni</th>';
            $tableCalls .= '</tr></thead><tbody>';
            foreach ($calls as $call) {
                $id = htmlspecialchars($call['id'] ?? '', ENT_QUOTES, 'UTF-8');
                $title = htmlspecialchars($call['title'] ?? '', ENT_QUOTES, 'UTF-8');
                $code = htmlspecialchars($call['code'] ?? '', ENT_QUOTES, 'UTF-8');
                $status = htmlspecialchars($call['status'] ?? '', ENT_QUOTES, 'UTF-8');
                $createdAt = htmlspecialchars($call['created_at'] ?? '', ENT_QUOTES, 'UTF-8');
                $editUrl = SITE_URL . '/callController/editCall/' . $id;
                $rowClass = '';
                if (strtolower($status) === 'open') {
                    $rowClass = 'table-success';
                } elseif (strtolower($status) === 'closed') {
                    $rowClass = 'table-danger';
                }
                $tableCalls .= '<tr' . ($rowClass ? ' class="' . $rowClass . '"' : '') . '>';
                $tableCalls .= '<td>' . $id . '</td>';
                $tableCalls .= '<td>' . $title . '</td>';
                $tableCalls .= '<td>' . $code . '</td>';
                $tableCalls .= '<td>' . $status . '</td>';
                $tableCalls .= '<td>' . $createdAt . '</td>';
                $submissionsUrl = SITE_URL . '/submissionsController/listSubmissions/' . $id;
                $tableCalls .= '<td class="text-nowrap"><a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a> <a href="' . $submissionsUrl . '" class="btn btn-sm btn-info ml-1">Vezi depuneri</a></td>';
                $tableCalls .= '</tr>';
            }
            $tableCalls .= '</tbody></table></div></div></div>';
        } else {
            $tableCalls = '<div class="alert alert-info">Nu există apeluri de afișat.</div>';
        }

        $template->assign('CALLS', $tableCalls);
        echo $template->show();
        $this->output = ob_get_contents();
        ob_end_clean();
        return $this->output;
    }
}
