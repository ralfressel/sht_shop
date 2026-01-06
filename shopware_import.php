<?php
/**
 * Shopware 6 SQL Import Script
 * Importiert Daten aus den exportierten SQL-Dateien
 * 
 * WICHTIG: Dieses Script parst die SQL-Dateien direkt,
 * ohne sie in MySQL zu importieren (wegen IONOS 1GB Limit)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');
set_time_limit(0);

require_once 'opendb.inc.php';

// Deutsche Language-ID (aus Shopware 6)
define('GERMAN_LANGUAGE_ID', '2fbb5fe2e29a4d70aa5854ce7ce3e20b');

// Pfad zu SQL-Dateien
define('SQL_DIR', __DIR__ . '/sql-import/');

// Mapping alte UUIDs -> neue IDs
$uuid_kategorie = [];
$uuid_produkt = [];

echo "<html><head><meta charset='UTF-8'><title>Shopware Import</title></head><body>";
echo "<h1>Shopware 6 Import</h1>";
echo "<pre>";

// =====================================================
// VORBEREITUNG: Tabellen erstellen/aktualisieren
// =====================================================
echo "=== VORBEREITUNG ===\n";

// uuid_mapping Tabelle neu erstellen
db_query("DROP TABLE IF EXISTS uuid_mapping");
$sql = "CREATE TABLE uuid_mapping (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tabelle VARCHAR(50) NOT NULL,
    alte_uuid VARCHAR(64) NOT NULL,
    neue_id INT UNSIGNED NOT NULL,
    erstellt DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tabelle_uuid (tabelle, alte_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if (db_query($sql)) {
    echo "✓ uuid_mapping Tabelle erstellt\n";
} else {
    echo "✗ Fehler bei uuid_mapping\n";
}

// Prüfe ob parent_id in produkte existiert
$result = $db->query("SHOW COLUMNS FROM produkte LIKE 'parent_id'");
if ($result && $result->num_rows == 0) {
    echo "Füge parent_id zu produkte hinzu...\n";
    db_query("ALTER TABLE produkte ADD COLUMN parent_id INT UNSIGNED DEFAULT NULL AFTER id");
    db_query("ALTER TABLE produkte ADD INDEX idx_parent (parent_id)");
    echo "✓ parent_id hinzugefügt\n";
}

// Prüfe Spaltennamen artikelnummer vs artikelnr  
$result = $db->query("SHOW COLUMNS FROM produkte LIKE 'artikelnummer'");
if ($result && $result->num_rows == 0) {
    $result2 = $db->query("SHOW COLUMNS FROM produkte LIKE 'artikelnr'");
    if ($result2 && $result2->num_rows > 0) {
        db_query("ALTER TABLE produkte CHANGE artikelnr artikelnummer VARCHAR(100) NOT NULL DEFAULT ''");
        echo "✓ artikelnr zu artikelnummer umbenannt\n";
    }
}

echo "\n";

// =====================================================
// HILFSFUNKTIONEN
// =====================================================

/**
 * Konvertiert Hex-String (0x...) zu normalem String
 */
function hexToString($hex) {
    if (strpos($hex, '0x') === 0) {
        $hex = substr($hex, 2);
    }
    return strtolower($hex);
}

/**
 * Extrahiert INSERT-Werte aus SQL
 * Gibt Array von Arrays zurück
 */
