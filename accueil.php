<?php require 'lib.php'; need();
$date=$_GET['date']??''; $rows=null; $err='';
if($date){ try{ $rows=extraire($date); }catch(Exception $e){ $err=$e->getMessage(); } }
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Accueil</title><link rel="stylesheet" href="style.css"></head><body>
<div id="loader" class="loader" hidden><div class="spin"></div><p id="loadmsg">Exécution de la requête en cours…</p></div>
<header><span class="brand"><img src="logo.png" alt="Logo" class="logo"><b>OD Commissions retrait réseau</b></span><a href="logout.php">Déconnexion</a></header>
<main><div class="card wide"><h1>Bienvenue</h1>
<form method="get" class="row" id="fReq" data-msg="Exécution de la requête en cours…"><label>Date comptable</label>
<input type="date" name="date" value="<?= htmlspecialchars($date) ?>" required><button>Exécuter la requête</button></form>
<?php if($err) echo "<p class='err'>".htmlspecialchars($err)."</p>"; ?>
<?php if($rows!==null): ?>
<p><?= count($rows) ?> ligne(s) extraite(s).</p>
<?php if($rows): ?>
<div class="row">
<a class="btn sec" href="traitement.php?mode=extraction&date=<?= urlencode($date) ?>">Télécharger l'extraction (Excel)</a>
<a class="btn sec" href="traitement.php?mode=detail&date=<?= urlencode($date) ?>">Détail par transaction</a>
<a class="btn" href="traitement.php?mode=union&date=<?= urlencode($date) ?>">Traitement → générer l'OD MUFID UNION</a>
<a class="btn" href="traitement.php?mode=affiliee&date=<?= urlencode($date) ?>">Traitement → générer l'OD MUFID AFFILIEE</a></div>
<div class="scroll"><table><tr><?php foreach(array_keys($rows[0]) as $h) echo "<th>$h</th>"; ?></tr>
<?php foreach($rows as $r){ echo '<tr>'; foreach($r as $v) echo '<td>'.htmlspecialchars((string)$v).'</td>'; echo '</tr>'; } ?></table></div>
<?php endif; endif; ?></div></main><script>
function showLoader(m){document.getElementById('loadmsg').textContent=m;document.getElementById('loader').hidden=false;}
document.getElementById('fReq').addEventListener('submit',function(){showLoader(this.dataset.msg);});
// Boutons de téléchargement : afficher le chargement quelques secondes (le fichier se télécharge sans quitter la page)
document.querySelectorAll('a.btn').forEach(function(l){l.addEventListener('click',function(){
  showLoader('Génération du fichier en cours…');setTimeout(function(){document.getElementById('loader').hidden=true;},4000);});});
// Retour arrière navigateur : masquer le loader
window.addEventListener('pageshow',function(){document.getElementById('loader').hidden=true;});
</script>
</body></html>
