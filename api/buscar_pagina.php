<?php  
/**  
 * API endpoint para buscar páginas de um livro por termo, folha ou número de página  
 *   
 * Este endpoint retorna um JSON com as páginas encontradas com base nos critérios de busca.  
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

// Parâmetros de busca  
$termo = isset($_GET['termo']) ? intval($_GET['termo']) : null;  
$folha = isset($_GET['folha']) ? intval($_GET['folha']) : null;  
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : null;  

// Validar que pelo menos um parâmetro de busca foi fornecido  
if ($termo === null && $folha === null && $pagina === null) {  
    header('Content-Type: application/json');  
    echo json_encode([  
        'success' => false,  
        'message' => 'É necessário fornecer ao menos um critério de busca: termo, folha ou página'  
    ]);  
    exit;  
}  

try {  
    // Construir a consulta base  
    $sql = "  
        SELECT   
            p.id,  
            p.numero_pagina,  
            p.numero_folha,  
            p.eh_verso,  
            p.termo_inicial,  
            p.caminho,  
            p.data_cadastro  
        FROM   
            paginas_livro p  
        WHERE   
            p.livro_id = ?  
    ";  
    
    $params = [$livro_id];  
    
    // Adicionar condições de busca  
    if ($termo !== null) {  
        // Busca por termo (encontrar a página que contém o termo)  
        $sql .= " AND (p.termo_inicial <= ? AND (p.termo_inicial + ? - 1) >= ?)";  
        $params[] = $termo;  
        $params[] = $livro['termos_por_pagina'];  
        $params[] = $termo;  
    }  
    
    if ($folha !== null) {  
        // Busca por número de folha  
        $sql .= " AND p.numero_folha = ?";  
        $params[] = $folha;  
    }  
    
    if ($pagina !== null) {  
        // Busca por número de página  
        $sql .= " AND p.numero_pagina = ?";  
        $params[] = $pagina;  
    }  
    
    // Ordenação  
    $sql .= " ORDER BY p.numero_pagina ASC";  
    
    // Executar a consulta  
    $stmt = $pdo->prepare($sql);  
    $stmt->execute($params);  
    $paginas = $stmt->fetchAll(PDO::FETCH_ASSOC);  
    
    // Se nenhuma página foi encontrada  
    if (empty($paginas)) {  
        header('Content-Type: application/json');  
        echo json_encode([  
            'success' => false,  
            'message' => 'Nenhuma página encontrada com os critérios informados',  
            'criterios' => [  
                'termo' => $termo,  
                'folha' => $folha,  
                'pagina' => $pagina  
            ]  
        ]);  
        exit;  
    }  
    
    // Formatar datas e adicionar informações  
    foreach ($paginas as &$pagina_item) {  
        if (isset($pagina_item['data_cadastro'])) {  
            $data = new DateTime($pagina_item['data_cadastro']);  
            $pagina_item['data_formatada'] = $data->format('d/m/Y H:i');  
        } else {  
            $pagina_item['data_formatada'] = 'Data não disponível';  
        }  
        
        // Adicionar informação se é frente ou verso para livros com contagem frente/verso  
        if ($livro['contagem_frente_verso']) {  
            $pagina_item['lado'] = $pagina_item['eh_verso'] ? 'Verso' : 'Frente';  
        } else {  
            $pagina_item['lado'] = '';  
        }  
        
        // Calcular termo final  
        $pagina_item['termo_final'] = $pagina_item['termo_inicial'] + $livro['termos_por_pagina'] - 1;  
        
        // Verificar se o arquivo existe  
        $pagina_item['arquivo_existe'] = file_exists('../' . $pagina_item['caminho']);  
    }  
    
    // Retornar resultados como JSON  
    header('Content-Type: application/json');  
    echo json_encode([  
        'success' => true,  
        'livro' => [  
            'id' => $livro['id'],  
            'numero' => $livro['numero'],  
            'tipo' => $livro['tipo']  
        ],  
        'paginas' => $paginas,  
        'total_encontrado' => count($paginas),  
        'criterios' => [  
            'termo' => $termo,  
            'folha' => $folha,  
            'pagina' => $pagina  
        ]  
    ]);  
    
} catch (PDOException $e) {  
    // Log do erro  
    error_log('Erro ao buscar páginas: ' . $e->getMessage());  
    
    // Retornar erro como JSON  
    header('Content-Type: application/json');  
    echo json_encode([  
        'success' => false,  
        'message' => 'Erro ao buscar páginas: ' . $e->getMessage()  
    ]);  
}