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
            $fehler[] = "Bitte füllen Sie alle Pflichtfelder aus.";
            break;
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
            
            db_query("INSERT INTO bestellpositionen (bestellung_id, produkt_id, artikelnummer, name, menge, einzelpreis, gesamtpreis)
                VALUES ($bestellung_id, $p_produkt_id, '$p_artikelnr', '$p_name', $p_menge, $p_preis, $p_gesamt)");
        }
        
        // Warenkorb leeren
        $_SESSION['warenkorb'] = [];
        $bestellung_erfolgreich = true;
    }
}

$page_title = $bestellung_erfolgreich ? 'Bestellung abgeschlossen' : 'Kasse - SHT Hebetechnik';
require_once 'header.inc.php';
?>

<style>
    .kasse-layout { display: grid; grid-template-columns: 1fr 350px; gap: 2rem; margin-top: 1.5rem; }
    @media (max-width: 900px) { .kasse-layout { grid-template-columns: 1fr; } }
    
    .form-section { background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; }
    .form-section h3 { color: #003366; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #003366; }
    
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    @media (max-width: 500px) { .form-row { grid-template-columns: 1fr; } }
    
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.3rem; font-weight: 500; color: #333; }
    .form-group label .required { color: #dc3545; }
    .form-group input, .form-group select, .form-group textarea { 
        width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; 
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { 
        outline: 2px solid #003366; border-color: #003366; 
    }
    
    .zahlart-option { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 0.5rem; cursor: pointer; }
    .zahlart-option:hover { border-color: #003366; background: #f9f9f9; }
    .zahlart-option input[type="radio"] { width: auto; margin: 0; }
    .zahlart-option span { font-weight: 500; }
    .zahlart-option small { color: #666; font-weight: normal; }
    
    /* Sidebar Zusammenfassung */
    .zusammenfassung { background: #f9f9f9; border-radius: 8px; padding: 1.5rem; position: sticky; top: 1rem; }
    .zusammenfassung h3 { color: #003366; margin-bottom: 1rem; }
    .zusammenfassung-artikel { max-height: 200px; overflow-y: auto; margin-bottom: 1rem; }
    .zusammenfassung-artikel .artikel { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #ddd; font-size: 0.9rem; }
    .zusammenfassung-zeile { display: flex; justify-content: space-between; padding: 0.5rem 0; }
    .zusammenfassung-zeile.gesamt { font-weight: bold; font-size: 1.2rem; border-top: 2px solid #003366; padding-top: 1rem; margin-top: 0.5rem; }
    
    /* Erfolgsseite */
    .erfolg { text-align: center; padding: 3rem; background: #d4edda; border-radius: 8px; }
    .erfolg h2 { color: #155724; margin-bottom: 1rem; }
    .erfolg .bestellnummer { font-size: 1.5rem; font-weight: bold; color: #003366; margin: 1rem 0; }
    
    .fehler-liste { background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 1rem; margin-bottom: 1rem; color: #721c24; }
</style>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <a href="index.php">Startseite</a>
        <span class="separator">&gt;</span>
        <a href="warenkorb.php">Warenkorb</a>
        <span class="separator">&gt;</span>
        <span class="current">Kasse</span>
    </div>
</div>

<!-- Page Header -->
<div class="page-header-simple">
    <div class="container">
        <h1><?= $bestellung_erfolgreich ? 'Bestellung abgeschlossen' : 'Kasse' ?></h1>
    </div>
</div>

<div class="container main-content">
    
    <?php if ($bestellung_erfolgreich): ?>
    
    <div class="erfolg">
        <h2>✓ Vielen Dank für Ihre Bestellung!</h2>
        <p>Ihre Bestellung wurde erfolgreich aufgenommen.</p>
        <p class="bestellnummer">Bestellnummer: <?= htmlspecialchars($bestellnummer) ?></p>
        <p>Sie erhalten in Kürze eine Bestätigung per E-Mail.</p>
        <p style="margin-top: 2rem;">
            <a href="index.php" class="btn btn-primary">Zurück zum Shop</a>
        </p>
    </div>
    
    <?php else: ?>
    
    <?php if (count($fehler) > 0): ?>
    <div class="fehler-liste">
        <strong>Bitte korrigieren Sie folgende Fehler:</strong>
        <ul style="margin: 0.5rem 0 0 1.5rem;">
            <?php foreach ($fehler as $f): ?>
            <li><?= htmlspecialchars($f) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <form method="post">
        <div class="kasse-layout">
            
            <div class="kasse-formular">
                
                <!-- Adressdaten -->
                <div class="form-section">
                    <h3>Rechnungsadresse</h3>
                    
                    <div class="form-group">
                        <label>Firma</label>
                        <input type="text" name="firma" value="<?= htmlspecialchars($_POST['firma'] ?? '') ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Vorname <span class="required">*</span></label>
                            <input type="text" name="vorname" required value="<?= htmlspecialchars($_POST['vorname'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Nachname <span class="required">*</span></label>
                            <input type="text" name="nachname" required value="<?= htmlspecialchars($_POST['nachname'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Straße &amp; Hausnummer <span class="required">*</span></label>
                        <input type="text" name="strasse" required value="<?= htmlspecialchars($_POST['strasse'] ?? '') ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>PLZ <span class="required">*</span></label>
                            <input type="text" name="plz" required value="<?= htmlspecialchars($_POST['plz'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Ort <span class="required">*</span></label>
                            <input type="text" name="ort" required value="<?= htmlspecialchars($_POST['ort'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Land</label>
                        <select name="land">
                            <option value="Deutschland">Deutschland</option>
                            <option value="Österreich">Österreich</option>
                            <option value="Schweiz">Schweiz</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>E-Mail <span class="required">*</span></label>
                            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Telefon</label>
                            <input type="tel" name="telefon" value="<?= htmlspecialchars($_POST['telefon'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Zahlart -->
                <div class="form-section">
                    <h3>Zahlungsart</h3>
                    
                    <label class="zahlart-option">
                        <input type="radio" name="zahlart" value="rechnung" <?= ($_POST['zahlart'] ?? '') == 'rechnung' ? 'checked' : '' ?>>
                        <span>Kauf auf Rechnung</span>
                        <small>- Zahlung innerhalb 14 Tagen</small>
                    </label>
                    
                    <label class="zahlart-option">
                        <input type="radio" name="zahlart" value="vorkasse" <?= ($_POST['zahlart'] ?? '') == 'vorkasse' ? 'checked' : '' ?>>
                        <span>Vorkasse</span>
                        <small>- 2% Skonto</small>
                    </label>
                    
                    <label class="zahlart-option">
                        <input type="radio" name="zahlart" value="stripe" <?= ($_POST['zahlart'] ?? '') == 'stripe' ? 'checked' : '' ?>>
                        <span>Kreditkarte / PayPal</span>
                        <small>- via Stripe</small>
                    </label>
                </div>
                
                <!-- Kommentar -->
                <div class="form-section">
                    <h3>Bemerkungen</h3>
                    <div class="form-group">
                        <textarea name="kommentar" rows="3" placeholder="Optionale Anmerkungen zu Ihrer Bestellung..."><?= htmlspecialchars($_POST['kommentar'] ?? '') ?></textarea>
                    </div>
                </div>
                
            </div>
            
            <!-- Zusammenfassung Sidebar -->
            <div>
                <div class="zusammenfassung">
                    <h3>Ihre Bestellung</h3>
                    
                    <div class="zusammenfassung-artikel">
                        <?php foreach ($_SESSION['warenkorb'] as $item): ?>
                        <div class="artikel">
                            <span><?= $item['menge'] ?>x <?= htmlspecialchars($item['name']) ?></span>
                            <span><?= number_format($item['preis'] * $item['menge'], 2, ',', '.') ?> €</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="zusammenfassung-zeile">
                        <span>Zwischensumme:</span>
                        <span><?= number_format($summe, 2, ',', '.') ?> €</span>
                    </div>
                    
                    <div class="zusammenfassung-zeile">
                        <span>Versand:</span>
                        <span><?= $versandkosten == 0 ? 'Kostenlos' : number_format($versandkosten, 2, ',', '.') . ' €' ?></span>
                    </div>
                    
                    <div class="zusammenfassung-zeile gesamt">
                        <span>Gesamtsumme:</span>
                        <span><?= number_format($gesamtsumme, 2, ',', '.') ?> €</span>
                    </div>
                    
                    <p style="font-size: 0.8rem; color: #666; margin-top: 0.5rem;">inkl. MwSt.</p>
                    
                    <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 1rem; padding: 1rem;">
                        Jetzt kaufen
                    </button>
                    
                    <p style="font-size: 0.75rem; color: #666; margin-top: 1rem; text-align: center;">
                        Mit Klick auf "Jetzt kaufen" akzeptieren Sie unsere AGB und Datenschutzbestimmungen.
                    </p>
                </div>
            </div>
            
        </div>
    </form>
    
    <?php endif; ?>
    
</div>

<?php require_once 'footer.inc.php'; ?>
