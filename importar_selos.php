<?php
/**
 * importar_selos.php
 *  – Importa selos a partir de um arquivo XLSX/XLS.
 *  – Usa PhpSpreadsheet via Composer (vendor/autoload.php).
 *  – Pode receber anexos comuns (PDF/JPG/JPEG/PNG) que serão replicados
 *    para cada selo processado (novos e restaurados) e, opcionalmente,
 *    também para selos já existentes/ativos quando solicitado.
 *  – Retorna JSON quando chamado por fetch/AJAX; redireciona caso contrário.
 */

ob_start();
error_reporting(E_ERROR);                       // ocultar warnings/notices
date_default_timezone_set('America/Sao_Paulo');
set_time_limit(0);                              // NOVO: evitar timeout em importações grandes

require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

/* ------------------------------------------------------------------
 |  PhpSpreadsheet via Composer
 *------------------------------------------------------------------*/
require_once __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;

/* ------------------------------------------------------------------
 |  Helpers
 *------------------------------------------------------------------*/
function json_exit(array $resp) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function log_msg($m){ error_log('['.date('Y-m-d H:i:s')."] $m"); }

/**
 * Converte PDF em JPGs (150 DPI) usando ImageMagick (magick/convert).
 * Retorna array de caminhos das imagens ou false em erro.
 */
function convert_pdf_to_jpg($pdf_path, $output_dir) {
    if (!file_exists($output_dir)) {
        if (!mkdir($output_dir, 0777, true)) {
            log_msg("Não foi possível criar o diretório: $output_dir");
            return false;
        }
    }
    try {
        $output_pattern = $output_dir . '/page_%04d.jpg';
        $command = "magick convert -density 150 -background white -alpha remove -alpha off -quality 90 \"{$pdf_path}\" \"{$output_pattern}\"";
        log_msg("Executando comando: $command");
        exec($command, $output, $return_code);

        if ($return_code !== 0) {
            log_msg("Erro ao executar ImageMagick. Código: $return_code");
            $command_alt = "convert -density 150 -background white -alpha remove -alpha off -quality 90 \"{$pdf_path}\" \"{$output_pattern}\"";
            exec($command_alt, $output_alt, $return_code_alt);

            if ($return_code_alt !== 0) {
                log_msg("Erro ao executar comando alternativo. Código: $return_code_alt");
                return false;
            }
        }

        $image_files = glob($output_dir . '/page_*.jpg');
        if (empty($image_files)) {
            log_msg("Nenhum arquivo JPG gerado");
            return false;
        }
        sort($image_files, SORT_NATURAL);
        return $image_files;

    } catch (Throwable $e) {
        log_msg("Exceção ao converter PDF: " . $e->getMessage());
        return false;
    }
}

/* ------------------------------------------------------------------
 |  Validação da requisição
 *------------------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['importar_selos'])) {
    json_exit(['success'=>false,'message'=>'Método ou parâmetro inválido.']);
}

if (!isset($_FILES['xlsx_file']) || $_FILES['xlsx_file']['error']!==UPLOAD_ERR_OK) {
    json_exit(['success'=>false,'message'=>'Arquivo XLSX não enviado ou corrompido.']);
}

/* ---- extensão e MIME ----------------------------------------------------*/
$nomeOriginal = $_FILES['xlsx_file']['name'];
$tmpPath      = $_FILES['xlsx_file']['tmp_name'];
$ext          = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

$allowedExt   = ['xlsx','xls'];
$allowedMime  = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel'
];

$mime = mime_content_type($tmpPath);          // PHP FileInfo
if (!in_array($ext, $allowedExt) || !in_array($mime, $allowedMime)) {
    json_exit(['success'=>false,'message'=>'Formato inválido. Envie um .xlsx ou .xls']);
}

/* ---- parâmetros do formulário -------------------------------------------*/
$colunaSelo = (isset($_POST['coluna_selo']) && is_numeric($_POST['coluna_selo']) && $_POST['coluna_selo'] > 0)
    ? (int)$_POST['coluna_selo']
    : 3;
$pularCab   = isset($_POST['pular_cabecalho']);
$anexarEmExistentes = isset($_POST['anexar_em_existentes']);   // NOVO

/* ------------------------------------------------------------------
 |  Lê a planilha (somente dados, ignora estilos)
 *------------------------------------------------------------------*/
try {
    if ($ext === 'xlsx') {
        $reader = new Xlsx();
    } else {
        $reader = new Xls();
    }
    $reader->setReadDataOnly(true);           // evita warnings de estilos
    $spreadsheet = $reader->load($tmpPath);
    $sheet       = $spreadsheet->getActiveSheet();
} catch (Throwable $e) {
    json_exit(['success'=>false,'message'=>'Erro ao abrir planilha: '.$e->getMessage()]);
}

/* ------------------------------------------------------------------
 |  Processa linhas
 *------------------------------------------------------------------*/
$inseridos=0; $restaurados=0; $duplicados=0; $erros=[];
$ids_inseridos = []; $ids_restaurados=[]; $ids_existentes=[];

$rowIdxStart = $pularCab ? 2 : 1;

