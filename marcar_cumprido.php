<?php
date_default_timezone_set('America/Sao_Paulo');
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);
if(!$id){ echo json_encode(['success'=>false,'message'=>'ID inválido']); exit; }

$stmt=$pdo->prepare("UPDATE mandados
                     SET status='cumprido',
                         cumprido_por=?,
                         data_cumprido=NOW()
                     WHERE id=? AND status!='excluido'");
$ok = $stmt->execute([$_SESSION['usuario_id'],$id]);

echo json_encode(['success'=>$ok]);
