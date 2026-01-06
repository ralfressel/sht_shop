<?php
/**
 * SHT Shop - Produktdetailseite
 */

require_once 'opendb.inc.php';

// Produkt-ID aus URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Produkt laden
$produkt = db_fetch_row("SELECT p.*, k.name as kategorie_name, k.slug as kategorie_slug 
    FROM produkte p 
    LEFT JOIN kategorien k ON p.kategorie_id = k.id 
    WHERE p.id = $id AND p.aktiv = 1");

if (!$produkt) {
    header('Location: index.php');
    exit;
}

// Prüfe ob dieses Produkt ein Hauptprodukt mit Varianten ist
$varianten = [];
$aktuelle_variante_id = $id;

// Wenn das Produkt kein parent_id hat, könnte es ein Hauptprodukt sein
if (!$produkt['parent_id']) {
    // Lade alle Varianten dieses Hauptprodukts (über parent_id)
    $varianten = db_fetch_all("SELECT id, artikelnummer, name, preis, preis_netto, lagerbestand, optionen_text
        FROM produkte 
        WHERE parent_id = $id AND aktiv = 1
        ORDER BY preis ASC");
}

// Wenn dieses Produkt selbst eine Variante ist, lade Parent + alle Geschwister
if ($produkt['parent_id']) {
    $parent_id = (int)$produkt['parent_id'];
    $aktuelle_variante_id = $id;
    
    // Lade alle Varianten inkl. dieser
    $varianten = db_fetch_all("SELECT id, artikelnummer, name, preis, preis_netto, lagerbestand, optionen_text
        FROM produkte 
        WHERE parent_id = $parent_id AND aktiv = 1
        ORDER BY preis ASC");
        
    // Lade auch das Parent-Produkt für Name/Beschreibung
    $parent = db_fetch_row("SELECT * FROM produkte WHERE id = $parent_id");
    if ($parent && empty($produkt['beschreibung'])) {
        $produkt['beschreibung'] = $parent['beschreibung'];
    }
}

// Warenkorb-Aktion
$meldung = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['in_warenkorb'])) {
    session_start();
    $menge = max(1, (int)$_POST['menge']);
    
    if (!isset($_SESSION['warenkorb'])) {
        $_SESSION['warenkorb'] = [];
    }
    
    if (isset($_SESSION['warenkorb'][$id])) {
        $_SESSION['warenkorb'][$id]['menge'] += $menge;
    } else {
        $_SESSION['warenkorb'][$id] = [
            'produkt_id' => $id,
            'name' => $produkt['name'],
            'artikelnr' => $produkt['artikelnr'],
            'preis' => $produkt['preis'],
            'menge' => $menge,
            'bild' => $produkt['bild']
        ];
    }
    
    $meldung = 'success';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($produkt['name']) ?> - SHT Hebetechnik</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        
        header { background: #2c3e50; color: white; padding: 1rem; }
        header h1 { font-size: 1.5rem; }
        nav { background: #34495e; padding: 0.5rem 1rem; }
        nav a { color: white; text-decoration: none; margin-right: 1rem; }
        nav a:hover { text-decoration: underline; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 1rem; }
        
        .breadcrumb { padding: 1rem 0; color: #666; font-size: 0.9rem; }
        .breadcrumb a { color: #2c3e50; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        
        .produkt-detail { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1rem; }
        @media (max-width: 768px) { .produkt-detail { grid-template-columns: 1fr; } }
        
        .produkt-bild { background: #f9f9f9; border-radius: 8px; padding: 1rem; text-align: center; }
        .produkt-bild img { max-width: 100%; max-height: 500px; object-fit: contain; }
        
        .produkt-info h1 { font-size: 1.75rem; margin-bottom: 0.5rem; }
        .produkt-info .artikelnr { color: #666; margin-bottom: 1rem; }
        .produkt-info .preis { font-size: 2rem; font-weight: bold; color: #e74c3c; margin: 1rem 0; }
        .produkt-info .preis small { font-size: 0.9rem; font-weight: normal; color: #666; }
        .produkt-info .mwst { font-size: 0.85rem; color: #666; }
        
        .beschreibung { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #ddd; }
        .beschreibung h3 { margin-bottom: 0.5rem; }
        
        .warenkorb-form { margin-top: 1.5rem; padding: 1.5rem; background: #f5f5f5; border-radius: 8px; }
        .warenkorb-form label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        .warenkorb-form input[type="number"] { width: 80px; padding: 0.5rem; font-size: 1rem; border: 1px solid #ddd; border-radius: 4px; }
        .warenkorb-form button { background: #27ae60; color: white; border: none; padding: 0.75rem 2rem; font-size: 1rem; border-radius: 4px; cursor: pointer; margin-top: 1rem; }
        .warenkorb-form button:hover { background: #219a52; }
        
        .varianten-gruppe { margin-bottom: 1rem; }
        .varianten-gruppe select { width: 100%; padding: 0.75rem; font-size: 1rem; border: 1px solid #ddd; border-radius: 4px; background: white; cursor: pointer; }
        .varianten-gruppe select:focus { outline: 2px solid #3498db; border-color: #3498db; }
        
        .meldung { padding: 1rem; margin-bottom: 1rem; border-radius: 4px; }
        .meldung.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        
        .lagerbestand { margin-top: 0.5rem; font-size: 0.9rem; }
        .lagerbestand.verfuegbar { color: #27ae60; }
        .lagerbestand.niedrig { color: #f39c12; }
        .lagerbestand.leer { color: #e74c3c; }
        
        footer { background: #2c3e50; color: white; padding: 2rem 1rem; margin-top: 3rem; text-align: center; }
    </style>
</head>
<body>

<header>
    <div class="container">
        <h1>🏗️ SHT Hebetechnik</h1>
    </div>
</header>

<nav>
    <div class="container">
        <a href="index.php">Startseite</a>
        <a href="warenkorb.php">Warenkorb</a>
    </div>
</nav>

<div class="container">
    
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="index.php">Startseite</a> &raquo;
        <?php if ($produkt['kategorie_name']): ?>
            <a href="index.php?kat=<?= $produkt['kategorie_id'] ?>"><?= htmlspecialchars($produkt['kategorie_name']) ?></a> &raquo;
        <?php endif; ?>
        <?= htmlspecialchars($produkt['name']) ?>
    </div>
    
    <?php if ($meldung == 'success'): ?>
    <div class="meldung success">
        ✓ Artikel wurde zum Warenkorb hinzugefügt. 
        <a href="warenkorb.php">Zum Warenkorb</a>
    </div>
    <?php endif; ?>
    
    <div class="produkt-detail">
        
        <!-- Bild -->
        <div class="produkt-bild">
            <?php if ($produkt['bild']): ?>
                <img src="<?= htmlspecialchars($produkt['bild']) ?>" alt="<?= htmlspecialchars($produkt['name']) ?>">
            <?php else: ?>
                <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 400'><rect fill='%23f0f0f0' width='400' height='400'/><text x='200' y='210' text-anchor='middle' fill='%23999' font-size='20'>Kein Bild vorhanden</text></svg>" alt="Kein Bild">
            <?php endif; ?>
        </div>
        
        <!-- Info -->
        <div class="produkt-info">
            <p class="artikelnr">Art.-Nr.: <?= htmlspecialchars($produkt['artikelnr']) ?></p>
            <h1><?= htmlspecialchars($produkt['name']) ?></h1>
            
            <p class="preis">
                <?= number_format($produkt['preis'], 2, ',', '.') ?> €
                <small>inkl. MwSt.</small>
            </p>
            
            <?php if ($produkt['preis_netto']): ?>
            <p class="mwst">
                Netto: <?= number_format($produkt['preis_netto'], 2, ',', '.') ?> € 
                (zzgl. <?= number_format($produkt['mwst_satz'], 0) ?>% MwSt.)
            </p>
            <?php endif; ?>
            
            <!-- Lagerbestand -->
            <?php 
            $lager = (int)$produkt['lagerbestand'];
            if ($lager > 10): ?>
                <p class="lagerbestand verfuegbar">✓ Auf Lager</p>
            <?php elseif ($lager > 0): ?>
                <p class="lagerbestand niedrig">⚠ Nur noch <?= $lager ?> Stück verfügbar</p>
            <?php else: ?>
                <p class="lagerbestand leer">✗ Derzeit nicht auf Lager</p>
            <?php endif; ?>
            
            <!-- Warenkorb-Formular -->
            <form method="post" class="warenkorb-form">
                
                <?php if (!empty($varianten)): ?>
                <!-- Varianten-Auswahl -->
                <div class="varianten-gruppe">
                    <label>Variante wählen:</label>
                    <select name="variante" id="variante_select" onchange="wechsleVariante(this.value)">
                        <?php foreach ($varianten as $var): 
                            $selected = ($var['id'] == $aktuelle_variante_id) ? 'selected' : '';
                            $optName = !empty($var['optionen_text']) ? $var['optionen_text'] : 'Variante';
                        ?>
                        <option value="<?= $var['id'] ?>" <?= $selected ?>>
                            <?= htmlspecialchars($optName) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <label for="menge">Menge:</label>
                <input type="number" name="menge" id="menge" value="1" min="1" max="99">
                <br>
                <button type="submit" name="in_warenkorb">🛒 In den Warenkorb</button>
            </form>
            
            <!-- Beschreibung -->
            <?php if ($produkt['beschreibung']): ?>
            <div class="beschreibung">
                <h3>Beschreibung</h3>
                <div><?= $produkt['beschreibung'] ?></div>
            </div>
            <?php endif; ?>
            
        </div>
        
    </div>
    
</div>

<footer>
    <p>&copy; <?= date('Y') ?> SHT Hebetechnik Suhl</p>
</footer>

<script>
function wechsleVariante(produktId) {
    // Zur neuen Variante navigieren
    window.location.href = 'produkt.php?id=' + produktId;
}
</script>

</body>
</html>
