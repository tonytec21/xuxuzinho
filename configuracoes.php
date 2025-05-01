<?php  
date_default_timezone_set('America/Sao_Paulo'); 
require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  

// Inicializar variáveis  
$mensagem = '';  
$tipo_mensagem = '';  
$usuario_id = $_SESSION['usuario_id'];  

// Buscar configurações atuais do usuário  
try {  
    $stmt = $pdo->prepare("SELECT   
        notificacoes_email,   
        autenticacao_dois_fatores,  
        tema_escuro,  
        idioma,  
        timezone   
    FROM usuarios WHERE id = ?");  
    $stmt->execute([$usuario_id]);  
    $config = $stmt->fetch(PDO::FETCH_ASSOC);  
    
    // Se as colunas não existirem, definir valores padrão  
    if (!isset($config['notificacoes_email'])) {  
        $config['notificacoes_email'] = 1;  
    }  
    if (!isset($config['autenticacao_dois_fatores'])) {  
        $config['autenticacao_dois_fatores'] = 0;  
    }  
    if (!isset($config['tema_escuro'])) {  
        $config['tema_escuro'] = 0;  
    }  
    if (!isset($config['idioma'])) {  
        $config['idioma'] = 'pt_BR';  
    }  
    if (!isset($config['timezone'])) {  
        $config['timezone'] = 'America/Sao_Paulo';  
    }  
    
} catch (PDOException $e) {  
    error_log("Erro ao buscar configurações: " . $e->getMessage());  
    $config = [  
        'notificacoes_email' => 1,  
        'autenticacao_dois_fatores' => 0,  
        'tema_escuro' => 0,  
        'idioma' => 'pt_BR',  
        'timezone' => 'America/Sao_Paulo'  
    ];  
}  

// Processar formulário de configurações  
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_configuracoes'])) {  
    $notificacoes_email = isset($_POST['notificacoes_email']) ? 1 : 0;  
    $autenticacao_dois_fatores = isset($_POST['autenticacao_dois_fatores']) ? 1 : 0;  
    $tema_escuro = isset($_POST['tema_escuro']) ? 1 : 0;  
    $idioma = $_POST['idioma'];  
    $timezone = $_POST['timezone'];  
    
    try {  
        // Verificar se as colunas existem na tabela  
        $stmt = $pdo->query("DESCRIBE usuarios");  
        $colunas_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);  
        
        // Preparar a consulta SQL  
        $sql = "UPDATE usuarios SET ";  
        $params = [];  
        
        // Adicionar colunas que existem  
        if (in_array('notificacoes_email', $colunas_existentes)) {  
            $sql .= "notificacoes_email = ?, ";  
            $params[] = $notificacoes_email;  
        }  
        if (in_array('autenticacao_dois_fatores', $colunas_existentes)) {  
            $sql .= "autenticacao_dois_fatores = ?, ";  
            $params[] = $autenticacao_dois_fatores;  
        }  
        if (in_array('tema_escuro', $colunas_existentes)) {  
            $sql .= "tema_escuro = ?, ";  
            $params[] = $tema_escuro;  
        }  
        if (in_array('idioma', $colunas_existentes)) {  
            $sql .= "idioma = ?, ";  
            $params[] = $idioma;  
        }  
        if (in_array('timezone', $colunas_existentes)) {  
            $sql .= "timezone = ?, ";  
            $params[] = $timezone;  
        }  
        
        // Remover a vírgula final  
        $sql = rtrim($sql, ", ");  
        $sql .= " WHERE id = ?";  
        $params[] = $usuario_id;  
        
        // Executar a consulta apenas se tiver campos para atualizar  
        if (count($params) > 1) {  
            $stmt = $pdo->prepare($sql);  
            $stmt->execute($params);  
            
            // Atualizar a sessão para refletir as novas configurações  
            $_SESSION['tema_escuro'] = $tema_escuro;  
            
            $mensagem = "Configurações atualizadas com sucesso!";  
            $tipo_mensagem = "success";  
            
            // Atualizar variáveis locais  
            $config['notificacoes_email'] = $notificacoes_email;  
            $config['autenticacao_dois_fatores'] = $autenticacao_dois_fatores;  
            $config['tema_escuro'] = $tema_escuro;  
            $config['idioma'] = $idioma;  
            $config['timezone'] = $timezone;  
        } else {  
            $mensagem = "Nenhuma configuração foi atualizada. As colunas necessárias não existem na tabela.";  
            $tipo_mensagem = "warning";  
        }  
    } catch (PDOException $e) {  
        $mensagem = "Erro ao atualizar configurações: " . $e->getMessage();  
        $tipo_mensagem = "danger";  
        error_log("Erro ao atualizar configurações do usuário ID $usuario_id: " . $e->getMessage());  
    }  
}  

