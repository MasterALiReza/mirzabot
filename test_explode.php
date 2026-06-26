<?php
try {
    $a = null;
    $parts = explode("_", $a);
    echo "OK\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
