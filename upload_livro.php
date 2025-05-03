<?php  
ob_start();  
date_default_timezone_set('America/Sao_Paulo');   

// Suprimir mensagens de aviso que podem afetar a saída JSON  
error_reporting(E_ERROR);  

require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  
require_once 'includes/functions.php';  

// Verificar se usuário está logado  
if (!isset($_SESSION['usuario_id'])) {  
    header('Content-Type: application/json');  
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);  
    exit;  
}  

// Verificar se é uma requisição POST  
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {  
    header('Content-Type: application/json');  
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);  
    exit;  
}  

// Verificar se o ID do livro foi fornecido  
if (!isset($_POST['livro_id']) || !is_numeric($_POST['livro_id'])) {  
    header('Content-Type: application/json');  
    echo json_encode(['success' => false, 'message' => 'ID do livro não fornecido ou inválido']);  
    exit;  
}  

$livro_id = intval($_POST['livro_id']);  

// Verificar se o livro existe  
$stmt = $pdo->prepare("SELECT * FROM livros WHERE id = ?");  
$stmt->execute([$livro_id]);  
$livro = $stmt->fetch(PDO::FETCH_ASSOC);  

if (!$livro) {  
    header('Content-Type: application/json');  
    echo json_encode(['success' => false, 'message' => 'Livro não encontrado']);  
    exit;  
}  

// Verificar se há arquivos enviados  
if (!isset($_FILES['arquivos']) || empty($_FILES['arquivos']['name'][0])) {  
    header('Content-Type: application/json');  
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado']);  
    exit;  
}  

// Definir diretório para salvar os arquivos  
$diretorio_base = 'uploads/';  
$diretorio_livro = $diretorio_base . 'livro_' . $livro_id . '/';  

// Criar diretório base se não existir  
if (!file_exists($diretorio_base)) {  
    mkdir($diretorio_base, 0777, true);  
    chmod($diretorio_base, 0777);  
}  

// Criar diretório do livro se não existir  
if (!file_exists($diretorio_livro)) {  
    mkdir($diretorio_livro, 0777, true);  
    chmod($diretorio_livro, 0777);  
}  

// Criar diretório para as páginas se não existir  
$diretorio_paginas = $diretorio_livro . 'paginas/';  
if (!file_exists($diretorio_paginas)) {  
    if (!mkdir($diretorio_paginas, 0777, true)) {  
        header('Content-Type: application/json');  
        echo json_encode([  
            'success' => false,  
            'message' => 'Não foi possível criar o diretório para as páginas: ' . $diretorio_paginas  
        ]);  
        exit;  
    }  
    chmod($diretorio_paginas, 0777);  
}  

// Configurar diretório temporário para ImageMagick  
$temp_dir = sys_get_temp_dir() . '/imagick_temp_' . uniqid();  
if (!file_exists($temp_dir)) {  
    mkdir($temp_dir, 0777, true);  
    chmod($temp_dir, 0777);  
}  
putenv("MAGICK_TMPDIR={$temp_dir}");  

// Processar cada arquivo enviado  
$arquivos_processados = 0;  
$erros = [];  

// Iniciar transação  
$pdo->beginTransaction();  

