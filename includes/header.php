<?php
if (!isset($_SESSION)) {
    session_start();
}

// Se for requisição AJAX, não renderizar HTML
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    return;
}

// Verificar se a função is_admin já existe
if (!function_exists('is_admin')) {
    function is_admin() {
        return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] == 'admin';
    }
}

// Processar cadastro de novo selo  
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar_selo'])) {  
    $numero_selo = sanitize($_POST['numero_selo']);  
    
    if (empty($numero_selo)) {  
        $mensagem = "Por favor, informe o número do selo.";  
    } else {  
        try {  
            // Verificar se o selo já existe  
            $stmt = $pdo->prepare("SELECT id, status FROM selos WHERE numero = ?");  
            $stmt->execute([$numero_selo]);  
            $selo_existente = $stmt->fetch();  

            if ($selo_existente) {  
                if ($selo_existente['status'] === 'ativo') {  
                    $mensagem = "Este número de selo já está cadastrado.";  
                } elseif ($selo_existente['status'] === 'excluido') {  
                    $pdo->beginTransaction();  
            
                    // Restaurar o selo  
                    $stmt = $pdo->prepare("UPDATE selos SET status = 'ativo', data_exclusao = NULL WHERE id = ?");  
                    $stmt->execute([$selo_existente['id']]);  
            
                    // Inserir no log  
                    $stmt = $pdo->prepare("INSERT INTO logs_sistema   
                        (usuario_id, usuario_nome, acao, tabela_afetada, registro_id, data_hora, detalhes)   
                        VALUES (?, ?, ?, ?, ?, NOW(), ?)");  
            
                    $detalhes = "Selo nº " . htmlspecialchars($numero_selo) . " restaurado após tentativa de novo cadastro.";  
                    $stmt->execute([  
                        $usuario_id,  
                        $_SESSION['nome'],  
                        'restauracao',  
                        'selos',  
                        $selo_existente['id'],  
                        $detalhes  
                    ]);  
            
                    $pdo->commit();  
            
                    $sucesso = true;  
                    $mensagem = "Selo restaurado com sucesso!";  
                    header("Location: selos.php?id=" . $selo_existente['id'] . "&restaurado=1");  
                    exit;  
                } else {  
                    $mensagem = "Este número de selo já existe com status: " . $selo_existente['status'];  
                }  
            } else {  
                // Inserir novo selo  
                $stmt = $pdo->prepare("INSERT INTO selos (numero, usuario_id) VALUES (?, ?)");  
                $stmt->execute([$numero_selo, $usuario_id]);  
                
                $novo_selo_id = $pdo->lastInsertId();  
                $sucesso = true;  
                $mensagem = "Selo cadastrado com sucesso!";  
                
                // Redirecionar para edição do selo  
                header("Location: selos.php?id=" . $novo_selo_id . "&success=1");  
                exit;  
            }  
        } catch (PDOException $e) {  
            $mensagem = "Erro ao cadastrar selo: " . $e->getMessage();  
        }  
    }  
}  
?> 
<!DOCTYPE html>  
<html lang="pt-BR" data-bs-theme="light">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Xuxuzinho - Sistema de Gestão Cartorial</title>  
    <!-- Bootstrap CSS -->  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">  
    <!-- SweetAlert2 CSS -->  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.20/dist/sweetalert2.min.css">  
    
    <!-- Fontes -->  
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">      
    <!-- Feather Icons -->  
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>  
    
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


    <!-- DataTables CSS com Bootstrap 5 -->  
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">  
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">  
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">  
    <?php include(__DIR__ . '/../css/style.php');?>   
