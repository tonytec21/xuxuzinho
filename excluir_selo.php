<?php  
date_default_timezone_set('America/Sao_Paulo'); 
require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  

// Verificar se o ID do selo foi fornecido  
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {  
    header("Location: selos.php?error=invalid_id");  
    exit;  
}  

$selo_id = intval($_GET['id']);  
$usuario_id = $_SESSION['usuario_id'];  
$usuario_nome = $_SESSION['nome']; // Certifique-se de que essa variável está disponível na sessão

try {  
    // Verificar se o selo pertence ao usuário atual e ainda não foi excluído  
    $stmt = $pdo->prepare("SELECT id, numero FROM selos WHERE id = ? AND status != 'excluido'");
    $stmt->execute([$selo_id]);
    $selo = $stmt->fetch();

    if (!$selo) {  
        header("Location: selos.php?error=not_found");  
        exit;  
    }  

    // Atualizar o status do selo para "excluido"  
    $pdo->beginTransaction();  

    $stmt = $pdo->prepare("UPDATE selos SET status = 'excluido', data_exclusao = NOW() WHERE id = ?");  
    $stmt->execute([$selo_id]);  

    // Inserir no log
    $stmt = $pdo->prepare("INSERT INTO logs_sistema 
        (usuario_id, usuario_nome, acao, tabela_afetada, registro_id, data_hora, detalhes) 
        VALUES (?, ?, ?, ?, ?, NOW(), ?)");  

    $detalhes = "Selo nº " . htmlspecialchars($selo['numero']) . " marcado como excluído.";  
    $stmt->execute([
        $usuario_id,
        $usuario_nome,
        'exclusao',
        'selos',
        $selo_id,
        $detalhes
    ]);

    // Confirmar a transação  
    $pdo->commit();  

    // Redirecionar  
    header("Location: selos.php?delete=success");  
    exit;  

} catch (PDOException $e) {  
    if ($pdo->inTransaction()) {  
        $pdo->rollBack();  
    }  

    error_log("Erro ao excluir selo: " . $e->getMessage());  
    header("Location: selos.php?error=db_error");  
    exit;  
}
