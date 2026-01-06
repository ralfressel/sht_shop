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

// Produkte laden - nur Hauptprodukte (ohne Varianten)
$where = "WHERE aktiv = 1 AND parent_id IS NULL";
if ($kategorie_id > 0) {
    $where .= " AND kategorie_id = $kategorie_id";
}

$sql = "SELECT * FROM produkte $where ORDER BY name LIMIT $offset, $pro_seite";
$produkte = db_fetch_all($sql);

// Gesamtanzahl für Pagination
$sql_count = "SELECT COUNT(*) as total FROM produkte $where";
$total = db_fetch_row($sql_count)['total'];
$seiten_gesamt = ceil($total / $pro_seite);

// Kategorien für Navigation (hierarchisch)
$alle_kategorien = db_fetch_all("SELECT * FROM kategorien WHERE aktiv = 1 ORDER BY sortierung, name");

// Baue hierarchische Struktur
$kategorien_by_id = [];
$hauptkategorien = [];
foreach ($alle_kategorien as $kat) {
    $kategorien_by_id[$kat['id']] = $kat;
    if (empty($kat['parent_id'])) {
        $hauptkategorien[] = $kat;
    }
}

// Funktion um Unterkategorien zu holen
function getUnterkategorien($parent_id, $alle_kategorien) {
    $children = [];
    foreach ($alle_kategorien as $kat) {
        if ($kat['parent_id'] == $parent_id) {
            $children[] = $kat;
        }
    }
    return $children;
}

// Aktuelle Kategorie Name für Titel
$aktuelle_kategorie_name = 'Alle Produkte';
if ($kategorie_id > 0 && isset($kategorien_by_id[$kategorie_id])) {
    $aktuelle_kategorie_name = $kategorien_by_id[$kategorie_id]['name'];
}

$page_title = $aktuelle_kategorie_name . ' - SHT Hebetechnik';
require_once 'header.inc.php';
?>

