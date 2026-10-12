<?php
// admin/inscriptions/generer_pdf.php - Générer un PDF des inscrits par classe avec html2pdf.js
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

// Récupérer les paramètres
$idClasse = isset($_GET['classe']) ? intval($_GET['classe']) : 0;
$idAnnee = isset($_GET['annee']) ? intval($_GET['annee']) : 0;

if ($idClasse <= 0) {
    die('Classe invalide.');
}

$db = db();

// ===== RÉCUPÉRER LE NOM DE L'ÉCOLE =====
$nom_ecole = 'Gestion Scolaire';
try {
    $stmt = $db->query("SELECT valeur FROM parametres WHERE nom = 'nom_ecole'");
    $row = $stmt->fetch();
    if ($row) {
        $nom_ecole = $row['valeur'];
    }
} catch (PDOException $e) {}

// ===== RÉCUPÉRER LES INFORMATIONS DE LA CLASSE =====
$stmt = $db->prepare("
    SELECT c.*, n.nomNiveau 
    FROM classes c 
    LEFT JOIN niveaux n ON c.idNiveau = n.idNiveau 
    WHERE c.idClasse = :id
");
$stmt->execute(['id' => $idClasse]);
$classe = $stmt->fetch();

if (!$classe) {
    die('Classe non trouvée.');
}

// ===== RÉCUPÉRER L'ANNÉE SCOLAIRE =====
if ($idAnnee > 0) {
    $stmt = $db->prepare("SELECT * FROM annees_scolaires WHERE idAnnee = :id");
    $stmt->execute(['id' => $idAnnee]);
    $annee = $stmt->fetch();
} else {
    $stmt = $db->query("SELECT * FROM annees_scolaires WHERE active = 1");
    $annee = $stmt->fetch();
}

// ===== RÉCUPÉRER LES ÉLÈVES INSCRITS DANS LA CLASSE =====
$stmt = $db->prepare("
    SELECT e.idEleve, e.matricule, e.nom, e.postNom, e.prenom, e.sexe, e.telephone, 
           i.dateInscription, i.idInscription
    FROM inscriptions i
    JOIN eleves e ON i.idEleve = e.idEleve
    WHERE i.idClasse = :classe
    ORDER BY e.nom, e.prenom
");
$stmt->execute(['classe' => $idClasse]);
$eleves = $stmt->fetchAll();

// ===== STATISTIQUES =====
$total = count($eleves);
$garcons = 0;
$filles = 0;
foreach ($eleves as $e) {
    if ($e['sexe'] === 'M') $garcons++;
    else $filles++;
}

require_once __DIR__ . '/../../fpdf186/fpdf.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Génération PDF</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Arial', sans-serif;
            background: #f8fafc;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 24px;
            color: #1a202c;
        }
        .header h2 {
            font-size: 18px;
            color: #667eea;
        }
        .header p {
            color: #718096;
            font-size: 14px;
        }
        .info-classe {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .info-classe strong {
            color: #2d3748;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background: #667eea;
            color: white;
            padding: 10px;
            text-align: left;
        }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        tr:nth-child(even) {
            background: #f7fafc;
        }
        .summary {
            margin-top: 20px;
            padding: 15px;
            background: #ebf8ff;
            border-radius: 8px;
            border-left: 4px solid #4299e1;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #a0aec0;
            font-size: 12px;
        }
        .btn-actions {
            text-align: center;
            margin: 20px 0;
        }
        .btn-actions .btn {
            padding: 10px 30px;
            background: #e53e3e;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        .btn-actions .btn:hover {
            background: #c53030;
        }
        .btn-actions .btn-print {
            background: #48bb78;
        }
        .btn-actions .btn-print:hover {
            background: #38a169;
        }
        .no-print {
            display: block;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .container { box-shadow: none; padding: 20px; }
            .info-classe { background: #f7fafc !important; }
            tr:nth-child(even) { background: #f7fafc !important; }
        }
    </style>
</head>
<body>

<div class="container" id="pdfContent">
    <!-- Header -->
    <div class="header">
        <h1><?php echo htmlspecialchars($nom_ecole); ?></h1>
        <h2>LISTE DES ÉLÈVES INSCRITS</h2>
        <p>Document généré le <?php echo date('d/m/Y à H:i'); ?></p>
    </div>

    <!-- Informations de la classe -->
    <div class="info-classe">
        <p><strong>Classe :</strong> <?php echo htmlspecialchars($classe['nomClasse']); ?></p>
        <p><strong>Niveau :</strong> <?php echo htmlspecialchars($classe['nomNiveau'] ?? 'Non défini'); ?></p>
        <?php if ($annee): ?>
        <p><strong>Année scolaire :</strong> <?php echo htmlspecialchars($annee['libelle']); ?></p>
        <?php endif; ?>
        <p><strong>Total élèves :</strong> <?php echo $total; ?></p>
    </div>

    <!-- Tableau des élèves -->
    <table>
        <thead>
            <tr>
                <th style="width:40px;">#</th>
                <th>Matricule</th>
                <th>Nom complet</th>
                <th>Sexe</th>
                <th>Téléphone</th>
                <th>Date inscription</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($eleves)): ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding:30px; color:#a0aec0;">
                        Aucun élève inscrit dans cette classe
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($eleves as $index => $e): 
                    $nom_complet = trim($e['nom'] . ' ' . ($e['postNom'] ?? '') . ' ' . $e['prenom']);
                    $date_inscription = date('d/m/Y', strtotime($e['dateInscription']));
                ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><strong><?php echo htmlspecialchars($e['matricule']); ?></strong></td>
                        <td><?php echo htmlspecialchars($nom_complet); ?></td>
                        <td><?php echo $e['sexe'] === 'M' ? 'M' : 'F'; ?></td>
                        <td><?php echo htmlspecialchars($e['telephone'] ?? '-'); ?></td>
                        <td><?php echo $date_inscription; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Résumé -->
    <div class="summary">
        <p><strong>Résumé :</strong></p>
        <p>Total : <?php echo $total; ?> élèves</p>
        <p>Garçons : <?php echo $garcons; ?> (<?php echo $total > 0 ? round(($garcons / $total) * 100, 1) : 0; ?>%)</p>
        <p>Filles : <?php echo $filles; ?> (<?php echo $total > 0 ? round(($filles / $total) * 100, 1) : 0; ?>%)</p>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>--------------------------------------------------</p>
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($nom_ecole); ?> - Tous droits réservés</p>
    </div>
</div>

<!-- Boutons -->
<div class="btn-actions no-print">
    <button class="btn" onclick="generatePDF()">
        <i class="fas fa-file-pdf"></i> Télécharger PDF
    </button>
    <button class="btn btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Imprimer
    </button>
    <button class="btn" onclick="window.close()" style="background:#a0aec0;">
        <i class="fas fa-times"></i> Fermer
    </button>
</div>

<script>
function generatePDF() {
    const element = document.getElementById('pdfContent');
    const btn = document.querySelector('.btn');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Génération...';
    btn.disabled = true;
    
    const opt = {
        margin: 10,
        filename: 'liste_eleves_<?php echo $classe['nomClasse']; ?>_<?php echo date('Y-m-d'); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { 
            scale: 2,
            useCORS: true,
            logging: false
        },
        jsPDF: { 
            unit: 'mm', 
            format: 'a4', 
            orientation: 'portrait' 
        }
    };
    
    html2pdf().set(opt).from(element).save().then(function() {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }).catch(function() {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Erreur lors de la génération du PDF.');
    });
}
</script>

</body>
</html>

<?php include __DIR__ . '/../../includes/footer.php'; ?>