<?php
// admin/inscriptions/generer_pdf.php
// Génération PDF avec FPDF 1.86

session_start();

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Administrateur'
) {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../fpdf186/fpdf.php';

$db = db();

// =====================================================
// RÉCUPÉRER LES PARAMÈTRES
// =====================================================

$idClasse = isset($_GET['classe']) ? intval($_GET['classe']) : 0;
$idAnnee  = isset($_GET['annee']) ? intval($_GET['annee']) : 0;

if ($idClasse <= 0) {
    die('Classe invalide.');
}

// =====================================================
// NOM DE L'ÉCOLE
// =====================================================

$nom_ecole = 'Gestion Scolaire';

try {
    $stmt = $db->query(
        "SELECT valeur
         FROM parametres
         WHERE nom = 'nom_ecole'
         LIMIT 1"
    );

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['valeur'])) {
        $nom_ecole = $row['valeur'];
    }
} catch (PDOException $e) {
    // Valeur par défaut
}

// =====================================================
// INFORMATIONS DE LA CLASSE
// =====================================================

$stmt = $db->prepare("
    SELECT 
        c.*,
        n.nomNiveau
    FROM classes c
    LEFT JOIN niveaux n
        ON c.idNiveau = n.idNiveau
    WHERE c.idClasse = :id
    LIMIT 1
");

$stmt->execute([
    'id' => $idClasse
]);

$classe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$classe) {
    die('Classe non trouvée.');
}
require_once __DIR__ . '/../../fpdf186/fpdf.php';
// =====================================================
// ANNÉE SCOLAIRE
// =====================================================

$annee = null;