<style>
    /* Layout */
    .flex { display: flex; gap: 2rem; }
    
    /* Sidebar */
    .sidebar { width: 280px; flex-shrink: 0; }
    .sidebar h3 { 
        margin-bottom: 0.75rem; 
        color: #003366; 
        font-size: 1.1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #003366;
    }
    .sidebar ul { list-style: none; }
    .sidebar > ul > li { margin: 0.2rem 0; border-bottom: 1px solid #eee; }
    .sidebar a { color: #333; text-decoration: none; display: block; padding: 0.5rem 0; transition: color 0.2s; }
    .sidebar a:hover { color: #0066cc; }
    .sidebar a.active { font-weight: bold; color: #003366; }
    
    /* Klappbares Menü */
    .kat-header { display: flex; align-items: center; justify-content: space-between; cursor: pointer; }
    .kat-header a { flex: 1; }
    .kat-toggle { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; color: #666; font-size: 0.8rem; transition: transform 0.2s; }
    .kat-toggle.open { transform: rotate(90deg); }
    .kat-submenu { display: none; margin-left: 1rem; padding-left: 0.5rem; border-left: 2px solid #ddd; margin-top: 0.3rem; margin-bottom: 0.3rem; }
    .kat-submenu.open { display: block; }
    .kat-submenu li { margin: 0.2rem 0; }
    .kat-submenu a { padding: 0.3rem 0; font-size: 0.95em; }
    .kat-submenu .kat-submenu { font-size: 0.9em; }
    
    /* Produkte Grid */
    .produkte { flex: 1; }
    .produkte-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem; }
    
    /* Produkt-Karte */
    .produkt-karte { 
        border: 1px solid #e0e0e0; 
        border-radius: 8px; 
        overflow: hidden; 
        transition: box-shadow 0.3s, transform 0.2s;
        background: white;
    }
    .produkt-karte:hover { 
        box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
        transform: translateY(-2px);
    }
    .produkt-karte img { width: 100%; height: 200px; object-fit: contain; background: #f9f9f9; }
    .produkt-karte .info { padding: 1rem; }
    .produkt-karte h3 { font-size: 0.95rem; margin-bottom: 0.5rem; color: #003366; line-height: 1.3; }
    .produkt-karte .artikelnr { color: #666; font-size: 0.8rem; }
    .produkt-karte .preis { font-size: 1.25rem; font-weight: bold; color: #333; margin: 0.75rem 0; }
    .produkt-karte .preis small { font-size: 0.75rem; font-weight: normal; color: #666; }
    .produkt-karte .btn { width: 100%; text-align: center; }
    
    /* Pagination */
    .pagination { margin-top: 2rem; text-align: center; }
    .pagination a, .pagination span { display: inline-block; padding: 0.5rem 1rem; margin: 0 0.2rem; border: 1px solid #ddd; text-decoration: none; color: #333; border-radius: 4px; }
    .pagination a:hover { background: #f0f4f8; border-color: #003366; }
    .pagination .current { background: #003366; color: white; border-color: #003366; }
    
    /* Hinweis wenn leer */
    .hinweis { padding: 2rem; background: #f9f9f9; text-align: center; border-radius: 8px; }
    
    /* Sortierung */
    .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .toolbar select { padding: 0.5rem 1rem; border: 1px solid #ddd; border-radius: 4px; }
    
    @media (max-width: 768px) {
        .flex { flex-direction: column; }
        .sidebar { width: 100%; }
    }
</style>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <a href="index.php">Startseite</a>
        <?php 
        if ($kategorie_id > 0):
            // Breadcrumb-Pfad aufbauen
            $pfad = [];
            $check = $kategorien_by_id[$kategorie_id] ?? null;
            while ($check) {
                array_unshift($pfad, $check);
                $check = $check['parent_id'] ? ($kategorien_by_id[$check['parent_id']] ?? null) : null;
            }
            foreach ($pfad as $idx => $p): ?>
            <span class="separator">&gt;</span>
            <?php if ($idx == count($pfad) - 1): ?>
            <span class="current"><?= htmlspecialchars($p['name']) ?></span>
            <?php else: ?>
            <a href="index.php?kat=<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></a>
            <?php endif; ?>
        <?php endforeach;
        endif; ?>
    </div>
</div>

<!-- Category Banner -->
<div class="category-banner">
    <div class="container">
        <div class="category-info">
            <div class="category-text">
                <h1><?= htmlspecialchars($aktuelle_kategorie_name) ?></h1>
                <?php if ($kategorie_id > 0 && isset($kategorien_by_id[$kategorie_id]['beschreibung'])): ?>
                <p><?= htmlspecialchars($kategorien_by_id[$kategorie_id]['beschreibung']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container main-content">
    <div class="flex">
        
        <!-- Sidebar mit Kategorien -->
        <aside class="sidebar">
            <h3>Kategorien</h3>
            <ul>
                <li><a href="index.php" <?= $kategorie_id == 0 ? 'class="active"' : '' ?>>Alle Produkte</a></li>
                <?php 
                // Finde aktive Kategorie-Pfad für Auto-Expand
                $active_path = [];
                if ($kategorie_id > 0) {
                    $check_id = $kategorie_id;
                    while ($check_id) {
                        $active_path[] = $check_id;
                        $check_id = $kategorien_by_id[$check_id]['parent_id'] ?? 0;
                    }
                }
                
                // Rekursive Funktion zum Rendern
                function renderKategorien($kategorien, $alle_kategorien, $kategorie_id, $active_path, $level = 0) {
                    foreach ($kategorien as $kat): 
                        $children = getUnterkategorien($kat['id'], $alle_kategorien);
                        $has_children = !empty($children);
                        $is_active = ($kat['id'] == $kategorie_id);
                        $is_in_path = in_array($kat['id'], $active_path);
                        $is_open = $is_in_path || $is_active;
                    ?>
                    <li>
                        <div class="kat-header">
                            <a href="index.php?kat=<?= $kat['id'] ?>" <?= $is_active ? 'class="active"' : '' ?>>
                                <?= htmlspecialchars($kat['name']) ?>
                            </a>
                            <?php if ($has_children): ?>
                            <span class="kat-toggle <?= $is_open ? 'open' : '' ?>" onclick="toggleKat(this)">▶</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($has_children): ?>
                        <ul class="kat-submenu <?= $is_open ? 'open' : '' ?>">
                            <?php renderKategorien($children, $alle_kategorien, $kategorie_id, $active_path, $level + 1); ?>
                        </ul>
                        <?php endif; ?>
                    </li>
                    <?php endforeach;
                }
                
                renderKategorien($hauptkategorien, $alle_kategorien, $kategorie_id, $active_path);
                ?>
            </ul>
        </aside>
        
        <!-- Produkte -->
        <main class="produkte">
            
            <?php if (count($produkte) > 0): ?>
            
            <p style="margin-bottom: 1rem; color: #666;"><?= $total ?> Produkte gefunden</p>
            
            <div class="produkte-grid">
                <?php foreach ($produkte as $prod): ?>
                <div class="produkt-karte">
                    <a href="produkt.php?id=<?= $prod['id'] ?>">
                        <?php if ($prod['bild']): ?>
                            <img src="<?= htmlspecialchars($prod['bild']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>">
                        <?php else: ?>
                            <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 400'><rect fill='%23f5f5f5' width='400' height='400'/><text x='200' y='210' text-anchor='middle' fill='%23999' font-size='16'>Kein Bild</text></svg>" alt="">
                        <?php endif; ?>
                    </a>
                    <div class="info">
                        <p class="artikelnr">Art.-Nr.: <?= htmlspecialchars($prod['artikelnummer']) ?></p>
                        <h3><a href="produkt.php?id=<?= $prod['id'] ?>" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($prod['name']) ?></a></h3>
                        <p class="preis">
                            <?= number_format($prod['preis'], 2, ',', '.') ?> €<small>*</small>
                        </p>
                        <a href="produkt.php?id=<?= $prod['id'] ?>" class="btn btn-primary">Details</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($seiten_gesamt > 1): ?>
            <div class="pagination">
                <?php if ($seite > 1): ?>
                    <a href="?kat=<?= $kategorie_id ?>&seite=<?= $seite - 1 ?>">« Zurück</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $seite - 2); $i <= min($seiten_gesamt, $seite + 2); $i++): ?>
                    <?php if ($i == $seite): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?kat=<?= $kategorie_id ?>&seite=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($seite < $seiten_gesamt): ?>
                    <a href="?kat=<?= $kategorie_id ?>&seite=<?= $seite + 1 ?>">Weiter »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <p style="margin-top: 1.5rem; font-size: 0.8rem; color: #666;">* Alle Preise inkl. MwSt., zzgl. Versand</p>
            
            <?php else: ?>
            <div class="hinweis">
                <p>In dieser Kategorie sind derzeit keine Produkte vorhanden.</p>
                <p><a href="index.php">Zurück zur Startseite</a></p>
            </div>
            <?php endif; ?>
            
        </main>
        
    </div>
</div>

<script>
function toggleKat(el) {
    el.classList.toggle('open');
    const submenu = el.parentElement.nextElementSibling;
    if (submenu) {
        submenu.classList.toggle('open');
    }
}
</script>

<?php require_once 'footer.inc.php'; ?>
