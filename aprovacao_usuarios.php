<?php  
session_start();  
date_default_timezone_set('America/Sao_Paulo'); 
require_once 'includes/db_connection.php';  
require_once 'includes/functions.php';  
require_once 'includes/log_functions.php';  
use PHPMailer\PHPMailer\PHPMailer;  
use PHPMailer\PHPMailer\SMTP;  
use PHPMailer\PHPMailer\Exception;  

// Importar PHPMailer  
require 'PHPMailer/src/Exception.php';  
require 'PHPMailer/src/PHPMailer.php';  
require 'PHPMailer/src/SMTP.php';  

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] != 'admin') {  
    header("Location: painel.php?erro=acesso_negado");  
    exit;  
}  

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['usuario_id'])) {  
    $usuario_id = intval($_POST['usuario_id']);  
    $action = $_POST['action'];  
    $motivo = isset($_POST['motivo']) ? sanitize($_POST['motivo']) : '';  
    $admin_id = $_SESSION['usuario_id'];  
    $admin_nome = $_SESSION['usuario_nome'];  

    try {  
        $stmt = $pdo->prepare("SELECT id, nome, email, status FROM usuarios WHERE id = ?");  
        $stmt->execute([$usuario_id]);  
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);  

        if (!$usuario) {  
            echo json_encode(['status' => 'error', 'message' => 'Usuário não encontrado.']);  
            exit;  
        }  

        if ($usuario['status'] !== 'pendente' && !isset($_POST['force_update'])) {  
            echo json_encode(['status' => 'error', 'message' => 'Este usuário já foi processado anteriormente.']);  
            exit;  
        }  

        if ($action === 'aprovar') {  
            $stmt = $pdo->prepare("UPDATE usuarios SET status = 'aprovado', data_atualizacao = NOW() WHERE id = ?");  
            $stmt->execute([$usuario_id]);  

            registrar_log('aprovacao', 'usuarios', $usuario_id, "Usuário aprovado no sistema", $admin_id, $admin_nome);  

            $email_to = $usuario['email'];  
            $subject = "Sua conta foi aprovada - Xuxuzinho";  
            $message = "Olá {$usuario['nome']},<br><br>";  
            $message .= "Sua solicitação de acesso ao sistema Xuxuzinho foi aprovada.<br>";  
            $message .= "Você já pode acessar o sistema utilizando seu e-mail e senha cadastrados.<br><br>";  
            $message .= "Atenciosamente,<br>Equipe Xuxuzinho";  

            enviar_email($email_to, $subject, $message);  

            $response = ['status' => 'success', 'message' => 'Usuário aprovado com sucesso.'];  
        } elseif ($action === 'rejeitar') {  
            if (empty($motivo)) {  
                echo json_encode(['status' => 'error', 'message' => 'É necessário informar um motivo para rejeitar o usuário.']);  
                exit;  
            }  

            $stmt = $pdo->prepare("UPDATE usuarios SET status = 'rejeitado', data_atualizacao = NOW() WHERE id = ?");  
            $stmt->execute([$usuario_id]);  

            registrar_log('rejeicao', 'usuarios', $usuario_id, "Usuário rejeitado. Motivo: $motivo", $admin_id, $admin_nome);  

            $email_to = $usuario['email'];  
            $subject = "Sua solicitação de acesso foi negada - Xuxuzinho";  
            $message = "Olá {$usuario['nome']},<br><br>";  
            $message .= "Infelizmente, sua solicitação de acesso ao sistema Xuxuzinho foi negada.<br><br>";  
            $message .= "<strong>Motivo:</strong> $motivo<br><br>";  
            $message .= "Se você acredita que isso foi um erro, entre em contato com o administrador do sistema.<br><br>";  
            $message .= "Atenciosamente,<br>Equipe Xuxuzinho";  

            enviar_email($email_to, $subject, $message);  

            $response = ['status' => 'success', 'message' => 'Usuário rejeitado com sucesso.'];  
        } else {  
            $response = ['status' => 'error', 'message' => 'Ação inválida.'];  
        }  

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {  
            echo json_encode($response);  
            exit;  
        } else {  
            header("Location: aprovacao_usuarios.php?msg=" . urlencode($response['message']));  
            exit;  
        }  

    } catch (PDOException $e) {  
        $error = 'Erro ao processar a solicitação: ' . $e->getMessage();  
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {  
            echo json_encode(['status' => 'error', 'message' => $error]);  
            exit;  
        } else {  
            header("Location: aprovacao_usuarios.php?erro=" . urlencode($error));  
            exit;  
        }  
    }  
}  