// Processar formulário de exclusão de dados  
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_exclusao'])) {  
    try {  
        // Verificar se já existe uma solicitação pendente  
        $stmt = $pdo->prepare("SELECT id FROM solicitacoes_exclusao WHERE usuario_id = ? AND status = 'pendente'");  
        $stmt->execute([$usuario_id]);  
        
        if ($stmt->rowCount() > 0) {  
            $mensagem_exclusao = "Você já possui uma solicitação de exclusão de dados pendente.";  
            $tipo_mensagem_exclusao = "warning";  
        } else {  
            // Criar uma nova solicitação  
            $stmt = $pdo->prepare("INSERT INTO solicitacoes_exclusao (usuario_id, data_solicitacao, status) VALUES (?, NOW(), 'pendente')");  
            $stmt->execute([$usuario_id]);  
            
            $mensagem_exclusao = "Solicitação de exclusão de dados enviada com sucesso. Você será notificado quando for processada.";  
            $tipo_mensagem_exclusao = "success";  
        }  
    } catch (PDOException $e) {  
        $mensagem_exclusao = "Erro ao processar solicitação: " . $e->getMessage();  
        $tipo_mensagem_exclusao = "danger";  
        
        // Verificar se a tabela existe  
        if (strpos($e->getMessage(), "Table") !== false && strpos($e->getMessage(), "doesn't exist") !== false) {  
            // Tentar criar a tabela  
            try {  
                $pdo->exec("CREATE TABLE IF NOT EXISTS solicitacoes_exclusao (  
                    id INT(11) NOT NULL AUTO_INCREMENT,  
                    usuario_id INT(11) NOT NULL,  
                    data_solicitacao DATETIME NOT NULL,  
                    data_processamento DATETIME DEFAULT NULL,  
                    status ENUM('pendente', 'concluida', 'rejeitada') DEFAULT 'pendente',  
                    observacoes TEXT DEFAULT NULL,  
                    PRIMARY KEY (id),  
                    KEY (usuario_id),  
                    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE  
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");  
                
                // Tentar novamente  
                $stmt = $pdo->prepare("INSERT INTO solicitacoes_exclusao (usuario_id, data_solicitacao, status) VALUES (?, NOW(), 'pendente')");  
                $stmt->execute([$usuario_id]);  
                
                $mensagem_exclusao = "Solicitação de exclusão de dados enviada com sucesso. Você será notificado quando for processada.";  
                $tipo_mensagem_exclusao = "success";  
            } catch (PDOException $e2) {  
                error_log("Erro ao criar tabela solicitacoes_exclusao: " . $e2->getMessage());  
                $mensagem_exclusao = "Não foi possível processar sua solicitação. Por favor, entre em contato com o suporte.";  
                $tipo_mensagem_exclusao = "danger";  
            }  
        }  
    }  
}  

// Verificar status de solicitação de exclusão  
$tem_solicitacao_pendente = false;  
try {  
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'solicitacoes_exclusao'");  
    $stmt->execute();  
    
    if ($stmt->rowCount() > 0) {  
        $stmt = $pdo->prepare("SELECT id, data_solicitacao, status FROM solicitacoes_exclusao WHERE usuario_id = ? ORDER BY id DESC LIMIT 1");  
        $stmt->execute([$usuario_id]);  
        $solicitacao = $stmt->fetch(PDO::FETCH_ASSOC);  
        
        if ($solicitacao && $solicitacao['status'] === 'pendente') {  
            $tem_solicitacao_pendente = true;  
        }  
    }  
} catch (PDOException $e) {  
    error_log("Erro ao verificar solicitações de exclusão: " . $e->getMessage());  
}  

