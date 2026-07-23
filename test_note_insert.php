<?php
require "includes/db.php";
$stmt = $pdo->prepare("INSERT INTO notes (title, content, project_id, color, is_pinned, created_by) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute(["Test", "Content", null, "#ffffff", 0, "admin"]);
echo "Note inserted";

