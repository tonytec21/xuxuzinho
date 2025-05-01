<?php  
date_default_timezone_set('America/Sao_Paulo'); 
require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  
require_once 'tcpdf/tcpdf.php';  

$selo_id = intval($_GET['id'] ?? 0);
$usuario_id = $_SESSION['usuario_id'];
$nome_usuario = $_SESSION['nome'] ?? '';

// Registrar o download
$stmt = $pdo->prepare("INSERT INTO downloads_selo (selo_id, usuario_id, nome_usuario) VALUES (?, ?, ?)");
$stmt->execute([$selo_id, $usuario_id, $nome_usuario]);

// Função para registrar logs  
function log_message($message) {  
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $message);  
}  

// Verificar se o ID do selo foi fornecido  
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {  
    header("Location: selos.php?error=invalid_id");  
    exit;  
}  

try {   
    // Verificar se o selo existe  
    $stmt = $pdo->prepare("SELECT * FROM selos WHERE id = ?");  
    $stmt->execute([$selo_id]);  
    $selo = $stmt->fetch();  
    
    if (!$selo) {  
        header("Location: selos.php?error=not_found");  
        exit;  
    }
 
    // Buscar anexos do selo  
    $stmt = $pdo->prepare("SELECT * FROM anexos WHERE selo_id = ? AND status = 'ativo' ORDER BY data_upload");  
    $stmt->execute([$selo_id]);  
    $anexos = $stmt->fetchAll();  
    
    if (count($anexos) === 0) {  
        header("Location: selos.php?id=" . $selo_id . "&error=no_attachments");  
        exit;  
    }  
    
    // Criar um novo documento PDF  
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);  
    $pdf->SetMargins(0, 0, 0);  
    $pdf->SetAutoPageBreak(false, 0);  
    $pdf->setPrintHeader(false);  
    $pdf->setPrintFooter(false);  
    
    // Processar cada anexo  
    foreach ($anexos as $anexo) {  
        // Verificar se o arquivo existe  
        if (!file_exists($anexo['caminho'])) {  
            log_message("Anexo não encontrado: " . $anexo['caminho']);  
            continue; // Pular este anexo  
        }  
        
        // Determinar o tipo do arquivo  
        $file_type = strtolower(pathinfo($anexo['caminho'], PATHINFO_EXTENSION));  
        
        if (in_array($file_type, ['jpg', 'jpeg', 'png', 'gif'])) {  
            // É uma imagem, vamos adicionar ao PDF  
            log_message("Processando imagem: " . $anexo['caminho']);  
            
            // Adicionar nova página  
            $pdf->AddPage();  
            
                        // Obter dimensões da imagem  
                        list($width, $height) = getimagesize($anexo['caminho']);  
            
                        // Calcular tamanho proporcional para caber na página  
                        $pageWidth = $pdf->getPageWidth();  
                        $pageHeight = $pdf->getPageHeight();  
                        
                        $ratio = min($pageWidth / $width, $pageHeight / $height);  
                        $newWidth = $width * $ratio;  
                        $newHeight = $height * $ratio;  
                        
                        // Calcular posição centralizada  
                        $x = ($pageWidth - $newWidth) / 2;  
                        $y = ($pageHeight - $newHeight) / 2;  
                        
                        // Adicionar imagem  
                        $pdf->Image($anexo['caminho'], $x, $y, $newWidth, $newHeight, '', '', '', false, 300);  
                    }   
                    elseif ($file_type == 'pdf') {  
                        log_message("Processando PDF: " . $anexo['caminho']);  
                        
                        // Verificar se o diretório de imagens existe  
                        if (!empty($anexo['diretorio_imagens']) && is_dir($anexo['diretorio_imagens'])) {  
                            // Buscar imagens extraídas organizadas por ordem  
                            $stmt = $pdo->prepare("SELECT * FROM imagens_anexo WHERE anexo_id = ? ORDER BY ordem");  
                            $stmt->execute([$anexo['id']]);  
                            $imagens = $stmt->fetchAll();  
                            
                            if (count($imagens) > 0) {  
                                // Usar as imagens já extraídas do banco de dados  
                                foreach ($imagens as $imagem) {  
                                    if (!file_exists($imagem['caminho'])) {  
                                        log_message("Imagem extraída não encontrada: " . $imagem['caminho']);  
                                        continue;  
                                    }  
                                    
                                    // Adicionar nova página  
                                    $pdf->AddPage();  
                                    
                                    // Obter dimensões da imagem  
                                    list($width, $height) = getimagesize($imagem['caminho']);  
                                    
                                    // Calcular tamanho proporcional para caber na página  
                                    $pageWidth = $pdf->getPageWidth();  
                                    $pageHeight = $pdf->getPageHeight();  
                                    
                                    $ratio = min($pageWidth / $width, $pageHeight / $height);  
                                    $newWidth = $width * $ratio;  
                                    $newHeight = $height * $ratio;  
                                    
                                    // Calcular posição centralizada  
                                    $x = ($pageWidth - $newWidth) / 2;  
                                    $y = ($pageHeight - $newHeight) / 2;  
                                    
                                    // Adicionar imagem  
                                    $pdf->Image($imagem['caminho'], $x, $y, $newWidth, $newHeight, '', '', '', false, 300);  
                                }  
                            } else {  
                                // Buscar imagens do diretório (fallback)  
                                $imagens = glob($anexo['diretorio_imagens'] . '/page_*.jpg');  
                                
                                // Ordenar numericamente para garantir sequência correta  
                                usort($imagens, function($a, $b) {  
                                    preg_match('/page_(\d+)\.jpg$/', $a, $matchesA);  
                                    preg_match('/page_(\d+)\.jpg$/', $b, $matchesB);  
                                    $numA = isset($matchesA[1]) ? intval($matchesA[1]) : 0;  
                                    $numB = isset($matchesB[1]) ? intval($matchesB[1]) : 0;  
                                    return $numA - $numB;  
                                });  
                                
                                foreach ($imagens as $imagem_path) {  
                                    if (!file_exists($imagem_path)) {  
                                        log_message("Imagem não encontrada: " . $imagem_path);  
                                        continue;  
                                    }  
                                    
                                    // Adicionar nova página  
                                    $pdf->AddPage();  
                                    
                                    // Obter dimensões da imagem  
                                    list($width, $height) = getimagesize($imagem_path);  
                                    
                                    // Calcular tamanho proporcional para caber na página  
                                    $pageWidth = $pdf->getPageWidth();  
                                    $pageHeight = $pdf->getPageHeight();  
                                    
                                    $ratio = min($pageWidth / $width, $pageHeight / $height);  
                                    $newWidth = $width * $ratio;  
                                    $newHeight = $height * $ratio;  
                                    
                                    // Calcular posição centralizada  
                                    $x = ($pageWidth - $newWidth) / 2;  
                                    $y = ($pageHeight - $newHeight) / 2;  
                                    
                                    // Adicionar imagem  
                                    $pdf->Image($imagem_path, $x, $y, $newWidth, $newHeight, '', '', '', false, 300);  
                                }  
                            }  
                        } else {  
                            // Nenhuma imagem extraída disponível, tentar converter na hora  
                            log_message("Tentando converter PDF para imagens em tempo real");  
                            
                            // Verificar se o script Python existe  
                            $python_script = __DIR__ . '/pdf_to_jpg.py';  
                            if (file_exists($python_script)) {  
                                // Criar diretório temporário para as imagens  
                                $temp_dir = sys_get_temp_dir() . '/pdf_images_' . time() . '_' . rand(1000, 9999);  
                                if (!file_exists($temp_dir)) {  
                                    mkdir($temp_dir, 0777, true);  
                                }  
                                
                                // Executar o script Python para converter PDF para imagens  
                                $python_command = 'python'; // ou 'python3' dependendo do ambiente  
                                $command = escapeshellcmd($python_command) . ' ' .   
                                           escapeshellarg($python_script) . ' ' .   
                                           escapeshellarg($anexo['caminho']) . ' ' .   
                                           escapeshellarg($temp_dir);  
                                
                                $output = [];  
                                $return_var = 0;  
                                exec($command, $output, $return_var);  
                                
                                if ($return_var === 0 && !empty($output)) {  
                                    // Processar cada imagem gerada  
                                    foreach ($output as $imagem_path) {  
                                        if (!file_exists($imagem_path)) {  
                                            continue;  
                                        }  
                                        
                                        // Adicionar nova página  
                                        $pdf->AddPage();  
                                        
                                        // Obter dimensões da imagem  
                                        list($width, $height) = getimagesize($imagem_path);  
                                        
                                        // Calcular tamanho proporcional para caber na página  
                                        $pageWidth = $pdf->getPageWidth();  
                                        $pageHeight = $pdf->getPageHeight();  
                                        
                                        $ratio = min($pageWidth / $width, $pageHeight / $height);  
                                        $newWidth = $width * $ratio;  
                                        $newHeight = $height * $ratio;  
                                        
                                        // Calcular posição centralizada  
                                        $x = ($pageWidth - $newWidth) / 2;  
                                        $y = ($pageHeight - $newHeight) / 2;  
                                        
                                        // Adicionar imagem  
                                        $pdf->Image($imagem_path, $x, $y, $newWidth, $newHeight, '', '', '', false, 300);  
                                        
                                        // Marcar para remoção posterior  
                                        $temp_files[] = $imagem_path;  
                                    }  
                                    
                                    // Marcar diretório para remoção  
                                    $temp_dirs[] = $temp_dir;  
                                } else {  
                                    // Falhou na conversão em tempo real, adicionar página de aviso  
                                    $pdf->AddPage();  
                                    $pdf->SetFont('helvetica', 'B', 16);  
                                    $pdf->SetXY(10, 50);  
                                    $pdf->Cell(0, 10, 'PDF incluído no selo: ' . $anexo['nome_arquivo'], 0, 1, 'C');  
                                    $pdf->SetFont('helvetica', '', 12);  
                                    $pdf->SetXY(10, 70);  
                                    $pdf->MultiCell(0, 10, 'Este anexo é um arquivo PDF que não pôde ser convertido em imagens. Por favor, baixe o anexo original para visualizá-lo.', 0, 'C');  
                                }  
                            } else {  
                                // Script Python não encontrado, adicionar página de aviso  
                                $pdf->AddPage();  
                                $pdf->SetFont('helvetica', 'B', 16);  
                                $pdf->SetXY(10, 50);  
                                $pdf->Cell(0, 10, 'PDF incluído no selo: ' . $anexo['nome_arquivo'], 0, 1, 'C');  
                                $pdf->SetFont('helvetica', '', 12);  
                                $pdf->SetXY(10, 70);  
                                $pdf->MultiCell(0, 10, 'Este anexo é um arquivo PDF que não pôde ser incorporado neste documento. Por favor, baixe o anexo original para visualizá-lo.', 0, 'C');  
                            }  
                        }  
                    }  
                }  
                
                // Gerar o arquivo PDF  
                $pdf_nome = $selo['numero'] . ' - Anexos Compilados.pdf';  
                $pdf->Output($pdf_nome, 'D'); // 'D' força o download  
                
                // Limpar arquivos temporários  
                if (!empty($temp_files)) {  
                    foreach ($temp_files as $temp_file) {  
                        if (file_exists($temp_file)) {  
                            @unlink($temp_file);  
                        }  
                    }  
                }  
                
                // Limpar diretórios temporários  
                if (!empty($temp_dirs)) {  
                    foreach ($temp_dirs as $temp_dir) {  
                        if (is_dir($temp_dir)) {  
                            $files = glob($temp_dir . '/*');  
                            foreach ($files as $file) {  
                                @unlink($file);  
                            }  
                            @rmdir($temp_dir);  
                        }  
                    }  
                }  
                
            } catch (Exception $e) {  
                // Log do erro e redirecionamento  
                log_message("Erro ao gerar documento compilado: " . $e->getMessage());  
                header("Location: selos.php?error=pdf_error&msg=" . urlencode($e->getMessage()));  
                exit;  
            }