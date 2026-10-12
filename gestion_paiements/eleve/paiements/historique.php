<?php
// eleve/paiements/historique.php - Historique des paiements de l'élève
session_start();

if (!isset($_SESSION['eleve_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$db = db();
$eleve_id = $_SESSION['eleve_id'];

// Récupérer tous les paiements de l'élève
$stmt = $db->prepare("
    SELECT p.*, f.libelle as frais_libelle, f.typeFrais,
           c.nomClasse, a.libelle as annee
    FROM paiements p
    JOIN frais f ON p.idFrais = f.idFrais
    JOIN inscriptions i ON p.idInscription = i.idInscription
    JOIN classes c ON i.idClasse = c.idClasse
    JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
    WHERE i.idEleve = :idEleve
    ORDER BY p.datePaiement DESC
");
$stmt->execute(['idEleve' => $eleve_id]);
$paiements = $stmt->fetchAll();

// Statistiques
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(montantPaye) as total_montant,
        COUNT(DISTINCT YEAR(datePaiement)) as annees
    FROM paiements p
    JOIN inscriptions i ON p.idInscription = i.idInscription
    WHERE i.idEleve = :idEleve
");
$stmt->execute(['idEleve' => $eleve_id]);
$stats = $stmt->fetch();

$page_title = 'Mes paiements - Élève';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes paiements - Élève</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f4f8;
        }

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
        .stat-card.blue { border-color: #4299e1; }

        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .table-container .table-header {
            padding: 12px 18px;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .table-container .table-header .title {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .table-container .table-header .title i { color: #667eea; margin-right: 8px; }
        .table-container .table-header .count {
            font-size: 0.75rem;
            color: #718096;
        }
        .table-responsive { overflow-x: auto; }
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            min-width: 600px;
        }
        .table thead {
            background: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        .table thead th {
            padding: 8px 14px;
            text-align: left;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4a5568;
            white-space: nowrap;
        }
        .table tbody tr { border-bottom: 1px solid #f0f2f5; transition: 0.3s; }
        .table tbody tr:hover { background: #f7fafc; }
        .table tbody td { padding: 8px 14px; vertical-align: middle; }

        .badge-recu {
            background: #ebf8ff;
            color: #2b6cb0;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-statut {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-statut.paye {
            background: #f0fff4;
            color: #276749;
        }

        .table-total {
            background: #f7fafc;
            font-weight: 700;
        }
        .table-total td {
            padding: 10px 14px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #a0aec0;
        }
        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: #e2e8f0;
        }
        .empty-state h5 { color: #4a5568; font-size: 1rem; }
        .empty-state p { font-size: 0.85rem; margin: 0; }

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
            .page-header .logout-header { display: inline-block; }
            .stats { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 576px) {
            .main-content { padding: 10px; }
            .page-header h2 { font-size: 1rem; }
            .stats { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 10px 12px; }
            .stat-card .number { font-size: 1rem; }
            .table { font-size: 0.7rem; min-width: 500px; }
            .table thead th, .table tbody td { padding: 4px 8px; }
            .table-container .table-header { flex-direction: column; text-align: center; }
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
        <li><a href="../dashboard.php"><i class="fas fa-chart-pie"></i> Tableau de bord</a></li>
        <li><a href="historique.php" class="active"><i class="fas fa-money-bill-wave"></i> Mes paiements</a></li>
        <li><a href="../recus/index.php"><i class="fas fa-file-invoice"></i> Mes reçus</a></li>
        <li class="menu-section">Compte</li>
        <li><a href="../profil.php"><i class="fas fa-user"></i> Mon profil</a></li>
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
        <a href="../../logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
    </div>
</div>

<div class="main-content">

    <div class="page-header">
        <h2><i class="fas fa-receipt"></i> Mes paiements</h2>
        <div>
            <span class="badge"><i class="fas fa-id-card me-1"></i> <?php echo $_SESSION['eleve_matricule']; ?></span>
            <a href="../../logout.php" class="btn btn-sm btn-outline-danger ms-2 logout-header" style="border-radius:8px; padding:4px 14px; font-size:0.8rem;">
                <i class="fas fa-sign-out-alt me-1"></i> Quitter
            </a>
        </div>
    </div>

    <div class="stats">
        <div class="stat-card">
            <div class="number"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="label"><i class="fas fa-receipt me-1"></i> Total paiements</div>
        </div>
        <div class="stat-card green">
            <div class="number">$<?php echo number_format($stats['total_montant'] ?? 0, 2); ?></div>
            <div class="label"><i class="fas fa-dollar-sign me-1"></i> Total payé</div>
        </div>
        <div class="stat-card orange">
            <div class="number"><?php echo $stats['annees'] ?? 0; ?></div>
            <div class="label"><i class="fas fa-calendar-alt me-1"></i> Années concernées</div>
        </div>
        <div class="stat-card blue">
            <div class="number"><?php echo date('Y'); ?></div>
            <div class="label"><i class="fas fa-calendar-check me-1"></i> Année en cours</div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-header">
            <span class="title"><i class="fas fa-list"></i> Liste de mes paiements</span>
            <span class="count"><?php echo count($paiements); ?> paiement(s)</span>
        </div>

        <?php if (empty($paiements)): ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <h5>Aucun paiement effectué</h5>
                <p>Vous n'avez pas encore effectué de paiement.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>N° Reçu</th>
                            <th>Frais</th>
                            <th>Classe</th>
                            <th>Année</th>
                            <th>Montant</th>
                            <th>Date</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paiements as $p): ?>
                        <tr>
                            <td>
                                <span class="badge-recu">
                                    <i class="fas fa-file-invoice me-1"></i>
                                    <?php echo htmlspecialchars($p['numeroRecu'] ?? 'N/A'); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($p['frais_libelle']); ?></td>
                            <td><?php echo htmlspecialchars($p['nomClasse']); ?></td>
                            <td><?php echo htmlspecialchars($p['annee']); ?></td>
                            <td><strong>$<?php echo number_format($p['montantPaye'], 2); ?></strong></td>
                            <td>
                                <small><?php echo date('d/m/Y', strtotime($p['datePaiement'])); ?></small>
                                <br><small class="text-muted"><?php echo date('H:i', strtotime($p['datePaiement'])); ?></small>
                            </td>
                            <td>
                                <span class="badge-statut paye">
                                    <i class="fas fa-check-circle me-1" style="font-size:8px;"></i>
                                    Payé
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-total">
                            <td colspan="4" class="text-end">TOTAL :</td>
                            <td><strong>$<?php echo number_format($stats['total_montant'] ?? 0, 2); ?></strong></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
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