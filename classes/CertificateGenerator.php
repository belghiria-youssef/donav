<?php
require_once(__DIR__ . '/tcpdf/tcpdf.php');

class CertificateGenerator
{
    private string $student_name, $group;
    private string $teacher_name;
    private string $activity_title;

    public function __construct(string $student_name, string $group, string $teacher_name = "Teacher", string $activity_title = "Soft Skills Activity")
    {
        $this->student_name = $student_name;
        $this->group = $group;
        $this->teacher_name = $teacher_name;
        $this->activity_title = $activity_title;
    }

    public function generateCertificate(string $filename = "certificate.pdf")
    {
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('OFPPT');
        $pdf->SetAuthor('OFPPT');
        $pdf->SetTitle('Certificat de Participation');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // Page dimensions
        $pageWidth = 297;
        $pageHeight = 210;

        // ========== DECORATIVE BORDER ==========
        // Outer border - elegant dark blue frame
        $pdf->SetDrawColor(0, 51, 102);
        $pdf->SetLineWidth(3);
        $pdf->Rect(8, 8, $pageWidth - 16, $pageHeight - 16);

        // Inner decorative border
        $pdf->SetDrawColor(0, 102, 153);
        $pdf->SetLineWidth(1);
        $pdf->Rect(12, 12, $pageWidth - 24, $pageHeight - 24);

        // Corner decorative elements
        $pdf->SetDrawColor(180, 150, 80); // Gold color
        $pdf->SetLineWidth(0.5);
        $cornerSize = 20;
        // Top-left corner
        $pdf->Line(12, 12, 12 + $cornerSize, 12);
        $pdf->Line(12, 12, 12, 12 + $cornerSize);
        // Top-right corner
        $pdf->Line($pageWidth - 12, 12, $pageWidth - 12 - $cornerSize, 12);
        $pdf->Line($pageWidth - 12, 12, $pageWidth - 12, 12 + $cornerSize);
        // Bottom-left corner
        $pdf->Line(12, $pageHeight - 12, 12 + $cornerSize, $pageHeight - 12);
        $pdf->Line(12, $pageHeight - 12, 12, $pageHeight - 12 - $cornerSize);
        // Bottom-right corner
        $pdf->Line($pageWidth - 12, $pageHeight - 12, $pageWidth - 12 - $cornerSize, $pageHeight - 12);
        $pdf->Line($pageWidth - 12, $pageHeight - 12, $pageWidth - 12, $pageHeight - 12 - $cornerSize);

        // ========== OFPPT LOGO ==========
        $logoPathPng = __DIR__ . '/../assets/ofppt_logo.png';
        $logoPathJpg = __DIR__ . '/../assets/ofppt_logo.jpg';

        // Convert PNG to JPG if needed (to avoid alpha channel issues)
        if (file_exists($logoPathPng) && !file_exists($logoPathJpg)) {
            if (function_exists('imagecreatefrompng')) {
                $img = @imagecreatefrompng($logoPathPng);
                if ($img) {
                    $w = imagesx($img);
                    $h = imagesy($img);
                    $white = imagecreatetruecolor($w, $h);
                    imagefill($white, 0, 0, imagecolorallocate($white, 255, 255, 255));
                    imagecopy($white, $img, 0, 0, 0, 0, $w, $h);
                    imagejpeg($white, $logoPathJpg, 95);
                    imagedestroy($img);
                    imagedestroy($white);
                }
            }
        }

        // Use JPG if available, otherwise try PNG
        try {
            if (file_exists($logoPathJpg)) {
                $pdf->Image($logoPathJpg, 20, 18, 35, 35, 'JPG');
            } elseif (file_exists($logoPathPng)) {
                $pdf->Image($logoPathPng, 20, 18, 35, 35, 'PNG');
            }
        } catch (Exception $e) {
            // Skip logo if there's an error
        }

        // ========== HEADER SECTION ==========
        $pdf->SetXY(0, 22);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(0, 51, 102);
        $pdf->Cell(0, 6, "ROYAUME DU MAROC", 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(0, 102, 153);
        $pdf->Cell(0, 5, "Office de la Formation Professionnelle", 0, 1, 'C');
        $pdf->Cell(0, 5, "et de la Promotion du Travail", 0, 1, 'C');

        // ========== DECORATIVE LINE ==========
        $pdf->SetDrawColor(180, 150, 80);
        $pdf->SetLineWidth(1.5);
        $pdf->Line(60, 50, $pageWidth - 60, 50);

        // ========== MAIN TITLE ==========
        $pdf->SetXY(0, 55);
        $pdf->SetFont('helvetica', 'B', 32);
        $pdf->SetTextColor(0, 51, 102);
        $pdf->Cell(0, 15, "CERTIFICAT DE PARTICIPATION", 0, 1, 'C');

        // ========== SUBTITLE ==========
        $pdf->SetFont('helvetica', '', 14);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Ln(3);
        $pdf->Cell(0, 8, "Ce certificat est décerné avec fierté à", 0, 1, 'C');

        // ========== STUDENT NAME ==========
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 28);
        $pdf->SetTextColor(0, 51, 102);
        $pdf->Cell(0, 12, mb_strtoupper($this->student_name, 'UTF-8'), 0, 1, 'C');

        // Decorative underline for name
        $nameWidth = $pdf->GetStringWidth(mb_strtoupper($this->student_name, 'UTF-8'));
        $nameX = ($pageWidth - $nameWidth) / 2;
        $pdf->SetDrawColor(180, 150, 80);
        $pdf->SetLineWidth(0.8);
        $pdf->Line($nameX - 10, $pdf->GetY() + 2, $nameX + $nameWidth + 10, $pdf->GetY() + 2);

        // ========== GROUP INFO ==========
        $pdf->Ln(8);
        $pdf->SetFont('helvetica', '', 14);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 7, "du Groupe", 0, 1, 'C');

        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetTextColor(0, 102, 153);
        $pdf->Cell(0, 10, mb_strtoupper($this->group, 'UTF-8'), 0, 1, 'C');