function enviar_email($to, $subject, $message, $nome_destinatario = '') {  
    try {  
        $mail = new PHPMailer(true);  
        $mail->isSMTP();  
        $mail->Host = 'smtp.hostinger.com';  
        $mail->SMTPAuth = true;  
        $mail->Username = 'recuperacao@atlasged.com';  
        $mail->Password = '@Rr6rh3264f9';  
        $mail->SMTPSecure = 'ssl';  
        $mail->Port = 465;  
        $mail->CharSet = 'UTF-8';  
        $mail->setFrom('recuperacao@atlasged.com', 'Sistema Xuxuzinho');  
        $mail->addAddress($to, $nome_destinatario);  
        $mail->isHTML(true);  
        $mail->Subject = $subject;  
        $mail->Body = $message;  
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>'], "\n", $message));  
        $mail->send();  

        registrar_log('email', 'sistema', 1, "E-mail enviado com sucesso para: $to, Assunto: $subject", $_SESSION['usuario_id'] ?? 0, $_SESSION['usuario_nome'] ?? 'Sistema');  
        return true;  
    } catch (Exception $e) {  
        registrar_log('email', 'sistema', 0, "Falha no envio de e-mail para: $to, Assunto: $subject, Erro: " . $mail->ErrorInfo, $_SESSION['usuario_id'] ?? 0, $_SESSION['usuario_nome'] ?? 'Sistema');  
        return false;  
    }  
}  

$stmt = $pdo->prepare("  
    SELECT id, nome, email, data_cadastro, status, telefone  
    FROM usuarios  
    ORDER BY  
        CASE WHEN status = 'pendente' THEN 1  
             WHEN status = 'aprovado' THEN 2  
             ELSE 3 END,  
        data_cadastro DESC  
");  
$stmt->execute();  
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);  

include 'includes/header.php';  
?>

