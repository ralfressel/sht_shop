<?php
/**
 * Reimport Beschreibungen MIT HTML - V2
 * Korrekte Spaltenreihenfolge
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(600);
ini_set('memory_limit', '512M');

require_once 'opendb.inc.php';

define('GERMAN_LANG_ID', '2fbb5fe2e29a4d70aa5854ce7ce3e20b');

$sql_dir = __DIR__ . '/sql-import/';

echo "<h1>Reimport Beschreibungen mit HTML v2</h1>";
echo "<pre>";

// SCHRITT 1: Lade UUID-Mapping zuerst
echo "=== SCHRITT 1: Lade Produkt-Mapping ===\n";
$result = $db->query("SELECT alte_uuid, neue_id FROM uuid_mapping WHERE tabelle = 'produkte'");
$produkt_mapping = [];
while ($row = $result->fetch_assoc()) {
    $produkt_mapping[$row['alte_uuid']] = $row['neue_id'];
}
echo "Produkt-Mappings: " . count($produkt_mapping) . "\n\n";

// SCHRITT 2: Verarbeite product_translation.sql zeilenweise
echo "=== SCHRITT 2: Verarbeite Übersetzungen ===\n";
$file = $sql_dir . 'product_translation.sql';
$handle = fopen($file, 'r');

if (!$handle) {
    die("Kann Datei nicht öffnen!");
}

$updated = 0;
$found = 0;
$in_values = false;
$buffer = '';

while (($line = fgets($handle)) !== false) {
    // Suche nach VALUES-Beginn
    if (strpos($line, 'INSERT INTO') !== false) {
        $in_values = true;
        // Finde VALUES in dieser Zeile
        $pos = strpos($line, 'VALUES');
        if ($pos !== false) {
            $buffer = substr($line, $pos + 6);
        }
        continue;
    }
    
    if ($in_values) {
        $buffer .= $line;
        
        // Prüfe ob deutsche Sprach-ID in dieser Zeile
        if (stripos($line, GERMAN_LANG_ID) !== false || stripos($buffer, GERMAN_LANG_ID) !== false) {
            // Parse diese Zeile
            // Suche (0xPRODUCT_ID, 0xVERSION_ID, 0xLANG_ID, ...
            if (preg_match('/\(0x([a-f0-9]+),\s*0x[a-f0-9]+,\s*0x' . GERMAN_LANG_ID . ',/i', $buffer, $m)) {
                $product_uuid = strtolower($m[1]);
                
                // Prüfe ob Produkt existiert
                if (isset($produkt_mapping[$product_uuid])) {
                    $found++;
                    
                    // Extrahiere description (7. Spalte)
                    // Format: (id, version, lang, meta_desc, name, keywords, description, ...)
                    // Wir müssen die 7. Wert finden
                    
                    // Finde den kompletten Datensatz
                    if (preg_match('/\(0x' . $product_uuid . '[^)]+\)/is', $buffer, $full_match)) {
                        $record = $full_match[0];
                        
                        // Parse die Felder
                        // Zähle Kommas außerhalb von Strings
                        $fields = [];
                        $current = '';
                        $in_string = false;
                        $escape_next = false;
                        $paren_depth = 0;
                        
                        // Entferne äußere Klammern
                        $record = trim($record);
                        if ($record[0] === '(') $record = substr($record, 1);
                        if (substr($record, -1) === ')') $record = substr($record, 0, -1);
                        
                        for ($i = 0; $i < strlen($record); $i++) {
                            $char = $record[$i];
                            
                            if ($escape_next) {
                                $current .= $char;
                                $escape_next = false;
                                continue;
                            }
                            
                            if ($char === '\\') {
                                $current .= $char;
                                $escape_next = true;
                                continue;
                            }
                            
                            if ($char === "'" && !$in_string) {
                                $in_string = true;
                                $current .= $char;
                            } elseif ($char === "'" && $in_string) {
                                $in_string = false;
                                $current .= $char;
                            } elseif ($char === ',' && !$in_string && $paren_depth === 0) {
                                $fields[] = trim($current);
                                $current = '';
                            } else {
                                $current .= $char;
                            }
                        }
                        if ($current !== '') {
                            $fields[] = trim($current);
                        }
                        
                        // description ist Index 6 (7. Spalte)
                        if (isset($fields[6]) && $fields[6] !== 'NULL') {
                            $description = $fields[6];
                            
                            // Entferne Anführungszeichen
                            if (substr($description, 0, 1) === "'" && substr($description, -1) === "'") {
                                $description = substr($description, 1, -1);
                            }
                            
                            // Unescape
                            $description = str_replace("\\'", "'", $description);
                            $description = str_replace('\\"', '"', $description);
                            $description = str_replace('\\n', "\n", $description);
                            $description = str_replace('\\r', '', $description);
                            $description = str_replace('\\\\', '\\', $description);
                            
                            if (!empty($description) && strlen($description) > 10) {
                                $produkt_id = $produkt_mapping[$product_uuid];
                                $desc_escaped = $db->real_escape_string($description);
                                
                                $db->query("UPDATE produkte SET beschreibung = '$desc_escaped' WHERE id = $produkt_id");
                                if ($db->affected_rows > 0) {
                                    $updated++;
                                }
                            }
                        }
                    }
                }
            }
            
            // Buffer leeren nach Verarbeitung
            $buffer = '';
        }
        
        // Begrenze Buffer-Größe
        if (strlen($buffer) > 100000) {
            $buffer = substr($buffer, -50000);
        }
    }
}

fclose($handle);

echo "Deutsche Einträge gefunden: $found\n";
echo "✓ $updated Beschreibungen aktualisiert\n\n";

// SCHRITT 3: Prüfe Ergebnis
echo "=== SCHRITT 3: Prüfe Blechgreifer ===\n";
$produkt = db_fetch_row("SELECT beschreibung FROM produkte WHERE id = 12878");
$has_html = preg_match('/<[^>]+>/', $produkt['beschreibung']);
echo "Enthält HTML: " . ($has_html ? 'JA ✓' : 'NEIN') . "\n\n";

if ($has_html) {
    echo "HTML-Tags gefunden:\n";
    preg_match_all('/<([a-z0-9]+)[^>]*>/i', $produkt['beschreibung'], $tags);
    echo implode(', ', array_unique($tags[1])) . "\n\n";
}

echo "Erste 800 Zeichen:\n";
echo htmlspecialchars(substr($produkt['beschreibung'], 0, 800));

echo "</pre>";
?>
