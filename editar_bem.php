<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id             = (int) $_POST['id'];
    $tipo_id        = (int) $_POST['tipo_id'];
    $categoria_id   = (int) $_POST['categoria_id'];
    $modelo         = sanitize($_POST['modelo']);
    $configuracao   = sanitize($_POST['configuracao']);
    $quantidade     = (int) $_POST['quantidade'];
    $localizacao    = sanitize($_POST['localizacao']);
    $data_aquisicao = $_POST['data_aquisicao'] ?: null;
    $observacoes    = sanitize($_POST['observacoes']);

    try {
        $stmt = $pdo->prepare("
            UPDATE bens SET
              tipo_id=?, categoria_id=?, modelo=?, configuracao=?,
              quantidade=?, localizacao=?, data_aquisicao=?, observacoes=?
            WHERE id=?
        ");
        $stmt->execute([
            $tipo_id, $categoria_id, $modelo, $configuracao,
            $quantidade, $localizacao, $data_aquisicao,
            $observacoes, $id
        ]);
        header("Location: inventory.php?success_edit=Bem atualizado com sucesso!");
    } catch (PDOException $e) {
        header("Location: inventory.php?error=" . urlencode($e->getMessage()));
    }
    exit;
}
