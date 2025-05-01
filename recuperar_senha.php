<?php  
session_start();  
date_default_timezone_set('America/Sao_Paulo'); 
require_once 'includes/db_connection.php';  
require_once 'includes/functions.php';  
use PHPMailer\PHPMailer\PHPMailer;  
use PHPMailer\PHPMailer\SMTP;  
use PHPMailer\PHPMailer\Exception;  

// Importar PHPMailer  
require 'PHPMailer/src/Exception.php';  
require 'PHPMailer/src/PHPMailer.php';  
require 'PHPMailer/src/SMTP.php';  

$mensagem = '';  
$sucesso = false;  

// Função para obter a URL base do sistema  
function getBaseUrl() {  
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';  
    $host = $_SERVER['HTTP_HOST'];  
    $script_name = dirname($_SERVER['SCRIPT_NAME']);  
    $base_url = $protocol . $host . $script_name;  
    
    // Garantir que a URL termina com uma barra  
    if (substr($base_url, -1) !== '/') {  
        $base_url .= '/';  
    }  
    
    return $base_url;  
}  

// Processar a solicitação de recuperação de senha  
if ($_SERVER['REQUEST_METHOD'] === 'POST') {  
    $email = sanitize($_POST['email']);  
    
    if (empty($email)) {  
        $mensagem = "Por favor, informe seu email.";  
    } else {  
        try {  
            // Verificar se o email existe  
            $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ? AND status = 'aprovado'");  
            $stmt->execute([$email]);  
            $usuario = $stmt->fetch();  
            
            if (!$usuario) {  
                $mensagem = "Email não encontrado ou conta não aprovada.";  
            } else {  
                // Gerar token de recuperação  
                $token = bin2hex(random_bytes(32)); // Usar função mais segura para gerar token  
                $token_expira = date('Y-m-d H:i:s', strtotime('+1 hour'));  
                
                // Salvar token no banco  
                $stmt = $pdo->prepare("UPDATE usuarios SET token_recuperacao = ?, token_expira = ? WHERE id = ?");  
                $stmt->execute([$token, $token_expira, $usuario['id']]);  
                
                // Obter a URL base do sistema  
                $base_url = getBaseUrl();  
                $reset_link = $base_url . "redefinir_senha.php?token=" . $token;  
                
                // Enviar email com link de recuperação  
                $mail = new PHPMailer(true);  
                
                try {  
                    // Configurações do servidor  
                    $mail->isSMTP();  
                    $mail->Host       = 'smtp.hostinger.com';  
                    $mail->SMTPAuth   = true;  
                    $mail->Username   = 'recuperacao@atlasged.com';  
                    $mail->Password   = '@Rr6rh3264f9';  
                    $mail->SMTPSecure = 'ssl';  
                    $mail->Port       = 465;  
                    $mail->CharSet    = 'UTF-8'; // Garantir que caracteres especiais sejam exibidos corretamente  
                    
                    // Destinatários  
                    $mail->setFrom('recuperacao@atlasged.com', 'Sistema Xuxuzinho');  
                    $mail->addAddress($email, $usuario['nome']);  
                    
                    // Conteúdo  
                    $mail->isHTML(true);  
                    $mail->Subject = 'Recuperação de Senha - Sistema Xuxuzinho';  
                    $mail->Body    = '  
                        <h2>Recuperação de Senha</h2>  
                        <p>Olá, ' . htmlspecialchars($usuario['nome']) . '!</p>  
                        <p>Você solicitou a recuperação de senha. Clique no link abaixo para criar uma nova senha:</p>  
                        <p><a href="' . $reset_link . '">Redefinir Senha</a></p>  
                        <p>Este link é válido por 1 hora.</p>  
                        <p>Se você não solicitou esta recuperação, por favor ignore este email.</p>  
                        <p>Atenciosamente,<br>Equipe Xuxuzinho</p>  
                    ';  
                    
                    $mail->send();  
                    $sucesso = true;  
                    $mensagem = "Enviamos instruções de recuperação para seu email.";  
                    
                    // Registrar no log, se a função existir  
                    if (function_exists('registrar_log')) {  
                        registrar_log('recuperacao_senha', 'Solicitação de recuperação de senha', $usuario['id']);  
                    }  
                    
                } catch (Exception $e) {  
                    $mensagem = "Ocorreu um erro ao enviar o email: " . $mail->ErrorInfo;  
                    error_log("Erro ao enviar email de recuperação: " . $mail->ErrorInfo);  
                }  
            }  
        } catch (PDOException $e) {  
            $mensagem = "Erro ao processar solicitação: " . $e->getMessage();  
            error_log("Erro na recuperação de senha: " . $e->getMessage());  
        }  
    }  
}  
?>  

<!DOCTYPE html>  
<html lang="pt-BR">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Recuperar Senha - Xuxuzinho</title>  
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
                            <h2 class="fw-bold text-primary">Recuperar Senha</h2>  
                            <p class="text-muted">Informe seu email para receber as instruções</p>  
                        </div>  
                        
                        <?php if (!empty($mensagem) && !$sucesso): ?>  
                            <div class="alert alert-danger"><?php echo $mensagem; ?></div>  
                        <?php elseif (!empty($mensagem) && $sucesso): ?>  
                            <div class="alert alert-success"><?php echo $mensagem; ?></div>  
                        <?php endif; ?>  
                        
                        <?php if (!$sucesso): ?>  
                        <form method="POST" action="recuperar_senha.php">  
                            <div class="mb-3">  
                                <label for="email" class="form-label">Email</label>  
                                <input type="email" class="form-control" id="email" name="email" required>  
                            </div>  
                            <div class="d-grid gap-2">  
                                <button type="submit" class="btn btn-primary">Recuperar Senha</button>  
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
        
        <?php if ($sucesso): ?>  
        Swal.fire({  
            title: 'Email Enviado',  
            text: 'Verifique sua caixa de entrada para redefinir sua senha.',  
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