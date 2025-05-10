<?php
date_default_timezone_set('America/Sao_Paulo');
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

header('Content-Type: application/json; charset=utf-8');

$usuario_id   = $_SESSION['usuario_id'] ?? 0;
$usuario_nome = $_SESSION['nome']        ?? 'Usuário';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Método inválido.']); exit;
}

$registro_id = intval($_POST['id'] ?? 0);
if (!$registro_id) {
    echo json_encode(['success'=>false,'message'=>'ID inválido.']); exit;
}

/* ---------- valida se existe e está pendente -------------------- */
$stmt = $pdo->prepare("SELECT status FROM triagem_registros WHERE id = ?");
$stmt->execute([$registro_id]);
$reg = $stmt->fetch();

if (!$reg || $reg['status'] !== 'pendente') {
    echo json_encode(['success'=>false,'message'=>'Registro não encontrado ou já processado.']); exit;
}

/* ---------- aprova ---------------------------------------------- */
$ok = $pdo->prepare("
    UPDATE triagem_registros
       SET status='aprovado',
           data_aprovacao = NOW(),
           aprovado_por   = ?
     WHERE id = ?
")->execute([$usuario_nome, $registro_id]);

echo json_encode(['success'=>$ok]);
