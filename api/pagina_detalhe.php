<?php  
/**  
 * API endpoint para obter detalhes de uma página específica de um livro  
 *   
 * Este endpoint retorna um JSON com todos os detalhes de uma página específica,  
 * incluindo caminho da imagem, metadados e informações de navegação.  
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

// Verificar se o ID da página foi fornecido  
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {  
    header('Content-Type: application/json');  
    echo json_encode([  
        'success' => false,  
        'message' => 'ID da página não fornecido ou inválido'  
    ]);  
    exit;  
}  

$pagina_id = intval($_GET['id']);  

try {  
    // Buscar detalhes da página  
    $stmt = $pdo->prepare("  
        SELECT   
            p.*,  
            l.numero as livro_numero,  
            l.tipo as livro_tipo,  
            l.contagem_frente_verso,  
            l.termos_por_pagina,  
            a.nome_arquivo as nome_anexo  
        FROM   
            paginas_livro p  
        INNER JOIN   
            livros l ON p.livro_id = l.id  
        LEFT JOIN   
            anexos_livro a ON p.anexo_id = a.id  
        WHERE   
            p.id = ?  
    ");  
    
    $stmt->execute([$pagina_id]);  
    $pagina = $stmt->fetch(PDO::FETCH_ASSOC);  
    
    if (!$pagina) {  
        header('Content-Type: application/json');  
        echo json_encode([  
            'success' => false,  
            'message' => 'Página não encontrada'  
        ]);  
        exit;  
    }  
    
    // Formatar data  
    if (isset($pagina['data_cadastro'])) {  
        $data = new DateTime($pagina['data_cadastro']);  
        $pagina['data_formatada'] = $data->format('d/m/Y H:i');  
    } else {  
        $pagina['data_formatada'] = 'Data não disponível';  
    }  
    
    // Adicionar informação se é frente ou verso para livros com contagem frente/verso  
    if ($pagina['contagem_frente_verso']) {  
        $pagina['lado'] = $pagina['eh_verso'] ? 'Verso' : 'Frente';  
    } else {  
        $pagina['lado'] = '';  
    }  
    
    // Calcular termos na página  
    $pagina['termo_final'] = $pagina['termo_inicial'] + $pagina['termos_por_pagina'] - 1;  
    
    // Buscar informações da página anterior  
    $stmt = $pdo->prepare("  
        SELECT   
            id,   
            numero_pagina  
        FROM   
            paginas_livro  
        WHERE   
            livro_id = ?   
            AND numero_pagina < ?  
        ORDER BY   
            numero_pagina DESC  
        LIMIT 1  
    ");  
    $stmt->execute([$pagina['livro_id'], $pagina['numero_pagina']]);  
    $pagina_anterior = $stmt->fetch(PDO::FETCH_ASSOC);  
    
    // Buscar informações da próxima página  
    $stmt = $pdo->prepare("  
        SELECT   
            id,   
            numero_pagina  
        FROM   
            paginas_livro  
        WHERE   
            livro_id = ?   
            AND numero_pagina > ?  
        ORDER BY   
            numero_pagina ASC  
        LIMIT 1  
    ");  
    $stmt->execute([$pagina['livro_id'], $pagina['numero_pagina']]);  
    $proxima_pagina = $stmt->fetch(PDO::FETCH_ASSOC);  
    
    // Buscar total de páginas do livro  
    $stmt = $pdo->prepare("  
        SELECT   
            COUNT(*) as total_paginas  
        FROM   
            paginas_livro  
        WHERE   
            livro_id = ?  
    ");  
    $stmt->execute([$pagina['livro_id']]);  
    $total = $stmt->fetch(PDO::FETCH_ASSOC);  
    
    // Verificar se o arquivo da imagem existe  
    $arquivo_existe = file_exists('../' . $pagina['caminho']);  
    
    // Montar resultado  
    $resultado = [  
        'success' => true,  
        'pagina' => [  
            'id' => $pagina['id'],  
            'livro_id' => $pagina['livro_id'],  
            'livro_numero' => $pagina['livro_numero'],  
            'livro_tipo' => $pagina['livro_tipo'],  
            'numero_pagina' => $pagina['numero_pagina'],  
            'numero_folha' => $pagina['numero_folha'],  
            'eh_verso' => (bool)$pagina['eh_verso'],  
            'lado' => $pagina['lado'],  
            'termo_inicial' => $pagina['termo_inicial'],  
            'termo_final' => $pagina['termo_final'],  
            'caminho' => $pagina['caminho'],  
            'arquivo_existe' => $arquivo_existe,  
            'data_cadastro' => $pagina['data_cadastro'],  
            'data_formatada' => $pagina['data_formatada'],  
            'nome_anexo' => $pagina['nome_anexo']  
        ],  
        'navegacao' => [  
            'total_paginas' => $total['total_paginas'],  
            'pagina_atual' => $pagina['numero_pagina'],  
            'tem_anterior' => !empty($pagina_anterior),  
            'tem_proxima' => !empty($proxima_pagina),  
            'anterior' => $pagina_anterior ? [  
                'id' => $pagina_anterior['id'],  
                'numero_pagina' => $pagina_anterior['numero_pagina']  
            ] : null,  
            'proxima' => $proxima_pagina ? [  
                'id' => $proxima_pagina['id'],  
                'numero_pagina' => $proxima_pagina['numero_pagina']  
            ] : null  
        ]  
    ];  
    
    // Retornar resultados como JSON  
    header('Content-Type: application/json');  
    echo json_encode($resultado);  
    
} catch (PDOException $e) {  
    // Log do erro  
    error_log('Erro ao buscar detalhes da página: ' . $e->getMessage());  
    
    // Retornar erro como JSON  
    header('Content-Type: application/json');  
    echo json_encode([  
        'success' => false,  
        'message' => 'Erro ao buscar detalhes da página: ' . $e->getMessage()  
    ]);  
}