// Incluir o cabeçalho  
include 'includes/header.php';  
?>  

<div class="container-fluid py-4">  
    <div class="row mb-4">  
        <div class="col-12">  
            <h1 class="fw-bold">Configurações</h1>  
            <p class="text-muted">Personalize sua experiência na plataforma</p>  
        </div>  
    </div>  
    
    <?php if (!empty($mensagem)): ?>  
    <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">  
        <?php echo $mensagem; ?>  
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>  
    </div>  
    <?php endif; ?>  
    
    <div class="row">  
        <!-- Menu lateral de configurações -->  
        <div class="col-md-3 mb-4">  
            <div class="card border-0 shadow-sm">  
                <div class="card-body p-0">  
                    <div class="list-group list-group-flush rounded-0">  
                        <a href="#config-geral" class="list-group-item list-group-item-action active" data-bs-toggle="list">  
                            <i data-feather="settings" class="feather-sm me-2"></i> Geral  
                        </a>  
                        <a href="#config-notificacoes" class="list-group-item list-group-item-action" data-bs-toggle="list">  
                            <i data-feather="bell" class="feather-sm me-2"></i> Notificações  
                        </a>  
                        <a href="#config-seguranca" class="list-group-item list-group-item-action" data-bs-toggle="list">  
                            <i data-feather="shield" class="feather-sm me-2"></i> Segurança  
                        </a>  
                        <a href="#config-privacidade" class="list-group-item list-group-item-action" data-bs-toggle="list">  
                            <i data-feather="lock" class="feather-sm me-2"></i> Privacidade  
                        </a>  
                    </div>  
                </div>  
            </div>  
        </div>  
        
        <!-- Conteúdo das configurações -->  
        <div class="col-md-9">  
            <div class="tab-content">  
                <!-- Configurações Gerais -->  
                <div class="tab-pane fade show active" id="config-geral">  
                    <div class="card border-0 shadow-sm mb-4">  
                        <div class="card-header bg-white">  
                            <h5 class="mb-0">Configurações Gerais</h5>  
                        </div>  
                        <div class="card-body">  
                            <form method="POST" action="configuracoes.php">  
                                <div class="mb-3">  
                                    <label for="idioma" class="form-label">Idioma</label>  
                                    <select class="form-select" id="idioma" name="idioma">  
                                        <option value="pt_BR" <?php echo ($config['idioma'] === 'pt_BR') ? 'selected' : ''; ?>>Português (Brasil)</option>  
                                        <option value="en_US" <?php echo ($config['idioma'] === 'en_US') ? 'selected' : ''; ?>>Inglês (EUA)</option>  
                                        <option value="es_ES" <?php echo ($config['idioma'] === 'es_ES') ? 'selected' : ''; ?>>Espanhol</option>  
                                    </select>  
                                </div>  
                                
                                <div class="mb-3">  
                                    <label for="timezone" class="form-label">Fuso Horário</label>  
                                    <select class="form-select" id="timezone" name="timezone">  
                                        <option value="America/Sao_Paulo" <?php echo ($config['timezone'] === 'America/Sao_Paulo') ? 'selected' : ''; ?>>Brasília (GMT-3)</option>  
                                        <option value="America/Manaus" <?php echo ($config['timezone'] === 'America/Manaus') ? 'selected' : ''; ?>>Manaus (GMT-4)</option>  
                                        <option value="America/Belem" <?php echo ($config['timezone'] === 'America/Belem') ? 'selected' : ''; ?>>Belém (GMT-3)</option>  
                                        <option value="America/Fortaleza" <?php echo ($config['timezone'] === 'America/Fortaleza') ? 'selected' : ''; ?>>Fortaleza (GMT-3)</option>  
                                    </select>  
                                </div>  
                                
                                <div class="form-check form-switch mb-3">  
                                    <input class="form-check-input" type="checkbox" id="tema_escuro" name="tema_escuro" <?php echo ($config['tema_escuro'] == 1) ? 'checked' : ''; ?>>  
                                    <label class="form-check-label" for="tema_escuro">Ativar tema escuro</label>  
                                </div>  
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">  
                                    <button type="submit" name="salvar_configuracoes" class="btn btn-primary">Salvar Configurações</button>  
                                </div>  
                            </form>  
                        </div>  
                    </div>  
                </div>  
                
                                <!-- Configurações de Notificações -->  
                                <div class="tab-pane fade" id="config-notificacoes">  
                    <div class="card border-0 shadow-sm mb-4">  
                        <div class="card-header bg-white">  
                            <h5 class="mb-0">Notificações</h5>  
                        </div>  
                        <div class="card-body">  
                            <form method="POST" action="configuracoes.php">  
                                <div class="form-check form-switch mb-3">  
                                    <input class="form-check-input" type="checkbox" id="notificacoes_email" name="notificacoes_email" <?php echo ($config['notificacoes_email'] == 1) ? 'checked' : ''; ?>>  
                                    <label class="form-check-label" for="notificacoes_email">Receber notificações por e-mail</label>  
                                </div>  
                                
                                <div class="mt-4">  
                                    <h6 class="mb-3">Tipos de notificação:</h6>  
                                    
                                    <div class="form-check mb-2">  
                                        <input class="form-check-input" type="checkbox" id="notif_novos_documentos" name="notif_novos_documentos" checked>  
                                        <label class="form-check-label" for="notif_novos_documentos">Novos documentos disponíveis</label>  
                                    </div>  
                                    
                                    <div class="form-check mb-2">  
                                        <input class="form-check-input" type="checkbox" id="notif_atualizacoes" name="notif_atualizacoes" checked>  
                                        <label class="form-check-label" for="notif_atualizacoes">Atualizações do sistema</label>  
                                    </div>  
                                    
                                    <div class="form-check mb-2">  
                                        <input class="form-check-input" type="checkbox" id="notif_lembretes" name="notif_lembretes" checked>  
                                        <label class="form-check-label" for="notif_lembretes">Lembretes e vencimentos</label>  
                                    </div>  
                                    
                                    <div class="form-check mb-2">  
                                        <input class="form-check-input" type="checkbox" id="notif_marketing" name="notif_marketing">  
                                        <label class="form-check-label" for="notif_marketing">Informações promocionais e marketing</label>  
                                    </div>  
                                </div>  
                                
                                <hr class="my-4">  
                                
                                <h6 class="mb-3">Frequência de resumos por e-mail:</h6>  
                                <div class="form-check mb-2">  
                                    <input class="form-check-input" type="radio" name="frequencia_resumo" id="freq_diaria" value="diaria">  
                                    <label class="form-check-label" for="freq_diaria">Diária</label>  
                                </div>  
                                
                                <div class="form-check mb-2">  
                                    <input class="form-check-input" type="radio" name="frequencia_resumo" id="freq_semanal" value="semanal" checked>  
                                    <label class="form-check-label" for="freq_semanal">Semanal</label>  
                                </div>  
                                
                                <div class="form-check mb-2">  
                                    <input class="form-check-input" type="radio" name="frequencia_resumo" id="freq_mensal" value="mensal">  
                                    <label class="form-check-label" for="freq_mensal">Mensal</label>  
                                </div>  
                                
                                <div class="form-check mb-2">  
                                    <input class="form-check-input" type="radio" name="frequencia_resumo" id="freq_nunca" value="nunca">  
                                    <label class="form-check-label" for="freq_nunca">Nunca</label>  
                                </div>  
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">  
                                    <button type="submit" name="salvar_configuracoes" class="btn btn-primary">Salvar Configurações</button>  
                                </div>  
                            </form>  
                        </div>  
                    </div>  
                </div>  
                
                <!-- Configurações de Segurança -->  
                <div class="tab-pane fade" id="config-seguranca">  
                    <div class="card border-0 shadow-sm mb-4">  
                        <div class="card-header bg-white">  
                            <h5 class="mb-0">Segurança</h5>  
                        </div>  
                        <div class="card-body">  
                            <form method="POST" action="configuracoes.php">  
                                <div class="mb-4">  
                                    <h6>Autenticação</h6>  
                                    <div class="form-check form-switch mb-3">  
                                        <input class="form-check-input" type="checkbox" id="autenticacao_dois_fatores" name="autenticacao_dois_fatores" <?php echo ($config['autenticacao_dois_fatores'] == 1) ? 'checked' : ''; ?>>  
                                        <label class="form-check-label" for="autenticacao_dois_fatores">Ativar autenticação de dois fatores</label>  
                                    </div>  
                                    
                                    <?php if ($config['autenticacao_dois_fatores'] == 0): ?>  
                                    <div class="alert alert-info small">  
                                        <i data-feather="info" class="feather-sm me-2"></i>  
                                        A autenticação de dois fatores adiciona uma camada extra de segurança à sua conta.   
                                        Após ativar, você precisará fornecer um código adicional ao fazer login.  
                                    </div>  
                                    <?php else: ?>  
                                    <div class="alert alert-success small">  
                                        <i data-feather="check-circle" class="feather-sm me-2"></i>  
                                        A autenticação de dois fatores está ativada. Sua conta está protegida com uma camada adicional de segurança.  
                                    </div>  
                                    <?php endif; ?>  
                                </div>  
                                
                                <hr class="my-4">  
                                
                                <div class="mb-4">  
                                    <h6>Sessões Ativas</h6>  
                                    <p class="text-muted small">Dispositivos onde sua conta está conectada atualmente.</p>  
                                    
                                    <div class="list-group mt-3">  
                                        <div class="list-group-item d-flex justify-content-between align-items-center">  
                                            <div>  
                                                <h6 class="mb-1">Este dispositivo</h6>  
                                                <p class="text-muted mb-0 small">  
                                                    <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT']); ?><br>  
                                                    <span class="text-success">Ativo agora</span>  
                                                </p>  
                                            </div>  
                                            <span class="badge bg-primary rounded-pill">Atual</span>  
                                        </div>  
                                    </div>  
                                    
                                    <div class="mt-3">  
                                        <button type="button" class="btn btn-outline-danger btn-sm">  
                                            <i data-feather="log-out" class="feather-sm"></i> Encerrar todas as outras sessões  
                                        </button>  
                                    </div>  
                                </div>  
                                
                                <hr class="my-4">  
                                
                                <div class="mb-3">  
                                    <h6>Histórico de Acessos</h6>  
                                    <p class="text-muted small">Últimas atividades de login em sua conta.</p>  
                                    
                                    <div class="table-responsive mt-3">  
                                        <table class="table table-sm table-hover">  
                                            <thead class="table-light">  
                                                <tr>  
                                                    <th>Data e Hora</th>  
                                                    <th>IP</th>  
                                                    <th>Navegador</th>  
                                                    <th>Status</th>  
                                                </tr>  
                                            </thead>  
                                            <tbody>  
                                                <tr>  
                                                    <td><?php echo date('d/m/Y H:i'); ?></td>  
                                                    <td><?php echo $_SERVER['REMOTE_ADDR']; ?></td>  
                                                    <td>  
                                                        <?php   
                                                        $user_agent = $_SERVER['HTTP_USER_AGENT'];  
                                                        if (strpos($user_agent, 'Chrome') !== false) {  
                                                            echo 'Chrome';  
                                                        } elseif (strpos($user_agent, 'Firefox') !== false) {  
                                                            echo 'Firefox';  
                                                        } elseif (strpos($user_agent, 'Safari') !== false) {  
                                                            echo 'Safari';  
                                                        } elseif (strpos($user_agent, 'Edge') !== false) {  
                                                            echo 'Edge';  
                                                        } else {  
                                                            echo 'Desconhecido';  
                                                        }  
                                                        ?>  
                                                    </td>  
                                                    <td><span class="badge bg-success">Sucesso</span></td>  
                                                </tr>  
                                            </tbody>  
                                        </table>  
                                    </div>  
                                </div>  
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">  
                                    <button type="submit" name="salvar_configuracoes" class="btn btn-primary">Salvar Configurações</button>  
                                </div>  
                            </form>  
                        </div>  
                    </div>  
                </div>  
                
                <!-- Configurações de Privacidade -->  
                <div class="tab-pane fade" id="config-privacidade">  
                    <div class="card border-0 shadow-sm mb-4">  
                        <div class="card-header bg-white">  
                            <h5 class="mb-0">Privacidade e Dados</h5>  
                        </div>  
                        <div class="card-body">  
                            <div class="mb-4">  
                                <h6>Política de Privacidade</h6>  
                                <p class="text-muted small">  
                                    Nossa política de privacidade descreve como coletamos, usamos e compartilhamos seus dados pessoais.  
                                </p>  
                                <a href="privacidade.php" class="btn btn-sm btn-outline-secondary">  
                                    <i data-feather="file-text" class="feather-sm me-2"></i> Ver Política de Privacidade  
                                </a>  
                            </div>  
                            
                            <hr class="my-4">  
                            
                            <div class="mb-4">  
                                <h6>Cookies e Rastreamento</h6>  
                                <div class="form-check form-switch mb-3">  
                                    <input class="form-check-input" type="checkbox" id="aceitar_cookies" name="aceitar_cookies" checked>  
                                    <label class="form-check-label" for="aceitar_cookies">Aceitar cookies essenciais</label>  
                                </div>  
                                <div class="form-check form-switch mb-3">  
                                    <input class="form-check-input" type="checkbox" id="cookies_analiticos" name="cookies_analiticos" checked>  
                                    <label class="form-check-label" for="cookies_analiticos">Permitir cookies analíticos</label>  
                                </div>  
                                <div class="form-check form-switch mb-3">  
                                    <input class="form-check-input" type="checkbox" id="cookies_marketing" name="cookies_marketing">  
                                    <label class="form-check-label" for="cookies_marketing">Permitir cookies de marketing</label>  
                                </div>  
                            </div>  
                            
                            <hr class="my-4">  
                            
                            <div class="mb-4">  
                                <h6>Seus Dados</h6>  
                                <p class="text-muted small">  
                                    Você tem direito a solicitar uma cópia dos seus dados pessoais que mantemos, bem como solicitar sua exclusão.  
                                </p>  
                                
                                <div class="d-grid gap-2 d-md-block">  
                                    <a href="baixar_dados.php" class="btn btn-sm btn-outline-primary me-2">  
                                        <i data-feather="download" class="feather-sm me-2"></i> Baixar Meus Dados  
                                    </a>  
                                    
                                    <?php if ($tem_solicitacao_pendente): ?>  
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>  
                                        <i data-feather="clock" class="feather-sm me-2"></i> Solicitação de Exclusão Pendente  
                                    </button>  
                                    <?php else: ?>  
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalExcluirDados">  
                                        <i data-feather="trash-2" class="feather-sm me-2"></i> Solicitar Exclusão de Dados  
                                    </button>  
                                    <?php endif; ?>  
                                </div>  
                                
                                <?php if (!empty($mensagem_exclusao)): ?>  
                                <div class="alert alert-<?php echo $tipo_mensagem_exclusao; ?> alert-dismissible fade show mt-3" role="alert">  
                                    <?php echo $mensagem_exclusao; ?>  
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>  
                                </div>  
                                <?php endif; ?>  
                            </div>  
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">  
                                <button type="submit" name="salvar_configuracoes" class="btn btn-primary">Salvar Configurações</button>  
                            </div>  
                        </div>  
                    </div>  
                </div>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- Modal para Exclusão de Dados -->  
