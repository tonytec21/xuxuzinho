<?php
date_default_timezone_set('America/Sao_Paulo'); 
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$selo_id = $_POST['selo_id'] ?? 0;

if (!$selo_id || !$usuario_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

// Verificar se o selo pertence ao usuário
$stmt = $pdo->prepare("SELECT id FROM selos WHERE id = ?");
$stmt->execute([$selo_id]);
if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Selo não encontrado.']);
    exit;
}

// Verificar se já houve pelo menos 1 download
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM downloads_selo WHERE selo_id = ?");
$stmt->execute([$selo_id]);
$total_downloads = $stmt->fetchColumn();

if ($total_downloads < 1) {
    echo json_encode([
        'success' => false,
        'message' => 'Este documento ainda não foi baixado. Só é possível marcar como enviado após ao menos 1 download.'
    ]);
    exit;
}

$stmt = $pdo->prepare("UPDATE selos SET enviado_portal = 'sim', data_envio_portal = NOW(), enviado_por = ? WHERE id = ?");
$stmt->execute([$_SESSION['nome'], $selo_id]);

// Atualizar status de envio
$stmt = $pdo->prepare("UPDATE selos SET enviado_portal = 'sim', data_envio_portal = NOW() WHERE id = ?");
if ($stmt->execute([$selo_id])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar o selo.']);
}
