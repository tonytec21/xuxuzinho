<?php  
// Função para criar um diretório de selo de forma segura  
function criar_diretorio_selo($selo_id) {  
    $dir = "../uploads/selo_" . $selo_id;  
    if (!file_exists($dir)) {  
        mkdir($dir, 0755, true);  
    }  
    return $dir;  
}  

// Função para validar upload de arquivo  
function validar_arquivo($file) {  
    $tipos_permitidos = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];  
    $tamanho_maximo = 10 * 1024 * 1024; // 10MB  
    
    if (!in_array($file['type'], $tipos_permitidos)) {  
        return "Tipo de arquivo não permitido. Use apenas PDF, JPG ou PNG.";  
    }  
    
    if ($file['size'] > $tamanho_maximo) {  
        return "Tamanho máximo do arquivo excedido. Limite de 10MB.";  
    }  
    
    return true;  
}  

// Função para gerar token aleatório para recuperação de senha  
function gerar_token() {  
    return bin2hex(random_bytes(32));  
}  

// Função para sanitizar dados de entrada  
function sanitize($data) {  
    $data = trim($data);  
    $data = stripslashes($data);  
    $data = htmlspecialchars($data);  
    return $data;  
}  

function set_mensagem($tipo, $texto) {  
    $_SESSION['mensagem'] = [  
        'tipo' => $tipo,  
        'texto' => $texto  
    ];  
}  
?>