<div class="container-fluid">  
    <div class="d-flex justify-content-between align-items-center mb-4">  
        <h2><i data-feather="users" class="me-2"></i>Aprovação de Usuários</h2>  
    </div>  
    
    <?php if (isset($_GET['msg'])): ?>  
        <div class="alert alert-success alert-dismissible fade show" role="alert">  
            <?php echo htmlspecialchars($_GET['msg']); ?>  
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>  
        </div>  
    <?php endif; ?>  
    
    <?php if (isset($_GET['erro'])): ?>  
        <div class="alert alert-danger alert-dismissible fade show" role="alert">  
            <?php echo htmlspecialchars($_GET['erro']); ?>  
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>  
        </div>  
    <?php endif; ?>  
    
    <div class="card shadow-sm">  
        <div class="card-body">  
            <ul class="nav nav-tabs mb-3" id="userTabs" role="tablist">  
                <li class="nav-item" role="presentation">  
                    <button class="nav-link active" id="pendentes-tab" data-bs-toggle="tab" data-bs-target="#pendentes" type="button" role="tab" aria-controls="pendentes" aria-selected="true">  
                        Pendentes  
                        <span class="badge bg-warning ms-1" id="contador-pendentes">  
                            <?php   
                            $contador = 0;  
                            foreach ($usuarios as $u) {  
                                if ($u['status'] == 'pendente') $contador++;  
                            }  
                            echo $contador;  
                            ?>  
                        </span>  
                    </button>  
                </li>  
                <li class="nav-item" role="presentation">  
                    <button class="nav-link" id="aprovados-tab" data-bs-toggle="tab" data-bs-target="#aprovados" type="button" role="tab" aria-controls="aprovados" aria-selected="false">  
                        Aprovados  
                    </button>  
                </li>  
                <li class="nav-item" role="presentation">  
                    <button class="nav-link" id="rejeitados-tab" data-bs-toggle="tab" data-bs-target="#rejeitados" type="button" role="tab" aria-controls="rejeitados" aria-selected="false">  
                        Rejeitados  
                    </button>  
                </li>  
            </ul>  
            
            <div class="tab-content" id="userTabsContent">  
                <!-- Tab Pendentes -->  
                <div class="tab-pane fade show active" id="pendentes" role="tabpanel" aria-labelledby="pendentes-tab">  
                    <?php if ($contador > 0): ?>  
                        <div class="table-responsive">  
                            <table class="table table-hover" id="tabela-pendentes">  
                                <thead>  
                                    <tr>  
                                        <th>Nome</th>  
                                        <th>Email</th>  
                                        <th>Telefone</th>  
                                        <th>Data Cadastro</th>  
                                        <th>Ações</th>  
                                    </tr>  
                                </thead>  
                                <tbody>  
                                    <?php foreach ($usuarios as $usuario): ?>  
                                        <?php if ($usuario['status'] === 'pendente'): ?>  
                                            <tr>  
                                                <td><?php echo htmlspecialchars($usuario['nome']); ?></td>  
                                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>  
                                                <td><?php echo htmlspecialchars($usuario['telefone'] ?? 'Não informado'); ?></td>  
                                                <td><?php echo date('d/m/Y H:i', strtotime($usuario['data_cadastro'])); ?></td>  
                                                <td>  
                                                    <button   
                                                        class="btn btn-sm btn-success btn-aprovar"   
                                                        data-id="<?php echo $usuario['id']; ?>"  
                                                        data-nome="<?php echo htmlspecialchars($usuario['nome']); ?>"  
                                                    >  
                                                        <i data-feather="check" class="feather-sm"></i> Aprovar  
                                                    </button>  
                                                    <button   
                                                        class="btn btn-sm btn-danger btn-rejeitar"   
                                                        data-id="<?php echo $usuario['id']; ?>"  
                                                        data-nome="<?php echo htmlspecialchars($usuario['nome']); ?>"  
                                                    >  
                                                        <i data-feather="x" class="feather-sm"></i> Rejeitar  
                                                    </button>  
                                                </td>  
                                            </tr>  
                                        <?php endif; ?>  
                                    <?php endforeach; ?>  
                                </tbody>  
                            </table>  
                        </div>  
                    <?php else: ?>  
                        <div class="alert alert-info">  
                            <i data-feather="info" class="me-2"></i> Não há usuários pendentes de aprovação.  
                        </div>  
                    <?php endif; ?>  
                </div>  
                
                <!-- Tab Aprovados -->  
                <div class="tab-pane fade" id="aprovados" role="tabpanel" aria-labelledby="aprovados-tab">  
                    <div class="table-responsive">  
                        <table class="table table-hover" id="tabela-aprovados">  
                            <thead>  
                                <tr>  
                                    <th>Nome</th>  
                                    <th>Email</th>  
                                    <th>Telefone</th>  
                                    <th>Data Cadastro</th>  
                                    <th>Ações</th>  
                                </tr>  
                            </thead>  
                            <tbody>  
                                <?php foreach ($usuarios as $usuario): ?>  
                                    <?php if ($usuario['status'] === 'aprovado'): ?>  
                                        <tr>  
                                            <td><?php echo htmlspecialchars($usuario['nome']); ?></td>  
                                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>  
                                            <td><?php echo htmlspecialchars($usuario['telefone'] ?? 'Não informado'); ?></td>  
                                            <td><?php echo date('d/m/Y H:i', strtotime($usuario['data_cadastro'])); ?></td>  
                                            <td>  
                                                <button   
                                                    class="btn btn-sm btn-danger btn-rejeitar"   
                                                    data-id="<?php echo $usuario['id']; ?>"  
                                                    data-nome="<?php echo htmlspecialchars($usuario['nome']); ?>"  
                                                    data-force="true"  
                                                >  
                                                    <i data-feather="x" class="feather-sm"></i> Rejeitar  
                                                </button>  
                                            </td>  
                                        </tr>  
                                    <?php endif; ?>  
                                <?php endforeach; ?>  
                            </tbody>  
                        </table>  
                    </div>  
                </div>  
                
                                <!-- Tab Rejeitados -->  
                                <div class="tab-pane fade" id="rejeitados" role="tabpanel" aria-labelledby="rejeitados-tab">  
                    <div class="table-responsive">  
                        <table class="table table-hover" id="tabela-rejeitados">  
                            <thead>  
                                <tr>  
                                    <th>Nome</th>  
                                    <th>Email</th>  
                                    <th>Telefone</th>  
                                    <th>Data Cadastro</th>  
                                    <th>Ações</th>  
                                </tr>  
                            </thead>  
                            <tbody>  
                                <?php foreach ($usuarios as $usuario): ?>  
                                    <?php if ($usuario['status'] === 'rejeitado'): ?>  
                                        <tr>  
                                            <td><?php echo htmlspecialchars($usuario['nome']); ?></td>  
                                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>  
                                            <td><?php echo htmlspecialchars($usuario['telefone'] ?? 'Não informado'); ?></td>  
                                            <td><?php echo date('d/m/Y H:i', strtotime($usuario['data_cadastro'])); ?></td>  
                                            <td>  
                                                <button   
                                                    class="btn btn-sm btn-success btn-aprovar"   
                                                    data-id="<?php echo $usuario['id']; ?>"  
                                                    data-nome="<?php echo htmlspecialchars($usuario['nome']); ?>"  
                                                    data-force="true"  
                                                >  
                                                    <i data-feather="check" class="feather-sm"></i> Aprovar  
                                                </button>  
                                                <button   
                                                    class="btn btn-sm btn-info btn-historico"   
                                                    data-id="<?php echo $usuario['id']; ?>"  
                                                    data-nome="<?php echo htmlspecialchars($usuario['nome']); ?>"  
                                                >  
                                                    <i data-feather="clock" class="feather-sm"></i> Histórico  
                                                </button>  
                                            </td>  
                                        </tr>  
                                    <?php endif; ?>  
                                <?php endforeach; ?>  
                            </tbody>  
                        </table>  
                    </div>  
                </div>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- Modal de Rejeição -->  
