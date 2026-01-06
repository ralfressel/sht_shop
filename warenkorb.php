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
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Warenkorb - SHT Hebetechnik</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        
        header { background: #2c3e50; color: white; padding: 1rem; }
        header h1 { font-size: 1.5rem; }
        nav { background: #34495e; padding: 0.5rem 1rem; }
        nav a { color: white; text-decoration: none; margin-right: 1rem; }
        nav a:hover { text-decoration: underline; }
        
        .container { max-width: 1000px; margin: 0 auto; padding: 1rem; }
        
        h2 { margin: 1rem 0; color: #2c3e50; }
        
        .meldung { padding: 1rem; margin-bottom: 1rem; border-radius: 4px; }
        .meldung.success { background: #d4edda; color: #155724; }
        
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        
        .artikel-bild { width: 80px; height: 80px; object-fit: contain; background: #f9f9f9; border-radius: 4px; }
        .artikel-info { display: flex; align-items: center; gap: 1rem; }
        .artikel-name { font-weight: bold; }
        .artikel-nr { color: #666; font-size: 0.85rem; }
        
        .menge-input { width: 60px; padding: 0.5rem; text-align: center; border: 1px solid #ddd; border-radius: 4px; }
        
        .preis { font-weight: bold; white-space: nowrap; }
        .summe-zeile td { font-weight: bold; font-size: 1.1rem; background: #f5f5f5; }
        
        .btn { display: inline-block; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 0.9rem; }
        .btn-primary { background: #2c3e50; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-outline { background: white; border: 1px solid #ddd; color: #333; }
        .btn:hover { opacity: 0.9; }
        
        .aktionen { margin-top: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        
        .warenkorb-leer { text-align: center; padding: 3rem; background: #f9f9f9; border-radius: 8px; }
        .warenkorb-leer p { margin: 1rem 0; color: #666; }
        
        .checkout-box { background: #f5f5f5; padding: 1.5rem; border-radius: 8px; margin-top: 2rem; }
        .checkout-box h3 { margin-bottom: 1rem; }
        .checkout-summe { font-size: 1.5rem; font-weight: bold; color: #e74c3c; margin: 1rem 0; }
        
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
        <a href="warenkorb.php">Warenkorb (<?= $anzahl_artikel ?>)</a>
    </div>
</nav>

<div class="container">
    
    <h2>🛒 Warenkorb</h2>
    
    <?php if ($meldung): ?>
    <div class="meldung success">
        <?php if ($meldung == 'aktualisiert'): ?>✓ Warenkorb wurde aktualisiert.<?php endif; ?>
        <?php if ($meldung == 'entfernt'): ?>✓ Artikel wurde entfernt.<?php endif; ?>
        <?php if ($meldung == 'geleert'): ?>✓ Warenkorb wurde geleert.<?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if (count($_SESSION['warenkorb']) > 0): ?>
    
    <form method="post">
        <table>
            <thead>
                <tr>
                    <th>Artikel</th>
                    <th>Einzelpreis</th>
                    <th>Menge</th>
                    <th>Gesamt</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_SESSION['warenkorb'] as $id => $item): ?>
                <tr>
                    <td>
                        <div class="artikel-info">
                            <?php if ($item['bild']): ?>
                                <img src="<?= htmlspecialchars($item['bild']) ?>" alt="" class="artikel-bild">
                            <?php endif; ?>
                            <div>
                                <div class="artikel-name"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="artikel-nr">Art.-Nr.: <?= htmlspecialchars($item['artikelnr']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="preis"><?= number_format($item['preis'], 2, ',', '.') ?> €</td>
                    <td>
                        <input type="number" name="menge[<?= $id ?>]" value="<?= $item['menge'] ?>" min="0" max="99" class="menge-input">
                    </td>
                    <td class="preis"><?= number_format($item['preis'] * $item['menge'], 2, ',', '.') ?> €</td>
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
            <div>
                <button type="submit" name="aktualisieren" class="btn btn-outline">Mengen aktualisieren</button>
                <button type="submit" name="leeren" class="btn btn-danger" onclick="return confirm('Warenkorb wirklich leeren?')">Warenkorb leeren</button>
            </div>
            <a href="index.php" class="btn btn-outline">← Weiter einkaufen</a>
        </div>
    </form>
    
    <div class="checkout-box">
        <h3>Zur Kasse</h3>
        <p>Gesamtsumme inkl. MwSt.:</p>
        <p class="checkout-summe"><?= number_format($summe, 2, ',', '.') ?> €</p>
        <p style="color: #666; font-size: 0.9rem;">zzgl. Versandkosten</p>
        <br>
        <a href="kasse.php" class="btn btn-success" style="font-size: 1.1rem; padding: 0.75rem 2rem;">Zur Kasse →</a>
    </div>
    
    <?php else: ?>
    
    <div class="warenkorb-leer">
        <p style="font-size: 3rem;">🛒</p>
        <p>Ihr Warenkorb ist leer.</p>
        <br>
        <a href="index.php" class="btn btn-primary">Jetzt einkaufen</a>
    </div>
    
    <?php endif; ?>
    
</div>

<footer>
    <p>&copy; <?= date('Y') ?> SHT Hebetechnik Suhl</p>
</footer>

</body>
</html>
