<?php
date_default_timezone_set('America/Sao_Paulo');
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Método inválido']); exit;
}

$id = intval($_POST['id'] ?? 0);
$motivo = trim($_POST['motivo'] ?? '');

if (!$id)               { echo json_encode(['success'=>false,'message'=>'ID inválido']); exit; }
if ($motivo === '')     { echo json_encode(['success'=>false,'message'=>'Motivo obrigatório']); exit; }

$stmt = $pdo->prepare("SELECT status FROM triagem_registros WHERE id=?");
$stmt->execute([$id]);
$reg = $stmt->fetch();
if (!$reg)              { echo json_encode(['success'=>false,'message'=>'Registro não encontrado']); exit; }
if ($reg['status']!=='pendente'){
    echo json_encode(['success'=>false,'message'=>'Somente registros pendentes podem ser rejeitados']); exit;
}

$ok = $pdo->prepare("UPDATE triagem_registros SET status='rejeitado', motivo_rejeicao=?, data_rejeicao=NOW() WHERE id=?")
          ->execute([$motivo,$id]);

echo json_encode(['success'=>$ok]);
