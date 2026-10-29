<?php
session_start();
// ===== CONFIGURATION =====
const DB_NAME='octopus'; const DB_HOST='172.29.3.70'; const DB_PORT=19301; const DB_USER='jwanjo'; const DB_PASS='W@njo2025';
const COMPTE_UNION='A_RENSEIGNER';          // n° de compte commission MUFID UNION
const MOT_CLE_UNION='UNION'; // une MUFID dont le nom contient ce mot est exclue de l'OD des MUFID affiliées
const INTITULE_UNION='MUFID UNION';
const NATURE_OPERATION='RETESP_RESEAU';
// Taux (en %) : 1 ligne => UNION 75 / origine 25 ; 2 lignes => UNION 25 / origine 25 / retrait 50
const TAUX_1=['union'=>75,'origine'=>25];
const TAUX_COMMISSION=0.75;      // commission = 0,75 % du montant du retrait
const PLAFOND_COMMISSION=10000;  // plafonnée à 10 000 (avant répartition)
const TAUX_2=['union'=>25,'origine'=>25,'retrait'=>50];

function need(){ if(empty($_SESSION['ok'])){ header('Location: index.php'); exit; } }

function extraire($date){
  $pdo=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $sql="SELECT m.date_comptable_created AS DATE_COMPTABLE, m.mvt_order AS ORDRE_ECRITURE,
  m.nature_operation_code AS TYPE_OPERATION, a.numero_complet_tirets_mufid AS COMPTE, a.intitule AS INTITULE,
  IF(m.sens='Business_Libelle_Sens_Debit',m.montant,0) AS DEBIT,
  IF(m.sens='Business_Libelle_Sens_Credit',m.montant,0) AS CREDIT,
  a2.name AS MUFID, t.code AS REFERENCE
  FROM octopus.movement m
  JOIN octopus.account a ON a.account_id=m.account_id
  JOIN octopus.agency a2 ON a2.agency_id=m.agency_id
  LEFT JOIN octopus.transactions t ON t.transac_id=m.transaction_id
  WHERE m.date_comptable_created >= :d1 AND m.date_comptable_created < :d2
  AND m.nature_operation_code IN (:nat) AND a.numero_complet_tirets_mufid LIKE '%-0204-%'
  ORDER BY m.date_comptable_created ASC, m.transaction_id ASC, m.mvt_order ASC";
  $st=$pdo->prepare($sql);
  $st->execute([':d1'=>$date,':d2'=>date('Y-m-d',strtotime("$date +1 day")),':nat'=>NATURE_OPERATION]);
  return $st->fetchAll();
}

// Si le compte commission d'une MUFID se déduit du compte -0204- (ex: autre code), renseigner ici le code de remplacement
const CODE_COMPTE_COMMISSION=null; // ex: '0705' => remplace '-0204-' par '-0705-'
function compteComm($c){ return CODE_COMPTE_COMMISSION? str_replace('-0204-','-'.CODE_COMPTE_COMMISSION.'-',$c):$c; }

// Arrondi à l'entier (le système bancaire rejette les décimales) : 0,5 arrondi vers le haut
function arr($x){ return (int)round($x,0,PHP_ROUND_HALF_UP); }

