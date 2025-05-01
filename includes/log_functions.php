<?php  
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
?>