foreach ($sheet->getRowIterator($rowIdxStart) as $row) {
    $rowIndex = $row->getRowIndex();
    $valor    = trim((string)$sheet->getCellByColumnAndRow($colunaSelo, $rowIndex)->getValue());
    if ($valor==='') { continue; }

    $numero_selo = sanitize($valor);

    try {
        $pdo->beginTransaction();

        // procura selo existente
        $stmt = $pdo->prepare("SELECT id,status FROM selos WHERE numero=? FOR UPDATE");
        $stmt->execute([$numero_selo]);
        $exist = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exist) {
            if ($exist['status']==='ativo') {
                $duplicados++; 
                $ids_existentes[] = (int)$exist['id'];
                $pdo->commit(); 
                continue;
            }
            // restaura selo inativo/excluído
            $pdo->prepare("UPDATE selos SET status='ativo',data_exclusao=NULL WHERE id=?")
                ->execute([$exist['id']]);

            // log
            $pdo->prepare("INSERT INTO logs_sistema
                    (usuario_id,usuario_nome,acao,tabela_afetada,registro_id,data_hora,detalhes)
                    VALUES (?,?,?,?,?,NOW(),?)")
                ->execute([
                    $_SESSION['usuario_id'],
                    $_SESSION['nome'],
                    'restauracao',
                    'selos',
                    $exist['id'],
                    "Selo nº $numero_selo restaurado via importação XLSX"
                ]);
            $restaurados++; 
            $ids_restaurados[] = (int)$exist['id'];
            $pdo->commit(); 
            continue;
        }

        // insere selo novo
        $pdo->prepare("INSERT INTO selos (numero,usuario_id) VALUES (?,?)")
            ->execute([$numero_selo, $_SESSION['usuario_id']]);
        $newId = (int)$pdo->lastInsertId();
        $ids_inseridos[] = $newId;
        $inseridos++;
        $pdo->commit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $erros[] = "Linha $rowIndex – ".$e->getMessage();
        log_msg("Import XLSX falhou: ".$e->getMessage());
    }
}

/* ------------------------------------------------------------------
 |  ANEXOS COMUNS (opcional)
 *------------------------------------------------------------------*/
$anexos_aplicados = 0;
$erros_anexos     = [];

$target_ids = array_unique(array_merge($ids_inseridos, $ids_restaurados));
if ($anexarEmExistentes) {
    $target_ids = array_unique(array_merge($target_ids, $ids_existentes));
}

// Há arquivos anexos comuns?
$temAnexosComuns = isset($_FILES['anexos_comuns']) && is_array($_FILES['anexos_comuns']['name']) &&
                   count(array_filter($_FILES['anexos_comuns']['name'])) > 0;

if ($temAnexosComuns && count($target_ids) === 0) {
    $erros_anexos[] = 'Anexos enviados, mas não há selos de destino. Marque a opção de aplicar em existentes, se desejar.';
}

