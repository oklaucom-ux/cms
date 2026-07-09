<?php
if ($method === 'GET') {
    $stage=$_GET['stage']??null;
    if ($id) {
        $s=$pdo->prepare("SELECT * FROM crm_leads WHERE id=?"); $s->execute([$id]);
        $l=$s->fetch(PDO::FETCH_ASSOC); $l?apiOk($l):apiError(404,'Not found');
    } else {
        $rows=$stage?$pdo->prepare("SELECT * FROM crm_leads WHERE stage=? ORDER BY last_contact DESC"):$pdo->query("SELECT * FROM crm_leads ORDER BY last_contact DESC");
        if($stage) $rows->execute([$stage]); apiOk($rows->fetchAll(PDO::FETCH_ASSOC));
    }
} elseif ($method === 'POST') {
    $b=getBody(); if(empty($b['lead_name'])||empty($b['owner_id'])) apiError(400,'lead_name and owner_id required');
    $pdo->prepare("INSERT INTO crm_leads (lead_name,company,email,value,stage,owner_id) VALUES (?,?,?,?,?,?)")
        ->execute([$b['lead_name'],$b['company']??'',$b['email']??'',$b['value']??0,$b['stage']??'Prospect',$b['owner_id']]);
    apiOk(['id'=>$pdo->lastInsertId(),'created'=>true]);
} elseif ($method === 'PUT' && $id) {
    $b=getBody(); $fields=[]; $vals=[];
    foreach(['lead_name','company','email','value','stage','owner_id'] as $f){ if(isset($b[$f])){ $fields[]="$f=?"; $vals[]=$b[$f]; } }
    if(empty($fields)) apiError(400,'No fields'); $vals[]=$id;
    $pdo->prepare("UPDATE crm_leads SET ".implode(',',$fields)." WHERE id=?")->execute($vals); apiOk(['updated'=>true]);
} else { apiError(405,'Method not allowed'); }
