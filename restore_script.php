<?php
$old_content = file_get_contents('old_dashboard.php');
$new_content = file_get_contents('dashboard.php');

if (preg_match('/<script>[\s\S]*?revenueChart[\s\S]*?<\/script>/i', $old_content, $matches)) {
    $script_block = $matches[0];
    
    $tag = '<?php else: ' . '?>';
    if (strpos($new_content, $tag) !== false) {
        $updated_content = str_replace($tag, $script_block . "\n\n    " . $tag, $new_content);
        file_put_contents('dashboard.php', $updated_content);
        echo "Successfully restored script block!\n";
    } else {
        echo "Could not find else tag in dashboard.php\n";
    }
} else {
    echo "Could not find the script block in old_dashboard.php\n";
}
