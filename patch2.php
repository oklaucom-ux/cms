<?php
$content = file_get_contents('chat.php');
$target = '<div id="dynamicUsersList">';
$replace = '<div id="dynamicUsersList">
                <?php foreach($internal_users as $u):
                    $uId   = json_encode($u["login_id"]);
                    $uName = json_encode($u["name"]);
                    $initial = strtoupper(substr($u["name"], 0, 1));
                ?>
                    <div class="user-list-item" data-login-id="<?= htmlspecialchars($u["login_id"], ENT_QUOTES) ?>" onclick="selectUser(event, <?= htmlspecialchars($uId, ENT_QUOTES) ?>, <?= htmlspecialchars($uName, ENT_QUOTES) ?>)">
                        <div class="chat-avatar avatar-user"><?= $initial ?></div>
                        <div style="flex:1; min-width:0;">
                            <strong><?= htmlspecialchars($u["name"]) ?></strong>
                            <span>@<?= htmlspecialchars($u["login_id"]) ?></span>
                        </div>
                        <div class="unread-badge" style="display:none; background:#ef4444; color:white; font-size:11px; font-weight:bold; padding:2px 6px; border-radius:10px;">0</div>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($internal_users)): ?>
                    <div style="padding:20px; color:#94a3b8; text-align:center; font-size:13px;">No other users found.</div>
                <?php endif; ?>';
                
$content = preg_replace('/<div id="dynamicUsersList">\s*<\/div>/', $replace, $content);
file_put_contents('chat.php', $content);
echo "Fixed chat.php";
