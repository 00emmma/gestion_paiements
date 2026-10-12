<?php
// caissier/paiements/modifier.php - Modifier un paiement (avec montant automatique)
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SESSION['role'] !== 'Caissier') {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$db = db();

// ===== RÉCUPÉRATION DE L'ID =====
$idPaiement = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['idPaiement'])) {
    $idPaiement = intval($_POST['idPaiement']);
} elseif (isset($_GET['id'])) {
    $idPaiement = intval($_GET['id']);
}

if ($idPaiement <= 0) {
    die('<div class="alert alert-danger text-center m-3"><i class="fas fa-exclamation-circle me-2"></i> ID du paiement manquant ou invalide.</div>');
}

// ===== RÉCUPÉRER LES INFORMATIONS DU PAIEMENT =====
$stmt = $db->prepare("
    SELECT p.*, 
           e.idEleve, e.matricule, e.nom, e.prenom, e.postNom,
           f.libelle as frais_libelle, f.montant as frais_montant,
           c.nomClasse,
           a.libelle as annee,
           u.nom as caissier_nom, u.prenom as caissier_prenom
    FROM paiements p
    JOIN inscriptions i ON p.idInscription = i.idInscription
    JOIN eleves e ON i.idEleve = e.idEleve
    JOIN frais f ON p.idFrais = f.idFrais
    JOIN classes c ON i.idClasse = c.idClasse
    JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
    JOIN utilisateurs u ON p.idCaissier = u.idUtilisateur
    WHERE p.idPaiement = :id
");
$stmt->execute(['id' => $idPaiement]);
$paiement = $stmt->fetch();

if (!$paiement) {
    die('<div class="alert alert-danger text-center m-3"><i class="fas fa-exclamation-circle me-2"></i> Paiement non trouvé.</div>');
}

// ===== RÉCUPÉRER LES LISTES =====
$stmt = $db->query("SELECT idEleve, matricule, nom, prenom FROM eleves ORDER BY nom");
$eleves = $stmt->fetchAll();

// Récupérer les frais avec le montant
$stmt = $db->query("SELECT idFrais, libelle, montant FROM frais WHERE statut = 'Actif' ORDER BY libelle");
$frais_list = $stmt->fetchAll();

// Liste des mois
$mois_liste = ['Septembre', 'Octobre', 'Novembre', 'Décembre', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin'];

$error = '';

// ===== TRAITEMENT DU FORMULAIRE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier_paiement') {
    $idPaiement = intval($_POST['idPaiement'] ?? 0);
    $idEleve = intval($_POST['idEleve'] ?? 0);
    $idFrais = intval($_POST['idFrais'] ?? 0);
    $mois = $_POST['mois'] ?? '';
    $datePaiement = $_POST['datePaiement'] ?? date('Y-m-d H:i:s');
    
    if ($idPaiement <= 0) {
        $error = 'ID du paiement invalide.';
    } elseif ($idEleve <= 0 || $idFrais <= 0) {
        $error = 'Veuillez sélectionner un élève et un frais.';
    } elseif (empty($mois)) {
        $error = 'Veuillez sélectionner un mois.';
    } else {
        // Récupérer le montant du frais sélectionné
        $stmt = $db->prepare("SELECT montant FROM frais WHERE idFrais = :id");
        $stmt->execute(['id' => $idFrais]);
        $frais = $stmt->fetch();
        $montantPaye = $frais ? floatval($frais['montant']) : 0;
        
        if ($montantPaye <= 0) {
            $error = 'Le montant du frais est invalide.';
        } else {
            // Vérifier si l'élève a une inscription active
            $stmt = $db->prepare("SELECT idInscription FROM inscriptions WHERE idEleve = :eleve AND idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)");
            $stmt->execute(['eleve' => $idEleve]);
            $inscription = $stmt->fetch();
            
            if (!$inscription) {
                $error = 'Cet élève n\'est pas inscrit pour l\'année en cours.';
            } else {
                $stmt = $db->prepare("
                    UPDATE paiements 
                    SET idInscription = :inscription, 
                        idFrais = :frais, 
                        mois = :mois, 
                        montantPaye = :montant, 
                        datePaiement = :date
                    WHERE idPaiement = :id
                ");
                
                $result = $stmt->execute([
                    'inscription' => $inscription['idInscription'],
                    'frais' => $idFrais,
                    'mois' => $mois,
                    'montant' => $montantPaye,
                    'date' => $datePaiement,
                    'id' => $idPaiement
                ]);
                
                if ($result) {
                    echo '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i> ✅ Paiement modifié avec succès !</div>';
                    echo '<script>setTimeout(function(){ window.parent.location.reload(); }, 1500);</script>';
                    exit();
                } else {
                    $error = 'Erreur lors de la modification du paiement.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: transparent;
            padding: 10px 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .info-card {
            background: #f7fafc;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 20px;
            border-left: 4px solid #ed8936;
        }
        .info-card .info-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            flex-wrap: wrap;
        }
        .info-card .info-row .label {
            font-weight: 600;
            color: #4a5568;
            font-size: 0.85rem;
        }
        .info-card .info-row .value {
            color: #1a202c;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #2d3748;
        }
        .btn-warning-custom {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
            border: none;
            font-weight: 600;
        }
        .btn-warning-custom:hover {
            background: linear-gradient(135deg, #dd6b20 0%, #c05621 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(237, 137, 54, 0.35);
        }
        .form-control:focus, .form-select:focus {
            border-color: #ed8936;
            box-shadow: 0 0 0 3px rgba(237, 137, 54, 0.1);
        }
        .montant-display {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 1.1rem;
            color: #1a202c;
            cursor: default;
        }
        @media (max-width: 576px) {
            .info-card .info-row {
                flex-direction: column;
                text-align: center;
            }
            .form-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid p-0">

    <!-- ===== INFO PAIEMENT ===== -->
    <div class="info-card">
        <div class="info-row">
            <span class="label"><i class="fas fa-file-invoice me-1"></i> N° Reçu</span>
            <span class="value"><?php echo htmlspecialchars($paiement['numeroRecu'] ?? 'Non généré'); ?></span>
        </div>
        <div class="info-row">
            <span class="label"><i class="fas fa-user me-1"></i> Élève</span>
            <span class="value">
                <?php echo htmlspecialchars($paiement['nom'] . ' ' . ($paiement['postNom'] ?? '') . ' ' . $paiement['prenom']); ?>
                (<strong><?php echo htmlspecialchars($paiement['matricule']); ?></strong>)
            </span>
        </div>
        <div class="info-row">
            <span class="label"><i class="fas fa-calendar-alt me-1"></i> Date</span>
            <span class="value"><?php echo date('d/m/Y H:i', strtotime($paiement['datePaiement'])); ?></span>
        </div>
        <div class="info-row">
            <span class="label"><i class="fas fa-user-cog me-1"></i> Caissier</span>
            <span class="value"><?php echo htmlspecialchars($paiement['caissier_nom'] . ' ' . $paiement['caissier_prenom']); ?></span>
        </div>
    </div>

    <!-- ===== ERREURS ===== -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- ===== FORMULAIRE ===== -->
    <form method="POST" action="" id="editForm">
        <input type="hidden" name="action" value="modifier_paiement">
        <input type="hidden" name="idPaiement" value="<?php echo $idPaiement; ?>">

        <!-- Élève -->
        <div class="mb-3">
            <label class="form-label">
                <i class="fas fa-user-graduate me-1"></i> Élève <span class="text-danger">*</span>
            </label>
            <select class="form-select" name="idEleve" required>
                <option value="">Sélectionner un élève</option>
                <?php foreach ($eleves as $e): ?>
                    <option value="<?php echo $e['idEleve']; ?>" 
                        <?php echo $e['idEleve'] == $paiement['idEleve'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($e['matricule'] . ' - ' . $e['nom'] . ' ' . $e['prenom']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Frais (avec montant intégré) -->
        <div class="mb-3">
            <label class="form-label">
                <i class="fas fa-tag me-1"></i> Type de frais <span class="text-danger">*</span>
            </label>
            <select class="form-select" name="idFrais" id="idFrais" required onchange="mettreAJourMontant()">
                <option value="">Sélectionner un frais</option>
                <?php foreach ($frais_list as $f): ?>
                    <option value="<?php echo $f['idFrais']; ?>" 
                        data-montant="<?php echo $f['montant']; ?>"
                        <?php echo $f['idFrais'] == $paiement['idFrais'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($f['libelle']); ?> - 
                        $<?php echo number_format($f['montant'], 2); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Montant (automatique, en lecture seule) -->
        <div class="mb-3">
            <label class="form-label">
                <i class="fas fa-dollar-sign me-1"></i> Montant à payer
            </label>
            <div class="montant-display">
                <span id="montantAffiche">$<?php echo number_format($paiement['montantPaye'], 2); ?></span>
            </div>
            <small class="text-muted" style="font-size:0.75rem;">
                <i class="fas fa-info-circle me-1"></i> Le montant est automatiquement récupéré du frais sélectionné.
            </small>
        </div>

        <!-- Mois -->
        <div class="mb-3">
            <label class="form-label">
                <i class="fas fa-calendar-alt me-1"></i> Mois <span class="text-danger">*</span>
            </label>
            <select class="form-select" name="mois" required>
                <option value="">Sélectionner un mois</option>
                <?php foreach ($mois_liste as $m): ?>
                    <option value="<?php echo $m; ?>" 
                        <?php echo $m == $paiement['mois'] ? 'selected' : ''; ?>>
                        <?php echo $m; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Date -->
        <div class="mb-3">
            <label class="form-label">
                <i class="fas fa-calendar-alt me-1"></i> Date de paiement
            </label>
            <input type="datetime-local" class="form-control" name="datePaiement" 
                   value="<?php echo date('Y-m-d\TH:i', strtotime($paiement['datePaiement'])); ?>">
        </div>

        <!-- Boutons -->
        <div class="d-flex justify-content-end gap-2 pt-3 border-top flex-wrap">
            <button type="button" class="btn btn-outline-secondary" onclick="window.parent.closeEditModal()">
                <i class="fas fa-times me-1"></i> Annuler
            </button>
            <button type="submit" class="btn btn-warning-custom">
                <i class="fas fa-save me-1"></i> Enregistrer
            </button>
        </div>
    </form>

</div>

<!-- ===== SCRIPTS ===== -->
<script>
    // ===== Mettre à jour le montant affiché =====
    function mettreAJourMontant() {
        const select = document.getElementById('idFrais');
        const montantAffiche = document.getElementById('montantAffiche');
        const selectedOption = select.options[select.selectedIndex];
        
        if (selectedOption && selectedOption.value) {
            const montant = selectedOption.getAttribute('data-montant') || 0;
            montantAffiche.textContent = '$' + parseFloat(montant).toFixed(2);
        } else {
            montantAffiche.textContent = '$0.00';
        }
    }

    // ===== Initialiser le montant au chargement =====
    document.addEventListener('DOMContentLoaded', function() {
        mettreAJourMontant();
    });
</script>
</body>
</html>