</head>  
<body>  
    <div class="wrapper">  
        <!-- Menu lateral -->  
        <nav id="sidebar">  
            <div class="sidebar-header">  
                <img src="images/logo-white.png" alt="Xuxuzinho" class="img-fluid">  
            </div>  

            <ul class="list-unstyled components">  
                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'painel.php') ? 'active' : ''; ?>">  
                    <a href="painel.php">  
                        <i data-feather="home"></i>  
                        <span>Início</span>  
                    </a>  
                </li>  
                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'selos.php' && !isset($_GET['id'])) ? 'active' : ''; ?>">  
                    <a href="selos.php">  
                        <i data-feather="file-text"></i>  
                        <span>Controle de Selos</span>  
                    </a>  
                </li>  

                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == '#novoSeloModal' && !isset($_GET['id'])) ? 'active' : ''; ?>">  
                    <a type="button" data-bs-toggle="modal" data-bs-target="#novoSeloModal">  
                        <i data-feather="plus"></i>  
                        Novo Selo  
                    </a>      
                </li>  

                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'relatorios.php' && !isset($_GET['id'])) ? 'active' : ''; ?>">  
                    <a href="relatorios.php">  
                        <i data-feather="bar-chart"></i>  
                        <span>Relatórios</span>  
                    </a>  
                </li>  
                
                <?php if (is_admin()): ?>  
                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'aprovacao_usuarios.php') ? 'active' : ''; ?>">  
                    <a href="aprovacao_usuarios.php">  
                        <i data-feather="users"></i>  
                        <span>Aprovar Usuários</span>  
                    </a>  
                </li>  
                <?php endif; ?>  
                
                <!-- <li>  
                    <a href="logout.php">  
                        <i data-feather="log-out"></i>  
                        <span>Sair</span>  
                    </a>  
                </li>   -->
            </ul>  
            
            <div class="sidebar-footer">  
                <button id="theme-toggle" class="btn btn-sm d-flex align-items-center theme-toggle-btn">  
                    <div class="theme-toggle-track">  
                        <div class="theme-toggle-thumb"></div>  
                        <svg class="theme-toggle-icon theme-toggle-icon-light" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>  
                        <svg class="theme-toggle-icon theme-toggle-icon-dark" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>  
                    </div>  
                    <span class="ms-2" id="theme-text">Modo Escuro</span>  
                </button>  
            </div> 
        </nav>  

        <!-- Conteúdo da página -->  
        <div id="content">  
            <nav class="navbar navbar-expand-lg top-navbar">  
                <div class="container-fluid">  
                    <button type="button" id="sidebarCollapse" class="navbar-btn">  
                        <i data-feather="menu"></i>  
                    </button>  
                    
                    <div class="ms-auto user-info">  
                        <?php   
                        // Buscar informações da foto do perfil  
                        $usuario_id = $_SESSION['usuario_id'];  
                        $stmt = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id = ?");  
                        $stmt->execute([$usuario_id]);  
                        $usuario_foto = $stmt->fetch(PDO::FETCH_COLUMN);  
                        
                        if (!empty($usuario_foto) && file_exists($usuario_foto)):   
                        ?>  
                            <img src="<?php echo $usuario_foto; ?>" alt="Avatar" class="avatar" style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%;">  
                        <?php else: ?>  
                            <?php if (!empty($_SESSION['usuario_nome'])): ?>  
                                <div class="avatar-text bg-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; border-radius: 50%;">  
                                    <span class="text-white fw-bold"><?php echo strtoupper(substr($_SESSION['usuario_nome'], 0, 1)); ?></span>  
                                </div>  
                            <?php else: ?>  
                                <img src="images/avatar.png" alt="Avatar" class="avatar">  
                            <?php endif; ?>  
                        <?php endif; ?>  
                        
                        <div class="ms-2">  
                            <p class="user-name mb-0">  
                                <?php   
                                // Função para obter primeiro e último nome  
                                function getPrimeiroUltimoNome($nomeCompleto) {  
                                    $nomes = explode(' ', trim($nomeCompleto));  
                                    
                                    if (count($nomes) <= 1) {  
                                        return $nomeCompleto;  
                                    }  
                                    
                                    $primeiroNome = $nomes[0];  
                                    $ultimoNome = end($nomes);  
                                    
                                    return $primeiroNome . ' ' . $ultimoNome;  
                                }  
                                
                                echo htmlspecialchars(getPrimeiroUltimoNome($_SESSION['usuario_nome']));   
                                ?>  
                            </p> 
                            <p class="user-role mb-0 small text-muted">  
                                <?php echo ($_SESSION['usuario_tipo'] == 'admin') ? 'Administrador' : 'Usuário'; ?>  
                            </p>  
                        </div>  
                        <div class="dropdown ms-2">  
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">  
                                <i data-feather="chevron-down" class="feather-sm"></i>  
                            </button>  
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">  
                                <li><a class="dropdown-item" href="perfil.php"><i data-feather="user" class="feather-sm me-2"></i> Meu Perfil</a></li>  
                                <li><a class="dropdown-item" href="configuracoes.php"><i data-feather="settings" class="feather-sm me-2"></i> Configurações</a></li>  
                                <li><hr class="dropdown-divider"></li>  
                                <li><a class="dropdown-item text-danger" href="logout.php"><i data-feather="log-out" class="feather-sm me-2"></i> Sair</a></li>  
                            </ul>  
                        </div>  
                    </div>  
                </div>  
            </nav>

            <div class="main-container">