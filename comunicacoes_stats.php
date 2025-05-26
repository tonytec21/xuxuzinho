<?php
// comunicacoes_stats.php
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

header('Content-Type: application/json');

try {
    $stats = [];
    
    // Total de pendentes
    $stmt = $pdo->query("SELECT COUNT(*) FROM comunicacoes_crc WHERE status = 'pendente'");
    $stats['pendentes'] = $stmt->fetchColumn();
    
    // Total de anotadas
    $stmt = $pdo->query("SELECT COUNT(*) FROM comunicacoes_crc WHERE status = 'anotada'");
    $stats['anotadas'] = $stmt->fetchColumn();
    
    // Total de recusadas
    $stmt = $pdo->query("SELECT COUNT(*) FROM comunicacoes_crc WHERE status = 'recusada'");
    $stats['recusadas'] = $stmt->fetchColumn();
    
    // Total geral (sem excluídos)
    $stmt = $pdo->query("SELECT COUNT(*) FROM comunicacoes_crc WHERE status != 'excluido'");
    $stats['total'] = $stmt->fetchColumn();
    
    echo json_encode($stats);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>