<?php
/**
 * Cadastra (ou restaura) um selo a partir de um PDF analisado por OCR
 * Requer: Poppler (pdftoppm) e Tesseract instalados nos caminhos abaixo.
 */
ob_start();
date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ERROR);

require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

/* ---------------------------------------------------------
   1. Caminhos dos executáveis externos
--------------------------------------------------------- */
$PDFTOPPM_PATH   = '"C:\\Program Files\\poppler-24.08.0\\Library\\bin\\pdftoppm.exe"';
$TESSERACT_PATH  = '"C:\\Program Files\\Tesseract-OCR\\tesseract.exe"';

/* ---------------------------------------------------------
   2. Validação básica da requisição
--------------------------------------------------------- */
header('Content-Type: application/json; charset=utf-8');
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método inválido (use POST).';
    echo json_encode($response); exit;
}

if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'Envie um único arquivo PDF.';
    echo json_encode($response); exit;
}

if (strtolower(pathinfo($_FILES['pdf']['name'], PATHINFO_EXTENSION)) !== 'pdf') {
    $response['message'] = 'Somente PDFs são aceitos.';
    echo json_encode($response); exit;
}

/* ---------------------------------------------------------
   3. Converter a 1ª página do PDF em PNG
--------------------------------------------------------- */
$tmpPdf   = $_FILES['pdf']['tmp_name'];
$tmpDir   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_selo_' . uniqid();
@mkdir($tmpDir, 0777, true);
$outPref  = $tmpDir . DIRECTORY_SEPARATOR . 'page';

$cmdConvert = "$PDFTOPPM_PATH -f 1 -l 1 -png \"$tmpPdf\" \"$outPref\"";
exec($cmdConvert, $dummy, $ret);
if ($ret !== 0 || !($png = glob($outPref . '-1.png')[0] ?? null)) {
    rrmdir($tmpDir);
    $response['message'] = 'Falha ao converter PDF (pdftoppm).';
    echo json_encode($response); exit;
}

/* ---------------------------------------------------------
   4. OCR da imagem gerada
--------------------------------------------------------- */
$cmdOcr = "$TESSERACT_PATH \"$png\" stdout -l por";
exec($cmdOcr, $lines, $ret);
$texto = implode("\n", $lines);

if ($ret !== 0 || !preg_match('/Selo:\s*([A-Z0-9]{10,})\s*,/iu', $texto, $m)) {
    rrmdir($tmpDir);
    $response['message'] = 'Número do selo não encontrado no PDF.';
    echo json_encode($response); exit;
}
$numeroSelo = trim($m[1]);

/* ---------------------------------------------------------
   5. Inserir (ou restaurar) no banco
--------------------------------------------------------- */
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, status FROM selos WHERE numero = ?");
    $stmt->execute([$numeroSelo]);
    $found = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($found && $found['status'] === 'ativo') {
        // Já existe
        $pdo->commit();
        rrmdir($tmpDir);
        echo json_encode([
            'success'  => true,
            'exists'   => true,
            'selo_id'  => $found['id'],
            'message'  => 'Selo já cadastrado.',
        ]);
        exit;
    }

    if ($found && $found['status'] === 'excluido') {
        // Restaurar
        $pdo->prepare("UPDATE selos SET status='ativo', data_exclusao=NULL WHERE id = ?")
            ->execute([$found['id']]);
        $seloId = $found['id'];
    } else {
        // Inserir novo
        $stmt = $pdo->prepare("INSERT INTO selos (numero, usuario_id) VALUES (?, ?)");
        $stmt->execute([$numeroSelo, $_SESSION['usuario_id']]);
        $seloId = $pdo->lastInsertId();
    }

    $pdo->commit();
    rrmdir($tmpDir);
    echo json_encode([
        'success' => true,
        'selo_id' => $seloId,
        'message' => "Selo $numeroSelo cadastrado com sucesso.",
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    rrmdir($tmpDir);
    $response['message'] = 'Erro no banco: ' . $e->getMessage();
    echo json_encode($response);
}

/* ---------------------------------------------------------
   Função utilitária p/ limpar diretório temporário
--------------------------------------------------------- */
function rrmdir($dir) {
    if (!is_dir($dir)) return;
    foreach (array_diff(scandir($dir), ['.','..']) as $f) {
        $p = "$dir/$f";
        is_dir($p) ? rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}
