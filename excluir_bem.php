<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare("UPDATE bens SET status='inativo' WHERE id=?");
    $stmt->execute([$id]);
    header("Location: inventory.php?success_del=Bem excluído com sucesso!");
    exit;
}
header("Location: inventory.php");
