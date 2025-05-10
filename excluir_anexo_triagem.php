<?php
date_default_timezone_set('America/Sao_Paulo');
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: triagem.php?error=anexo_id_invalido");
    exit;
}

$anexo_id = intval($_GET['id']);
$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['nome'] ?? 'Usuário ID: ' . $usuario_id;

try {
    // Buscar o anexo e seu registro vinculado
    $stmt = $pdo->prepare("SELECT id, registro_id FROM triagem_anexos WHERE id = ? AND status = 'ativo'");
    $stmt->execute([$anexo_id]);
    $anexo = $stmt->fetch();

    if (!$anexo) {
        header("Location: triagem.php?error=anexo_nao_encontrado");
        exit;
    }

    // Iniciar transação
    $pdo->beginTransaction();

    // Marcar anexo como excluído
    $stmt = $pdo->prepare("
        UPDATE triagem_anexos
        SET status = 'excluido',
            excluido_por = ?,
            excluido_por_id = ?,
            data_exclusao = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$usuario_nome, $usuario_id, $anexo_id]);

    // Registrar no log do sistema
    $stmt = $pdo->prepare("
        INSERT INTO logs_sistema (usuario_id, usuario_nome, acao, tabela_afetada, registro_id, data_hora)
        VALUES (?, ?, 'exclusão', 'triagem_anexos', ?, NOW())
    ");
    $stmt->execute([$usuario_id, $usuario_nome, $anexo_id]);

    // Confirmar
    $pdo->commit();

    header("Location: triagem.php?id=" . $anexo['registro_id'] . "&delete=success");
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Erro ao excluir anexo de triagem: " . $e->getMessage());
    header("Location: triagem.php?error=erro_excluir");
    exit;
}
