<?php
// eleve/dashboard.php - Tableau de bord élève
session_start();

// Vérifier si l'élève est connecté
if (!isset($_SESSION['eleve_id'])) {
    header('Location: ../login.php');
    exit();
}

// ===== CHEMIN CORRIGÉ =====
require_once __DIR__ . '/../config/database.php';

$db = db();
$eleve_id = $_SESSION['eleve_id'];

// Récupérer les informations de l'élève
$stmt = $db->prepare("SELECT * FROM eleves WHERE idEleve = :id");
$stmt->execute(['id' => $eleve_id]);
$eleve = $stmt->fetch();

// Récupérer l'inscription en cours
$stmt = $db->prepare("
    SELECT i.*, c.nomClasse, n.nomNiveau, a.libelle as annee
    FROM inscriptions i
    JOIN classes c ON i.idClasse = c.idClasse
    JOIN niveaux n ON c.idNiveau = n.idNiveau
    JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
    WHERE i.idEleve = :idEleve 
    AND i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
    LIMIT 1
");
$stmt->execute(['idEleve' => $eleve_id]);
$inscription = $stmt->fetch();

// Récupérer les paiements
$stmt = $db->prepare("
    SELECT p.*, f.libelle as frais_libelle
    FROM paiements p
    JOIN frais f ON p.idFrais = f.idFrais
    JOIN inscriptions i ON p.idInscription = i.idInscription
    WHERE i.idEleve = :idEleve
    ORDER BY p.datePaiement DESC
    LIMIT 5
");
$stmt->execute(['idEleve' => $eleve_id]);
$paiements = $stmt->fetchAll();

// Récupérer tous les reçus (avec numéro)
$stmt = $db->prepare("
    SELECT p.*, f.libelle as frais_libelle
    FROM paiements p
    JOIN frais f ON p.idFrais = f.idFrais
    JOIN inscriptions i ON p.idInscription = i.idInscription
    WHERE i.idEleve = :idEleve
    AND p.numeroRecu IS NOT NULL
    ORDER BY p.datePaiement DESC
");
$stmt->execute(['idEleve' => $eleve_id]);
$recus = $stmt->fetchAll();

// Statistiques
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_paiements,
        SUM(montantPaye) as total_montant
    FROM paiements p
    JOIN inscriptions i ON p.idInscription = i.idInscription
    WHERE i.idEleve = :idEleve
");
$stmt->execute(['idEleve' => $eleve_id]);
$stats = $stmt->fetch();

// Dernière année scolaire
$stmt = $db->query("SELECT * FROM annees_scolaires WHERE active = 1");
$annee_active = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace - Élève</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f4f8;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: linear-gradient(180deg, #1a202c 0%, #2d3748 100%);
            padding: 20px 15px;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            color: white;
            transition: transform 0.3s ease-in-out;
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar::-webkit-scrollbar { width: 3px; }
        .sidebar::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 4px; }

        .sidebar .logo {
            font-size: 1.1rem;
            font-weight: 700;
            padding-bottom: 18px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar .logo i { color: #667eea; font-size: 1.3rem; }
        .sidebar .logo span { color: #667eea; }

        .sidebar .menu { list-style: none; padding: 0; margin: 0; }
        .sidebar .menu .menu-section {
            font-size: 0.6rem;
            text-transform: uppercase;
            color: #a0aec0;
            opacity: 0.4;
            padding: 12px 14px 6px;
            letter-spacing: 0.8px;
        }
        .sidebar .menu li { margin-bottom: 2px; }
        .sidebar .menu li a {
            color: #a0aec0;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: 0.3s;
            font-size: 0.85rem;
        }
        .sidebar .menu li a:hover { background: rgba(255,255,255,0.08); color: white; }
        .sidebar .menu li a.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102,126,234,0.4);
        }
        .sidebar .menu li a i { width: 18px; text-align: center; font-size: 0.9rem; }

        .sidebar .footer {
            border-top: 1px solid rgba(255,255,255,0.06);
            padding-top: 16px;
            margin-top: 16px;
        }
        .sidebar .footer .user {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar .footer .user .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }
        .sidebar .footer .user .name {
            font-size: 0.85rem;
            font-weight: 600;
        }
        .sidebar .footer .user .matricule {
            font-size: 0.7rem;
            color: #a0aec0;
        }
        .sidebar .footer .btn-logout {
            background: rgba(255,255,255,0.08);
            color: #fc8181;
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
            width: 100%;
            transition: 0.3s;
            font-size: 0.85rem;
        }
        .sidebar .footer .btn-logout:hover {
            background: rgba(255,255,255,0.15);
            color: #feb2b2;
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
            transition: opacity 0.3s ease;
        }
        .sidebar-overlay.active { display: block; }

        .hamburger-btn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 1001;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(102,126,234,0.4);
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .hamburger-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(102,126,234,0.5);
        }

        .main-content {
            margin-left: 260px;
            padding: 20px 25px;
            min-height: 100vh;
            max-height: 100vh;
            overflow-y: auto;
        }

        .page-header {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .page-header h2 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1a202c;
            margin: 0;
        }
        .page-header h2 i { color: #667eea; margin-right: 10px; }
        .page-header .badge {
            background: #ebf8ff;
            color: #2b6cb0;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .page-header .logout-header { display: none; }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 14px 18px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 3px solid #667eea;
        }
        .stat-card .number { font-size: 1.3rem; font-weight: 700; color: #1a202c; }
        .stat-card .label { font-size: 0.7rem; color: #718096; margin-top: 2px; }
        .stat-card.green { border-color: #48bb78; }
        .stat-card.orange { border-color: #ed8936; }
        .stat-card.red { border-color: #fc8181; }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .info-card {
            background: white;
            padding: 16px 18px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .info-card .label {
            font-size: 0.65rem;
            color: #a0aec0;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .info-card .value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1a202c;
            margin-top: 4px;
        }
        .info-card .value small { font-weight: 400; color: #718096; font-size: 0.8rem; }

        .widget {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .widget .widget-header {
            padding: 12px 18px;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .widget .widget-header .title {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .widget .widget-header .title i { color: #667eea; margin-right: 8px; }
        .widget .widget-header .badge-count {
            background: #ebf8ff;
            color: #2b6cb0;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        .widget table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .widget th {
            background: #f7fafc;
            padding: 8px 14px;
            text-align: left;
            font-size: 0.65rem;
            text-transform: uppercase;
            color: #4a5568;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #edf2f7;
        }
        .widget td {
            padding: 8px 14px;
            border-bottom: 1px solid #f0f2f5;
        }
        .widget tr:hover td { background: #f7fafc; }
        .widget .empty {
            text-align: center;
            padding: 25px;
            color: #a0aec0;
        }
        .widget .empty i { font-size: 1.5rem; display: block; margin-bottom: 8px; opacity: 0.3; }

        .badge-recu {
            background: #ebf8ff;
            color: #2b6cb0;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }

        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.open { transform: translateX(0); }
            .hamburger-btn { display: flex !important; }
            .main-content {
                margin-left: 0;
                padding: 15px;
                max-height: none;
            }
            .page-header {
                flex-wrap: wrap;
                gap: 10px;
            }
            .page-header .logout-header { display: inline-block; }
            .stats { grid-template-columns: repeat(2, 1fr); }
            .info-grid { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 576px) {
            .main-content { padding: 10px; }
            .page-header h2 { font-size: 1rem; }
            .stats { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 10px 12px; }
            .stat-card .number { font-size: 1rem; }
            .info-grid { grid-template-columns: 1fr; }
            .widget table { font-size: 0.7rem; }
            .widget th, .widget td { padding: 4px 8px; }
            .hamburger-btn {
                width: 42px;
                height: 42px;
                font-size: 0.9rem;
                bottom: 15px;
                left: 15px;
            }
        }

        @media (min-width: 992px) {
            .hamburger-btn { display: none !important; }
            .sidebar-overlay { display: none !important; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<button class="hamburger-btn" id="hamburgerBtn" title="Menu">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar" id="sidebar">
    <div class="logo">
        <i class="fas fa-school"></i>
        Gestion<span>Scolaire</span>
    </div>
    <ul class="menu">
        <li class="menu-section">Navigation</li>
        <li><a href="dashboard.php" class="active"><i class="fas fa-chart-pie"></i> Tableau de bord</a></li>
        <li><a href="paiements/historique.php"><i class="fas fa-money-bill-wave"></i> Mes paiements</a></li>
        <li><a href="recus/index.php"><i class="fas fa-file-invoice"></i> Mes reçus</a></li>
        <li class="menu-section">Compte</li>
        <li><a href="profil.php"><i class="fas fa-user"></i> Mon profil</a></li>
    </ul>
    <div class="footer">
        <div class="user">
            <div class="avatar">
                <?php echo strtoupper(substr($_SESSION['eleve_prenom'] ?? 'E', 0, 1) . substr($_SESSION['eleve_nom'] ?? 'A', 0, 1)); ?>
            </div>
            <div>
                <div class="name"><?php echo $_SESSION['eleve_nom'] . ' ' . $_SESSION['eleve_prenom']; ?></div>
                <div class="matricule"><?php echo $_SESSION['eleve_matricule']; ?></div>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
    </div>
</div>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-user-graduate"></i> Tableau de bord</h2>
        <div>
            <span class="badge"><i class="fas fa-id-card me-1"></i> <?php echo $_SESSION['eleve_matricule']; ?></span>
            <a href="../logout.php" class="btn btn-sm btn-outline-danger ms-2 logout-header" style="border-radius:8px; padding:4px 14px; font-size:0.8rem;">
                <i class="fas fa-sign-out-alt me-1"></i> Quitter
            </a>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="stats">
        <div class="stat-card">
            <div class="number"><?php echo $stats['total_paiements'] ?? 0; ?></div>
            <div class="label"><i class="fas fa-receipt me-1"></i> Total paiements</div>
        </div>
        <div class="stat-card green">
            <div class="number">$<?php echo number_format($stats['total_montant'] ?? 0, 2); ?></div>
            <div class="label"><i class="fas fa-dollar-sign me-1"></i> Total payé</div>
        </div>
        <div class="stat-card <?php echo $inscription ? 'green' : 'red'; ?>">
            <div class="number">
                <?php if ($inscription): ?>
                    <i class="fas fa-check-circle" style="color:#48bb78;"></i> Actif
                <?php else: ?>
                    <i class="fas fa-times-circle" style="color:#e53e3e;"></i> Inactif
                <?php endif; ?>
            </div>
            <div class="label"><i class="fas fa-book-open me-1"></i> Inscription</div>
        </div>
        <div class="stat-card orange">
            <div class="number"><?php echo count($recus); ?></div>
            <div class="label"><i class="fas fa-file-invoice me-1"></i> Reçus disponibles</div>
        </div>
    </div>

    <!-- Informations -->
    <div class="info-grid">
        <div class="info-card">
            <div class="label"><i class="fas fa-user me-1"></i> Identité</div>
            <div class="value">
                <?php echo htmlspecialchars($eleve['nom'] . ' ' . ($eleve['postNom'] ?? '') . ' ' . $eleve['prenom']); ?>
            </div>
            <div style="font-size:0.75rem; color:#718096; margin-top:2px;">
                <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($eleve['telephone'] ?? 'Non renseigné'); ?>
            </div>
        </div>
        <div class="info-card">
            <div class="label"><i class="fas fa-graduation-cap me-1"></i> Scolarité</div>
            <div class="value">
                <?php if ($inscription): ?>
                    <i class="fas fa-check-circle" style="color:#48bb78;"></i>
                    <?php echo htmlspecialchars($inscription['nomClasse']); ?>
                    <small>(<?php echo htmlspecialchars($inscription['nomNiveau']); ?>)</small>
                <?php else: ?>
                    <span style="color:#ed8936;">
                        <i class="fas fa-exclamation-triangle"></i> Non inscrit
                    </span>
                <?php endif; ?>
            </div>
            <div style="font-size:0.75rem; color:#718096; margin-top:2px;">
                <i class="fas fa-calendar-alt me-1"></i>
                <?php echo $inscription ? htmlspecialchars($inscription['annee']) : ($annee_active['libelle'] ?? 'Année en cours'); ?>
            </div>
        </div>
        <div class="info-card">
            <div class="label"><i class="fas fa-file-invoice me-1"></i> Reçus</div>
            <div class="value"><?php echo count($recus); ?> reçu(s) disponible(s)</div>
            <div style="font-size:0.75rem; color:#718096; margin-top:2px;">
                <a href="recus/index.php" class="text-primary" style="text-decoration:none; font-weight:600;">
                    <i class="fas fa-arrow-right me-1"></i> Voir tous mes reçus
                </a>
            </div>
        </div>
    </div>

    <!-- Derniers paiements -->
    <div class="widget">
        <div class="widget-header">
            <span class="title"><i class="fas fa-history"></i> Mes derniers paiements</span>
            <span class="badge-count"><?php echo count($paiements); ?></span>
        </div>
        <?php if (empty($paiements)): ?>
            <div class="empty">
                <i class="fas fa-receipt"></i>
                Aucun paiement effectué
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Reçu</th>
                        <th>Frais</th>
                        <th>Montant</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paiements as $p): ?>
                    <tr>
                        <td><span class="badge-recu"><?php echo $p['numeroRecu'] ?? 'N/A'; ?></span></td>
                        <td><?php echo htmlspecialchars($p['frais_libelle']); ?></td>
                        <td class="fw-bold text-success">$<?php echo number_format($p['montantPaye'], 2); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($p['datePaiement'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Mes reçus -->
    <div class="widget">
        <div class="widget-header">
            <span class="title"><i class="fas fa-file-invoice"></i> Mes reçus récents</span>
            <a href="recus/index.php" class="text-primary" style="font-size:0.8rem; font-weight:600; text-decoration:none;">
                Voir tout <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
        <?php if (empty($recus)): ?>
            <div class="empty">
                <i class="fas fa-file-invoice"></i>
                Aucun reçu disponible
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>N° Reçu</th>
                        <th>Frais</th>
                        <th>Montant</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($recus, 0, 5) as $r): ?>
                    <tr>
                        <td><span class="badge-recu"><i class="fas fa-file-invoice me-1"></i> <?php echo $r['numeroRecu']; ?></span></td>
                        <td><?php echo htmlspecialchars($r['frais_libelle']); ?></td>
                        <td class="fw-bold text-success">$<?php echo number_format($r['montantPaye'], 2); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($r['datePaiement'])); ?></td>
                        <td>
                            <a href="recus/imprimer.php?id=<?php echo $r['idPaiement']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" style="border-radius:6px; padding:2px 10px; font-size:0.7rem;">
                                <i class="fas fa-print"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const overlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (hamburgerBtn) {
        hamburgerBtn.addEventListener('click', openSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    if (sidebar) {
        sidebar.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 991) {
                    closeSidebar();
                }
            });
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
});
</script>
</body>
</html>