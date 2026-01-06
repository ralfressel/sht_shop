<?php
/**
 * Import Varianten-Text aus Shopware
 * 
 * Lädt die Options-Werte (z.B. "500kg") aus product_option und 
 * property_group_option_translation und speichert sie direkt am Produkt
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

require_once 'opendb.inc.php';

define('GERMAN_LANG_ID', '2fbb5fe2e29a4d70aa5854ce7ce3e20b');

$sql_dir = __DIR__ . '/sql-import/';

echo "<h1>Import Varianten-Text</h1>";
echo "<pre>";

// Spalte optionen_text hinzufügen falls nicht vorhanden
$result = $db->query("SHOW COLUMNS FROM produkte LIKE 'optionen_text'");
if ($result->num_rows == 0) {
    $db->query("ALTER TABLE produkte ADD COLUMN optionen_text VARCHAR(500) DEFAULT NULL AFTER name");
    echo "✓ Spalte 'optionen_text' hinzugefügt\n\n";
} else {
    echo "✓ Spalte 'optionen_text' existiert bereits\n\n";
}

// Prüfe ob Dateien existieren
$files = ['product_option.sql', 'property_group_option_translation.sql'];
foreach ($files as $f) {
    if (!file_exists($sql_dir . $f)) {
        die("❌ Datei fehlt: $f\n");
    }
}

// Hilfsfunktionen
function parseInsertValues($sql) {
    $rows = [];
    if (preg_match_all('/\(([^;]+?)\)(?:,|\s*;)/s', $sql, $matches)) {
        foreach ($matches[1] as $rowData) {
            $values = [];
            $current = '';
            $inString = false;
            $stringChar = '';
            $depth = 0;
            
            for ($i = 0; $i < strlen($rowData); $i++) {
                $char = $rowData[$i];
                $prevChar = $i > 0 ? $rowData[$i-1] : '';
                
                if (!$inString && ($char == "'" || $char == '"')) {
                    $inString = true;
                    $stringChar = $char;
                    $current .= $char;
                } elseif ($inString && $char == $stringChar && $prevChar != '\\') {
                    $inString = false;
                    $current .= $char;
                } elseif (!$inString && $char == '(') {
                    $depth++;
                    $current .= $char;
                } elseif (!$inString && $char == ')') {
                    $depth--;
                    $current .= $char;
                } elseif (!$inString && $char == ',' && $depth == 0) {
                    $values[] = trim($current);
                    $current = '';
                } else {
                    $current .= $char;
                }
            }
            if (trim($current) !== '') {
                $values[] = trim($current);
            }
            if (!empty($values)) {
                $rows[] = $values;
            }
        }
    }
    return $rows;
}

function hexToUuid($hex) {
    $hex = trim($hex);
    if (stripos($hex, '0x') === 0) {
        return strtolower(substr($hex, 2));
    }
    return strtolower($hex);
}

function extractString($val) {
    $val = trim($val);
    if ($val === 'NULL' || $val === '') return null;
    if ((substr($val, 0, 1) == "'" && substr($val, -1) == "'") ||
        (substr($val, 0, 1) == '"' && substr($val, -1) == '"')) {
        $val = substr($val, 1, -1);
    }
    $val = str_replace("\\'", "'", $val);
    $val = str_replace('\\"', '"', $val);
    $val = str_replace('\\\\', '\\', $val);
    return $val;
}

// SCHRITT 1: Lade deutsche Options-Namen
echo "=== SCHRITT 1: Options-Namen laden ===\n";

$opt_trans_sql = file_get_contents($sql_dir . 'property_group_option_translation.sql');
$opt_trans_rows = parseInsertValues($opt_trans_sql);

$option_names = []; // option_uuid => name

foreach ($opt_trans_rows as $row) {
    if (count($row) < 3) continue;
    $opt_uuid = hexToUuid($row[0]);
    $lang_uuid = hexToUuid($row[1]);
    $name = extractString($row[2]);
    
    if ($lang_uuid === GERMAN_LANG_ID && $name) {
        $option_names[$opt_uuid] = $name;
    }
}

echo "Geladen: " . count($option_names) . " deutsche Options-Namen\n\n";

// SCHRITT 2: Lade product_option Verknüpfungen
echo "=== SCHRITT 2: Product-Option Verknüpfungen laden ===\n";

$prod_opts_sql = file_get_contents($sql_dir . 'product_option.sql');
$prod_opt_rows = parseInsertValues($prod_opts_sql);

$product_options = []; // product_uuid => [option_uuid, ...]

foreach ($prod_opt_rows as $row) {
    if (count($row) < 2) continue;
    $product_uuid = hexToUuid($row[0]);
    $option_uuid = hexToUuid($row[1]);
    
    if (!isset($product_options[$product_uuid])) {
        $product_options[$product_uuid] = [];
    }
    $product_options[$product_uuid][] = $option_uuid;
}

echo "Geladen: " . count($product_options) . " Produkt-Option-Verknüpfungen\n\n";

// SCHRITT 3: Lade Produkt-Mapping
echo "=== SCHRITT 3: Produkt-Mapping laden ===\n";

$result = $db->query("SELECT alte_uuid, neue_id FROM uuid_mapping WHERE tabelle = 'produkte'");
$produkt_mapping = [];
while ($row = $result->fetch_assoc()) {
    $produkt_mapping[$row['alte_uuid']] = $row['neue_id'];
}

echo "Geladen: " . count($produkt_mapping) . " Produkt-Mappings\n\n";

// SCHRITT 4: Update Produkte mit optionen_text
echo "=== SCHRITT 4: Produkte aktualisieren ===\n";

$updated = 0;
$skipped = 0;

foreach ($product_options as $product_uuid => $option_uuids) {
    // Finde Produkt-ID
    if (!isset($produkt_mapping[$product_uuid])) {
        $skipped++;
        continue;
    }
    
    $produkt_id = $produkt_mapping[$product_uuid];
    
    // Sammle Options-Namen
    $opt_texts = [];
    foreach ($option_uuids as $opt_uuid) {
        if (isset($option_names[$opt_uuid])) {
            $opt_texts[] = $option_names[$opt_uuid];
        }
    }
    
    if (!empty($opt_texts)) {
        $optionen_text = $db->real_escape_string(implode(' / ', $opt_texts));
        $db->query("UPDATE produkte SET optionen_text = '$optionen_text' WHERE id = $produkt_id");
        $updated++;
    }
}

echo "✓ $updated Produkte aktualisiert\n";
echo "⚠ $skipped übersprungen (Produkt nicht gefunden)\n\n";

// Zeige Beispiele
echo "=== BEISPIELE ===\n";
$examples = db_fetch_all("SELECT id, artikelnummer, name, optionen_text, preis 
    FROM produkte 
    WHERE optionen_text IS NOT NULL AND optionen_text != ''
    ORDER BY name
    LIMIT 20");

foreach ($examples as $ex) {
    echo "#{$ex['id']} {$ex['artikelnummer']}: {$ex['optionen_text']} ({$ex['preis']} €)\n";
}

echo "\n=== BLECHGREIFER VARIANTEN ===\n";
$blech = db_fetch_all("SELECT id, parent_id, artikelnummer, optionen_text, preis 
    FROM produkte 
    WHERE name LIKE '%Blechgreifer%Blechklemme%'
    ORDER BY parent_id, preis");

foreach ($blech as $b) {
    $p = $b['parent_id'] ? "  -> Parent {$b['parent_id']}" : "MAIN";
    echo "#{$b['id']} $p | {$b['artikelnummer']} | {$b['optionen_text']} | {$b['preis']} €\n";
}

echo "</pre>";
?>
