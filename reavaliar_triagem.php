<?php
date_default_timezone_set('America/Sao_Paulo');
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

header('Content-Type: application/json; charset=utf-8');

$usuario_id   = $_SESSION['usuario_id'] ?? 0;
$usuario_nome = $_SESSION['nome']        ?? 'Usuário';

/* ------------------------------------------------------------------  
   1. Validação da requisição  
------------------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$registro_id = intval($_POST['id'] ?? 0);
if (!$registro_id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

/* ------------------------------------------------------------------  
   2. Busca do registro e verificação de status  
------------------------------------------------------------------*/
$stmt = $pdo->prepare("SELECT status FROM triagem_registros WHERE id = ?");
$stmt->execute([$registro_id]);
$reg = $stmt->fetch();

if (!$reg || !in_array($reg['status'], ['pendente', 'rejeitado'])) {
    echo json_encode(['success' => false, 'message' => 'Registro não encontrado ou já processado.']);
    exit;
}

/* ------------------------------------------------------------------  
   3. Fluxos de atualização  
      - Se estava rejeitado  ➔ volta para pendente  
      - Se estava pendente  ➔ aprova  
------------------------------------------------------------------*/
try {
    if ($reg['status'] === 'rejeitado') {
        // volta para pendente e limpa campos de rejeição/aprovação
        $sql = "
            UPDATE triagem_registros
               SET status          = 'pendente',
                   motivo_rejeicao = NULL,
                   data_rejeicao   = NULL,
                   aprovado_por    = NULL,
                   data_aprovacao  = NULL
             WHERE id = ?
        ";
        $ok = $pdo->prepare($sql)->execute([$registro_id]);

    } else { // status 'pendente'
        // aprova normalmente
        $sql = "
            UPDATE triagem_registros
               SET status         = 'aprovado',
                   data_aprovacao = NOW(),
                   aprovado_por   = ?
             WHERE id = ?
        ";
        $ok = $pdo->prepare($sql)->execute([$usuario_nome, $registro_id]);
    }

    echo json_encode(['success' => $ok]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados.']);
}
