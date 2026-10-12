<?php
// admin/eleves/ajouter.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Eleve.php';
require_once __DIR__ . '/../../models/Inscription.php';
require_once __DIR__ . '/../../models/Classe.php';

$page_title = 'Ajouter un Élève';
$db = db();
$eleveModel = new Eleve();
$inscriptionModel = new InscriptionModel();
$classeModel = new ClasseModel();

$classes = $classeModel->getAll();
$matricule = $eleveModel->generateMatricule();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'matricule' => $_POST['matricule'] ?? $matricule,
        'nom' => trim($_POST['nom'] ?? ''),
        'postNom' => trim($_POST['postNom'] ?? ''),
        'prenom' => trim($_POST['prenom'] ?? ''),
        'sexe' => $_POST['sexe'] ?? '',
        'dateNaissance' => $_POST['dateNaissance'] ?? null,
        'adresse' => trim($_POST['adresse'] ?? ''),
        'telephone' => trim($_POST['telephone'] ?? ''),
        'motDePasse' => $_POST['motDePasse'] ?? ''
    ];
    
    // Validation
    if (empty($data['nom']) || empty($data['prenom']) || empty($data['sexe']) || empty($data['motDePasse'])) {
        $error = 'Le nom, le prénom, le sexe et le mot de passe sont obligatoires.';
    } elseif (strlen($data['motDePasse']) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        if ($eleveModel->create($data)) {
            $idEleve = $db->lastInsertId();
            
            if (!empty($_POST['idClasse'])) {
                $inscriptionData = [
                    'idEleve' => $idEleve,
                    'idClasse' => $_POST['idClasse'],
                    'idAnnee' => $_POST['idAnnee'] ?? 1
                ];
                $inscriptionModel->create($inscriptionData);
            }
            
            $_SESSION['flash'] = [
                'type' => 'success',
                'text' => 'Élève ajouté avec succès ! Matricule: ' . $data['matricule']
            ];
            header('Location: index.php');
            exit();
        } else {
            $error = 'Erreur lors de l\'ajout de l\'élève.';
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
/* ===== RESET ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: #f8fafc;
}

/* ===== LAYOUT ===== */
.app-layout {
    display: flex;
    min-height: 100vh;
}

/* ===== SIDEBAR ===== */
.sidebar-admin {
    width: 240px;
    flex-shrink: 0;
    background: linear-gradient(180deg, #1a202c 0%, #2d3748 100%);
    min-height: 100vh;
    padding: 18px 12px;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s ease-in-out;
    z-index: 1000;
}

.sidebar-admin::-webkit-scrollbar {
    width: 3px;
}
.sidebar-admin::-webkit-scrollbar-thumb {
    background: #4a5568;
    border-radius: 4px;
}

.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 16px;
    margin-bottom: 16px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.sidebar-logo i {
    font-size: 1.3rem;
    color: #667eea;
}
.sidebar-logo span {
    font-size: 1rem;
    font-weight: 700;
    color: white;
    flex: 1;
}

.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    flex: 1;
}
.sidebar-menu li {
    margin-bottom: 2px;
}
.sidebar-menu li a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
    border-radius: 8px;
    color: #a0aec0;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.8rem;
    transition: all 0.3s ease;
}
.sidebar-menu li a:hover {
    background: rgba(255, 255, 255, 0.08);
    color: white;
    text-decoration: none;
}
.sidebar-menu li a.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}
.sidebar-menu li a i {
    width: 18px;
    text-align: center;
    font-size: 0.85rem;
}

