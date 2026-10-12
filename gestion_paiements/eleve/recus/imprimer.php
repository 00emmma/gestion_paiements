<?php
// eleve/recus/imprimer.php - Imprimer un reçu pour l'élève
session_start();

if (!isset($_SESSION['eleve_id'])) {
    header('Location: ../login.php');
    exit();
}

$idPaiement = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idPaiement <= 0) {
    die('ID de paiement invalide.');
}

require_once __DIR__ . '/../../config/database.php';

$db = db();
$eleve_id = $_SESSION['eleve_id'];

// Récupérer les informations du paiement (vérifier que c'est bien le paiement de l'élève)
$sql = "
    SELECT 
        p.idPaiement,
        p.montantPaye,
        p.datePaiement,
        p.numeroRecu,
        p.mois,
        e.matricule,
        e.nom,
        e.postNom,
        e.prenom,
        e.telephone,
        e.adresse,
        c.nomClasse,
        n.nomNiveau,
        a.libelle as annee_scolaire
    FROM paiements p
    JOIN inscriptions i ON p.idInscription = i.idInscription
    JOIN eleves e ON i.idEleve = e.idEleve
    JOIN classes c ON i.idClasse = c.idClasse
    JOIN niveaux n ON c.idNiveau = n.idNiveau
    JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
    WHERE p.idPaiement = :id
    AND i.idEleve = :eleve_id
";

$stmt = $db->prepare($sql);
$stmt->execute(['id' => $idPaiement, 'eleve_id' => $eleve_id]);
$paiement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paiement) {
    die('Reçu non trouvé ou vous n\'avez pas l\'autorisation de le consulter.');
}

// Vérifier que le reçu a un numéro
if (empty($paiement['numeroRecu'])) {
    die('Ce paiement n\'a pas encore de reçu généré.');
}

// Nom de l'école
$nom_ecole = "École Primaire et Secondaire de Kinshasa";
$adresse_ecole = "123, Avenue de la Révolution, Kinshasa";
$telephone_ecole = "+243 812 345 678";
$email_ecole = "info@ecolekinshasa.cd";

// Numéro de reçu
$num_recu = $paiement['numeroRecu'];

// Date du reçu
$date_recu = date('d/m/Y');
$heure_recu = date('H:i');

// Nom complet de l'élève
$nom_complet = trim($paiement['nom'] . ' ' . ($paiement['postNom'] ?? '') . ' ' . $paiement['prenom']);

// Année scolaire
$annee_scolaire = $paiement['annee_scolaire'] ?? 'Année en cours';

// Mois
$mois_affichage = $paiement['mois'] ?? 'Non spécifié';

