<?php
/**
 * detalhes_comunicacao_json.php
 * ------------------------------------------------------------------
 * Retorna os detalhes de uma comunicação em formato JSON
 * ------------------------------------------------------------------
 */
date_default_timezone_set('America/Sao_Paulo');

require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
    exit;
}

$id = intval($_GET['id']);

try {
    $sql = "SELECT c.*, p.arquivo AS pdf_original 
            FROM comunicacoes_crc c 
            LEFT JOIN anexos_crc_pdf p ON c.pdf_id = p.id 
            WHERE c.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $comunicacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($comunicacao) {
        echo json_encode([
            'success' => true,
            'comunicacao' => $comunicacao
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Comunicação não encontrada'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar comunicação: ' . $e->getMessage()
    ]);
}
?>