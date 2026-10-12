<?php
// receptionniste/eleves/generer_recu.php - Générer un reçu d'inscription élégant
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Réceptionniste') {
    header('Location: ../../login.php');
    exit();
}

$idEleve = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idEleve <= 0) {
    die('ID de l\'élève invalide.');
}

require_once __DIR__ . '/../../config/database.php';

$db = db();

// Récupérer les informations de l'élève et son inscription
$sql = "
    SELECT 
        e.*,
        i.idInscription,
        i.dateInscription,
        i.statut as inscription_statut,
        c.nomClasse,
        c.idClasse,
        n.nomNiveau,
        a.idAnnee,
        a.libelle as annee_scolaire
    FROM eleves e
    LEFT JOIN inscriptions i ON e.idEleve = i.idEleve 
        AND i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
    LEFT JOIN classes c ON i.idClasse = c.idClasse
    LEFT JOIN niveaux n ON c.idNiveau = n.idNiveau
    LEFT JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
    WHERE e.idEleve = :id
";

$stmt = $db->prepare($sql);
$stmt->execute(['id' => $idEleve]);
$eleve = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$eleve) {
    die('Élève non trouvé.');
}

// Vérifier si l'élève est inscrit
if (!$eleve['idInscription']) {
    die('Cet élève n\'est pas inscrit pour l\'année en cours.');
}

// Nom de l'école
$nom_ecole = "École Primaire et Secondaire de Kinshasa";
$adresse_ecole = "123, Avenue de la Révolution, Kinshasa";
$telephone_ecole = "+243 812 345 678";
$email_ecole = "info@ecolekinshasa.cd";

// Numéro de reçu
$num_recu = 'REC-' . date('Y') . '-' . str_pad($eleve['idInscription'], 5, '0', STR_PAD_LEFT);

// Date du reçu
$date_recu = date('d/m/Y');
$heure_recu = date('H:i');

// Année scolaire
$annee_scolaire = $eleve['annee_scolaire'] ?? 'Année en cours';

