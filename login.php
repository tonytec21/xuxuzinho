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
<html lang="pt-BR">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Login - Xuxuzinho</title>  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.20/dist/sweetalert2.min.css">  
    <link rel="stylesheet" href="css/style.css">  
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>  
</head>  
<body class="bg-light">  
    <div class="container">  
        <div class="row justify-content-center mt-5">  
            <div class="col-md-6 col-lg-4">  
                <div class="card shadow">  
                    <div class="card-body p-5">  
                        <div class="text-center mb-4">  
                            <h2 class="fw-bold text-primary">Xuxuzinho</h2>  
                            <p class="text-muted">Sistema de Gestão Cartorial</p>  
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