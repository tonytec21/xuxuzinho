<?php  
session_start();  
date_default_timezone_set('America/Sao_Paulo');   
require_once 'includes/db_connection.php';  
require_once 'includes/functions.php';  
require_once 'includes/log_functions.php';  

// Se já estiver logado, redirecione para o painel  
if (isset($_SESSION['usuario_id'])) {  
    header("Location: painel.php");  
    exit;  
}  

$mensagem = '';  

// Processar o login  
if ($_SERVER['REQUEST_METHOD'] === 'POST') {  
    $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';  
    $senha = isset($_POST['senha']) ? $_POST['senha'] : ''; // Não sanitizar a senha antes da verificação  
    
    if (empty($email) || empty($senha)) {  
        $mensagem = "Por favor, preencha todos os campos.";  
    } else {  
        try {  
            $stmt = $pdo->prepare("SELECT id, nome, senha, status, tipo FROM usuarios WHERE email = ?");  
            $stmt->execute([$email]);  
            $usuario = $stmt->fetch();  
            
            if ($usuario && password_verify($senha, $usuario['senha'])) {  
                if ($usuario['status'] === 'pendente') {  
                    $mensagem = "Sua conta ainda está pendente de aprovação pelo administrador.";  
                } else if ($usuario['status'] === 'rejeitado') {  
                    $mensagem = "Sua solicitação de acesso foi rejeitada. Entre em contato com o administrador.";  
                } else {  
                    // Login bem-sucedido  
                    $_SESSION['usuario_id'] = $usuario['id'];  
                    $_SESSION['usuario_nome'] = $usuario['nome'];  
                    $_SESSION['nome'] = $usuario['nome']; // Adicionado para compatibilidade com novos scripts  
                    $_SESSION['usuario_tipo'] = $usuario['tipo'];  
                    
                    // Log de login - com parâmetros corretos  
                    registrar_log(  
                        'login',   
                        'usuarios',   
                        $usuario['id'],   
                        'Usuário realizou login com sucesso',   
                        $usuario['id'],   
                        $usuario['nome']  
                    );  
                    
                    header("Location: painel.php");  
                    exit;  
                }  
            } else {  
                $mensagem = "Email ou senha incorretos.";  
            }  
        } catch (PDOException $e) {  
            $mensagem = "Erro ao processar login: " . $e->getMessage();  
        }  
    }  
}   
?>  

<!DOCTYPE html>  
<html lang="pt-BR" data-bs-theme="light">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Login - Xuxuzinho</title>  
    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.20/dist/sweetalert2.min.css">  
    <link rel="stylesheet" href="css/style.css">  
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>  
    <style>  
        .theme-toggle {  
            position: absolute;  
            top: 15px;  
            right: 15px;  
            z-index: 1000;  
        }  
        
        .logo-container {  
            width: 100px;  
            height: 100px;  
            margin: 0 auto 20px;  
            display: flex;  
            justify-content: center;  
            align-items: center;  
        }  
        
        .logo-container img {  
            max-width: 100%;  
            max-height: 100%;  
        }  
        
        /* Estilos para o modo escuro */  
        html[data-bs-theme="dark"] {  
            --bs-body-bg: #121212;  
            --bs-body-color: #f8f9fa;  
        }  
        
        html[data-bs-theme="dark"] body {  
            background-color: #121212;  
            color: #f8f9fa;  
        }  
        
        html[data-bs-theme="dark"] .card {  
            background-color: #1e1e1e;  
            color: #f8f9fa;  
            border-color: #2c2c2c;  
        }  
        
        html[data-bs-theme="dark"] .form-control {  
            background-color: #2c2c2c;  
            color: #f8f9fa;  
            border-color: #444;  
        }  
        
        html[data-bs-theme="dark"] .form-control:focus {  
            background-color: #2c2c2c;  
            color: #f8f9fa;  
        }  
        
        html[data-bs-theme="dark"] .text-muted {  
            color: #adb5bd !important;  
        }  
        
        html[data-bs-theme="dark"] .btn-outline-secondary {  
            color: #adb5bd;  
            border-color: #6c757d;  
        }  
        
        html[data-bs-theme="dark"] .theme-toggle {  
            color: #f8f9fa;  
            background-color: transparent;  
            border-color: #6c757d;  
        }  
        
        html[data-bs-theme="dark"] .alert-danger {  
            background-color: #2c1215;  
            color: #ea868f;  
            border-color: #842029;  
        }  
    </style>  
