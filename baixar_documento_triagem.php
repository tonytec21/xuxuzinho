<?php
date_default_timezone_set('America/Sao_Paulo');
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'tcpdf/tcpdf.php';

/* ------------------------------------------------------------------
   1. Validação de ID
-------------------------------------------------------------------*/
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Registro inválido.');
}
$registro_id  = intval($_GET['id']);

/* ------------------------------------------------------------------
   2. Buscar registro
-------------------------------------------------------------------*/
$stmt = $pdo->prepare("SELECT protocolo FROM triagem_registros WHERE id = ?");
$stmt->execute([$registro_id]);
$registro = $stmt->fetch();
if (!$registro) {
    die('Registro não encontrado.');
}

/* ------------------------------------------------------------------
   3. Buscar anexos ativos
-------------------------------------------------------------------*/
$stmt = $pdo->prepare("
    SELECT * FROM triagem_anexos 
    WHERE registro_id = ? AND status = 'ativo'
    ORDER BY data_upload
");
$stmt->execute([$registro_id]);
$anexos = $stmt->fetchAll();
if (!$anexos) {
    die('Nenhum anexo para compilar.');
}

/* ------------------------------------------------------------------
   4. Iniciar PDF
-------------------------------------------------------------------*/
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

/* ------------------------------------------------------------------
   5. Função auxiliar (imagem proporcional)
-------------------------------------------------------------------*/
function addImageToPDF(TCPDF $pdf, string $imgPath)
{
    list($w, $h) = getimagesize($imgPath);
    $pageW  = $pdf->getPageWidth();
    $pageH  = $pdf->getPageHeight();
    $ratio  = min($pageW / $w, $pageH / $h);
    $newW   = $w * $ratio;
    $newH   = $h * $ratio;
    $x      = ($pageW - $newW) / 2;
    $y      = ($pageH - $newH) / 2;

    $pdf->AddPage();
    $pdf->Image($imgPath, $x, $y, $newW, $newH, '', '', '', false, 300);
}

/* ------------------------------------------------------------------
   6. Processar cada anexo
-------------------------------------------------------------------*/
$temp_dirs  = [];
$temp_files = [];

foreach ($anexos as $an) {
    $caminho = $an['caminho'];
    if (!file_exists($caminho)) {
        error_log("[triagem] anexo não encontrado: $caminho");
        continue;
    }

    $ext = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));

    /* ------ imagens ------------------------------------------------ */
    if (in_array($ext, ['jpg','jpeg','png','gif'])) {
        addImageToPDF($pdf, $caminho);
        continue;
    }

    /* ------ PDFs --------------------------------------------------- */
    if ($ext === 'pdf') {
        // 6.1 – Temos imagens extraídas?
        if ($an['diretorio_imagens'] && is_dir($an['diretorio_imagens'])) {
            $stmt = $pdo->prepare("
                SELECT caminho FROM triagem_imagens_anexo 
                WHERE anexo_id = ? ORDER BY ordem
            ");
            $stmt->execute([$an['id']]);
            $imgs = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // fallback para glob, se DB vazio
            if (!$imgs) {
                $imgs = glob($an['diretorio_imagens'].'/page_*.jpg');
                natsort($imgs);
            }

            foreach ($imgs as $img) {
                if (file_exists($img)) addImageToPDF($pdf, $img);
            }
        } 
        else {
            /* 6.2 – Converter “na hora” (ImageMagick) ----------------- */
            $tmpDir = sys_get_temp_dir() . '/pdf2img_' . uniqid();
            mkdir($tmpDir, 0777, true);
            $cmd = "magick convert -density 150 -quality 90 -background white ".
                   "-alpha remove -alpha off " .
                   escapeshellarg($caminho) . " " . escapeshellarg("$tmpDir/page_%04d.jpg");
            exec($cmd, $out, $code);

            if ($code === 0) {
                $imgs = glob("$tmpDir/page_*.jpg");
                natsort($imgs);
                foreach ($imgs as $img) {
                    addImageToPDF($pdf, $img);
                    $temp_files[] = $img;
                }
                $temp_dirs[] = $tmpDir;
            } else {
                // não converteu; gera página de aviso
                $pdf->AddPage();
                $pdf->SetFont('helvetica', '', 12);
                $pdf->MultiCell(0, 10,
                    "O anexo '{$an['nome_arquivo']}' é um PDF que não pôde ser convertido.\n" .
                    "Baixe o arquivo original para visualizá-lo.",
                    0, 'C', false, 1, '', 60);
            }
        }
    }
}

/* ------------------------------------------------------------------
   7. Output
-------------------------------------------------------------------*/
$nomeSaida = $registro['protocolo'] . ' - Documento Comprobatório.pdf';
$pdf->Output($nomeSaida, 'D');   // força download

/* ------------------------------------------------------------------
   8. Limpeza de temporários
-------------------------------------------------------------------*/
foreach ($temp_files as $f) @unlink($f);
foreach ($temp_dirs  as $d) {
    if (is_dir($d)) {
        foreach (glob("$d/*") as $f) @unlink($f);
        @rmdir($d);
    }
}
