<?php
/**
 * Debug UUID Matching
 */
require_once 'opendb.inc.php';

$sql_dir = __DIR__ . '/sql-import/';

echo "<h1>UUID Debug</h1><pre>";

// Hilfsfunktionen
function hexToUuid($hex) {
    $hex = trim($hex);
    if (stripos($hex, '0x') === 0) {
        return strtolower(substr($hex, 2));
    }
    return strtolower($hex);
}

// 1. Zeige ein paar UUIDs aus uuid_mapping
echo "=== UUIDs aus uuid_mapping (erste 5) ===\n";
$result = $db->query("SELECT alte_uuid, neue_id FROM uuid_mapping WHERE tabelle = 'produkte' LIMIT 5");
while ($row = $result->fetch_assoc()) {
    echo "DB: '{$row['alte_uuid']}' -> ID {$row['neue_id']}\n";
}

// 2. Zeige ein paar UUIDs aus product_option.sql
echo "\n=== UUIDs aus product_option.sql (erste 5) ===\n";
$sql = file_get_contents($sql_dir . 'product_option.sql');

// Finde erste INSERT Zeile
if (preg_match('/INSERT INTO.*?VALUES\s*(\([^;]+)/is', $sql, $m)) {
    $values_part = $m[1];
    // Parse erste paar Werte
    if (preg_match_all('/\(([^)]+)\)/s', $values_part, $rows)) {
        $count = 0;
        foreach ($rows[1] as $row) {
            if ($count >= 5) break;
            $parts = explode(',', $row);
            if (count($parts) >= 2) {
                $raw_uuid = trim($parts[0]);
                $converted = hexToUuid($raw_uuid);
                echo "RAW: '$raw_uuid'\n";
                echo "Converted: '$converted'\n\n";
            }
            $count++;
        }
    }
}

// 3. Suche nach einem bekannten Produkt (Blechgreifer 750001)
echo "\n=== Suche Blechgreifer UUID ===\n";
$result = $db->query("SELECT alte_uuid, neue_id FROM uuid_mapping WHERE tabelle = 'produkte' AND neue_id IN (12878, 12879, 15101, 15102, 15103, 15104)");
while ($row = $result->fetch_assoc()) {
    echo "Produkt ID {$row['neue_id']}: UUID = '{$row['alte_uuid']}'\n";
}

// 4. Suche diese UUIDs in product_option.sql
echo "\n=== Suche in product_option.sql ===\n";
$result = $db->query("SELECT alte_uuid FROM uuid_mapping WHERE tabelle = 'produkte' AND neue_id = 12879");
if ($row = $result->fetch_assoc()) {
    $uuid = $row['alte_uuid'];
    echo "Suche UUID: $uuid\n";
    
    // Suche in verschiedenen Formaten
    $formats = [
        $uuid,
        '0x' . $uuid,
        strtoupper($uuid),
        '0x' . strtoupper($uuid)
    ];
    
    foreach ($formats as $f) {
        $found = strpos($sql, $f) !== false;
        echo "Format '$f': " . ($found ? "GEFUNDEN" : "nicht gefunden") . "\n";
    }
}

echo "</pre>";
?>
