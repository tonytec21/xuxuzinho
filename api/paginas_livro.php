<?php  
/**  
 * API endpoint para listar as páginas de um livro  
 *   
 * Este endpoint retorna um JSON com todas as páginas de um livro específico,  
 * para ser consumido pelo frontend (principalmente pelo visualizador de páginas).  
 */  

require_once '../conexao.php';  
session_start();  

// Verificar se usuário está logado  
if (!isset($_SESSION['usuario_id'])) {  
    header('Content-Type: application/json');  
    echo json_encode([  
        'success' => false,  
        'message' => 'Usuário não autenticado'  
    ]);  
    exit;  
}  

// Verificar se o ID do livro foi fornecido  
if (!isset($_GET['livro_id']) || !is_numeric($_GET['livro_id'])) {  
    header('Content-Type: application/json');  
    echo json_encode([  
        'success' => false,  
        'message' => 'ID do livro não fornecido ou inválido'  
    ]);  
    exit;  
}  

$livro_id = intval($_GET['livro_id']);  

// Verificar se o livro existe  
$stmt = $pdo->prepare("SELECT * FROM livros WHERE id = ?");  
$stmt->execute([$livro_id]);  
$livro = $stmt->fetch(PDO::FETCH_ASSOC);  

if (!$livro) {  
    header('Content-Type: application/json');  
    echo json_encode([  
        'success' => false,  
        'message' => 'Livro não encontrado'  
    ]);  
    exit;  
}  

try {  
    // Buscar todas as páginas do livro  
    $stmt = $pdo->prepare("  
        SELECT   
            p.id,  
            p.numero_pagina,  
            p.numero_folha,  
            p.eh_verso,  
            p.termo_inicial,  
            p.data_cadastro,  
            p.caminho  
        FROM   
            paginas_livro p  
        WHERE   
            p.livro_id = ?  
        ORDER BY   
            p.numero_pagina ASC  
    ");  
    
    $stmt->execute([$livro_id]);  
    $paginas = $stmt->fetchAll(PDO::FETCH_ASSOC);  
    
    // Formatar datas para melhor visualização no frontend  
    foreach ($paginas as &$pagina) {  
        if (isset($pagina['data_cadastro'])) {  
            $data = new DateTime($pagina['data_cadastro']);  
            $pagina['data_formatada'] = $data->format('d/m/Y H:i');  
        } else {  
            $pagina['data_formatada'] = 'Data não disponível';  
        }  
        
        // Adicionar informação se é frente ou verso para livros com contagem frente/verso  
        if ($livro['contagem_frente_verso']) {  
            $pagina['lado'] = $pagina['eh_verso'] ? 'Verso' : 'Frente';  
        } else {  
            $pagina['lado'] = '';  
        }  
    }  
    
    // Retornar resultados como JSON  
    header('Content-Type: application/json');  
    echo json_encode([  
        'success' => true,  
        'livro' => [  
            'id' => $livro['id'],  
            'numero' => $livro['numero'],  
            'tipo' => $livro['tipo'],  
            'termo_inicial' => $livro['termo_inicial'],  
            'termos_por_pagina' => $livro['termos_por_pagina'],  
            'contagem_frente_verso' => (bool)$livro['contagem_frente_verso']  
        ],  
        'paginas' => $paginas,  
        'total' => count($paginas)  
    ]);  
    
} catch (PDOException $e) {  
    // Log do erro  
    error_log('Erro ao buscar páginas do livro: ' . $e->getMessage());  
    
    // Retornar erro como JSON  
    header('Content-Type: application/json');  
    echo json_encode([  
        'success' => false,  
        'message' => 'Erro ao buscar páginas do livro'  
    ]);  
}