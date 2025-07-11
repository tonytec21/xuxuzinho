<?php
require 'includes/auth_check.php';
require 'includes/db_connection.php';
require_once 'includes/functions.php';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $tipo_id   = (int)$_POST['tipo_id'];
  $nome      = sanitize($_POST['nome']);
  $descricao = sanitize($_POST['descricao']);
  $pdo->prepare("
    INSERT INTO categorias_bem(tipo_id,nome,descricao)
    VALUES(?,?,?)
  ")->execute([$tipo_id,$nome,$descricao]);
  header("Location: inventory.php?success=Categoria cadastrada!");
}
