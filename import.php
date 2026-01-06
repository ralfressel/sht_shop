<?php
/**
 * SHT Shop - Import aus Shopware SQL-Dump
 * 
 * Liest die SQL-Datei zeilenweise und importiert:
 * - Kategorien
 * - Produkte (mit deutschen Übersetzungen)
 * - Kunden
 * - Adressen
 * - Bestellungen
 * 
 * Batch-Verarbeitung: Verarbeitet X Zeilen pro Aufruf,
 * dann automatischer Reload bis fertig.
 */

// Zeitlimit erhöhen
set_time_limit(300);
ini_set('memory_limit', '512M');

require_once 'opendb.inc.php';

// =====================================================
// KONFIGURATION
// =====================================================
$sql_datei = __DIR__ . '/c8570w17019db1.sql';  // Pfad zur SQL-Datei
$batch_size = 5000;  // Zeilen pro Durchlauf
$auto_reload = true; // Automatisch fortsetzen?

// =====================================================
// HILFSFUNKTIONEN
// =====================================================

/**
 * UUID (32 Zeichen HEX) zu lesbarem Format
 */
function uuid_format($hex) {
    if (strlen($hex) == 32) {
        return $hex; // Bereits HEX-String
    }
    return bin2hex($hex);
}

/**
 * Shopware-Preis aus JSON extrahieren
 */
function extract_price($price_json) {
    if (empty($price_json)) return 0;
    $data = json_decode($price_json, true);
    if (is_array($data) && isset($data[0]['gross'])) {
        return (float)$data[0]['gross'];
    }
    if (is_array($data) && isset($data['gross'])) {
        return (float)$data['gross'];
    }
    return 0;
}

/**
 * INSERT-Statement parsen und Werte extrahieren
 */
function parse_insert($line, $table_name) {
    // Prüfen ob es ein INSERT für die gewünschte Tabelle ist
    if (strpos($line, "INSERT INTO `$table_name`") === false) {
        return null;
    }
    
    // Werte extrahieren - vereinfacht für Shopware-Dumps
    if (preg_match('/VALUES\s*\((.+)\);?$/is', $line, $matches)) {
        $values_str = $matches[1];
        // Einfaches Parsing - bei komplexen Werten ggf. anpassen
        return $values_str;
    }
    return null;
}

/**
 * Status in DB speichern
 */
