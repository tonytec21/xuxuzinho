<?php
require 'includes/auth_check.php';
require 'includes/db_connection.php';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $id        = (int)$_POST['id'];
  $tipo_id   = (int)$_POST['tipo_id'];
  $nome      = sanitize($_POST['nome']);
  $descricao = sanitize($_POST['descricao']);
  $pdo->prepare("
    UPDATE categorias_bem
    SET tipo_id=?,nome=?,descricao=?
    WHERE id=?
  ")->execute([$tipo_id,$nome,$descricao,$id]);
  header("Location: categorias_bem.php?success_edit=Categoria atualizada!");
}
