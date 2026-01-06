<?php
/**
 * Schema Update Script
 * Aktualisiert die bestehende Datenbank auf IONOS
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'opendb.inc.php';

echo "<html><head><meta charset='UTF-8'><title>Schema Update</title></head><body>";
echo "<h1>Schema Update</h1>";
echo "<pre>";

// Prüfe ob parent_id in produkte existiert
$result = $db->query("SHOW COLUMNS FROM produkte LIKE 'parent_id'");
if ($result->num_rows == 0) {
    echo "Füge parent_id zu produkte hinzu...\n";
    if ($db->query("ALTER TABLE produkte ADD COLUMN parent_id INT UNSIGNED DEFAULT NULL AFTER id")) {
        echo "✓ parent_id hinzugefügt\n";
    } else {
        echo "✗ Fehler: " . $db->error . "\n";
    }
    
    if ($db->query("ALTER TABLE produkte ADD INDEX idx_parent (parent_id)")) {
        echo "✓ Index idx_parent hinzugefügt\n";
    }
} else {
    echo "✓ parent_id existiert bereits\n";
}

// Prüfe ob artikelnummer existiert (statt artikelnr)
$result = $db->query("SHOW COLUMNS FROM produkte LIKE 'artikelnummer'");
if ($result->num_rows == 0) {
    // Prüfe ob artikelnr existiert
    $result2 = $db->query("SHOW COLUMNS FROM produkte LIKE 'artikelnr'");
    if ($result2->num_rows > 0) {
        echo "Benenne artikelnr zu artikelnummer um...\n";
        if ($db->query("ALTER TABLE produkte CHANGE artikelnr artikelnummer VARCHAR(100) NOT NULL")) {
            echo "✓ artikelnr zu artikelnummer umbenannt\n";
        } else {
            echo "✗ Fehler: " . $db->error . "\n";
        }
    } else {
        echo "Füge artikelnummer hinzu...\n";
        if ($db->query("ALTER TABLE produkte ADD COLUMN artikelnummer VARCHAR(100) NOT NULL DEFAULT '' AFTER parent_id")) {
            echo "✓ artikelnummer hinzugefügt\n";
        } else {
            echo "✗ Fehler: " . $db->error . "\n";
        }
    }
} else {
    echo "✓ artikelnummer existiert bereits\n";
}

// Prüfe ob slug in kategorien nullable ist
$result = $db->query("SHOW COLUMNS FROM kategorien LIKE 'slug'");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if ($row['Null'] === 'NO') {
        echo "Mache slug nullable...\n";
        if ($db->query("ALTER TABLE kategorien MODIFY COLUMN slug VARCHAR(255) DEFAULT NULL")) {
            echo "✓ slug ist jetzt nullable\n";
        } else {
            echo "✗ Fehler: " . $db->error . "\n";
        }
    } else {
        echo "✓ slug ist bereits nullable\n";
    }
} else {
    echo "✓ slug existiert nicht (wird nicht benötigt)\n";
}

// Erstelle uuid_mapping Tabelle
echo "\nErstelle uuid_mapping Tabelle...\n";
$sql = "CREATE TABLE IF NOT EXISTS uuid_mapping (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tabelle VARCHAR(50) NOT NULL,
    alte_uuid VARCHAR(64) NOT NULL,
    neue_id INT UNSIGNED NOT NULL,
    erstellt DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tabelle_uuid (tabelle, alte_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($db->query($sql)) {
    echo "✓ uuid_mapping erstellt/existiert\n";
} else {
    echo "✗ Fehler: " . $db->error . "\n";
}

// Erstelle import_status Tabelle
echo "\nErstelle import_status Tabelle...\n";
$sql = "CREATE TABLE IF NOT EXISTS import_status (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tabelle VARCHAR(50) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    anzahl INT DEFAULT 0,
    fehler TEXT,
    gestartet DATETIME DEFAULT CURRENT_TIMESTAMP,
    beendet DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($db->query($sql)) {
    echo "✓ import_status erstellt/existiert\n";
} else {
    echo "✗ Fehler: " . $db->error . "\n";
}

// Zeige aktuelle Tabellenstruktur
echo "\n\n=== Aktuelle Tabellenstruktur ===\n\n";

echo "KATEGORIEN:\n";
$result = $db->query("DESCRIBE kategorien");
while ($row = $result->fetch_assoc()) {
    echo "  " . $row['Field'] . " - " . $row['Type'] . " - " . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}

echo "\nPRODUKTE:\n";
$result = $db->query("DESCRIBE produkte");
while ($row = $result->fetch_assoc()) {
    echo "  " . $row['Field'] . " - " . $row['Type'] . " - " . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}

echo "\n\n=== Schema Update abgeschlossen ===\n";

echo "</pre>";
echo "<p><a href='shopware_import.php'>Weiter zum Import</a></p>";
echo "</body></html>";
?>