if ($idAnnee > 0) {

    $stmt = $db->prepare("
        SELECT *
        FROM annees_scolaires
        WHERE idAnnee = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $idAnnee
    ]);

    $annee = $stmt->fetch(PDO::FETCH_ASSOC);

} else {

    $stmt = $db->query("
        SELECT *
        FROM annees_scolaires
        WHERE active = 1
        LIMIT 1
    ");

    $annee = $stmt->fetch(PDO::FETCH_ASSOC);
}

// =====================================================
// ÉLÈVES INSCRITS
// =====================================================

$stmt = $db->prepare("
    SELECT
        e.idEleve,
        e.matricule,
        e.nom,
        e.postNom,
        e.prenom,
        e.sexe,
        e.telephone,
        i.dateInscription,
        i.idInscription
    FROM inscriptions i
    INNER JOIN eleves e
        ON i.idEleve = e.idEleve
    WHERE i.idClasse = :classe
    ORDER BY e.nom ASC, e.prenom ASC
");

$stmt->execute([
    'classe' => $idClasse
]);

$eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// STATISTIQUES
// =====================================================

$total = count($eleves);
$garcons = 0;
$filles = 0;

foreach ($eleves as $eleve) {

    if (strtoupper($eleve['sexe']) === 'M') {
        $garcons++;
    } else {
        $filles++;
    }
}

// =====================================================
// FONCTION POUR CONVERTIR UTF-8 VERS WINDOWS-1252
// FPDF STANDARD NE GÈRE PAS DIRECTEMENT UTF-8
// =====================================================

function pdfText($text)
{
    return iconv(
        'UTF-8',
        'windows-1252//TRANSLIT',
        $text
    );
}

// =====================================================
// CLASSE PDF
// =====================================================

class ListeElevesPDF extends FPDF
{
    public $nomEcole;
    public $classe;
    public $niveau;
    public $annee;

    // -------------------------------------------------
    // EN-TÊTE
    // -------------------------------------------------

    function Header()
    {
        $this->SetFont('Arial', 'B', 16);

        $this->Cell(
            0,
            8,
            pdfText($this->nomEcole),
            0,
            1,
            'C'
        );

        $this->SetFont('Arial', 'B', 13);

        $this->Cell(
            0,
            8,
            pdfText('LISTE DES ÉLÈVES INSCRITS'),
            0,
            1,
            'C'
        );

        $this->SetFont('Arial', '', 10);

        $this->Cell(
            0,
            6,
            pdfText(
                'Classe : ' .
                $this->classe .
                ' | Niveau : ' .
                $this->niveau
            ),
            0,
            1,
            'C'
        );

        if (!empty($this->annee)) {

            $this->Cell(
                0,
                6,
                pdfText('Année scolaire : ' . $this->annee),
                0,
                1,
                'C'
            );
        }

        $this->Ln(4);

        // Ligne
        $this->SetDrawColor(100, 100, 100);

        $this->Line(
            10,
            $this->GetY(),
            200,
            $this->GetY()
        );

        $this->Ln(5);

        // Titre du tableau
        $this->SetFont('Arial', 'B', 9);

        $this->SetFillColor(70, 70, 70);
        $this->SetTextColor(255, 255, 255);

        $this->Cell(10, 8, '#', 1, 0, 'C', true);
        $this->Cell(30, 8, pdfText('Matricule'), 1, 0, 'C', true);
        $this->Cell(65, 8, pdfText('Nom complet'), 1, 0, 'C', true);
        $this->Cell(15, 8, pdfText('Sexe'), 1, 0, 'C', true);
        $this->Cell(35, 8, pdfText('Téléphone'), 1, 0, 'C', true);
        $this->Cell(35, 8, pdfText('Date inscription'), 1, 1, 'C', true);

        // Retour texte normal
        $this->SetTextColor(0, 0, 0);
    }

    // -------------------------------------------------
    // PIED DE PAGE
    // -------------------------------------------------

    function Footer()
    {
        $this->SetY(-15);

        $this->SetFont('Arial', 'I', 8);

        $this->Cell(
            0,
            10,
            pdfText(
                'Page ' .
                $this->PageNo() .
                ' - Généré le ' .
                date('d/m/Y à H:i')
            ),
            0,
            0,
            'C'
        );
    }
}

// =====================================================
// CRÉATION DU PDF
// =====================================================

$pdf = new ListeElevesPDF('P', 'mm', 'A4');

$pdf->nomEcole = $nom_ecole;

$pdf->classe = $classe['nomClasse'] ?? 'Non définie';

$pdf->niveau = $classe['nomNiveau'] ?? 'Non défini';

$pdf->annee = $annee['libelle'] ?? '';

$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 20);

$pdf->AddPage();

// =====================================================
// INFORMATIONS
// =====================================================

$pdf->SetFont('Arial', '', 10);

$pdf->Cell(
    0,
    7,
    pdfText('Total élèves : ' . $total),
    0,
    1
);

$pdf->Ln(2);

// =====================================================
// TABLEAU
// =====================================================

$pdf->SetFont('Arial', '', 8);

foreach ($eleves as $index => $eleve) {

    $nomComplet = trim(
        $eleve['nom'] . ' ' .
        ($eleve['postNom'] ?? '') . ' ' .
        $eleve['prenom']
    );

    $dateInscription = '-';

    if (!empty($eleve['dateInscription'])) {

        $timestamp = strtotime($eleve['dateInscription']);

        if ($timestamp !== false) {
            $dateInscription = date(
                'd/m/Y',
                $timestamp
            );
        }
    }

    $sexe = strtoupper($eleve['sexe']) === 'M'
        ? 'M'
        : 'F';

    $telephone = !empty($eleve['telephone'])
        ? $eleve['telephone']
        : '-';

    // Alterner les lignes
    if ($index % 2 === 0) {
        $pdf->SetFillColor(245, 245, 245);
        $fill = true;
    } else {
        $fill = false;
    }

    $pdf->Cell(
        10,
        7,
        $index + 1,
        1,
        0,
        'C',
        $fill
    );

    $pdf->Cell(
        30,
        7,
        pdfText($eleve['matricule']),
        1,
        0,
        'L',
        $fill
    );

    $pdf->Cell(
        65,
        7,
        pdfText($nomComplet),
        1,
        0,
        'L',
        $fill
    );

    $pdf->Cell(
        15,
        7,
        $sexe,
        1,
        0,
        'C',
        $fill
    );

    $pdf->Cell(
        35,
        7,
        pdfText($telephone),
        1,
        0,
        'L',
        $fill
    );

    $pdf->Cell(
        35,
        7,
        $dateInscription,
        1,
        1,
        'C',
        $fill
    );
}

// =====================================================
// RÉSUMÉ
// =====================================================

$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 10);

$pdf->Cell(
    0,
    7,
    pdfText('RÉSUMÉ'),
    0,
    1
);

$pdf->SetFont('Arial', '', 9);

$pourcentageGarcons = $total > 0
    ? round(($garcons / $total) * 100, 1)
    : 0;

$pourcentageFilles = $total > 0
    ? round(($filles / $total) * 100, 1)
    : 0;

$pdf->Cell(
    0,
    6,
    pdfText('Total : ' . $total . ' élèves'),
    0,
    1
);

$pdf->Cell(
    0,
    6,
    pdfText(
        'Garçons : ' .
        $garcons .
        ' (' .
        $pourcentageGarcons .
        '%)'
    ),
    0,
    1
);

$pdf->Cell(
    0,
    6,
    pdfText(
        'Filles : ' .
        $filles .
        ' (' .
        $pourcentageFilles .
        '%)'
    ),
    0,
    1
);

// =====================================================
// GÉNÉRATION DU FICHIER
// =====================================================

$nomClasse = preg_replace(
    '/[^A-Za-z0-9_-]/',
    '_',
    $classe['nomClasse']
);

$nomFichier =
    'liste_eleves_' .
    $nomClasse .
    '_' .
    date('Y-m-d') .
    '.pdf';

$pdf->Output('D', $nomFichier);

exit();