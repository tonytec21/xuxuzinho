<?php
ob_start();
date_default_timezone_set('America/Sao_Paulo');

// Suprimir avisos que atrapalham JSON
error_reporting(E_ERROR);

require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

/* ---------- 1. Validações iniciais ---------- */
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!isset($_POST['livro_id']) || !is_numeric($_POST['livro_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID do livro não fornecido ou inválido']);
    exit;
}

$livro_id = (int) $_POST['livro_id'];

/* ---------- 2. Busca do livro ---------- */
$stmt = $pdo->prepare("SELECT * FROM livros WHERE id = ?");
$stmt->execute([$livro_id]);
$livro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$livro) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Livro não encontrado']);
    exit;
}

/* ---------- 3. Validação de arquivos ---------- */
if (!isset($_FILES['arquivos']) || empty($_FILES['arquivos']['name'][0])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado']);
    exit;
}

/* ---------- 4. Diretórios ---------- */
$diretorio_base  = 'uploads/';
$diretorio_livro = $diretorio_base . 'livro_' . $livro_id . '/';
$diretorio_paginas = $diretorio_livro . 'paginas/';

foreach ([$diretorio_base, $diretorio_livro, $diretorio_paginas] as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
        chmod($dir, 0777);
    }
}

/* ---------- 5. Tmp do ImageMagick ---------- */
$temp_dir = sys_get_temp_dir() . '/imagick_temp_' . uniqid();
mkdir($temp_dir, 0777, true);
chmod($temp_dir, 0777);
putenv("MAGICK_TMPDIR={$temp_dir}");

/* ---------- 6. Upload / processamento ---------- */
$arquivos_processados = 0;
$erros = [];

$pdo->beginTransaction();
try {
    foreach ($_FILES['arquivos']['name'] as $key => $nomeOriginal) {
        if (empty($nomeOriginal)) continue;

        /* ---- 6.1 Erros de upload / tipo / tamanho ---- */
        if ($_FILES['arquivos']['error'][$key] !== UPLOAD_ERR_OK) {
            $erros[] = "Erro no upload do arquivo {$nomeOriginal}: " .
                       traduzirErroUpload($_FILES['arquivos']['error'][$key]);
            continue;
        }
        if ($_FILES['arquivos']['size'][$key] > 2 * 1024 * 1024 * 1024) {
            $erros[] = "O arquivo {$nomeOriginal} excede 2 GB.";
            continue;
        }
        $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
            $erros[] = "Tipo não permitido ({$nomeOriginal}).";
            continue;
        }

        /* ---- 6.2 Move p/ diretório do livro ---- */
        $nomeUnico = time() . '_' . uniqid() . '.' . $ext;
        $caminhoCompleto = $diretorio_livro . $nomeUnico;

        if (!move_uploaded_file($_FILES['arquivos']['tmp_name'][$key], $caminhoCompleto)) {
            $erros[] = "Falha ao mover {$nomeOriginal}.";
            continue;
        }
        chmod($caminhoCompleto, 0666);

        /* ---- 6.3 Registra anexo ---- */
        $stmt = $pdo->prepare("
            INSERT INTO anexos_livros
            (livro_id,nome_arquivo,caminho,tipo_arquivo,tamanho,data_upload,usuario_id)
            VALUES (?,?,?,?,?,NOW(),?)
        ");
        if (!$stmt->execute([
            $livro_id, $nomeOriginal, $caminhoCompleto,
            $_FILES['arquivos']['type'][$key],
            $_FILES['arquivos']['size'][$key],
            $_SESSION['usuario_id']
        ])) {
            $erros[] = "Falha ao registrar anexo {$nomeOriginal}.";
            continue;
        }
        $anexo_id = $pdo->lastInsertId();

        /* ---- 6.4 Processa ---- */
        $resultado = ($ext === 'pdf')
            ? processarPDF($caminhoCompleto, $livro_id, $anexo_id, $pdo, $diretorio_paginas)
            : processarImagem($caminhoCompleto, $livro_id, $anexo_id, $pdo, $diretorio_paginas);

        if (!$resultado['sucesso']) {
            $erros[] = $resultado['mensagem'];
            continue;
        }

        $arquivos_processados++;
    }

    /* ---------- 7. Commit ou rollback ---------- */
    if ($arquivos_processados > 0) {
        $pdo->commit();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "Upload concluído. {$arquivos_processados} arquivo(s) processado(s) com sucesso.",
            'erros'   => $erros
        ]);
    } else {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Nenhum arquivo processado.',
            'erros'   => $erros
        ]);
    }
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erro ao processar arquivos: ' . $e->getMessage()]);
    exit;
}

