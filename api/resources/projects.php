<?php
if ($method === 'GET') {
    if ($id) {
        $s=$pdo->prepare("SELECT * FROM projects WHERE id=?"); $s->execute([$id]);
        $p=$s->fetch(PDO::FETCH_ASSOC); if(!$p) apiError(404,'Not found');
        $tasks=$pdo->prepare("SELECT id,name,status,due_date FROM tasks WHERE project_id=?"); $tasks->execute([$id]);
        $p['tasks']=$tasks->fetchAll(PDO::FETCH_ASSOC); apiOk($p);
    } else {
        $rows=$pdo->query("SELECT * FROM projects ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC); apiOk($rows);
    }
} elseif ($method === 'POST') {
    $b=getBody();
    if(empty($b['name'])) apiError(400,'name required');
    $pdo->prepare("INSERT INTO projects (name,client,budget,deadline,status,created_by) VALUES (?,?,?,?,?,?)")
        ->execute([$b['name'],$b['client']??'',$b['budget']??0,$b['deadline']??null,$b['status']??'Planning','api']);
    apiOk(['id'=>$pdo->lastInsertId(),'created'=>true]);
} elseif ($method === 'PUT' && $id) {
    $b=getBody(); $fields=[]; $vals=[];
    foreach(['name','client','budget','deadline','status'] as $f){ if(isset($b[$f])){ $fields[]="$f=?"; $vals[]=$b[$f]; } }
    if(empty($fields)) apiError(400,'No fields'); $vals[]=$id;
    $pdo->prepare("UPDATE projects SET ".implode(',',$fields)." WHERE id=?")->execute($vals); apiOk(['updated'=>true]);
} else { apiError(405,'Method not allowed'); }
