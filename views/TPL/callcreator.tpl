<!DOCTYPE html>
<html lang="ro">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Creează Call - Managementul proiectelor UAIC</title>
        <link rel="stylesheet" href="{URLSITE}/css/bootstrap-4.1.0.min.css">
        <script src="{URLSITE}/js/jquery-3.3.1.min.js"></script>
        <script src="{URLSITE}/js/popper-1.14.0.min.js"></script>
        <script src="{URLSITE}/js/bootstrap-4.1.0.min.js"></script>
        <link rel="stylesheet" href="{URLSITE}/css/style.css">
    </head>
    <body>

          <div class="jumbotron text-center" style="margin-bottom:0">
            <h1>Managementul proiectelor UAIC</h1>
          </div>
          
          <div class="container" style="margin-top:30px">
          <nav class="navbar navbar-expand-sm navbar-custom">
            <a class="navbar-brand" href="{URLSITE}"><img src="{URLSITE}/imagini/logo.png" style="height: 30px;"></a>
            <button class="navbar-toggler custom-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar">
              <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="collapsibleNavbar">
              <ul class="navbar-nav">
                {MENU}
              </ul>
            </div>
          </nav>              
          </div>

          <div class="container" style="margin-top:30px">
            <div class="row">
              <div class="col-md-12">
                <div class="card shadow-sm mb-4">
                  <div class="card-body">
                    <h2 class="card-title">Creează un Call</h2>
                    <p>Completează datele de mai jos pentru a înregistra un nou apel de proiect. Toate câmpurile marcate cu * sunt obligatorii.</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-8">
                <div class="card shadow-sm">
                  <div class="card-body">
                    <h4 class="card-title">Previzualizare formular</h4>
                    <form id="callForm" method="post" action="{URLSITE}/callController/saveCall">
                      <div class="form-row">
                        <div class="form-group col-md-6">
                          <label for="title">Titlu call *</label>
                          <input type="text" class="form-control" id="title" name="title" value="{TITLE}" placeholder="Titlul call-ului" required>
                        </div>
                        <div class="form-group col-md-6">
                          <label for="code">Cod call *</label>
                          <input type="text" class="form-control" id="code" name="code" value="{CODE}" placeholder="C1, C2 etc." required>
                        </div>
                      </div>
                      <div id="previewFields" class="mb-4">
                        <div class="alert alert-info">Adaugă câmpuri în editorul din dreapta pentru a le vedea în previzualizare.</div>
                      </div>
                      <div class="form-group">
                        <label for="objectives">Obiective</label>
                        <textarea class="form-control" id="objectives" name="objectives" rows="3" placeholder="Obiectivele call-ului">{OBJECTIVES}</textarea>
                      </div>
                      <div class="form-group">
                        <label for="eligibility">Reguli de eligibilitate</label>
                        <textarea class="form-control" id="eligibility" name="eligibility" rows="3" placeholder="Condiții și reguli de eligibilitate">{ELIGIBILITY}</textarea>
                      </div>
                      <div class="mb-4">
                        <h5 class="mb-1">Calendarul apelului</h5>
                        <p class="text-muted small mb-3">Datele trebuie să fie în ordinea de mai jos; o etapă nu poate fi înaintea celor de deasupra ei. Ora este opțională: implicit 16:00 la termenul limită de depunere și la cele două etape de contestații, nedefinită la rest. Etapele fără dată sunt ignorate.</p>
                        {TIMELINE_FIELDS}
                      </div>
                      <div class="form-row">
                        <div class="form-group col-md-6">
                          <label for="budget">Buget total</label>
                          <input type="number" class="form-control" id="budget" name="budget" value="{BUDGET}" placeholder="0.00" step="0.01" min="0">
                        </div>
                        <div class="form-group col-md-6">
                          <label for="status">Stare</label>
                          <select class="form-control" id="status" name="status">
                            <option value="draft" {STATUS_DRAFT_SELECTED}>Draft</option>
                            <option value="open" {STATUS_OPEN_SELECTED}>Deschis</option>
                            <option value="closed" {STATUS_CLOSED_SELECTED}>Închis</option>
                          </select>
                        </div>
                      </div>
                      <input type="hidden" id="callId" name="call_id" value="{CALL_ID}">
                      <input type="hidden" id="fieldsJson" name="fields_json" value="{FIELDS_JSON}">
                      <div class="mb-4">
                        <h5 class="mb-1">Evaluatori</h5>
                        <p class="text-muted small mb-2">Introduceți adresa de email a evaluatorului. După ce câmpul este completat, butonul + adaugă încă un evaluator (cel mult 5). Câmpurile lăsate goale sunt ignorate.</p>
                        <input type="hidden" id="evaluatorsJson" value="{EVALUATORS_JSON}">
                        <div id="evaluatorsList"></div>
                      </div>
                      <div class="text-right">
                        <button type="button" id="deleteCallBtn" class="btn btn-danger mr-2">Șterge apel</button>
                        <button type="submit" class="btn btn-primary">Salvează modificările făcute</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card shadow-sm field-editor-card">
                  <div class="card-body">
                    <h4 class="card-title">Editor câmpuri</h4>
                    <div class="form-group">
                      <label for="newFieldLabel">Denumire câmp</label>
                      <input type="text" class="form-control" id="newFieldLabel" placeholder="Ex: Nume proiect">
                    </div>
                    <div class="form-group">
                      <label for="newFieldType">Tip câmp</label>
                      <select class="form-control" id="newFieldType">
                        <option value="text">Text</option>
                        <option value="textarea">Text lung</option>
                        <option value="number">Număr</option>
                        <option value="date">Dată</option>
                        <option value="email">Email</option>
                        <option value="datetime-local">Dată și oră</option>
                        <option value="select">Select</option>
                        <option value="radio">Radio</option>
                        <option value="checkbox">Checkbox</option>
                        <option value="file">Fișier</option>
                      </select>
                    </div>
                    <div class="form-group" id="fileFormatsRow" style="display:none;">
                      <label for="newFieldFileFormats">Format fișier acceptat</label>
                      <input type="text" class="form-control" id="newFieldFileFormats" placeholder="Ex: .pdf, .csv, .docx">
                      <small class="form-text text-muted">Introduceți formatele acceptate separate prin virgulă.</small>
                    </div>
                    <div class="form-group" id="selectOptionsRow" style="display:none;">
                      <label for="newFieldOptions">Opțiuni (câte una pe linie)</label>
                      <textarea class="form-control" id="newFieldOptions" rows="3" placeholder="Opțiunea 1\nOpțiunea 2\nOpțiunea 3"></textarea>
                    </div>
                    <div class="form-group">
                      <label for="newFieldDescription">Descriere opțională</label>
                      <input type="text" class="form-control" id="newFieldDescription" placeholder="Ex: Introdu doar numele proiectului">
                    </div>
                    <div class="form-group">
                      <label for="newFieldScore">Punctaj maxim</label>
                      <input type="number" class="form-control" id="newFieldScore" min="0" step="1" value="0" placeholder="0">
                      <small class="form-text text-muted">Punctajul maxim pe care îl poate obține acest câmp la evaluare.</small>
                    </div>
                    <div class="form-check mb-3">
                      <input class="form-check-input" type="checkbox" id="newFieldRequired">
                      <label class="form-check-label" for="newFieldRequired">Câmp obligatoriu</label>
                    </div>
                    <button type="button" id="addFieldBtn" class="btn btn-secondary btn-block">Adaugă câmp</button>
                    <hr>
                    <div class="mb-3">
                      <strong>Total punctaj maxim:</strong> <span id="totalScoreDisplay">0</span>
                    </div>
                    <div id="fieldsList"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <style>
            .field-editor-card {
              background-color: #f7f9fc;
            }
          </style>
          <script>
            $(function() {
              var fields = [];
              // Initialize fields from server-provided JSON (for edit pages)
              try {
                var initial = $('#fieldsJson').val();
                if (initial) {
                  var parsed = JSON.parse(initial);
                  if (Array.isArray(parsed)) {
                    fields = parsed;
                  }
                }
              } catch (e) {
                console.log('Failed to parse initial fields JSON', e);
              }

              function updateHiddenJson() {
                $('#fieldsJson').val(JSON.stringify(fields));
              }

              function renderFields() {
                var listContainer = $('#fieldsList');
                var previewContainer = $('#previewFields');
                var totalScore = 0;
                listContainer.empty();
                previewContainer.empty();

                if (fields.length === 0) {
                  listContainer.append('<div class="alert alert-info">Nu există câmpuri definite încă. Adaugă câmpuri în editor.</div>');
                  previewContainer.append('<div class="alert alert-info">Adaugă câmpuri în editorul din dreapta pentru a le vedea în previzualizare.</div>');
                  $('#callForm').attr('enctype', 'application/x-www-form-urlencoded');
                  $('#totalScoreDisplay').text('0');
                  return;
                }

                var hasFileField = false;

                fields.forEach(function(field, index) {
                  var requiredText = field.required ? 'Da' : 'Nu';
                  var optionsHtml = '';
                  if ((field.type === 'select' || field.type === 'radio' || field.type === 'checkbox') && Array.isArray(field.options)) {
                    optionsHtml = '<p class="mb-1"><strong>Opțiuni:</strong> ' + field.options.join(', ') + '</p>';
                  }
                  if (field.type === 'file' && field.fileFormats) {
                    optionsHtml = '<p class="mb-1"><strong>Formate acceptate:</strong> ' + field.fileFormats + '</p>';
                  }

                  var item = $('<div class="card mb-3"></div>');
                  var body = $('<div class="card-body"></div>');
                  totalScore += Number(field.score) || 0;
                  // Câmpurile fixe (de ex. Codul Brainmap) nu pot fi mutate sau șterse
                  var fieldActions = field.fixed
                    ? '<span class="badge badge-secondary">Câmp fix</span>'
                    : '<button type="button" class="btn btn-sm btn-light move-field-up mr-1" data-index="' + index + '">↑</button><button type="button" class="btn btn-sm btn-light move-field-down mr-1" data-index="' + index + '">↓</button><button type="button" class="btn btn-sm btn-danger remove-field" data-index="' + index + '">Șterge</button>';
                  body.append('<div class="d-flex justify-content-between align-items-start mb-2"><div><h5 class="card-title mb-1">' + $('<div/>').text(field.label).html() + '</h5><p class="mb-0 text-muted small">Tip: ' + field.type + ' | Obligatoriu: ' + requiredText + '</p></div><div>' + fieldActions + '</div></div>');
                  body.append('<p class="mb-1"><strong>Punctaj maxim:</strong> ' + (Number(field.score) || 0) + '</p>');
                  body.append('<p class="mb-1"><strong>Descriere:</strong> ' + $('<div/>').text(field.description || '-').html() + '</p>');
                  body.append(optionsHtml);
                  item.append(body);
                  listContainer.append(item);

                  var previewField = $('<div class="form-group"></div>');
                  var inputName = 'dynamic_field_' + index;
                  previewField.append('<label>' + $('<div/>').text(field.label).html() + (field.required ? ' (obligatoriu pentru propuneri)' : '') + '<br><small class="text-muted">Punctaj max: ' + (Number(field.score) || 0) + '</small></label>');
                  if (field.description) {
                    previewField.append('<small class="form-text text-muted mb-2 d-block">' + $('<div/>').text(field.description).html() + '</small>');
                  }

                  var inputElement;
                  if (field.type === 'textarea') {
                    inputElement = $('<textarea class="form-control" rows="3"></textarea>').attr('name', inputName);
                  } else if (field.type === 'select') {
                    inputElement = $('<select class="form-control"></select>').attr('name', inputName);
                    inputElement.append('<option value="">-- Selectați --</option>');
                    (field.options || []).forEach(function(option) {
                      inputElement.append('<option>' + $('<div/>').text(option).html() + '</option>');
                    });
                  } else if (field.type === 'radio' || field.type === 'checkbox') {
                    inputElement = $('<div></div>');
                    var optionType = field.type;
                    var optionName = inputName + (optionType === 'checkbox' ? '[]' : '');
                    (field.options || []).forEach(function(option, optIndex) {
                      var optionId = inputName + '_' + optIndex;
                      var optionInput = $('<input>')
                        .attr('type', optionType)
                        .attr('name', optionName)
                        .attr('value', option)
                        .attr('id', optionId)
                        .addClass(optionType === 'checkbox' ? 'form-check-input' : 'form-check-input');
                      var optionLabel = $('<label class="form-check-label ml-2">').attr('for', optionId).text(option);
                      var wrapper = $('<div class="form-check"></div>');
                      wrapper.append(optionInput).append(optionLabel);
                      inputElement.append(wrapper);
                    });
                  } else if (field.type === 'file') {
                    hasFileField = true;
                    inputElement = $('<input type="file" class="form-control-file">').attr('name', inputName);
                    if (field.fileFormats) {
                      var accept = field.fileFormats.split(',').map(function(item) { return item.trim(); }).filter(function(item) { return item.length > 0; }).join(',');
                      if (accept) {
                        inputElement.attr('accept', accept);
                      }
                    }
                  } else {
                    inputElement = $('<input class="form-control">').attr('type', field.type).attr('name', inputName);
                  }

                  previewField.append(inputElement);
                  previewContainer.append(previewField);
                });

                $('#totalScoreDisplay').text(totalScore);

                if (hasFileField) {
                  $('#callForm').attr('enctype', 'multipart/form-data');
                } else {
                  $('#callForm').attr('enctype', 'application/x-www-form-urlencoded');
                }
              }

              function resetNewFieldInputs() {
                $('#newFieldLabel').val('');
                $('#newFieldDescription').val('');
                $('#newFieldScore').val('0');
                $('#newFieldRequired').prop('checked', false);
                $('#newFieldOptions').val('');
                $('#newFieldFileFormats').val('');
              }

              $('#newFieldType').on('change', function() {
                var type = $(this).val();
                if (type === 'select' || type === 'radio' || type === 'checkbox') {
                  $('#selectOptionsRow').show();
                } else {
                  $('#selectOptionsRow').hide();
                }
                if (type === 'file') {
                  $('#fileFormatsRow').show();
                } else {
                  $('#fileFormatsRow').hide();
                }
              });

              $('#addFieldBtn').on('click', function() {
                var label = $('#newFieldLabel').val().trim();
                var type = $('#newFieldType').val();
                var description = $('#newFieldDescription').val().trim();
                var score = parseFloat($('#newFieldScore').val());
                var required = $('#newFieldRequired').is(':checked');
                var options = [];
                var fileFormats = '';

                if (isNaN(score) || score < 0) {
                  alert('Introduceți un punctaj maxim valid (0 sau mai mare).');
                  return;
                }

                if (!label) {
                  alert('Introduceți denumirea câmpului.');
                  return;
                }

                if (type === 'select' || type === 'radio' || type === 'checkbox') {
                  options = $('#newFieldOptions').val().split('\n').map(function(item) {
                    return item.trim();
                  }).filter(function(item) {
                    return item.length > 0;
                  });
                  if (options.length === 0) {
                    alert('Introduceți cel puțin o opțiune pentru câmpul de tip select, radio sau checkbox.');
                    return;
                  }
                }

                if (type === 'file') {
                  fileFormats = $('#newFieldFileFormats').val().trim();
                  if (!fileFormats) {
                    alert('Introduceți cel puțin un format de fișier pentru câmpul de tip fișier.');
                    return;
                  }
                }

                var newField = {
                  label: label,
                  type: type,
                  description: description,
                  required: required,
                  score: score
                };

                if (options.length > 0) {
                  newField.options = options;
                }
                if (fileFormats) {
                  newField.fileFormats = fileFormats;
                }

                fields.push(newField);
                updateHiddenJson();
                renderFields();
                resetNewFieldInputs();
                $('#selectOptionsRow').hide();
                $('#fileFormatsRow').hide();
                $('#newFieldType').val('text');
              });

              $('#fieldsList').on('click', '.remove-field', function() {
                var index = $(this).data('index');
                if (fields[index] && fields[index].fixed) {
                  return;
                }
                fields.splice(index, 1);
                updateHiddenJson();
                renderFields();
              });

              $('#fieldsList').on('click', '.move-field-up', function() {
                var index = $(this).data('index');
                // Un câmp nu poate urca deasupra câmpurilor fixe de la început
                if (index > 0 && !fields[index - 1].fixed) {
                  var field = fields[index];
                  fields.splice(index, 1);
                  fields.splice(index - 1, 0, field);
                  updateHiddenJson();
                  renderFields();
                }
              });

              $('#fieldsList').on('click', '.move-field-down', function() {
                var index = $(this).data('index');
                if (index < fields.length - 1) {
                  var field = fields[index];
                  fields.splice(index, 1);
                  fields.splice(index + 1, 0, field);
                  updateHiddenJson();
                  renderFields();
                }
              });

              $('#deleteCallBtn').on('click', function() {
                var callId = $('#callId').val();
                if (!callId) {
                  alert('Nu există niciun call salvat pentru ștergere.');
                  return;
                }

                if (!confirm('Ești sigur că vrei să ștergi acest call? Această acțiune este ireversibilă.')) {
                  return;
                }

                $.ajax({
                  url: '{URLSITE}/callController/deleteCall',
                  method: 'POST',
                  contentType: 'application/json',
                  data: JSON.stringify([callId]),
                  success: function(resp, statusText, xhr) {
                    var raw = (xhr && xhr.responseText) ? xhr.responseText : resp;
                    var obj = null;
                    if (typeof resp === 'object' && resp !== null) {
                      obj = resp;
                    } else {
                      try {
                        obj = JSON.parse(raw);
                      } catch (e) {
                        console.log('Response parse error', e, raw);
                      }
                    }
                    if (obj && obj.success) {
                      alert('Call-ul a fost șters cu succes.');
                      window.location = '{URLSITE}';
                    } else {
                      alert('Ștergerea a eșuat. Verifică consola pentru detalii.');
                      console.error('Delete failed', raw);
                    }
                  },
                  error: function(xhr) {
                    console.error('Delete failed', xhr.responseText);
                    alert('A apărut o eroare la ștergere. Vezi consola pentru detalii.');
                  }
                });
              });

              $('#callForm').on('submit', function(event) {
                event.preventDefault();
                var formData = {
                  title: $('#title').val(),
                  code: $('#code').val(),
                  objectives: $('#objectives').val(),
                  eligibility: $('#eligibility').val(),
                  budget: $('#budget').val(),
                  status: $('#status').val(),
                  call_id: $('#callId').val(),
                  id: $('#callId').val(),
                  fields: fields,
                  fields_json: JSON.stringify(fields)
                };
                // Toate etapele din calendarul apelului, ca „AAAA-LL-ZZ” sau „AAAA-LL-ZZ HH:MM”
                $('.timeline-date').each(function() {
                  formData[this.name] = timelineMoment(this);
                });
                // Adresele de email ale evaluatorilor; câmpurile goale nu se trimit
                formData.evaluators = $('#evaluatorsList .evaluator-email').map(function() {
                  return $.trim(this.value);
                }).get().filter(function(email) {
                  return email !== '';
                });

                // Ensure call id is present for update operations
                console.log('Sending JSON payload (call_id,id):', $('#callId').val(), JSON.stringify(formData));
                $.ajax({
                  url: 'callController/saveCall',
                  method: 'POST',
                  contentType: 'application/json',
                  data: JSON.stringify(formData),
                  success: function(resp, statusText, xhr) {
                    // resp may be empty string or already parsed object
                    var raw = (xhr && xhr.responseText) ? xhr.responseText : resp;
                    console.log('Raw server response:', raw);
                    if (!raw || raw.toString().trim() === '') {
                      console.warn('Empty response from server');
                      return;
                    }
                    var obj = null;
                    if (typeof resp === 'object' && resp !== null) {
                      obj = resp;
                    } else {
                      try {
                        obj = JSON.parse(raw);
                      } catch (e) {
                        console.log('Response parse error', e, raw);
                        return;
                      }
                    }
                    console.log('Parsed response object:', obj);
                    if (obj && obj.success) {
                        // Update hidden id field so subsequent saves update same record
                        $('#callId').val(obj.id);
                        $('#fieldsJson').val(formData.fields_json || JSON.stringify(formData.fields || []));
                        // Show a brief success message in the form
                        var msg = $('<div class="alert alert-success mt-3">Salvat cu succes (ID: ' + obj.id + ')</div>');
                        $('.card-body').first().find('.alert-success').remove();
                        $('.card-body').first().append(msg);
                        // After short delay, redirect to main site URL from template
                        setTimeout(function() {
                          window.location = '{URLSITE}';
                        }, 1200);
                        return;
                      }
                  },
                  error: function(xhr) {
                    console.error('Save failed', xhr.responseText);
                    // Serverul trimite un mesaj explicit, de exemplu pentru un calendar în ordine greșită
                    var message = 'A apărut o eroare la salvare. Vezi consola pentru detalii.';
                    try {
                      var errorObj = JSON.parse(xhr.responseText);
                      if (errorObj && errorObj.message) {
                        message = errorObj.message;
                      }
                    } catch (e) {
                      // răspunsul nu este JSON; rămâne mesajul general
                    }
                    alert(message);
                  },
                  complete: function(xhr) {
                    // ensure raw response is visible
                    try {
                      console.log('AJAX complete, responseText:', xhr.responseText);
                    } catch (e) {
                      // ignore
                    }
                  }
                });
              });

              // Data și ora unei etape; ora poate lipsi (nedefinită)
              function timelineParts(dateInput) {
                return {
                  date: dateInput.value,
                  time: document.getElementById(dateInput.id + '_time').value
                };
              }

              // Valoarea salvată: „AAAA-LL-ZZ”, „AAAA-LL-ZZ HH:MM” sau '' dacă etapa nu are dată
              function timelineMoment(dateInput) {
                var parts = timelineParts(dateInput);
                if (!parts.date) {
                  return '';
                }
                return parts.date + (parts.time ? ' ' + parts.time : '');
              }

              // Fiecare etapă poate fi aleasă doar după cele completate deasupra ei și înaintea celor
              // completate dedesubt. Ora contează doar când etapa cade în aceeași zi cu o vecină care are oră.
              // Browserul refuză salvarea dacă o valoare iese din aceste limite.
              function updateTimelineLimits() {
                var dates = $('.timeline-date');
                var allParts = dates.map(function() {
                  return timelineParts(this);
                }).get();

                dates.each(function(index) {
                  // cea mai târzie zi de deasupra și cea mai devreme de dedesubt, cu ora cea mai restrictivă din acea zi
                  var previousDate = '';
                  var previousTime = '';
                  var nextDate = '';
                  var nextTime = '';
                  for (var i = 0; i < allParts.length; i++) {
                    var parts = allParts[i];
                    if (!parts.date || i === index) {
                      continue;
                    }
                    if (i < index) {
                      if (parts.date > previousDate) {
                        previousDate = parts.date;
                        previousTime = parts.time;
                      } else if (parts.date === previousDate && parts.time > previousTime) {
                        previousTime = parts.time;
                      }
                    } else {
                      if (nextDate === '' || parts.date < nextDate) {
                        nextDate = parts.date;
                        nextTime = parts.time;
                      } else if (parts.date === nextDate && parts.time && (nextTime === '' || parts.time < nextTime)) {
                        nextTime = parts.time;
                      }
                    }
                  }

                  var timeInput = document.getElementById(this.id + '_time');
                  this.min = previousDate;
                  this.max = nextDate;
                  timeInput.min = (this.value && this.value === previousDate) ? previousTime : '';
                  timeInput.max = (this.value && this.value === nextDate) ? nextTime : '';
                });
              }

              $('.timeline-date, .timeline-time').on('change input', updateTimelineLimits);
              updateTimelineLimits();

              // Evaluatorii apelului: câte un câmp de email pe rând; „+” de lângă ultimul câmp adaugă
              // încă unul, doar dacă acesta e completat și nu s-a ajuns la 5 evaluatori
              var MAX_EVALUATORS = 5;

              function addEvaluatorRow(email) {
                var row = $('<div class="input-group mb-2 evaluator-row"></div>');
                row.append($('<input type="email" class="form-control evaluator-email" placeholder="adresa de email a evaluatorului">').val(email || ''));
                $('#evaluatorsList').append(row);
                updateEvaluatorButtons();
              }

              // Butonul „+” stă doar lângă ultimul câmp și e activ doar când acesta e completat
              function updateEvaluatorButtons() {
                var rows = $('#evaluatorsList .evaluator-row');
                rows.find('.input-group-append').remove();
                if (rows.length >= MAX_EVALUATORS) {
                  return;
                }
                var lastRow = rows.last();
                var isFilled = $.trim(lastRow.find('.evaluator-email').val()) !== '';
                var addButton = $('<div class="input-group-append"><button type="button" class="btn btn-outline-secondary add-evaluator" title="Adaugă încă un evaluator">+</button></div>');
                addButton.find('button').prop('disabled', !isFilled);
                lastRow.append(addButton);
              }

              $('#evaluatorsList').on('input', '.evaluator-email', updateEvaluatorButtons);
              $('#evaluatorsList').on('click', '.add-evaluator', function() {
                if ($('#evaluatorsList .evaluator-row').length < MAX_EVALUATORS) {
                  addEvaluatorRow('');
                  $('#evaluatorsList .evaluator-email').last().focus();
                }
              });

              var initialEvaluators = [];
              try {
                initialEvaluators = JSON.parse($('#evaluatorsJson').val() || '[]');
              } catch (e) {
                console.log('Failed to parse initial evaluators JSON', e);
              }
              if (!initialEvaluators.length) {
                initialEvaluators = [''];
              }
              $.each(initialEvaluators.slice(0, MAX_EVALUATORS), function(index, email) {
                addEvaluatorRow(email);
              });

              renderFields();
              updateHiddenJson();
            });
          </script>
    </body>
</html>