function parseInsertValues($sql_content, $table_name) {
    $results = [];
    
    // Finde alle INSERT INTO Statements für diese Tabelle
    $pattern = '/INSERT INTO `' . preg_quote($table_name) . '`\s*\([^)]+\)\s*VALUES\s*/i';
    
    if (!preg_match($pattern, $sql_content, $match, PREG_OFFSET_CAPTURE)) {
        return $results;
    }
    
    // Spalten extrahieren
    preg_match('/INSERT INTO `' . preg_quote($table_name) . '`\s*\(([^)]+)\)/i', $sql_content, $cols_match);
    if (!isset($cols_match[1])) return $results;
    
    $columns = array_map(function($c) {
        return trim(str_replace('`', '', $c));
    }, explode(',', $cols_match[1]));
    
    // Position nach VALUES
    $start = $match[0][1] + strlen($match[0][0]);
    $content = substr($sql_content, $start);
    
    // Parse Zeilen - jede Zeile beginnt mit ( und endet mit ),
    $in_string = false;
    $escape_next = false;
    $string_char = '';
    $current_value = '';
    $values = [];
    $paren_depth = 0;
    $row_count = 0;
    
    for ($i = 0; $i < strlen($content) && $row_count < 50000; $i++) {
        $char = $content[$i];
        
        if ($escape_next) {
            $current_value .= $char;
            $escape_next = false;
            continue;
        }
        
        if ($char === '\\') {
            $current_value .= $char;
            $escape_next = true;
            continue;
        }
        
        if ($in_string) {
            if ($char === $string_char) {
                $in_string = false;
            }
            $current_value .= $char;
            continue;
        }
        
        if ($char === "'" || $char === '"') {
            $in_string = true;
            $string_char = $char;
            $current_value .= $char;
            continue;
        }
        
        if ($char === '(') {
            $paren_depth++;
            if ($paren_depth === 1) {
                $current_value = '';
                $values = [];
            } else {
                $current_value .= $char;
            }
            continue;
        }
        
        if ($char === ')') {
            $paren_depth--;
            if ($paren_depth === 0) {
                // Ende einer Zeile
                $values[] = trim($current_value);
                
                // Kombiniere Spalten und Werte
                if (count($values) === count($columns)) {
                    $row = [];
                    foreach ($columns as $idx => $col) {
                        $row[$col] = $values[$idx];
                    }
                    $results[] = $row;
                    $row_count++;
                }
                $values = [];
                $current_value = '';
            } else {
                $current_value .= $char;
            }
            continue;
        }
        
        if ($char === ',' && $paren_depth === 1) {
            $values[] = trim($current_value);
            $current_value = '';
            continue;
        }
        
        if ($paren_depth >= 1) {
            $current_value .= $char;
        }
        
        // Ende bei Semikolon außerhalb von Klammern
        if ($char === ';' && $paren_depth === 0) {
            // Prüfe ob nächstes INSERT kommt
            $remaining = substr($content, $i + 1, 200);
            if (preg_match('/^\s*INSERT INTO/i', $remaining)) {
                // Nächstes INSERT - weiter parsen
                $i += strpos($remaining, 'VALUES') + 6;
            } else {
                break;
            }
        }
    }
    
    return $results;
}

/**
 * Bereinigt SQL-String-Wert
 */
function cleanSqlValue($val) {
    if ($val === 'NULL' || $val === 'null') {
        return null;
    }
    // Entferne umschließende Anführungszeichen
    if ((substr($val, 0, 1) === "'" && substr($val, -1) === "'") ||
        (substr($val, 0, 1) === '"' && substr($val, -1) === '"')) {
        $val = substr($val, 1, -1);
    }
    // Unescape
    $val = str_replace("\\'", "'", $val);
    $val = str_replace('\\"', '"', $val);
    $val = str_replace('\\n', "\n", $val);
    $val = str_replace('\\r', "\r", $val);
    $val = str_replace('\\\\', '\\', $val);
    return $val;
}

/**
 * Extrahiert Preis aus JSON
 */
function extractPrice($price_json) {
    if (empty($price_json) || $price_json === 'NULL') {
        return 0;
    }
    
    $price_json = cleanSqlValue($price_json);
    
    // Versuche JSON zu parsen
    $data = json_decode($price_json, true);
    if ($data && is_array($data)) {
        // Suche nach gross Preis
        foreach ($data as $currency_id => $price_data) {
            if (isset($price_data['gross'])) {
                return floatval($price_data['gross']);
            }
        }
    }
    
    // Fallback: Regex für gross-Wert
    if (preg_match('/"gross"\s*:\s*([\d.]+)/', $price_json, $m)) {
        return floatval($m[1]);
    }
    
    return 0;
}

// =====================================================
// SCHRITT 1: Kategorien importieren
// =====================================================
echo "\n=== SCHRITT 1: Kategorien ===\n";

// Alte Kategorien löschen
db_query("DELETE FROM kategorien WHERE id > 0");

// Lade category.sql
$cat_sql = file_get_contents(SQL_DIR . 'category.sql');
$categories = parseInsertValues($cat_sql, 'category');
echo "Gefundene Kategorien: " . count($categories) . "\n";

// Lade category_translation.sql
$cat_trans_sql = file_get_contents(SQL_DIR . 'category_translation.sql');
$cat_translations = parseInsertValues($cat_trans_sql, 'category_translation');
echo "Gefundene Kategorie-Übersetzungen: " . count($cat_translations) . "\n";

