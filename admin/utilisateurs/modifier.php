<?php
// admin/utilisateurs/modifier.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Utilisateur.php';

$page_title = 'Modifier un Utilisateur';
$db = db();
$userModel = new Utilisateur();

$id = $_GET['id'] ?? 0;
if (!$id) {
    header('Location: index.php');
    exit();
}

$user = $userModel->getById($id);
if (!$user) {
    $_SESSION['flash'] = ['type' => 'danger', 'text' => 'Utilisateur non trouvé.'];
    header('Location: index.php');
    exit();
}

$roles = $db->query("SELECT * FROM roles ORDER BY nomRole")->fetchAll();

$error = '';
$success = '';

// Traitement de la modification des informations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_info') {
    $data = [
        'nom' => trim($_POST['nom'] ?? ''),
        'postNom' => trim($_POST['postNom'] ?? ''),
        'prenom' => trim($_POST['prenom'] ?? ''),
        'sexe' => $_POST['sexe'] ?? null,
        'telephone' => trim($_POST['telephone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'idRole' => intval($_POST['idRole'] ?? 0),
        'statut' => $_POST['statut'] ?? 'Actif'
    ];
    
    if (empty($data['nom']) || empty($data['prenom']) || empty($data['idRole'])) {
        $error = 'Le nom, prénom et rôle sont obligatoires.';
    } else {
        if ($userModel->update($id, $data)) {
            $userModel->journaliser("Modification de l'utilisateur " . $user['username']);
            $success = '✅ Informations mises à jour avec succès !';
            $user = $userModel->getById($id);
        } else {
            $error = 'Erreur lors de la modification.';
        }
    }
}

// Traitement du changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $nouveauMdp = $_POST['nouveauMdp'] ?? '';
    $confirmerMdp = $_POST['confirmerMdp'] ?? '';
    
    if (strlen($nouveauMdp) < 6) {
        $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
    } elseif ($nouveauMdp !== $confirmerMdp) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        if ($userModel->changePassword($id, $nouveauMdp)) {
            $userModel->journaliser("Changement de mot de passe pour l'utilisateur " . $user['username']);
            $success = '✅ Mot de passe changé avec succès !';
        } else {
            $error = 'Erreur lors du changement de mot de passe.';
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
    width: 260px;
    flex-shrink: 0;
    background: linear-gradient(180deg, #1a202c 0%, #2d3748 100%);
    min-height: 100vh;
    padding: 20px 15px;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.sidebar-admin::-webkit-scrollbar {
    width: 4px;
}
.sidebar-admin::-webkit-scrollbar-thumb {
    background: #4a5568;
    border-radius: 4px;
}

/* Logo */
.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 20px;
    margin-bottom: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.sidebar-logo i {
    font-size: 1.6rem;
    color: #667eea;
}
.sidebar-logo span {
    font-size: 1.2rem;
    font-weight: 700;
    color: white;
}

/* Menu */
.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    flex: 1;
}
.sidebar-menu li {
    margin-bottom: 3px;
}
.sidebar-menu li a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    border-radius: 10px;
    color: #a0aec0;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
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
    width: 20px;
    text-align: center;
    font-size: 1rem;
}

/* Footer sidebar */
.sidebar-footer {
    padding-top: 20px;
    margin-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.sidebar-footer .user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 0;
}
.sidebar-footer .user-info i {
    font-size: 1.8rem;
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
    font-size: 0.85rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sidebar-footer .user-info div span {
    color: #a0aec0;
    font-size: 0.7rem;
}
.sidebar-footer .btn-logout {
    background: rgba(255, 255, 255, 0.08);
    color: #fc8181;
    border: none;
    border-radius: 8px;
    padding: 8px 12px;
    transition: all 0.3s ease;
    text-decoration: none;
    flex-shrink: 0;
}
.sidebar-footer .btn-logout:hover {
    background: rgba(255, 255, 255, 0.15);
    color: #feb2b2;
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
    max-width: 750px;
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
    color: #ed8936;
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

/* Username en lecture seule */
.username-display {
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
.username-display i {
    color: #667eea;
    font-size: 1.1rem;
}
.username-display .hint {
    font-weight: 400;
    color: #a0aec0;
    font-size: 0.8rem;
    margin-left: auto;
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

.btn-password {
    background: #ed8936;
    color: white;
    border: none;
    padding: 10px 30px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-password:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(237, 137, 54, 0.35);
}

/* Alerte */
.alert-box {
    padding: 12px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-box.success {
    background: #f0fff4;
    color: #276749;
    border: 1px solid #c6f6d5;
}
.alert-box.danger {
    background: #fff5f5;
    color: #c53030;
    border: 1px solid #fed7d7;
}
.alert-box i {
    font-size: 1.1rem;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 991px) {
    .sidebar-admin {
        display: none;
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
    .username-display {
        flex-wrap: wrap;
    }
    .username-display .hint {
        margin-left: 0;
        width: 100%;
    }
}
</style>

<!-- ============================================================ -->
<!-- ===== LAYOUT PRINCIPAL ===== -->
<!-- ============================================================ -->
<div class="app-layout">

    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar-admin">
        
        <!-- Logo -->
        <div class="sidebar-logo">
            <i class="fas fa-school"></i>
            <span>GestionScolaire</span>
        </div>
        
        <!-- Menu -->
        <ul class="sidebar-menu">
            <li>
                <a href="/admin/dashboard.php">
                    <i class="fas fa-chart-pie"></i> Tableau de bord
                </a>
            </li>
            <li>
                <a href="/admin/eleves/index.php">
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
                <a href="/admin/utilisateurs/index.php" class="active">
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
        
        <!-- Footer -->
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
                <h4><i class="fas fa-edit"></i>Modifier un utilisateur</h4>
                <a href="index.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>

            <!-- Messages -->
            <?php if ($success): ?>
                <div class="alert-box success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-box danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- ===== FORMULAIRE INFORMATIONS (colonne gauche) ===== -->
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_info">

                <!-- Nom d'utilisateur (lecture seule) -->
                <div class="section-title"><i class="fas fa-key"></i>Identifiants de connexion</div>

                <div class="form-group">
                    <label>Nom d'utilisateur</label>
                    <div class="username-display">
                        <i class="fas fa-user-circle"></i>
                        <?php echo htmlspecialchars($user['username']); ?>
                        <span class="hint"><i class="fas fa-info-circle"></i> Non modifiable</span>
                    </div>
                </div>

                <!-- Informations personnelles -->
                <div class="section-title" style="margin-top:25px;"><i class="fas fa-user"></i>Informations personnelles</div>

                <div class="form-group">
                    <label>Nom <span class="required">*</span></label>
                    <input type="text" class="form-control" name="nom" value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Post-Nom</label>
                    <input type="text" class="form-control" name="postNom" value="<?php echo htmlspecialchars($user['postNom'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Prénom <span class="required">*</span></label>
                    <input type="text" class="form-control" name="prenom" value="<?php echo htmlspecialchars($user['prenom']); ?>" required>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Sexe</label>
                            <select class="form-select" name="sexe">
                                <option value="">Sélectionner</option>
                                <option value="M" <?php echo ($user['sexe'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculin</option>
                                <option value="F" <?php echo ($user['sexe'] ?? '') === 'F' ? 'selected' : ''; ?>>Féminin</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" class="form-control" name="telephone" value="<?php echo htmlspecialchars($user['telephone'] ?? ''); ?>" placeholder="+243 812 345 678">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="exemple@email.com">
                </div>

                <!-- Rôle et statut -->
                <div class="section-title" style="margin-top:25px;"><i class="fas fa-user-tag"></i>Rôle et statut</div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Rôle <span class="required">*</span></label>
                            <select class="form-select" name="idRole" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['idRole']; ?>" <?php echo $role['idRole'] == $user['idRole'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['nomRole']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Statut</label>
                            <select class="form-select" name="statut">
                                <option value="Actif" <?php echo $user['statut'] === 'Actif' ? 'selected' : ''; ?>>Actif</option>
                                <option value="Inactif" <?php echo $user['statut'] === 'Inactif' ? 'selected' : ''; ?>>Inactif</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Bouton Mettre à jour -->
                <div class="form-actions">
                    <a href="index.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Mettre à jour
                    </button>
                </div>

            </form>

            <!-- ===== SECTION MOT DE PASSE (colonne séparée) ===== -->
            <hr style="margin: 30px 0; border-color: #edf2f7;">

            <div class="section-title" style="color:#ed8936; border-color:#ed8936;">
                <i class="fas fa-key"></i> Changer le mot de passe
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="change_password">

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="required">Nouveau mot de passe</label>
                            <input type="password" class="form-control" name="nouveauMdp" 
                                   placeholder="Minimum 6 caractères" required>
                            <small class="text-muted" style="font-size:0.7rem;">
                                <i class="fas fa-info-circle me-1"></i>Doit contenir au moins 6 caractères
                            </small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="required">Confirmer le mot de passe</label>
                            <input type="password" class="form-control" name="confirmerMdp" 
                                   placeholder="Confirmez le mot de passe" required>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn-password">
                        <i class="fas fa-key me-2"></i> Changer le mot de passe
                    </button>
                </div>
            </form>

        </div>

        <?php include __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
</body>
</html>