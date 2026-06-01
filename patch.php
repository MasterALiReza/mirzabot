<?php

function patch_file($filename, $replacements) {
    $content = file_get_contents($filename);
    $orig = $content;
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    if ($content !== $orig) {
        file_put_contents($filename, $content);
        echo "Patched $filename\n";
    } else {
        echo "No changes made to $filename\n";
    }
}

// 1. Patch panels.php
patch_file('panels.php', [
    "require_once __DIR__ . '/x-ui_single.php';" => "require_once __DIR__ . '/x-ui_single.php';\nrequire_once __DIR__ . '/MHSanaei-3.2.php';",
    
    'elseif ($Get_Data_Panel[\'type\'] == "x-ui_single") {' => 'elseif ($Get_Data_Panel[\'type\'] == "MHSanaei-3.2") {
            return MHSanaei_router(__FUNCTION__, func_get_args());
        } elseif ($Get_Data_Panel[\'type\'] == "x-ui_single") {',
        
    'elseif ($panel[\'type\'] == "x-ui_single") {' => 'elseif ($panel[\'type\'] == "MHSanaei-3.2") {
            return MHSanaei_router(__FUNCTION__, func_get_args());
        } elseif ($panel[\'type\'] == "x-ui_single") {',
        
    'elseif ($panel[\'type\'] == \'x-ui_single\') {' => 'elseif ($panel[\'type\'] == "MHSanaei-3.2") {
            return MHSanaei_router(__FUNCTION__, func_get_args());
        } elseif ($panel[\'type\'] == \'x-ui_single\') {',
]);

// 2. Patch admin.php
patch_file('admin.php', [
    '$userdata[\'type\'] == "x-ui_single"' => '($userdata[\'type\'] == "x-ui_single" || $userdata[\'type\'] == "MHSanaei-3.2")',
    '$marzban_list_get[\'type\'] == "x-ui_single"' => '($marzban_list_get[\'type\'] == "x-ui_single" || $marzban_list_get[\'type\'] == "MHSanaei-3.2")',
    '$typepanel[\'type\'] == "x-ui_single"' => '($typepanel[\'type\'] == "x-ui_single" || $typepanel[\'type\'] == "MHSanaei-3.2")',
    '$typepanel == "x-ui_single"' => '($typepanel == "x-ui_single" || $typepanel == "MHSanaei-3.2")',
    '[\'marzban\', "x-ui_single", "marzneshin"]' => '[\'marzban\', "x-ui_single", "MHSanaei-3.2", "marzneshin"]',
]);

// 3. Patch keyboard.php
patch_file('keyboard.php', [
    "['text' => \$textbotlang['extracted']['keyboard_php']['panelTypeSanaei'], 'callback_data' => 'typepanel#x-ui_single']," => "['text' => \$textbotlang['extracted']['keyboard_php']['panelTypeSanaei'], 'callback_data' => 'typepanel#x-ui_single'],\n            ['text' => 'MHSanaei 3x-ui', 'callback_data' => 'typepanel#MHSanaei-3.2'],"
]);

// 4. Patch function.php
patch_file('function.php', [
    '$typepanel == "x-ui_single"' => '($typepanel == "x-ui_single" || $typepanel == "MHSanaei-3.2")'
]);

echo "Done\n";
