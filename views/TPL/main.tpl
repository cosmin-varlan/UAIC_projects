<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Manangementul proiectelor din cadrul UAIC</title>
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
            <div class="collapse navbar-collapse" id="collapsibleNavbar">
              <ul class="navbar-nav">

                {MENU}
   

              </ul>
            </div>  
          </nav>              
          </div>




          <div class="container" style="margin-top:30px">
            <div class="row">
              <div class="col-md-6">
                <h2>Informații</h2>                
                <p>Sunteți autentificat ca <strong>{USERNAME}</strong>.</p>
                <p>Pentru autentificare s-a folosit adresa de email: <br><strong>{USEREMAIL}</strong></p>
                <p>Facultatea de care ați declarat că aparțineți: <br><strong>{USERFACULTY}</strong></p>
                

              </div>
              <div class="col-md-6">
                <h2>Deautentificare</h2>
                <p>Avem două tipuri de deautentificare: dacă sunteți pe un calculator ce nu vă aparține și doriți să vă deautentificați doar de pe acesta, se va utiliza prima metodă. Celelalte calculatoare în care aveți încredere (vă aparțin) nu vor cere să vă reautentificați. Dacă însă ați uitat să vă deauntificați de pe un calculator la care și alte persoane au acces, vă recomandăm deautentificarea globală deoarece aceasta va elimina credențialele de pe toate calculatoarele pe care v-ați autentificat în trecut.</p>
                <p><a href="{URLSITE}/UserController/logout">Deautentificare</a> - doar de pe acest calculator.</p>                
                <p><a href="{URLSITE}/UserController/globalLogout">Deautentificare globala</a> - de pe toate calculatoarele pe care v-ați autentificat.</p>                
            </div>    
            </div>  
           </div>
            


        <form>
            
        </form>    
    </body>
</html>