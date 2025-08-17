<?php
/**
 * cadastrar_selos_manual.php
 *  – Cadastra (ou restaura) selos informados manualmente, separados por ';' (sem espaços).
 *  – Aceita anexos comuns que serão replicados para cada selo de destino
 *    (novos e restaurados) e, opcionalmente, para selos já existentes/ativos.
 *  – Retorna JSON quando chamado por fetch/AJAX; redireciona caso contrário.
 */

ob_start();
error_reporting(E_ERROR);
date_default_timezone_set('America/Sao_Paulo');
set_time_limit(0);

require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

function json_exit(array $resp){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function log_msg($m){ error_log('['.date('Y-m-d H:i:s')."] $m"); }

/** Converte PDF em JPGs (150 DPI) usando ImageMagick (magick/convert). */
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

/** Achata $_FILES['campo'] em array uniforme de arquivos */
function flatten_files_array($filesField){
    $out = [];
    if (!isset($_FILES[$filesField])) return $out;
    $f = $_FILES[$filesField];

    // Suporta tanto '[]' quanto simples
    if (is_array($f['name'])) {
        $n = count($f['name']);
        for ($i=0; $i<$n; $i++){
            if ($f['name'][$i] === '' || $f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $out[] = [
                'name'     => $f['name'][$i],
                'type'     => $f['type'][$i],
                'tmp_name' => $f['tmp_name'][$i],
                'error'    => $f['error'][$i],
                'size'     => $f['size'][$i],
            ];
        }
    } else {
        if ($f['name'] !== '' && $f['error'] !== UPLOAD_ERR_NO_FILE){
            $out[] = [
                'name'     => $f['name'],
                'type'     => $f['type'],
                'tmp_name' => $f['tmp_name'],
                'error'    => $f['error'],
                'size'     => $f['size'],
            ];
        }
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cadastrar_selos_manual'])) {
    json_exit(['success'=>false,'message'=>'Método ou parâmetro inválido.']);
}

/* -------------------- Normalização/validação dos números ------------------ */
$raw = $_POST['numeros_selos'] ?? '';
$raw = str_replace(',', ';', $raw);
$raw = preg_replace('/\s+/', '', $raw);
$raw = preg_replace('/;{2,}/', ';', $raw);
$raw = trim($raw, ';');

if ($raw === '' || !preg_match('/^[A-Za-z0-9]+(?:;[A-Za-z0-9]+)*$/', $raw)) {
    json_exit(['success'=>false,'message'=>'Entrada inválida. Use apenas letras/números separados por “;” (sem espaços).']);
}
$tokens = array_values(array_filter(explode(';', $raw)));

/* -------------------- Processamento de selos (criar/restaurar/existentes) -- */
$inseridos=0; $restaurados=0; $duplicados=0; $erros=[];
$ids_inseridos = []; $ids_restaurados=[]; $ids_existentes=[];

foreach ($tokens as $t) {
    $numero_selo = sanitize($t);
    if ($numero_selo === '') { continue; }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id,status FROM selos WHERE numero=? FOR UPDATE");
        $stmt->execute([$numero_selo]);
        $exist = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exist) {
            if ($exist['status'] === 'ativo') {
                $duplicados++;
                $ids_existentes[] = (int)$exist['id'];
                $pdo->commit();
            } else {
                // restaurar
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
                        "Selo nº $numero_selo restaurado via cadastro manual"
                    ]);
                $restaurados++;
                $ids_restaurados[] = (int)$exist['id'];
                $pdo->commit();
            }
            continue;
        }

        // inserir novo
        $pdo->prepare("INSERT INTO selos (numero,usuario_id) VALUES (?,?)")
            ->execute([$numero_selo, $_SESSION['usuario_id']]);
        $newId = (int)$pdo->lastInsertId();
        $ids_inseridos[] = $newId;
        $inseridos++;
        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erros[] = "Selo {$numero_selo} – ".$e->getMessage();
        log_msg("Cadastro manual falhou: ".$e->getMessage());
    }
}

/* -------------------- Anexos em comum (opcional) --------------------------- */
$anexos_aplicados = 0;
$erros_anexos     = [];
$anexarEmExistentes = isset($_POST['anexar_em_existentes']);

$target_ids = array_unique(array_merge($ids_inseridos, $ids_restaurados));
if ($anexarEmExistentes) {
    $target_ids = array_unique(array_merge($target_ids, $ids_existentes));
}

/* Flatten robusto dos arquivos (suporta [] e simples) */
$files = array_merge(
    flatten_files_array('anexos_comuns'),
    flatten_files_array('anexos') // fail-safe
);
$temAnexosComuns = count($files) > 0;

if ($temAnexosComuns && count($target_ids) === 0) {
    $erros_anexos[] = 'Anexos enviados, mas não há selos de destino. Marque a opção de aplicar em existentes, se desejar.';
}

