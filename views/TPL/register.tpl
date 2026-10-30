<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Inregistrare</title>
        <!--link href="{URLSITE}/css/bootstrap-5.3.3.min.css" rel="stylesheet">
        <script src="{URLSITE}/js/bootstrap-5.3.3.bundle.min.js"></script-->
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
            <!--div class="collapse navbar-collapse" id="collapsibleNavbar">
              <ul class="navbar-nav">
                <li class="nav-item">
                  <a class="nav-link" href="index.php">Înscriere grup</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="editare.php">Grupurile mele</a>
                </li>      
                <li class="nav-item">
                  <a class="nav-link" href="informatii.php">Informaţii</a>
                </li>           
                <li class="nav-item">
                  <a class="nav-link" href="en_index.php">[ENG]</a>
                </li>                               
              </ul>
            </div-->  
          </nav>              
          </div>
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h3 class="card-title text-center mb-4">Înregistrare</h3>
                            {MESSAGE_BLOCK}
                            <form method="post" action="{URLSITE}/UserController/register">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Adresă de email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="{EMAIL}" required>
                                </div>
                                <div class="mb-3">
                                    <label for="firstname" class="form-label">Prenume</label>
                                    <input type="text" class="form-control" id="firstname" name="firstname" value="{FIRSTNAME}" required>
                                </div>
                                <div class="mb-3">
                                    <label for="lastname" class="form-label">Nume</label>
                                    <input type="text" class="form-control" id="lastname" name="lastname" value="{LASTNAME}" required>
                                </div>
                                <div class="mb-3">
                                    <label for="faculty" class="form-label">Facultate</label>
                                    <input type="text" class="form-control" id="faculty" name="faculty" value="{FACULTY}" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Înregistrează-mă</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