.sidebar-footer {
    padding-top: 16px;
    margin-top: 16px;
    border-top: 1px solid rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.sidebar-footer .user-info {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 0;
}
.sidebar-footer .user-info i {
    font-size: 1.4rem;
    color: #667eea;
    flex-shrink: 0;
}
.sidebar-footer .user-info div {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.sidebar-footer .user-info div strong {
    color: white;
    font-size: 0.75rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sidebar-footer .user-info div span {
    color: #a0aec0;
    font-size: 0.65rem;
}
.sidebar-footer .btn-logout {
    background: rgba(255, 255, 255, 0.08);
    color: #fc8181;
    border: none;
    border-radius: 6px;
    padding: 6px 10px;
    transition: all 0.3s ease;
    text-decoration: none;
    flex-shrink: 0;
    font-size: 0.8rem;
}
.sidebar-footer .btn-logout:hover {
    background: rgba(255, 255, 255, 0.15);
    color: #feb2b2;
}

/* ===== OVERLAY ===== */
.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.4);
    z-index: 999;
    display: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.sidebar-overlay.active {
    display: block;
    opacity: 1;
}

/* ===== TOGGLE BUTTON ===== */
.sidebar-toggle-mobile {
    position: fixed;
    bottom: 20px;
    left: 20px;
    z-index: 1001;
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: none;
    align-items: center;
    justify-content: center;
}
.sidebar-toggle-mobile:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
}

.sidebar-close {
    background: none;
    border: none;
    color: #a0aec0;
    font-size: 1rem;
    cursor: pointer;
    display: none;
    padding: 4px 8px;
}
.sidebar-close:hover {
    color: white;
}

/* ===== CONTENU PRINCIPAL ===== */
.main-content {
    flex: 1;
    padding: 30px 40px;
    background: #f8fafc;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: flex-start;
}

/* ===== FORMULAIRE CENTRÉ ===== */
.form-container {
    width: 100%;
    max-width: 700px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    padding: 35px 40px;
    margin-top: 20px;
}

/* En-tête du formulaire */
.form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 20px;
    margin-bottom: 25px;
    border-bottom: 2px solid #f0f2f5;
}
.form-header h4 {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1a202c;
    margin: 0;
}
.form-header h4 i {
    color: #667eea;
    margin-right: 10px;
}
.form-header .btn-back {
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 0.85rem;
    color: #4a5568;
    text-decoration: none;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.form-header .btn-back:hover {
    background: #edf2f7;
    border-color: #cbd5e0;
}

/* Champs du formulaire */
.form-group {
    margin-bottom: 18px;
}
.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.85rem;
    color: #2d3748;
    margin-bottom: 5px;
}
.form-group label .required {
    color: #e53e3e;
    margin-left: 2px;
}
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
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}
.form-group .form-control[readonly] {
    background: #f7fafc;
    cursor: not-allowed;
}
.form-group .form-text {
    font-size: 0.75rem;
    color: #a0aec0;
    margin-top: 4px;
}
.form-group .form-text i {
    margin-right: 4px;
}

/* Section titre */
.section-title {
    font-weight: 700;
    color: #667eea;
    font-size: 0.9rem;
    padding-bottom: 8px;
    margin-bottom: 15px;
    border-bottom: 2px solid #f0f2f5;
    display: flex;
    align-items: center;
    gap: 8px;
}
.section-title i {
    font-size: 0.95rem;
}

/* Boutons */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 20px;
    margin-top: 10px;
    border-top: 2px solid #f0f2f5;
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
}
.btn-cancel:hover {
    background: #f7fafc;
    border-color: #cbd5e0;
}
.btn-submit {
    padding: 10px 30px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    border: none;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    transition: all 0.3s;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
}

/* Alerte d'erreur */
.alert-box {
    padding: 12px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    background: #fff5f5;
    color: #c53030;
    border: 1px solid #fed7d7;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-box i {
    font-size: 1.1rem;
}