<div class="modal fade" id="modalExcluirDados" tabindex="-1" aria-labelledby="modalExcluirDadosLabel" aria-hidden="true">  
    <div class="modal-dialog">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="modalExcluirDadosLabel">Solicitar Exclusão de Dados</h5>  
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>  
            </div>  
            <div class="modal-body">  
                <div class="alert alert-warning">  
                    <i data-feather="alert-triangle" class="feather-sm me-2"></i>  
                    <strong>Atenção!</strong> A exclusão de dados é irreversível.  
                </div>  
                
                <p>Ao solicitar a exclusão de seus dados:</p>  
                <ul>  
                    <li>Sua conta será desativada imediatamente</li>  
                    <li>Seus dados pessoais serão anonimizados ou removidos em até 30 dias</li>  
                    <li>Documentos e registros relacionados à sua conta serão excluídos</li>  
                    <li>Não será possível recuperar esses dados posteriormente</li>  
                </ul>  
                
                <p>O processamento da solicitação pode levar até 30 dias para ser concluído.</p>  
                
                <form method="POST" action="configuracoes.php" id="form-exclusao">  
                    <div class="mb-3">  
                        <label for="motivo_exclusao" class="form-label">Motivo da exclusão (opcional):</label>  
                        <select class="form-select" id="motivo_exclusao" name="motivo_exclusao">  
                            <option value="">Selecione um motivo...</option>  
                            <option value="nao_utilizo">Não utilizo mais o serviço</option>  
                            <option value="privacidade">Preocupações com privacidade</option>  
                            <option value="insatisfeito">Insatisfeito com o serviço</option>  
                            <option value="outro">Outro motivo</option>  
                        </select>  
                    </div>  
                    
                    <div class="mb-3">  
                        <label for="confirmacao_exclusao" class="form-label">Para confirmar, digite "EXCLUIR MEUS DADOS":</label>  
                        <input type="text" class="form-control" id="confirmacao_exclusao" name="confirmacao_exclusao" required>  
                    </div>  
                </form>  
            </div>  
            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>  
                <button type="submit" form="form-exclusao" name="solicitar_exclusao" class="btn btn-danger"   
                        onclick="return validarExclusao()">Solicitar Exclusão</button>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- Modal para cadastrar novo selo -->  