</head>  
<body>  
    <!-- Botão toggle de tema -->  
    <button class="btn btn-sm theme-toggle" id="themeToggle" title="Alternar tema">  
        <i data-feather="moon" id="darkIcon"></i>  
        <i data-feather="sun" id="lightIcon" style="display: none;"></i>  
    </button>  
    
    <div class="container">  
        <div class="row justify-content-center mt-5">  
            <div class="col-md-6 col-lg-4">  
                <div class="card shadow">  
                    <div class="card-body p-5">  
                        <div class="text-center mb-4">  
                            <div class="logo-container">  
                                <img src="images/favicon.png" alt="Xuxuzinho Logo" class="img-fluid">  
                            </div>  
                            <h4 class="text-center mb-4">Acesso ao Sistema</h4>   
                        </div>  
                        
                        <?php if (!empty($mensagem)): ?>  
                            <div class="alert alert-danger"><?php echo $mensagem; ?></div>  
                        <?php endif; ?>  
                        
                        <form method="POST" action="login.php">  
                            <div class="mb-3">  
                                <label for="email" class="form-label">Email</label>  
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>  
                            </div>  
                            <div class="mb-3">  
                                <label for="senha" class="form-label">Senha</label>  
                                <input type="password" class="form-control" id="senha" name="senha" required>  
                            </div>  
                            <div class="d-grid gap-2">  
                                <button type="submit" class="btn btn-primary">Entrar</button>  
                            </div>  
                        </form>  
                        
                        <div class="mt-4 text-center">  
                            <a href="recuperar_senha.php" class="text-decoration-none">Esqueceu sua senha?</a>  
                            <hr>  
                            <a href="register.php" class="btn btn-outline-secondary">Solicitar Acesso</a>  
                        </div>  
                    </div>  
                </div>  
            </div>  
        </div>  
    </div>  
    
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>  
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>  
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.20/dist/sweetalert2.min.js"></script>  
    <script>  
        feather.replace();  
        
        // Função para alternar entre temas claro e escuro  
        document.getElementById('themeToggle').addEventListener('click', function() {  
            const html = document.documentElement;  
            const darkIcon = document.getElementById('darkIcon');  
            const lightIcon = document.getElementById('lightIcon');  
            
            if (html.getAttribute('data-bs-theme') === 'dark') {  
                html.setAttribute('data-bs-theme', 'light');  
                darkIcon.style.display = 'block';  
                lightIcon.style.display = 'none';  
                localStorage.setItem('theme', 'light');  
            } else {  
                html.setAttribute('data-bs-theme', 'dark');  
                darkIcon.style.display = 'none';  
                lightIcon.style.display = 'block';  
                localStorage.setItem('theme', 'dark');  
            }  
        });  
        
        // Verificar tema salvo no localStorage ao carregar a página  
        document.addEventListener('DOMContentLoaded', function() {  
            const savedTheme = localStorage.getItem('theme');  
            const html = document.documentElement;  
            const darkIcon = document.getElementById('darkIcon');  
            const lightIcon = document.getElementById('lightIcon');  
            
            if (savedTheme === 'dark') {  
                html.setAttribute('data-bs-theme', 'dark');  
                darkIcon.style.display = 'none';  
                lightIcon.style.display = 'block';  
            }  
            
            // Aplicar o tema imediatamente  
            feather.replace();  
        });  
        
        // Verificar parâmetros na URL  
        const params = new URLSearchParams(window.location.search);  
        if (params.has('erro')) {  
            if (params.get('erro') === 'conta_pendente') {  
                Swal.fire({  
                    title: 'Acesso Pendente',  
                    text: 'Sua conta ainda está pendente de aprovação pelo administrador.',  
                    icon: 'warning'  
                });  
            } else if (params.get('erro') === 'usuario_nao_encontrado') {  
                Swal.fire({  
                    title: 'Erro',  
                    text: 'Usuário não encontrado. Por favor, faça login novamente.',  
                    icon: 'error'  
                });  
            }  
        }  
        
        // Verificar se houve logout bem-sucedido  
        if (params.has('logout')) {  
            Swal.fire({  
                title: 'Logout realizado',  
                text: 'Você saiu do sistema com sucesso.',  
                icon: 'success',  
                timer: 3000,  
                showConfirmButton: false  
            });  
        }  
    </script>  
</body>  
</html>