// Montant
$montant = floatval($paiement['montantPaye'] ?? 0);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu - <?php echo htmlspecialchars($num_recu); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #e8ecf1;
            padding: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .recu-wrapper {
            max-width: 750px;
            width: 100%;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            overflow: hidden;
            position: relative;
        }
        
        /* ===== BANDEAU SUPÉRIEUR ===== */
        .recu-top-bar {
            background: linear-gradient(135deg, #1a365d, #2b6cb0);
            padding: 8px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .recu-top-bar span {
            color: rgba(255,255,255,0.7);
            font-size: 0.65rem;
            letter-spacing: 0.5px;
        }
        .recu-top-bar .recu-num {
            color: #fbd38d;
            font-weight: 700;
            font-size: 0.7rem;
        }
        
        /* ===== EN-TÊTE ===== */
        .recu-header {
            padding: 25px 35px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #edf2f7;
        }
        .recu-header .ecole-info h1 {
            font-size: 1.3rem;
            font-weight: 800;
            color: #1a365d;
            letter-spacing: -0.5px;
        }
        .recu-header .ecole-info h1 i {
            color: #2b6cb0;
            margin-right: 8px;
        }
        .recu-header .ecole-info p {
            font-size: 0.7rem;
            color: #718096;
            margin: 2px 0;
        }
        .recu-header .ecole-info p i {
            width: 18px;
            color: #2b6cb0;
        }
        .recu-header .recu-badge {
            text-align: center;
            background: linear-gradient(135deg, #ebf8ff, #bee3f8);
            padding: 8px 20px;
            border-radius: 10px;
            border: 2px solid #2b6cb0;
        }
        .recu-header .recu-badge .label {
            font-size: 0.55rem;
            text-transform: uppercase;
            color: #2b6cb0;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .recu-header .recu-badge .number {
            font-size: 1.1rem;
            font-weight: 800;
            color: #1a365d;
        }
        
        /* ===== CORPS ===== */
        .recu-body {
            padding: 20px 35px 25px;
        }
        
        /* Titre */
        .recu-title {
            text-align: center;
            font-size: 1.2rem;
            font-weight: 800;
            color: #1a365d;
            padding-bottom: 12px;
            margin-bottom: 18px;
            border-bottom: 3px solid #2b6cb0;
            position: relative;
            letter-spacing: 1px;
        }
        .recu-title::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: #fbd38d;
        }
        .recu-title i {
            color: #2b6cb0;
            margin-right: 10px;
        }
        
        /* ===== CARTE D'IDENTITÉ ===== */
        .identity-card {
            background: linear-gradient(135deg, #f7fafc, #edf2f7);
            border-radius: 12px;
            padding: 18px 22px;
            margin-bottom: 18px;
            border-left: 4px solid #2b6cb0;
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .identity-card .avatar {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #2b6cb0, #1a365d);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .identity-card .info {
            flex: 1;
        }
        .identity-card .info .name {
            font-size: 1.05rem;
            font-weight: 700;
            color: #1a365d;
        }
        .identity-card .info .matricule {
            font-size: 0.8rem;
            color: #4a5568;
        }
        .identity-card .info .matricule strong {
            color: #2b6cb0;
            font-weight: 700;
        }
        .identity-card .status {
            text-align: right;
        }
        .identity-card .status .badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            display: inline-block;
        }
        .badge.paye {
            background: #f0fff4;
            color: #276749;
            border: 1px solid #48bb78;
        }
        .badge i {
            margin-right: 4px;
        }
        
        /* ===== GRILLE ===== */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 25px;
            margin-bottom: 12px;
        }
        .info-item {
            display: flex;
            padding: 5px 0;
            border-bottom: 1px dotted #e2e8f0;
        }
        .info-item .label {
            width: 40%;
            font-size: 0.7rem;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .info-item .value {
            width: 60%;
            font-size: 0.85rem;
            color: #1a202c;
            font-weight: 500;
        }
        .info-item .value strong {
            color: #1a365d;
        }
        
        /* ===== MONTANT ===== */
        .montant-box {
            background: linear-gradient(135deg, #ebf8ff, #bee3f8);
            border-radius: 12px;
            padding: 15px 20px;
            margin: 12px 0 10px;
            text-align: center;
            border: 2px solid #2b6cb0;
        }
        .montant-box .label {
            font-size: 0.7rem;
            color: #2b6cb0;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .montant-box .amount {
            font-size: 1.8rem;
            font-weight: 800;
            color: #1a365d;
        }
        .montant-box .amount span {
            font-size: 1rem;
            color: #4a5568;
        }
        
        /* ===== MENTION ===== */
        .mention {
            text-align: center;
            padding: 10px 0 5px;
        }
        .mention p {
            font-size: 0.7rem;
            color: #718096;
            margin: 0;
        }
        .mention p i {
            color: #2b6cb0;
            margin-right: 4px;
        }
        .mention .seal {
            margin-top: 4px;
        }
        .mention .seal i {
            font-size: 1.5rem;
            color: #2b6cb0;
            opacity: 0.3;
        }
        
        /* ===== PIED ===== */
        .recu-footer {
            background: #f7fafc;
            padding: 12px 35px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .recu-footer .signature {
            font-size: 0.75rem;
            color: #4a5568;
        }
        .recu-footer .signature .line {
            display: inline-block;
            width: 140px;
            border-bottom: 1.5px solid #a0aec0;
            margin-top: 2px;
        }
        .recu-footer .signature .line-label {
            font-size: 0.6rem;
            color: #a0aec0;
        }
        .recu-footer .date-print {
            font-size: 0.65rem;
            color: #a0aec0;
            text-align: right;
        }
        .recu-footer .date-print i {
            margin-right: 4px;
        }
        
        /* ===== BOUTONS ===== */
        .btn-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            padding: 15px 35px 20px;
            flex-wrap: wrap;
            background: #fafbfc;
        }
        .btn-actions .btn {
            padding: 8px 22px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-actions .btn-print {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            box-shadow: 0 4px 15px rgba(72,187,120,0.25);
        }
        .btn-actions .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(72,187,120,0.35);
        }
        .btn-actions .btn-back {
            background: #e2e8f0;
            color: #4a5568;
        }
        .btn-actions .btn-back:hover {
            background: #cbd5e0;
            transform: translateY(-2px);
        }
        
        /* ===== IMPRESSION ===== */
        @media print {
            body {
                background: white;
                padding: 0;
                min-height: auto;
                align-items: flex-start;
            }
            .recu-wrapper {
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
            }
            .btn-actions {
                display: none !important;
            }
            .recu-top-bar {
                background: #1a365d !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                padding: 5px 25px;
            }
            .recu-header .recu-badge {
                background: #ebf8ff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .identity-card {
                background: #f7fafc !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .montant-box {
                background: #ebf8ff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .badge {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .recu-body {
                padding: 15px 25px 20px;
            }
            .recu-header {
                padding: 15px 25px 15px;
            }
            .recu-footer {
                padding: 10px 25px;
            }
            .identity-card .avatar {
                background: #2b6cb0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .recu-title::after {
                background: #fbd38d !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 640px) {
            body { padding: 10px; }
            .recu-header {
                flex-direction: column;
                text-align: center;
                padding: 15px 20px;
                gap: 12px;
            }
            .recu-header .recu-badge {
                width: 100%;
            }
            .recu-top-bar {
                flex-direction: column;
                text-align: center;
                padding: 6px 15px;
                gap: 2px;
            }
            .recu-body { padding: 15px 18px 20px; }
            .info-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .info-item .label {
                width: 40%;
            }
            .info-item .value {
                width: 60%;
            }
            .identity-card {
                flex-direction: column;
                text-align: center;
            }
            .identity-card .status {
                text-align: center;
            }
            .montant-box .amount {
                font-size: 1.4rem;
            }
            .recu-footer {
                flex-direction: column;
                text-align: center;
                padding: 10px 18px;
            }
            .recu-footer .date-print {
                text-align: center;
            }
            .btn-actions {
                flex-direction: column;
                padding: 12px 18px 16px;
            }
            .btn-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="recu-wrapper">
    
    <!-- ===== BANDEAU SUPÉRIEUR ===== -->
    <div class="recu-top-bar">
        <span><i class="fas fa-print"></i> Reçu de paiement</span>
        <span class="recu-num"><i class="fas fa-hashtag"></i> N° <?php echo htmlspecialchars($num_recu); ?></span>
    </div>

    <!-- ===== EN-TÊTE ===== -->
    <div class="recu-header">
        <div class="ecole-info">
            <h1><i class="fas fa-graduation-cap"></i> <?php echo $nom_ecole; ?></h1>
            <p><i class="fas fa-map-marker-alt"></i> <?php echo $adresse_ecole; ?></p>
            <p><i class="fas fa-phone"></i> <?php echo $telephone_ecole; ?> | <i class="fas fa-envelope"></i> <?php echo $email_ecole; ?></p>
        </div>
        <div class="recu-badge">
            <div class="label">Reçu de paiement</div>
            <div class="number"><?php echo htmlspecialchars($num_recu); ?></div>
        </div>
    </div>

    <!-- ===== CORPS ===== -->
    <div class="recu-body">
        
        <!-- Titre -->
        <div class="recu-title">
            <i class="fas fa-file-invoice"></i> REÇU DE PAIEMENT
        </div>

        <!-- Carte d'identité -->
        <div class="identity-card">
            <div class="avatar">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="info">
                <div class="name"><?php echo htmlspecialchars($nom_complet); ?></div>
                <div class="matricule">
                    Matricule : <strong><?php echo htmlspecialchars($paiement['matricule']); ?></strong>
                </div>
            </div>
            <div class="status">
                <span class="badge paye">
                    <i class="fas fa-check-circle"></i>
                    Payé
                </span>
            </div>
        </div>

        <!-- Informations -->
        <div class="info-grid">
            <div class="info-item">
                <span class="label">Classe</span>
                <span class="value"><strong><?php echo htmlspecialchars($paiement['nomClasse'] ?? 'Non définie'); ?></strong></span>
            </div>
            <div class="info-item">
                <span class="label">Niveau</span>
                <span class="value"><?php echo htmlspecialchars($paiement['nomNiveau'] ?? 'Non défini'); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Année scolaire</span>
                <span class="value"><?php echo htmlspecialchars($annee_scolaire); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Mois</span>
                <span class="value"><strong><?php echo htmlspecialchars($mois_affichage); ?></strong></span>
            </div>
            <div class="info-item">
                <span class="label">Date de paiement</span>
                <span class="value"><?php echo date('d/m/Y', strtotime($paiement['datePaiement'])); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Téléphone</span>
                <span class="value"><?php echo htmlspecialchars($paiement['telephone'] ?? '-'); ?></span>
            </div>
        </div>

        <!-- Montant -->
        <div class="montant-box">
            <div class="label">Montant payé</div>
            <div class="amount">
                <?php echo number_format($montant, 0, ',', ' '); ?> <span>CDF</span>
            </div>
        </div>

        <!-- Mention légale -->
        <div class="mention">
            <p>
                <i class="fas fa-check-circle"></i> 
                Le présent reçu atteste du paiement effectué pour le mois de 
                <strong><?php echo htmlspecialchars($mois_affichage); ?></strong>
                de l'année scolaire <strong><?php echo htmlspecialchars($annee_scolaire); ?></strong>
            </p>
            <div class="seal">
                <i class="fas fa-certificate"></i>
            </div>
        </div>

    </div>

    <!-- ===== PIED ===== -->
    <div class="recu-footer">
        <div class="signature">
            <div>Fait à Kinshasa, le <?php echo $date_recu; ?></div>
            <div style="margin-top: 2px;">
                <span class="line"></span>
                <br>
                <span class="line-label">Signature du responsable</span>
            </div>
        </div>
        <div class="date-print">
            <i class="fas fa-print"></i> Imprimé le <?php echo $date_recu . ' à ' . $heure_recu; ?>
        </div>
    </div>

</div>

<!-- ===== BOUTONS ===== -->
<div class="btn-actions">
    <button class="btn btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Imprimer
    </button>
    <a href="index.php" class="btn btn-back">
        <i class="fas fa-arrow-left"></i> Retour
    </a>
</div>

</body>
</html>