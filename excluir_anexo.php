<?php  
date_default_timezone_set('America/Sao_Paulo'); 
require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {  
    header("Location: selos.php?error=invalid_id");  
    exit;  
}  

$anexo_id = intval($_GET['id']);  
$usuario_id = $_SESSION['usuario_id'];  
$usuario_nome = $_SESSION['nome'] ?? 'Usuário ID: ' . $usuario_id;  

try {  
    // Verificar se o anexo existe e pertence ao usuário  
    $stmt = $pdo->prepare("  
        SELECT a.id, a.selo_id  
        FROM anexos a  
        WHERE a.id = ?  
    ");  
    $stmt->execute([$anexo_id]);  
    $anexo = $stmt->fetch();  
    
    if (!$anexo) {  
        header("Location: selos.php?error=not_found");  
        exit;  
    }  

    // Iniciar uma transação para garantir a integridade dos dados  
    $pdo->beginTransaction();  

    // Atualizar o status do anexo e registrar quem fez a exclusão  
    $stmt = $pdo->prepare("  
        UPDATE anexos   
        SET   
            status = 'excluido',   
            excluido_por = ?,   
            excluido_por_id = ?,   
            data_exclusao = NOW()   
        WHERE id = ?  
    ");  
    $stmt->execute([$usuario_nome, $usuario_id, $anexo_id]);  
    
    // Registrar a ação em uma tabela de log (opcional, mas recomendado)  
    $stmt = $pdo->prepare("  
        INSERT INTO logs_sistema   
        (usuario_id, usuario_nome, acao, tabela_afetada, registro_id, data_hora)  
        VALUES (?, ?, 'exclusão', 'anexos', ?, NOW())  
    ");  
    $stmt->execute([$usuario_id, $usuario_nome, $anexo_id]);  

    // Confirmar a transação  
    $pdo->commit();  

    header("Location: selos.php?id=" . $anexo['selo_id'] . "&delete=success");  
    exit;  

} catch (PDOException $e) {  
    // Reverter as mudanças em caso de erro  
    if ($pdo->inTransaction()) {  
        $pdo->rollBack();  
    }  
    
    error_log("Erro ao excluir anexo: " . $e->getMessage());  
    header("Location: selos.php?error=db_error");  
    exit;  
}