<?php
/**
 * SHT Shop - Startseite / Produktübersicht
 */

require_once 'opendb.inc.php';

// Kategorie-Filter
$kategorie_id = isset($_GET['kat']) ? (int)$_GET['kat'] : 0;
$seite = isset($_GET['seite']) ? max(1, (int)$_GET['seite']) : 1;
$pro_seite = 24;
$offset = ($seite - 1) * $pro_seite;

// Produkte laden
$where = "WHERE aktiv = 1";
if ($kategorie_id > 0) {
    $where .= " AND kategorie_id = $kategorie_id";
}

$sql = "SELECT * FROM produkte $where ORDER BY name LIMIT $offset, $pro_seite";
$produkte = db_fetch_all($sql);

// Gesamtanzahl für Pagination
$sql_count = "SELECT COUNT(*) as total FROM produkte $where";
$total = db_fetch_row($sql_count)['total'];
$seiten_gesamt = ceil($total / $pro_seite);

// Kategorien für Navigation
$kategorien = db_fetch_all("SELECT * FROM kategorien WHERE aktiv = 1 ORDER BY sortierung, name");
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>SHT Hebetechnik - Shop</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        
        /* Header */
        header { background: #2c3e50; color: white; padding: 1rem; }
        header h1 { font-size: 1.5rem; }
        nav { background: #34495e; padding: 0.5rem 1rem; }
        nav a { color: white; text-decoration: none; margin-right: 1rem; }
        nav a:hover { text-decoration: underline; }
        
        /* Layout */
        .container { max-width: 1200px; margin: 0 auto; padding: 1rem; }
        .flex { display: flex; gap: 2rem; }
        
        /* Sidebar */
        .sidebar { width: 250px; flex-shrink: 0; }
        .sidebar h3 { margin-bottom: 0.5rem; border-bottom: 2px solid #2c3e50; padding-bottom: 0.5rem; }
        .sidebar ul { list-style: none; }
        .sidebar li { margin: 0.3rem 0; }
        .sidebar a { color: #2c3e50; text-decoration: none; }
        .sidebar a:hover { color: #e74c3c; }
        .sidebar a.active { font-weight: bold; color: #e74c3c; }
        
        /* Produkte Grid */
        .produkte { flex: 1; }
        .produkte-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem; }
        
        /* Produkt-Karte */
        .produkt-karte { border: 1px solid #ddd; border-radius: 8px; overflow: hidden; transition: box-shadow 0.3s; }
        .produkt-karte:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .produkt-karte img { width: 100%; height: 200px; object-fit: contain; background: #f9f9f9; }
        .produkt-karte .info { padding: 1rem; }
        .produkt-karte h3 { font-size: 1rem; margin-bottom: 0.5rem; }
        .produkt-karte .artikelnr { color: #666; font-size: 0.85rem; }
        .produkt-karte .preis { font-size: 1.25rem; font-weight: bold; color: #e74c3c; margin: 0.5rem 0; }
        .produkt-karte .btn { display: inline-block; background: #2c3e50; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; }
        .produkt-karte .btn:hover { background: #e74c3c; }
        
        /* Pagination */
        .pagination { margin-top: 2rem; text-align: center; }
        .pagination a, .pagination span { display: inline-block; padding: 0.5rem 1rem; margin: 0 0.2rem; border: 1px solid #ddd; text-decoration: none; color: #333; }
        .pagination a:hover { background: #f0f0f0; }
        .pagination .current { background: #2c3e50; color: white; border-color: #2c3e50; }
        
        /* Hinweis wenn leer */
        .hinweis { padding: 2rem; background: #f9f9f9; text-align: center; border-radius: 8px; }
        
        /* Footer */
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
    <div class="flex">
        
        <!-- Sidebar mit Kategorien -->
        <aside class="sidebar">
            <h3>Kategorien</h3>
            <ul>
                <li><a href="index.php" <?= $kategorie_id == 0 ? 'class="active"' : '' ?>>Alle Produkte</a></li>
                <?php foreach ($kategorien as $kat): ?>
                    <li>
                        <a href="index.php?kat=<?= $kat['id'] ?>" <?= $kategorie_id == $kat['id'] ? 'class="active"' : '' ?>>
                            <?= htmlspecialchars($kat['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>
        
        <!-- Produkte -->
        <main class="produkte">
            <h2>
                <?php if ($kategorie_id > 0): ?>
                    <?php 
                    $aktuelle_kat = db_fetch_row("SELECT name FROM kategorien WHERE id = $kategorie_id");
                    echo htmlspecialchars($aktuelle_kat['name'] ?? 'Kategorie');
                    ?>
                <?php else: ?>
                    Alle Produkte
                <?php endif; ?>
                <small style="font-weight:normal; color:#666;">(<?= $total ?> Artikel)</small>
            </h2>
            
            <?php if (count($produkte) > 0): ?>
                <div class="produkte-grid" style="margin-top: 1rem;">
                    <?php foreach ($produkte as $p): ?>
                        <div class="produkt-karte">
                            <?php if ($p['bild']): ?>
                                <img src="<?= htmlspecialchars($p['bild']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                            <?php else: ?>
                                <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 200'><rect fill='%23f0f0f0' width='200' height='200'/><text x='100' y='105' text-anchor='middle' fill='%23999' font-size='14'>Kein Bild</text></svg>" alt="Kein Bild">
                            <?php endif; ?>
                            <div class="info">
                                <p class="artikelnr">Art.-Nr.: <?= htmlspecialchars($p['artikelnr']) ?></p>
                                <h3><?= htmlspecialchars($p['name']) ?></h3>
                                <p class="preis"><?= number_format($p['preis'], 2, ',', '.') ?> €</p>
                                <a href="produkt.php?id=<?= $p['id'] ?>" class="btn">Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($seiten_gesamt > 1): ?>
                    <div class="pagination">
                        <?php if ($seite > 1): ?>
                            <a href="?<?= $kategorie_id ? "kat=$kategorie_id&" : '' ?>seite=<?= $seite - 1 ?>">« Zurück</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $seiten_gesamt; $i++): ?>
                            <?php if ($i == $seite): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?<?= $kategorie_id ? "kat=$kategorie_id&" : '' ?>seite=<?= $i ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($seite < $seiten_gesamt): ?>
                            <a href="?<?= $kategorie_id ? "kat=$kategorie_id&" : '' ?>seite=<?= $seite + 1 ?>">Weiter »</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="hinweis">
                    <p>Keine Produkte gefunden.</p>
                    <p style="margin-top:1rem; color:#666;">
                        Falls die Datenbank leer ist, bitte zuerst:
                        <br>1. <code>schema.sql</code> auf IONOS importieren
                        <br>2. <code>import.php</code> ausführen
                    </p>
                </div>
            <?php endif; ?>
        </main>
        
    </div>
</div>

<footer>
    <p>&copy; <?= date('Y') ?> SHT Hebetechnik Suhl</p>
    <p style="margin-top:0.5rem; font-size:0.85rem; opacity:0.7;">Entwicklungsmodus - nicht für Suchmaschinen freigegeben</p>
</footer>

</body>
</html>
