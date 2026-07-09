<?php
if ($method==='GET') {
    $uid=$_GET['user_id']??null; $status=$_GET['status']??null;
    if($id){ $s=$pdo->prepare("SELECT * FROM leaves WHERE id=?"); $s->execute([$id]); $l=$s->fetch(PDO::FETCH_ASSOC); $l?apiOk($l):apiError(404,'Not found'); }
    $where=[]; $vals=[];
    if($uid){ $where[]="user_id=?"; $vals[]=$uid; } if($status){ $where[]="status=?"; $vals[]=$status; }
    $q=$pdo->prepare("SELECT * FROM leaves ".($where?"WHERE ".implode(' AND ',$where):'')." ORDER BY created_at DESC");
    $q->execute($vals); apiOk($q->fetchAll(PDO::FETCH_ASSOC));
} elseif ($method==='POST') {
    $b=getBody(); if(empty($b['user_id'])||empty($b['leave_type'])||empty($b['start_date'])||empty($b['end_date'])) apiError(400,'Required: user_id, leave_type, start_date, end_date');
    $pdo->prepare("INSERT INTO leaves (user_id,start_date,end_date,leave_type,reason,status) VALUES (?,?,?,?,?,'Pending')")->execute([$b['user_id'],$b['start_date'],$b['end_date'],$b['leave_type'],$b['reason']??'']);
    apiOk(['id'=>$pdo->lastInsertId(),'created'=>true]);
} elseif ($method==='PUT'&&$id) {
    $b=getBody(); if(!empty($b['status'])) { $pdo->prepare("UPDATE leaves SET status=? WHERE id=?")->execute([$b['status'],$id]); apiOk(['updated'=>true]); } else apiError(400,'No status field');
} else { apiError(405,'Method not allowed'); }
