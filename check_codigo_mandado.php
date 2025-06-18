<?php
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

$codigo = trim($_GET['codigo'] ?? '');
header('Content-Type: application/json');

if ($codigo === '') {
    echo json_encode(['success' => false, 'message' => 'Código vazio']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM mandados WHERE codigo_rastreabilidade = ? AND status != 'excluido'");
$stmt->execute([$codigo]);
$id = $stmt->fetchColumn();

echo json_encode(['success' => true, 'existe' => (bool)$id, 'id' => $id]);
