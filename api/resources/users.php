<?php
// GET /api/users, GET /api/users/1, POST /api/users, PUT /api/users/1
if ($method === 'GET') {
    if ($id) {
        $s = $pdo->prepare("SELECT id,login_id,name,email,role,department,status,created_at FROM users WHERE id=?"); $s->execute([$id]);
        $u = $s->fetch(PDO::FETCH_ASSOC);
        $u ? apiOk($u) : apiError(404,'User not found');
    } else {
        $page = max(1,intval($_GET['page']??1)); $limit=intval($_GET['limit']??25);
        $offset=($page-1)*$limit;
        $total=$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $rows=$pdo->query("SELECT id,login_id,name,email,role,department,status,created_at FROM users LIMIT {$limit} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);
        apiOk($rows,['total'=>(int)$total,'page'=>$page,'limit'=>$limit]);
    }
} elseif ($method === 'POST') {
    $b = getBody();
    if (empty($b['login_id'])||empty($b['name'])||empty($b['email'])) apiError(400,'login_id, name, email required');
    $hash=password_hash($b['password']??'changeme123',PASSWORD_BCRYPT);
    try {
        $pdo->prepare("INSERT INTO users (login_id,password,name,email,role,department,status) VALUES (?,?,?,?,?,?,'Active')")
            ->execute([$b['login_id'],$hash,$b['name'],$b['email'],$b['role']??'Employee',$b['department']??'']);
        apiOk(['id'=>$pdo->lastInsertId(),'login_id'=>$b['login_id'],'created'=>true]);
    } catch(Exception $e){ apiError(409,'Conflict: '.$e->getMessage()); }
} elseif ($method === 'PUT' && $id) {
    $b=getBody();
    $fields=[]; $vals=[];
    foreach(['name','email','role','department','status'] as $f){ if(isset($b[$f])){ $fields[]="$f=?"; $vals[]=$b[$f]; } }
    if(empty($fields)) apiError(400,'No fields to update');
    $vals[]=$id;
    $pdo->prepare("UPDATE users SET ".implode(',',$fields)." WHERE id=?")->execute($vals);
    apiOk(['updated'=>true,'id'=>$id]);
} else { apiError(405,'Method not allowed'); }
