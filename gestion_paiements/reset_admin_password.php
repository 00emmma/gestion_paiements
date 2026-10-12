```php
<?php
// reset_admin_password.php
// Réinitialisation sécurisée du mot de passe administrateur

require_once __DIR__ . '/config/database.php';

$db = db();

echo "========================================\n";
echo "🔐 RÉINITIALISATION MOT DE PASSE ADMIN\n";
echo "========================================\n\n";

// =====================================================
// 1. NOUVEAU MOT DE PASSE
// =====================================================

$newPassword = 'admin123';

// Génération du hash sécurisé
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

if ($hashedPassword === false) {
    echo "❌ Impossible de générer le hash du mot de passe.\n";
    exit;
}

echo "✅ Hash du mot de passe généré avec succès.\n\n";

// =====================================================
// 2. VÉRIFIER QUE LA TABLE EXISTE
// =====================================================

try {

    $stmt = $db->query("SHOW TABLES LIKE 'utilisateurs'");

    if ($stmt->rowCount() === 0) {
        echo "❌ La table 'utilisateurs' n'existe pas.\n";
        exit;
    }

    echo "✅ Table 'utilisateurs' trouvée.\n\n";

} catch (PDOException $e) {

    echo "❌ Erreur : " . $e->getMessage() . "\n";
    exit;
}

// =====================================================
// 3. RECHERCHER L'ADMINISTRATEUR
// =====================================================

try {

    $stmt = $db->prepare("
        SELECT idUtilisateur, nom, prenom, email, username, idRole, statut
        FROM utilisateurs
        WHERE username = :username
        LIMIT 1
    ");

    $stmt->execute([
        'username' => 'admin'
    ]);

    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    echo "❌ Erreur lors de la recherche de l'administrateur : ";
    echo $e->getMessage() . "\n";
    exit;
}

// =====================================================
// 4. SI ADMIN TROUVÉ → METTRE À JOUR LE MOT DE PASSE
// =====================================================

if ($admin) {

    echo "👤 Administrateur trouvé :\n";
    echo "   ID       : {$admin['idUtilisateur']}\n";
    echo "   Nom      : {$admin['nom']} {$admin['prenom']}\n";
    echo "   Email    : {$admin['email']}\n";
    echo "   Username : {$admin['username']}\n";
    echo "   Rôle ID  : {$admin['idRole']}\n";
    echo "   Statut   : {$admin['statut']}\n\n";

    try {

        // Mise à jour avec requête préparée
        $stmt = $db->prepare("
            UPDATE utilisateurs
            SET motDePasse = :motDePasse
            WHERE idUtilisateur = :idUtilisateur
        ");

        $stmt->execute([
            'motDePasse' => $hashedPassword,
            'idUtilisateur' => $admin['idUtilisateur']
        ]);

        // =================================================
        // 5. VÉRIFIER QUE LA MISE À JOUR A RÉUSSI
        // =================================================

        $stmt = $db->prepare("
            SELECT motDePasse
            FROM utilisateurs
            WHERE idUtilisateur = :idUtilisateur
        ");

        $stmt->execute([
            'idUtilisateur' => $admin['idUtilisateur']
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo "❌ Impossible de récupérer le mot de passe après mise à jour.\n";
            exit;
        }

        // Vérification du mot de passe avec password_verify()
        if (password_verify($newPassword, $user['motDePasse'])) {

            echo "✅ Mot de passe mis à jour avec succès !\n";
            echo "✅ Le mot de passe est bien enregistré sous forme de HASH.\n\n";

        } else {

            echo "❌ ERREUR : le mot de passe n'a pas été correctement enregistré.\n";
            exit;
        }

    } catch (PDOException $e) {

        echo "❌ Erreur lors de la mise à jour : ";
        echo $e->getMessage() . "\n";
        exit;
    }

} else {

    // =================================================
    // 6. SI USERNAME ADMIN NON TROUVÉ
    //    CHERCHER PAR EMAIL
    // =================================================

    echo "⚠️ Aucun utilisateur avec username = 'admin'.\n";
    echo "🔎 Recherche par email...\n\n";

    try {

        $stmt = $db->prepare("
            SELECT idUtilisateur, nom, prenom, email, username, idRole, statut
            FROM utilisateurs
            WHERE email = :email
            LIMIT 1
        ");

        $stmt->execute([
            'email' => 'admin@ecole.com'
        ]);

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {

            echo "❌ Aucun administrateur trouvé.\n";
            echo "💡 Vérifiez les utilisateurs présents dans la base.\n";
            exit;
        }

        echo "✅ Administrateur trouvé avec l'email : {$admin['email']}\n\n";

        $stmt = $db->prepare("
            UPDATE utilisateurs
            SET motDePasse = :motDePasse
            WHERE idUtilisateur = :idUtilisateur
        ");

        $stmt->execute([
            'motDePasse' => $hashedPassword,
            'idUtilisateur' => $admin['idUtilisateur']
        ]);

        // Vérification
        $stmt = $db->prepare("
            SELECT motDePasse
            FROM utilisateurs
            WHERE idUtilisateur = :idUtilisateur
        ");

        $stmt->execute([
            'idUtilisateur' => $admin['idUtilisateur']
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($newPassword, $user['motDePasse'])) {

            echo "✅ Mot de passe mis à jour avec succès !\n";
            echo "✅ Hash vérifié avec password_verify().\n";

        } else {

            echo "❌ Échec de la vérification du mot de passe.\n";
            exit;
        }

    } catch (PDOException $e) {

        echo "❌ Erreur : " . $e->getMessage() . "\n";
        exit;
    }
}

// =====================================================
// 7. INFORMATIONS DE CONNEXION
// =====================================================

echo "\n";
echo "========================================\n";
echo "🔐 INFORMATIONS DE CONNEXION\n";
echo "========================================\n";
echo "👤 Username    : admin\n";
echo "🔑 Mot de passe: admin123\n";
echo "========================================\n\n";

echo "⚠️ IMPORTANT :\n";
echo "Supprimez le fichier reset_admin_password.php\n";
echo "après avoir réinitialisé le mot de passe.\n";
?>
```
