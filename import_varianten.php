<?php
/**
 * SHT Shop - Varianten Import aus Shopware 6
 * 
 * Importiert:
 * - property_group -> varianten_gruppen (z.B. "Tragfähigkeit")
 * - property_group_option -> varianten_werte (z.B. "500kg")
 * - product_option -> produkt_varianten (Verknüpfung Produkt<->Wert)
 * 
 * Benötigte SQL-Dateien in sql-import/:
 * - property_group.sql
 * - property_group_translation.sql
 * - property_group_option.sql
 * - property_group_option_translation.sql
 * - product_option.sql
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

require_once 'opendb.inc.php';

// Deutsche Sprach-ID (aus Shopware)
define('GERMAN_LANG_ID', '2fbb5fe2e29a4d70aa5854ce7ce3e20b');

$sql_dir = __DIR__ . '/sql-import/';

echo "<h1>Varianten-Import aus Shopware 6</h1>";
echo "<pre style='background:#f5f5f5; padding:1rem; max-height:80vh; overflow:auto;'>";

// =====================================================
// Hilfsfunktionen
// =====================================================

/**
 * Parst INSERT-Werte aus SQL
 */
function parseInsertValues($sql) {
    $rows = [];
    
    // Finde alle VALUES-Blöcke
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

/**
 * Konvertiert Shopware Hex-UUID zu 32-Zeichen String
 */
function hexToUuid($hex) {
    $hex = trim($hex);
    if (stripos($hex, '0x') === 0) {
        return strtolower(substr($hex, 2));
    }
    return strtolower($hex);
}

/**
 * Extrahiert String-Wert aus SQL
 */
function extractString($val) {
    $val = trim($val);
    if ($val === 'NULL' || $val === '') return null;
    
    // Entferne Anführungszeichen
    if ((substr($val, 0, 1) == "'" && substr($val, -1) == "'") ||
        (substr($val, 0, 1) == '"' && substr($val, -1) == '"')) {
        $val = substr($val, 1, -1);
    }
    
    // Entferne Escape-Zeichen
    $val = str_replace("\\'", "'", $val);
    $val = str_replace('\\"', '"', $val);
    $val = str_replace('\\\\', '\\', $val);
    
    return $val;
}

// =====================================================
// Prüfe ob Dateien existieren
// =====================================================

$required_files = [
    'property_group.sql',
    'property_group_translation.sql', 
    'property_group_option.sql',
    'property_group_option_translation.sql',
    'product_option.sql'
];

$missing = [];
foreach ($required_files as $file) {
    if (!file_exists($sql_dir . $file)) {
        $missing[] = $file;
    }
}

if (!empty($missing)) {
    echo "❌ Fehlende SQL-Dateien:\n";
    foreach ($missing as $m) {
        echo "   - $m\n";
    }
    echo "\n";
    echo "Bitte exportiere diese Tabellen aus phpMyAdmin:\n";
    echo "1. Öffne phpMyAdmin für die Shopware-Datenbank\n";
    echo "2. Gehe zu 'Exportieren' für jede Tabelle\n";
    echo "3. Wähle 'SQL' Format\n";
    echo "4. Speichere die Dateien in: sql-import/\n";
    echo "</pre>";
    exit;
}

echo "✓ Alle benötigten SQL-Dateien gefunden\n\n";

// =====================================================
// Tabellen vorbereiten
// =====================================================

echo "Erstelle Tabellen falls nicht vorhanden...\n";

$db->query("CREATE TABLE IF NOT EXISTS varianten_gruppen (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    sortierung INT DEFAULT 0,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->query("CREATE TABLE IF NOT EXISTS varianten_werte (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gruppe_id INT UNSIGNED NOT NULL,
    wert VARCHAR(255) NOT NULL,
    sortierung INT DEFAULT 0,
    INDEX idx_gruppe (gruppe_id),
    INDEX idx_wert (wert)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->query("CREATE TABLE IF NOT EXISTS produkt_varianten (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produkt_id INT UNSIGNED NOT NULL,
    wert_id INT UNSIGNED NOT NULL,
    INDEX idx_produkt (produkt_id),
    INDEX idx_wert (wert_id),
    UNIQUE KEY uk_produkt_wert (produkt_id, wert_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Leere die Tabellen
$db->query("TRUNCATE TABLE produkt_varianten");
$db->query("TRUNCATE TABLE varianten_werte");
$db->query("TRUNCATE TABLE varianten_gruppen");

echo "✓ Tabellen vorbereitet\n\n";

// =====================================================
// SCHRITT 1: Property Groups (Varianten-Gruppen)
// =====================================================

echo "=== SCHRITT 1: Varianten-Gruppen ===\n";

// Lade deutsche Übersetzungen
$translations_sql = file_get_contents($sql_dir . 'property_group_translation.sql');
$trans_rows = parseInsertValues($translations_sql);

$group_names = []; // property_group_id => name

foreach ($trans_rows as $row) {
    if (count($row) < 3) continue;
    
    // property_group_id, language_id, name, ...
    $group_uuid = hexToUuid($row[0]);
    $lang_uuid = hexToUuid($row[1]);
    $name = extractString($row[2]);
    
    // Nur deutsche Übersetzungen
    if ($lang_uuid === GERMAN_LANG_ID && $name) {
        $group_names[$group_uuid] = $name;
    }
}

echo "Gefunden: " . count($group_names) . " deutsche Gruppen-Namen\n";

// Lade Gruppen
$groups_sql = file_get_contents($sql_dir . 'property_group.sql');
$group_rows = parseInsertValues($groups_sql);

$group_mapping = []; // uuid => neue_id
$groups_imported = 0;

foreach ($group_rows as $row) {
    if (count($row) < 1) continue;
    
    $uuid = hexToUuid($row[0]);
    $name = isset($group_names[$uuid]) ? $group_names[$uuid] : 'Unbekannt';
    $sortierung = isset($row[4]) ? (int)$row[4] : 0;
    
    $name = $db->real_escape_string($name);
    
    $db->query("INSERT INTO varianten_gruppen (name, sortierung) VALUES ('$name', $sortierung)");
    $group_mapping[$uuid] = $db->insert_id;
    $groups_imported++;
}

echo "✓ $groups_imported Varianten-Gruppen importiert\n\n";

// =====================================================
// SCHRITT 2: Property Group Options (Varianten-Werte)
// =====================================================

echo "=== SCHRITT 2: Varianten-Werte ===\n";

// Lade deutsche Übersetzungen
$opt_trans_sql = file_get_contents($sql_dir . 'property_group_option_translation.sql');
$opt_trans_rows = parseInsertValues($opt_trans_sql);

$option_names = []; // option_id => name

foreach ($opt_trans_rows as $row) {
    if (count($row) < 3) continue;
    
    $opt_uuid = hexToUuid($row[0]);
    $lang_uuid = hexToUuid($row[1]);
    $name = extractString($row[2]);
    
    if ($lang_uuid === GERMAN_LANG_ID && $name) {
        $option_names[$opt_uuid] = $name;
    }
}

echo "Gefunden: " . count($option_names) . " deutsche Options-Namen\n";

// Lade Options
$opts_sql = file_get_contents($sql_dir . 'property_group_option.sql');
$opt_rows = parseInsertValues($opts_sql);

$option_mapping = []; // uuid => neue_id
$options_imported = 0;

foreach ($opt_rows as $row) {
    if (count($row) < 2) continue;
    
    $uuid = hexToUuid($row[0]);
    $group_uuid = hexToUuid($row[1]);
    
    // Prüfe ob Gruppe existiert
    if (!isset($group_mapping[$group_uuid])) {
        continue;
    }
    
    $gruppe_id = $group_mapping[$group_uuid];
    $wert = isset($option_names[$uuid]) ? $option_names[$uuid] : 'Unbekannt';
    $sortierung = isset($row[3]) ? (int)$row[3] : 0;
    
    $wert = $db->real_escape_string($wert);
    
    $db->query("INSERT INTO varianten_werte (gruppe_id, wert, sortierung) VALUES ($gruppe_id, '$wert', $sortierung)");
    $option_mapping[$uuid] = $db->insert_id;
    $options_imported++;
}

echo "✓ $options_imported Varianten-Werte importiert\n\n";

// =====================================================
// SCHRITT 3: Product Options (Verknüpfung)
// =====================================================

echo "=== SCHRITT 3: Produkt-Varianten-Verknüpfungen ===\n";

// Lade Produkt-Mapping
$produkt_mapping = [];
$result = $db->query("SELECT shopware_uuid, neue_id FROM uuid_mapping WHERE tabelle = 'produkte'");
while ($row = $result->fetch_assoc()) {
    $produkt_mapping[$row['shopware_uuid']] = $row['neue_id'];
}
echo "Geladen: " . count($produkt_mapping) . " Produkt-Mappings\n";

// Lade Product Options
$prod_opts_sql = file_get_contents($sql_dir . 'product_option.sql');
$prod_opt_rows = parseInsertValues($prod_opts_sql);

$links_imported = 0;
$links_skipped = 0;

foreach ($prod_opt_rows as $row) {
    if (count($row) < 2) continue;
    
    $product_uuid = hexToUuid($row[0]);
    $option_uuid = hexToUuid($row[1]);
    
    // Prüfe ob Produkt und Option existieren
    if (!isset($produkt_mapping[$product_uuid])) {
        $links_skipped++;
        continue;
    }
    if (!isset($option_mapping[$option_uuid])) {
        $links_skipped++;
        continue;
    }
    
    $produkt_id = $produkt_mapping[$product_uuid];
    $wert_id = $option_mapping[$option_uuid];
    
    // Vermeide Duplikate
    $exists = $db->query("SELECT id FROM produkt_varianten WHERE produkt_id = $produkt_id AND wert_id = $wert_id")->num_rows;
    if ($exists == 0) {
        $db->query("INSERT INTO produkt_varianten (produkt_id, wert_id) VALUES ($produkt_id, $wert_id)");
        $links_imported++;
    }
}

echo "✓ $links_imported Verknüpfungen importiert\n";
echo "⚠ $links_skipped Verknüpfungen übersprungen (Produkt/Option nicht gefunden)\n\n";

// =====================================================
// Zusammenfassung
// =====================================================

echo "===========================================\n";
echo "IMPORT ABGESCHLOSSEN\n";
echo "===========================================\n";
echo "Varianten-Gruppen: $groups_imported\n";
echo "Varianten-Werte:   $options_imported\n";
echo "Verknüpfungen:     $links_imported\n";
echo "\n";

// Zeige Beispiele
echo "=== Beispiel-Daten ===\n";
$example = $db->query("SELECT vg.name as gruppe, vw.wert, COUNT(pv.id) as produkte
    FROM varianten_gruppen vg
    JOIN varianten_werte vw ON vw.gruppe_id = vg.id
    LEFT JOIN produkt_varianten pv ON pv.wert_id = vw.id
    GROUP BY vg.id, vw.id
    ORDER BY vg.name, vw.sortierung
    LIMIT 20");

if ($example->num_rows > 0) {
    echo sprintf("%-20s %-20s %s\n", "Gruppe", "Wert", "Produkte");
    echo str_repeat("-", 50) . "\n";
    while ($row = $example->fetch_assoc()) {
        echo sprintf("%-20s %-20s %s\n", 
            substr($row['gruppe'], 0, 20), 
            substr($row['wert'], 0, 20), 
            $row['produkte']);
    }
}

echo "</pre>";
?>
