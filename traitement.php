<?php require 'lib.php'; need();
$date=$_GET['date']??''; $mode=$_GET['mode']??'union';
$rows=extraire($date);
if($mode==='extraction'){
  csv("extraction_$date.csv",$rows?array_keys($rows[0]):['VIDE'],$rows);
}
if($mode==='detail'){ calculer($rows,$d); csv("detail_$date.csv",['REFERENCE','ROLE','COMPTE','INTITULE','DEBIT','CREDIT'],$d); }
if($mode==='affiliee'){
  $lib='LOT COMMISSION RETRAIT RESEAU DU '.date('d/m/Y'); $fmt=fn($n)=>number_format($n,0,'','');
  calculer($rows,$d,$parts); $map=mapping(); $manque=[]; $fichiers=[]; $info=[];
  foreach($parts as $mufid=>$montant){
    if($montant<=0){ $info[]="$mufid : montant $montant (ignorée, montant nul)"; continue; }
    if(stripos($mufid,MOT_CLE_UNION)!==false){ $info[]="$mufid : montant $montant (ignorée, MUFID UNION)"; continue; }
    $m=$map[strtoupper(trim($mufid))]??null;
    if(!$m){ $manque[]=$mufid; continue; }
    $fichiers[preg_replace('/[^A-Za-z0-9_-]+/','_',$mufid).'_'.date('Ymd').'.csv']=csvTexte(
      ['COMPTE','INTITULE','LIBELLE','DEBIT','CREDIT','PROVISION'],
      [[$m[1],$m[2],$lib,$fmt($montant),$fmt(0),''],[$m[3],$m[4],$lib,$fmt(0),$fmt($montant),'']]);
  }
  if($manque) die('Comptes miroir/produit introuvables dans comptes_mufid.csv pour : '.htmlspecialchars(implode(', ',$manque)));
  if(!$fichiers){
    $vues=array_unique(array_map(fn($r)=>(string)$r['INTITULE'],$rows));
    die('<pre style="font:14px monospace;padding:20px">Aucune commission à traiter pour cette date.'
      ."\n\nLignes extraites : ".count($rows)
      ."\nValeurs de la colonne INTITULE dans l'extraction : ".htmlspecialchars(implode(' | ',$vues))
      ."\nMUFID calculées : ".($parts?'':'aucune')
      ."\n".htmlspecialchars(implode("\n",$info))
      .'</pre>');
  }
  if(!class_exists('ZipArchive')) die('Extension PHP zip requise (activer extension=zip dans php.ini).');
  $tmp=tempnam(sys_get_temp_dir(),'od'); $z=new ZipArchive; $z->open($tmp,ZipArchive::OVERWRITE);
  foreach($fichiers as $n=>$c) $z->addFromString($n,$c); $z->close();
  header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="OD_MUFID_AFFILIEES_'.date('Ymd').'.zip"');
  readfile($tmp); unlink($tmp); exit;
}
$fmt=fn($n)=>number_format($n,0,'','');
$lib='LOT COMMISSION RETRAIT RESEAU DU '.date('d/m/Y'); // date du jour de téléchargement
$lignes=[]; $td=0; $tc=0;
foreach(calculer($rows) as $compte=>$c){ $td+=$c['debit']; $tc+=$c['credit']; $lignes[]=[$compte,$c['intitule'],$lib,$fmt($c['debit']),$fmt($c['credit']),''];}
if($td!==$tc){ die("OD non équilibrée : débit $td / crédit $tc"); }
csv('OD_commissions_'.date('Ymd').'.csv',['COMPTE','INTITULE','LIBELLE','DEBIT','CREDIT','PROVISION'],$lignes);