// Baue Übersetzungs-Map (nur Deutsch)
$cat_trans_map = [];
foreach ($cat_translations as $trans) {
    $cat_id = hexToString($trans['category_id']);
    $lang_id = hexToString($trans['language_id']);
    
    if ($lang_id === GERMAN_LANGUAGE_ID) {
        $cat_trans_map[$cat_id] = [
            'name' => cleanSqlValue($trans['name']),
            'description' => cleanSqlValue($trans['description'] ?? ''),
            'meta_title' => cleanSqlValue($trans['meta_title'] ?? ''),
            'meta_description' => cleanSqlValue($trans['meta_description'] ?? '')
        ];
    }
}
echo "Deutsche Kategorie-Übersetzungen: " . count($cat_trans_map) . "\n";

// Deutsche Root-Kategorie-ID finden (parent der Hauptkategorien)
// Die Root "Deutsch" hat level=1 und name="Deutsch"
$german_root_uuid = null;
foreach ($categories as $cat) {
    $uuid = hexToString($cat['id']);
    $level = isset($cat['level']) ? intval($cat['level']) : 0;
    if ($level == 1 && isset($cat_trans_map[$uuid])) {
        $name = $cat_trans_map[$uuid]['name'];
        if ($name === 'Deutsch' || $name === 'German') {
            $german_root_uuid = $uuid;
            echo "Deutsche Root-Kategorie gefunden: $uuid\n";
            break;
        }
    }
}

// Kategorien filtern und sortieren
$valid_categories = [];
$cat_parent_map = []; // UUID -> parent_uuid
$cat_level_map = []; // UUID -> level

foreach ($categories as $cat) {
    $uuid = hexToString($cat['id']);
    $parent_uuid = isset($cat['parent_id']) && $cat['parent_id'] !== 'NULL' ? hexToString($cat['parent_id']) : null;
    $active = isset($cat['active']) ? intval($cat['active']) : 1;
    $visible = isset($cat['visible']) ? intval($cat['visible']) : 1;
    $level = isset($cat['level']) ? intval($cat['level']) : 1;
    $type = isset($cat['type']) ? cleanSqlValue($cat['type']) : 'page';
    
    $cat_parent_map[$uuid] = $parent_uuid;
    $cat_level_map[$uuid] = $level;
    
    // Filter: nur page-Typ, aktiv, sichtbar, Level >= 2
    if ($type !== 'page' || !$active || !$visible || $level < 2) {
        continue;
    }
    
    // Muss deutsche Übersetzung haben
    if (!isset($cat_trans_map[$uuid]) || empty($cat_trans_map[$uuid]['name'])) {
        continue;
    }
    
    $name = $cat_trans_map[$uuid]['name'];
    
    // Ausschluss-Filter für nicht-Produktkategorien
    $exclude_patterns = [
        '/^Blog/i',           // Blog-Einträge
        '/^Blogeintrag/i',    // Blog-Einträge
        '/^CGV$/i',           // Französisch AGB
        '/^Conditions/i',     // Französische Seiten
        '/^Chariots/i',       // Französische Kategorien
        '/^Butées$/i',        // Französisch
        '/^Mentions/i',       // Mentions légales
        '/livraison/i',       // Lieferung (FR)
        '/paiement/i',        // Zahlung (FR)
        '/^Footermenü/i',     // Footer
        '/^Service$/i',       // Service-Seite
        '/^Impressum$/i',     // Impressum
        '/^Datenschutz$/i',   // Datenschutz
        '/^AGB$/i',           // AGB
        '/^Widerrufs/i',      // Widerruf
        '/^Kontakt$/i',       // Kontakt
    ];
    
    $excluded = false;
    foreach ($exclude_patterns as $pattern) {
        if (preg_match($pattern, $name)) {
            $excluded = true;
            break;
        }
    }
    if ($excluded) continue;
    
    // Prüfe ob unter deutscher Root (oder dessen Kindern)
    $is_under_german_root = false;
    $check_uuid = $uuid;
    $depth = 0;
    while ($check_uuid && $depth < 10) {
        if ($check_uuid === $german_root_uuid) {
            $is_under_german_root = true;
            break;
        }
        $check_uuid = $cat_parent_map[$check_uuid] ?? null;
        $depth++;
    }
    
    if (!$is_under_german_root && $german_root_uuid) {
        continue; // Nicht unter deutscher Root
    }
    
    $valid_categories[$uuid] = [
        'uuid' => $uuid,
        'parent_uuid' => $parent_uuid,
        'level' => $level,
        'name' => $name
    ];
}

echo "Gültige Kategorien nach Filter: " . count($valid_categories) . "\n";

