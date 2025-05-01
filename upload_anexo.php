<?php  
ob_start();  
date_default_timezone_set('America/Sao_Paulo'); 

// Suprimir mensagens de aviso que podem afetar a saída JSON  
error_reporting(E_ERROR);  

require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  
require_once 'includes/functions.php';  

function log_message($message) {  
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $message);  
}  

function normalizarNomeArquivo($nome) {  
    $nome = preg_replace('/[àáâãäåçèéêëìíîïñòóôõöùúûüýÿ]/ui', '', $nome);  
    $nome = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $nome);  
    return $nome;  
}  

function convert_pdf_to_jpg($pdf_path, $output_dir) {  
    if (!file_exists($output_dir)) {  
        if (!mkdir($output_dir, 0777, true)) {  
            log_message("Não foi possível criar o diretório: $output_dir");  
            return false;  
        }  
    }  

    try {  
        $output_pattern = $output_dir . '/page_%04d.jpg';  
        $command = "magick convert -density 150 -background white -alpha remove -alpha off -quality 90 \"{$pdf_path}\" \"{$output_pattern}\"";  
        log_message("Executando comando: $command");  
        exec($command, $output, $return_code);  

        if ($return_code !== 0) {  
            log_message("Erro ao executar ImageMagick. Código: $return_code");  
            $command_alt = "convert -density 150 -background white -alpha remove -alpha off -quality 90 \"{$pdf_path}\" \"{$output_pattern}\"";  
            exec($command_alt, $output_alt, $return_code_alt);  

            if ($return_code_alt !== 0) {  
                log_message("Erro ao executar comando alternativo. Código: $return_code_alt");  
                return false;  
            }  
        }  

        $image_files = glob($output_dir . '/page_*.jpg');  
        if (empty($image_files)) {  
            log_message("Nenhum arquivo JPG gerado");  
            return false;  
        }  

        sort($image_files, SORT_NATURAL);  
        return $image_files;  

    } catch (Exception $e) {  
        log_message("Exceção ao converter PDF: " . $e->getMessage());  
        return false;  
    }  
}  

function upload_error_message($error_code) {  
    switch ($error_code) {  
        case UPLOAD_ERR_INI_SIZE: return 'O arquivo excede o tamanho máximo permitido pelo servidor.';  
        case UPLOAD_ERR_FORM_SIZE: return 'O arquivo excede o tamanho máximo permitido pelo formulário.';  
        case UPLOAD_ERR_PARTIAL: return 'O upload do arquivo foi feito parcialmente.';  
        case UPLOAD_ERR_NO_FILE: return 'Nenhum arquivo foi enviado.';  
        case UPLOAD_ERR_NO_TMP_DIR: return 'Falta uma pasta temporária no servidor.';  
        case UPLOAD_ERR_CANT_WRITE: return 'Falha ao escrever o arquivo no disco.';  
        case UPLOAD_ERR_EXTENSION: return 'Uma extensão PHP interrompeu o upload do arquivo.';  
        default: return 'Erro desconhecido no upload.';  
    }  
}  

// Limpar qualquer saída anterior  
ob_clean();  

$response = ['success' => false, 'message' => '', 'arquivos_processados' => 0, 'erros' => []];  