/* Matricule display */
.matricule-display {
    background: #f7fafc;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #4a5568;
    font-weight: 600;
}
.matricule-display i {
    color: #667eea;
    font-size: 1.1rem;
}
.matricule-display .hint {
    font-weight: 400;
    color: #a0aec0;
    font-size: 0.8rem;
    margin-left: auto;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 991px) {
    .sidebar-admin {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        transform: translateX(-100%);
        width: 260px;
        z-index: 1000;
        height: 100vh;
        border-radius: 0;
    }
    .sidebar-admin.open {
        transform: translateX(0);
    }
    .sidebar-close {
        display: block !important;
    }
    .sidebar-toggle-mobile {
        display: flex !important;
    }
    .sidebar-overlay.active {
        display: block !important;
        opacity: 1;
    }
    .main-content {
        padding: 20px;
    }
    .form-container {
        padding: 25px 20px;
        margin-top: 10px;
    }
    .form-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions .btn-cancel,
    .form-actions .btn-submit {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .form-container {
        padding: 20px 15px;
    }
    .form-header h4 {
        font-size: 1rem;
    }
    .matricule-display {
        flex-wrap: wrap;
    }
    .matricule-display .hint {
        margin-left: 0;
        width: 100%;
    }
}

@media (min-width: 992px) {
    .sidebar-toggle-mobile {
        display: none !important;
    }
    .sidebar-overlay {
        display: none !important;
    }
    .sidebar-close {
        display: none !important;
    }
}
</style>

<!-- ============================================================ -->
<!-- ===== OVERLAY ET TOGGLE ===== -->
<!-- ============================================================ -->
<button class="sidebar-toggle-mobile" id="sidebarToggle" title="Menu">
    <i class="fas fa-bars"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ============================================================ -->
<!-- ===== LAYOUT PRINCIPAL ===== -->
<!-- ============================================================ -->
<div class="app-layout">

    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar-admin" id="sidebarAdmin">
        
        <div class="sidebar-logo">
            <i class="fas fa-school"></i>
            <span>GestionScolaire</span>
            <button class="sidebar-close" id="sidebarClose">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="/admin/dashboard.php">
                    <i class="fas fa-chart-pie"></i> Tableau de bord
                </a>
            </li>
            <li>
                <a href="/admin/eleves/index.php" class="active">
                    <i class="fas fa-users"></i> Élèves
                </a>
            </li>
            <li>
                <a href="/admin/inscriptions/index.php">
                    <i class="fas fa-book-open"></i> Inscriptions
                </a>
            </li>
            <li>
                <a href="/admin/paiements/index.php">
                    <i class="fas fa-money-bill-wave"></i> Paiements
                </a>
            </li>
            <li>
                <a href="/admin/frais/index.php">
                    <i class="fas fa-tag"></i> Frais
                </a>
            </li>
            <li>
                <a href="/admin/classes/index.php">
                    <i class="fas fa-chalkboard"></i> Classes
                </a>
            </li>
            <li>
                <a href="/admin/annees/index.php">
                    <i class="fas fa-calendar-alt"></i> Années scolaires
                </a>
            </li>
            <li>
                <a href="/admin/utilisateurs/index.php">
                    <i class="fas fa-user-cog"></i> Utilisateurs
                </a>
            </li>
            <li>
                <a href="/admin/rapports/index.php">
                    <i class="fas fa-file-alt"></i> Rapports
                </a>
            </li>
            <li>
                <a href="/admin/journal/index.php">
                    <i class="fas fa-history"></i> Journal
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <div>
                    <strong><?php echo $_SESSION['nom'] . ' ' . $_SESSION['prenom']; ?></strong>
                    <span><?php echo $_SESSION['role']; ?></span>
                </div>
            </div>
            <a href="/admin/logout.php" class="btn-logout" title="Déconnexion">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>

    <!-- ===== CONTENU PRINCIPAL ===== -->
    <div class="main-content">

        <!-- ===== FORMULAIRE ===== -->
        <div class="form-container">

            <!-- En-tête -->
            <div class="form-header">
                <h4><i class="fas fa-user-plus"></i>Ajouter un élève</h4>
                <a href="index.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>

            <!-- Erreur -->
            <?php if ($error): ?>
                <div class="alert-box">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Formulaire -->
            <form method="POST" action="">

                <!-- Matricule -->
                <div class="section-title"><i class="fas fa-id-card"></i>Identifiant</div>

                <div class="form-group">
                    <label>Matricule</label>
                    <div class="matricule-display">
                        <i class="fas fa-id-card"></i>
                        <?php echo $matricule; ?>
                        <span class="hint"><i class="fas fa-info-circle"></i> Généré automatiquement</span>
                    </div>
                </div>

                <!-- Informations personnelles -->
                <div class="section-title" style="margin-top:25px;"><i class="fas fa-user"></i>Informations personnelles</div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Nom <span class="required">*</span></label>
                            <input type="text" class="form-control" name="nom" placeholder="Nom de famille" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Post-Nom</label>
                            <input type="text" class="form-control" name="postNom" placeholder="Post-Nom">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Prénom <span class="required">*</span></label>
                            <input type="text" class="form-control" name="prenom" placeholder="Prénom" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Sexe <span class="required">*</span></label>
                            <select class="form-select" name="sexe" required>
                                <option value="">Sélectionner</option>
                                <option value="M">Masculin</option>
                                <option value="F">Féminin</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Date de naissance</label>
                            <input type="date" class="form-control" name="dateNaissance">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Adresse</label>
                    <input type="text" class="form-control" name="adresse" placeholder="Adresse complète">
                </div>

                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="tel" class="form-control" name="telephone" placeholder="+243 812 345 678">
                </div>

                <!-- ===== MOT DE PASSE ===== -->
                <div class="section-title" style="margin-top:25px;"><i class="fas fa-key"></i>Connexion</div>

                <div class="form-group">
                    <label>Mot de passe <span class="required">*</span></label>
                    <input type="password" class="form-control" name="motDePasse" placeholder="Minimum 6 caractères" required>
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i>
                        Ce mot de passe permettra à l'élève de se connecter à son espace personnel.
                    </div>
                </div>

                <!-- Inscription (optionnelle) -->
                <div class="section-title" style="margin-top:25px;"><i class="fas fa-book-open"></i>Inscription (optionnelle)</div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Classe</label>
                            <select class="form-select" name="idClasse">
                                <option value="">Pas d'inscription</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['idClasse']; ?>">
                                        <?php echo htmlspecialchars($classe['nomClasse']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Année scolaire</label>
                            <select class="form-select" name="idAnnee">
                                <?php
                                $annees = $db->query("SELECT * FROM annees_scolaires ORDER BY idAnnee DESC")->fetchAll();
                                foreach ($annees as $annee):
                                ?>
                                    <option value="<?php echo $annee['idAnnee']; ?>" <?php echo $annee['active'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($annee['libelle']); ?>
                                        <?php echo $annee['active'] ? '(active)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-text" style="margin-top:-10px; margin-bottom:15px;">
                    <i class="fas fa-info-circle"></i>
                    L'inscription est facultative. Vous pourrez inscrire l'élève plus tard.
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <a href="index.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>

            </form>
        </div>

        <?php include __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- ===== SCRIPT ===== -->
<!-- ============================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebarAdmin');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');
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

    if (toggleBtn) toggleBtn.addEventListener('click', openSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    if (sidebar) {
        sidebar.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 991) closeSidebar();
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