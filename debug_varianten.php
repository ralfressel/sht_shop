<?php
/**
 * Debug-Skript für Varianten
 */
require_once 'opendb.inc.php';

echo "<h1>Varianten-Debug</h1>";
echo "<pre>";

// 1. Suche nach Blechgreifer
echo "=== 1. BLECHGREIFER SUCHEN ===\n";
$results = db_fetch_all("SELECT id, parent_id, artikelnummer, name, preis FROM produkte WHERE name LIKE '%Blechgreifer%' OR name LIKE '%Blechklemme%' LIMIT 20");
echo "Gefunden: " . count($results) . " Produkte\n\n";
foreach ($results as $r) {
    echo "ID: {$r['id']}, Parent: {$r['parent_id']}, Art: {$r['artikelnummer']}, Preis: {$r['preis']}\n";
    echo "   Name: {$r['name']}\n\n";
}

// 2. Prüfe parent_id Verteilung
echo "\n=== 2. PARENT_ID STATISTIK ===\n";
$stats = db_fetch_row("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN parent_id IS NULL THEN 1 ELSE 0 END) as ohne_parent,
    SUM(CASE WHEN parent_id IS NOT NULL THEN 1 ELSE 0 END) as mit_parent
    FROM produkte");
echo "Gesamt Produkte: {$stats['total']}\n";
echo "Ohne parent_id (Hauptprodukte): {$stats['ohne_parent']}\n";
echo "Mit parent_id (Varianten): {$stats['mit_parent']}\n";

// 3. Prüfe produkt_varianten Tabelle
echo "\n=== 3. PRODUKT_VARIANTEN TABELLE ===\n";
$pv_count = db_fetch_row("SELECT COUNT(*) as cnt FROM produkt_varianten");
echo "Verknüpfungen: {$pv_count['cnt']}\n";

// 4. Zeige Beispiel-Verknüpfungen
echo "\n=== 4. BEISPIEL VERKNÜPFUNGEN ===\n";
$examples = db_fetch_all("SELECT pv.produkt_id, p.name as produkt, vg.name as gruppe, vw.wert
    FROM produkt_varianten pv
    JOIN produkte p ON p.id = pv.produkt_id
    JOIN varianten_werte vw ON vw.id = pv.wert_id
    JOIN varianten_gruppen vg ON vg.id = vw.gruppe_id
    LIMIT 20");
foreach ($examples as $ex) {
    echo "Produkt #{$ex['produkt_id']}: {$ex['produkt']} -> {$ex['gruppe']}: {$ex['wert']}\n";
}

// 5. Zeige Varianten-Gruppen
echo "\n=== 5. VARIANTEN-GRUPPEN ===\n";
$groups = db_fetch_all("SELECT vg.name, COUNT(vw.id) as werte_count 
    FROM varianten_gruppen vg 
    LEFT JOIN varianten_werte vw ON vw.gruppe_id = vg.id 
    GROUP BY vg.id 
    ORDER BY werte_count DESC 
    LIMIT 15");
foreach ($groups as $g) {
    echo "{$g['name']}: {$g['werte_count']} Werte\n";
}

// 6. Suche Tragfähigkeit-Gruppe
echo "\n=== 6. TRAGFÄHIGKEIT WERTE ===\n";
$trag = db_fetch_all("SELECT vw.id, vw.wert FROM varianten_werte vw 
    JOIN varianten_gruppen vg ON vg.id = vw.gruppe_id 
    WHERE vg.name LIKE '%Tragf%' OR vg.name LIKE '%Last%' OR vg.name LIKE '%kg%'
    LIMIT 20");
foreach ($trag as $t) {
    echo "ID {$t['id']}: {$t['wert']}\n";
}

echo "</pre>";
?>
