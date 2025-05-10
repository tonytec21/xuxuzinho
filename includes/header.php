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
    <title>Xuxuzinho</title>  
    <link rel="icon" type="image/png" href="images/favicon.png">
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
        <!-- Menu Lateral XZ - Novo e Moderno -->  
        <div class="xz-sidebar-container">  
            <!-- Overlay para fechamento do menu em dispositivos móveis -->  
            <div class="xz-sidebar-overlay"></div>  
            
            <!-- Menu lateral principal -->  
            <nav class="xz-sidebar">  
                <div class="xz-sidebar-header">  
                    <img src="images/logo-white.png" alt="Xuxuzinho" class="xz-logo">  
                    <button type="button" class="xz-sidebar-close d-md-none">  
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>  
                    </button>  
                </div>  
                
                <div class="xz-sidebar-content">  
                    <ul class="xz-sidebar-menu">  
                        <li class="xz-sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) == 'painel.php') ? 'xz-active' : ''; ?>">  
                            <a href="painel.php" class="xz-sidebar-link">  
                                <i data-feather="home"></i>  
                                <span>&nbsp;Início</span>  
                            </a>  
                        </li>  
                        
                        <li class="xz-sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) == 'selos.php' && !isset($_GET['id'])) ? 'xz-active' : ''; ?>">  
                            <a href="selos.php" class="xz-sidebar-link">  
                                <i data-feather="file-text"> </i>  
                                <span>&nbsp;Controle de Selos</span>  
                            </a>  
                        </li>   
                        
                        <li class="xz-sidebar-item">  
                            <a href="#" class="xz-sidebar-link" data-bs-toggle="modal" data-bs-target="#novoSeloModal">  
                                <i data-feather="plus"></i>  
                                <span>&nbsp;Novo Selo</span>  
                            </a>  
                        </li>  
                        
                        <li class="xz-sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) == 'relatorios.php' && !isset($_GET['id'])) ? 'xz-active' : ''; ?>">  
                            <a href="relatorios.php" class="xz-sidebar-link">  
                                <i data-feather="bar-chart"></i>  
                                <span>&nbsp;Relatórios</span>  
                            </a>  
                        </li>  

                        <li class="xz-sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) == 'livros.php' && !isset($_GET['id'])) ? 'xz-active' : ''; ?>">  
                            <a href="livros.php" class="xz-sidebar-link">  
                                <i data-feather="book"> </i>  
                                <span>&nbsp;Livros</span>  
                            </a>  
                        </li> 

                        <li class="xz-sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) == 'triagem.php' && !isset($_GET['id'])) ? 'xz-active' : ''; ?>">  
                            <a href="triagem.php" class="xz-sidebar-link">  
                                <i data-feather="clipboard"></i>  
                                <span>&nbsp;Registre-se</span>  
                            </a>  
                        </li>
                        
                        <?php if (is_admin()): ?>  
                        <li class="xz-sidebar-item <?php echo (basename($_SERVER['PHP_SELF']) == 'aprovacao_usuarios.php') ? 'xz-active' : ''; ?>">  
                            <a href="aprovacao_usuarios.php" class="xz-sidebar-link">  
                                <i data-feather="users"></i>  
                                <span>&nbsp;Aprovar Usuários</span>  
                            </a>  
                        </li>  
                        <?php endif; ?>  
                    </ul>  
                </div>  
                
                <div class="xz-sidebar-footer">  
                    <button id="xz-theme-toggle" class="xz-theme-toggle">  
                        <div class="xz-theme-icon">  
                            <i data-feather="sun" class="xz-theme-light"></i>  
                            <i data-feather="moon" class="xz-theme-dark"></i>  
                        </div>  
                        <span id="xz-theme-text">Modo Escuro</span>  
                    </button>  
                </div>  
            </nav>  
        </div>  

        <!-- Botão para abrir o menu em dispositivos móveis -->  
        <button class="xz-sidebar-toggler d-md-none">  
            <i data-feather="menu"></i>  
        </button> 

        <!-- Conteúdo da página -->  
        <div id="content">  
            <!-- XZ Navbar Superior -->  
            <nav class="xz-topbar">  
                <div class="xz-topbar-container">  
                    <!-- Botão para controlar o menu lateral em telas maiores -->  
                    <button type="button" class="xz-topbar-toggle d-none d-md-flex">  
                        <i data-feather="menu"></i>  
                    </button>  
                    
                    <!-- Título da página (opcional) -->  
                    <h2 class="xz-page-title d-none d-md-block">  
                        <?php   
                        // Detectar a página atual e mostrar o título apropriado  
                        $current_page = basename($_SERVER['PHP_SELF']);  
                        switch ($current_page) {  
                            case 'painel.php':  
                                echo 'Painel de Controle';  
                                break;  
                            case 'selos.php':  
                                echo isset($_GET['id']) ? 'Detalhes do Selo' : 'Controle de Selos';  
                                break;  
                            case 'relatorios.php':  
                                echo 'Relatórios';  
                                break;  
                            case 'aprovacao_usuarios.php':  
                                echo 'Aprovação de Usuários';  
                                break;  
                            case 'perfil.php':  
                                echo 'Meu Perfil';  
                                break;  
                            case 'livros.php':  
                                echo 'Livros';  
                                break;  
                            case 'configuracoes.php':  
                                echo 'Configurações';  
                                break;  
                            default:  
                                echo 'Xuxuzinho';  
                        }  
                        ?>  
                    </h2>  
                    
                    <!-- Informações do usuário -->  
                    <div class="xz-user-area">  
                        <?php   
                        // Buscar informações da foto do perfil  
                        $usuario_id = $_SESSION['usuario_id'];  
                        $stmt = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id = ?");  
                        $stmt->execute([$usuario_id]);  
                        $usuario_foto = $stmt->fetch(PDO::FETCH_COLUMN);  
                        ?>  
                        
                        <!-- Foto/Avatar do usuário -->  
                        <div class="xz-user-avatar">  
                            <?php if (!empty($usuario_foto) && file_exists($usuario_foto)): ?>  
                                <img src="<?php echo $usuario_foto; ?>" alt="Avatar">  
                            <?php elseif (!empty($_SESSION['usuario_nome'])): ?>  
                                <div class="xz-avatar-text">  
                                    <span><?php echo strtoupper(substr($_SESSION['usuario_nome'], 0, 1)); ?></span>  
                                </div>  
                            <?php else: ?>  
                                <img src="images/avatar.png" alt="Avatar">  
                            <?php endif; ?>  
                        </div>  
                        
                        <!-- Nome e cargo do usuário -->  
                        <div class="xz-user-info">  
                            <p class="xz-user-name">  
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
                            <p class="xz-user-role">  
                                <?php echo ($_SESSION['usuario_tipo'] == 'admin') ? 'Administrador' : 'Usuário'; ?>  
                            </p>  
                        </div>  
                        
                        <!-- Dropdown do usuário -->  
                        <div class="xz-user-dropdown">  
                            <button class="xz-dropdown-toggle" type="button" id="xzUserDropdown" data-bs-toggle="dropdown" aria-expanded="false">  
                                <i data-feather="chevron-down"></i>  
                            </button>  
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="xzUserDropdown">  
                                <li>  
                                    <a class="dropdown-item" href="perfil.php">  
                                        <i data-feather="user" class="feather-sm me-2"></i> Meu Perfil  
                                    </a>  
                                </li>  
                                <li>  
                                    <a class="dropdown-item" href="configuracoes.php">  
                                        <i data-feather="settings" class="feather-sm me-2"></i> Configurações  
                                    </a>  
                                </li>  
                                <li><hr class="dropdown-divider"></li>  
                                <li>  
                                    <a class="dropdown-item text-danger" href="logout.php">  
                                        <i data-feather="log-out" class="feather-sm me-2"></i> Sair  
                                    </a>  
                                </li>  
                            </ul>  
                        </div>  
                    </div>  
                </div>  
            </nav>

            <div class="main-container">