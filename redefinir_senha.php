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
$tipo_mensagem = 'danger';  
$token_valido = false;  
$token = isset($_GET['token']) ? $_GET['token'] : '';  

// Verificar se o token é válido  
if (!empty($token)) {  
    try {  
        $stmt = $pdo->prepare("  
            SELECT id, nome, email   
            FROM usuarios   
            WHERE token_recuperacao = ?   
            AND token_expira > NOW()   
            AND status = 'aprovado'  
        ");  
        $stmt->execute([$token]);  
        $usuario = $stmt->fetch();  
        
        if ($usuario) {  
            $token_valido = true;  
        } else {  
            $mensagem = "O link de recuperação é inválido ou expirou. Por favor, solicite um novo link.";  
        }  
    } catch (PDOException $e) {  
        $mensagem = "Erro ao verificar token: " . $e->getMessage();  
        error_log("Erro ao verificar token: " . $e->getMessage());  
    }  
} else {  
    $mensagem = "Token de recuperação não fornecido.";  
}  

// Processar a redefinição de senha  
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {  
    $senha = isset($_POST['senha']) ? $_POST['senha'] : '';  
    $confirmar_senha = isset($_POST['confirmar_senha']) ? $_POST['confirmar_senha'] : '';  
    
    if (empty($senha) || empty($confirmar_senha)) {  
        $mensagem = "Por favor, preencha todos os campos.";  
    } elseif (strlen($senha) < 8) {  
        $mensagem = "A senha deve ter pelo menos 8 caracteres.";  
    } elseif ($senha !== $confirmar_senha) {  
        $mensagem = "As senhas não coincidem.";  
    } else {  
        try {  
            // Hash da nova senha  
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);  
            
            // Atualizar a senha e limpar o token  
            $stmt = $pdo->prepare("  
                UPDATE usuarios   
                SET senha = ?, token_recuperacao = NULL, token_expira = NULL   
                WHERE token_recuperacao = ?  
            ");  
            $stmt->execute([$senha_hash, $token]);  
            
            if ($stmt->rowCount() > 0) {  
                $mensagem = "Senha redefinida com sucesso! Você já pode fazer login com sua nova senha.";  
                $tipo_mensagem = 'success';  
                $token_valido = false; // Impede que o formulário seja exibido novamente  
                
                // Registrar no log, se a função existir  
                if (function_exists('registrar_log')) {  
                    registrar_log('redefinir_senha', 'Senha redefinida com sucesso', $usuario['id']);  
                }  
            } else {  
                $mensagem = "Erro ao redefinir senha. Por favor, tente novamente.";  
            }  
        } catch (PDOException $e) {  
            $mensagem = "Erro ao redefinir senha: " . $e->getMessage();  
            error_log("Erro ao redefinir senha: " . $e->getMessage());  
        }  
    }  
}  
?>  

<!DOCTYPE html>  
<html lang="pt-BR">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Redefinir Senha - Xuxuzinho</title>  
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
                            <h2 class="fw-bold text-primary">Redefinir Senha</h2>  
                            <p class="text-muted">Crie uma nova senha para sua conta</p>  
                        </div>  
                        
                        <?php if (!empty($mensagem)): ?>  
                            <div class="alert alert-<?php echo $tipo_mensagem; ?>"><?php echo $mensagem; ?></div>  
                        <?php endif; ?>  
                        
                        <?php if ($token_valido): ?>  
                        <form method="POST" action="redefinir_senha.php?token=<?php echo htmlspecialchars($token); ?>">  
                            <div class="mb-3">  
                                <label for="senha" class="form-label">Nova Senha</label>  
                                <input type="password" class="form-control" id="senha" name="senha" minlength="8" required>  
                                <small class="form-text text-muted">A senha deve ter pelo menos 8 caracteres.</small>  
                            </div>  
                            <div class="mb-3">  
                                <label for="confirmar_senha" class="form-label">Confirmar Senha</label>  
                                <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>  
                            </div>  
                            <div class="d-grid gap-2">  
                                <button type="submit" class="btn btn-primary">Salvar Nova Senha</button>  
                            </div>  
                        </form>  
                        <?php endif; ?>  
                        
                        <div class="mt-4 text-center">  
                            <a href="login.php" class="text-decoration-none">Voltar para login</a>  
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
        
        // Verificar força da senha  
        document.getElementById('senha').addEventListener('input', function() {  
            const senha = this.value;  
            const forcaSenha = verificarForcaSenha(senha);  
            const barraForça = document.getElementById('forca-senha');  
            
            if (forcaSenha === 'fraca') {  
                barraForça.className = 'progress-bar bg-danger';  
                barraForça.style.width = '33%';  
                barraForça.textContent = 'Fraca';  
            } else if (forcaSenha === 'media') {  
                barraForça.className = 'progress-bar bg-warning';  
                barraForça.style.width = '66%';  
                barraForça.textContent = 'Média';  
            } else {  
                barraForça.className = 'progress-bar bg-success';  
                barraForça.style.width = '100%';  
                barraForça.textContent = 'Forte';  
            }  
        });  
        
        // Verificar se as senhas coincidem  
        document.getElementById('confirmar_senha').addEventListener('input', function() {  
            const senha = document.getElementById('senha').value;  
            const confirmarSenha = this.value;  
            const feedback = document.getElementById('senhas-feedback');  
            
            if (senha === confirmarSenha) {  
                this.classList.remove('is-invalid');  
                this.classList.add('is-valid');  
                if (feedback) feedback.style.display = 'none';  
            } else {  
                this.classList.remove('is-valid');  
                this.classList.add('is-invalid');  
                if (feedback) feedback.style.display = 'block';  
            }  
        });  
        
        function verificarForcaSenha(senha) {  
            if (senha.length < 8) return 'fraca';  
            
            let pontos = 0;  
            if (/[a-z]/.test(senha)) pontos++;  
            if (/[A-Z]/.test(senha)) pontos++;  
            if (/\d/.test(senha)) pontos++;  
            if (/[^a-zA-Z0-9]/.test(senha)) pontos++;  
            
            if (pontos <= 2) return 'fraca';  
            if (pontos === 3) return 'media';  
            return 'forte';  
        }  
        
        <?php if ($tipo_mensagem === 'success'): ?>  
        Swal.fire({  
            title: 'Senha Redefinida!',  
            text: 'Sua senha foi alterada com sucesso. Você será redirecionado para a página de login.',  
            icon: 'success',  
            timer: 5000,  
            showConfirmButton: false  
        }).then(function() {  
            window.location.href = 'login.php';  
        });  
        <?php endif; ?>  
    </script>  
</body>  
</html>