if ($temAnexosComuns && count($target_ids) > 0) {
    $tempRoot = __DIR__ . '/uploads';
    if (!is_dir($tempRoot)) { @mkdir($tempRoot, 0755, true); }
    $tempDir  = $tempRoot . '/tmp_cad_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    if (!is_dir($tempDir) && !mkdir($tempDir, 0775, true)) {
        $erros_anexos[] = 'Não foi possível criar diretório temporário de upload.';
    } else {
        $validTypes = ['application/pdf','image/jpeg','image/jpg','image/png'];
        $maxBytes   = 10 * 1024 * 1024;

        $tempFiles = [];
        $seenHash  = [];

        foreach ($files as $f) {
            $name = $f['name'];
            $type = $f['type'];
            $tmp  = $f['tmp_name'];
            $err  = $f['error'];
            $size = $f['size'];

            if ($err !== UPLOAD_ERR_OK) {
                $erros_anexos[] = "Erro no upload do arquivo {$name}.";
                continue;
            }

            $ok = true;
            if (function_exists('validar_arquivo')) {
                $val = validar_arquivo($f);
                if ($val !== true) { $ok=false; $erros_anexos[] = $val . " - Arquivo: {$name}"; }
            } else {
                if (!in_array($type, $validTypes)) {
                    $ok=false; $erros_anexos[] = "Tipo de arquivo não suportado: {$name}";
                }
                if ($size > $maxBytes) {
                    $ok=false; $erros_anexos[] = "Arquivo maior que 10MB: {$name}";
                }
            }
            if (!$ok) continue;

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $tempName = uniqid('shared_', true).'.'.$ext;
            $tempPath = $tempDir . '/' . $tempName;

            if (!move_uploaded_file($tmp, $tempPath)) {
                $erros_anexos[] = "Falha ao mover arquivo temporário: {$name}";
                continue;
            }

            // De-duplicação por conteúdo
            $hash = @sha1_file($tempPath) ?: ($name.'|'.$size);
            if (isset($seenHash[$hash])) {
                @unlink($tempPath);
                // Aviso opcional (mantive)
                $erros_anexos[] = "Arquivo duplicado ignorado: {$name}";
                continue;
            }
            $seenHash[$hash] = true;

            $tempFiles[] = ['path'=>$tempPath, 'name'=>$name, 'type'=>$type];
        }

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

                    $tipo    = $f['type'];
                    $tamanho = filesize($destPath);
                    $dirImagensRel = null;

                    $pdo->beginTransaction();
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
                        $dirImagensRel
                    ]);
                    $anexo_id = (int)$pdo->lastInsertId();

                    if ($ext === 'pdf') {
                        $dirImagensAbs = $destDir . '/' . pathinfo($baseName, PATHINFO_FILENAME);
                        $imgs = convert_pdf_to_jpg($destPath, $dirImagensAbs);
                        if ($imgs && count($imgs)>0) {
                            $dirImagensRel = "uploads/selo_{$sid}/".pathinfo($baseName, PATHINFO_FILENAME);
                            $pdo->prepare("UPDATE anexos SET diretorio_imagens=? WHERE id=?")
                                ->execute([ $dirImagensRel, $anexo_id ]);

                            $stmtImg = $pdo->prepare("INSERT INTO imagens_anexo (anexo_id, caminho, ordem) VALUES (?, ?, ?)");
                            foreach ($imgs as $idx=>$imgPathAbs) {
                                $rel = $dirImagensRel.'/'.basename($imgPathAbs);
                                $stmtImg->execute([$anexo_id, $rel, $idx+1]);
                            }
                        }
                    }

                    $pdo->commit();
                    $anexos_aplicados++;

                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $erros_anexos[] = "Erro ao registrar anexo para selo {$sid}: ".$e->getMessage();
                    log_msg("Anexo comum (manual) falhou (selo {$sid}): ".$e->getMessage());
                }
            }
        }

        // Limpeza do diretório temporário
        foreach (glob($tempDir.'/*') as $p) { @unlink($p); }
        @rmdir($tempDir);
    }
}

/* -------------------- Resposta -------------------------------------------- */
$mensagem  = "$inseridos novos selos inseridos.";
if ($restaurados) $mensagem.=" $restaurados restaurados.";
if ($duplicados)  $mensagem.=" $duplicados já existiam ativos.";
if ($anexos_aplicados) {
    $mensagem.=" {$anexos_aplicados} anexo(s) aplicados em ".count($target_ids)." selo(s).";
}
if ($anexarEmExistentes) {
    $mensagem.=" (opção de aplicar também em existentes ativada)";
}
if ($erros)       $mensagem.=" ".count($erros)." erro(s) ao processar selos.";
if ($erros_anexos)$mensagem.=" ".count($erros_anexos)." aviso(s)/erro(s) ao anexar arquivos.";

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
    json_exit($response);
}

/*  Redireciona (requisição não-AJAX) */
$q = http_build_query([
    'cad_ok' => $response['success']?1:0,
    'msg'    => urlencode($mensagem)
]);
header("Location: selos.php?$q");
exit;
