<?php
/**
 * SHT Shop - Warenkorb
 */

session_start();
require_once 'opendb.inc.php';

// Warenkorb initialisieren
if (!isset($_SESSION['warenkorb'])) {
    $_SESSION['warenkorb'] = [];
}

// Aktionen verarbeiten
$meldung = '';

// Menge aktualisieren
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Mengen aktualisieren
    if (isset($_POST['aktualisieren'])) {
        foreach ($_POST['menge'] as $id => $menge) {
            $id = (int)$id;
            $menge = (int)$menge;
            
            if ($menge <= 0) {
                unset($_SESSION['warenkorb'][$id]);
            } elseif (isset($_SESSION['warenkorb'][$id])) {
                $_SESSION['warenkorb'][$id]['menge'] = $menge;
            }
        }
        $meldung = 'aktualisiert';
    }
    
    // Artikel entfernen
    if (isset($_POST['entfernen'])) {
        $id = (int)$_POST['entfernen'];
        unset($_SESSION['warenkorb'][$id]);
        $meldung = 'entfernt';
    }
    
    // Warenkorb leeren
    if (isset($_POST['leeren'])) {
        $_SESSION['warenkorb'] = [];
        $meldung = 'geleert';
    }
}

// Summe berechnen
$summe = 0;
$anzahl_artikel = 0;
foreach ($_SESSION['warenkorb'] as $item) {
    $summe += $item['preis'] * $item['menge'];
    $anzahl_artikel += $item['menge'];
}

$page_title = 'Warenkorb - SHT Hebetechnik';
require_once 'header.inc.php';
?>

<style>
    table { width: 100%; border-collapse: collapse; margin: 1rem 0; background: white; }
    th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e0e0e0; }
    th { background: #f5f5f5; font-weight: 600; color: #003366; }
    
    .artikel-bild { width: 80px; height: 80px; object-fit: contain; background: #f9f9f9; border-radius: 4px; }
    .artikel-info { display: flex; align-items: center; gap: 1rem; }
    .artikel-name { font-weight: 600; color: #003366; }
    .artikel-nr { color: #666; font-size: 0.85rem; }
    .artikel-option { color: #666; font-size: 0.85rem; font-style: italic; }
    
    .menge-input { width: 60px; padding: 0.5rem; text-align: center; border: 1px solid #ddd; border-radius: 4px; }
    
    .preis { font-weight: 600; white-space: nowrap; }
    .summe-zeile td { font-weight: bold; font-size: 1.1rem; background: #f5f5f5; }
    
    .aktionen { display: flex; gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap; }
    
    .hinweis-leer { text-align: center; padding: 3rem; background: #f9f9f9; border-radius: 8px; }
    .hinweis-leer h2 { color: #666; margin-bottom: 1rem; }
</style>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <a href="index.php">Startseite</a>
        <span class="separator">&gt;</span>
        <span class="current">Warenkorb</span>
    </div>
</div>

<!-- Page Header -->
<div class="page-header-simple">
    <div class="container">
        <h1>Warenkorb</h1>
    </div>
</div>

<div class="container main-content">
    
    <?php if ($meldung): ?>
    <div class="meldung success">
        <?php 
        switch($meldung) {
            case 'aktualisiert': echo '✓ Warenkorb wurde aktualisiert.'; break;
            case 'entfernt': echo '✓ Artikel wurde entfernt.'; break;
            case 'geleert': echo '✓ Warenkorb wurde geleert.'; break;
        }
        ?>
    </div>
    <?php endif; ?>
    
    <?php if (count($_SESSION['warenkorb']) > 0): ?>
    
    <form method="post">
        <table>
            <thead>
                <tr>
                    <th>Artikel</th>
                    <th style="width: 120px;">Einzelpreis</th>
                    <th style="width: 100px;">Menge</th>
                    <th style="width: 120px;">Summe</th>
                    <th style="width: 80px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_SESSION['warenkorb'] as $id => $item): 
                    $artikel_summe = $item['preis'] * $item['menge'];
                ?>
                <tr>
                    <td>
                        <div class="artikel-info">
                            <?php if ($item['bild']): ?>
                            <img src="<?= htmlspecialchars($item['bild']) ?>" alt="" class="artikel-bild">
                            <?php else: ?>
                            <div class="artikel-bild" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999;">—</div>
                            <?php endif; ?>
                            <div>
                                <div class="artikel-name"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="artikel-nr">Art.-Nr.: <?= htmlspecialchars($item['artikelnr']) ?></div>
                                <?php if (!empty($item['optionen_text'])): ?>
                                <div class="artikel-option"><?= htmlspecialchars($item['optionen_text']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="preis"><?= number_format($item['preis'], 2, ',', '.') ?> €</td>
                    <td>
                        <input type="number" name="menge[<?= $id ?>]" value="<?= $item['menge'] ?>" min="0" max="99" class="menge-input">
                    </td>
                    <td class="preis"><?= number_format($artikel_summe, 2, ',', '.') ?> €</td>
                    <td>
                        <button type="submit" name="entfernen" value="<?= $id ?>" class="btn btn-outline" title="Entfernen">✕</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <tr class="summe-zeile">
                    <td colspan="3" style="text-align: right;">Gesamtsumme:</td>
                    <td class="preis"><?= number_format($summe, 2, ',', '.') ?> €</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        
        <div class="aktionen">
            <button type="submit" name="aktualisieren" class="btn btn-outline">Aktualisieren</button>
            <button type="submit" name="leeren" class="btn btn-danger" onclick="return confirm('Warenkorb wirklich leeren?')">Warenkorb leeren</button>
            <a href="index.php" class="btn btn-outline">Weiter einkaufen</a>
            <a href="kasse.php" class="btn btn-success">Zur Kasse</a>
        </div>
    </form>
    
    <p style="margin-top: 1.5rem; font-size: 0.85rem; color: #666;">
        * Alle Preise inkl. MwSt., zzgl. Versand. Versandkostenfrei ab 100 € Bestellwert.
    </p>
    
    <?php else: ?>
    
    <div class="hinweis-leer">
        <h2>🛒 Ihr Warenkorb ist leer</h2>
        <p>Stöbern Sie in unserem Sortiment und finden Sie die passenden Produkte.</p>
        <p style="margin-top: 1.5rem;">
            <a href="index.php" class="btn btn-primary">Zum Shop</a>
        </p>
    </div>
    
    <?php endif; ?>
    
</div>

<?php require_once 'footer.inc.php'; ?>
