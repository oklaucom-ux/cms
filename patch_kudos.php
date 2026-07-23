<?php
$content = file_get_contents('controllers/save_kudos.php');
$target = "// Auto-migrate schema";
$replace = "// Auto-migrate schema\n    \$receiver = \$_POST['receiver_id'];\n    \$points = intval(\$_POST['points']);\n    \$message = \$_POST['message'];\n";
$content = str_replace($target, $replace, $content);
file_put_contents('controllers/save_kudos.php', $content);
echo "Fixed.";
