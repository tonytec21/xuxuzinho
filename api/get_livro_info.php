<?php  
require_once '../includes/auth_check.php';  
require_once '../includes/db_connection.php';  

header('Content-Type: application/json');  

if (!isset($_SESSION['usuario_id'])) {  
    echo json_encode(['sucesso' => false, 'mensagem' => 'Usuário não autenticado']);  
    exit;  
}  

if (!isset($_GET['livro_id']) || !is_numeric($_GET['livro_id'])) {  
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID do livro não fornecido ou inválido']);  
    exit;  
}  

$livro_id = intval($_GET['livro_id']);  

try {  
    $stmt = $pdo->prepare("SELECT * FROM livros WHERE id = ?");  
    $stmt->execute([$livro_id]);  
    $livro = $stmt->fetch(PDO::FETCH_ASSOC);  
    
    if (!$livro) {  
        echo json_encode(['sucesso' => false, 'mensagem' => 'Livro não encontrado']);  
        exit;  
    }  
    
    echo json_encode([  
        'sucesso' => true,  
        'livro' => $livro  
    ]);  
    
} catch (PDOException $e) {  
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao buscar informações do livro: ' . $e->getMessage()]);  
}  
?>