try {  
    // Processar cada arquivo  
    foreach ($_FILES['arquivos']['name'] as $key => $nome) {  
        // Ignorar se não houver arquivo  
        if (empty($_FILES['arquivos']['name'][$key])) {  
            continue;  
        }  
        
        // Verificar se houve erro no upload  
        if ($_FILES['arquivos']['error'][$key] !== UPLOAD_ERR_OK) {  
            $codigo_erro = $_FILES['arquivos']['error'][$key];  
            $mensagem_erro = traduzirErroUpload($codigo_erro);  
            $erros[] = "Erro no upload do arquivo {$nome}: {$mensagem_erro}";  
            continue;  
        }  
        
        // Verificar tamanho máximo (2GB)  
        if ($_FILES['arquivos']['size'][$key] > 2 * 1024 * 1024 * 1024) {  
            $erros[] = "O arquivo {$nome} excede o tamanho máximo de 2GB.";  
            continue;  
        }  
        
        // Verificar tipo de arquivo  
        $tipo_arquivo = $_FILES['arquivos']['type'][$key];  
        $extensao = strtolower(pathinfo($_FILES['arquivos']['name'][$key], PATHINFO_EXTENSION));  
        
        if (!in_array($extensao, ['pdf', 'jpg', 'jpeg', 'png'])) {  
            $erros[] = "O arquivo {$nome} não é de um tipo permitido. Apenas PDF, JPG, JPEG e PNG são aceitos.";  
            continue;  
        }  
        
        // Gerar nome único para o arquivo  
        $nome_arquivo_unico = time() . '_' . uniqid() . '.' . $extensao;  
        $caminho_completo = $diretorio_livro . $nome_arquivo_unico;  
        
        // Mover arquivo para o diretório  
        if (!move_uploaded_file($_FILES['arquivos']['tmp_name'][$key], $caminho_completo)) {  
            $erros[] = "Falha ao mover o arquivo {$nome} para o diretório de upload.";  
            continue;  
        }  
        
        // Definir permissões no arquivo enviado  
        chmod($caminho_completo, 0666);  
        
        // Verificar se o arquivo foi realmente salvo  
        if (!file_exists($caminho_completo) || filesize($caminho_completo) == 0) {  
            $erros[] = "Falha ao salvar o arquivo {$nome}. O arquivo não existe ou está vazio.";  
            continue;  
        }  
        
        // Salvar informações do anexo no banco de dados  
        $stmt = $pdo->prepare("  
            INSERT INTO anexos_livros  
            (livro_id, nome_arquivo, caminho, tipo_arquivo, tamanho, data_upload, usuario_id)  
            VALUES (?, ?, ?, ?, ?, NOW(), ?)  
        ");  
        
        $resultado = $stmt->execute([  
            $livro_id,  
            $nome,  
            $caminho_completo,  
            $tipo_arquivo,  
            $_FILES['arquivos']['size'][$key],  
            $_SESSION['usuario_id']  
        ]);  
        
        if (!$resultado) {  
            $erros[] = "Falha ao registrar o anexo {$nome} no banco de dados.";  
            continue;  
        }  
        
        $anexo_id = $pdo->lastInsertId();  
        
        // Processar o arquivo de acordo com o tipo  
        if ($extensao === 'pdf') {  
            // Processar arquivo PDF - Extrair páginas  
            $resultado_processamento = processarPDF($caminho_completo, $livro_id, $anexo_id, $pdo, $diretorio_paginas);  
            
            if (!$resultado_processamento['sucesso']) {  
                $erros[] = $resultado_processamento['mensagem'];  
                continue;  
            }  
        } else {  
            // Processar arquivo de imagem  
            $resultado_processamento = processarImagem($caminho_completo, $livro_id, $anexo_id, $pdo, $diretorio_paginas);  
            
            if (!$resultado_processamento['sucesso']) {  
                $erros[] = $resultado_processamento['mensagem'];  
                continue;  
            }  
        }  
        
        // Incrementar contador de arquivos processados  
        $arquivos_processados++;  
    }  
    
    // Se há erros mas também arquivos processados, confirmar a transação  
    if ($arquivos_processados > 0) {  
        $pdo->commit();  
        
        // Resposta de sucesso com alertas, se houver  
        header('Content-Type: application/json');  
        echo json_encode([  
            'success' => true,   
            'message' => "Upload concluído. {$arquivos_processados} arquivo(s) processado(s) com sucesso.",  
            'erros' => $erros  
        ]);  
        exit;  
    } else if (count($erros) > 0) {  
        // Se não há arquivos processados e há erros, reverter a transação  
        $pdo->rollBack();  
        
        header('Content-Type: application/json');  
        echo json_encode([  
            'success' => false,   
            'message' => 'Nenhum arquivo foi processado devido a erros.',   
            'erros' => $erros  
        ]);  
        exit;  
    } else {  
        // Não houve erros, mas também não houve arquivos processados  
        $pdo->rollBack();  
        
        header('Content-Type: application/json');  
        echo json_encode([  
            'success' => false,   
            'message' => 'Nenhum arquivo foi enviado ou processado.'  
        ]);  
        exit;  
    }  
} catch (Exception $e) {  
    // Em caso de exceção, reverter transação  
    $pdo->rollBack();  
    
    header('Content-Type: application/json');  
    echo json_encode([  
        'success' => false,   
        'message' => 'Erro ao processar arquivos: ' . $e->getMessage()  
    ]);  
    exit;  
}  

/**  
 * Função para processar arquivo PDF e extrair suas páginas  
 */  
function processarPDF($caminho_arquivo, $livro_id, $anexo_id, $pdo, $diretorio_paginas) {  
    // Verificar se o arquivo existe  
    if (!file_exists($caminho_arquivo)) {  
        return [  
            'sucesso' => false,  
            'mensagem' => 'Arquivo PDF não encontrado: ' . $caminho_arquivo  
        ];  
    }  
    
    // Verificar se o arquivo é legível  
    if (!is_readable($caminho_arquivo)) {  
        return [  
            'sucesso' => false,  
            'mensagem' => 'Arquivo PDF não pode ser lido (permissões): ' . $caminho_arquivo  
        ];  
    }  
    
    // Usar caminho absoluto para evitar problemas com o ImageMagick  
    $caminho_absoluto = realpath($caminho_arquivo);  
    if ($caminho_absoluto === false) {  
        return [  
            'sucesso' => false,  
            'mensagem' => 'Não foi possível resolver o caminho absoluto para: ' . $caminho_arquivo  
        ];  
    }  
    
    try {  
        // Garantir que o diretório de páginas existe com permissões corretas  
        if (!file_exists($diretorio_paginas)) {  
            mkdir($diretorio_paginas, 0777, true);  
        }  
        chmod($diretorio_paginas, 0777);  
        
        error_log("Processando PDF: {$caminho_absoluto}");  
        error_log("Diretório de saída: {$diretorio_paginas}");  
        
        // Usar Imagick para extrair as páginas do PDF  
        $imagick = new Imagick();  
        
        // Configurar limites para arquivos grandes  
        $imagick->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 1024*1024*1024); // 1GB de memória  
        $imagick->setResourceLimit(Imagick::RESOURCETYPE_MAP, 2048*1024*1024);   // 2GB de mapeamento de memória  
        
        // Carregar o PDF com caminho absoluto  
        $imagick->setResolution(300, 300);
        $imagick->readImage($caminho_absoluto);
  
        // Obter quantidade de páginas  
        $numero_paginas = $imagick->getNumberImages();  
        
        // Se não houver páginas, retornar erro  
        if ($numero_paginas <= 0) {  
            return [  
                'sucesso' => false,  
                'mensagem' => 'O PDF não contém páginas para extração.'  
            ];  
        }  
        
        // Consultar o livro para obter informações de paginação  
        $stmt = $pdo->prepare("SELECT * FROM livros WHERE id = ?");  
        $stmt->execute([$livro_id]);  
        $livro = $stmt->fetch(PDO::FETCH_ASSOC);  
        
        // Verificar o último número de página registrado para este livro  
        $stmt = $pdo->prepare("  
            SELECT MAX(numero_pagina) as ultima_pagina,  
                   MAX(numero_folha) as ultima_folha,  
                   MAX(termo_inicial) as ultimo_termo  
            FROM paginas_livro  
            WHERE livro_id = ?  
        ");  
        $stmt->execute([$livro_id]);  
        $ultima_info = $stmt->fetch(PDO::FETCH_ASSOC);  
        
        $proximo_numero_pagina = $ultima_info['ultima_pagina'] ? $ultima_info['ultima_pagina'] + 1 : 1;  
        $proximo_numero_folha = $ultima_info['ultima_folha'] ? $ultima_info['ultima_folha'] + 1 : 1;  
        $proximo_termo = $ultima_info['ultimo_termo']  
            ? $ultima_info['ultimo_termo'] + $livro['termos_por_pagina']  
            : $livro['termo_inicial'];  
        
        // Inicializar contador da página atual no loop  
        $pagina_atual = 0;  
        
        // Para cada página do PDF  
        for ($i = 0; $i < $numero_paginas; $i++) {  
            // Selecionar a página atual  
            $imagick->setIteratorIndex($i);  
            
            // Converter para JPG com boa qualidade  
            $imagem_pagina = $imagick->getImage();  
            $imagem_pagina->setImageFormat('jpg');  
            $imagem_pagina->setImageCompressionQuality(100);  
            
            // Definir nome do arquivo para esta página  
            $nome_arquivo = 'pagina_' . sprintf("%04d", $proximo_numero_pagina) . '.jpg';  
            $caminho_pagina = $diretorio_paginas . $nome_arquivo;  
            
            error_log("Salvando página {$i} em: {$caminho_pagina}");  
            
                        // Garantir que o diretório de destino existe e tem permissões  
                        if (!is_dir(dirname($caminho_pagina))) {  
                            mkdir(dirname($caminho_pagina), 0777, true);  
                        }  
                        chmod(dirname($caminho_pagina), 0777);  
                        
                        // Tentar salvar com caminho absoluto  
                        try {  
                            $caminho_absoluto_dir = realpath(dirname($caminho_pagina));  
                            if ($caminho_absoluto_dir === false) {  
                                throw new Exception("Não foi possível obter o caminho absoluto para: " . dirname($caminho_pagina));  
                            }  
                            $caminho_absoluto_pagina = $caminho_absoluto_dir . '/' . $nome_arquivo;  
                            $imagem_pagina->writeImage($caminho_absoluto_pagina);  
                            
                            // Verificar se o arquivo foi salvo  
                            if (!file_exists($caminho_absoluto_pagina)) {  
                                throw new Exception("Arquivo não foi criado em: " . $caminho_absoluto_pagina);  
                            }  
                            
                            // Definir permissões no arquivo criado  
                            chmod($caminho_absoluto_pagina, 0666);  
                            
                            // Usar o caminho relativo para salvar no banco de dados  
                            $caminho_pagina = $diretorio_paginas . $nome_arquivo;  
                        } catch (Exception $ex) {  
                            error_log("Erro ao salvar com caminho absoluto: " . $ex->getMessage());  
                            
                            // Tentar salvar com caminho relativo  
                            try {  
                                $imagem_pagina->writeImage($caminho_pagina);  
                                
                                // Verificar se o arquivo foi salvo  
                                if (!file_exists($caminho_pagina)) {  
                                    throw new Exception("Falha ao salvar com caminho relativo");  
                                }  
                                
                                // Definir permissões no arquivo criado  
                                chmod($caminho_pagina, 0666);  
                            } catch (Exception $e) {  
                                return [  
                                    'sucesso' => false,  
                                    'mensagem' => "Erro ao processar PDF: " . $e->getMessage()  
                                ];  
                            }  
                        }  
                        
                        // Calcular número da folha e termo  
                        $numero_folha = $proximo_numero_folha;  
                        
                        // Se o livro usa contagem frente/verso, incrementar a folha a cada 2 páginas  
                        $eh_verso = false;  
                        if ($livro['contagem_frente_verso']) {  
                            $eh_verso = ($pagina_atual % 2 == 1);  
                            if ($eh_verso && $pagina_atual > 0) {  
                                // É verso, mantém o mesmo número de folha da página anterior  
                                $numero_folha = $proximo_numero_folha - 1;  
                            }  
                        }  
                        
                        // Calcular termo inicial para esta página  
                        $termo_inicial = $proximo_termo;  
                        // Calcular termo final (para compatibilidade com a tabela)  
                        $termo_final = $termo_inicial + $livro['termos_por_pagina'] - 1;  
                        
                        // Registrar página no banco de dados  
                        $stmt = $pdo->prepare("  
                            INSERT INTO paginas_livro  
                            (livro_id, anexo_id, numero_pagina, numero_folha, eh_verso, caminho, termo_inicial, termo_final, data_cadastro)  
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())  
                        ");  
                        
                        $stmt->execute([  
                            $livro_id,  
                            $anexo_id,  
                            $proximo_numero_pagina,  
                            $numero_folha,  
                            $eh_verso ? 1 : 0,  
                            $caminho_pagina,  
                            $termo_inicial,  
                            $termo_final  
                        ]);  
                        
                        // Incrementar contadores para a próxima página  
                        $proximo_numero_pagina++;  
                        $pagina_atual++;  
                        
                        // Se for frente ou o livro não usa frente/verso, incrementar a folha  
                        if (!$eh_verso || !$livro['contagem_frente_verso']) {  
                            $proximo_numero_folha++;  
                        }  
                        
                        // Incrementar o termo para a próxima página  
                        $proximo_termo += $livro['termos_por_pagina'];  
                        
                        // Limpar memória  
                        $imagem_pagina->clear();  
                        $imagem_pagina->destroy();  
                    }  
                    
                    // Limpar memória  
                    $imagick->clear();  
                    $imagick->destroy();  
                    
                    return [  
                        'sucesso' => true,  
                        'mensagem' => "PDF processado com sucesso. {$numero_paginas} páginas extraídas."  
                    ];  
                    
                } catch (Exception $e) {  
                    error_log("Erro completo ao processar PDF: " . $e->getMessage());  
                    return [  
                        'sucesso' => false,  
                        'mensagem' => 'Erro ao processar PDF: ' . $e->getMessage()  
                    ];  
                }  
            }  
            
            /**  
             * Função para processar arquivo de imagem  
             */  
            function processarImagem($caminho_arquivo, $livro_id, $anexo_id, $pdo, $diretorio_paginas) {
                static $contador_imagem = 0; // contador estático para rastrear frente/verso durante múltiplos uploads
            
                // Verificar se o arquivo existe
                if (!file_exists($caminho_arquivo)) {
                    return [
                        'sucesso' => false,
                        'mensagem' => 'Arquivo de imagem não encontrado: ' . $caminho_arquivo
                    ];
                }
            
                // Garantir que o diretório de páginas existe
                if (!file_exists($diretorio_paginas)) {
                    mkdir($diretorio_paginas, 0777, true);
                    chmod($diretorio_paginas, 0777);
                }
            
                // Consultar o livro
                $stmt = $pdo->prepare("SELECT * FROM livros WHERE id = ?");
                $stmt->execute([$livro_id]);
                $livro = $stmt->fetch(PDO::FETCH_ASSOC);
            
                // Buscar o último número de página
                $stmt = $pdo->prepare("
                    SELECT MAX(numero_pagina) as ultima_pagina,
                           MAX(numero_folha) as ultima_folha,
                           MAX(termo_inicial) as ultimo_termo
                    FROM paginas_livro
                    WHERE livro_id = ?
                ");
                $stmt->execute([$livro_id]);
                $ultima_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
                $proximo_numero_pagina = $ultima_info['ultima_pagina'] ? $ultima_info['ultima_pagina'] + 1 : 1;
                $proximo_numero_folha_base = $ultima_info['ultima_folha'] ? $ultima_info['ultima_folha'] + 1 : 1;
                $proximo_termo = $ultima_info['ultimo_termo']
                    ? $ultima_info['ultimo_termo'] + $livro['termos_por_pagina']
                    : $livro['termo_inicial'];
            
                // Calcular verso
                $eh_verso = false;
                $numero_folha = $proximo_numero_folha_base;
            
                if ($livro['contagem_frente_verso']) {
                    $eh_verso = ($contador_imagem % 2 == 1);
                    if ($eh_verso) {
                        $numero_folha = $proximo_numero_folha_base - 1;
                    }
                }
            
                // Gerar nome de arquivo final
                $nome_arquivo_final = 'pagina_' . sprintf("%04d", $proximo_numero_pagina) . '.jpg';
                $caminho_destino = $diretorio_paginas . $nome_arquivo_final;
            
                // Copiar imagem sem processamento
                if (!copy($caminho_arquivo, $caminho_destino)) {
                    return [
                        'sucesso' => false,
                        'mensagem' => 'Falha ao copiar a imagem para o diretório de páginas.'
                    ];
                }
            
                chmod($caminho_destino, 0666);
            
                // Calcular termo final
                $termo_final = $proximo_termo + $livro['termos_por_pagina'] - 1;
            
                // Inserir registro
                $stmt = $pdo->prepare("
                    INSERT INTO paginas_livro
                    (livro_id, anexo_id, numero_pagina, numero_folha, eh_verso, caminho, termo_inicial, termo_final, data_cadastro)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
            
                $stmt->execute([
                    $livro_id,
                    $anexo_id,
                    $proximo_numero_pagina,
                    $numero_folha,
                    $eh_verso ? 1 : 0,
                    $caminho_destino,
                    $proximo_termo,
                    $termo_final
                ]);
            
                // Incrementar contador global
                $contador_imagem++;
            
                return [
                    'sucesso' => true,
                    'mensagem' => "Imagem copiada como página {$proximo_numero_pagina}."
                ];
            }            
             
            
            /**  
             * Função para traduzir códigos de erro de upload  
             */  
            function traduzirErroUpload($codigo) {  
                switch ($codigo) {  
                    case UPLOAD_ERR_INI_SIZE:  
                        return 'O arquivo excede o tamanho máximo permitido no php.ini';  
                    case UPLOAD_ERR_FORM_SIZE:  
                        return 'O arquivo excede o tamanho máximo permitido no formulário';  
                    case UPLOAD_ERR_PARTIAL:  
                        return 'O upload foi interrompido antes de terminar';  
                    case UPLOAD_ERR_NO_FILE:  
                        return 'Nenhum arquivo foi enviado';  
                    case UPLOAD_ERR_NO_TMP_DIR:  
                        return 'Pasta temporária não encontrada';  
                    case UPLOAD_ERR_CANT_WRITE:  
                        return 'Falha ao gravar o arquivo no disco';  
                    case UPLOAD_ERR_EXTENSION:  
                        return 'Upload interrompido por uma extensão PHP';  
                    default:  
                        return 'Erro desconhecido no upload';  
                }  
            }