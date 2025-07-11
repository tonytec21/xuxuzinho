<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';  // <— adicionado para usar sanitize()

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // limpa os inputs
    $nome      = sanitize($_POST['nome']);
    $descricao = sanitize($_POST['descricao']);

    try {
        $stmt = $pdo->prepare("INSERT INTO tipos_bem (nome, descricao) VALUES (?, ?)");
        $stmt->execute([$nome, $descricao]);
        header("Location: inventory.php?success=Tipo cadastrado com sucesso!");
    } catch (PDOException $e) {
        // você pode logar $e->getMessage() ou mostrar uma mensagem genérica
        header("Location: inventory.php?error=" . urlencode("Falha ao cadastrar tipo."));
    }
    exit;
}
