<?php
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

header('Content-Type: application/json');

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$id = intval($_POST['id']);

try {
    $stmt = $pdo->prepare("UPDATE triagem_registros SET status = 'entregue' WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registro não encontrado ou já entregue']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
