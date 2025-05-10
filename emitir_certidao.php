<?php
date_default_timezone_set('America/Sao_Paulo');
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

/* ------------------------------------------------------------------
   1. Somente POST
-------------------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: triagem.php?error=metodo_invalido");
    exit;
}

/* ------------------------------------------------------------------
   2. Sanitizar parâmetros
-------------------------------------------------------------------*/
$registro_id = intval($_POST['id']         ?? 0);
$numero_selo = trim($_POST['numero_selo']  ?? '');

$usuario_id   = $_SESSION['usuario_id'] ?? 0;
$usuario_nome = $_SESSION['nome']       ?? 'Usuário';

/* ------------------------------------------------------------------
   3. Validações básicas
-------------------------------------------------------------------*/
if (!$registro_id || $numero_selo === '') {
    jsonOut(false, 'Dados inválidos.');
}

$stmt = $pdo->prepare("SELECT status FROM triagem_registros WHERE id = ?");
$stmt->execute([$registro_id]);
$reg = $stmt->fetch();

if (!$reg) {
    jsonOut(false, 'Registro não encontrado.');
}
if ($reg['status'] !== 'aprovado') {
    jsonOut(false, 'Somente registros aprovados podem ser emitidos.');
}

/* ------------------------------------------------------------------
   4. Atualizar registro
-------------------------------------------------------------------*/
$stmt = $pdo->prepare("
    UPDATE triagem_registros 
    SET status      = 'emitido',
        data_emissao= NOW(),
        emitido_por = ?,
        numero_selo = ?
    WHERE id = ?
");
$ok = $stmt->execute([$usuario_nome, $numero_selo, $registro_id]);

/* ------------------------------------------------------------------
   5. Log opcional (se existir logs_sistema)
-------------------------------------------------------------------*/
try {
    $pdo->prepare("
        INSERT INTO logs_sistema
        (usuario_id, usuario_nome, acao, tabela_afetada, registro_id, data_hora, detalhes)
        VALUES (?,?,?,?,?,NOW(),?)
    ")->execute([
        $usuario_id, $usuario_nome,
        'emitir_certidao', 'triagem_registros', $registro_id,
        "Selo nº: $numero_selo"
    ]);
} catch (Exception $e) { /* tabela pode não existir – ignora */ }

/* ------------------------------------------------------------------
   6. Resposta
-------------------------------------------------------------------*/
if ($ok) {
    jsonOut(true);
} else {
    jsonOut(false, 'Erro ao atualizar registro.');
}

/* ------------------------------------------------------------------
   Função helper – always JSON
-------------------------------------------------------------------*/
function jsonOut(bool $success, string $msg = '')
{
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