<div class="modal fade" id="novoSeloModal" tabindex="-1" aria-labelledby="novoSeloModalLabel" aria-hidden="true">  
    <div class="modal-dialog">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="novoSeloModalLabel">Cadastrar Novo Selo</h5>  
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>  
            </div>  
            <form method="POST" action="selos.php">  
                <div class="modal-body">  
                    <div class="mb-3">  
                        <label for="numero_selo" class="form-label">Número do Selo Eletrônico</label>  
                        <input type="text" class="form-control" id="numero_selo" name="numero_selo" required>  
                        <div class="form-text">Digite o número do selo eletrônico conforme consta no documento oficial.</div>  
                    </div>  
                </div>  
                <div class="modal-footer">  
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>  
                    <button type="submit" name="cadastrar_selo" class="btn btn-primary">Cadastrar</button>  
                </div>  
            </form>  
        </div>  
    </div>  
</div>  

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/feather-icons"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>  
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>  
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>  
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>  
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>  
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>  

<script>  
document.addEventListener('DOMContentLoaded', function() {  
    // Inicializar os ícones feather  
    if (typeof feather !== 'undefined') {  
        feather.replace();  
    }  
    
    // Adicionar funcionalidade de alternar entre as abas  
    const tabLinks = document.querySelectorAll('.list-group-item-action');  
    tabLinks.forEach(function(link) {  
        link.addEventListener('click', function() {  
            tabLinks.forEach(item => item.classList.remove('active'));  
            this.classList.add('active');  
        });  
    });  
    
    // Verificar a força da senha se o campo existir  
    const novaSenhaInput = document.getElementById('nova_senha');  
    if (novaSenhaInput) {  
        novaSenhaInput.addEventListener('input', verificarForcaSenha);  
    }  
    
    // Verificar se tema escuro está ativado e aplicar  
    const temaEscuroSwitch = document.getElementById('tema_escuro');  
    if (temaEscuroSwitch) {  
        temaEscuroSwitch.addEventListener('change', function() {  
            if (this.checked) {  
                document.body.classList.add('dark-mode');  
            } else {  
                document.body.classList.remove('dark-mode');  
            }  
        });  
        
        // Aplicar tema escuro se estiver ativado  
        if (temaEscuroSwitch.checked) {  
            document.body.classList.add('dark-mode');  
        }  
    }  
    
    // Habilitar tooltips do Bootstrap  
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));  
    if (typeof bootstrap !== 'undefined') {  
        tooltipTriggerList.map(function (tooltipTriggerEl) {  
            return new bootstrap.Tooltip(tooltipTriggerEl);  
        });  
    }  
});  

