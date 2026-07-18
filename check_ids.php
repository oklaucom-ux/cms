<?php
$c = file_get_contents('reception.php');
preg_match_all("/document\.getElementById\('([^']+)'\)/", $c, $m);
$ids = $m[1];
preg_match_all('/id="([^"]+)"/', $c, $m2);
$html_ids = $m2[1];
$diff = array_diff($ids, $html_ids);
print_r($diff);
