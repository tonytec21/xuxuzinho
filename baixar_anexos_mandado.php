<?php
/****************************************************************************
 *  baixar_anexos_mandado.php
 *  ------------------------------------------------------------------------
 *  Compila TODOS os anexos “ativos” de um mandado em um único PDF:
 *    • imagens → colocadas como páginas
 *    • PDF     → extraído para imagens se já houver miniaturas salvas ou,
 *                em último caso, convertendo “na hora” via ImageMagick
 *  Saída: download forçado  (Content-Disposition: attachment)
 *  Requer:  TCPDF  +  ImageMagick‘s “convert” (quando preciso gerar páginas)
 ****************************************************************************/

date_default_timezone_set('America/Sao_Paulo');
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'tcpdf/tcpdf.php';

/* ------------------------------------------------------------------ 1. ID */
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    exit('Mandado inválido.');
}
$mandado_id = (int)$_GET['id'];

/* ------------------------------------------------------------------ 2. Mandado existe? */
$mandado = $pdo->prepare("SELECT codigo_rastreabilidade FROM mandados 
                           WHERE id = ? AND status != 'excluido'");
$mandado->execute([$mandado_id]);
$mandado = $mandado->fetch();
if (!$mandado) exit('Mandado não encontrado.');

/* ------------------------------------------------------------------ 3. Anexos ativos */
$anexos = $pdo->prepare("
    SELECT * FROM mandados_anexos
     WHERE mandado_id = ? AND status = 'ativo'
  ORDER BY data_upload
");
$anexos->execute([$mandado_id]);
$anexos = $anexos->fetchAll();
if (!$anexos) exit('Nenhum anexo para compilar.');

/* ------------------------------------------------------------------ 4. Iniciar PDF */
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetMargins(0,0,0);
$pdf->SetAutoPageBreak(false,0);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

/* ------------------------------------------------------------------ 5. Helper img ⇒ página */
function addImageToPDF(TCPDF $pdf, string $imgPath) {
    list($w,$h) = getimagesize($imgPath);
    $pw = $pdf->getPageWidth();
    $ph = $pdf->getPageHeight();
    $ratio = min($pw/$w, $ph/$h);
    $nw = $w*$ratio;  $nh = $h*$ratio;
    $x = ($pw - $nw)/2; $y = ($ph - $nh)/2;
    $pdf->AddPage();
    $pdf->Image($imgPath, $x, $y, $nw, $nh, '', '', '', false, 300);
}

/* ------------------------------------------------------------------ 6. Loop anexos */
$temp_dirs  = [];
$temp_files = [];

foreach ($anexos as $ax) {
    $path = $ax['caminho'];
    if (!file_exists($path)) { error_log("[mandados] anexo ausente: $path"); continue; }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    /* -- IMAGENS --------------------------------------------------- */
    if (in_array($ext, ['jpg','jpeg','png','gif'])) {
        addImageToPDF($pdf, $path);
        continue;
    }

    /* -- PDFs ------------------------------------------------------ */
    if ($ext === 'pdf') {
        /* 6.1 — Imagens já extraídas? (campo diretorio_imagens) */
        if (!empty($ax['diretorio_imagens']) && is_dir($ax['diretorio_imagens'])) {
            // tenta pegar (talvez) cache no banco
            $imgs = [];
            $stmt = $pdo->prepare("
                SELECT caminho FROM mandados_imagens_anexo
                 WHERE anexo_id = ? ORDER BY ordem
            ");
            $stmt->execute([$ax['id']]);
            $imgs = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!$imgs) { // fallback glob
                $imgs = glob($ax['diretorio_imagens'].'/page_*.jpg');
                natsort($imgs);
            }

            foreach ($imgs as $img) 
                if (file_exists($img)) addImageToPDF($pdf, $img);

            continue;
        }

        /* 6.2 — Converter “on the fly” com ImageMagick -------------- */
        $tmpDir = sys_get_temp_dir().'/pdf2img_'.uniqid();
        mkdir($tmpDir, 0777, true);
        $cmd = 'magick convert -density 150 -quality 90 -background white '.
               '-alpha remove -alpha off '
               . escapeshellarg($path) . ' ' . escapeshellarg("$tmpDir/page_%04d.jpg");
        exec($cmd, $o, $ret);

        if ($ret === 0) {
            $imgs = glob("$tmpDir/page_*.jpg");
            natsort($imgs);
            foreach ($imgs as $img) {
                addImageToPDF($pdf, $img);
                $temp_files[] = $img;
            }
            $temp_dirs[] = $tmpDir;
        } else {
            // falhou conversão → página aviso
            $pdf->AddPage();
            $pdf->SetFont('helvetica','',12);
            $pdf->MultiCell(0,10,
                "O anexo '{$ax['nome_arquivo']}' é um PDF protegido ou com formato " .
                "que não pôde ser convertido. Baixe o arquivo original para visualização.",
                0,'C',false,1,'',80);
        }
    }
}

/* ------------------------------------------------------------------ 7. Download */
$nome = $mandado['codigo_rastreabilidade'].' - Anexos.pdf';
$pdf->Output($nome, 'D');

/* ------------------------------------------------------------------ 8. Clean-up */
foreach ($temp_files as $f) @unlink($f);
foreach ($temp_dirs as $d) {
    if (is_dir($d)) {
        foreach (glob("$d/*") as $f) @unlink($f);
        @rmdir($d);
    }
}
