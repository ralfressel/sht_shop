<?php
/**
 * SHT Shop - Kasse / Checkout
 */

session_start();
require_once 'opendb.inc.php';

// Warenkorb prüfen
if (!isset($_SESSION['warenkorb']) || count($_SESSION['warenkorb']) == 0) {
    header('Location: warenkorb.php');
    exit;
}

// Summe berechnen
$summe = 0;
foreach ($_SESSION['warenkorb'] as $item) {
    $summe += $item['preis'] * $item['menge'];
}

// Versandkosten (einfache Logik)
$versandkosten = $summe >= 100 ? 0 : 6.90;
$gesamtsumme = $summe + $versandkosten;

// Fehler und Erfolg
$fehler = [];
$bestellung_erfolgreich = false;
$bestellnummer = '';

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Pflichtfelder prüfen
    $pflichtfelder = ['vorname', 'nachname', 'email', 'strasse', 'plz', 'ort', 'zahlart'];
    foreach ($pflichtfelder as $feld) {
        if (empty(trim($_POST[$feld] ?? ''))) {
            $fehler[] = "Bitte füllen Sie das Feld '$feld' aus.";
        }
    }
    
    // E-Mail prüfen
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $fehler[] = "Bitte geben Sie eine gültige E-Mail-Adresse ein.";
    }
    
    // Zahlart prüfen
    $erlaubte_zahlarten = ['rechnung', 'vorkasse', 'stripe'];
    if (!in_array($_POST['zahlart'] ?? '', $erlaubte_zahlarten)) {
        $fehler[] = "Bitte wählen Sie eine Zahlart.";
    }
    
    // Wenn keine Fehler, Bestellung speichern
    if (count($fehler) == 0) {
        
        // Bestellnummer generieren
        $bestellnummer = 'SHT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Daten escapen
        $vorname = db_escape($_POST['vorname']);
        $nachname = db_escape($_POST['nachname']);
        $firma = db_escape($_POST['firma'] ?? '');
        $email = db_escape($_POST['email']);
        $telefon = db_escape($_POST['telefon'] ?? '');
        $strasse = db_escape($_POST['strasse']);
        $plz = db_escape($_POST['plz']);
        $ort = db_escape($_POST['ort']);
        $land = db_escape($_POST['land'] ?? 'Deutschland');
        $zahlart = db_escape($_POST['zahlart']);
        $kommentar = db_escape($_POST['kommentar'] ?? '');
        
        // Berechnung
        $summe_netto = round($summe / 1.19, 2);
        $summe_mwst = round($summe - $summe_netto, 2);
        
        // Bestellung einfügen
        $sql = "INSERT INTO bestellungen (
            bestellnummer, 
            re_vorname, re_nachname, re_firma, re_strasse, re_plz, re_ort, re_land, re_email, re_telefon,
            li_vorname, li_nachname, li_firma, li_strasse, li_plz, li_ort, li_land,
            summe_netto, summe_mwst, summe_brutto, versandkosten,
            status, zahlart, zahlstatus, kundenkommentar
        ) VALUES (
            '$bestellnummer',
            '$vorname', '$nachname', '$firma', '$strasse', '$plz', '$ort', '$land', '$email', '$telefon',
            '$vorname', '$nachname', '$firma', '$strasse', '$plz', '$ort', '$land',
            $summe_netto, $summe_mwst, $summe, $versandkosten,
            'neu', '$zahlart', 'offen', '$kommentar'
        )";
        
        db_query($sql);
        $bestellung_id = db_insert_id();
        
        // Positionen einfügen
        foreach ($_SESSION['warenkorb'] as $item) {
            $p_artikelnr = db_escape($item['artikelnr']);
            $p_name = db_escape($item['name']);
            $p_menge = (int)$item['menge'];
            $p_preis = (float)$item['preis'];
            $p_gesamt = $p_preis * $p_menge;
            $p_produkt_id = (int)$item['produkt_id'];
            
            db_query("INSERT INTO bestellpositionen 
                (bestellung_id, produkt_id, artikelnr, name, menge, einzelpreis, gesamtpreis) 
                VALUES ($bestellung_id, $p_produkt_id, '$p_artikelnr', '$p_name', $p_menge, $p_preis, $p_gesamt)");
        }
        
        // Bei Stripe-Zahlung weiterleiten
        if ($_POST['zahlart'] == 'stripe') {
            // Hier später Stripe-Integration
            // header('Location: stripe_checkout.php?bestellung=' . $bestellung_id);
            // exit;
        }
        
        // Warenkorb leeren
        $_SESSION['warenkorb'] = [];
        $bestellung_erfolgreich = true;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Kasse - SHT Hebetechnik</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        
        header { background: #2c3e50; color: white; padding: 1rem; }
        header h1 { font-size: 1.5rem; }
        nav { background: #34495e; padding: 0.5rem 1rem; }
        nav a { color: white; text-decoration: none; margin-right: 1rem; }
        
        .container { max-width: 900px; margin: 0 auto; padding: 1rem; }
        
        h2 { margin: 1rem 0; color: #2c3e50; }
        h3 { margin: 1.5rem 0 1rem; color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 0.5rem; }
        
        .fehler { background: #ffebee; color: #c62828; padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .fehler ul { margin-left: 1.5rem; }
        
        .erfolg { background: #e8f5e9; padding: 2rem; border-radius: 8px; text-align: center; }
        .erfolg h2 { color: #2e7d32; }
        .erfolg .bestellnr { font-size: 1.5rem; font-weight: bold; margin: 1rem 0; }
        
        .layout { display: grid; grid-template-columns: 1fr 350px; gap: 2rem; }
        @media (max-width: 800px) { .layout { grid-template-columns: 1fr; } }
        
        .form-gruppe { margin-bottom: 1rem; }
        .form-gruppe label { display: block; margin-bottom: 0.3rem; font-weight: bold; }
        .form-gruppe input, .form-gruppe select, .form-gruppe textarea { 
            width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; 
        }
        .form-gruppe input:focus, .form-gruppe select:focus { border-color: #2c3e50; outline: none; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        
        .zahlart-option { display: flex; align-items: center; padding: 1rem; border: 2px solid #ddd; border-radius: 8px; margin-bottom: 0.5rem; cursor: pointer; }
        .zahlart-option:hover { border-color: #2c3e50; }
        .zahlart-option input { margin-right: 1rem; }
        .zahlart-option.selected { border-color: #27ae60; background: #f0fff4; }
        .zahlart-info { font-size: 0.85rem; color: #666; margin-top: 0.3rem; }
        
        .zusammenfassung { background: #f5f5f5; padding: 1.5rem; border-radius: 8px; position: sticky; top: 1rem; }
        .zusammenfassung h3 { margin-top: 0; }
        .artikel-liste { max-height: 200px; overflow-y: auto; margin-bottom: 1rem; }
        .artikel-item { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #ddd; font-size: 0.9rem; }
        .summen-tabelle { width: 100%; }
        .summen-tabelle td { padding: 0.5rem 0; }
        .summen-tabelle .total { font-weight: bold; font-size: 1.2rem; border-top: 2px solid #333; }
        
        .btn { display: inline-block; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 1rem; }
        .btn-success { background: #27ae60; color: white; width: 100%; }
        .btn-success:hover { background: #219a52; }
        
        .hinweis { font-size: 0.85rem; color: #666; margin-top: 1rem; }
        
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
    
    <?php if ($bestellung_erfolgreich): ?>
    
    <div class="erfolg">
        <h2>✓ Vielen Dank für Ihre Bestellung!</h2>
        <p class="bestellnr">Bestellnummer: <?= htmlspecialchars($bestellnummer) ?></p>
        <p>Eine Bestätigung wurde an Ihre E-Mail-Adresse gesendet.</p>
        <br>
        <a href="index.php" class="btn btn-success" style="width: auto;">Zurück zum Shop</a>
    </div>
    
    <?php else: ?>
    
    <h2>Kasse</h2>
    
    <?php if (count($fehler) > 0): ?>
    <div class="fehler">
        <strong>Bitte korrigieren Sie folgende Fehler:</strong>
        <ul>
            <?php foreach ($fehler as $f): ?>
            <li><?= htmlspecialchars($f) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <form method="post">
        <div class="layout">
            
            <!-- Linke Spalte: Formulare -->
            <div>
                <h3>📋 Rechnungsadresse</h3>
                
                <div class="form-gruppe">
                    <label>Firma (optional)</label>
                    <input type="text" name="firma" value="<?= htmlspecialchars($_POST['firma'] ?? '') ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-gruppe">
                        <label>Vorname *</label>
                        <input type="text" name="vorname" required value="<?= htmlspecialchars($_POST['vorname'] ?? '') ?>">
                    </div>
                    <div class="form-gruppe">
                        <label>Nachname *</label>
                        <input type="text" name="nachname" required value="<?= htmlspecialchars($_POST['nachname'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-gruppe">
                    <label>Straße, Hausnummer *</label>
                    <input type="text" name="strasse" required value="<?= htmlspecialchars($_POST['strasse'] ?? '') ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-gruppe">
                        <label>PLZ *</label>
                        <input type="text" name="plz" required value="<?= htmlspecialchars($_POST['plz'] ?? '') ?>">
                    </div>
                    <div class="form-gruppe">
                        <label>Ort *</label>
                        <input type="text" name="ort" required value="<?= htmlspecialchars($_POST['ort'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-gruppe">
                    <label>Land</label>
                    <select name="land">
                        <option value="Deutschland">Deutschland</option>
                        <option value="Österreich">Österreich</option>
                        <option value="Schweiz">Schweiz</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-gruppe">
                        <label>E-Mail *</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-gruppe">
                        <label>Telefon (optional)</label>
                        <input type="tel" name="telefon" value="<?= htmlspecialchars($_POST['telefon'] ?? '') ?>">
                    </div>
                </div>
                
                <h3>💳 Zahlungsart</h3>
                
                <label class="zahlart-option">
                    <input type="radio" name="zahlart" value="rechnung" <?= ($_POST['zahlart'] ?? '') == 'rechnung' ? 'checked' : '' ?>>
                    <div>
                        <strong>Rechnung</strong>
                        <div class="zahlart-info">Zahlung innerhalb von 14 Tagen nach Erhalt der Ware</div>
                    </div>
                </label>
                
                <label class="zahlart-option">
                    <input type="radio" name="zahlart" value="vorkasse" <?= ($_POST['zahlart'] ?? '') == 'vorkasse' ? 'checked' : '' ?>>
                    <div>
                        <strong>Vorkasse</strong>
                        <div class="zahlart-info">Überweisung vor Versand - Bankdaten in der Bestätigungs-E-Mail</div>
                    </div>
                </label>
                
                <label class="zahlart-option">
                    <input type="radio" name="zahlart" value="stripe" <?= ($_POST['zahlart'] ?? '') == 'stripe' ? 'checked' : '' ?>>
                    <div>
                        <strong>Kreditkarte / SEPA</strong>
                        <div class="zahlart-info">Sichere Zahlung über Stripe (Visa, Mastercard, SEPA-Lastschrift)</div>
                    </div>
                </label>
                
                <h3>📝 Anmerkungen</h3>
                
                <div class="form-gruppe">
                    <label>Kommentar zur Bestellung (optional)</label>
                    <textarea name="kommentar" rows="3"><?= htmlspecialchars($_POST['kommentar'] ?? '') ?></textarea>
                </div>
                
            </div>
            
            <!-- Rechte Spalte: Zusammenfassung -->
            <div>
                <div class="zusammenfassung">
                    <h3>🛒 Ihre Bestellung</h3>
                    
                    <div class="artikel-liste">
                        <?php foreach ($_SESSION['warenkorb'] as $item): ?>
                        <div class="artikel-item">
                            <span><?= $item['menge'] ?>x <?= htmlspecialchars($item['name']) ?></span>
                            <span><?= number_format($item['preis'] * $item['menge'], 2, ',', '.') ?> €</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <table class="summen-tabelle">
                        <tr>
                            <td>Zwischensumme:</td>
                            <td style="text-align: right;"><?= number_format($summe, 2, ',', '.') ?> €</td>
                        </tr>
                        <tr>
                            <td>Versand:</td>
                            <td style="text-align: right;">
                                <?php if ($versandkosten == 0): ?>
                                    <span style="color: #27ae60;">kostenlos</span>
                                <?php else: ?>
                                    <?= number_format($versandkosten, 2, ',', '.') ?> €
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="total">
                            <td>Gesamtsumme:</td>
                            <td style="text-align: right;"><?= number_format($gesamtsumme, 2, ',', '.') ?> €</td>
                        </tr>
                    </table>
                    
                    <p class="hinweis">inkl. MwSt.</p>
                    
                    <br>
                    <button type="submit" class="btn btn-success">Zahlungspflichtig bestellen</button>
                    
                    <p class="hinweis" style="margin-top: 1rem;">
                        Mit Ihrer Bestellung akzeptieren Sie unsere AGB und Datenschutzbestimmungen.
                    </p>
                </div>
            </div>
            
        </div>
    </form>
    
    <?php endif; ?>
    
</div>

<footer>
    <p>&copy; <?= date('Y') ?> SHT Hebetechnik Suhl</p>
</footer>

<script>
// Zahlart-Auswahl hervorheben
document.querySelectorAll('.zahlart-option').forEach(function(el) {
    el.querySelector('input').addEventListener('change', function() {
        document.querySelectorAll('.zahlart-option').forEach(function(opt) {
            opt.classList.remove('selected');
        });
        if (this.checked) {
            el.classList.add('selected');
        }
    });
    // Initial setzen
    if (el.querySelector('input').checked) {
        el.classList.add('selected');
    }
});
</script>

</body>
</html>
