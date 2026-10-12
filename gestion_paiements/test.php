<?php
// ============================================
// FICHIER DE DIAGNOSTIC - test.php
// ============================================

// 1. AFFICHER TOUTES LES ERREURS
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 DIAGNOSTIC DU SERVEUR</h1>";
echo "<hr>";

// 2. INFORMATIONS PHP
echo "<h2>📌 Informations PHP</h2>";
echo "Version PHP : " . phpversion() . "<br>";
echo "Système d'exploitation : " . PHP_OS . "<br>";
echo "Serveur : " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root : " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Path : " . __FILE__ . "<br>";
echo "<hr>";

// 3. EXTENSIONS PHP CHARGÉES
echo "<h2>📦 Extensions PHP chargées</h2>";
$extensions = get_loaded_extensions();
sort($extensions);
echo "<ul>";
foreach ($extensions as $ext) {
    echo "<li>" . $ext . "</li>";
}
echo "</ul>";
echo "<hr>";

// 4. EXTENSIONS CRITIQUES À VÉRIFIER
echo "<h2>✅ Vérification des extensions critiques</h2>";
$extensions_necessaires = ['pdo', 'pdo_mysql', 'mbstring', 'xml', 'json', 'curl', 'gd'];
foreach ($extensions_necessaires as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext : <span style='color:green'>Installée</span><br>";
    } else {
        echo "❌ $ext : <span style='color:red'>MANQUANTE !</span><br>";
    }
}
echo "<hr>";

// 5. PERMISSIONS DES FICHIERS
echo "<h2>🔐 Vérification des permissions</h2>";
$fichiers_a_verifier = ['index.php', '.env', '.htaccess', 'storage', 'bootstrap/cache'];
foreach ($fichiers_a_verifier as $fichier) {
    if (file_exists($fichier)) {
        $perms = substr(sprintf('%o', fileperms($fichier)), -4);
        $owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($fichier))['name'] : fileowner($fichier);
        echo "📄 $fichier : Permissions $perms, Propriétaire : $owner<br>";
    } else {
        if ($fichier == 'storage' || $fichier == 'bootstrap/cache') {
            echo "⚠️ Dossier $fichier n'existe pas ! (À créer)<br>";
        } else {
            echo "ℹ️ $fichier : Non trouvé (peut être normal)<br>";
        }
    }
}
echo "<hr>";

// 6. TESTS DE CONNEXION À LA BASE DE DONNÉES
echo "<h2>🗄️ Test de connexion à la base de données</h2>";

// Lire le fichier .env si présent
if (file_exists('.env')) {
    $env = file_get_contents('.env');
    preg_match('/DB_HOST=(.*)/', $env, $db_host);
    preg_match('/DB_DATABASE=(.*)/', $env, $db_name);
    preg_match('/DB_USERNAME=(.*)/', $env, $db_user);
    preg_match('/DB_PASSWORD=(.*)/', $env, $db_pass);
    
    $host = isset($db_host[1]) ? trim($db_host[1]) : 'localhost';
    $database = isset($db_name[1]) ? trim($db_name[1]) : 'database';
    $username = isset($db_user[1]) ? trim($db_user[1]) : 'root';
    $password = isset($db_pass[1]) ? trim($db_pass[1]) : '';
    
    echo "Host : $host<br>";
    echo "Database : $database<br>";
    echo "Username : $username<br>";
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✅ Connexion à la base de données : <span style='color:green'>RÉUSSIE !</span><br>";
        
        // Test de requête simple
        $stmt = $pdo->query("SELECT 1");
        echo "✅ Test de requête : <span style='color:green'>OK</span><br>";
        
    } catch (PDOException $e) {
        echo "❌ Erreur de connexion : <span style='color:red'>" . $e->getMessage() . "</span><br>";
    }
} else {
    echo "⚠️ Fichier .env non trouvé !<br>";
}
echo "<hr>";

// 7. TEST D'INCLUSION DE FICHIER
echo "<h2>📂 Test d'inclusion de fichiers</h2>";
if (file_exists('index.php')) {
    echo "✅ index.php trouvé<br>";
    
    // Vérifier si index.php contient du code
    $content = file_get_contents('index.php');
    if (strlen($content) > 100) {
        echo "✅ index.php contient du code (" . strlen($content) . " caractères)<br>";
    } else {
        echo "⚠️ index.php est vide ou trop petit (" . strlen($content) . " caractères)<br>";
    }
} else {
    echo "❌ index.php NON TROUVÉ dans le dossier actuel !<br>";
}
echo "<hr>";

// 8. LISTE DES FICHIERS DANS LE DOSSIER
echo "<h2>📁 Contenu du dossier actuel</h2>";
$fichiers = scandir('.');
echo "<ul>";
foreach ($fichiers as $fichier) {
    if ($fichier != '.' && $fichier != '..') {
        $type = is_dir($fichier) ? '📁' : '📄';
        $taille = is_file($fichier) ? ' (' . round(filesize($fichier)/1024, 2) . ' KB)' : '';
        echo "<li>$type $fichier$taille</li>";
    }
}
echo "</ul>";
echo "<hr>";

// 9. TEST D'ERREUR DE PHP
echo "<h2>🧪 Test d'exécution PHP</h2>";
try {
    // Tester une fonction PHP basique
    $test = str_repeat("PHP fonctionne ! ", 3);
    echo "✅ $test<br>";
    echo "✅ Aucune erreur fatale détectée<br>";
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "<br>";
}
echo "<hr>";

// 10. RECOMMANDATIONS
echo "<h2>💡 RECOMMANDATIONS</h2>";
echo "<ul>";
if (!extension_loaded('pdo_mysql')) {
    echo "<li style='color:red'>❌ Installez l'extension PDO MySQL</li>";
}
if (!file_exists('.env')) {
    echo "<li style='color:orange'>⚠️ Créez le fichier .env à partir de .env.example</li>";
}
if (!file_exists('storage') || !is_writable('storage')) {
    echo "<li style='color:orange'>⚠️ Créez le dossier storage et donnez les permissions 777</li>";
}
if (!file_exists('bootstrap/cache') || !is_writable('bootstrap/cache')) {
    echo "<li style='color:orange'>⚠️ Créez le dossier bootstrap/cache et donnez les permissions 777</li>";
}
echo "<li>✅ Si tout est vert, essayez d'accéder à votre index.php</li>";
echo "<li>🔗 Essayez : <a href='index.php'>index.php</a></li>";
echo "</ul>";

echo "<hr>";
echo "<h2>📊 Résumé du diagnostic</h2>";
echo "Date : " . date('Y-m-d H:i:s') . "<br>";
echo "IP : " . $_SERVER['REMOTE_ADDR'] . "<br>";
?>