<div class="modal fade" id="rejeicaoModal" tabindex="-1" aria-labelledby="rejeicaoModalLabel" aria-hidden="true">  
    <div class="modal-dialog">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="rejeicaoModalLabel">Rejeitar Usuário</h5>  
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>  
            </div>  
            <div class="modal-body">  
                <form id="rejeicaoForm" method="post">  
                    <input type="hidden" name="action" value="rejeitar">  
                    <input type="hidden" name="usuario_id" id="rejeicao_usuario_id">  
                    <input type="hidden" name="force_update" id="rejeicao_force" value="0">  
                    
                    <div class="mb-3">  
                        <p>Você está prestes a rejeitar o usuário <strong id="rejeicao_nome_usuario"></strong>.</p>  
                        <p>Por favor, informe um motivo para a rejeição:</p>  
                    </div>  
                    
                    <div class="mb-3">  
                        <label for="motivo" class="form-label">Motivo da Rejeição</label>  
                        <textarea class="form-control" id="motivo" name="motivo" rows="3" required></textarea>  
                    </div>  
                </form>  
            </div>  
            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>  
                <button type="submit" form="rejeicaoForm" class="btn btn-danger">Rejeitar</button>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- Modal de Confirmação de Aprovação -->  
<div class="modal fade" id="aprovacaoModal" tabindex="-1" aria-labelledby="aprovacaoModalLabel" aria-hidden="true">  
    <div class="modal-dialog">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="aprovacaoModalLabel">Aprovar Usuário</h5>  
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>  
            </div>  
            <div class="modal-body">  
                <form id="aprovacaoForm" method="post">  
                    <input type="hidden" name="action" value="aprovar">  
                    <input type="hidden" name="usuario_id" id="aprovacao_usuario_id">  
                    <input type="hidden" name="force_update" id="aprovacao_force" value="0">  
                    
                    <p>Você está prestes a aprovar o usuário <strong id="aprovacao_nome_usuario"></strong>.</p>  
                    <p>O usuário receberá um e-mail informando que sua conta foi aprovada e poderá acessar o sistema.</p>  
                </form>  
            </div>  
            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>  
                <button type="submit" form="aprovacaoForm" class="btn btn-success">Aprovar</button>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- Modal de Histórico -->  
<div class="modal fade" id="historicoModal" tabindex="-1" aria-labelledby="historicoModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-lg">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="historicoModalLabel">Histórico do Usuário</h5>  
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>  
            </div>  
            <div class="modal-body">  
                <div id="historico-loading" class="text-center py-4">  
                    <div class="spinner-border text-primary" role="status">  
                        <span class="visually-hidden">Carregando...</span>  
                    </div>  
                    <p class="mt-2">Carregando histórico...</p>  
                </div>  
                <div id="historico-content" class="d-none">  
                    <table class="table table-striped table-hover">  
                        <thead>  
                            <tr>  
                                <th>Data/Hora</th>  
                                <th>Ação</th>  
                                <th>Detalhes</th>  
                                <th>Realizado por</th>  
                            </tr>  
                        </thead>  
                        <tbody id="historico-table-body">  
                            <!-- Preenchido via AJAX -->  
                        </tbody>  
                    </table>  
                </div>  
                <div id="historico-empty" class="d-none alert alert-info">  
                    <i data-feather="info" class="me-2"></i> Não foram encontrados registros de histórico para este usuário.  
                </div>  
                <div id="historico-error" class="d-none alert alert-danger">  
                    <i data-feather="alert-circle" class="me-2"></i> Ocorreu um erro ao buscar o histórico. Por favor, tente novamente.  
                </div>  
            </div>  
            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>  
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

<!-- DataTables depois -->  
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>  
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>  
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>  
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>  

