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
<html lang="pt-BR">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Solicitar Acesso - Xuxuzinho</title>  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.20/dist/sweetalert2.min.css">  
    <link rel="stylesheet" href="css/style.css">  
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>  
</head>  
<body class="bg-light">  
    <div class="container">  
        <div class="row justify-content-center mt-5">  
            <div class="col-md-6">  
                <div class="card shadow">  
                    <div class="card-body p-5">  
                        <div class="text-center mb-4">  
                            <h2 class="fw-bold text-primary">Solicitar Acesso</h2>  
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
                            <div class="mb-3">  
                                <label for="senha" class="form-label">Senha</label>  
                                <input type="password" class="form-control" id="senha" name="senha" required minlength="6">  
                            </div>  
                            <div class="mb-3">  
                                <label for="confirmar_senha" class="form-label">Confirmar Senha</label>  
                                <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required minlength="6">  
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
        
        <?php if ($sucesso): ?>  
        // Redirecionar para login após 3 segundos  
        setTimeout(function() {  
            window.location.href = 'login.php';  
        }, 3000);  
        <?php endif; ?>  
    </script>  
</body>  
</html>