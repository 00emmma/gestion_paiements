<?php
// caissier/paiements/export_pdf.php - Exporter les paiements en PDF avec html2pdf.js
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Caissier') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$db = db();

// Récupérer les paramètres de filtrage
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01');
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-d');
$idEleve = isset($_GET['idEleve']) ? intval($_GET['idEleve']) : 0;
$idClasse = isset($_GET['idClasse']) ? intval($_GET['idClasse']) : 0;
$mois = isset($_GET['mois']) ? $_GET['mois'] : '';

// ===== RÉCUPÉRER LE NOM DU CAISSIER =====
$stmt = $db->prepare("SELECT nom, prenom FROM utilisateurs WHERE idUtilisateur = :id");
$stmt->execute(['id' => $_SESSION['user_id']]);
$caissier = $stmt->fetch();
$nom_caissier = $caissier['nom'] . ' ' . $caissier['prenom'];

// ===== RÉCUPÉRER LE NOM DE L'ÉCOLE =====
$nom_ecole = 'Gestion Scolaire';
try {
    $stmt = $db->query("SELECT valeur FROM parametres WHERE nom = 'nom_ecole'");
    $row = $stmt->fetch();
    if ($row) {
        $nom_ecole = $row['valeur'];
    }
} catch (PDOException $e) {}

// ===== CONSTRUIRE LA REQUÊTE =====
$sql = "
    SELECT p.*, e.nom, e.postNom, e.prenom, e.matricule,
           c.nomClasse, f.libelle as frais_libelle, f.typeFrais,
           u.nom as caissier_nom, u.prenom as caissier_prenom
    FROM paiements p
    JOIN inscriptions i ON p.idInscription = i.idInscription
    JOIN eleves e ON i.idEleve = e.idEleve
    JOIN classes c ON i.idClasse = c.idClasse
    JOIN frais f ON p.idFrais = f.idFrais
    LEFT JOIN utilisateurs u ON p.idCaissier = u.idUtilisateur
    WHERE 1=1
";

$params = [];

if (!empty($date_debut)) {
    $sql .= " AND DATE(p.datePaiement) >= :date_debut";
    $params['date_debut'] = $date_debut;
}

if (!empty($date_fin)) {
    $sql .= " AND DATE(p.datePaiement) <= :date_fin";
    $params['date_fin'] = $date_fin;
}

if ($idEleve > 0) {
    $sql .= " AND i.idEleve = :idEleve";
    $params['idEleve'] = $idEleve;
}

if ($idClasse > 0) {
    $sql .= " AND i.idClasse = :idClasse";
    $params['idClasse'] = $idClasse;
}

if (!empty($mois)) {
    $sql .= " AND p.mois = :mois";
    $params['mois'] = $mois;
}

$sql .= " ORDER BY p.datePaiement DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$paiements = $stmt->fetchAll();

// ===== STATISTIQUES =====
$total_paiements = count($paiements);
$montant_total = 0;

foreach ($paiements as $p) {
    $montant_total += $p['montantPaye'];
}

include __DIR__ . '/../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Export PDF - Paiements</title>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        /* =====================================================
           STYLE SOBRE : NOIR / BLANC / GRIS
           ===================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f2f2f2;
            color: #000;
            padding: 20px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border: 1px solid #000;
        }

        /* =========================
           EN-TÊTE
           ========================= */

        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 24px;
            color: #000;
            margin-bottom: 6px;
            font-weight: bold;
        }

        .header h2 {
            font-size: 18px;
            color: #000;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .header p {
            color: #000;
            font-size: 13px;
            margin: 3px 0;
        }

        /* =========================
           INFORMATIONS
           ========================= */

        .info {
            background: #fff;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #000;
        }

        .info p {
            color: #000;
            margin: 5px 0;
            font-size: 13px;
        }

        .info strong {
            color: #000;
        }

        /* =========================
           TABLEAU
           ========================= */

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 12px;
            color: #000;
        }

        th {
            background: #000;
            color: #fff;
            padding: 8px;
            text-align: left;
            border: 1px solid #000;
            font-weight: bold;
        }

        td {
            padding: 7px 8px;
            border: 1px solid #000;
            color: #000;
            background: #fff;
        }

        tr:nth-child(even) td {
            background: #f5f5f5;
        }

        /* =========================
           RÉSUMÉ
           ========================= */

        .summary {
            margin-top: 20px;
            padding: 15px;
            background: #fff;
            border: 1px solid #000;
            color: #000;
        }

        .summary p {
            margin: 5px 0;
            font-size: 13px;
        }

        .summary strong {
            color: #000;
        }

        /* =========================
           PIED DE PAGE
           ========================= */

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #000;
            color: #000;
            font-size: 11px;
        }

        .footer p {
            margin: 4px 0;
        }

        /* =========================
           BOUTONS
           ========================= */

        .btn-actions {
            text-align: center;
            margin: 20px 0;
        }

        .btn-actions .btn {
            padding: 10px 25px;
            background: #000;
            color: #fff;
            border: 1px solid #000;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
            margin: 5px;
        }

        .btn-actions .btn:hover {
            background: #333;
        }

        .btn-actions .btn-print {
            background: #fff;
            color: #000;
            border: 1px solid #000;
        }

        .btn-actions .btn-print:hover {
            background: #eee;
        }

        .no-print {
            display: block;
        }

        /* =========================
           IMPRESSION
           ========================= */

        @media print {

            .no-print {
                display: none !important;
            }

            body {
                background: #fff;
                padding: 0;
                color: #000;
            }

            .container {
                max-width: 100%;
                border: none;
                box-shadow: none;
                padding: 15px;
            }

            .info,
            .summary {
                background: #fff !important;
                border: 1px solid #000;
            }

            table,
            th,
            td {
                border: 1px solid #000 !important;
            }

            th {
                background: #000 !important;
                color: #fff !important;
            }

            td {
                background: #fff !important;
                color: #000 !important;
            }

            .header {
                border-bottom: 2px solid #000;
            }

            .footer {
                border-top: 1px solid #000;
            }
        }
    </style>
