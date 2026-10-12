<?php
// receptionniste/inscriptions/pdf.php - Generation PDF des inscriptions (Design Admin)
session_start();

// Masquer les avertissements de dépréciation
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SESSION['role'] !== 'Receptionniste') {
    header('Location: ../../index.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../fpdf186/fpdf.php';

$db = db();

// =====================================================
// RECUPERATION DES PARAMETRES
// =====================================================

$filtreClasse = isset($_GET['classe']) ? intval($_GET['classe']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'inscriptions';

if ($filtreClasse <= 0 && $type === 'inscriptions') {
    $filtreClasse = 0;
}

// =====================================================
// NOM DE L'ECOLE
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
// INFORMATIONS DE LA CLASSE (SI FILTREE)
// =====================================================

$classe = null;
$nomClasse = 'Toutes les classes';
$niveauClasse = '';

if ($filtreClasse > 0) {
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
        'id' => $filtreClasse
    ]);

    $classe = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($classe) {
        $nomClasse = $classe['nomClasse'] ?? 'Non définie';
        $niveauClasse = $classe['nomNiveau'] ?? 'Non défini';
    }
}

// =====================================================
// ANNEE SCOLAIRE
// =====================================================

$annee = null;

$stmt = $db->query("
    SELECT *
    FROM annees_scolaires
    WHERE active = 1
    LIMIT 1
");

$annee = $stmt->fetch(PDO::FETCH_ASSOC);

// =====================================================
// RECUPERATION DES ELEVES INSCRITS
// =====================================================

if ($filtreClasse > 0) {
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
            i.idInscription,
            c.nomClasse,
            n.nomNiveau
        FROM inscriptions i
        INNER JOIN eleves e
            ON i.idEleve = e.idEleve
        INNER JOIN classes c
            ON i.idClasse = c.idClasse
        LEFT JOIN niveaux n
            ON c.idNiveau = n.idNiveau
        WHERE i.idClasse = :classe
        AND i.idAnnee = :annee
        ORDER BY e.nom ASC, e.prenom ASC
    ");

    $stmt->execute([
        'classe' => $filtreClasse,
        'annee' => $annee['idAnnee']
    ]);

    $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
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
            i.idInscription,
            c.nomClasse,
            n.nomNiveau
        FROM inscriptions i
        INNER JOIN eleves e
            ON i.idEleve = e.idEleve
        INNER JOIN classes c
            ON i.idClasse = c.idClasse
        LEFT JOIN niveaux n
            ON c.idNiveau = n.idNiveau
        WHERE i.idAnnee = :annee
        ORDER BY e.nom ASC, e.prenom ASC
    ");

    $stmt->execute([
        'annee' => $annee['idAnnee']
    ]);

    $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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
// FPDF STANDARD NE GERE PAS DIRECTEMENT UTF-8
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

class ListeInscriptionsPDF extends FPDF
{
    public $nomEcole;
    public $classe;
    public $niveau;
    public $annee;

    // -------------------------------------------------
    // EN-TETE
    // -------------------------------------------------

    function Header()
    {
        $this->SetFont('Arial', 'B', 20);

        $this->Cell(
            0,
            10,
            pdfText($this->nomEcole),
            0,
            1,
            'C'
        );

        $this->SetFont('Arial', 'B', 16);

        $this->Cell(
            0,
            10,
            pdfText('LISTE DES ÉLÈVES INSCRITS'),
            0,
            1,
            'C'
        );

        $this->SetFont('Arial', '', 13);

        if ($this->classe && $this->classe !== 'Toutes les classes') {
            $this->Cell(
                0,
                8,
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
        } else {
            $this->Cell(
                0,
                8,
                pdfText('Toutes les classes'),
                0,
                1,
                'C'
            );
        }

        if (!empty($this->annee)) {
            $this->Cell(
                0,
                8,
                pdfText('Année scolaire : ' . $this->annee),
                0,
                1,
                'C'
            );
        }

        $this->Ln(5);

        // Ligne
        $this->SetDrawColor(120, 120, 120);

        $this->Line(
            10,
            $this->GetY(),
            200,
            $this->GetY()
        );

        $this->Ln(10);

        // Titre du tableau
        $this->SetFont('Arial', 'B', 11);

        $this->SetFillColor(70, 70, 70);
        $this->SetTextColor(255, 255, 255);

        $this->Cell(10, 10, '#', 1, 0, 'C', true);
        $this->Cell(30, 10, pdfText('Matricule'), 1, 0, 'C', true);
        $this->Cell(65, 10, pdfText('Nom complet'), 1, 0, 'C', true);
        $this->Cell(15, 10, pdfText('Sexe'), 1, 0, 'C', true);
        $this->Cell(35, 10, pdfText('Téléphone'), 1, 0, 'C', true);
        $this->Cell(35, 10, pdfText('Date inscription'), 1, 1, 'C', true);

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
// CREATION DU PDF
// =====================================================

$pdf = new ListeInscriptionsPDF('P', 'mm', 'A4');

$pdf->nomEcole = $nom_ecole;

$pdf->classe = $nomClasse;

$pdf->niveau = $niveauClasse;

$pdf->annee = $annee['libelle'] ?? '';

$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 20);

$pdf->AddPage();

// =====================================================
// INFORMATIONS
// =====================================================

$pdf->SetFont('Arial', '', 12);

$pdf->Cell(
    0,
    8,
    pdfText('Total élèves : ' . $total),
    0,
    1
);

$pdf->Ln(3);

// =====================================================
// TABLEAU
// =====================================================

$pdf->SetFont('Arial', '', 8);
$fill = false;

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

    $telephone = !empty($eleve['telephone']) ? $eleve['telephone'] : '-';

    $pdf->Cell(
        10,
        9,
        $index + 1,
        1,
        0,
        'C',
        $fill
    );

    $pdf->Cell(
        30,
        9,
        pdfText($eleve['matricule']),
        1,
        0,
        'L',
        $fill
    );

    $pdf->Cell(
        65,
        9,
        pdfText($nomComplet),
        1,
        0,
        'L',
        $fill
    );

    $pdf->Cell(
        15,
        9,
        $sexe,
        1,
        0,
        'C',
        $fill
    );

    $pdf->Cell(
        35,
        9,
        pdfText($telephone),
        1,
        0,
        'L',
        $fill
    );

    $pdf->Cell(
        35,
        9,
        $dateInscription,
        1,
        1,
        'C',
        $fill
    );
}

// =====================================================
// RESUME
// =====================================================

$pdf->Ln(12);

$pdf->SetFont('Arial', 'B', 14);

$pdf->Cell(
    0,
    8,
    pdfText('RÉSUMÉ'),
    0,
    1
);

$pdf->Ln(2);

$pdf->SetFont('Arial', '', 12);

$pourcentageGarcons = $total > 0
    ? round(($garcons / $total) * 100, 1)
    : 0;

$pourcentageFilles = $total > 0
    ? round(($filles / $total) * 100, 1)
    : 0;

$pdf->Cell(
    0,
    8,
    pdfText('Total : ' . $total . ' élève(s)'),
    0,
    1
);

$pdf->Cell(
    0,
    8,
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
    8,
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
// SORTIE DU FICHIER PDF
// =====================================================

$nomFichier = 'liste_inscriptions_' . date('Y-m-d') . '.pdf';
if ($filtreClasse > 0) {
    $nomFichier = 'liste_inscriptions_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $nomClasse) . '_' . date('Y-m-d') . '.pdf';
}

$pdf->Output('D', $nomFichier);
exit();
?>