function calculer($rows,&$detail=null,&$parts=null){
  $detail=[]; $parts=[]; // $parts[MUFID] = commission qui reste définitivement sur son compte 204 après l'OD UNION
  $keep=function($l,$m) use(&$parts){ $k=trim($l['INTITULE']); $parts[$k]=($parts[$k]??0)+$m; }; $net=[]; $g=[]; $i=0;
  foreach($rows as $r){ $k=($r['REFERENCE']===null||$r['REFERENCE']==='')?'SANS_REF_'.(++$i):$r['REFERENCE']; $g[$k][]=$r; }
  $add=function($ref,$role,$compte,$intitule,$deb,$cre) use(&$net,&$detail){
    if(arr($deb)==0 && arr($cre)==0) return;
    if(!isset($net[$compte])) $net[$compte]=['intitule'=>$intitule,'montant'=>0]; // montant>0 = débit, <0 = crédit
    $net[$compte]['montant']+=$deb-$cre;
    $detail[]=[$ref,$role,$compte,$intitule,arr($deb),arr($cre)];
  };
  foreach($g as $ref=>$lignes){
    $deb=array_values(array_filter($lignes,fn($l)=>(float)$l['DEBIT']>0));
    $cre=array_values(array_filter($lignes,fn($l)=>(float)$l['CREDIT']>0));
    $sD=array_sum(array_map(fn($l)=>(float)$l['DEBIT'],$lignes));
    $sC=array_sum(array_map(fn($l)=>(float)$l['CREDIT'],$lignes));
    $mt=count($lignes)==1?max($sD,$sC):($sD>0?$sD:$sC);           // montant du retrait
    $C=min(arr($mt*TAUX_COMMISSION/100),PLAFOND_COMMISSION);   // commission totale plafonnée
    if(count($lignes)==1){
      // MUFID UNION a fait le retrait : elle garde 75 %, débitée de 25 % au profit de la MUFID de la ligne
      $l=$lignes[0]; $p=arr($C*TAUX_1['origine']/100);
      $add($ref,'UNION débitée (25%)',COMPTE_UNION,INTITULE_UNION,$p,0);
      $add($ref,'MUFID créditée (25%)',compteComm($l['COMPTE']),$l['INTITULE'],0,$p); $keep($l,$p);
    } else {
      // une MUFID a fait le retrait (débitée) : elle garde 50 %, débitée de 25 % (crédit MUFID) + 25 % (crédit UNION)
      $pu=arr($C*TAUX_2['union']/100); $totCre=$pu;
      foreach($cre as $l){ $p=arr($C*TAUX_2['origine']/100*((float)$l['CREDIT']/$sC)); $totCre+=$p;
        $add($ref,'MUFID créditée (25%)',compteComm($l['COMPTE']),$l['INTITULE'],0,$p); $keep($l,$p); }
      $add($ref,'UNION créditée (25%)',COMPTE_UNION,INTITULE_UNION,0,$pu);
      $reste=$totCre; $resteC=$C; $n=count($deb);
      foreach($deb as $k=>$l){ $p=($k==$n-1)?$reste:arr($totCre*((float)$l['DEBIT']/$sD)); $reste-=$p;
        $poche=($k==$n-1)?$resteC:arr($C*((float)$l['DEBIT']/$sD)); $resteC-=$poche; $keep($l,$poche-$p);
        $add($ref,'MUFID débitée (retrait)',compteComm($l['COMPTE']),$l['INTITULE'],$p,0); }
    }
  }
  $out=[]; foreach($net as $c=>$v){ $m=arr($v['montant']);
    $out[$c]=['intitule'=>$v['intitule'],'debit'=>$m>0?$m:0,'credit'=>$m<0?-$m:0]; }
  return $out;
}

function csv($nom,$entetes,$lignes){
  header('Content-Type: text/csv; charset=utf-8');
  header("Content-Disposition: attachment; filename=\"$nom\"");
  echo "\xEF\xBB\xBF"; $f=fopen('php://output','w');
  fputcsv($f,$entetes,';'); foreach($lignes as $l) fputcsv($f,$l,';'); exit;
}

// ===== OD MUFID AFFILIEE =====
// Fichier comptes_mufid.csv (séparateur ;) : INTITULE (tel que dans l'extraction);COMPTE_MIROIR;INTITULE_MIROIR;COMPTE_PRODUIT;INTITULE_PRODUIT
function mapping(){
  $f=__DIR__.'/comptes_mufid.csv'; $m=[]; if(!is_file($f)) return $m;
  $h=fopen($f,'r'); fgetcsv($h,0,';');
  while(($r=fgetcsv($h,0,';'))!==false){ if(count($r)>=5 && trim($r[0])!=='') $m[strtoupper(trim($r[0]))]=array_map('trim',$r); }
  return $m;
}
function csvTexte($entetes,$lignes){
  $f=fopen('php://temp','w+'); fputcsv($f,$entetes,';'); foreach($lignes as $l) fputcsv($f,$l,';');
  rewind($f); return "\xEF\xBB\xBF".stream_get_contents($f);
}
