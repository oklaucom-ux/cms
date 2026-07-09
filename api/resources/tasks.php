<?php
if ($method === 'GET') {
    if ($id) {
        $s=$pdo->prepare("SELECT * FROM tasks WHERE id=?"); $s->execute([$id]);
        $t=$s->fetch(PDO::FETCH_ASSOC); $t?apiOk($t):apiError(404,'Task not found');
    } else {
        $status=$_GET['status']??null; $assigned=$_GET['assigned_to']??null;
        $where=[]; $vals=[];
        if($status){ $where[]="status=?"; $vals[]=$status; }
        if($assigned){ $where[]="assigned_to LIKE ?"; $vals[]="%{$assigned}%"; }
        $whereStr=$where?"WHERE ".implode(' AND ',$where):'';
        $rows=$pdo->prepare("SELECT * FROM tasks {$whereStr} AND status!='Deleted' ORDER BY due_date ASC LIMIT 50");
        // fix for no-param case
        $rows=$pdo->prepare("SELECT * FROM tasks ".($where?"WHERE ".implode(' AND ',$where)." AND status!='Deleted'":" WHERE status!='Deleted'")." ORDER BY due_date ASC LIMIT 50");
        $rows->execute($vals); apiOk($rows->fetchAll(PDO::FETCH_ASSOC));
    }
} elseif ($method === 'POST') {
    $b=getBody();
    if(empty($b['name'])||empty($b['assigned_to'])) apiError(400,'name and assigned_to required');
    $pdo->prepare("INSERT INTO tasks (task_id,name,description,assigned_to,due_date,priority,status,project_id) VALUES (?,?,?,?,?,?,?,?)")
        ->execute(['TSK-'.rand(1000,9999),$b['name'],$b['description']??'',$b['assigned_to'],$b['due_date']??null,$b['priority']??'Medium','Pending',$b['project_id']??0]);
    apiOk(['id'=>$pdo->lastInsertId(),'created'=>true]);
} elseif ($method === 'PUT' && $id) {
    $b=getBody(); $fields=[]; $vals=[];
    foreach(['name','description','assigned_to','due_date','priority','status','project_id'] as $f){ if(isset($b[$f])){ $fields[]="$f=?"; $vals[]=$b[$f]; } }
    if(empty($fields)) apiError(400,'No fields'); $vals[]=$id;
    $pdo->prepare("UPDATE tasks SET ".implode(',',$fields)." WHERE id=?")->execute($vals);
    apiOk(['updated'=>true]);
} elseif ($method === 'DELETE' && $id) {
    $pdo->prepare("UPDATE tasks SET status='Deleted' WHERE id=?")->execute([$id]);
    apiOk(['deleted'=>true]);
} else { apiError(405,'Method not allowed'); }
