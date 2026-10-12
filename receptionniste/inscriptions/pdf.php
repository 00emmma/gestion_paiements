<?php
// receptionniste/inscriptions/pdf.php - Generation PDF des inscriptions
session_start();

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SESSION['role'] !== 'Réceptionniste') {
    header('Location: ../../index.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$fpdfPath = __DIR__ . '/../../fpdf186/fpdf.php';

if (file_exists($fpdfPath)) {
    define('FPDF_FONTPATH', __DIR__ . '/../../fpdf186/font/');
    require_once $fpdfPath;
} else {
    $fpdfPathAlt = __DIR__ . '/../../fpdf/fpdf.php';
    if (file_exists($fpdfPathAlt)) {
        define('FPDF_FONTPATH', __DIR__ . '/../../fpdf/font/');
        require_once $fpdfPathAlt;
    } else {
        die('Erreur : Impossible de trouver le fichier fpdf.php à la racine du projet.');
    }
}

$db = db();

$filtreClasse = isset($_GET['classe']) ? intval($_GET['classe']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'inscriptions';

$nom_ecole = 'Gestion Scolaire';
try {
    $stmt = $db->query("SELECT valeur FROM parametres WHERE nom = 'nom_ecole'");
    $row = $stmt->fetch();
    if ($row) {
        $nom_ecole = $row['valeur'];
    }
} catch (PDOException $e) {}

if ($type === 'inscriptions') {
    if ($filtreClasse > 0) {
        $stmt = $db->prepare("
            SELECT i.*, e.matricule, e.nom, e.postNom, e.prenom, 
                   c.nomClasse, n.nomNiveau
            FROM inscriptions i
            JOIN eleves e ON i.idEleve = e.idEleve
            JOIN classes c ON i.idClasse = c.idClasse
            JOIN niveaux n ON c.idNiveau = n.idNiveau
            WHERE i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
            AND i.idClasse = :idClasse
            ORDER BY e.nom ASC
        ");
        $stmt->execute(['idClasse' => $filtreClasse]);
    } else {
        $stmt = $db->query("
            SELECT i.*, e.matricule, e.nom, e.postNom, e.prenom, 
                   c.nomClasse, n.nomNiveau
            FROM inscriptions i
            JOIN eleves e ON i.idEleve = e.idEleve
            JOIN classes c ON i.idClasse = c.idClasse
            JOIN niveaux n ON c.idNiveau = n.idNiveau
            WHERE i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
            ORDER BY e.nom ASC
        ");
    }
    $data = $stmt->fetchAll();
    $titre = 'LISTE OFFICIELLE DES INSCRIPTIONS';
} else {
    $data = [];
    $titre = 'LISTE';
}

$stmt = $db->query("SELECT * FROM annees_scolaires WHERE active = 1");
$anneeActuelle = $stmt->fetch();

$nomClasse = '';
$niveauClasse = '';
if ($filtreClasse > 0) {
    $stmt = $db->prepare("
        SELECT c.nomClasse, n.nomNiveau 
        FROM classes c 
        LEFT JOIN niveaux n ON c.idNiveau = n.idNiveau 
        WHERE c.idClasse = :id
    ");
    $stmt->execute(['id' => $filtreClasse]);
    $classe = $stmt->fetch();
    $nomClasse = $classe['nomClasse'] ?? '';
    $niveauClasse = $classe['nomNiveau'] ?? '';
}

class PDF extends FPDF
{
    public $nom_ecole;
    public $titreRapport;
    public $infoClasse;

    function Header()
    {
        // Nom de l'école (Design épuré et pro)
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(41, 128, 185); // Bleu pro
        $this->Cell(0, 7, $this->nom_ecole, 0, 1, 'L');
        
        // Ligne fine de séparation sous l'en-tête
        $this->SetDrawColor(189, 195, 199);
        $this->SetLineWidth(0.4);
        $this->Line(10, $this->GetY() + 1, 287, $this->GetY() + 1);
        $this->Ln(4);

        // Titre du document
        $this->SetFont('Arial', 'B', 13);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(0, 6, $this->titreRapport, 0, 1, 'C');

        if (!empty($this->infoClasse)) {
            $this->SetFont('Arial', 'I', 10);
            $this->SetTextColor(127, 140, 141);
            $this->Cell(0, 5, $this->infoClasse, 0, 1, 'C');
        }
        $this->Ln(6);
    }

    function Footer()
    {
        $this->SetY(-15);
        // Ligne fine au-dessus du footer
        $this->SetDrawColor(220, 224, 230);
        $this->SetLineWidth(0.2);
        $this->Line(10, $this->GetY(), 287, $this->GetY());
        $this->Ln(2);

        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(149, 165, 166);
        $this->Cell(130, 8, 'Edite le ' . date('d/m/Y à H:i'), 0, 0, 'L');
        $this->Cell(0, 8, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'R');
    }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->nom_ecole = $nom_ecole;
$pdf->titreRapport = $titre . ' (' . ($anneeActuelle['libelle'] ?? 'Annee en cours') . ')';
if ($nomClasse) {
    $pdf->infoClasse = 'Classe : ' . $nomClasse . ($niveauClasse ? ' - Niveau : ' . $niveauClasse : '');
}

$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// ============================================================
// EN-TETES DU TABLEAU (Design moderne : fond sombre/bleu, texte blanc)
// ============================================================
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(41, 128, 185); // Bleu élégant
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(189, 195, 199);
$pdf->SetLineWidth(0.2);

$pdf->Cell(12, 8, '#', 1, 0, 'C', true);
$pdf->Cell(42, 8, 'Matricule', 1, 0, 'C', true);
$pdf->Cell(83, 8, 'Nom complet', 1, 0, 'L', true);
$pdf->Cell(50, 8, 'Classe', 1, 0, 'L', true);
$pdf->Cell(35, 8, 'Niveau', 1, 0, 'L', true);
$pdf->Cell(30, 8, 'Date', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Statut', 1, 0, 'C', true);
$pdf->Ln();

// ============================================================
// DONNEES (Lignes alternées gris clair / blanc pour le confort visuel)
// ============================================================
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(44, 62, 80);
$i = 1;
$fill = false;

if (empty($data)) {
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell(277, 8, 'Aucune inscription trouvee pour ce filtre.', 1, 1, 'C', true);
} else {
    foreach ($data as $row) {
        $nomComplet = trim($row['nom'] . ' ' . ($row['postNom'] ?? '') . ' ' . $row['prenom']);
        $date = !empty($row['dateInscription']) ? date('d/m/Y', strtotime($row['dateInscription'])) : '';
        $statut = $row['statut'] ?? 'Actif';
        
        // Couleur alternée (Zebra)
        if ($fill) {
            $pdf->SetFillColor(247, 249, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->Cell(12, 7, $i, 1, 0, 'C', true);
        $pdf->Cell(42, 7, $row['matricule'], 1, 0, 'C', true);
        $pdf->Cell(83, 7, ' ' . substr($nomComplet, 0, 45), 1, 0, 'L', true);
        $pdf->Cell(50, 7, ' ' . $row['nomClasse'], 1, 0, 'L', true);
        $pdf->Cell(35, 7, ' ' . $row['nomNiveau'], 1, 0, 'L', true);
        $pdf->Cell(30, 7, $date, 1, 0, 'C', true);
        $pdf->Cell(25, 7, $statut, 1, 0, 'C', true);
        $pdf->Ln();
        
        $i++;
        $fill = !$fill;
    }
}

// ============================================================
// TOTAL & SYNTHESE
// ============================================================
$pdf->Ln(4);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(41, 128, 185);
$pdf->Cell(0, 8, 'Total inscrits : ' . count($data), 0, 1, 'R');

// ============================================================
// SORTIE DU PDF
// ============================================================
$nomFichier = 'Liste_inscriptions_' . date('Y-m-d') . '.pdf';
if ($filtreClasse > 0) {
    $nomFichier = 'Liste_classe_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $nomClasse) . '_' . date('Y-m-d') . '.pdf';
}
$pdf->Output('D', $nomFichier);
exit();
?>