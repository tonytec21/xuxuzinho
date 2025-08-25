<?php
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
  SELECT status_anterior, novo_status, usuario_nome, ip, user_agent, criado_em
    FROM comunicacoes_crc_status_log
   WHERE comunicacao_id = ?
ORDER BY criado_em DESC, id DESC
");
$stmt->execute([$id]);
echo json_encode([
  'success' => true,
  'logs'    => $stmt->fetchAll()
]);
