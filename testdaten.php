<?php
/**
 * SHT Shop - Testdaten einfügen
 * Fügt Beispiel-Kategorien und Produkte ein, um den Shop zu testen
 */

require_once 'opendb.inc.php';

$meldung = '';
$fehler = '';

// Aktion verarbeiten
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Tabellen leeren
    if (isset($_POST['leeren'])) {
        db_query("DELETE FROM bestellpositionen");
        db_query("DELETE FROM bestellungen");
        db_query("DELETE FROM produkte");
        db_query("DELETE FROM kategorien");
        db_query("ALTER TABLE kategorien AUTO_INCREMENT = 1");
        db_query("ALTER TABLE produkte AUTO_INCREMENT = 1");
        db_query("ALTER TABLE bestellungen AUTO_INCREMENT = 1");
        $meldung = "Alle Tabellen wurden geleert.";
    }
    
    // Testdaten einfügen
    if (isset($_POST['einfuegen'])) {
        
        // Kategorien
        $kategorien = [
            ['name' => 'Kettenzüge', 'slug' => 'kettenzuege'],
            ['name' => 'Seilzüge', 'slug' => 'seilzuege'],
            ['name' => 'Hebezeuge', 'slug' => 'hebezeuge'],
            ['name' => 'Krane', 'slug' => 'krane'],
            ['name' => 'Anschlagmittel', 'slug' => 'anschlagmittel'],
            ['name' => 'Zubehör', 'slug' => 'zubehoer'],
        ];
        
        $kat_ids = [];
        foreach ($kategorien as $kat) {
            $name = db_escape($kat['name']);
            $slug = db_escape($kat['slug']);
            db_query("INSERT INTO kategorien (name, slug, aktiv) VALUES ('$name', '$slug', 1)");
            $kat_ids[$kat['slug']] = db_insert_id();
        }
        
        // Produkte
        $produkte = [
            // Kettenzüge
            ['kat' => 'kettenzuege', 'artikelnr' => 'KZ-1000', 'name' => 'Elektrokettenzug 1000 kg', 'preis' => 1299.00, 'beschreibung' => 'Robuster Elektrokettenzug mit 1000 kg Tragfähigkeit. Hubhöhe 6m, 230V Anschluss.', 'lager' => 5],
            ['kat' => 'kettenzuege', 'artikelnr' => 'KZ-2000', 'name' => 'Elektrokettenzug 2000 kg', 'preis' => 1899.00, 'beschreibung' => 'Leistungsstarker Elektrokettenzug mit 2000 kg Tragfähigkeit. Hubhöhe 6m, 400V Anschluss.', 'lager' => 3],
            ['kat' => 'kettenzuege', 'artikelnr' => 'KZ-500M', 'name' => 'Manueller Kettenzug 500 kg', 'preis' => 189.00, 'beschreibung' => 'Handkettenzug für leichte Lasten bis 500 kg. Kompakt und wartungsarm.', 'lager' => 12],
            ['kat' => 'kettenzuege', 'artikelnr' => 'KZ-3000', 'name' => 'Elektrokettenzug 3000 kg', 'preis' => 2499.00, 'beschreibung' => 'Schwerlast-Elektrokettenzug mit 3000 kg Tragfähigkeit. Hubhöhe 9m.', 'lager' => 2],
            
            // Seilzüge
            ['kat' => 'seilzuege', 'artikelnr' => 'SZ-5000', 'name' => 'Elektroseilzug 5000 kg', 'preis' => 4299.00, 'beschreibung' => 'Industrieller Elektroseilzug für schwere Lasten bis 5 Tonnen.', 'lager' => 1],
            ['kat' => 'seilzuege', 'artikelnr' => 'SZ-1000', 'name' => 'Kompakt-Seilzug 1000 kg', 'preis' => 899.00, 'beschreibung' => 'Kompakter Seilzug für Werkstatt und Industrie. 1000 kg Tragkraft.', 'lager' => 8],
            
            // Hebezeuge
            ['kat' => 'hebezeuge', 'artikelnr' => 'HZ-WINDE', 'name' => 'Handseilwinde 500 kg', 'preis' => 129.00, 'beschreibung' => 'Robuste Handseilwinde mit Ratschenfunktion. Ideal für Montage und Transport.', 'lager' => 20],
            ['kat' => 'hebezeuge', 'artikelnr' => 'HZ-HEBER', 'name' => 'Hydraulik-Hubtisch 1000 kg', 'preis' => 749.00, 'beschreibung' => 'Fahrbarer Hydraulik-Hubtisch. Hubhöhe 800mm, Plattform 1000x500mm.', 'lager' => 4],
            ['kat' => 'hebezeuge', 'artikelnr' => 'HZ-GABEL', 'name' => 'Gabelhubwagen 2500 kg', 'preis' => 349.00, 'beschreibung' => 'Standard-Gabelhubwagen für Paletten. Tragkraft 2500 kg.', 'lager' => 15],
            
            // Krane
            ['kat' => 'krane', 'artikelnr' => 'KR-SCHWENK', 'name' => 'Säulenschwenkkran 500 kg', 'preis' => 3499.00, 'beschreibung' => 'Wandschwenkkran mit 3m Ausladung. Inkl. Elektrokettenzug 500 kg.', 'lager' => 0],
            ['kat' => 'krane', 'artikelnr' => 'KR-PORTAL', 'name' => 'Portalkran mobil 1000 kg', 'preis' => 2899.00, 'beschreibung' => 'Mobiler Portalkran, zerlegbar. Spannweite 3m, Höhe 3m.', 'lager' => 2],
            
            // Anschlagmittel
            ['kat' => 'anschlagmittel', 'artikelnr' => 'AM-KETTE2', 'name' => 'Anschlagkette 2-strängig 2000 kg', 'preis' => 189.00, 'beschreibung' => '2-strängige Anschlagkette, Güteklasse 10, mit Verkürzungshaken.', 'lager' => 25],
            ['kat' => 'anschlagmittel', 'artikelnr' => 'AM-GURT3', 'name' => 'Hebeband 3000 kg', 'preis' => 45.00, 'beschreibung' => 'Polyester-Hebeband, 3m Länge, 3000 kg Tragfähigkeit.', 'lager' => 50],
            ['kat' => 'anschlagmittel', 'artikelnr' => 'AM-SEIL1', 'name' => 'Anschlagseil 1000 kg', 'preis' => 79.00, 'beschreibung' => 'Stahlseil mit Pressklemmen, 2m Länge.', 'lager' => 30],
            ['kat' => 'anschlagmittel', 'artikelnr' => 'AM-SCHAE', 'name' => 'Schäkel 2000 kg', 'preis' => 18.50, 'beschreibung' => 'Hochfester Schäkel mit Schraubbolzen.', 'lager' => 100],
            
            // Zubehör
            ['kat' => 'zubehoer', 'artikelnr' => 'ZB-FERN', 'name' => 'Funkfernsteuerung', 'preis' => 349.00, 'beschreibung' => 'Funkfernbedienung für Elektrokettenzüge. Reichweite bis 100m.', 'lager' => 7],
            ['kat' => 'zubehoer', 'artikelnr' => 'ZB-FAHR', 'name' => 'Elektrofahrwerk 1000 kg', 'preis' => 599.00, 'beschreibung' => 'Elektrofahrwerk für I-Träger, passend für alle Kettenzüge.', 'lager' => 6],
            ['kat' => 'zubehoer', 'artikelnr' => 'ZB-HAKEN', 'name' => 'Lasthaken mit Sicherung 2000 kg', 'preis' => 39.00, 'beschreibung' => 'Ersatz-Lasthaken mit Sicherheitsfalle.', 'lager' => 40],
            ['kat' => 'zubehoer', 'artikelnr' => 'ZB-ROLLE', 'name' => 'Umlenkrolle 1000 kg', 'preis' => 89.00, 'beschreibung' => 'Umlenkrolle für Seile und Ketten.', 'lager' => 15],
        ];
        
        $anzahl = 0;
        foreach ($produkte as $p) {
            $kat_id = $kat_ids[$p['kat']];
            $artikelnr = db_escape($p['artikelnr']);
            $name = db_escape($p['name']);
            $preis = (float)$p['preis'];
            $preis_netto = round($preis / 1.19, 2);
            $beschreibung = db_escape($p['beschreibung']);
            $lager = (int)$p['lager'];
            
            db_query("INSERT INTO produkte (kategorie_id, artikelnr, name, beschreibung, preis, preis_netto, mwst_satz, lagerbestand, aktiv) 
                VALUES ($kat_id, '$artikelnr', '$name', '$beschreibung', $preis, $preis_netto, 19, $lager, 1)");
            $anzahl++;
        }
        
        $meldung = count($kategorien) . " Kategorien und $anzahl Produkte wurden eingefügt.";
    }
}

