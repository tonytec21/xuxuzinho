<?php  
// api/get_usuario_historico.php  
session_start();  
require_once '../includes/db_connection.php';  
require_once '../includes/functions.php';  

// Verificar se o usuário está logado e é administrador  
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] != 'admin') {  
    echo json_encode([  
        'status' => 'error',  
        'message' => 'Acesso negado'  
    ]);  
    exit;  
}  

// Verificar se o ID do usuário foi fornecido  
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {  
    echo json_encode([  
        'status' => 'error',  
        'message' => 'ID do usuário não fornecido ou inválido'  
    ]);  
    exit;  
}  

$usuario_id = intval($_GET['id']);  

try {  
    // Verificar se o usuário existe  
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");  
    $stmt->execute([$usuario_id]);  
    
    if (!$stmt->fetch()) {  
        echo json_encode([  
            'status' => 'error',  
            'message' => 'Usuário não encontrado'  
        ]);  
        exit;  
    }  
    
    // Buscar histórico do usuário  
    $stmt = $pdo->prepare("  
        SELECT   
            l.id,  
            l.acao,  
            l.tabela,  
            l.registro_id,  
            l.detalhes,  
            l.usuario_id,  
            l.usuario_nome,  
            l.data_hora  
        FROM   
            logs l  
        WHERE   
            l.tabela = 'usuarios' AND l.registro_id = ?  
        ORDER BY   
            l.data_hora DESC  
    ");  
    $stmt->execute([$usuario_id]);  
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);  
    
    echo json_encode([  
        'status' => 'success',  
        'historico' => $logs  
    ]);  
    
} catch (PDOException $e) {  
    echo json_encode([  
        'status' => 'error',  
        'message' => 'Erro ao buscar histórico: ' . $e->getMessage()  
    ]);  
}  
?>