        // ========== ACTIVITY DESCRIPTION ==========
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', '', 13);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 7, "pour sa participation active à :", 0, 1, 'C');

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(0, 102, 153);
        $pdf->Ln(2);
        $pdf->Cell(0, 10, $this->activity_title, 0, 1, 'C');

        // ========== FOOTER SECTION ==========
        // Date on the left
        $pdf->SetXY(30, $pageHeight - 45);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(80, 6, "Fait le " . date("d/m/Y"), 0, 1, 'L');

        // Signature on the right
        // Signature line first
        $pdf->SetDrawColor(0, 51, 102);
        $pdf->SetLineWidth(0.5);
        $pdf->Line($pageWidth - 100, $pageHeight - 45, $pageWidth - 40, $pageHeight - 45);

        // Teacher name below the line
        $pdf->SetXY($pageWidth - 110, $pageHeight - 43);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(0, 51, 102);
        $pdf->Cell(80, 6, $this->teacher_name, 0, 1, 'C');

        // "Le Formateur" label at the bottom
        $pdf->SetXY($pageWidth - 110, $pageHeight - 36);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(80, 6, "Le Formateur", 0, 1, 'C');

        // ========== BOTTOM DECORATIVE LINE ==========
        $pdf->SetDrawColor(180, 150, 80);
        $pdf->SetLineWidth(1.5);
        $pdf->Line(60, $pageHeight - 20, $pageWidth - 60, $pageHeight - 20);

        // ========== OFPPT TAGLINE ==========
        $pdf->SetXY(0, $pageHeight - 18);
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->SetTextColor(0, 102, 153);
        $pdf->Cell(0, 5, "La Voie de l'Avenir", 0, 1, 'C');

        $pdf->Output($filename, 'D');
    }
}
?>