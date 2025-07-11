<?php
require 'includes/auth_check.php';
require 'includes/db_connection.php';
if (isset($_GET['id'])) {
  $pdo->prepare("DELETE FROM tipos_bem WHERE id=?")
      ->execute([(int)$_GET['id']]);
  header("Location: tipos_bem.php?success_del=Tipo excluído!");
}
