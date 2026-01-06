<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <title><?= $page_title ?? 'SHT Hebetechnik - Shop' ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            line-height: 1.6; 
            color: #333;
            min-height: 100vh;
        }
        
        /* Top Service Bar */
        .top-bar {
            background: #003366;
            color: white;
            font-size: 0.8rem;
            padding: 0.4rem 0;
        }
        .top-bar .container {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .top-bar-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            white-space: nowrap;
        }
        .top-bar-item svg { width: 14px; height: 14px; fill: currentColor; }
        
        /* Header */
        .main-header {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 0.75rem 0;
        }
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
        }
        .logo img {
            height: 50px;
            width: auto;
        }
        
        /* Navigation */
        .main-nav {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .main-nav a {
            color: #003366;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .main-nav a:hover {
            background: #f0f4f8;
            color: #0056b3;
        }
        .main-nav a.active {
            background: #003366;
            color: white;
        }
        
        /* Header Icons */
        .header-icons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .header-icon {
            color: #003366;
            font-size: 1.5rem;
            text-decoration: none;
        }
        .header-icon:hover { opacity: 0.7; }
        
        /* Page Header with Background */
        .page-header {
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e4ec 100%);
            position: relative;
            padding: 1.5rem 0;
            overflow: hidden;
        }
        .page-header::after {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 40%;
            background: url('sht_background.jpg') right center no-repeat;
            background-size: cover;
            opacity: 0.6;
        }
        .page-header-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .page-header-left { flex: 1; }
        .page-header-right {
            flex-shrink: 0;
            margin-left: 2rem;
        }
        .page-header-right img {
            height: 60px;
            width: auto;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            color: #003366;
            font-size: 0.85rem;
            margin-bottom: 0.75rem;
        }
        .breadcrumb a {
            color: #0066cc;
            text-decoration: none;
            font-weight: 500;
        }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #666; margin: 0 0.3rem; }
        
        /* Page Title */
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #003366;
            margin: 0;
        }
        
        /* Container */
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 0 1rem; 
        }
        .main-content {
            padding: 1.5rem 0;
        }
        
        /* Footer */
        footer { 
            background: #003366; 
            color: white; 
            padding: 2rem 1rem; 
            margin-top: 3rem; 
            text-align: center; 
        }
        footer a { color: #7eb8e7; }
        
        /* Buttons */
        .btn { 
            display: inline-block; 
            padding: 0.6rem 1.25rem; 
            text-decoration: none; 
            border-radius: 4px; 
            border: none; 
            cursor: pointer; 
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-primary { background: #003366; color: white; }
        .btn-primary:hover { background: #004080; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-outline { background: white; border: 1px solid #003366; color: #003366; }
        .btn-outline:hover { background: #003366; color: white; }
        
        /* Meldungen */
        .meldung { padding: 1rem; margin-bottom: 1rem; border-radius: 4px; }
        .meldung.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .meldung.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .meldung.info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .top-bar .container { font-size: 0.7rem; gap: 1rem; }
            .header-content { flex-wrap: wrap; }
            .main-nav { justify-content: center; width: 100%; }
            .page-header::after { width: 30%; opacity: 0.3; }
            .page-title { font-size: 1.3rem; }
        }
        
        /* Kontakt-Sidebar */
        .kontakt-sidebar {
            position: fixed;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1000;
            display: flex;
            align-items: stretch;
        }
        
        .kontakt-tab {
            background: linear-gradient(180deg, #004080 0%, #002855 100%);
            color: white;
            padding: 1rem 0.4rem;
            cursor: pointer;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 1px;
            border-radius: 0 8px 8px 0;
            box-shadow: 2px 2px 8px rgba(0,0,0,0.2);
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .kontakt-tab:hover {
            background: linear-gradient(180deg, #0055a5 0%, #003366 100%);
        }
        
        .kontakt-panel {
            background: white;
            width: 280px;
            padding: 1.5rem;
            box-shadow: 3px 0 15px rgba(0,0,0,0.15);
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            border-radius: 0 8px 8px 0;
        }
        
        .kontakt-sidebar.open .kontakt-panel {
            transform: translateX(0);
        }
        
        .kontakt-sidebar.open .kontakt-tab {
            border-radius: 0;
        }
        
        .kontakt-panel h4 {
            color: #003366;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .kontakt-panel .kontakt-phone {
            font-size: 1.3rem;
            font-weight: 700;
            color: #003366;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        
        .kontakt-panel .kontakt-email {
            text-align: center;
            margin-bottom: 0.5rem;
        }
        
        .kontakt-panel .kontakt-email a {
            color: #0066cc;
            text-decoration: none;
        }
        
        .kontakt-panel .kontakt-email a:hover {
            text-decoration: underline;
        }
        
        .kontakt-panel .kontakt-whatsapp {
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .kontakt-panel .kontakt-whatsapp a {
            color: #25D366;
            text-decoration: none;
            font-weight: 500;
        }
        
        .kontakt-panel .kontakt-hours {
            text-align: center;
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .kontakt-panel .kontakt-form-link {
            text-align: center;
            font-size: 0.9rem;
        }
        
        .kontakt-panel .kontakt-form-link a {
            color: #003366;
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .kontakt-sidebar {
                top: auto;
                bottom: 0;
                left: 0;
                right: 0;
                transform: none;
                flex-direction: column-reverse;
            }
            .kontakt-tab {
                writing-mode: horizontal-tb;
                border-radius: 8px 8px 0 0;
                padding: 0.5rem 1rem;
            }
            .kontakt-panel {
                width: 100%;
                transform: translateY(100%);
                border-radius: 8px 8px 0 0;
            }
            .kontakt-sidebar.open .kontakt-panel {
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>

<!-- Kontakt-Sidebar -->
<div class="kontakt-sidebar" id="kontaktSidebar">
    <div class="kontakt-panel">
        <h4>Unterstützung und Beratung unter:</h4>
        <div class="kontakt-phone">03681 / 454266-20</div>
        <div class="kontakt-email">
            <a href="mailto:info@sht-hebezeuge.de">info@sht-hebezeuge.de</a>
        </div>
        <div class="kontakt-whatsapp">
            <a href="https://wa.me/4936814542662" target="_blank">Whatsapp-Beratung</a>
        </div>
        <div class="kontakt-hours">
            Mo-Do 06:30 - 16:00 Uhr<br>
            Fr 6:30 - 12:00 Uhr
        </div>
        <div class="kontakt-form-link">
            Oder über unser <a href="#">Kontaktformular</a>.
        </div>
    </div>
    <div class="kontakt-tab" onclick="toggleKontakt()">Kontakt</div>
</div>

<script>
function toggleKontakt() {
    document.getElementById('kontaktSidebar').classList.toggle('open');
}
</script>

<!-- Top Service Bar -->
<div class="top-bar">
    <div class="container">
        <div class="top-bar-item">
            <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
            Schneller Versand
        </div>
        <div class="top-bar-item">
            <svg viewBox="0 0 24 24"><path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/></svg>
            Prüfung, Wartung &amp; Reparatur
        </div>
        <div class="top-bar-item">
            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            über 15.000 Kunden vertrauen uns
        </div>
        <div class="top-bar-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
            Bequemer Kauf auf Rechnung
        </div>
        <div class="top-bar-item">
            <svg viewBox="0 0 24 24"><path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/></svg>
            03681 454266-20
        </div>
    </div>
</div>

<!-- Main Header -->
<header class="main-header">
    <div class="container">
        <div class="header-content">
            <a href="index.php" class="logo">
                <img src="sht_logo.jpg" alt="SHT Hebetechnik">
            </a>
            
            <nav class="main-nav">
                <a href="index.php"<?= basename($_SERVER['PHP_SELF']) == 'index.php' && empty($_GET['kat']) ? ' class="active"' : '' ?>>Home</a>
                <a href="index.php?kat=1">Anschlagmittel</a>
                <a href="index.php?kat=2">Hebezeuge</a>
                <a href="index.php?kat=3">Ladungssicherung</a>
            </nav>
            
            <div class="header-icons">
                <a href="#" class="header-icon" title="Suchen">🔍</a>
                <a href="warenkorb.php" class="header-icon" title="Warenkorb">🛒</a>
            </div>
        </div>
    </div>
</header>