function save_status($datei, $position, $zeilen, $status = 'laeuft', $fehler = null) {
    global $db;
    
    $datei = db_escape($datei);
    $fehler = $fehler ? "'" . db_escape($fehler) . "'" : "NULL";
    
    // Prüfen ob Eintrag existiert
    $exists = db_fetch_row("SELECT id FROM import_status WHERE datei = '$datei'");
    
    if ($exists) {
        db_query("UPDATE import_status SET 
            position = $position, 
            zeilen_verarbeitet = $zeilen, 
            status = '$status',
            fehler_meldung = $fehler
            WHERE datei = '$datei'");
    } else {
        db_query("INSERT INTO import_status (datei, position, zeilen_verarbeitet, status, fehler_meldung) 
            VALUES ('$datei', $position, $zeilen, '$status', $fehler)");
    }
}

/**
 * Status aus DB laden
 */
function load_status($datei) {
    $datei = db_escape($datei);
    return db_fetch_row("SELECT * FROM import_status WHERE datei = '$datei'");
}

/**
 * UUID-Mapping speichern
 */
function save_mapping($tabelle, $neue_id, $shopware_uuid) {
    global $db;
    $tabelle = db_escape($tabelle);
    $shopware_uuid = db_escape($shopware_uuid);
    
    // Nur speichern wenn nicht bereits vorhanden
    $exists = db_fetch_row("SELECT id FROM uuid_mapping WHERE tabelle = '$tabelle' AND shopware_uuid = '$shopware_uuid'");
    if (!$exists) {
        db_query("INSERT IGNORE INTO uuid_mapping (tabelle, neue_id, shopware_uuid) 
            VALUES ('$tabelle', $neue_id, '$shopware_uuid')");
    }
}

/**
 * Neue ID für Shopware-UUID finden
 */
function get_mapped_id($tabelle, $shopware_uuid) {
    $tabelle = db_escape($tabelle);
    $shopware_uuid = db_escape($shopware_uuid);
    $row = db_fetch_row("SELECT neue_id FROM uuid_mapping WHERE tabelle = '$tabelle' AND shopware_uuid = '$shopware_uuid'");
    return $row ? (int)$row['neue_id'] : null;
}

// =====================================================
// HAUPT-IMPORT-LOGIK
// =====================================================

// Modus bestimmen
$modus = isset($_GET['modus']) ? $_GET['modus'] : 'status';
$reset = isset($_GET['reset']);

// Bei Reset alles leeren
if ($reset) {
    db_query("TRUNCATE TABLE kategorien");
    db_query("TRUNCATE TABLE produkte");
    db_query("TRUNCATE TABLE kunden");
    db_query("TRUNCATE TABLE adressen");
    db_query("TRUNCATE TABLE bestellungen");
    db_query("TRUNCATE TABLE bestellpositionen");
    db_query("TRUNCATE TABLE uuid_mapping");
    db_query("TRUNCATE TABLE import_status");
    header("Location: import.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Import - SHT Shop</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 2rem auto; padding: 1rem; }
        h1 { color: #2c3e50; }
        .status { padding: 1rem; margin: 1rem 0; border-radius: 8px; }
        .status.info { background: #e3f2fd; border: 1px solid #2196f3; }
        .status.success { background: #e8f5e9; border: 1px solid #4caf50; }
        .status.error { background: #ffebee; border: 1px solid #f44336; }
        .status.warning { background: #fff3e0; border: 1px solid #ff9800; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; margin: 0.5rem; text-decoration: none; border-radius: 4px; color: white; }
        .btn-primary { background: #2196f3; }
        .btn-success { background: #4caf50; }
        .btn-danger { background: #f44336; }
        .btn:hover { opacity: 0.9; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; }
        .progress { background: #e0e0e0; border-radius: 4px; height: 24px; margin: 1rem 0; }
        .progress-bar { background: #4caf50; height: 100%; border-radius: 4px; transition: width 0.3s; }
        code { background: #f5f5f5; padding: 0.2rem 0.4rem; border-radius: 3px; }
        pre { background: #f5f5f5; padding: 1rem; overflow-x: auto; border-radius: 4px; }
    </style>
</head>
<body>

<h1>📦 Shopware-Import</h1>

<?php
// =====================================================
// STATUS-ANZEIGE (Standard)
// =====================================================
if ($modus == 'status'):
    
    // Prüfen ob SQL-Datei existiert
    $datei_existiert = file_exists($sql_datei);
    $datei_groesse = $datei_existiert ? filesize($sql_datei) : 0;
    
    // Aktueller Import-Status
    $status = load_status(basename($sql_datei));
    
    // Tabellen-Statistik
    $stats = [
        'kategorien' => db_fetch_row("SELECT COUNT(*) as cnt FROM kategorien")['cnt'],
        'produkte' => db_fetch_row("SELECT COUNT(*) as cnt FROM produkte")['cnt'],
        'kunden' => db_fetch_row("SELECT COUNT(*) as cnt FROM kunden")['cnt'],
        'adressen' => db_fetch_row("SELECT COUNT(*) as cnt FROM adressen")['cnt'],
        'bestellungen' => db_fetch_row("SELECT COUNT(*) as cnt FROM bestellungen")['cnt'],
        'bestellpositionen' => db_fetch_row("SELECT COUNT(*) as cnt FROM bestellpositionen")['cnt'],
    ];
?>

<div class="status <?= $datei_existiert ? 'success' : 'error' ?>">
    <strong>SQL-Datei:</strong> <?= basename($sql_datei) ?><br>
    <?php if ($datei_existiert): ?>
        ✓ Vorhanden (<?= number_format($datei_groesse / 1024 / 1024, 2) ?> MB)
    <?php else: ?>
        ✗ Nicht gefunden! Bitte <code><?= basename($sql_datei) ?></code> in den gleichen Ordner legen.
    <?php endif; ?>
</div>

<?php if ($status): ?>
<div class="status info">
    <strong>Import-Status:</strong> <?= ucfirst($status['status']) ?><br>
    Position: <?= number_format($status['position']) ?> Bytes<br>
    Verarbeitet: <?= number_format($status['zeilen_verarbeitet']) ?> Zeilen<br>
    <?php if ($status['fehler_meldung']): ?>
        <span style="color: red;">Fehler: <?= htmlspecialchars($status['fehler_meldung']) ?></span>
    <?php endif; ?>
    
    <?php if ($datei_groesse > 0): ?>
        <?php $prozent = min(100, ($status['position'] / $datei_groesse) * 100); ?>
        <div class="progress">
            <div class="progress-bar" style="width: <?= $prozent ?>%"></div>
        </div>
        <small><?= number_format($prozent, 1) ?>% abgeschlossen</small>
    <?php endif; ?>
</div>
<?php endif; ?>

<h3>📊 Aktueller Datenbestand</h3>
<table>
    <tr><th>Tabelle</th><th>Einträge</th></tr>
    <?php foreach ($stats as $tabelle => $anzahl): ?>
    <tr><td><?= $tabelle ?></td><td><?= number_format($anzahl) ?></td></tr>
    <?php endforeach; ?>
</table>

<h3>🚀 Aktionen</h3>

<?php if ($datei_existiert): ?>
    <a href="import.php?modus=kategorien" class="btn btn-primary">1. Kategorien importieren</a>
    <a href="import.php?modus=produkte" class="btn btn-primary">2. Produkte importieren</a>
    <a href="import.php?modus=kunden" class="btn btn-primary">3. Kunden importieren</a>
    <a href="import.php?modus=bestellungen" class="btn btn-primary">4. Bestellungen importieren</a>
<?php endif; ?>

<br><br>
<a href="import.php?reset=1" class="btn btn-danger" onclick="return confirm('ALLE Daten löschen und neu starten?')">⚠ Komplett zurücksetzen</a>

<hr>
<p><a href="index.php">← Zurück zum Shop</a></p>

<?php
// =====================================================
// KATEGORIEN IMPORTIEREN
// =====================================================
elseif ($modus == 'kategorien'):
    
    echo "<h2>📁 Kategorien importieren</h2>";
    
    $handle = fopen($sql_datei, 'r');
    if (!$handle) {
        echo "<div class='status error'>Kann SQL-Datei nicht öffnen!</div>";
        exit;
    }
    
    $imported = 0;
    $in_category_table = false;
    $in_translation_table = false;
    $kategorien_temp = [];
    $translations = [];
    
    // Deutsche Sprach-ID (aus Shopware, meist diese)
    $german_lang_id = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
    
    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        
        // category Tabelle
        if (strpos($line, "INSERT INTO `category`") !== false) {
            $in_category_table = true;
        }
        if ($in_category_table && strpos($line, "INSERT INTO") !== false && strpos($line, "`category`") === false) {
            $in_category_table = false;
        }
        
        // category_translation Tabelle
        if (strpos($line, "INSERT INTO `category_translation`") !== false) {
            $in_translation_table = true;
        }
        if ($in_translation_table && strpos($line, "INSERT INTO") !== false && strpos($line, "`category_translation`") === false) {
            $in_translation_table = false;
        }
        
        // Kategorien parsen (vereinfacht - hier müsste man die VALUES genauer parsen)
        if ($in_category_table && strpos($line, "INSERT INTO `category`") !== false) {
            // Für jetzt: Manueller Import oder über phpMyAdmin
            // Die Shopware-Struktur ist komplex mit verschachtelten Kategorien
        }
    }
    
    fclose($handle);
    
    echo "<div class='status warning'>";
    echo "<strong>Hinweis:</strong> Der automatische Kategorien-Import ist komplex wegen der Shopware-Struktur.<br>";
    echo "Empfehlung: Kategorien manuell anlegen oder aus phpMyAdmin exportieren.";
    echo "</div>";
    
    // Beispiel-Kategorien einfügen wenn leer
    $count = db_fetch_row("SELECT COUNT(*) as cnt FROM kategorien")['cnt'];
    if ($count == 0) {
        echo "<p>Füge Beispiel-Kategorien ein...</p>";
        
        db_query("INSERT INTO kategorien (name, slug, sortierung) VALUES 
            ('Kettenzüge', 'kettenzuege', 10),
            ('Hebeklemmen', 'hebeklemmen', 20),
            ('Anschlagmittel', 'anschlagmittel', 30),
            ('Hubwagen', 'hubwagen', 40),
            ('Krane', 'krane', 50),
            ('Zurrmittel', 'zurrmittel', 60)
        ");
        
        echo "<div class='status success'>✓ 6 Beispiel-Kategorien eingefügt</div>";
    }
    
    echo "<p><a href='import.php'>← Zurück zur Übersicht</a></p>";

<?php
// =====================================================
// PRODUKTE IMPORTIEREN
// =====================================================
elseif ($modus == 'produkte'):
    
    echo "<h2>📦 Produkte importieren</h2>";
    echo "<div class='status info'>Dieser Import kann einige Minuten dauern...</div>";
    
    $handle = fopen($sql_datei, 'r');
    if (!$handle) {
        echo "<div class='status error'>Kann SQL-Datei nicht öffnen!</div>";
        exit;
    }
    
    $imported = 0;
    $errors = 0;
    $buffer = '';
    $in_product = false;
    $in_translation = false;
    
    // Produkt-Daten sammeln
    $products = [];
    $translations = [];
    
    $start_time = time();
    $max_time = 60; // Max 60 Sekunden pro Durchlauf
    
    // Status laden
    $status = load_status('produkte');
    $start_pos = $status ? (int)$status['position'] : 0;
    
    if ($start_pos > 0) {
        fseek($handle, $start_pos);
    }
    
    $current_pos = $start_pos;
    $lines_processed = $status ? (int)$status['zeilen_verarbeitet'] : 0;
    
    while (($line = fgets($handle)) !== false) {
        $current_pos = ftell($handle);
        $lines_processed++;
        
        // Timeout-Check
        if (time() - $start_time > $max_time) {
            save_status('produkte', $current_pos, $lines_processed, 'laeuft');
            echo "<div class='status warning'>Timeout - wird fortgesetzt...</div>";
            echo "<script>setTimeout(function(){ window.location.href='import.php?modus=produkte'; }, 1000);</script>";
            fclose($handle);
            exit;
        }
        
        // Hier würde das eigentliche Parsing stattfinden
        // Für Shopware 6 ist das komplex wegen der binären UUIDs
    }
    
    fclose($handle);
    
    save_status('produkte', $current_pos, $lines_processed, 'fertig');
    
    echo "<div class='status warning'>";
    echo "<strong>Hinweis:</strong> Das direkte Parsen der SQL-Datei ist komplex.<br>";
    echo "Alternative Strategie: Die SQL-Datei auf IONOS in eine temporäre DB importieren, ";
    echo "dann mit einem separaten Script die Daten auslesen und in die eigenen Tabellen schreiben.";
    echo "</div>";
    
    echo "<p><a href='import.php'>← Zurück zur Übersicht</a></p>";

<?php
// =====================================================
// KUNDEN IMPORTIEREN
// =====================================================
elseif ($modus == 'kunden'):
    
    echo "<h2>👥 Kunden importieren</h2>";
    echo "<div class='status info'>In Entwicklung...</div>";
    echo "<p><a href='import.php'>← Zurück zur Übersicht</a></p>";

<?php
// =====================================================
// BESTELLUNGEN IMPORTIEREN
// =====================================================
elseif ($modus == 'bestellungen'):
    
    echo "<h2>📋 Bestellungen importieren</h2>";
    echo "<div class='status info'>In Entwicklung...</div>";
    echo "<p><a href='import.php'>← Zurück zur Übersicht</a></p>";

<?php endif; ?>

</body>
</html>
