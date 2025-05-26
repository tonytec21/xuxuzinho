<?php  
/**  
 * upload_pdf_crc.php  
 * ------------------------------------------------------------------  
 * Recebe um PDF com várias comunicações da CRC, armazena o arquivo,  
 * converte cada página em PNG (pdftoppm), executa OCR (tesseract),  
 * separa as comunicações, extrai campos com parseComunicacaoCRC()  
 * e grava cada registro em comunicacoes_crc, vinculando‐o ao PDF.  
 *  
 * Requisitos instalados no servidor:  
 *   • poppler-utils  (comando pdftoppm)  
 *   • tesseract-ocr  (com idioma "por")  
 * ------------------------------------------------------------------*/  
ini_set('display_errors', 1);  
error_reporting(E_ALL);  

header('Content-Type: application/json; charset=utf-8');  

require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  
require_once 'includes/parse_crc.php';  

// Configuração de caminhos para o Windows  
$PDFTOPPM_PATH = '"C:\\Program Files\\poppler-24.08.0\\Library\\bin\\pdftoppm.exe"';  
$TESSERACT_PATH = '"C:\\Program Files\\Tesseract-OCR\\tesseract.exe"';  

/* ------------------------------------------------------------------  
   0. Validação do upload  
------------------------------------------------------------------*/  
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['arquivo_pdf'])) {  
    echo json_encode(['success' => false, 'message' => 'Requisição inválida.']);  
    exit;  
}  

$file = $_FILES['arquivo_pdf'];  

if ($file['error'] !== UPLOAD_ERR_OK) {  
    echo json_encode(['success' => false, 'message' => 'Erro no upload. Código: ' . $file['error']]);  
    exit;  
}  

if (mime_content_type($file['tmp_name']) !== 'application/pdf') {  
    echo json_encode(['success' => false, 'message' => 'Envie apenas arquivos PDF.']);  
    exit;  
}  

/* ------------------------------------------------------------------  
   1. Armazenar PDF  
------------------------------------------------------------------*/  
$uploadDir   = __DIR__ . '/uploads/comunicacoes';  
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);  

$nomeBase    = pathinfo($file['name'], PATHINFO_FILENAME);  
$nomeSeguro  = preg_replace('/[^\w\-]/', '_', $nomeBase);  
$destName    = $nomeSeguro . '_' . date('Ymd_His') . '.pdf';  
$destPath    = $uploadDir . '/' . $destName;  

if (!move_uploaded_file($file['tmp_name'], $destPath)) {  
    echo json_encode(['success' => false, 'message' => 'Falha ao salvar arquivo PDF.']);  
    exit;  
}  

