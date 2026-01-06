<?php
/**
 * Debug product_translation Format
 */
require_once 'opendb.inc.php';

$sql_dir = __DIR__ . '/sql-import/';

echo "<h1>Debug product_translation.sql</h1><pre>";

$sql = file_get_contents($sql_dir . 'product_translation.sql');
echo "Datei: " . number_format(strlen($sql)) . " Bytes\n\n";

// Zeige erste 3000 Zeichen
echo "=== ERSTE 3000 ZEICHEN ===\n";
echo htmlspecialchars(substr($sql, 0, 3000));

echo "\n\n=== SUCHE SPALTENREIHENFOLGE ===\n";
if (preg_match('/INSERT INTO[^(]+\(([^)]+)\)/i', $sql, $m)) {
    echo "Spalten: " . $m[1] . "\n";
}

// Suche nach der deutschen Sprach-ID in der Datei
echo "\n=== SUCHE DEUTSCHE SPRACH-ID ===\n";
$german_id = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
$pos = stripos($sql, $german_id);
if ($pos !== false) {
    echo "Gefunden an Position: $pos\n";
    echo "Context:\n";
    echo htmlspecialchars(substr($sql, max(0, $pos - 50), 200));
} else {
    echo "NICHT GEFUNDEN als String!\n";
    
    // Suche als 0x Format
    $pos = stripos($sql, '0x' . $german_id);
    if ($pos !== false) {
        echo "Gefunden als 0x Format an Position: $pos\n";
    }
}

// Zeige erste Values-Zeile komplett
echo "\n\n=== ERSTE VALUES ZEILE ===\n";
if (preg_match('/VALUES\s*\(([^)]+)\)/is', $sql, $m)) {
    echo htmlspecialchars($m[1]);
}

// Zähle unique language_ids
echo "\n\n=== SPRACH-IDs IN DATEI ===\n";
preg_match_all('/0x([a-f0-9]{32})/i', substr($sql, 0, 50000), $ids);
$unique = array_unique($ids[1]);
foreach ($unique as $id) {
    $count = substr_count(strtolower($sql), strtolower($id));
    echo strtolower($id) . " : $count mal\n";
}

echo "</pre>";
?>