</head>

<body>

<div class="container" id="pdfContent">

    <div class="header">
        <h1><?php echo htmlspecialchars($nom_ecole); ?></h1>

        <h2>RAPPORT DES PAIEMENTS</h2>

        <p>
            Période :
            <?php echo date('d/m/Y', strtotime($date_debut)); ?>
            -
            <?php echo date('d/m/Y', strtotime($date_fin)); ?>
        </p>

        <p>
            Caissier :
            <?php echo htmlspecialchars($nom_caissier); ?>
        </p>
    </div>

    <div class="info">

        <p>
            <strong>Total paiements :</strong>
            <?php echo $total_paiements; ?>
        </p>

        <p>
            <strong>Montant total :</strong>
            <?php echo number_format($montant_total, 2); ?> $
        </p>

        <?php if ($idClasse > 0): ?>

            <?php
            $stmt = $db->prepare(
                "SELECT nomClasse FROM classes WHERE idClasse = :id"
            );

            $stmt->execute([
                'id' => $idClasse
            ]);

            $classe = $stmt->fetch();
            ?>

            <p>
                <strong>Classe :</strong>
                <?php echo htmlspecialchars($classe['nomClasse'] ?? 'Toutes'); ?>
            </p>

        <?php endif; ?>

        <?php if (!empty($mois)): ?>

            <p>
                <strong>Mois :</strong>
                <?php echo htmlspecialchars($mois); ?>
            </p>

        <?php endif; ?>

    </div>

    <table>

        <thead>
            <tr>
                <th style="width:40px;">#</th>
                <th>Matricule</th>
                <th>Élève</th>
                <th>Classe</th>
                <th>Frais</th>
                <th>Mois</th>
                <th style="text-align:right;">Montant</th>
                <th>Date</th>
            </tr>
        </thead>

        <tbody>

        <?php if (empty($paiements)): ?>

            <tr>
                <td colspan="8" style="text-align:center; padding:30px;">
                    Aucun paiement trouvé pour cette période
                </td>
            </tr>

        <?php else: ?>

            <?php foreach ($paiements as $index => $p):

                $nom_complet = trim(
                    $p['nom'] . ' ' .
                    ($p['postNom'] ?? '') . ' ' .
                    $p['prenom']
                );

                $date_paiement = date(
                    'd/m/Y H:i',
                    strtotime($p['datePaiement'])
                );

            ?>

                <tr>

                    <td>
                        <?php echo $index + 1; ?>
                    </td>

                    <td>
                        <strong>
                            <?php echo htmlspecialchars($p['matricule']); ?>
                        </strong>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($nom_complet); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($p['nomClasse']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($p['frais_libelle']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($p['mois'] ?? '-'); ?>
                    </td>

                    <td style="text-align:right;">
                        <strong>
                            <?php echo number_format($p['montantPaye'], 2); ?> $
                        </strong>
                    </td>

                    <td>
                        <?php echo $date_paiement; ?>
                    </td>

                </tr>

            <?php endforeach; ?>

        <?php endif; ?>

        </tbody>

    </table>

    <div class="summary">

        <p>
            <strong>Résumé :</strong>
        </p>

        <p>
            Nombre de paiements :
            <?php echo $total_paiements; ?>
        </p>

        <p>
            Montant total encaissé :
            <strong>
                <?php echo number_format($montant_total, 2); ?> $
            </strong>
        </p>

    </div>

    <div class="footer">

        <p>
            Document généré le
            <?php echo date('d/m/Y à H:i'); ?>
        </p>

        <p>
            --------------------------------------------------
        </p>

        <p>
            &copy; <?php echo date('Y'); ?>
            <?php echo htmlspecialchars($nom_ecole); ?>
            - Tous droits réservés
        </p>

    </div>

</div>

<div class="btn-actions no-print">

    <button class="btn" onclick="generatePDF()">
        <i class="fas fa-file-pdf"></i>
        Télécharger PDF
    </button>

    <button class="btn btn-print" onclick="window.print()">
        <i class="fas fa-print"></i>
        Imprimer
    </button>

    <button
        class="btn"
        onclick="window.close()"
    >
        <i class="fas fa-times"></i>
        Fermer
    </button>

</div>

<script>
function generatePDF() {

    const element = document.getElementById('pdfContent');

    const btn = document.querySelector('.btn');

    const originalText = btn.innerHTML;

    btn.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Génération...';

    btn.disabled = true;

    const opt = {

        margin: 10,

        filename:
            'rapport_paiements_<?php echo date('Y-m-d'); ?>.pdf',

        image: {
            type: 'jpeg',
            quality: 0.98
        },

        html2canvas: {
            scale: 2,
            useCORS: true,
            logging: false
        },

        jsPDF: {
            unit: 'mm',
            format: 'a4',
            orientation: 'landscape'
        }
    };

    html2pdf()
        .set(opt)
        .from(element)
        .save()
        .then(function() {

            btn.innerHTML = originalText;
            btn.disabled = false;

        })
        .catch(function() {

            btn.innerHTML = originalText;
            btn.disabled = false;

            alert(
                'Erreur lors de la génération du PDF.'
            );

        });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

</body>
</html>
```
