<?php  
session_start();  
date_default_timezone_set('America/Sao_Paulo');   
require_once 'includes/db_connection.php';  
require_once 'includes/functions.php';  

// Se já estiver logado, redirecione para o painel  
if (isset($_SESSION['usuario_id'])) {  
    header("Location: painel.php");  
    exit;  
}  

$mensagem = '';  
$sucesso = false;  

// Processar o registro  
if ($_SERVER['REQUEST_METHOD'] === 'POST') {  
    $nome = sanitize($_POST['nome']);  
    $email = sanitize($_POST['email']);  
    $senha = sanitize($_POST['senha']);  
    $confirmar_senha = sanitize($_POST['confirmar_senha']);  
    
    if (empty($nome) || empty($email) || empty($senha) || empty($confirmar_senha)) {  
        $mensagem = "Por favor, preencha todos os campos.";  
    } else if ($senha !== $confirmar_senha) {  
        $mensagem = "As senhas não coincidem.";  
    } else {  
        try {  
            // Verificar se o email já existe  
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");  
            $stmt->execute([$email]);  
            
            if ($stmt->rowCount() > 0) {  
                $mensagem = "Este email já está registrado.";  
            } else {  
                // Criar o novo usuário  
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);  
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");  
                $stmt->execute([$nome, $email, $senha_hash]);  
                
                $sucesso = true;  
                $mensagem = "Sua solicitação de acesso foi enviada com sucesso! Aguarde a aprovação do administrador.";  
            }  
        } catch (PDOException $e) {  
            $mensagem = "Erro ao registrar: " . $e->getMessage();  
        }  
    }  
}  
?>  

<!DOCTYPE html>  
<html lang="pt-BR" data-bs-theme="light">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Solicitar Acesso - Xuxuzinho</title>  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.20/dist/sweetalert2.min.css">  
    <?php include(__DIR__ . '/css/style.php');?>   
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
        
        html[data-bs-theme="dark"] .alert-success {  
            background-color: #051b11;  
            color: #75b798;  
            border-color: #0f5132;  
        }  

        .password-toggle {  
            border-top-right-radius: 0.25rem;  
            border-bottom-right-radius: 0.25rem;  
        }  

        html[data-bs-theme="dark"] .password-toggle {  
            color: #f8f9fa;  
            background-color: #2c2c2c;  
            border-color: #444;  
        }  

        html[data-bs-theme="dark"] .password-toggle:hover {  
            background-color: #3c3c3c;  
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
            <div class="col-md-6">  
                <div class="card shadow">  
                    <div class="card-body p-5">  
                        <div class="text-center mb-4">  
                            <!-- Logo adicionada acima do título -->  
                            <div class="logo-container">  
                                <img src="images/favicon.png" alt="Xuxuzinho Logo" class="img-fluid">  
                            </div>  
                            <h4 class="text-center mb-4">Solicitar Acesso</h4>  
                            <p class="text-muted">Preencha seus dados para solicitar acesso ao sistema</p>  
                        </div>  
                        
                        <?php if (!empty($mensagem) && !$sucesso): ?>  
                            <div class="alert alert-danger"><?php echo $mensagem; ?></div>  
                        <?php elseif (!empty($mensagem) && $sucesso): ?>  
                            <div class="alert alert-success"><?php echo $mensagem; ?></div>  
                        <?php endif; ?>  
                        
                        <?php if (!$sucesso): ?>  
                            <form method="POST" action="register.php">  
                            <div class="mb-3">  
                                <label for="nome" class="form-label">Nome Completo</label>  
                                <input type="text" class="form-control" id="nome" name="nome" required>  
                            </div>  
                            <div class="mb-3">  
                                <label for="email" class="form-label">Email</label>  
                                <input type="email" class="form-control" id="email" name="email" required>  
                            </div>  
                            <div class="row">  
                                <div class="col-md-6 mb-3">  
                                    <label for="senha" class="form-label">Senha</label>  
                                    <div class="input-group">  
                                        <input type="password" class="form-control" id="senha" name="senha" required minlength="6">  
                                        <button class="btn btn-outline-secondary password-toggle" type="button" data-target="senha">  
                                            <i data-feather="eye" class="show-password"></i>  
                                            <i data-feather="eye-off" class="hide-password" style="display: none;"></i>  
                                        </button>  
                                    </div>  
                                </div>  
                                <div class="col-md-6 mb-3">  
                                    <label for="confirmar_senha" class="form-label">Confirmar Senha</label>  
                                    <div class="input-group">  
                                        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required minlength="6">  
                                        <button class="btn btn-outline-secondary password-toggle" type="button" data-target="confirmar_senha">  
                                            <i data-feather="eye" class="show-password"></i>  
                                            <i data-feather="eye-off" class="hide-password" style="display: none;"></i>  
                                        </button>  
                                    </div>  
                                </div>  
                            </div> 
                            <div class="d-grid gap-2">  
                                <button type="submit" class="btn btn-primary">Solicitar Acesso</button>  
                            </div>  
                        </form>  
                        <?php endif; ?>  
                        
                        <div class="mt-4 text-center">  
                            <a href="login.php" class="text-decoration-none">Já tem uma conta? Faça login</a>  
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
        
        <?php if ($sucesso): ?>  
        // Redirecionar para login após 3 segundos  
        setTimeout(function() {  
            window.location.href = 'login.php';  
        }, 3000);  
        <?php endif; ?> 
        
        // Função para mostrar/esconder senha  
        document.querySelectorAll('.password-toggle').forEach(button => {  
            button.addEventListener('click', function() {  
                const targetId = this.getAttribute('data-target');  
                const passwordInput = document.getElementById(targetId);  
                const showIcon = this.querySelector('.show-password');  
                const hideIcon = this.querySelector('.hide-password');  
                
                if (passwordInput.type === 'password') {  
                    passwordInput.type = 'text';  
                    showIcon.style.display = 'none';  
                    hideIcon.style.display = 'block';  
                } else {  
                    passwordInput.type = 'password';  
                    showIcon.style.display = 'block';  
                    hideIcon.style.display = 'none';  
                }  
            });  
        });
    </script>  
</body>  
</html>