if ($_SERVER['REQUEST_METHOD'] === 'POST') {  
    if (!isset($_POST['selo_id']) || !is_numeric($_POST['selo_id'])) {  
        $response['message'] = 'ID do selo inválido.';  
        goto output_json;  
    }  

    $selo_id = $_POST['selo_id'];  

    $stmt = $pdo->prepare("SELECT id FROM selos WHERE id = ?");
    $stmt->execute([$selo_id]);
    if ($stmt->rowCount() === 0) {
        $response['message'] = 'Selo não encontrado.';
        goto output_json;
    }

    // Verificar se o upload é múltiplo ou único  
    $is_multiple = isset($_FILES['arquivos']) && is_array($_FILES['arquivos']['name']);  

    if ($is_multiple) {  
        if (empty($_FILES['arquivos']['name'][0])) {  
            $response['message'] = 'Nenhum arquivo foi enviado.';  
            goto output_json;  
        }  
        $files = $_FILES['arquivos'];  
    } else {  
        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] === UPLOAD_ERR_NO_FILE) {  
            $response['message'] = 'Nenhum arquivo foi enviado.';  
            goto output_json;  
        }  
        // Converter para formato de array múltiplo  
        $files = [  
            'name' => [$_FILES['arquivo']['name']],  
            'type' => [$_FILES['arquivo']['type']],  
            'tmp_name' => [$_FILES['arquivo']['tmp_name']],  
            'error' => [$_FILES['arquivo']['error']],  
            'size' => [$_FILES['arquivo']['size']]  
        ];  
    }  

    // Criar o diretório para os arquivos  
    $diretorio = "uploads/selo_" . $selo_id;  
    if (!file_exists($diretorio)) {  
        mkdir($diretorio, 0755, true);  
    }  

    $arquivos_totais = count($files['name']);  
    $arquivos_processados = 0;  
    $imagens_total = 0;  

    // Processar cada arquivo  
    for ($i = 0; $i < $arquivos_totais; $i++) {  
        $nome_original = $files['name'][$i];  
        $tipo = $files['type'][$i];  
        $tmp_name = $files['tmp_name'][$i];  
        $erro = $files['error'][$i];  
        $tamanho = $files['size'][$i];  

        // Verificar erro de upload  
        if ($erro !== UPLOAD_ERR_OK) {  
            $response['erros'][] = "Erro no upload do arquivo {$nome_original}: " . upload_error_message($erro);  
            continue;  
        }  

        // Validar arquivo  
        $arquivo_temp = [  
            'name' => $nome_original,  
            'type' => $tipo,  
            'tmp_name' => $tmp_name,  
            'error' => $erro,  
            'size' => $tamanho  
        ];  

        $validacao = validar_arquivo($arquivo_temp);  
        if ($validacao !== true) {  
            $response['erros'][] = $validacao . " - Arquivo: {$nome_original}";  
            continue;  
        }  

        // Preparar nome do arquivo  
        $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));  
        $nome_arquivo = uniqid() . '.' . $extensao;  
        $caminho_completo = $diretorio . '/' . $nome_arquivo;  

        // Mover o arquivo  
        if (move_uploaded_file($tmp_name, $caminho_completo)) {  
            $diretorio_imagens = null;  
            $imagens_extraidas = [];  

            // Converter PDF para imagens  
            if ($extensao === 'pdf') {  
                $diretorio_imagens = $diretorio . '/' . pathinfo($nome_arquivo, PATHINFO_FILENAME);  
                $imagens_extraidas = convert_pdf_to_jpg($caminho_completo, $diretorio_imagens);  
                
                if ($imagens_extraidas === false) {  
                    log_message("Falha ao converter PDF para JPG: $caminho_completo");  
                } else {  
                    $imagens_total += count($imagens_extraidas);  
                    log_message("PDF convertido com sucesso. " . count($imagens_extraidas) . " imagens geradas.");  
                }  
            }  

            try {  
                // Usar transação para garantir integridade  
                $pdo->beginTransaction();  
                
                // Inserir registro do anexo  
                $stmt = $pdo->prepare("  
                    INSERT INTO anexos (selo_id, nome_arquivo, caminho, tipo, tamanho, data_upload, diretorio_imagens)   
                    VALUES (?, ?, ?, ?, ?, NOW(), ?)  
                ");  
                
                $stmt->execute([  
                    $selo_id,   
                    $nome_original,   
                    $caminho_completo,   
                    $tipo,   
                    $tamanho,   
                    $diretorio_imagens  
                ]);  

                $anexo_id = $pdo->lastInsertId();  

                // Registrar imagens extraídas  
                if (!empty($imagens_extraidas)) {  
                    $stmt = $pdo->prepare("INSERT INTO imagens_anexo (anexo_id, caminho, ordem) VALUES (?, ?, ?)");  
                    
                    foreach ($imagens_extraidas as $index => $imagem_path) {  
                        $stmt->execute([  
                            $anexo_id,  
                            $imagem_path,  
                            $index + 1  
                        ]);  
                    }  
                }  

                // Confirmar a transação  
                $pdo->commit();  
                $arquivos_processados++;  
                
            } catch (PDOException $e) {  
                // Desfazer a transação  
                $pdo->rollBack();  
                
                // Remover os arquivos  
                if (file_exists($caminho_completo)) {  
                    unlink($caminho_completo);  
                }  
                
                if (!empty($imagens_extraidas)) {  
                    foreach ($imagens_extraidas as $imagem_path) {  
                        if (file_exists($imagem_path)) {  
                            unlink($imagem_path);  
                        }  
                    }  
                    
                    if (is_dir($diretorio_imagens)) {  
                        rmdir($diretorio_imagens);  
                    }  
                }  
                
                $response['erros'][] = 'Erro ao salvar no banco: ' . $e->getMessage();  
                log_message("Erro ao salvar no banco: " . $e->getMessage());  
            }  
        } else {  
            $response['erros'][] = "Falha ao mover o arquivo {$nome_original}.";  
            log_message("Falha ao mover o arquivo para: $caminho_completo");  
        }  
    }  

    // Preparar resposta  
    $response['arquivos_processados'] = $arquivos_processados;  
    
    if ($arquivos_processados > 0) {  
        $response['success'] = true;  
        
        if ($arquivos_totais === 1) {  
            $response['message'] = "Arquivo enviado com sucesso!";  
            if ($imagens_total > 0) {  
                $response['message'] .= " PDF convertido em {$imagens_total} imagens.";  
            }  
        } else {  
            $response['message'] = "{$arquivos_processados} arquivos enviados com sucesso.";  
            if ($imagens_total > 0) {  
                $response['message'] .= " {$imagens_total} imagens extraídas de PDFs.";  
            }  
        }  
        
        if (count($response['erros']) > 0) {  
            $response['message'] .= ' Alguns arquivos apresentaram erro.';  
        }  
    } else {  
        $response['message'] = 'Nenhum arquivo foi processado com sucesso.';  
    }  
} else {  
    $response['message'] = 'Requisição inválida. Método esperado: POST.';  
}  

// Marca para gerar saída JSON  
output_json:  

// Garantir que headers e saída sejam JSON limpo  
header('Content-Type: application/json; charset=utf-8');  
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);  
exit;