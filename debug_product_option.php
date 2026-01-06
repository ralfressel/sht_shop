<?php
/**
 * Debug product_option.sql Format
 */
require_once 'opendb.inc.php';

$sql_dir = __DIR__ . '/sql-import/';

echo "<h1>Debug product_option.sql</h1><pre>";

$sql = file_get_contents($sql_dir . 'product_option.sql');

echo "Datei-Größe: " . strlen($sql) . " Bytes\n\n";

// Zeige erste 2000 Zeichen
echo "=== ERSTE 2000 ZEICHEN ===\n";
echo htmlspecialchars(substr($sql, 0, 2000));
echo "\n\n";

// Suche nach INSERT
echo "=== SUCHE INSERT ===\n";
if (preg_match('/INSERT INTO[^(]+\(([^)]+)\)/i', $sql, $m)) {
    echo "Spalten: " . $m[1] . "\n";
}

// Zeige erste VALUES
echo "\n=== ERSTE VALUES ===\n";
if (preg_match('/VALUES\s*(\([^)]+\))/is', $sql, $m)) {
    echo "Erste Row: " . $m[1] . "\n";
}

// Test verschiedene Regex-Patterns
echo "\n=== REGEX TESTS ===\n";

// Pattern 1: Zwei hex values
preg_match_all("/\(0x([a-f0-9]+),\s*0x([a-f0-9]+)\)/i", $sql, $matches);
echo "Pattern (0xHEX, 0xHEX): " . count($matches[0]) . " Treffer\n";

// Pattern 2: Mit Leerzeichen/Newlines
preg_match_all("/\(\s*0x([a-f0-9]+)\s*,\s*0x([a-f0-9]+)\s*\)/i", $sql, $matches);
echo "Pattern mit Whitespace: " . count($matches[0]) . " Treffer\n";

// Pattern 3: Beliebige Anzahl Spalten, erste zwei sind hex
preg_match_all("/\(0x([a-f0-9]+),\s*0x([a-f0-9]+)[^)]*\)/i", $sql, $matches);
echo "Pattern mit mehr Spalten: " . count($matches[0]) . " Treffer\n";

echo "</pre>";
?>
