-- SHT Shop - Datenbankschema
-- Vor Go-Live auf IONOS ausführen
-- Kompaktes Schema mit Auto-Increment IDs

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- KATEGORIEN
-- =====================================================
DROP TABLE IF EXISTS kategorien;
CREATE TABLE kategorien (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    sortierung INT DEFAULT 0,
    aktiv TINYINT(1) DEFAULT 1,
    erstellt DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_parent (parent_id),
    INDEX idx_slug (slug),
    INDEX idx_aktiv (aktiv)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- PRODUKTE
-- =====================================================
DROP TABLE IF EXISTS produkte;
CREATE TABLE produkte (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kategorie_id INT UNSIGNED DEFAULT NULL,
    artikelnr VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    beschreibung TEXT,
    preis DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    preis_netto DECIMAL(10,2) DEFAULT NULL,
    mwst_satz DECIMAL(4,2) DEFAULT 19.00,
    bild VARCHAR(500) DEFAULT NULL,
    lagerbestand INT DEFAULT 0,
    gewicht DECIMAL(10,3) DEFAULT NULL,
    aktiv TINYINT(1) DEFAULT 1,
    erstellt DATETIME DEFAULT CURRENT_TIMESTAMP,
    aktualisiert DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kategorie (kategorie_id),
    INDEX idx_artikelnr (artikelnr),
    INDEX idx_aktiv (aktiv),
    UNIQUE KEY uk_artikelnr (artikelnr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- KUNDEN
-- =====================================================
DROP TABLE IF EXISTS kunden;
CREATE TABLE kunden (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    passwort VARCHAR(255) DEFAULT NULL,
    anrede VARCHAR(50) DEFAULT NULL,
    vorname VARCHAR(100) NOT NULL,
    nachname VARCHAR(100) NOT NULL,
    firma VARCHAR(255) DEFAULT NULL,
    ustid VARCHAR(50) DEFAULT NULL,
    telefon VARCHAR(50) DEFAULT NULL,
    aktiv TINYINT(1) DEFAULT 1,
    newsletter TINYINT(1) DEFAULT 0,
    erstellt DATETIME DEFAULT CURRENT_TIMESTAMP,
    letzter_login DATETIME DEFAULT NULL,
    INDEX idx_email (email),
    UNIQUE KEY uk_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- ADRESSEN
-- =====================================================
DROP TABLE IF EXISTS adressen;
CREATE TABLE adressen (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kunde_id INT UNSIGNED NOT NULL,
    typ ENUM('rechnung', 'lieferung', 'beide') DEFAULT 'beide',
    anrede VARCHAR(50) DEFAULT NULL,
    vorname VARCHAR(100) NOT NULL,
    nachname VARCHAR(100) NOT NULL,
    firma VARCHAR(255) DEFAULT NULL,
    strasse VARCHAR(255) NOT NULL,
    plz VARCHAR(20) NOT NULL,
    ort VARCHAR(100) NOT NULL,
    land VARCHAR(100) DEFAULT 'Deutschland',
    land_code VARCHAR(2) DEFAULT 'DE',
    telefon VARCHAR(50) DEFAULT NULL,
    ist_standard TINYINT(1) DEFAULT 0,
    erstellt DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kunde (kunde_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- BESTELLUNGEN
-- =====================================================
DROP TABLE IF EXISTS bestellungen;
CREATE TABLE bestellungen (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bestellnummer VARCHAR(50) NOT NULL,
    kunde_id INT UNSIGNED DEFAULT NULL,
    
    -- Rechnungsadresse (kopiert, falls Kunde später Adresse ändert)
    re_anrede VARCHAR(50) DEFAULT NULL,
    re_vorname VARCHAR(100) NOT NULL,
    re_nachname VARCHAR(100) NOT NULL,
    re_firma VARCHAR(255) DEFAULT NULL,
    re_strasse VARCHAR(255) NOT NULL,
    re_plz VARCHAR(20) NOT NULL,
    re_ort VARCHAR(100) NOT NULL,
    re_land VARCHAR(100) DEFAULT 'Deutschland',
    re_email VARCHAR(255) NOT NULL,
    re_telefon VARCHAR(50) DEFAULT NULL,
    
    -- Lieferadresse
    li_anrede VARCHAR(50) DEFAULT NULL,
    li_vorname VARCHAR(100) DEFAULT NULL,
    li_nachname VARCHAR(100) DEFAULT NULL,
    li_firma VARCHAR(255) DEFAULT NULL,
    li_strasse VARCHAR(255) DEFAULT NULL,
    li_plz VARCHAR(20) DEFAULT NULL,
    li_ort VARCHAR(100) DEFAULT NULL,
    li_land VARCHAR(100) DEFAULT NULL,
    
    -- Beträge
    summe_netto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    summe_mwst DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    summe_brutto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    versandkosten DECIMAL(10,2) DEFAULT 0.00,
    
    -- Status und Zahlung
    status ENUM('neu', 'bezahlt', 'versendet', 'abgeschlossen', 'storniert') DEFAULT 'neu',
    zahlart ENUM('rechnung', 'vorkasse', 'stripe', 'paypal') NOT NULL,
    zahlstatus ENUM('offen', 'bezahlt', 'teilbezahlt', 'erstattet') DEFAULT 'offen',
    stripe_payment_id VARCHAR(255) DEFAULT NULL,
    
    -- Zeitstempel
    bestellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
    bezahlt_am DATETIME DEFAULT NULL,
    versendet_am DATETIME DEFAULT NULL,
    
    -- Sonstiges
    kundenkommentar TEXT DEFAULT NULL,
    interner_kommentar TEXT DEFAULT NULL,
    
    INDEX idx_kunde (kunde_id),
    INDEX idx_status (status),
    INDEX idx_zahlstatus (zahlstatus),
    INDEX idx_datum (bestellt_am),
    UNIQUE KEY uk_bestellnummer (bestellnummer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- BESTELLPOSITIONEN
-- =====================================================
DROP TABLE IF EXISTS bestellpositionen;
CREATE TABLE bestellpositionen (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bestellung_id INT UNSIGNED NOT NULL,
    produkt_id INT UNSIGNED DEFAULT NULL,
    artikelnr VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    menge INT UNSIGNED NOT NULL DEFAULT 1,
    einzelpreis DECIMAL(10,2) NOT NULL,
    mwst_satz DECIMAL(4,2) DEFAULT 19.00,
    gesamtpreis DECIMAL(10,2) NOT NULL,
    INDEX idx_bestellung (bestellung_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- UUID MAPPING (für Reimport aus Shopware)
-- =====================================================
DROP TABLE IF EXISTS uuid_mapping;
CREATE TABLE uuid_mapping (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tabelle VARCHAR(50) NOT NULL,
    neue_id INT UNSIGNED NOT NULL,
    shopware_uuid VARCHAR(32) NOT NULL,
    INDEX idx_tabelle_uuid (tabelle, shopware_uuid),
    UNIQUE KEY uk_mapping (tabelle, shopware_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- IMPORT STATUS (für Batch-Import)
-- =====================================================
DROP TABLE IF EXISTS import_status;
CREATE TABLE import_status (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    datei VARCHAR(255) NOT NULL,
    position BIGINT UNSIGNED DEFAULT 0,
    zeilen_verarbeitet INT UNSIGNED DEFAULT 0,
    status ENUM('laeuft', 'fertig', 'fehler') DEFAULT 'laeuft',
    gestartet DATETIME DEFAULT CURRENT_TIMESTAMP,
    aktualisiert DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fehler_meldung TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- HINWEIS: Dieses Schema später erweitern um:
-- - produktbilder (mehrere Bilder pro Produkt)
-- - produktvarianten (Größen, Farben etc.)
-- - versandarten
-- - gutscheine
-- =====================================================
