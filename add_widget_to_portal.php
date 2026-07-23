<?php
$content = file_get_contents('client_portal.php');
$content = str_replace("<?php require_once 'includes/footer.php';", "<?php require_once 'includes/client_chat_widget.php'; ?>\n<?php require_once 'includes/footer.php';", $content);
file_put_contents('client_portal.php', $content);
echo "Included widget.";
