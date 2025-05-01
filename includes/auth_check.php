<?php  
session_start();  

/**  
 * Função para registrar ações do usuário em log  
 *   
 * @param string $acao Ação realizada pelo usuário  
 * @param string $tabela_afetada Nome da tabela afetada pela ação  
 * @param int $registro_id ID do registro afetado  
 * @param string $detalhes Detalhes adicionais sobre a ação  
 * @param int|null $usuario_id ID do usuário (opcional, usado principalmente para login)  
 * @param string|null $usuario_nome Nome do usuário (opcional, usado principalmente para login)  
 * @return bool Retorna true se o log foi registrado com sucesso, false caso contrário  
 */  
function registrar_log($acao, $tabela_afetada = '', $registro_id = 0, $detalhes = null, $usuario_id = null, $usuario_nome = null) {  
    global $pdo;  
    
    try {  
        // Verificar se a tabela de logs existe  
        $tabela_existe = $pdo->query("SHOW TABLES LIKE 'logs_sistema'")->rowCount() > 0;  
        
        if ($tabela_existe) {  
            // Se não foram fornecidos IDs e nomes específicos, usar os da sessão  
            if ($usuario_id === null) {  
                $usuario_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 0;  
            }  
            
            if ($usuario_nome === null) {  
                $usuario_nome = isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Sistema';  
            }  
            
            $stmt = $pdo->prepare("  
                INSERT INTO logs_sistema   
                (usuario_id, usuario_nome, acao, tabela_afetada, registro_id, data_hora, detalhes)  
                VALUES (?, ?, ?, ?, ?, NOW(), ?)  
            ");  
            $stmt->execute([  
                $usuario_id,  
                $usuario_nome,  
                $acao,  
                $tabela_afetada,  
                $registro_id,  
                $detalhes  
            ]);  
            return true;  
        }  
        return false;  
    } catch (PDOException $e) {  
        error_log("Erro ao registrar log: " . $e->getMessage());  
        return false;  
    }  
}  

// Verificar se o usuário está logado  
if (!isset($_SESSION['usuario_id'])) {  
    // Redirecionar para a página de login  
    header("Location: login.php");  
    exit;  
}  

// Verificar se o status do usuário é aprovado  
require_once 'db_connection.php';  

// Verificar se precisamos buscar o nome do usuário (se não estiver na sessão)  
if (!isset($_SESSION['nome']) || !isset($_SESSION['usuario_nome'])) {  
    $stmt = $pdo->prepare("SELECT nome, status FROM usuarios WHERE id = ?");  
    $stmt->execute([$_SESSION['usuario_id']]);  
    $usuario = $stmt->fetch();  
    
    if ($usuario) {  
        $_SESSION['nome'] = $usuario['nome'];  
        $_SESSION['usuario_nome'] = $usuario['nome']; // Para compatibilidade  
        
        // Verificação de status abaixo  
        if ($usuario['status'] !== 'aprovado') {  
            session_destroy();  
            header("Location: login.php?erro=conta_pendente");  
            exit;  
        }  
    } else {  
        // Se o usuário não for encontrado no banco de dados  
        session_destroy();  
        header("Location: login.php?erro=usuario_nao_encontrado");  
        exit;  
    }  
} else {  
    // Se o nome já estiver na sessão, ainda precisa verificar o status  
    $stmt = $pdo->prepare("SELECT status FROM usuarios WHERE id = ?");  
    $stmt->execute([$_SESSION['usuario_id']]);  
    $usuario = $stmt->fetch();  
    
    if (!$usuario || $usuario['status'] !== 'aprovado') {  
        session_destroy();  
        header("Location: login.php?erro=conta_pendente");  
        exit;  
    }  
}  

// Função para verificar se o usuário é admin  
function is_admin() {  
    global $pdo;  
    $stmt = $pdo->prepare("SELECT tipo FROM usuarios WHERE id = ?");  
    $stmt->execute([$_SESSION['usuario_id']]);  
    $usuario = $stmt->fetch();  
    return $usuario['tipo'] === 'admin';  
}  
?>