// Nom complet de l'élève
$nom_complet = trim($eleve['nom'] . ' ' . ($eleve['postNom'] ?? '') . ' ' . $eleve['prenom']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu d'inscription - <?php echo htmlspecialchars($eleve['matricule']); ?></title>
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
        .badge.actif {
            background: #f0fff4;
            color: #276749;
            border: 1px solid #48bb78;
        }
        .badge.termine {
            background: #edf2f7;
            color: #4a5568;
            border: 1px solid #a0aec0;
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
            width: 38%;
            font-size: 0.7rem;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .info-item .value {
            width: 62%;
            font-size: 0.85rem;
            color: #1a202c;
            font-weight: 500;
        }
        .info-item .value strong {
            color: #1a365d;
        }
        
        /* ===== CRÉDENTIALS AVEC ESPACE BLANC POUR MOT DE PASSE ===== */
        .credentials {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border-radius: 10px;
            padding: 12px 20px;
            margin: 12px 0 10px;
            border: 1px solid #f6c23e;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .credentials .title {
            font-size: 0.7rem;
            font-weight: 700;
            color: #975a16;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .credentials .title i {
            margin-right: 6px;
        }
        .credentials .infos {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        .credentials .infos .cred-item {
            font-size: 0.8rem;
            font-weight: 600;
            color: #1a202c;
        }
        .credentials .infos .cred-item .cred-label {
            font-weight: 400;
            color: #718096;
            font-size: 0.65rem;
        }
        .credentials .infos .cred-item .cred-value {
            font-family: 'Courier New', monospace;
            background: white;
            padding: 2px 10px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }
        /* ===== ESPACE BLANC POUR MOT DE PASSE ===== */
        .credentials .infos .cred-item .password-blank {
            display: inline-block;
            width: 180px;
            min-height: 30px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 2px 10px;
            margin-left: 4px;
        }
        .credentials .infos .cred-item .password-blank::after {
            content: '';
            display: block;
            width: 100%;
            height: 100%;
            min-height: 24px;
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
            .credentials {
                background: #fffbeb !important;
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
            .credentials .infos .cred-item .password-blank {
                background: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                border: 1px solid #e2e8f0 !important;
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
            .credentials {
                flex-direction: column;
                text-align: center;
            }
            .credentials .infos {
                justify-content: center;
            }
            .credentials .infos .cred-item .password-blank {
                width: 140px;
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
        <span><i class="fas fa-print"></i> Document officiel</span>
        <span class="recu-num"><i class="fas fa-hashtag"></i> N° <?php echo $num_recu; ?></span>
    </div>

    <!-- ===== EN-TÊTE ===== -->
    <div class="recu-header">
        <div class="ecole-info">
            <h1><i class="fas fa-graduation-cap"></i> <?php echo $nom_ecole; ?></h1>
            <p><i class="fas fa-map-marker-alt"></i> <?php echo $adresse_ecole; ?></p>
            <p><i class="fas fa-phone"></i> <?php echo $telephone_ecole; ?> | <i class="fas fa-envelope"></i> <?php echo $email_ecole; ?></p>
        </div>
        <div class="recu-badge">
            <div class="label">Reçu d'inscription</div>
            <div class="number"><?php echo $num_recu; ?></div>
        </div>
    </div>

    <!-- ===== CORPS ===== -->
    <div class="recu-body">
        
        <!-- Titre -->
        <div class="recu-title">
            <i class="fas fa-file-invoice"></i> ATTESTATION D'INSCRIPTION
        </div>

        <!-- Carte d'identité -->
        <div class="identity-card">
            <div class="avatar">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="info">
                <div class="name"><?php echo htmlspecialchars($nom_complet); ?></div>
                <div class="matricule">
                    Matricule : <strong><?php echo htmlspecialchars($eleve['matricule']); ?></strong>
                </div>
            </div>
            <div class="status">
                <span class="badge <?php echo strtolower($eleve['inscription_statut'] ?? 'actif'); ?>">
                    <i class="fas <?php echo ($eleve['inscription_statut'] ?? 'Actif') === 'Actif' ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                    <?php echo $eleve['inscription_statut'] ?? 'Actif'; ?>
                </span>
            </div>
        </div>

        <!-- Grille d'informations -->
        <div class="info-grid">
            <div class="info-item">
                <span class="label">Sexe</span>
                <span class="value"><?php echo $eleve['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?></span>
            </div>
            <div class="info-item">
                <span class="label">Date naissance</span>
                <span class="value"><?php echo $eleve['dateNaissance'] ? date('d/m/Y', strtotime($eleve['dateNaissance'])) : '-'; ?></span>
            </div>
            <div class="info-item">
                <span class="label">Téléphone</span>
                <span class="value"><?php echo htmlspecialchars($eleve['telephone'] ?? '-'); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Année scolaire</span>
                <span class="value"><strong><?php echo htmlspecialchars($annee_scolaire); ?></strong></span>
            </div>
            <div class="info-item">
                <span class="label">Niveau</span>
                <span class="value"><?php echo htmlspecialchars($eleve['nomNiveau'] ?? '-'); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Classe</span>
                <span class="value"><strong><?php echo htmlspecialchars($eleve['nomClasse'] ?? '-'); ?></strong></span>
            </div>
            <div class="info-item">
                <span class="label">Date inscription</span>
                <span class="value"><?php echo $eleve['dateInscription'] ? date('d/m/Y', strtotime($eleve['dateInscription'])) : '-'; ?></span>
            </div>
        </div>

        <!-- Parents (si renseignés) -->
        <?php if (!empty($eleve['nomPere']) || !empty($eleve['nomMere'])): ?>
        <div class="info-item" style="border-bottom: 1px dotted #e2e8f0; padding: 5px 0; display: flex;">
            <span class="label" style="width: 19%; font-size:0.7rem; font-weight:600; color:#718096; text-transform:uppercase;">Parents</span>
            <span class="value" style="width: 81%; font-size:0.85rem; color:#1a202c;">
                <?php 
                $parents = [];
                if (!empty($eleve['nomPere'])) $parents[] = 'Père: ' . htmlspecialchars($eleve['nomPere']);
                if (!empty($eleve['nomMere'])) $parents[] = 'Mère: ' . htmlspecialchars($eleve['nomMere']);
                echo implode(' | ', $parents);
                ?>
            </span>
        </div>
        <?php endif; ?>

        <!-- Adresse (si renseignée) -->
        <?php if (!empty($eleve['adresse'])): ?>
        <div class="info-item" style="border-bottom: 1px dotted #e2e8f0; padding: 5px 0; display: flex;">
            <span class="label" style="width: 19%; font-size:0.7rem; font-weight:600; color:#718096; text-transform:uppercase;">Adresse</span>
            <span class="value" style="width: 81%; font-size:0.85rem; color:#1a202c;"><?php echo htmlspecialchars($eleve['adresse']); ?></span>
        </div>
        <?php endif; ?>

        <!-- ===== CRÉDENTIALS AVEC ESPACE BLANC POUR MOT DE PASSE ===== -->
        <div class="credentials">
            <div class="title">
                <i class="fas fa-key"></i> Informations de connexion
            </div>
            <div class="infos">
                <div class="cred-item">
                    <span class="cred-label">Matricule</span>
                    <span class="cred-value"><?php echo htmlspecialchars($eleve['matricule']); ?></span>
                </div>
                <div class="cred-item">
                    <span class="cred-label">Mot de passe</span>
                    <span class="password-blank"></span>
                </div>
            </div>
        </div>

        <!-- Mention légale -->
        <div class="mention">
            <p>
                <i class="fas fa-check-circle"></i> 
                Le présent document atteste que l'élève est régulièrement inscrit(e) pour l'année scolaire 
                <strong><?php echo htmlspecialchars($annee_scolaire); ?></strong>
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