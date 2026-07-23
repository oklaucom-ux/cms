<?php
$content = file_get_contents('chat.php');
$target = "                <?php endif; ?>\n        </div>\n\n        <!-- Chat Area -->";
$replace = "                <?php endif; ?>\n            </div>\n        </div>\n\n        <!-- Chat Area -->";
$content = str_replace($target, $replace, $content);
file_put_contents('chat.php', $content);
echo "Fixed layout";
