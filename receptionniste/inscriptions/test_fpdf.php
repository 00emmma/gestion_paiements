<?php
// receptionniste/inscriptions/test_fpdf.php

// 1. Charger FPDF avec le chemin exact validé
$fpdfPath = 'D:/PROJET ACADEMIQUE/gestion_paiements/fpdf119/fpdf119/fpdf.php';

if (!file_exists($fpdfPath)) {
    die('❌ Erreur : Le fichier fpdf.php est introuvable au chemin : <strong>' . $fpdfPath . '</strong>');
}

require_once $fpdfPath;

// 2. Créer une instance PDF simple
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// 3. Afficher un texte de test
$pdf->Cell(0, 10, utf8_decode('Test de génération FPDF réussi !'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, utf8_decode('Si vous voyez ce PDF, FPDF fonctionne parfaitement.'), 0, 1, 'C');

// 4. Sortie du fichier PDF dans le navigateur
$pdf->Output('I', 'test.pdf');
exit();
?>