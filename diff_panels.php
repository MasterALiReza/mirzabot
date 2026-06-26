<?php
$file1 = 'temp_mirzabot/panels.php';
$file2 = 'panels.php';

if (!file_exists($file1) || !file_exists($file2)) {
    die("One of the files does not exist.\n");
}

$lines1 = file($file1, FILE_IGNORE_NEW_LINES);
$lines2 = file($file2, FILE_IGNORE_NEW_LINES);

$diff = [];
$len1 = count($lines1);
$len2 = count($lines2);
$max = max($len1, $len2);

for ($i = 0; $i < $max; $i++) {
    $l1 = isset($lines1[$i]) ? $lines1[$i] : '[EOF]';
    $l2 = isset($lines2[$i]) ? $lines2[$i] : '[EOF]';
    if ($l1 !== $l2) {
        $diff[] = "Line " . ($i + 1) . ":\n- " . $l1 . "\n+ " . $l2 . "\n";
    }
}

file_put_contents('diff_panels.txt', implode("\n", $diff));
echo "Diff complete, written to diff_panels.txt. Total diff size: " . count($diff) . " blocks.\n";
?>
