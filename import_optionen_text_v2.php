<?php
/**
 * Import Varianten-Text - Version 2
 * Einfachere Methode mit direktem String-Matching
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

require_once 'opendb.inc.php';

define('GERMAN_LANG_ID', '2fbb5fe2e29a4d70aa5854ce7ce3e20b');

$sql_dir = __DIR__ . '/sql-import/';

echo "<h1>Import Varianten-Text v2</h1>";
echo "<pre>";

// Spalte hinzufügen falls nicht vorhanden
$result = $db->query("SHOW COLUMNS FROM produkte LIKE 'optionen_text'");
if ($result->num_rows == 0) {
    $db->query("ALTER TABLE produkte ADD COLUMN optionen_text VARCHAR(500) DEFAULT NULL AFTER name");
    echo "✓ Spalte 'optionen_text' hinzugefügt\n\n";
}

// SCHRITT 1: Lade alle Produkt-UUIDs aus uuid_mapping
echo "=== SCHRITT 1: Produkt-Mappings laden ===\n";
$result = $db->query("SELECT alte_uuid, neue_id FROM uuid_mapping WHERE tabelle = 'produkte'");
$produkt_mapping = [];
while ($row = $result->fetch_assoc()) {
    $produkt_mapping[$row['alte_uuid']] = $row['neue_id'];
}
echo "Geladen: " . count($produkt_mapping) . " Produkte\n\n";

// SCHRITT 2: Lade deutsche Options-Namen
echo "=== SCHRITT 2: Options-Namen laden ===\n";
$trans_sql = file_get_contents($sql_dir . 'property_group_option_translation.sql');

$option_names = [];
// Regex für: (0xOPTION_UUID, 0xLANG_UUID, 'NAME', ...)
preg_match_all("/\(0x([a-f0-9]{32}),\s*0x([a-f0-9]{32}),\s*'([^']*?)'/i", $trans_sql, $matches, PREG_SET_ORDER);

foreach ($matches as $m) {
    $option_uuid = strtolower($m[1]);
    $lang_uuid = strtolower($m[2]);
    $name = stripslashes($m[3]);
    
    if ($lang_uuid === GERMAN_LANG_ID) {
        $option_names[$option_uuid] = $name;
    }
}
echo "Geladen: " . count($option_names) . " deutsche Options-Namen\n";

// Zeige Beispiele
$i = 0;
foreach ($option_names as $uuid => $name) {
    if ($i++ >= 5) break;
    echo "  $uuid => $name\n";
}
echo "\n";

// SCHRITT 3: Lade product_option Verknüpfungen
echo "=== SCHRITT 3: Product-Option Verknüpfungen laden ===\n";
$prod_opt_sql = file_get_contents($sql_dir . 'product_option.sql');

$product_options = [];
// Regex für: (0xPRODUCT_UUID, 0xOPTION_UUID)
preg_match_all("/\(0x([a-f0-9]{32}),\s*0x([a-f0-9]{32})\)/i", $prod_opt_sql, $matches, PREG_SET_ORDER);

foreach ($matches as $m) {
    $product_uuid = strtolower($m[1]);
    $option_uuid = strtolower($m[2]);
    
    if (!isset($product_options[$product_uuid])) {
        $product_options[$product_uuid] = [];
    }
    $product_options[$product_uuid][] = $option_uuid;
}
echo "Geladen: " . count($product_options) . " Produkte mit Options\n";

// Zeige Beispiel
$i = 0;
foreach ($product_options as $puuid => $opts) {
    if ($i++ >= 3) break;
    echo "  Produkt $puuid hat " . count($opts) . " Options\n";
}
echo "\n";

// SCHRITT 4: Aktualisiere Produkte
echo "=== SCHRITT 4: Produkte aktualisieren ===\n";

$updated = 0;
$skipped = 0;
$no_options = 0;

foreach ($produkt_mapping as $uuid => $produkt_id) {
    // Hat dieses Produkt Options?
    if (!isset($product_options[$uuid])) {
        $no_options++;
        continue;
    }
    
    // Sammle Options-Namen
    $opt_texts = [];
    foreach ($product_options[$uuid] as $opt_uuid) {
        if (isset($option_names[$opt_uuid])) {
            $opt_texts[] = $option_names[$opt_uuid];
        }
    }
    
    if (!empty($opt_texts)) {
        $optionen_text = $db->real_escape_string(implode(' / ', $opt_texts));
        $db->query("UPDATE produkte SET optionen_text = '$optionen_text' WHERE id = $produkt_id");
        if ($db->affected_rows > 0) {
            $updated++;
        }
    } else {
        $skipped++;
    }
}

echo "✓ $updated Produkte aktualisiert\n";
echo "⚠ $no_options Produkte haben keine Options\n";
echo "⚠ $skipped Options-Namen nicht gefunden\n\n";

// SCHRITT 5: Zeige Blechgreifer Varianten
echo "=== BLECHGREIFER VARIANTEN ===\n";
$blech = db_fetch_all("SELECT id, parent_id, artikelnummer, optionen_text, preis 
    FROM produkte 
    WHERE name LIKE '%Blechgreifer%Blechklemme%'
    ORDER BY parent_id, preis");

foreach ($blech as $b) {
    $p = $b['parent_id'] ? "  Variante" : "HAUPT";
    $opt = $b['optionen_text'] ?: '(kein Text)';
    echo "#{$b['id']} $p | Art: {$b['artikelnummer']} | $opt | {$b['preis']} €\n";
}

echo "\n=== WEITERE BEISPIELE ===\n";
$examples = db_fetch_all("SELECT id, artikelnummer, optionen_text, preis 
    FROM produkte 
    WHERE optionen_text IS NOT NULL AND optionen_text != ''
    LIMIT 15");

foreach ($examples as $ex) {
    echo "#{$ex['id']} {$ex['artikelnummer']}: {$ex['optionen_text']} ({$ex['preis']} €)\n";
}

echo "</pre>";
?>
