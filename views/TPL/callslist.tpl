<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Managementul proiectelor UAIC</title>
        <link href="{URLSITE}/css/bootstrap-5.3.3.min.css" rel="stylesheet">
        <script src="{URLSITE}/js/bootstrap-5.3.3.bundle.min.js"></script>
        <link rel="stylesheet" href="{URLSITE}/css/bootstrap-4.1.0.min.css">
        <link rel="stylesheet" href="{URLSITE}/css/style.css">
        
        <script src="{URLSITE}/js/jquery-3.3.1.min.js"></script>
        <script src="{URLSITE}/js/popper-1.14.0.min.js"></script>
        <script src="{URLSITE}/js/bootstrap-4.1.0.min.js"></script>        
        
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
            <div class="row mb-3">
              <div class="col-md-1"></div>
              <div class="col-md-10 text-right">
                <a href="{URLSITE}/callController/createCall" class="btn btn-success">Crează un nou call</a>
              </div>
              <div class="col-md-1"></div>
            </div>
            <div class="row">
              <div class="col-md-1"></div>
              <div class="col-md-10">
                {CALLS}
              </div>
              <div class="col-md-1"></div>
            </div>
           </div>
            


        <form>
            
        </form>    
    </body>
</html>