// Sortiere nach Level (Eltern zuerst)
uasort($valid_categories, function($a, $b) {
    return $a['level'] - $b['level'];
});

// Importiere Kategorien
$imported_cats = 0;
foreach ($valid_categories as $uuid => $cat_data) {
    $name = $cat_data['name'];
    $parent_uuid = $cat_data['parent_uuid'];
    $level = $cat_data['level'];
    
    // Parent-ID ermitteln (falls Parent bereits importiert)
    $parent_new_id = null;
    if ($parent_uuid && isset($uuid_kategorie[$parent_uuid])) {
        $parent_new_id = $uuid_kategorie[$parent_uuid];
    }
    
    $sql = "INSERT INTO kategorien (name, parent_id, aktiv) VALUES ('" . 
           db_escape($name) . "', " . ($parent_new_id ? $parent_new_id : 'NULL') . ", 1)";
    
    if (db_query($sql)) {
        $new_id = db_insert_id();
        $uuid_kategorie[$uuid] = $new_id;
        $imported_cats++;
        
        // Mapping speichern
        db_query("INSERT INTO uuid_mapping (tabelle, alte_uuid, neue_id) VALUES ('kategorien', '$uuid', $new_id)");
        
        // Debug: Zeige Hierarchie
        $indent = str_repeat('  ', $level - 2);
        echo "$indent- $name (Level $level)\n";
    }
}
echo "\nImportierte Kategorien: $imported_cats\n";

// =====================================================
// SCHRITT 2: Produkte importieren
// =====================================================
echo "\n=== SCHRITT 2: Produkte ===\n";

// Alte Produkte löschen
db_query("DELETE FROM produkte WHERE id > 0");

// Lade product.sql
$prod_sql = file_get_contents(SQL_DIR . 'product.sql');
$products = parseInsertValues($prod_sql, 'product');
echo "Gefundene Produkte: " . count($products) . "\n";

// Lade product_translation.sql
$prod_trans_sql = file_get_contents(SQL_DIR . 'product_translation.sql');
$prod_translations = parseInsertValues($prod_trans_sql, 'product_translation');
echo "Gefundene Produkt-Übersetzungen: " . count($prod_translations) . "\n";

// Baue Übersetzungs-Map (nur Deutsch)
$prod_trans_map = [];
foreach ($prod_translations as $trans) {
    $prod_id = hexToString($trans['product_id']);
    $lang_id = hexToString($trans['language_id']);
    
    if ($lang_id === GERMAN_LANGUAGE_ID) {
        $prod_trans_map[$prod_id] = [
            'name' => cleanSqlValue($trans['name']),
            'description' => cleanSqlValue($trans['description'] ?? ''),
            'meta_title' => cleanSqlValue($trans['meta_title'] ?? ''),
            'keywords' => cleanSqlValue($trans['keywords'] ?? '')
        ];
    }
}
echo "Deutsche Produkt-Übersetzungen: " . count($prod_trans_map) . "\n";

// Sammle parent_id Zuordnungen für Varianten
$prod_parent_map = [];

// Importiere Produkte
$imported_prods = 0;
$skipped_no_name = 0;
$skipped_inactive = 0;

