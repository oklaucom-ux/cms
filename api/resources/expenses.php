<?php
if ($method==='GET') {
    $status=$_GET['status']??null;
    if($id){ $s=$pdo->prepare("SELECT * FROM expenses WHERE id=?"); $s->execute([$id]); $e=$s->fetch(PDO::FETCH_ASSOC); $e?apiOk($e):apiError(404,'Not found'); }
    $rows=$status?$pdo->prepare("SELECT * FROM expenses WHERE status=? ORDER BY created_at DESC"):$pdo->query("SELECT * FROM expenses ORDER BY created_at DESC");
    if($status) $rows->execute([$status]); apiOk($rows->fetchAll(PDO::FETCH_ASSOC));
} elseif ($method==='POST') {
    $b=getBody(); if(empty($b['user_id'])||empty($b['category'])||empty($b['amount'])) apiError(400,'user_id, category, amount required');
    $pdo->prepare("INSERT INTO expenses (user_id,project_id,category,amount,description,status) VALUES (?,?,?,?,?,'Pending')")->execute([$b['user_id'],$b['project_id']??0,$b['category'],$b['amount'],$b['description']??'']);
    apiOk(['id'=>$pdo->lastInsertId(),'created'=>true]);
} elseif ($method==='PUT'&&$id) {
    $b=getBody(); if(!empty($b['status'])) { $pdo->prepare("UPDATE expenses SET status=? WHERE id=?")->execute([$b['status'],$id]); apiOk(['updated'=>true]); } else apiError(400,'No fields');
} else { apiError(405,'Method not allowed'); }
