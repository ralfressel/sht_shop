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
$produkt = db_fetch_row("SELECT p.*, k.name as kategorie_name, k.id as kat_id 
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

// Breadcrumb-Pfad aufbauen
$breadcrumb = [];
if ($produkt['kat_id']) {
    $alle_kategorien = db_fetch_all("SELECT * FROM kategorien WHERE aktiv = 1");
    $kategorien_by_id = [];
    foreach ($alle_kategorien as $kat) {
        $kategorien_by_id[$kat['id']] = $kat;
    }
    $check_id = $produkt['kat_id'];
    while ($check_id && isset($kategorien_by_id[$check_id])) {
        array_unshift($breadcrumb, $kategorien_by_id[$check_id]);
        $check_id = $kategorien_by_id[$check_id]['parent_id'];
    }
}

// Warenkorb-Aktion
$meldung = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['in_warenkorb'])) {
    session_start();
    $menge = max(1, (int)$_POST['menge']);
    
    // Varianten-ID verwenden wenn ausgewählt
    $warenkorb_id = isset($_POST['variante']) ? (int)$_POST['variante'] : $id;
    $warenkorb_produkt = $warenkorb_id != $id ? db_fetch_row("SELECT * FROM produkte WHERE id = $warenkorb_id") : $produkt;
    
    if (!isset($_SESSION['warenkorb'])) {
        $_SESSION['warenkorb'] = [];
    }
    
    if (isset($_SESSION['warenkorb'][$warenkorb_id])) {
        $_SESSION['warenkorb'][$warenkorb_id]['menge'] += $menge;
    } else {
        $_SESSION['warenkorb'][$warenkorb_id] = [
            'produkt_id' => $warenkorb_id,
            'name' => $warenkorb_produkt['name'],
            'artikelnr' => $warenkorb_produkt['artikelnummer'],
            'preis' => $warenkorb_produkt['preis'],
            'menge' => $menge,
            'bild' => $warenkorb_produkt['bild'],
            'optionen_text' => $warenkorb_produkt['optionen_text'] ?? ''
        ];
    }
    
    $meldung = 'success';
}

$page_title = htmlspecialchars($produkt['name']) . ' - SHT Hebetechnik';
require_once 'header.inc.php';
?>

<style>
    .produkt-detail { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1.5rem; }
    @media (max-width: 768px) { .produkt-detail { grid-template-columns: 1fr; } }
    
    .produkt-bild { background: #f9f9f9; border-radius: 8px; padding: 1rem; text-align: center; }
    .produkt-bild img { max-width: 100%; max-height: 500px; object-fit: contain; }
    
    .produkt-info h1 { font-size: 1.5rem; color: #003366; margin-bottom: 0.5rem; }
    .produkt-info .artikelnr { color: #666; margin-bottom: 1rem; font-size: 0.9rem; }
    .produkt-info .preis { font-size: 1.75rem; font-weight: bold; color: #333; margin: 1rem 0; }
    .produkt-info .preis small { font-size: 0.9rem; font-weight: normal; color: #666; }
    .produkt-info .mwst { font-size: 0.85rem; color: #666; }
    .produkt-info .inhalt { font-size: 0.9rem; color: #666; margin-top: -0.5rem; }
    
    .lagerbestand { margin-top: 0.75rem; font-size: 0.9rem; }
    .lagerbestand.verfuegbar { color: #28a745; }
    .lagerbestand.niedrig { color: #ffc107; }
    .lagerbestand.leer { color: #dc3545; }
    
    .warenkorb-form { margin-top: 1.5rem; padding: 1.5rem; background: #f5f5f5; border-radius: 8px; }
    .warenkorb-form label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #003366; }
    .warenkorb-form input[type="number"] { width: 80px; padding: 0.5rem; font-size: 1rem; border: 1px solid #ddd; border-radius: 4px; }
    .warenkorb-form button { background: #28a745; color: white; border: none; padding: 0.75rem 2rem; font-size: 1rem; border-radius: 4px; cursor: pointer; margin-top: 1rem; transition: background 0.2s; }
    .warenkorb-form button:hover { background: #218838; }
    
    .varianten-gruppe { margin-bottom: 1rem; }
    .varianten-gruppe select { width: 100%; padding: 0.75rem; font-size: 1rem; border: 1px solid #ddd; border-radius: 4px; background: white; cursor: pointer; }
    .varianten-gruppe select:focus { outline: 2px solid #003366; border-color: #003366; }
    
    /* Beschreibung */
    .beschreibung-full { margin-top: 2.5rem; padding: 2rem; background: #f9f9f9; border-radius: 8px; }
    .beschreibung-full h3 { 
        font-size: 1.2rem; 
        color: #003366; 
        margin-bottom: 1rem; 
        padding-bottom: 0.75rem; 
        border-bottom: 2px solid #003366; 
    }
    .beschreibung-content { line-height: 1.8; }
    .beschreibung-content h2, .beschreibung-content h3, .beschreibung-content h4 { color: #003366; margin: 1.5rem 0 0.75rem 0; }
    .beschreibung-content h2 { font-size: 1.3rem; }
    .beschreibung-content h3 { font-size: 1.15rem; }
    .beschreibung-content ul, .beschreibung-content ol { margin: 1rem 0; padding-left: 1.5rem; }
    .beschreibung-content li { margin-bottom: 0.5rem; }
    .beschreibung-content p { margin-bottom: 1rem; }
    .beschreibung-content strong, .beschreibung-content b { color: #003366; }
</style>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <a href="index.php">Startseite</a>
        <?php foreach ($breadcrumb as $bc): ?>
        <span class="separator">&gt;</span>
        <a href="index.php?kat=<?= $bc['id'] ?>"><?= htmlspecialchars($bc['name']) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Category Banner -->
<div class="category-banner">
    <div class="container">
        <div class="category-info">
            <div class="category-text">
                <h1><?= htmlspecialchars($produkt['name']) ?></h1>
            </div>
        </div>
    </div>
</div>

<div class="container main-content">
    
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
            <p class="artikelnr">Art.-Nr.: <?= htmlspecialchars($produkt['artikelnummer']) ?></p>
            <h1><?= htmlspecialchars($produkt['name']) ?></h1>
            
            <p class="preis">
                <?= number_format($produkt['preis'], 2, ',', '.') ?> €<small>*</small>
            </p>
            <p class="inhalt">Inhalt: 1 Stück</p>
            
            <?php if ($produkt['preis_netto']): ?>
            <p class="mwst">
                Netto: <?= number_format($produkt['preis_netto'], 2, ',', '.') ?> € 
                (zzgl. <?= number_format($produkt['mwst_satz'] ?? 19, 0) ?>% MwSt.)
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
            
        </div>
        
    </div>
    
    <!-- Beschreibung unter dem Produkt, volle Breite -->
    <?php if ($produkt['beschreibung']): ?>
    <div class="beschreibung-full">
        <h3>Produktinformationen "<?= htmlspecialchars($produkt['name']) ?>"</h3>
        <div class="beschreibung-content"><?= $produkt['beschreibung'] ?></div>
    </div>
    <?php endif; ?>
    
    <p style="margin-top: 1.5rem; font-size: 0.8rem; color: #666;">* Alle Preise inkl. MwSt., zzgl. Versand</p>
    
</div>

<script>
function wechsleVariante(produktId) {
    window.location.href = 'produkt.php?id=' + produktId;
}
</script>

<?php require_once 'footer.inc.php'; ?>