// Aktuelle Anzahl abfragen
$anz_kategorien = db_fetch_row("SELECT COUNT(*) as cnt FROM kategorien")['cnt'] ?? 0;
$anz_produkte = db_fetch_row("SELECT COUNT(*) as cnt FROM produkte")['cnt'] ?? 0;
$anz_bestellungen = db_fetch_row("SELECT COUNT(*) as cnt FROM bestellungen")['cnt'] ?? 0;

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Testdaten - SHT Shop Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 2rem; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { color: #2c3e50; margin-bottom: 1rem; }
        
        .box { background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .box h2 { margin-bottom: 1rem; font-size: 1.2rem; color: #34495e; }
        
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem; }
        .stat { text-align: center; padding: 1rem; background: #ecf0f1; border-radius: 8px; }
        .stat .zahl { font-size: 2rem; font-weight: bold; color: #2c3e50; }
        .stat .label { color: #7f8c8d; font-size: 0.9rem; }
        
        .meldung { padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .meldung.success { background: #d4edda; color: #155724; }
        .meldung.error { background: #f8d7da; color: #721c24; }
        
        .btn { display: inline-block; padding: 0.75rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; margin-right: 0.5rem; margin-bottom: 0.5rem; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn:hover { opacity: 0.9; }
        
        .hinweis { background: #fff3cd; padding: 1rem; border-radius: 4px; margin-bottom: 1rem; color: #856404; }
        
        a { color: #3498db; }
    </style>
</head>
<body>

<div class="container">
    <h1>🛠️ SHT Shop - Testdaten</h1>
    
    <?php if ($meldung): ?>
    <div class="meldung success">✓ <?= htmlspecialchars($meldung) ?></div>
    <?php endif; ?>
    
    <?php if ($fehler): ?>
    <div class="meldung error">✗ <?= htmlspecialchars($fehler) ?></div>
    <?php endif; ?>
    
    <div class="box">
        <h2>📊 Aktueller Datenbestand</h2>
        <div class="stats">
            <div class="stat">
                <div class="zahl"><?= $anz_kategorien ?></div>
                <div class="label">Kategorien</div>
            </div>
            <div class="stat">
                <div class="zahl"><?= $anz_produkte ?></div>
                <div class="label">Produkte</div>
            </div>
            <div class="stat">
                <div class="zahl"><?= $anz_bestellungen ?></div>
                <div class="label">Bestellungen</div>
            </div>
        </div>
    </div>
    
    <div class="box">
        <h2>🔧 Aktionen</h2>
        
        <div class="hinweis">
            <strong>Hinweis:</strong> Diese Seite fügt Beispiel-Produkte für Hebetechnik ein, damit Sie den Shop testen können.
        </div>
        
        <form method="post" style="margin-bottom: 1rem;">
            <button type="submit" name="einfuegen" class="btn btn-success">
                ➕ Testdaten einfügen (<?= $anz_produkte > 0 ? 'weitere hinzufügen' : '6 Kategorien, 20 Produkte' ?>)
            </button>
        </form>
        
        <form method="post" onsubmit="return confirm('Wirklich ALLE Daten löschen?');">
            <button type="submit" name="leeren" class="btn btn-danger">
                🗑️ Alle Tabellen leeren
            </button>
        </form>
    </div>
    
    <div class="box">
        <h2>🔗 Links</h2>
        <p>
            <a href="index.php">→ Shop Startseite</a><br>
            <a href="warenkorb.php">→ Warenkorb</a><br>
            <a href="test_db.php">→ Datenbanktest</a><br>
            <a href="import.php">→ Shopware-Import</a>
        </p>
    </div>
    
</div>

</body>
</html>
