# Managementul proiectelor UAIC – specificații

*Versiunea din 22.10.2026. Descrie aplicația așa cum este implementată la această dată.*

## Cuprins

1. [Scopul aplicației](#1-scopul-aplicației)
2. [Utilizatori și roluri](#2-utilizatori-și-roluri)
3. [Fluxul general](#3-fluxul-general)
4. [Cerințe funcționale](#4-cerințe-funcționale)
5. [Reguli de business](#5-reguli-de-business)
6. [Modelul de date](#6-modelul-de-date)
7. [Arhitectura și rutele](#7-arhitectura-și-rutele)
8. [Instalare și date de test](#8-instalare-și-date-de-test)
9. [Limitări cunoscute și decizii deschise](#9-limitări-cunoscute-și-decizii-deschise)

---

## 1. Scopul aplicației

Aplicația gestionează competițiile interne de proiecte ale universității, de la publicarea unui apel până la evaluarea propunerilor:

1. Administratorul creează un **apel** (call): date generale, calendarul etapelor, formularul pe care îl completează propunătorii și evaluatorii apelului.
2. Membrii universității văd apelurile deschise, completează formularul și **depun** un proiect.
3. Administratorul vede depunerile și poate **întoarce** un proiect la propunător pentru completări.
4. **Evaluatorii** apelului acordă note pe câmpurile punctate ale formularului și își finalizează evaluarea.

Interfața este în limba română.

## 2. Utilizatori și roluri

| Rol | Cum se obține | Ce poate face |
|---|---|---|
| Utilizator autentificat | Cont creat la înregistrare | Vede apelurile deschise, depune proiecte, își vede și modifică propriile proiecte |
| Propunător (Owner) | Automat, la prima salvare a unei propuneri | Proprietarul unei propuneri; doar el o poate modifica și depune |
| Evaluator | Adresa lui de email este trecută de administrator în lista de evaluatori a unui apel | Vede și evaluează toate proiectele depuse la acel apel; descarcă fișierele lor |
| Administrator (Admin) | Rândul `Admin` din tabela `roles` (`project_id = 0`) | Creează și editează apeluri, vede depunerile, întoarce proiecte la propunător, descarcă orice fișier |

Un utilizator poate avea mai multe roluri, dar **nu poate fi propunător și evaluator la același apel** (vezi [5.4](#54-conflict-de-interese)).

### Meniul

Meniul de sus este același pe toate paginile autentificate și depinde de roluri:

| Intrare | Vizibilă pentru |
|---|---|
| Propuneri | toți utilizatorii autentificați |
| Proiectele dvs. | toți utilizatorii autentificați |
| De evaluat | cei trecuți ca evaluatori la cel puțin un apel |
| Apeluri | administratorii |

## 3. Fluxul general

```
Administrator                       Propunător                        Evaluator
─────────────                       ──────────                        ─────────
Creează apelul (draft)
Definește formularul și calendarul
Atribuie evaluatorii
Schimbă starea în „Deschis”  ──►   Vede apelul în „Propuneri”
                                    Completează și salvează ciorna
                                    Depune proiectul  ───────────►   Vede proiectul în „De evaluat”
Vede depunerile                                                       Acordă note, salvează
(opțional) Întoarce proiectul ──►  Completează și depune din nou     Finalizează evaluarea
```

## 4. Cerințe funcționale

### 4.1 Autentificare și cont

| Cod | Cerință |
|---|---|
| AUT-1 | Autentificarea se face fără parolă: utilizatorul introduce adresa de email și primește un link de autentificare. |
| AUT-2 | Deocamdată emailul nu se trimite efectiv; linkul este scris în fișierul `linkuri.txt` din rădăcina proiectului. |
| AUT-3 | Accesarea linkului setează cookie-ul `login_token` (valabil 3 zile pe calculatorul respectiv). |
| AUT-4 | Un email necunoscut duce la formularul de înregistrare (prenume, nume, email, facultate). |
| AUT-5 | Deautentificarea locală șterge cookie-ul doar pe calculatorul curent. |
| AUT-6 | Deautentificarea globală invalidează tokenul, deci utilizatorul este deautentificat de pe toate calculatoarele. |
| AUT-7 | Toate paginile, cu excepția celor de autentificare și înregistrare, cer un utilizator autentificat; altfel se face redirecționare la pagina de conectare. |

### 4.2 Apeluri (administrator)

Pagini: lista apelurilor (meniul „Apeluri”) și editorul de apel (creare și editare).

| Cod | Cerință |
|---|---|
| APL-1 | Lista apelurilor arată ID, titlu, cod, stare și data creării, cu butoanele „Edit” și „Vezi depuneri” pe fiecare rând. Dacă nu există apeluri, butonul „Crează un nou call” rămâne disponibil. |
| APL-2 | Un apel are: titlu, cod, obiective, reguli de eligibilitate, buget total, stare (`draft`, `open` – Deschis, `closed` – Închis), calendar, formular și evaluatori. |
| APL-3 | „Crează un nou call” creează imediat un apel gol (titlu și cod „-”, stare draft) și deschide editorul pe el. |
| APL-4 | Salvarea se face prin AJAX; la reușită utilizatorul este dus pe pagina principală. Mesajele de eroare ale serverului sunt afișate explicit. |
| APL-5 | „Șterge apel” cere confirmare și șterge apelul împreună cu lista lui de evaluatori. |

#### Calendarul apelului

| Cod | Cerință |
|---|---|
| CAL-1 | Calendarul are 14 etape, afișate una sub alta, în ordinea: (1) Începere depuneri, (2) Termen limită depuneri, (3) Verificare eligibilitate, (4) Afișarea eligibilității, (5) Contestații eligibilitate, (6) Rezolvarea contestațiilor pentru eligibilitate, (7) Evaluare, (8) Postarea rezultatelor evaluării, (9) Contestații, (10) Postarea rezultatelor contestațiilor, (11) Aprobare în BECA, (12) Contractare, (13) Implementare (început), (14) Implementare (sfârșit). |
| CAL-2 | Fiecare etapă are o dată și, alături, o oră opțională. Ora este implicit **16:00** la Termen limită depuneri, Contestații eligibilitate și Contestații; la celelalte etape ora este implicit nedefinită. |
| CAL-3 | Etapele trebuie să fie în ordine cronologică: o etapă nu poate fi înaintea celor completate deasupra ei. Ordinea se verifică pe zile; ora contează doar între două etape din aceeași zi care au amândouă oră. Două etape pot avea aceeași zi. |
| CAL-4 | Etapele fără dată sunt ignorate (calendarul poate fi parțial). |
| CAL-5 | Ordinea este impusă în browser (datele din afara intervalului nu pot fi alese) și verificată din nou pe server, care refuză salvarea cu un mesaj ce numește etapa problematică. |

#### Formularul propunerii (editorul de câmpuri)

| Cod | Cerință |
|---|---|
| FRM-1 | Administratorul construiește formularul din editorul din dreapta paginii. Tipuri de câmp: text, text lung, număr, dată, email, dată și oră, select, radio, checkbox, fișier. |
| FRM-2 | Fiecare câmp are: denumire, tip, descriere opțională, obligatoriu (da/nu), punctaj maxim la evaluare. Câmpurile select/radio/checkbox au o listă de opțiuni, iar câmpurile fișier au formatele acceptate (de exemplu `.pdf, .docx`). |
| FRM-3 | Câmpurile pot fi mutate în sus și în jos și pot fi șterse; editorul arată o previzualizare a formularului și totalul punctajului maxim. |
| FRM-4 | Fiecare câmp primește la salvare un identificator stabil, astfel încât răspunsurile și fișierele propunerilor rămân legate de câmp chiar dacă ordinea câmpurilor se schimbă. |
| FRM-5 | Câmpul text **„Codul Brainmap”** există în orice formular, mereu pe prima poziție. Este fix: nu poate fi mutat, șters sau modificat, este opțional și nu se punctează. Serverul îl impune la fiecare salvare. |

#### Evaluatorii apelului

| Cod | Cerință |
|---|---|
| EVL-1 | În editor, deasupra butonului „Șterge apel”, se introduc adresele de email ale evaluatorilor. Butonul „+” de lângă ultimul câmp adaugă încă un câmp și se activează doar dacă acesta este completat. |
| EVL-2 | Un apel are cel mult **5** evaluatori. Câmpurile goale sunt ignorate, dublurile eliminate, iar adresele salvate cu litere mici. |
| EVL-3 | O adresă invalidă sau mai mult de 5 evaluatori opresc salvarea apelului cu un mesaj explicit. |
| EVL-4 | Nu poate fi evaluator cine are deja o propunere (depusă sau ciornă) la apelul respectiv; salvarea este refuzată cu un mesaj care numește persoana. |

### 4.3 Propuneri (propunător)

| Cod | Cerință |
|---|---|
| PRP-1 | Pagina „Propuneri” listează apelurile cu starea Deschis al căror termen limită de depunere nu a trecut. Fiecare apel arată titlul, codul, obiectivele, regulile de eligibilitate, perioada de depunere și bugetul. |
| PRP-2 | Butonul unui apel este „Detalii și depunere proiect”; dacă utilizatorul are deja o propunere la apel, butonul devine „Vezi propunerea dvs.”. Evaluatorii apelului văd în locul butonului mesajul că nu pot depune proiecte la el. |
| PRP-3 | Pagina apelului arată toate datele lui (fără ID), calendarul complet și formularul generat din definiția câmpurilor. |
| PRP-4 | „Salvează propunerea” păstrează formularul ca ciornă, chiar incomplet: câmpurile obligatorii nu sunt verificate la salvare. Valorile greșite (un text într-un câmp numeric, un email invalid, o opțiune inexistentă, un fișier cu format neacceptat) sunt însă refuzate. |
| PRP-5 | „Depunere proiect” există atât pe pagina unui proiect nou, cât și la editare. Cere confirmare, salvează formularul și apoi marchează proiectul ca depus. |
| PRP-6 | Dacă la depunere lipsesc câmpuri obligatorii, datele și fișierele trimise sunt salvate, dar proiectul rămâne nedepus; pagina se reafișează cu câmpurile lipsă marcate. |
| PRP-7 | Un proiect depus nu mai poate fi modificat de propunător, ci doar vizualizat (formular dezactivat, fișierele rămân descărcabile). |
| PRP-8 | Pagina „Proiectele dvs.” listează propunerile utilizatorului: apel, starea apelului, starea proiectului (Depus la …, Nedepus, Nedepus – termen expirat), datele de creare și modificare, cu butonul „Editează” sau „Vezi”. |
| PRP-9 | Fișierele încărcate se descarcă prin aplicație, cu numele original. Un fișier nou încărcat la un câmp îl înlocuiește pe cel vechi. |

### 4.4 Depuneri (administrator)

| Cod | Cerință |
|---|---|
| DEP-1 | „Vezi depuneri” din lista de apeluri deschide tabelul propunerilor apelului: număr, email (link de email), propunător, facultate, stare; cele depuse primele, apoi cele nedepuse (cu gri). |
| DEP-2 | „Vizualizează” arată datele propunătorului și răspunsul la fiecare câmp, doar pentru citire; fișierele sunt linkuri de descărcare. |
| DEP-3 | „Întoarce la propunător” (doar la proiectele depuse) cere confirmare și readuce proiectul în starea Nedepus, cu datele și fișierele păstrate, astfel încât propunătorul îl poate modifica și depune din nou. |
| DEP-4 | La întoarcere se trimite o notificare propunătorului. Deocamdată ea este scrisă în `linkuri.txt`, sub forma `<email>: "Deoarece proiectul nr. … de la apelul „…” (…) a fost modificat, este nevoie să completați câteva date adiționale. Proiectul poate fi completat aici: <link>"`. |
| DEP-5 | Dacă termenul limită a trecut, mesajul de confirmare avertizează că propunătorul poate modifica proiectul doar dacă termenul este prelungit. |
| DEP-6 | Paginile de depuneri sunt accesibile doar administratorilor. |

### 4.5 Evaluare (evaluator)

| Cod | Cerință |
|---|---|
| EVA-1 | „De evaluat” listează apelurile la care utilizatorul este evaluator: titlu, cod, stare, termen limită, numărul de proiecte depuse și butonul „Vezi proiectele”. |
| EVA-2 | Pagina unui apel listează doar proiectele depuse: număr, propunător, email, data depunerii, starea evaluării proprii și butonul de evaluare. |
| EVA-3 | Starea evaluării proprii este „Neîncepută” (buton „Evaluează”), „X / maxim – de continuat” (buton „Continuă evaluarea”) sau „X / maxim – final” (buton „Vezi evaluarea”). |
| EVA-4 | Pagina de evaluare arată datele proiectului și, pentru fiecare câmp, răspunsul propunătorului și nota. Câmpurile fără punctaj arată „nu se punctează”. |
| EVA-5 | Nota unui câmp punctat este un număr între 0 și punctajul maxim al câmpului; sunt acceptate zecimale (punct sau virgulă). |
| EVA-6 | Punctajul total (de exemplu „59.5 / 100”) se actualizează pe măsură ce sunt introduse notele. |
| EVA-7 | „Salvează evaluarea” acceptă și note lipsă; evaluarea poate fi continuată ulterior de unde a rămas. |
| EVA-8 | „Finalizează evaluarea” cere toate notele și confirmare; după finalizare evaluarea poate fi doar vizualizată, iar serverul refuză orice modificare. |
| EVA-9 | Fiecare evaluator are propria evaluare a fiecărui proiect, independentă de a celorlalți evaluatori; evaluatorii nu își văd notele unii altora. |
| EVA-10 | Evaluatorul poate descărca fișierele tuturor proiectelor din apelurile lui. |

## 5. Reguli de business

### 5.1 Starea și termenul unui apel

- Se pot crea și modifica propuneri doar la apelurile cu starea **Deschis** și cu termenul limită de depunere netrecut.
- Un termen limită fără oră ține până la sfârșitul acelei zile. Un apel fără termen limită rămâne deschis.
- După termenul limită: apelul dispare din „Propuneri”, propunerile nedepuse devin doar pentru citire, iar accesul direct (adresa paginii sau trimiterea formularului) este refuzat.
- Toate calculele de timp folosesc ora României (`Europe/Bucharest`, setată în `utils/config.php`).

### 5.2 O singură propunere per apel

Un utilizator are cel mult o propunere la un apel. Dacă are deja una, pagina apelului îl duce la propunerea existentă, iar o a doua propunere trimisă direct este refuzată.

### 5.3 Ciornă și depunere

| Stare | Propunătorul poate | Evaluatorii văd proiectul |
|---|---|---|
| Ciornă (nedepus) | modifica, salva, depune | nu |
| Depus | doar vizualiza | da |
| Întors la propunător | modifica, salva, depune din nou | nu (evaluarea începută rămâne salvată) |

### 5.4 Conflict de interese

Un evaluator nu își poate evalua propriul proiect. De aceea:

- un evaluator nu poate crea, salva sau depune propuneri la apelurile la care este evaluator;
- nu poate fi trecut ca evaluator cine are deja o propunere (depusă sau ciornă) la acel apel;
- ca protecție suplimentară, pagina de evaluare refuză propriul proiect, iar în listă acesta apare ca „Propriul proiect – nu poate fi evaluat”.

Utilizatorii sunt identificați după adresa de email (fără deosebire între litere mari și mici).

### 5.5 Fișiere încărcate

- Formatul este verificat după extensie, față de formatele acceptate ale câmpului. Extensiile executabile sau de tip script (`php`, `phtml`, `phar`, `htaccess`, `exe`, `js`, `html`, `svg` etc.) sunt refuzate întotdeauna.
- Pe disc fișierul primește un nume aleator; numele original este păstrat doar în baza de date.
- Directorul `uploads/` este blocat pentru accesul direct (`uploads/.htaccess`); fișierele se descarcă doar prin aplicație.
- Au acces la un fișier: proprietarul proiectului, administratorii și evaluatorii apelului.
- Dimensiunea maximă este dată de configurarea PHP (`upload_max_filesize`, `post_max_size`; local 40 MB, respectiv 256 MB).

## 6. Modelul de date

Baza de date este SQLite, în fișierul `utils/database.db`.

| Tabelă | Conține | Coloane importante |
|---|---|---|
| `users` | Utilizatorii | `email` (unic), `firstname`, `lastname`, `faculty`, `login_token`, `token_created_at` |
| `roles` | Rolurile | `user_id`, `project_id`, `role`: `Admin` (cu `project_id = 0`) sau `Owner` (proprietarul propunerii `project_id`) |
| `calls` | Apelurile | `title`, `code`, `objectives`, `eligibility_rules`, `budget_total`, `status`, cele 14 etape ale calendarului, `description` (definiția formularului, JSON) |
| `call_evaluators` | Evaluatorii fiecărui apel | `call_id`, `email`, `position`; unic pe (`call_id`, `email`) |
| `projects` | Propunerile | `call_id`, `user_id`, `propunere` (răspunsurile, JSON), `submitted_at` (completat = depus) |
| `uploads` | Fișierele propunerilor | `project_id`, `field_id`, `original_name`, `stored_name` (numele de pe disc), `mime_type`, `size_bytes` |
| `evaluations` | Evaluările | `project_id`, `evaluator_email`, `scores` (JSON), `total`, `finalized_at` (completat = finalizată); unic pe (`project_id`, `evaluator_email`) |

### Formate JSON

**`calls.description`** – lista câmpurilor formularului, în ordinea afișării:

```json
[
  {"id": "brainmap_code", "label": "Codul Brainmap", "type": "text", "description": "", "required": false, "score": 0, "fixed": true},
  {"id": "abstract", "label": "Rezumatul proiectului", "type": "textarea", "description": "Cel mult 300 de cuvinte.", "required": true, "score": 20},
  {"id": "research_field", "label": "Domeniul de cercetare", "type": "select", "required": true, "score": 0, "options": ["Științe exacte", "Științe ale vieții"]},
  {"id": "work_plan", "label": "Plan de lucru (PDF)", "type": "file", "required": true, "score": 10, "fileFormats": ".pdf"}
]
```

**`projects.propunere`** – răspunsurile, indexate după `id`-ul câmpului (checkbox = listă de opțiuni; fișierele nu apar aici, ci în `uploads`):

```json
{"brainmap_code": "U-1700-039R-5521", "abstract": "…", "research_field": "Științe umaniste"}
```

**`evaluations.scores`** – notele, indexate după `id`-ul câmpului punctat:

```json
{"purpose": 32.5, "teaching_relevance": 27}
```

**Calendarul** (coloanele de la `submission_start_at` la `implementation_end_date`): text `AAAA-LL-ZZ HH:MM` sau `AAAA-LL-ZZ` când ora nu este definită.

### Fișiere pe disc

```
uploads/
  .htaccess                       accesul direct interzis
  <id apel>/<id proiect>/<nume aleator>.<extensie>
```

## 7. Arhitectura și rutele

### Tehnologii

- PHP (testat cu PHP 8.2 în XAMPP), fără framework; MVC propriu.
- SQLite prin PDO.
- Bootstrap 4.1 (și 5.3 pe câteva pagini), jQuery 3.3 și Popper 1.14, toate incluse local în `css/` și `js/`, deci aplicația funcționează fără internet.
- Apache cu `mod_rewrite`.

### Cum este tratată o cerere

1. `.htaccess` trimite orice adresă care nu este un fișier real către `index.php`.
2. `index.php` caută în adresă primul segment care conține `Controller`; următorul segment este acțiunea, iar restul sunt parametri (de exemplu `/projectsController/editProject/7`).
3. Utilizatorul este identificat la fiecare cerere după cookie-ul `login_token`, împreună cu rolurile lui.
4. Controllerul (de exemplu `ProjectsController`) încarcă automat modelul și view-ul cu același nume (`ProjectsModel`, `ProjectsView`).
5. View-ul completează un șablon din `views/TPL/` (marcaje de forma `{NUME}`) și întoarce HTML-ul.

### Structura

| Director / fișier | Conținut |
|---|---|
| `index.php` | Controllerul frontal (rutare, autentificare) |
| `controllers/` | `UserController`, `MainController`, `CallController`, `ProjectsController`, `SubmissionsController`, `EvaluationController` |
| `models/` | Modelele corespunzătoare; `CallModel` conține și regulile calendarului, ale câmpurilor fixe și ale evaluatorilor |
| `views/`, `views/TPL/` | View-urile și șabloanele HTML; `projectpage.tpl` este șablonul comun al paginilor de propuneri, depuneri și evaluare |
| `utils/` | `db.php` (conexiunea), `config.php` (`SITE_URL`, fusul orar), `template.php`, `menu.php` (meniul comun), `autoloader.php`, `init_DB.php` |
| `uploads/` | Fișierele încărcate |
| `linkuri.txt` | Linkurile de autentificare și notificările, până la trimiterea reală a emailurilor |

### Rute

| Rută | Cine | Ce face |
|---|---|---|
| `/` | autentificat | Pagina principală: datele utilizatorului, deautentificare |
| `UserController/loginForm`, `auth`, `register` | oricine | Conectare, link de autentificare, înregistrare |
| `UserController/logout`, `globalLogout` | autentificat | Deautentificare locală / globală |
| `callController/listCalls` | admin | Lista apelurilor |
| `callController/createCall`, `editCall/<id>` | admin | Editorul de apel |
| `callController/saveCall`, `deleteCall` | admin | Salvare (JSON) și ștergere apel |
| `submissionsController/listSubmissions/<id apel>` | admin | Depunerile unui apel |
| `submissionsController/viewProject/<id proiect>` | admin | Vizualizarea unui proiect |
| `submissionsController/returnProject` (POST) | admin | Întoarcerea proiectului la propunător |
| `projectsController/displayCalls` | autentificat | Apelurile deschise („Propuneri”) |
| `projectsController/viewCall/<id apel>` | autentificat | Pagina apelului și formularul unei propuneri noi |
| `projectsController/saveProject` (POST) | autentificat | Salvarea sau depunerea unei propuneri |
| `projectsController/myProjects` | autentificat | „Proiectele dvs.” |
| `projectsController/editProject/<id proiect>` | proprietar | Propunerea, precompletată |
| `projectsController/download/<id fișier>` | proprietar, admin, evaluatorii apelului | Descărcarea unui fișier |
| `projectsController/toEvaluate[/<id apel>]` | evaluator | Apelurile de evaluat / proiectele unui apel |
| `evaluationController/evaluate/<id proiect>` | evaluatorii apelului | Pagina de evaluare |
| `evaluationController/saveEvaluation` (POST) | evaluatorii apelului | Salvarea sau finalizarea evaluării |

## 8. Instalare și date de test

### Instalare

1. Copiați proiectul în `htdocs/UAIC_projects` al XAMPP (cu PHP 8.x). Dacă directorul sau portul diferă, modificați `SITE_URL` în `utils/config.php`.
2. Verificați că sunt active `mod_rewrite` și extensiile PHP `pdo_sqlite` și `fileinfo` (implicit în XAMPP).
3. Pe Linux sau macOS, Apache trebuie să poată scrie în `utils/`, `uploads/` și `linkuri.txt`.
4. Inițializați baza de date (mai jos) sau copiați un `utils/database.db` existent împreună cu directorul `uploads/`.

### Baza de date de test

`utils/init_DB.php` creează toate tabelele și le populează cu un scenariu complet. Rulează doar pe o bază goală:

1. ștergeți `utils/database.db` și conținutul directorului `uploads/` (fără `uploads/.htaccess`);
2. rulați `php utils/init_DB.php` sau deschideți `http://localhost/UAIC_projects/utils/init_DB.php`.

| Utilizator | Rol în datele de test |
|---|---|
| user1@gmail.com (Andrei Popa) | administrator |
| user2@gmail.com (Elena Ionescu) | evaluator la toate cele 3 apeluri |
| user3@gmail.com (Mihai Rusu) | evaluator la primele 2 apeluri |
| user4@gmail.com (Ioana Munteanu) | propunător |
| user5@gmail.com (Radu Ciobanu) | propunător |

| Apel | Stare | Propuneri și evaluări |
|---|---|---|
| Granturi interne pentru tineri cercetători 2027 | Deschis (termen limită la ~30 de zile de la rulare) | user5 – depusă, cu PDF; user4 – ciornă |
| Mobilități academice internaționale 2026 | Închis, în evaluare | user4 și user5 – depuse; user2 a finalizat evaluarea proiectului lui user4 (88/100), user3 a început-o (59.5/100) |
| Modernizarea laboratoarelor didactice 2027 | Draft | – |

La fiecare apel, câmpurile punctate însumează 100 de puncte. Datele calendarului sunt calculate față de ziua rulării scriptului.

Pentru autentificare: introduceți adresa pe pagina de conectare, apoi deschideți linkul apărut în `linkuri.txt`.

## 9. Limitări cunoscute și decizii deschise

### Funcționalități neimplementate

- Trimiterea efectivă a emailurilor (autentificare și notificări); acum se scriu în `linkuri.txt`.
- Afișarea rezultatelor evaluării (de exemplu media evaluatorilor) pentru administrator și propunători.
- Folosirea etapelor din calendar după termenul limită de depunere (eligibilitate, contestații, perioada de evaluare); deocamdată ele sunt doar informative.

### Decizii deschise

- Un proiect întors la propunător după termenul limită nu poate fi modificat decât dacă se prelungește termenul.
- Câmpul „Codul Brainmap” este opțional și nu se punctează; poate deveni obligatoriu.
- Notele de evaluare acceptă zecimale; pot fi limitate la numere întregi.
- Tabelul depunerilor arată și propunerile nedepuse; poate fi limitat la cele depuse.

### Probleme de rezolvat înainte de o instalare publică

- Fișierele din `utils/` (`database.db`, `savecall_debug.log`, `show_DB.php`, `init_DB.php` și scripturile de test `simulate_savecall.php`, `test_update_call.php`, `get_call_18.php`, `get_call_21.php`, `check_last_call.php`) și `linkuri.txt` sunt accesibile prin browser fără autentificare. Logul conține tokenuri de autentificare.
- Deautentificarea globală golește tokenul în loc să îl șteargă; un token gol ar putea fi folosit pentru autentificare.
- Tokenurile de autentificare nu expiră (cele 3 zile se aplică doar cookie-ului).
- Formularele nu au protecție CSRF, iar deautentificarea se face prin GET.
- Înregistrarea unui email care există deja produce o eroare necontrolată.
- Ștergerea unui apel nu șterge propunerile, fișierele și evaluările lui.
- Datele de creare, modificare și depunere sunt salvate în UTC (cu 2–3 ore în urma orei României), în timp ce termenele sunt în ora României.
- `.htaccess` conține o setare cPanel pentru PHP 7.0, rămasă de pe serverul de producție.
