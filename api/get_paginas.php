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
    // Verificar se o livro existe  
    $stmt = $pdo->prepare("SELECT id FROM livros WHERE id = ?");  
    $stmt->execute([$livro_id]);  
    if (!$stmt->fetch()) {  
        echo json_encode(['sucesso' => false, 'mensagem' => 'Livro não encontrado']);  
        exit;  
    }  
    
    // Buscar todas as páginas do livro, ordenadas por número de página  
    $stmt = $pdo->prepare("  
        SELECT id, livro_id, anexo_id, numero_pagina, numero_folha, eh_verso, caminho, termo_inicial, termo_final  
        FROM paginas_livro  
        WHERE livro_id = ?  
        ORDER BY numero_pagina ASC  
    ");  
    $stmt->execute([$livro_id]);  
    $paginas = $stmt->fetchAll(PDO::FETCH_ASSOC);  
    
    echo json_encode([  
        'sucesso' => true,  
        'paginas' => $paginas  
    ]);  
    
} catch (PDOException $e) {  
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao buscar páginas: ' . $e->getMessage()]);  
}  
?>