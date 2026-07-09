<?php
if ($method==='GET') {
    $type=$_GET['type']??null; $assigned=$_GET['assigned_to']??null;
    if($id){ $s=$pdo->prepare("SELECT * FROM assets WHERE id=?"); $s->execute([$id]); $a=$s->fetch(PDO::FETCH_ASSOC); $a?apiOk($a):apiError(404,'Not found'); }
    $where=[]; $vals=[];
    if($type){ $where[]="type=?"; $vals[]=$type; } if($assigned){ $where[]="assigned_to=?"; $vals[]=$assigned; }
    $q=$pdo->prepare("SELECT * FROM assets ".($where?"WHERE ".implode(' AND ',$where):'')." ORDER BY asset_tag");
    $q->execute($vals); apiOk($q->fetchAll(PDO::FETCH_ASSOC));
} elseif ($method==='POST') {
    $b=getBody(); if(empty($b['asset_tag'])||empty($b['name'])||empty($b['type'])) apiError(400,'asset_tag, name, type required');
    try { $pdo->prepare("INSERT INTO assets (asset_tag,name,type,assigned_to,status,condition) VALUES (?,?,?,?,?,?)")->execute([$b['asset_tag'],$b['name'],$b['type'],$b['assigned_to']??null,$b['status']??'Unassigned',$b['condition']??'Good']); apiOk(['id'=>$pdo->lastInsertId(),'created'=>true]); } catch(Exception $e){ apiError(409,$e->getMessage()); }
} elseif ($method==='PUT'&&$id) {
    $b=getBody(); $fields=[]; $vals=[];
    foreach(['name','type','assigned_to','status','condition'] as $f){ if(isset($b[$f])){ $fields[]="$f=?"; $vals[]=$b[$f]; } }
    if(empty($fields)) apiError(400,'No fields'); $vals[]=$id;
    $pdo->prepare("UPDATE assets SET ".implode(',',$fields)." WHERE id=?")->execute($vals); apiOk(['updated'=>true]);
} else { apiError(405,'Method not allowed'); }