<!-- Bootstrap por último -->  
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>  

<!-- Outros scripts -->  
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>  
<script src="https://unpkg.com/feather-icons"></script>

<script>  
    document.addEventListener('DOMContentLoaded', function() {  
        console.log('DOM carregado. Verificando bibliotecas...');  
        console.log('jQuery disponível:', typeof $ !== 'undefined');  
        console.log('jQuery versão:', typeof $ !== 'undefined' ? $.fn.jquery : 'não disponível');  
        console.log('DataTable disponível:', typeof $ !== 'undefined' && typeof $.fn.DataTable === 'function');  
        console.log('Bootstrap disponível:', typeof bootstrap !== 'undefined');  
        console.log('Feather disponível:', typeof feather !== 'undefined');  
        
        // Função para carregar um script  
        function loadScript(url, callback) {  
            console.log('Carregando script:', url);  
            var script = document.createElement('script');  
            script.type = 'text/javascript';  
            script.src = url;  
            script.onload = callback;  
            script.onerror = function() {  
                console.error('Erro ao carregar script:', url);  
            };  
            document.head.appendChild(script);  
        }  
        
        // Inicializar DataTables com verificação de disponibilidade  
        function inicializarDataTables() {  
            console.log('Tentando inicializar DataTables...');  
            
            // Verificar se jQuery está disponível  
            if (typeof jQuery === 'undefined') {  
                console.error('jQuery não está disponível. Carregando jQuery...');  
                loadScript('https://code.jquery.com/jquery-3.6.0.min.js', inicializarDataTables);  
                return;  
            }  
            
            // Usar jQuery explicitamente para evitar conflitos  
            jQuery(function($) {  
                // Verificar se DataTable está disponível  
                if (typeof $.fn.DataTable !== 'function') {  
                    console.log('DataTable não está disponível. Carregando bibliotecas DataTables...');  
                    
                    // Carregar DataTables e suas dependências em sequência  
                    loadScript('https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js', function() {  
                        loadScript('https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js', function() {  
                            loadScript('https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js', function() {  
                                loadScript('https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js', function() {  
                                    // Todas as bibliotecas foram carregadas, inicializar DataTables  
                                    console.log('Bibliotecas DataTables carregadas, tentando inicializar novamente...');  
                                    inicializarDataTables();  
                                });  
                            });  
                        });  
                    });  
                    return;  
                }  
                
                console.log('Inicializando DataTables...');  
                
                const dataTableConfig = {  
                    language: {  
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'  
                    },  
                    responsive: true,  
                    pageLength: 10,  
                    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Todos"]],  
                    dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>' +  
                        '<"row"<"col-sm-12"tr>>' +  
                        '<"row"<"col-sm-5"i><"col-sm-7"p>>',  
                    order: [[3, 'desc']], // Ordenar por data de cadastro (decrescente)  
                    stateSave: true,  
                    drawCallback: function() {  
                        if (typeof feather !== 'undefined') {  
                            feather.replace();  
                        }  
                    }  
                };  
                
                try {  
                    // Verificar se as tabelas existem antes de inicializar  
                    if ($('#tabela-pendentes').length > 0) {  
                        if (!$.fn.DataTable.isDataTable('#tabela-pendentes')) {  
                            $('#tabela-pendentes').DataTable(dataTableConfig);  
                            console.log('DataTable (pendentes) inicializado com sucesso.');  
                        }  
                    }  
                    
                    if ($('#tabela-aprovados').length > 0) {  
                        if (!$.fn.DataTable.isDataTable('#tabela-aprovados')) {  
                            $('#tabela-aprovados').DataTable(dataTableConfig);  
                            console.log('DataTable (aprovados) inicializado com sucesso.');  
                        }  
                    }  
                    
                    if ($('#tabela-rejeitados').length > 0) {  
                        if (!$.fn.DataTable.isDataTable('#tabela-rejeitados')) {  
                            $('#tabela-rejeitados').DataTable(dataTableConfig);  
                            console.log('DataTable (rejeitados) inicializado com sucesso.');  
                        }  
                    }  
                    
                    // Configurar eventos para abas após inicialização das tabelas  
                    const userTabsButtons = document.querySelectorAll('#userTabs button[data-bs-toggle="tab"]');  
                    if (userTabsButtons.length > 0) {  
                        userTabsButtons.forEach(button => {  
                            button.addEventListener('shown.bs.tab', function (e) {  
                                try {  
                                    console.log('Aba ativada:', e.target.id);  
                                    
                                    // Quando a aba for mostrada, reajustar a largura das colunas da tabela DataTable  
                                    if (e.target.id === 'pendentes-tab') {  
                                        if ($.fn.DataTable.isDataTable('#tabela-pendentes')) {  
                                            $('#tabela-pendentes').DataTable().columns.adjust().responsive.recalc();  
                                            console.log('Colunas ajustadas: tabela-pendentes');  
                                        }  
                                    } else if (e.target.id === 'aprovados-tab') {  
                                        if ($.fn.DataTable.isDataTable('#tabela-aprovados')) {  
                                            $('#tabela-aprovados').DataTable().columns.adjust().responsive.recalc();  
                                            console.log('Colunas ajustadas: tabela-aprovados');  
                                        }  
                                    } else if (e.target.id === 'rejeitados-tab') {  
                                        if ($.fn.DataTable.isDataTable('#tabela-rejeitados')) {  
                                            $('#tabela-rejeitados').DataTable().columns.adjust().responsive.recalc();  
                                            console.log('Colunas ajustadas: tabela-rejeitados');  
                                        }  
                                    }  
                                } catch (error) {  
                                    console.error('Erro ao ajustar colunas:', error);  
                                }  
                            });  
                        });  
                    }  
                } catch (e) {  
                    console.error('Erro ao inicializar DataTables:', e);  
                }  
            });  
        }  
        
        // Inicializar DataTables  
        inicializarDataTables();  
        
        // Inicializar Feather Icons (para os ícones nos botões)  
        if (typeof feather !== 'undefined') {  
            feather.replace();  
            console.log('Feather Icons inicializado com sucesso.');  
        } else {  
            console.warn('Feather Icons não está disponível.');  
        }  
        
        // Modal de Rejeição  
        if (document.getElementById('rejeicaoModal')) {  
            const rejeicaoModal = new bootstrap.Modal(document.getElementById('rejeicaoModal'));  
            
            // Botões de rejeitar  
            document.querySelectorAll('.btn-rejeitar').forEach(button => {  
                button.addEventListener('click', function() {  
                    const usuarioId = this.getAttribute('data-id');  
                    const usuarioNome = this.getAttribute('data-nome');  
                    const forceUpdate = this.getAttribute('data-force') === 'true' ? '1' : '0';  
                    
                    document.getElementById('rejeicao_usuario_id').value = usuarioId;  
                    document.getElementById('rejeicao_nome_usuario').textContent = usuarioNome;  
                    document.getElementById('rejeicao_force').value = forceUpdate;  
                    
                    rejeicaoModal.show();  
                });  
            });  
            
            // Submit do formulário de rejeição via AJAX  
            const rejeicaoForm = document.getElementById('rejeicaoForm');  
            if (rejeicaoForm) {  
                rejeicaoForm.addEventListener('submit', function(e) {  
                    e.preventDefault();  
                    
                    const formData = new FormData(this);  
                    
                    fetch('aprovacao_usuarios.php', {  
                        method: 'POST',  
                        body: formData,  
                        headers: {  
                            'X-Requested-With': 'XMLHttpRequest'  
                        }  
                    })  
                    .then(response => response.json())  
                    .then(data => {  
                        rejeicaoModal.hide();  
                        
                        if (data.status === 'success') {  
                            Swal.fire({  
                                title: 'Sucesso!',  
                                text: data.message,  
                                icon: 'success',  
                                confirmButtonText: 'OK'  
                            }).then(() => {  
                                window.location.reload();  
                            });  
                        } else {  
                            Swal.fire({  
                                title: 'Erro!',  
                                text: data.message,  
                                icon: 'error',  
                                confirmButtonText: 'OK'  
                            });  
                        }  
                    })  
                    .catch(error => {  
                        rejeicaoModal.hide();  
                        
                        Swal.fire({  
                            title: 'Erro!',  
                            text: 'Ocorreu um erro ao processar a solicitação. Por favor, tente novamente.',  
                            icon: 'error',  
                            confirmButtonText: 'OK'  
                        });  
                        
                        console.error('Erro:', error);  
                    });  
                });  
            }  
        }  
        
        // Modal de Aprovação  
        if (document.getElementById('aprovacaoModal')) {  
            const aprovacaoModal = new bootstrap.Modal(document.getElementById('aprovacaoModal'));  
            
            // Botões de aprovar  
            document.querySelectorAll('.btn-aprovar').forEach(button => {  
                button.addEventListener('click', function() {  
                    const usuarioId = this.getAttribute('data-id');  
                    const usuarioNome = this.getAttribute('data-nome');  
                    const forceUpdate = this.getAttribute('data-force') === 'true' ? '1' : '0';  
                    
                    document.getElementById('aprovacao_usuario_id').value = usuarioId;  
                    document.getElementById('aprovacao_nome_usuario').textContent = usuarioNome;  
                    document.getElementById('aprovacao_force').value = forceUpdate;  
                    
                    aprovacaoModal.show();  
                });  
            });  
            
            // Submit do formulário de aprovação via AJAX  
            const aprovacaoForm = document.getElementById('aprovacaoForm');  
            if (aprovacaoForm) {  
                aprovacaoForm.addEventListener('submit', function(e) {  
                    e.preventDefault();  
                    
                    const formData = new FormData(this);  
                    
                    fetch('aprovacao_usuarios.php', {  
                        method: 'POST',  
                        body: formData,  
                        headers: {  
                            'X-Requested-With': 'XMLHttpRequest'  
                        }  
                    })  
                    .then(response => response.json())  
                    .then(data => {  
                        aprovacaoModal.hide();  
                        
                        if (data.status === 'success') {  
                            Swal.fire({  
                                title: 'Sucesso!',  
                                text: data.message,  
                                icon: 'success',  
                                confirmButtonText: 'OK'  
                            }).then(() => {  
                                window.location.reload();  
                            });  
                        } else {  
                            Swal.fire({  
                                title: 'Erro!',  
                                text: data.message,  
                                icon: 'error',  
                                confirmButtonText: 'OK'  
                            });  
                        }  
                    })  
                    .catch(error => {  
                        aprovacaoModal.hide();  
                        
                        Swal.fire({  
                            title: 'Erro!',  
                            text: 'Ocorreu um erro ao processar a solicitação. Por favor, tente novamente.',  
                            icon: 'error',  
                            confirmButtonText: 'OK'  
                        });  
                        
                        console.error('Erro:', error);  
                    });  
                });  
            }  
        }  
        
        // Modal de Histórico  
        if (document.getElementById('historicoModal')) {  
            const historicoModal = new bootstrap.Modal(document.getElementById('historicoModal'));  
            
            // Botões de histórico  
            document.querySelectorAll('.btn-historico').forEach(button => {  
                button.addEventListener('click', function() {  
                    const usuarioId = this.getAttribute('data-id');  
                    const usuarioNome = this.getAttribute('data-nome');  
                    
                    // Mostrar loading  
                    document.getElementById('historico-loading').classList.remove('d-none');  
                    document.getElementById('historico-content').classList.add('d-none');  
                    document.getElementById('historico-empty').classList.add('d-none');  
                    document.getElementById('historico-error').classList.add('d-none');  
                    
                    // Abrir modal  
                    historicoModal.show();  
                    
                    // Buscar histórico via AJAX  
                    fetch('api/get_usuario_historico.php?id=' + usuarioId)  
    .then(response => response.json())  
    .then(data => {  
        // Esconder loading  
        document.getElementById('historico-loading').classList.add('d-none');  
        
        if (data.status === 'success') {  
            if (data.historico.length > 0) {  
                // Preencher tabela de histórico  
                const tbody = document.getElementById('historico-table-body');  
                tbody.innerHTML = '';  
                
                data.historico.forEach(item => {  
                    const tr = document.createElement('tr');  
                    
                    // Data/Hora  
                    const tdData = document.createElement('td');  
                    const dataFormatada = new Date(item.data_hora).toLocaleString('pt-BR');  
                    tdData.textContent = dataFormatada;  
                    tr.appendChild(tdData);  
                    
                    // Ação  
                    const tdAcao = document.createElement('td');  
                    tdAcao.textContent = item.acao;  
                    
                    // Adicionar classe baseada no tipo de ação  
                    if (item.acao.includes('aprova')) {  
                        tdAcao.classList.add('text-success');  
                    } else if (item.acao.includes('rejeit')) {  
                        tdAcao.classList.add('text-danger');  
                    } else if (item.acao.includes('cadastr')) {  
                        tdAcao.classList.add('text-primary');  
                    }  
                    
                    tr.appendChild(tdAcao);  
                    
                    // Observações  
                    const tdObservacoes = document.createElement('td');  
                    tdObservacoes.textContent = item.observacoes || '-';  
                    tr.appendChild(tdObservacoes);  
                    
                    // Usuário responsável  
                    const tdUsuario = document.createElement('td');  
                    tdUsuario.textContent = item.usuario_responsavel || '-';  
                    tr.appendChild(tdUsuario);  
                    
                    tbody.appendChild(tr);  
                });  
                
                // Mostrar conteúdo  
                document.getElementById('historico-content').classList.remove('d-none');  
            } else {  
                // Sem histórico  
                document.getElementById('historico-empty').classList.remove('d-none');  
            }  
        } else {  
            // Erro  
            document.getElementById('historico-error').classList.remove('d-none');  
            document.getElementById('historico-error-message').textContent = data.message || 'Erro desconhecido ao buscar histórico.';  
            console.error('Erro ao buscar histórico:', data.message);  
        }  
    })  
    .catch(error => {  
        // Esconder loading e mostrar erro  
        document.getElementById('historico-loading').classList.add('d-none');  
        document.getElementById('historico-error').classList.remove('d-none');  
        document.getElementById('historico-error-message').textContent = 'Erro de comunicação com o servidor.';  
        
        console.error('Erro ao buscar histórico:', error);  
    });  
});   
});  
}  
        
// Modal de Detalhes do Usuário  
if (document.getElementById('detalhesModal')) {  
    const detalhesModal = new bootstrap.Modal(document.getElementById('detalhesModal'));  
    
    // Botões de detalhes  
    document.querySelectorAll('.btn-detalhes').forEach(button => {  
        button.addEventListener('click', function() {  
            const usuarioId = this.getAttribute('data-id');  
            const usuarioNome = this.getAttribute('data-nome');  
            
            // Configurar título do modal  
            document.getElementById('detalhes-titulo').textContent = 'Detalhes: ' + usuarioNome;  
            
            // Mostrar loading  
            document.getElementById('detalhes-loading').classList.remove('d-none');  
            document.getElementById('detalhes-content').classList.add('d-none');  
            document.getElementById('detalhes-error').classList.add('d-none');  
            
            // Abrir modal  
            detalhesModal.show();  
            
            // Buscar detalhes via AJAX  
            fetch('api/get_usuario_detalhes.php?id=' + usuarioId)  
                .then(response => response.json())  
                .then(data => {  
                    // Esconder loading  
                    document.getElementById('detalhes-loading').classList.add('d-none');  
                    
                    if (data.status === 'success') {  
                        // Preencher dados  
                        const usuario = data.usuario;  
                        document.getElementById('detalhes-nome').textContent = usuario.nome || '-';  
                        document.getElementById('detalhes-email').textContent = usuario.email || '-';  
                        document.getElementById('detalhes-telefone').textContent = usuario.telefone || '-';  
                        document.getElementById('detalhes-cpf').textContent = usuario.cpf || '-';  
                        
                        // Formatando a data de nascimento  
                        const dataNascimento = usuario.data_nascimento ? new Date(usuario.data_nascimento).toLocaleDateString('pt-BR') : '-';  
                        document.getElementById('detalhes-nascimento').textContent = dataNascimento;  
                        
                        // Formatando a data de cadastro  
                        const dataCadastro = usuario.data_cadastro ? new Date(usuario.data_cadastro).toLocaleDateString('pt-BR') : '-';  
                        document.getElementById('detalhes-cadastro').textContent = dataCadastro;  
                        
                        // Preencher outros campos de acordo com sua estrutura de dados  
                        document.getElementById('detalhes-endereco').textContent =   
                            (usuario.endereco ? usuario.endereco + ', ' : '') +   
                            (usuario.numero ? usuario.numero : '') +   
                            (usuario.complemento ? ' - ' + usuario.complemento : '') +   
                            (usuario.bairro ? ', ' + usuario.bairro : '') +   
                            (usuario.cidade ? ' - ' + usuario.cidade : '') +   
                            (usuario.estado ? '/' + usuario.estado : '') +   
                            (usuario.cep ? ' - CEP: ' + usuario.cep : '') || '-';  
                            
                        // Mostrar conteúdo  
                        document.getElementById('detalhes-content').classList.remove('d-none');  
                    } else {  
                        // Erro  
                        document.getElementById('detalhes-error').classList.remove('d-none');  
                        document.getElementById('detalhes-error-message').textContent = data.message || 'Erro desconhecido ao buscar detalhes.';  
                        console.error('Erro ao buscar detalhes:', data.message);  
                    }  
                })  
                .catch(error => {  
                    // Esconder loading e mostrar erro  
                    document.getElementById('detalhes-loading').classList.add('d-none');  
                    document.getElementById('detalhes-error').classList.remove('d-none');  
                    document.getElementById('detalhes-error-message').textContent = 'Erro de comunicação com o servidor.';  
                    
                    console.error('Erro ao buscar detalhes:', error);  
                });  
        });  
    });  
}  

});  
</script>

<?php  
// Incluir o footer  
include 'includes/footer.php';  
?>