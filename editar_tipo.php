<?php
require 'includes/auth_check.php';
require 'includes/db_connection.php';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $id        = (int)$_POST['id'];
  $nome      = sanitize($_POST['nome']);
  $descricao = sanitize($_POST['descricao']);
  $pdo->prepare("UPDATE tipos_bem SET nome=?,descricao=? WHERE id=?")
      ->execute([$nome,$descricao,$id]);
  header("Location: tipos_bem.php?success_edit=Tipo atualizado!");
}
