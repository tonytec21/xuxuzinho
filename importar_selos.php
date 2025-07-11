<?php
/**
 * importar_selos.php
 *  – Importa selos a partir de um arquivo XLSX/XLS.
 *  – Usa PhpSpreadsheet via Composer (vendor/autoload.php).
 *  – Retorna JSON quando chamado por fetch/AJAX; redireciona caso contrário.
 */

ob_start();
error_reporting(E_ERROR);                       // ocultar warnings/notices
date_default_timezone_set('America/Sao_Paulo');

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

/* ------------------------------------------------------------------
 |  Validação da requisição
 *------------------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['importar_selos'])) {
    json_exit(['success'=>false,'message'=>'Método ou parâmetro inválido.']);
}

if (!isset($_FILES['xlsx_file']) || $_FILES['xlsx_file']['error']!==UPLOAD_ERR_OK) {
    json_exit(['success'=>false,'message'=>'Arquivo não enviado ou corrompido.']);
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
                $duplicados++; $pdo->commit(); continue;
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
            $restaurados++; $pdo->commit(); continue;
        }

        // insere selo novo
        $pdo->prepare("INSERT INTO selos (numero,usuario_id) VALUES (?,?)")
            ->execute([$numero_selo, $_SESSION['usuario_id']]);
        $inseridos++;
        $pdo->commit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $erros[] = "Linha $rowIndex – ".$e->getMessage();
        log_msg("Import XLSX falhou: ".$e->getMessage());
    }
}

/* ------------------------------------------------------------------
 |  Resposta
 *------------------------------------------------------------------*/
$mensagem  = "$inseridos novos selos inseridos.";
if ($restaurados) $mensagem.=" $restaurados restaurados.";
if ($duplicados)  $mensagem.=" $duplicados já existiam ativos.";
if ($erros)       $mensagem.=" ".count($erros)." erro(s) durante o processo.";

$response = [
    'success' => ($inseridos+$restaurados)>0,
    'message' => $mensagem,
    'errors'  => $erros
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