foreach ($products as $prod) {
    $uuid = hexToString($prod['id']);
    $parent_uuid = isset($prod['parent_id']) && $prod['parent_id'] !== 'NULL' ? hexToString($prod['parent_id']) : null;
    $product_number = cleanSqlValue($prod['product_number'] ?? '');
    $active = isset($prod['active']) ? intval($prod['active']) : 1;
    $stock = isset($prod['stock']) ? intval($prod['stock']) : 0;
    $available = isset($prod['available']) ? intval($prod['available']) : 1;
    
    $prod_parent_map[$uuid] = $parent_uuid;
    
    // Nur aktive Produkte
    if (!$active || !$available) {
        $skipped_inactive++;
        continue;
    }
    
    // Name aus Übersetzung oder parent
    $name = '';
    $beschreibung = '';
    
    if (isset($prod_trans_map[$uuid]) && !empty($prod_trans_map[$uuid]['name'])) {
        $name = $prod_trans_map[$uuid]['name'];
        $beschreibung = $prod_trans_map[$uuid]['description'] ?? '';
    } elseif ($parent_uuid && isset($prod_trans_map[$parent_uuid])) {
        // Variante: Name vom Parent holen
        $name = $prod_trans_map[$parent_uuid]['name'];
        $beschreibung = $prod_trans_map[$parent_uuid]['description'] ?? '';
    }
    
    if (empty($name)) {
        $skipped_no_name++;
        continue;
    }
    
    // Preis extrahieren
    $preis = 0;
    if (isset($prod['price']) && $prod['price'] !== 'NULL') {
        $preis = extractPrice($prod['price']);
    }
    
    // Beschreibung kürzen und HTML bereinigen
    $beschreibung = strip_tags($beschreibung);
    if (strlen($beschreibung) > 5000) {
        $beschreibung = substr($beschreibung, 0, 5000);
    }
    
    // Insert
    $sql = "INSERT INTO produkte (artikelnummer, name, beschreibung, preis, lagerbestand, aktiv, erstellt) VALUES (
        '" . db_escape($product_number) . "',
        '" . db_escape($name) . "',
        '" . db_escape($beschreibung) . "',
        " . floatval($preis) . ",
        " . intval($stock) . ",
        1,
        NOW()
    )";
    
    if (db_query($sql)) {
        $new_id = db_insert_id();
        $uuid_produkt[$uuid] = $new_id;
        $imported_prods++;
        
        // Mapping speichern
        db_query("INSERT INTO uuid_mapping (tabelle, alte_uuid, neue_id) VALUES ('produkte', '$uuid', $new_id)");
        
        if ($imported_prods % 500 === 0) {
            echo "  ... $imported_prods Produkte importiert\n";
        }
    }
}

echo "\nImportierte Produkte: $imported_prods\n";
echo "Übersprungen (inaktiv): $skipped_inactive\n";
echo "Übersprungen (kein Name): $skipped_no_name\n";

// =====================================================
// SCHRITT 3: Produkt-Kategorie-Zuordnung
// =====================================================
echo "\n=== SCHRITT 3: Produkt-Kategorie-Zuordnung ===\n";

// Lade product_category.sql
$prod_cat_sql = file_get_contents(SQL_DIR . 'product_category.sql');
$prod_cats = parseInsertValues($prod_cat_sql, 'product_category');
echo "Gefundene Zuordnungen: " . count($prod_cats) . "\n";

$assigned = 0;
foreach ($prod_cats as $pc) {
    $prod_uuid = hexToString($pc['product_id']);
    $cat_uuid = hexToString($pc['category_id']);
    
    if (isset($uuid_produkt[$prod_uuid]) && isset($uuid_kategorie[$cat_uuid])) {
        $prod_id = $uuid_produkt[$prod_uuid];
        $cat_id = $uuid_kategorie[$cat_uuid];
        
        // Nur eine Kategorie pro Produkt (erste)
        $check = db_fetch_row("SELECT kategorie_id FROM produkte WHERE id = $prod_id");
        if (!$check || empty($check['kategorie_id'])) {
            db_query("UPDATE produkte SET kategorie_id = $cat_id WHERE id = $prod_id");
            $assigned++;
        }
    }
}
echo "Zugeordnete Produkt-Kategorien: $assigned\n";

// =====================================================
// SCHRITT 4: Varianten-Zuordnung (parent_id)
// =====================================================
echo "\n=== SCHRITT 4: Varianten-Zuordnung ===\n";

// Update parent_id für Varianten
$variants_updated = 0;
foreach ($uuid_produkt as $uuid => $new_id) {
    $parent_uuid = $prod_parent_map[$uuid];
    if ($parent_uuid && isset($uuid_produkt[$parent_uuid])) {
        $parent_new_id = $uuid_produkt[$parent_uuid];
        db_query("UPDATE produkte SET parent_id = $parent_new_id WHERE id = $new_id");
        $variants_updated++;
    }
}
echo "Varianten mit Parent verknüpft: $variants_updated\n";

// =====================================================
// ZUSAMMENFASSUNG
// =====================================================
echo "\n=== IMPORT ABGESCHLOSSEN ===\n";
echo "Kategorien: $imported_cats\n";
echo "Produkte: $imported_prods\n";
echo "Varianten: $variants_updated\n";

// Statistik
$cat_count = db_fetch_row("SELECT COUNT(*) as cnt FROM kategorien");
$prod_count = db_fetch_row("SELECT COUNT(*) as cnt FROM produkte");
echo "\nDatenbank enthält jetzt:\n";
echo "  Kategorien: " . $cat_count['cnt'] . "\n";
echo "  Produkte: " . $prod_count['cnt'] . "\n";

echo "</pre>";
echo "<p><a href='index.php'>Zum Shop</a></p>";
echo "</body></html>";
?>
