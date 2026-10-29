<?php require 'lib.php';
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(($_POST['login']??'')==='admin' && ($_POST['pwd']??'')==='admin'){ $_SESSION['ok']=1; header('Location: accueil.php'); exit; }
  $err='Identifiants incorrects';
}?>
<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Connexion</title><link rel="stylesheet" href="style.css"></head><body class="center">
<form method="post" class="card"><img src="logo.png" alt="Logo" class="logo-login"><h1 style="text-align: center;">Connexion</h1>
<?php if($err) echo "<p class='err'>$err</p>"; ?>
<label>Login</label><input name="login" required autofocus>
<label>Mot de passe</label><input type="password" name="pwd" required>
<button>Se connecter</button></form></body></html>
