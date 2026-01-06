<?php
/**
 * Debug Beschreibung
 */
require_once 'opendb.inc.php';

echo "<h1>Debug Beschreibung</h1><pre>";

// Blechgreifer Beschreibung
$produkt = db_fetch_row("SELECT id, name, beschreibung FROM produkte WHERE id = 12878");

echo "Produkt: {$produkt['name']}\n\n";
echo "=== RAW Beschreibung (erste 1000 Zeichen) ===\n";
echo htmlspecialchars(substr($produkt['beschreibung'], 0, 1000));

echo "\n\n=== Enthält HTML-Tags? ===\n";
if (preg_match('/<[^>]+>/', $produkt['beschreibung'])) {
    echo "JA - HTML Tags gefunden\n";
    preg_match_all('/<([a-z0-9]+)[^>]*>/i', $produkt['beschreibung'], $tags);
    echo "Gefundene Tags: " . implode(', ', array_unique($tags[1]));
} else {
    echo "NEIN - Keine HTML Tags\n";
}

echo "</pre>";
?>
