<?php
require 'includes/auth_check.php';
require 'includes/db_connection.php';
if (isset($_GET['id'])) {
  $pdo->prepare("DELETE FROM categorias_bem WHERE id=?")
      ->execute([(int)$_GET['id']]);
  header("Location: categorias_bem.php?success_del=Categoria excluída!");
}
