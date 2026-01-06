<?php
/**
 * Reimport Beschreibungen MIT HTML-Formatierung
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

require_once 'opendb.inc.php';

define('GERMAN_LANG_ID', '2fbb5fe2e29a4d70aa5854ce7ce3e20b');

$sql_dir = __DIR__ . '/sql-import/';

echo "<h1>Reimport Beschreibungen mit HTML</h1>";
echo "<pre>";

// SCHRITT 1: Lade deutsche Produkt-Übersetzungen
echo "=== SCHRITT 1: Lade product_translation.sql ===\n";
$trans_sql = file_get_contents($sql_dir . 'product_translation.sql');
echo "Datei geladen: " . number_format(strlen($trans_sql)) . " Bytes\n";

// Parse deutsche Beschreibungen
// Format: (product_id, language_id, product_version_id, name, description, ...)
$prod_descriptions = [];

// Regex: Suche alle INSERT VALUES
// Die Spaltenreihenfolge laut Shopware: product_id, language_id, product_version_id, name, keywords, description, ...
preg_match_all("/\(0x([a-f0-9]+),\s*0x([a-f0-9]+),\s*0x[a-f0-9]+,\s*'([^']*)',\s*(?:'[^']*'|NULL),\s*'((?:[^'\\\\]|\\\\.)*)'/is", $trans_sql, $matches, PREG_SET_ORDER);

echo "Regex-Treffer: " . count($matches) . "\n";

foreach ($matches as $m) {
    $product_uuid = strtolower($m[1]);
    $lang_uuid = strtolower($m[2]);
    $description = $m[4];
    
    if ($lang_uuid === GERMAN_LANG_ID && !empty($description) && $description !== 'NULL') {
        // Unescape
        $description = str_replace("\\'", "'", $description);
        $description = str_replace('\\"', '"', $description);
        $description = str_replace('\\n', "\n", $description);
        $description = str_replace('\\r', "\r", $description);
        $description = str_replace('\\\\', '\\', $description);
        
        $prod_descriptions[$product_uuid] = $description;
    }
}

echo "Deutsche Beschreibungen gefunden: " . count($prod_descriptions) . "\n\n";

// Zeige Beispiel
$i = 0;
foreach ($prod_descriptions as $uuid => $desc) {
    if ($i++ >= 2) break;
    echo "UUID: $uuid\n";
    echo "Beschreibung (erste 300 Zeichen):\n";
    echo htmlspecialchars(substr($desc, 0, 300)) . "...\n\n";
}

// SCHRITT 2: Lade UUID-Mapping
echo "=== SCHRITT 2: Lade Produkt-Mapping ===\n";
$result = $db->query("SELECT alte_uuid, neue_id FROM uuid_mapping WHERE tabelle = 'produkte'");
$produkt_mapping = [];
while ($row = $result->fetch_assoc()) {
    $produkt_mapping[$row['alte_uuid']] = $row['neue_id'];
}
echo "Produkt-Mappings: " . count($produkt_mapping) . "\n\n";

// SCHRITT 3: Update Beschreibungen
echo "=== SCHRITT 3: Update Beschreibungen ===\n";
$updated = 0;
$skipped = 0;

foreach ($prod_descriptions as $uuid => $beschreibung) {
    if (!isset($produkt_mapping[$uuid])) {
        $skipped++;
        continue;
    }
    
    $produkt_id = $produkt_mapping[$uuid];
    $beschreibung_escaped = $db->real_escape_string($beschreibung);
    
    $db->query("UPDATE produkte SET beschreibung = '$beschreibung_escaped' WHERE id = $produkt_id");
    if ($db->affected_rows > 0) {
        $updated++;
    }
}

echo "✓ $updated Beschreibungen aktualisiert\n";
echo "⚠ $skipped übersprungen (Produkt nicht gefunden)\n\n";

// SCHRITT 4: Prüfe Ergebnis
echo "=== SCHRITT 4: Prüfe Blechgreifer ===\n";
$produkt = db_fetch_row("SELECT beschreibung FROM produkte WHERE id = 12878");
echo "Enthält HTML: " . (preg_match('/<[^>]+>/', $produkt['beschreibung']) ? 'JA' : 'NEIN') . "\n";
echo "Erste 500 Zeichen:\n";
echo htmlspecialchars(substr($produkt['beschreibung'], 0, 500));

echo "</pre>";
?>
