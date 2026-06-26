<?php
$content = file_get_contents('index.php');

$pattern = '/\$([a-zA-Z0-9_]+)\s*=\s*json_decode\(\$marzban_list_get\[\'([a-zA-Z0-9_]+)\'\],\s*true\);\s*\$([a-zA-Z0-9_]+)\s*=\s*\$\1\[\$user\[\'agent\'\]\];/s';

$count = 0;
$content = preg_replace_callback($pattern, function($matches) use (&$count) {
    $count++;
    return "\${$matches[1]} = json_decode(\$marzban_list_get['{$matches[2]}'] ?? '{}', true) ?: [];\n" . 
           "        \${$matches[3]} = \${$matches[1]}[\$user['agent']] ?? 0;";
}, $content);

file_put_contents('index.php', $content);
echo "Replaced $count occurrences!\n";
?>
