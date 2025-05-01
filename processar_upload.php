<?php  
date_default_timezone_set('America/Sao_Paulo'); 
require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  

// Função para registrar logs  
function log_message($message) {  
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $message);  
}  

// Função para converter PDF para JPG usando Python  
function convert_pdf_to_jpg($pdf_path, $output_dir) {  
    // Verificar se o arquivo Python existe  
    $python_script = __DIR__ . '/pdf_to_jpg.py';  
    if (!file_exists($python_script)) {  
        log_message("Script Python não encontrado: $python_script");  
        return false;  
    }  
    
    // Verificar se o diretório de saída existe, se não, criar  
    if (!file_exists($output_dir)) {  
        if (!mkdir($output_dir, 0777, true)) {  
            log_message("Não foi possível criar o diretório: $output_dir");  
            return false;  
        }  
    }  
    
    // Montar o comando  
    $python_command = 'python'; // ou 'python3' dependendo do ambiente  
    $command = escapeshellcmd($python_command) . ' ' .   
               escapeshellarg($python_script) . ' ' .   
               escapeshellarg($pdf_path) . ' ' .   
               escapeshellarg($output_dir);  
    
    // Executar o comando  
    log_message("Executando comando: $command");  
    $output = [];  
    $return_var = 0;  
    exec($command, $output, $return_var);  
    
    // Verificar se o comando foi executado com sucesso  
    if ($return_var !== 0) {  
        log_message("Erro ao executar o comando. Código de retorno: $return_var");  
        log_message("Saída do comando: " . implode("\n", $output));  
        return false;  
    }  
    
    // Retornar os caminhos das imagens geradas  
    return $output;  
}  

// Verificar se a requisição é POST  
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {  
    header('HTTP/1.1 405 Method Not Allowed');  
    exit('Método não permitido');  
}  

// Verificar se o ID do selo foi fornecido  
if (!isset($_POST['selo_id']) || !is_numeric($_POST['selo_id'])) {  
    echo json_encode(['status' => 'error', 'message' => 'ID de selo inválido']);  
    exit;  
}  

$selo_id = $_POST['selo_id'];  
$usuario_id = $_SESSION['usuario_id'];  

// Verificar se o selo pertence ao usuário atual  
$stmt = $pdo->prepare("SELECT * FROM selos WHERE id = ? AND usuario_id = ?");  
$stmt->execute([$selo_id, $usuario_id]);  
$selo = $stmt->fetch();  

if (!$selo) {  
    echo json_encode(['status' => 'error', 'message' => 'Selo não encontrado']);  
    exit;  
}  

// Verificar se arquivo foi enviado  
if (!isset($_FILES['anexo']) || $_FILES['anexo']['error'] != UPLOAD_ERR_OK) {  
    $error_message = isset($_FILES['anexo']) ? upload_error_message($_FILES['anexo']['error']) : 'Nenhum arquivo enviado';  
    echo json_encode(['status' => 'error', 'message' => $error_message]);  
    exit;  
}  

// Definir o diretório de uploads  
$upload_dir = 'uploads/' . $usuario_id . '/' . $selo_id . '/';  
if (!file_exists($upload_dir)) {  
    mkdir($upload_dir, 0777, true);  
}  

// Obter informações do arquivo  
$file_name = $_FILES['anexo']['name'];  
$file_tmp = $_FILES['anexo']['tmp_name'];  
$file_size = $_FILES['anexo']['size'];  
$file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));  

// Gerar um nome único para o arquivo  
$unique_name = uniqid() . '_' . $file_name;  
$file_destination = $upload_dir . $unique_name;  

// Validar tipo de arquivo (PDF, JPG, PNG, etc.)  
$allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];  
if (!in_array($file_type, $allowed_types)) {  
    echo json_encode(['status' => 'error', 'message' => 'Tipo de arquivo não permitido']);  
    exit;  
}  

// Mover o arquivo para o destino  
if (!move_uploaded_file($file_tmp, $file_destination)) {  
    echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar o arquivo']);  
    exit;  
}  

// Se for um PDF, converter para JPG  
$images_dir = '';  
$image_paths = [];  
if ($file_type === 'pdf') {  
    $images_dir = $upload_dir . pathinfo($unique_name, PATHINFO_FILENAME) . '/';  
    $image_paths = convert_pdf_to_jpg($file_destination, $images_dir);  
    
    if ($image_paths === false) {  
        log_message("Falha ao converter PDF para JPG: $file_destination");  
        // Continuar mesmo se a conversão falhar - o PDF original foi salvo  
    }  
}  

// Inserir informações do anexo no banco de dados  
try {  
    $stmt = $pdo->prepare("INSERT INTO anexos (selo_id, nome_arquivo, caminho, tipo, tamanho, data_upload, diretorio_imagens)   
                         VALUES (?, ?, ?, ?, ?, NOW(), ?)");  
    $stmt->execute([  
        $selo_id,  
        $file_name,  
        $file_destination,  
        $file_type,  
        $file_size,  
        $images_dir ?: null  
    ]);  
    
    $anexo_id = $pdo->lastInsertId();  
    
    // Se houver imagens extraídas do PDF, registrar na tabela  
    if (!empty($image_paths)) {  
        $stmt = $pdo->prepare("INSERT INTO imagens_anexo (anexo_id, caminho, ordem) VALUES (?, ?, ?)");  
        
        foreach ($image_paths as $index => $image_path) {  
            $stmt->execute([  
                $anexo_id,  
                $image_path,  
                $index + 1  
            ]);  
        }  
    }  
    
    echo json_encode([  
        'status' => 'success',  
        'message' => 'Anexo enviado com sucesso',  
        'anexo' => [  
            'id' => $anexo_id,  
            'nome' => $file_name,  
            'caminho' => $file_destination,  
            'tipo' => $file_type,  
            'tamanho' => $file_size,  
            'imagens' => $image_paths  
        ]  
    ]);  
} catch (PDOException $e) {  
    log_message("Erro ao salvar anexo no banco: " . $e->getMessage());  
    echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar o anexo no banco de dados']);  
    exit;  
}  

// Função para converter código de erro de upload em mensagem legível  
function upload_error_message($error_code) {  
    switch ($error_code) {  
        case UPLOAD_ERR_INI_SIZE:  
            return 'O arquivo excede o tamanho máximo permitido pelo PHP';  
        case UPLOAD_ERR_FORM_SIZE:  
            return 'O arquivo excede o tamanho máximo permitido pelo formulário';  
        case UPLOAD_ERR_PARTIAL:  
            return 'O arquivo foi enviado parcialmente';  
        case UPLOAD_ERR_NO_FILE:  
            return 'Nenhum arquivo foi enviado';  
        case UPLOAD_ERR_NO_TMP_DIR:  
            return 'Diretório temporário não encontrado';  
        case UPLOAD_ERR_CANT_WRITE:  
            return 'Falha ao escrever o arquivo no disco';  
        case UPLOAD_ERR_EXTENSION:  
            return 'Uma extensão PHP interrompeu o upload do arquivo';  
        default:  
            return 'Erro desconhecido no upload';  
    }  
}