// Função para validar o formulário de exclusão  
function validarExclusao() {  
    const confirmacao = document.getElementById('confirmacao_exclusao').value;  
    if (confirmacao !== 'EXCLUIR MEUS DADOS') {  
        alert('Por favor, digite exatamente "EXCLUIR MEUS DADOS" para confirmar.');  
        return false;  
    }  
    
    return confirm('Tem certeza que deseja solicitar a exclusão dos seus dados? Esta ação não pode ser desfeita.');  
}  

// Função para verificar a força da senha  
function verificarForcaSenha() {  
    const senha = document.getElementById('nova_senha').value;  
    const forcaSenhaBar = document.getElementById('forca-senha');  
    
    if (!forcaSenhaBar) return;  
    
    let forca = 0;  
    
    // Critérios de força  
    if (senha.length >= 8) forca += 1;  
    if (senha.match(/[a-z]+/)) forca += 1;  
    if (senha.match(/[A-Z]+/)) forca += 1;  
    if (senha.match(/[0-9]+/)) forca += 1;  
    if (senha.match(/[^a-zA-Z0-9]+/)) forca += 1;  
    
    // Atualizar barra de progresso  
    switch (forca) {  
        case 0:  
        case 1:  
            forcaSenhaBar.style.width = '20%';  
            forcaSenhaBar.className = 'progress-bar bg-danger';  
            forcaSenhaBar.textContent = 'Muito fraca';  
            break;  
        case 2:  
            forcaSenhaBar.style.width = '40%';  
            forcaSenhaBar.className = 'progress-bar bg-warning';  
            forcaSenhaBar.textContent = 'Fraca';  
            break;  
        case 3:  
            forcaSenhaBar.style.width = '60%';  
            forcaSenhaBar.className = 'progress-bar bg-info';  
            forcaSenhaBar.textContent = 'Média';  
            break;  
        case 4:  
            forcaSenhaBar.style.width = '80%';  
            forcaSenhaBar.className = 'progress-bar bg-primary';  
            forcaSenhaBar.textContent = 'Forte';  
            break;  
        case 5:  
            forcaSenhaBar.style.width = '100%';  
            forcaSenhaBar.className = 'progress-bar bg-success';  
            forcaSenhaBar.textContent = 'Muito forte';  
            break;  
    }  
}  

// Verificar alterações não salvas antes de sair da página  
let formAlterado = false;  

// Marcar formulários como alterados quando campos são modificados  
document.querySelectorAll('form input, form select, form textarea').forEach(function(element) {  
    element.addEventListener('change', function() {  
        formAlterado = true;  
    });  
});  

// Aviso ao sair da página com alterações não salvas  
window.addEventListener('beforeunload', function(e) {  
    if (formAlterado) {  
        e.preventDefault();  
        e.returnValue = 'Você tem alterações não salvas. Deseja realmente sair desta página?';  
    }  
});  

// Resetar flag quando os formulários são enviados  
document.querySelectorAll('form').forEach(function(form) {  
    form.addEventListener('submit', function() {  
        formAlterado = false;  
    });  
});  
</script>  

<?php include 'includes/footer.php'; ?>