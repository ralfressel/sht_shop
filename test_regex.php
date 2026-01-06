<?php
/**
 * Direct Regex Test
 */
$sql_dir = __DIR__ . '/sql-import/';
$sql = file_get_contents($sql_dir . 'product_option.sql');

echo "<pre>";
echo "Datei geladen: " . strlen($sql) . " Bytes\n\n";

// Test-String aus der Datei
$test = "(0x01894e5b0e117135849f3d25518b1b3b, 0x0fa91ce3e96a4bc2be4bd9ce752c3425, 0x01894e5b0e0d71768e3221e6915ddb80)";

echo "=== TEST MIT BEKANNTEM STRING ===\n";
echo "Test: $test\n\n";

// Pattern für 3 Spalten
$pattern = '/\(0x([a-f0-9]+), 0x[a-f0-9]+, 0x([a-f0-9]+)\)/i';
echo "Pattern: $pattern\n";

if (preg_match($pattern, $test, $m)) {
    echo "Match! Product: {$m[1]}, Option: {$m[2]}\n";
} else {
    echo "Kein Match!\n";
}

echo "\n=== TEST MIT DATEI ===\n";

// Verschiedene Patterns testen
$patterns = [
    'Mit Space' => '/\(0x([a-f0-9]+), 0x[a-f0-9]+, 0x([a-f0-9]+)\)/i',
    'Mit \\s*' => '/\(0x([a-f0-9]+),\s*0x[a-f0-9]+,\s*0x([a-f0-9]+)\)/i',
    'Mit \\s+' => '/\(0x([a-f0-9]+),\s+0x[a-f0-9]+,\s+0x([a-f0-9]+)\)/i',
    'Alle 3 capture' => '/\(0x([a-f0-9]+),\s*0x([a-f0-9]+),\s*0x([a-f0-9]+)\)/i',
];

foreach ($patterns as $name => $pattern) {
    preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER);
    echo "$name: " . count($matches) . " Treffer\n";
    
    if (count($matches) > 0) {
        echo "  Erstes Match: Product={$matches[0][1]}\n";
    }
}

// Zeige raw bytes um die erste Zeile
echo "\n=== RAW CHECK ===\n";
$pos = strpos($sql, '0x01894e5b0e117135849f3d25518b1b3b');
if ($pos !== false) {
    $context = substr($sql, $pos - 5, 150);
    echo "Context:\n";
    echo bin2hex(substr($context, 0, 50)) . "\n";
    echo htmlspecialchars($context) . "\n";
}

echo "</pre>";
?>