/* ------------------------------------------------------------------  
   2. Registrar no BD (anexos_crc_pdf)  
------------------------------------------------------------------*/  
$stmt = $pdo->prepare("  
    INSERT INTO anexos_crc_pdf (arquivo, paginas, ocr_processado)  
    VALUES (?, 0, 'nao')  
");  
$stmt->execute([ 'uploads/comunicacoes/' . $destName ]);  
$pdfId = $pdo->lastInsertId();  

/* ------------------------------------------------------------------  
   3. Converter páginas em PNG  (pdftoppm)  
------------------------------------------------------------------*/  
$tmpDir = sys_get_temp_dir() . '/crc_' . uniqid();  
if (!is_dir($tmpDir)) {  
    if (!mkdir($tmpDir, 0755, true)) {  
        $pdo->prepare("UPDATE anexos_crc_pdf SET ocr_processado='erro', mensagem_ocr=? WHERE id=?")  
            ->execute(['Falha ao criar diretório temporário: ' . $tmpDir, $pdfId]);  
        echo json_encode(['success' => false, 'message' => 'Erro ao criar diretório temporário.']);  
        exit;  
    }  
}  

// Verificar se os arquivos executáveis existem  
if (!file_exists(trim($PDFTOPPM_PATH, '"'))) {  
    $pdo->prepare("UPDATE anexos_crc_pdf SET ocr_processado='erro', mensagem_ocr=? WHERE id=?")  
        ->execute(['O executável pdftoppm não foi encontrado em: ' . trim($PDFTOPPM_PATH, '"'), $pdfId]);  
    echo json_encode(['success' => false, 'message' => 'O executável pdftoppm não foi encontrado.']);  
    exit;  
}  

if (!file_exists(trim($TESSERACT_PATH, '"'))) {  
    $pdo->prepare("UPDATE anexos_crc_pdf SET ocr_processado='erro', mensagem_ocr=? WHERE id=?")  
        ->execute(['O executável tesseract não foi encontrado em: ' . trim($TESSERACT_PATH, '"'), $pdfId]);  
    echo json_encode(['success' => false, 'message' => 'O executável tesseract não foi encontrado.']);  
    exit;  
}  

// Converter PDF para PNG com caminhos completos dos executáveis  
$destPath_win = str_replace('/', '\\', $destPath);  
$tmpDir_win = str_replace('/', '\\', $tmpDir);  

$cmd = sprintf('%s -png -r 300 "%s" "%s\\page" 2>&1', $PDFTOPPM_PATH, $destPath_win, $tmpDir_win);  
exec($cmd, $out, $ret);  

if ($ret !== 0) {  
    $errorMsg = 'Falha no pdftoppm (código '.$ret.'): ' . implode("\n", $out);  
    $pdo->prepare("UPDATE anexos_crc_pdf SET ocr_processado='erro', mensagem_ocr=? WHERE id=?")  
        ->execute([$errorMsg . "\nComando: " . $cmd, $pdfId]);  
    echo json_encode(['success' => false, 'message' => 'Erro ao converter PDF em imagens.', 'details' => $errorMsg]);  
    exit;  
}  

$imagens = glob($tmpDir . '/page-*.png');  
if (empty($imagens)) {  
    $errorMsg = 'Nenhuma imagem foi gerada pelo pdftoppm. Verifique se o PDF não está vazio ou corrompido.';  
    $pdo->prepare("UPDATE anexos_crc_pdf SET ocr_processado='erro', mensagem_ocr=? WHERE id=?")  
        ->execute([$errorMsg . "\nComando: " . $cmd, $pdfId]);  
    echo json_encode(['success' => false, 'message' => $errorMsg]);  
    exit;  
}  

$totalPaginas = count($imagens);  

/* ------------------------------------------------------------------  
   4. OCR página a página  
------------------------------------------------------------------*/  
$textoTotal = '';  
foreach ($imagens as $img) {  
    // Verificar se a imagem existe e é acessível  
    if (!file_exists($img) || !is_readable($img)) {  
        continue;  
    }  
    
    // Converter caminho para formato Windows  
    $img_win = str_replace('/', '\\', $img);  
    
    // usa idioma português; se desejar, adicione "eng" para OCR multilíngue  
    $cmd = sprintf('%s "%s" stdout -l por 2>&1', $TESSERACT_PATH, $img_win);  
    $ocr = shell_exec($cmd);  
    
    if ($ocr) {  
        $textoTotal .= "\n\n" . $ocr;  
    }  
}  

// Verificar se obtivemos algum texto  
if (empty(trim($textoTotal))) {  
    $errorMsg = 'OCR não gerou nenhum texto. Verifique se o PDF contém texto reconhecível.';  
    $pdo->prepare("UPDATE anexos_crc_pdf SET ocr_processado='erro', mensagem_ocr=? WHERE id=?")  
        ->execute([$errorMsg, $pdfId]);  
    echo json_encode(['success' => false, 'message' => $errorMsg]);  
    
    // Limpar arquivos temporários antes de sair  
    array_map('unlink', $imagens);  
    rmdir($tmpDir);  
    exit;  
}  

/* remove imagens temp */  
array_map('unlink', $imagens);  
rmdir($tmpDir);  

/* ------------------------------------------------------------------  
   5. Separar cada comunicação  
------------------------------------------------------------------*/  
$comBlocos = splitComunicacoesCRC($textoTotal);  

// Se não encontrou comunicações  
if (empty($comBlocos)) {  
    $errorMsg = 'Não foi possível identificar comunicações CRC no texto extraído.';  
    $pdo->prepare("UPDATE anexos_crc_pdf SET ocr_processado='erro', mensagem_ocr=? WHERE id=?")  
        ->execute([$errorMsg . "\n\nTexto extraído: " . substr($textoTotal, 0, 1000) . "...", $pdfId]);  
    echo json_encode(['success' => false, 'message' => $errorMsg]);  
    exit;  
}  

/* ------------------------------------------------------------------  
   6. Processar e gravar  
------------------------------------------------------------------*/  
$inseridos  = 0;  
$duplicados = 0;  
$falhos     = 0;  
$erros      = [];  
$falhas_detalhadas = []; // Lista detalhada das falhas  

$pdo->beginTransaction();  
try {  
    foreach ($comBlocos as $index => $bloco) {  
        $dados = parseComunicacaoCRC($bloco);  

        if (!$dados) {   
            $falhos++;  
            
            // Capturar o código CRC mesmo para blocos falhos  
            preg_match('/Código da comunicação: (\d+)/', $bloco, $matches);  
            $codigo_falha = isset($matches[1]) ? $matches[1] : "Não identificado";  
            
            // Capturar os 50 primeiros caracteres do bloco para ajudar na identificação  
            $preview = substr(preg_replace('/\s+/', ' ', trim($bloco)), 0, 50);  
            
            // Armazenar detalhes da falha  
            $falhas_detalhadas[] = [  
                'indice' => $index + 1,  
                'codigo' => $codigo_falha,  
                'preview' => $preview . '...'  
            ];  
            
            $erros[] = "Bloco #{$index} não reconhecido: " . substr($bloco, 0, 100) . "...";  
            continue;   
        }  

        // Verificação adicional para nomes  
        // Tratar problema específico do cônjuge nos casamentos  
        if ($dados['tipo'] == 'casamento') {  
            // Verificar se há padrão de texto que indica cônjuge  
            if (empty($dados['nome_conjuge']) && preg_match('/e (.*?), [ao] qual passou a assinar/i', $bloco, $matches)) {  
                $dados['nome_conjuge'] = trim($matches[1]);  
            }  
        }  

        // linka com o PDF  
        $dados['pdf_id'] = $pdfId;  

        // verifica duplicidade  
        $stmt = $pdo->prepare("SELECT id FROM comunicacoes_crc WHERE codigo_crc = ?");  
        $stmt->execute([$dados['codigo_crc']]);  
        if ($stmt->fetch()) { $duplicados++; continue; }  

        /* INSERT dinâmico */  
        $cols = array_keys($dados);  
        $placeholders = array_fill(0, count($cols), '?');  
        $sql  = "INSERT INTO comunicacoes_crc (" . implode(',', $cols) . ")  
                 VALUES (" . implode(',', $placeholders) . ")";  
        $pdo->prepare($sql)->execute(array_values($dados));  
        $inseridos++;  
    }  

    /* atualiza info no PDF */  
    $mensagemOcr = "Inseridos: $inseridos; Duplicados: $duplicados; Falhos: $falhos";  
    if (!empty($erros)) {  
        $mensagemOcr .= "\n\nErros:\n" . implode("\n", array_slice($erros, 0, 5));  
        if (count($erros) > 5) {  
            $mensagemOcr .= "\n... e mais " . (count($erros) - 5) . " erros.";  
        }  
    }  
    
    $pdo->prepare("  
        UPDATE anexos_crc_pdf SET paginas = ?, ocr_processado = 'sim', mensagem_ocr = ?  
        WHERE id = ?  
    ")->execute([$totalPaginas, $mensagemOcr, $pdfId]);  

    $pdo->commit();  
} catch (Exception $e) {  
    $pdo->rollBack();  
    $errorMsg = 'Falha ao gravar no banco: ' . $e->getMessage();  
    $pdo->prepare("UPDATE anexos_crc_pdf SET ocr_processado='erro', mensagem_ocr=? WHERE id=?")  
        ->execute([$errorMsg, $pdfId]);  
    echo json_encode(['success' => false, 'message' => $errorMsg]);  
    exit;  
}  

/* ------------------------------------------------------------------  
   7. Resposta  
------------------------------------------------------------------*/  
echo json_encode([  
    'success'     => true,  
    'message'     => 'Processamento concluído.',  
    'inseridos'   => $inseridos,  
    'duplicados'  => $duplicados,  
    'falhos'      => $falhos,  
    'paginas'     => $totalPaginas,  
    'comunicacoes' => count($comBlocos),  
    'falhas_detalhadas' => $falhas_detalhadas, // Lista detalhada das falhas  
    'total_estimado' => 119 // Você pode ajustar esse valor ou calculá-lo  
]);    
?>