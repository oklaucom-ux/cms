<?php
global $pdo;

$queries = [
    "UPDATE applicants SET status = 'Applied' WHERE status = 'New'"
];

return $queries;
