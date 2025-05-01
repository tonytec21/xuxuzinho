<?php  
date_default_timezone_set('America/Sao_Paulo'); 
require_once 'includes/auth_check.php';  

// Registrar a ação de logout antes de destruir a sessão  
if (function_exists('registrar_log')) {  
    registrar_log('logout', 'Usuário encerrou sessão');  
}  

// Obter o caminho do cookie da sessão  
$session_cookie_params = session_get_cookie_params();  

// Limpar todas as variáveis de sessão  
$_SESSION = array();  

// Destruir o cookie da sessão, se existir  
if (isset($_COOKIE[session_name()])) {  
    setcookie(  
        session_name(),  
        '',  
        time() - 42000,  
        $session_cookie_params['path'],  
        $session_cookie_params['domain'],  
        $session_cookie_params['secure'],  
        $session_cookie_params['httponly']  
    );  
}  

// Destruir a sessão  
session_destroy();  

// Adicionar um parâmetro para evitar cache  
$redirect_url = 'login.php?logout=' . time();  

// Redirecionar para a página de login  
header("Location: $redirect_url");  
exit;