/* =========================================================
   FUNÇÕES AUXILIARES
   ========================================================= */

/* ---------- PDF ---------- */
function processarPDF($pdfPath, $livro_id, $anexo_id, $pdo, $dirPaginas)
{
    if (!file_exists($pdfPath) || !is_readable($pdfPath)) {
        return ['sucesso' => false, 'mensagem' => 'PDF inacessível.'];
    }

    /* --- Conf livro / última página --- */
    $livro = $pdo->query("SELECT * FROM livros WHERE id = $livro_id")->fetch(PDO::FETCH_ASSOC);
    $ultimaInfo = $pdo->query("
        SELECT MAX(numero_pagina) ultimo_pg,
               MAX(numero_folha)  ultima_fl
        FROM paginas_livro WHERE livro_id = $livro_id
    ")->fetch(PDO::FETCH_ASSOC);

    $proxPag    = $ultimaInfo['ultimo_pg'] ? $ultimaInfo['ultimo_pg'] + 1 : 1;
    $proxFolha  = $ultimaInfo['ultima_fl'] ? $ultimaInfo['ultima_fl'] + 1 : 1;
    $totalAntes = $proxPag - 1;                               // páginas já existentes

    /* --- Imagick --- */
    $im = new Imagick();
    $im->setResolution(300, 300);
    $im->readImage(realpath($pdfPath));
    $numPaginas = $im->getNumberImages();
    if ($numPaginas <= 0) {
        return ['sucesso' => false, 'mensagem' => 'PDF sem páginas.'];
    }

    /* --- Loop páginas --- */
    for ($i = 0; $i < $numPaginas; $i++) {
        $im->setIteratorIndex($i);
        $pgImg = $im->getImage();
        $pgImg->setImageFormat('jpg');
        $pgImg->setImageCompressionQuality(100);

        $nomeArq = 'pagina_' . sprintf('%04d', $proxPag) . '.jpg';
        $destino = $dirPaginas . $nomeArq;
        $pgImg->writeImage($destino);
        chmod($destino, 0666);

        /* ----- cálculo de folha / termo ----- */
        $paginaAbsoluta = $totalAntes + $i;                    // 0-based
        $ehVerso = ($livro['contagem_frente_verso'] && ($paginaAbsoluta % 2 == 1));
        $numFolha = $ehVerso ? ($proxFolha - 1) : $proxFolha;

        if ($livro['modo_termo'] === 'termos_por_pagina') {
            $termoInicial = $livro['termo_inicial']
                          + $paginaAbsoluta * $livro['termos_por_pagina'];
            $termoFinal   = $termoInicial + $livro['termos_por_pagina'] - 1;
        } else {                                              // paginas_por_termo
            $pagsPorTermo = max(1, intval($livro['paginas_por_termo']));
            $termoInicial = $livro['termo_inicial']
                          + floor($paginaAbsoluta / $pagsPorTermo);
            $termoFinal   = $termoInicial;                    // mesmo valor
        }

        /* ----- insert ----- */
        $stmt = $pdo->prepare("
            INSERT INTO paginas_livro
            (livro_id,anexo_id,numero_pagina,numero_folha,eh_verso,caminho,
             termo_inicial,termo_final,data_cadastro)
            VALUES (?,?,?,?,?,?,?,?,NOW())
        ");
        $stmt->execute([
            $livro_id, $anexo_id, $proxPag, $numFolha,
            $ehVerso ? 1 : 0, $destino, $termoInicial, $termoFinal
        ]);

        /* ----- incrementos ----- */
        $proxPag++;
        if (!$ehVerso || !$livro['contagem_frente_verso']) $proxFolha++;

        $pgImg->clear(); $pgImg->destroy();
    }

    $im->clear(); $im->destroy();
    return ['sucesso' => true, 'mensagem' => "PDF processado ({$numPaginas} páginas)."];
}

/* ---------- IMAGEM ---------- */
function processarImagem($imgPath, $livro_id, $anexo_id, $pdo, $dirPaginas)
{
    static $contador = 0;                                    // controla frente/verso

    if (!file_exists($imgPath)) {
        return ['sucesso' => false, 'mensagem' => 'Imagem não encontrada.'];
    }

    /* --- livro & última página --- */
    $livro = $pdo->query("SELECT * FROM livros WHERE id = $livro_id")->fetch(PDO::FETCH_ASSOC);
    $ultima = $pdo->query("
        SELECT MAX(numero_pagina) ultimo_pg,
               MAX(numero_folha)  ultima_fl
        FROM paginas_livro WHERE livro_id = $livro_id
    ")->fetch(PDO::FETCH_ASSOC);

    $proxPag = $ultima['ultimo_pg'] ? $ultima['ultimo_pg'] + 1 : 1;
    $proxFolhaBase = $ultima['ultima_fl'] ? $ultima['ultima_fl'] + 1 : 1;

    /* --- total de páginas já existentes (p/ termo quando paginas_por_termo) --- */
    $totalPaginasAntes = $proxPag - 1;

    /* --- verso / folha --- */
    $ehVerso = ($livro['contagem_frente_verso'] && ($contador % 2 == 1));
    $numFolha = $ehVerso ? $proxFolhaBase - 1 : $proxFolhaBase;

    /* --- termo --- */
    if ($livro['modo_termo'] === 'termos_por_pagina') {
        $termoInicial = $livro['termo_inicial']
                      + $totalPaginasAntes * $livro['termos_por_pagina'];
        $termoFinal   = $termoInicial + $livro['termos_por_pagina'] - 1;
    } else {                                                 // paginas_por_termo
        $pagsPorTermo = max(1, intval($livro['paginas_por_termo']));
        $termoInicial = $livro['termo_inicial']
                      + floor($totalPaginasAntes / $pagsPorTermo);
        $termoFinal   = $termoInicial;
    }

    /* --- copia imagem --- */
    $nomeArq = 'pagina_' . sprintf('%04d', $proxPag) . '.jpg';
    $destino = $dirPaginas . $nomeArq;
    copy($imgPath, $destino);
    chmod($destino, 0666);

    /* --- insert --- */
    $stmt = $pdo->prepare("
        INSERT INTO paginas_livro
        (livro_id,anexo_id,numero_pagina,numero_folha,eh_verso,caminho,
         termo_inicial,termo_final,data_cadastro)
        VALUES (?,?,?,?,?,?,?,?,NOW())
    ");
    $stmt->execute([
        $livro_id, $anexo_id, $proxPag, $numFolha,
        $ehVerso ? 1 : 0, $destino, $termoInicial, $termoFinal
    ]);

    $contador++;
    return ['sucesso' => true, 'mensagem' => "Imagem salva como página {$proxPag}."];
}

/* ---------- tradução de erros de upload ---------- */
function traduzirErroUpload($codigo) {
    return match ($codigo) {
        UPLOAD_ERR_INI_SIZE   => 'O arquivo excede o tamanho máximo do servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'O arquivo excede o tamanho permitido pelo formulário.',
        UPLOAD_ERR_PARTIAL    => 'Upload parcial.',
        UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
        UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária ausente.',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar no disco.',
        UPLOAD_ERR_EXTENSION  => 'Upload interrompido por extensão PHP.',
        default               => 'Erro desconhecido no upload.'
    };
}
