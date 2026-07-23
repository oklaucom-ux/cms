<?php
$html = file_get_contents('chat.php');
$doc = new DOMDocument();
@$doc->loadHTML($html);
echo 'chatArea parent: ' . $doc->getElementById('chatArea')->parentNode->getAttribute('class') . "\n";
echo 'noChatSelected parent: ' . $doc->getElementById('noChatSelected')->parentNode->getAttribute('class') . "\n";
