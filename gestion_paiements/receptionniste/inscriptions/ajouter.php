<?php
// receptionniste/inscriptions/ajouter.php - Ajout d'inscription
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SESSION['role'] !== 'Réceptionniste') {
    header('Location: ../../index.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$db = db();

// ===== RÉCUPÉRER LES CLASSES =====
$classes = $db->query("
    SELECT c.*, n.nomNiveau 
    FROM classes c 
    LEFT JOIN niveaux n ON c.idNiveau = n.idNiveau 
    ORDER BY n.nomNiveau, c.nomClasse
")->fetchAll();

// ===== RÉCUPÉRER LES ANNÉES SCOLAIRES =====
$annees = $db->query("SELECT * FROM annees_scolaires ORDER BY idAnnee DESC")->fetchAll();

// ===== RÉCUPÉRER L'ANNÉE ACTIVE =====
$anneeActuelle = $db->query("SELECT * FROM annees_scolaires WHERE active = 1")->fetch();

// ===== RECHERCHE D'ÉLÈVE =====
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$elevesRecherche = [];
if (!empty($search)) {
    $stmt = $db->prepare("
        SELECT * FROM eleves 
        WHERE matricule LIKE :search 
        OR nom LIKE :search 
        OR prenom LIKE :search 
        OR telephone LIKE :search
        LIMIT 10
    ");
    $stmt->execute(['search' => "%$search%"]);
    $elevesRecherche = $stmt->fetchAll();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ============================================================
    // CAS 1 : NOUVEL ÉLÈVE
    // ============================================================
    if ($action === 'nouvel_eleve') {
        $nom = trim($_POST['nom'] ?? '');
        $postNom = trim($_POST['postNom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $sexe = $_POST['sexe'] ?? '';
        $dateNaissance = $_POST['dateNaissance'] ?? null;
        $lieuNaissance = trim($_POST['lieuNaissance'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $nationalite = trim($_POST['nationalite'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $nomPere = trim($_POST['nomPere'] ?? '');
        $nomMere = trim($_POST['nomMere'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $motDePasse = trim($_POST['motDePasse'] ?? 'password123');
        $idClasse = intval($_POST['idClasse'] ?? 0);
        $idAnnee = intval($_POST['idAnnee'] ?? $anneeActuelle['idAnnee'] ?? 0);
        
        // Validation
        if (empty($nom) || empty($prenom) || empty($sexe)) {
            $error = 'Le nom, le prénom et le sexe sont obligatoires.';
        } elseif ($idClasse <= 0) {
            $error = 'Veuillez sélectionner une classe.';
        } else {
            try {
                // ===== GÉNÉRER LE MATRICULE =====
                $matricule = 'ELEV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Vérifier que le matricule n'existe pas déjà
                $stmt = $db->prepare("SELECT idEleve FROM eleves WHERE matricule = :matricule");
                $stmt->execute(['matricule' => $matricule]);
                if ($stmt->fetch()) {
                    $matricule = 'ELEV-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                }
                
                // ===== INSÉRER L'ÉLÈVE =====
                $hashedPassword = password_hash($motDePasse, PASSWORD_DEFAULT);
                
                $sql = "INSERT INTO eleves (matricule, nom, postNom, prenom, sexe, dateNaissance, 
                        lieuNaissance, province, nationalite, telephone, nomPere, nomMere, adresse, motDePasse) 
                        VALUES (:matricule, :nom, :postNom, :prenom, :sexe, :dateNaissance, 
                        :lieuNaissance, :province, :nationalite, :telephone, :nomPere, :nomMere, :adresse, :motDePasse)";
                
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([
                    'matricule' => $matricule,
                    'nom' => $nom,
                    'postNom' => $postNom,
                    'prenom' => $prenom,
                    'sexe' => $sexe,
                    'dateNaissance' => $dateNaissance,
                    'lieuNaissance' => $lieuNaissance,
                    'province' => $province,
                    'nationalite' => $nationalite,
                    'telephone' => $telephone,
                    'nomPere' => $nomPere,
                    'nomMere' => $nomMere,
                    'adresse' => $adresse,
                    'motDePasse' => $hashedPassword
                ]);
                
                if ($result) {
                    $idEleve = $db->lastInsertId();
                    
                    // ===== AJOUTER L'INSCRIPTION =====
                    $sqlInscription = "INSERT INTO inscriptions (idEleve, idClasse, idAnnee, dateInscription) 
                                       VALUES (:idEleve, :idClasse, :idAnnee, NOW())";
                    $stmt = $db->prepare($sqlInscription);
                    $stmt->execute([
                        'idEleve' => $idEleve,
                        'idClasse' => $idClasse,
                        'idAnnee' => $idAnnee
                    ]);
                    
                    // ===== JOURNALISER =====
                    try {
                        $sqlLog = "INSERT INTO journal_activite (idUtilisateur, action, type, dateAction) 
                                   VALUES (:idUser, :action, :type, NOW())";
                        $stmtLog = $db->prepare($sqlLog);
                        $stmtLog->execute([
                            'idUser' => $_SESSION['user_id'],
                            'action' => "Inscription du nouvel élève " . $matricule . " - " . $nom . ' ' . $prenom,
                            'type' => 'inscription'
                        ]);
                    } catch (PDOException $e) {}
                    
                    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Élève inscrit avec succès ! Matricule : ' . $matricule];
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Erreur lors de l\'inscription.';
                }
            } catch (PDOException $e) {
                $error = 'Erreur : ' . $e->getMessage();
            }
        }
    }
    
    // ============================================================
    // CAS 2 : RÉINSCRIPTION (ANCIEN ÉLÈVE)
    // ============================================================
    if ($action === 'reinscrire') {
        $idEleve = intval($_POST['idEleve'] ?? 0);
        $idClasse = intval($_POST['idClasse'] ?? 0);
        $idAnnee = intval($_POST['idAnnee'] ?? $anneeActuelle['idAnnee'] ?? 0);
        
        if (empty($idEleve) || empty($idClasse)) {
            $error = 'Veuillez sélectionner un élève et une classe.';
        } else {
            // Vérifier si l'élève est déjà inscrit pour cette année
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM inscriptions WHERE idEleve = :idEleve AND idAnnee = :idAnnee");
            $stmt->execute(['idEleve' => $idEleve, 'idAnnee' => $idAnnee]);
            $exists = $stmt->fetch();
            
            if ($exists['count'] > 0) {
                $error = 'Cet élève est déjà inscrit pour cette année scolaire.';
            } else {
                try {
                    $sql = "INSERT INTO inscriptions (idEleve, idClasse, idAnnee, dateInscription) 
                            VALUES (:idEleve, :idClasse, :idAnnee, NOW())";
                    $stmt = $db->prepare($sql);
                    $result = $stmt->execute([
                        'idEleve' => $idEleve,
                        'idClasse' => $idClasse,
                        'idAnnee' => $idAnnee
                    ]);
                    
                    if ($result) {
                        // Récupérer le nom de l'élève pour le message
                        $stmt = $db->prepare("SELECT matricule, nom, prenom FROM eleves WHERE idEleve = :id");
                        $stmt->execute(['id' => $idEleve]);
                        $eleve = $stmt->fetch();
                        
                        // Journaliser
                        try {
                            $sqlLog = "INSERT INTO journal_activite (idUtilisateur, action, type, dateAction) 
                                       VALUES (:idUser, :action, :type, NOW())";
                            $stmtLog = $db->prepare($sqlLog);
                            $stmtLog->execute([
                                'idUser' => $_SESSION['user_id'],
                                'action' => "Réinscription de l'élève " . $eleve['matricule'] . " - " . $eleve['nom'] . ' ' . $eleve['prenom'],
                                'type' => 'inscription'
                            ]);
                        } catch (PDOException $e) {}
                        
                        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Réinscription effectuée avec succès !'];
                        header('Location: index.php');
                        exit();
                    } else {
                        $error = 'Erreur lors de la réinscription.';
                    }
                } catch (PDOException $e) {
                    $error = 'Erreur : ' . $e->getMessage();
                }
            }
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une Inscription</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-container {
            width: 100%;
            max-width: 800px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.2);
            padding: 30px 35px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.4s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header-custom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 18px;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f2f5;
        }
        .modal-header-custom h4 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1a202c;
            margin: 0;
        }
        .modal-header-custom h4 i {
            color: #4299e1;
            margin-right: 10px;
        }
        .modal-header-custom .btn-close-custom {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4a5568;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 1.2rem;
        }
        .modal-header-custom .btn-close-custom:hover {
            background: #edf2f7;
            border-color: #cbd5e0;
        }

        .section-title {
            font-weight: 700;
            color: #4299e1;
            font-size: 0.9rem;
            margin: 15px 0 8px 0;
        }
        .section-title i { margin-right: 8px; }
        .section-divider {
            margin: 0 0 15px 0;
            border: 0;
            border-top: 1px solid #edf2f7;
        }

        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.85rem;
            color: #2d3748;
            margin-bottom: 5px;
        }
        .form-group label .required { color: #e53e3e; margin-left: 2px; }
        .form-group .form-control,
        .form-group .form-select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s;
            background: white;
        }
        .form-group .form-control:focus,
        .form-group .form-select:focus {
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66,153,225,0.1);
            outline: none;
        }
        .form-group .form-text {
            font-size: 0.75rem;
            color: #a0aec0;
            margin-top: 4px;
        }
        .form-group .form-text i { margin-right: 4px; }

        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .search-box input {
            flex: 1;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.9rem;
            min-width: 200px;
        }
        .search-box input:focus {
            border-color: #4299e1;
            outline: none;
            box-shadow: 0 0 0 3px rgba(66,153,225,0.1);
        }
        .search-box .btn-search {
            background: #4299e1;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .search-box .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(66,153,225,0.35);
        }

        .result-list {
            max-height: 180px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }
        .result-item {
            padding: 10px 14px;
            border-bottom: 1px solid #f0f2f5;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .result-item:hover { background: #f7fafc; }
        .result-item .matricule { font-weight: 600; color: #4299e1; }
        .result-item .info { color: #718096; font-size: 0.85rem; }
        .result-item .btn-select {
            background: #4299e1;
            color: white;
            border: none;
            padding: 4px 14px;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
        }

        .eleve-selectionne {
            padding: 10px 14px;
            background: #f7fafc;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            min-height: 45px;
        }
        .eleve-selectionne.active {
            border-color: #48bb78;
            background: #f0fff4;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 20px;
            margin-top: 10px;
            border-top: 2px solid #f0f2f5;
            flex-wrap: wrap;
        }
        .btn-cancel {
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            border: 2px solid #e2e8f0;
            background: white;
            color: #4a5568;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-cancel:hover { background: #f7fafc; border-color: #cbd5e0; }
        .btn-submit {
            padding: 10px 30px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            background: linear-gradient(135deg, #4299e1, #3182ce);
            color: white;
            transition: all 0.3s;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(66,153,225,0.35);
        }

        .alert-box {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-box.danger {
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #fed7d7;
        }
        .alert-box.success {
            background: #f0fff4;
            color: #276749;
            border: 1px solid #c6f6d5;
        }
        .alert-box i { font-size: 1.1rem; }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 10px;
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 8px 20px;
            border: none;
            background: transparent;
            font-weight: 600;
            font-size: 0.85rem;
            color: #718096;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s;
            flex: 1;
            text-align: center;
            min-width: 120px;
        }
        .tab-btn:hover { background: #f7fafc; }
        .tab-btn.active {
            background: #4299e1;
            color: white;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .matricule-display {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 600;
            color: #4a5568;
        }

        @media (max-width: 576px) {
            .modal-container { padding: 20px; }
            .tabs { flex-direction: column; }
            .tab-btn { min-width: auto; }
            .search-box { flex-direction: column; }
            .search-box input { min-width: auto; }
            .search-box .btn-search { width: 100%; justify-content: center; }
            .form-actions { flex-direction: column; }
            .form-actions .btn-cancel, .form-actions .btn-submit { width: 100%; justify-content: center; }
            .result-item { flex-direction: column; text-align: center; }
            .result-item .btn-select { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="modal-container" id="modalContainer">
        <!-- En-tête -->
        <div class="modal-header-custom">
            <h4><i class="fas fa-user-plus"></i> Ajouter une inscription</h4>
            <a href="index.php" class="btn-close-custom" title="Fermer">
                <i class="fas fa-times"></i>
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert-box danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-box success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <!-- ONGLETS -->
        <div class="tabs">
            <button class="tab-btn active" data-tab="nouvel_eleve">
                <i class="fas fa-user-plus"></i> Nouvel élève
            </button>
            <button class="tab-btn" data-tab="ancien_eleve">
                <i class="fas fa-undo"></i> Ancien élève
            </button>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 1 : NOUVEL ÉLÈVE -->
        <!-- ============================================================ -->
        <div class="tab-content active" id="tab-nouvel_eleve">
            <form method="POST" action="">
                <input type="hidden" name="action" value="nouvel_eleve">

                <div class="row g-3">
                    <div class="col-12">
                        <h6 style="font-weight:700; color:#4299e1; margin-bottom:8px; font-size:0.9rem;">
                            <i class="fas fa-user"></i> Identité
                        </h6>
                        <hr style="margin:0 0 12px 0;">
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group">
                            <label>Nom <span class="required">*</span></label>
                            <input type="text" class="form-control" name="nom" placeholder="Nom" required>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group">
                            <label>Post-Nom</label>
                            <input type="text" class="form-control" name="postNom" placeholder="Post-Nom">
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group">
                            <label>Prénom <span class="required">*</span></label>
                            <input type="text" class="form-control" name="prenom" placeholder="Prénom" required>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <div class="form-group">
                            <label>Sexe <span class="required">*</span></label>
                            <select class="form-select" name="sexe" required>
                                <option value="">Sélectionner</option>
                                <option value="M">Masculin</option>
                                <option value="F">Féminin</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group">
                            <label>Date de naissance</label>
                            <input type="date" class="form-control" name="dateNaissance">
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group">
                            <label>Lieu de naissance</label>
                            <input type="text" class="form-control" name="lieuNaissance" placeholder="Lieu de naissance">
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <div class="form-group">
                            <label>Province</label>
                            <input type="text" class="form-control" name="province" placeholder="Province">
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group">
                            <label>Nationalité</label>
                            <input type="text" class="form-control" name="nationalite" placeholder="Nationalité">
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" class="form-control" name="telephone" placeholder="+243 812 345 678">
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label>Nom du père</label>
                            <input type="text" class="form-control" name="nomPere" placeholder="Nom du père">
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label>Nom de la mère</label>
                            <input type="text" class="form-control" name="nomMere" placeholder="Nom de la mère">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Adresse</label>
                    <input type="text" class="form-control" name="adresse" placeholder="Adresse complète">
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <h6 style="font-weight:700; color:#4299e1; margin:12px 0 8px 0; font-size:0.9rem;">
                            <i class="fas fa-key"></i> Connexion
                        </h6>
                        <hr style="margin:0 0 12px 0;">
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label>Matricule</label>
                            <div class="matricule-display">
                                <?php 
                                    // Générer un matricule provisoire
                                    $matricule = 'ELEV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                                    echo $matricule;
                                ?>
                            </div>
                            <div class="form-text"><i class="fas fa-info-circle"></i> Généré automatiquement</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label>Mot de passe</label>
                            <input type="text" class="form-control" name="motDePasse" value="password123">
                            <div class="form-text"><i class="fas fa-info-circle"></i> Mot de passe par défaut</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <h6 style="font-weight:700; color:#4299e1; margin:12px 0 8px 0; font-size:0.9rem;">
                            <i class="fas fa-book-open"></i> Inscription
                        </h6>
                        <hr style="margin:0 0 12px 0;">
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label>Classe <span class="required">*</span></label>
                            <select class="form-select" name="idClasse" required>
                                <option value="">Sélectionner</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['idClasse']; ?>">
                                        <?php echo htmlspecialchars($classe['nomClasse']); ?>
                                        (<?php echo htmlspecialchars($classe['nomNiveau'] ?? 'Niveau non défini'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label>Année scolaire</label>
                            <select class="form-select" name="idAnnee">
                                <?php foreach ($annees as $annee): ?>
                                    <option value="<?php echo $annee['idAnnee']; ?>" <?php echo $annee['active'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($annee['libelle']); ?>
                                        <?php echo $annee['active'] ? '(active)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="index.php" class="btn-cancel"><i class="fas fa-times"></i> Annuler</a>
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Inscrire</button>
                </div>
            </form>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 2 : ANCIEN ÉLÈVE -->
        <!-- ============================================================ -->
        <div class="tab-content" id="tab-ancien_eleve">
            <div class="form-group">
                <label>Rechercher un élève</label>
                <div class="search-box">
                    <input type="text" id="searchEleve" placeholder="Matricule, nom, prénom ou téléphone..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn-search" onclick="rechercherEleve()">
                        <i class="fas fa-search"></i> Rechercher
                    </button>
                </div>
                <div id="resultatsRecherche" class="result-list">
                    <?php if (!empty($search) && !empty($elevesRecherche)): ?>
                        <?php foreach ($elevesRecherche as $eleve): ?>
                            <div class="result-item" onclick="selectionnerEleve(<?php echo $eleve['idEleve']; ?>, '<?php echo $eleve['matricule']; ?>', '<?php echo $eleve['nom'] . ' ' . $eleve['prenom']; ?>')">
                                <div>
                                    <span class="matricule"><?php echo $eleve['matricule']; ?></span>
                                    <span><?php echo $eleve['nom'] . ' ' . $eleve['prenom']; ?></span>
                                    <span class="info"><?php echo $eleve['telephone'] ?? ''; ?></span>
                                </div>
                                <button class="btn-select">Sélectionner</button>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif (!empty($search)): ?>
                        <div class="result-item" style="justify-content:center; color:#a0aec0;">Aucun élève trouvé</div>
                    <?php else: ?>
                        <div class="result-item" style="justify-content:center; color:#a0aec0;">
                            Saisissez un matricule, un nom, un prénom ou un téléphone pour rechercher
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST" action="" id="formReinscription">
                <input type="hidden" name="action" value="reinscrire">
                <input type="hidden" name="idEleve" id="idEleveReinscription" value="">

                <div class="form-group">
                    <label>Élève sélectionné</label>
                    <div id="eleveSelectionne" class="eleve-selectionne">
                        <span style="color:#a0aec0;">Aucun élève sélectionné</span>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label>Classe <span class="required">*</span></label>
                            <select class="form-select" name="idClasse" required>
                                <option value="">Sélectionner</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['idClasse']; ?>">
                                        <?php echo htmlspecialchars($classe['nomClasse']); ?>
                                        (<?php echo htmlspecialchars($classe['nomNiveau'] ?? 'Niveau non défini'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label>Année scolaire</label>
                            <select class="form-select" name="idAnnee">
                                <?php foreach ($annees as $annee): ?>
                                    <option value="<?php echo $annee['idAnnee']; ?>" <?php echo $annee['active'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($annee['libelle']); ?>
                                        <?php echo $annee['active'] ? '(active)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="index.php" class="btn-cancel"><i class="fas fa-times"></i> Annuler</a>
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Réinscrire</button>
                </div>
            </form>
        </div>

    </div>

    <script>
    // Onglets
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('tab-' + this.dataset.tab).classList.add('active');
        });
    });

    // Recherche élève
    function rechercherEleve() {
        const search = document.getElementById('searchEleve').value;
        if (search.length > 0) {
            window.location.href = 'ajouter.php?search=' + encodeURIComponent(search);
        }
    }

    document.getElementById('searchEleve').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            rechercherEleve();
        }
    });

    function selectionnerEleve(id, matricule, nom) {
        document.getElementById('idEleveReinscription').value = id;
        const el = document.getElementById('eleveSelectionne');
        el.innerHTML = '<strong>' + matricule + '</strong> - ' + nom +
            ' <span style="color:#48bb78; margin-left:10px;"><i class="fas fa-check-circle"></i> Sélectionné</span>';
        el.classList.add('active');
        document.getElementById('resultatsRecherche').style.display = 'none';
    }

    // Fermer avec Echap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.location.href = 'index.php';
        }
    });
    </script>
</body>
</html>