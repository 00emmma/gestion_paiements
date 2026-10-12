<?php
// receptionniste/classes/pdf_classe.php - Générer un PDF de la classe avec ses élèves
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SESSION['role'] !== 'Réceptionniste') {
    header('Location: ../../index.php');
    exit();
}

$idClasse = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idClasse <= 0) {
    die('ID de classe invalide.');
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$db = db();

// Récupérer les informations de la classe
$sql = "
    SELECT c.*, n.nomNiveau 
    FROM classes c
    JOIN niveaux n ON c.idNiveau = n.idNiveau
    WHERE c.idClasse = :id
";
$stmt = $db->prepare($sql);
$stmt->execute(['id' => $idClasse]);
$classe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$classe) {
    die('Classe non trouvée.');
}

// Récupérer les élèves de la classe
$sql = "
    SELECT e.*, i.idInscription, i.dateInscription, i.statut
    FROM eleves e
    JOIN inscriptions i ON e.idEleve = i.idEleve
    WHERE i.idClasse = :idClasse
    AND i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
    ORDER BY e.nom, e.prenom
";
$stmt = $db->prepare($sql);
$stmt->execute(['idClasse' => $idClasse]);
$eleves = $stmt->fetchAll();

$totalEleves = count($eleves);

// Créer le PDF avec Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);

// Nom de l'école
$nom_ecole = "École Primaire et Secondaire de Kinshasa";
$adresse_ecole = "123, Avenue de la Révolution, Kinshasa";
$telephone_ecole = "+243 812 345 678";

// Construction du HTML pour le PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 12px;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #1a365d;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18px;
            font-weight: bold;
            color: #1a365d;
            margin: 0;
        }
        .header p {
            font-size: 11px;
            color: #718096;
            margin: 3px 0;
        }
        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            color: #1a365d;
            margin-bottom: 15px;
        }
        .title .sub {
            font-size: 13px;
            font-weight: normal;
            color: #4a5568;
        }
        .info-box {
            background: #f7fafc;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #4299e1;
        }
        .info-box .row {
            display: flex;
            padding: 4px 0;
        }
        .info-box .label {
            width: 120px;
            font-weight: bold;
            color: #4a5568;
        }
        .info-box .value {
            flex: 1;
            color: #1a202c;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table th {
            background: #edf2f7;
            color: #2d3748;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        table td {
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            font-size: 11px;
        }
        table tr:hover td {
            background: #f7fafc;
        }
        .footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #a0aec0;
        }
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-m {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-f {
            background: #fce4ec;
            color: #c62828;
        }
        .badge-actif {
            background: #f0fff4;
            color: #276749;
        }
        .badge-termine {
            background: #edf2f7;
            color: #4a5568;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>🏫 ' . htmlspecialchars($nom_ecole) . '</h1>
        <p>' . htmlspecialchars($adresse_ecole) . ' | Tél: ' . htmlspecialchars($telephone_ecole) . '</p>
    </div>

    <div class="title">
        LISTE DES ÉLÈVES DE LA CLASSE
        <br>
        <span class="sub">' . htmlspecialchars($classe['nomClasse']) . ' - ' . htmlspecialchars($classe['nomNiveau']) . '</span>
    </div>

    <div class="info-box">
        <div class="row">
            <span class="label">Classe</span>
            <span class="value">' . htmlspecialchars($classe['nomClasse']) . '</span>
        </div>
        <div class="row">
            <span class="label">Niveau</span>
            <span class="value">' . htmlspecialchars($classe['nomNiveau']) . '</span>
        </div>
        <div class="row">
            <span class="label">Total élèves</span>
            <span class="value"><strong>' . $totalEleves . ' élève(s)</strong></span>
        </div>
        <div class="row">
            <span class="label">Date</span>
            <span class="value">' . date('d/m/Y à H:i') . '</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:30px;">#</th>
                <th>Matricule</th>
                <th>Nom complet</th>
                <th style="width:60px;">Sexe</th>
                <th>Téléphone</th>
                <th style="width:70px; text-align:center;">Statut</th>
            </tr>
        </thead>
        <tbody>';

if (empty($eleves)) {
    $html .= '
            <tr>
                <td colspan="6" style="text-align:center; padding:30px; color:#a0aec0;">
                    Aucun élève inscrit dans cette classe
                </td>
            </tr>';
} else {
    foreach ($eleves as $i => $eleve) {
        $nomComplet = trim($eleve['nom'] . ' ' . ($eleve['postNom'] ?? '') . ' ' . $eleve['prenom']);
        $sexeBadge = $eleve['sexe'] === 'M' ? 'badge-m' : 'badge-f';
        $sexeText = $eleve['sexe'] === 'M' ? 'Masculin' : 'Féminin';
        $statut = $eleve['statut'] ?? 'Actif';
        $statutBadge = strtolower($statut) === 'actif' ? 'badge-actif' : 'badge-termine';
        
        $html .= '
            <tr>
                <td style="text-align:center;">' . ($i + 1) . '</td>
                <td><strong>' . htmlspecialchars($eleve['matricule']) . '</strong></td>
                <td>' . htmlspecialchars($nomComplet) . '</td>
                <td style="text-align:center;">
                    <span class="badge ' . $sexeBadge . '">' . $sexeText . '</span>
                </td>
                <td>' . htmlspecialchars($eleve['telephone'] ?? '-') . '</td>
                <td style="text-align:center;">
                    <span class="badge ' . $statutBadge . '">' . $statut . '</span>
                </td>
            </tr>';
    }
}

$html .= '
        </tbody>
    </table>

    <div class="footer">
        <div>
            Document généré automatiquement
        </div>
        <div>
            Fait à Kinshasa, le ' . date('d/m/Y') . '
        </div>
    </div>

</body>
</html>';

// Générer le PDF
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Télécharger le PDF
$dompdf->stream('Liste_Classe_' . $classe['nomClasse'] . '.pdf', [
    'Attachment' => true
]);
exit();
?>