if ($temAnexosComuns && count($target_ids) > 0) {
    // Diretório temporário para manter os uploads antes de copiar para cada selo
    $tempRoot = __DIR__ . '/uploads';
    if (!is_dir($tempRoot)) { @mkdir($tempRoot, 0755, true); }
    $tempDir  = $tempRoot . '/tmp_import_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    if (!is_dir($tempDir) && !mkdir($tempDir, 0775, true)) {
        $erros_anexos[] = 'Não foi possível criar diretório temporário de upload.';
    } else {
        $names  = $_FILES['anexos_comuns']['name'];
        $types  = $_FILES['anexos_comuns']['type'];
        $tmps   = $_FILES['anexos_comuns']['tmp_name'];
        $errors = $_FILES['anexos_comuns']['error'];
        $sizes  = $_FILES['anexos_comuns']['size'];

        $validTypes = ['application/pdf','image/jpeg','image/jpg','image/png'];
        $maxBytes   = 10 * 1024 * 1024;

        // 1) Mover cada anexo comum para a pasta temporária
        $tempFiles = []; // cada item: ['path'=>, 'name'=>, 'type'=>]
        for ($i=0; $i<count($names); $i++) {
            if (!$names[$i]) continue;
            if ($errors[$i] !== UPLOAD_ERR_OK) {
                $erros_anexos[] = "Erro no upload do arquivo {$names[$i]}.";
                continue;
            }

            $fileInfo = [
                'name'     => $names[$i],
                'type'     => $types[$i],
                'tmp_name' => $tmps[$i],
                'error'    => $errors[$i],
                'size'     => $sizes[$i]
            ];

            // Validar usando função do sistema (se houver) ou validação mínima
            $ok = true;
            if (function_exists('validar_arquivo')) {
                $val = validar_arquivo($fileInfo);
                if ($val !== true) { $ok=false; $erros_anexos[] = $val . " - Arquivo: {$names[$i]}"; }
            } else {
                if (!in_array($fileInfo['type'], $validTypes)) {
                    $ok=false; $erros_anexos[] = "Tipo de arquivo não suportado: {$names[$i]}";
                }
                if ($fileInfo['size'] > $maxBytes) {
                    $ok=false; $erros_anexos[] = "Arquivo maior que 10MB: {$names[$i]}";
                }
            }
            if (!$ok) continue;

            $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
            $tempName = uniqid('shared_', true).'.'.$ext;
            $tempPath = $tempDir . '/' . $tempName;

            if (!move_uploaded_file($tmps[$i], $tempPath)) {
                $erros_anexos[] = "Falha ao mover arquivo temporário: {$names[$i]}";
                continue;
            }

            $tempFiles[] = ['path'=>$tempPath, 'name'=>$names[$i], 'type'=>$fileInfo['type']];
        }

        // 2) Copiar cada arquivo da temp para cada selo de destino
        foreach ($tempFiles as $f) {
            foreach ($target_ids as $sid) {
                try {
                    $destDir = __DIR__ . "/uploads/selo_{$sid}";
                    if (!is_dir($destDir)) { @mkdir($destDir, 0755, true); }

                    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                    $baseName = uniqid('', true) . '.' . $ext;
                    $destPath = $destDir . '/' . $baseName;

                    if (!copy($f['path'], $destPath)) {
                        $erros_anexos[] = "Falha ao copiar anexo para selo {$sid}: {$f['name']}";
                        continue;
                    }

                    // Preparar dados p/ banco
                    $tipo    = $f['type'];
                    $tamanho = filesize($destPath);
                    $dirImagens = null;
                    $pdo->beginTransaction();

                    // Inserir em anexos
                    $stmt = $pdo->prepare("
                        INSERT INTO anexos (selo_id, nome_arquivo, caminho, tipo, tamanho, data_upload, diretorio_imagens)
                        VALUES (?, ?, ?, ?, ?, NOW(), ?)
                    ");
                    $stmt->execute([
                        $sid,
                        $f['name'],
                        "uploads/selo_{$sid}/{$baseName}",
                        $tipo,
                        $tamanho,
                        $dirImagens
                    ]);
                    $anexo_id = (int)$pdo->lastInsertId();

                    // Se PDF -> converter para imagens
                    if ($ext === 'pdf') {
                        $dirImagensAbs = $destDir . '/' . pathinfo($baseName, PATHINFO_FILENAME);
                        $imgs = convert_pdf_to_jpg($destPath, $dirImagensAbs);
                        if ($imgs && count($imgs)>0) {
                            // Atualiza diretorio_imagens no anexo
                            $pdo->prepare("UPDATE anexos SET diretorio_imagens=? WHERE id=?")
                                ->execute([ "uploads/selo_{$sid}/".pathinfo($baseName, PATHINFO_FILENAME), $anexo_id ]);

                            $stmtImg = $pdo->prepare("INSERT INTO imagens_anexo (anexo_id, caminho, ordem) VALUES (?, ?, ?)");
                            foreach ($imgs as $idx=>$imgPathAbs) {
                                $rel = "uploads/selo_{$sid}/".pathinfo($baseName, PATHINFO_FILENAME).'/'.basename($imgPathAbs);
                                $stmtImg->execute([$anexo_id, $rel, $idx+1]);
                            }
                        }
                    }

                    $pdo->commit();
                    $anexos_aplicados++;

                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $erros_anexos[] = "Erro ao registrar anexo para selo {$sid}: ".$e->getMessage();
                    log_msg("Anexo comum falhou (selo {$sid}): ".$e->getMessage());
                }
            }
        }

        // 3) limpeza do diretório temporário
        foreach (glob($tempDir.'/*') as $p) { @unlink($p); }
        @rmdir($tempDir);
    }
}

/* ------------------------------------------------------------------
 |  Resposta
 *------------------------------------------------------------------*/
$mensagem  = "$inseridos novos selos inseridos.";
if ($restaurados) $mensagem.=" $restaurados restaurados.";
if ($duplicados)  $mensagem.=" $duplicados já existiam ativos.";
if ($anexos_aplicados) {
    $mensagem.=" {$anexos_aplicados} anexo(s) aplicados em ".count($target_ids)." selo(s).";
}
if ($anexarEmExistentes) {
    $mensagem.=" (opção de aplicar também em existentes ativada)";
}
if ($erros)       $mensagem.=" ".count($erros)." erro(s) durante o processamento da planilha.";
if ($erros_anexos)$mensagem.=" ".count($erros_anexos)." erro(s) ao anexar arquivos.";

$sucessoFinal = (($inseridos + $restaurados) > 0) || ($anexos_aplicados > 0);

$response = [
    'success' => $sucessoFinal,
    'message' => $mensagem,
    'stats'   => [
        'inseridos'  => $inseridos,
        'restaurados'=> $restaurados,
        'duplicados' => $duplicados,
        'anexos_aplicados' => $anexos_aplicados,
        'destinos'   => count($target_ids)
    ],
    'errors'  => array_merge($erros, $erros_anexos)
];

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest') {
    json_exit($response);                    // chamada via fetch/AJAX
}

/*  Redireciona (requisição não-AJAX) */
$q = http_build_query([
    'import_ok' => $response['success']?1:0,
    'msg'       => urlencode($mensagem)
]);
header("Location: selos.php?$q");
exit;
