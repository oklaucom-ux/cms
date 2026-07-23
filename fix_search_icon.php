<?php
$content = file_get_contents('includes/header.php');
$content = preg_replace('/<span id="searchIcon">.*?<\/span>/s', '<i id="searchIcon" class="fas fa-search"></i>', $content);
file_put_contents('includes/header.php', $content);
echo "Fixed search icon";
