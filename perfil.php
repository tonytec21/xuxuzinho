<?php  
date_default_timezone_set('America/Sao_Paulo'); 
require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  

// Inicializar variáveis  
$mensagem = '';  
$tipo_mensagem = '';  
$usuario_id = $_SESSION['usuario_id'];  

// Buscar informações do usuário atual  
try {  
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");  
    $stmt->execute([$usuario_id]);  
    $usuario = $stmt->fetch();  

    if (!$usuario) {  
        // Se não encontrar o usuário (situação rara), redirecionar para logout  
        header("Location: logout.php");  
        exit;  
    }  
} catch (PDOException $e) {  
    $mensagem = "Erro ao buscar informações do usuário: " . $e->getMessage();  
    $tipo_mensagem = "danger";  
    error_log("Erro ao buscar informações do usuário ID $usuario_id: " . $e->getMessage());  
}  

// Processar atualização de informações pessoais  
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_perfil'])) {  
    $nome = trim($_POST['nome']);  
    $email = trim($_POST['email']);  
    $telefone = isset($_POST['telefone']) ? trim($_POST['telefone']) : null;  
    $endereco = isset($_POST['endereco']) ? trim($_POST['endereco']) : null;  
    $cidade = isset($_POST['cidade']) ? trim($_POST['cidade']) : null;  
    $estado = isset($_POST['estado']) ? trim($_POST['estado']) : null;  
    $cep = isset($_POST['cep']) ? trim($_POST['cep']) : null;  
    
    // Validar campos obrigatórios  
    if (empty($nome) || empty($email)) {  
        $mensagem = "Nome e e-mail são campos obrigatórios.";  
        $tipo_mensagem = "danger";  
    } else {  
        try {  
            // Verificar se o email já existe (exceto para o próprio usuário)  
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");  
            $stmt->execute([$email, $usuario_id]);  
            
            if ($stmt->rowCount() > 0) {  
                $mensagem = "Este e-mail já está sendo usado por outro usuário.";  
                $tipo_mensagem = "danger";  
            } else {  
                // Atualizar informações do usuário  
                $stmt = $pdo->prepare("  
                    UPDATE usuarios SET   
                    nome = ?,   
                    email = ?,   
                    telefone = ?,   
                    endereco = ?,   
                    cidade = ?,   
                    estado = ?,   
                    cep = ?  
                    WHERE id = ?  
                ");  
                
                $stmt->execute([  
                    $nome,   
                    $email,   
                    $telefone,   
                    $endereco,   
                    $cidade,   
                    $estado,   
                    $cep,   
                    $usuario_id  
                ]);  
                
                // Atualizar a sessão com o novo nome  
                $_SESSION['usuario_nome'] = $nome;  
                
                $mensagem = "Perfil atualizado com sucesso!";  
                $tipo_mensagem = "success";  
                
                // Buscar informações atualizadas do usuário  
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");  
                $stmt->execute([$usuario_id]);  
                $usuario = $stmt->fetch();  
            }  
        } catch (PDOException $e) {  
            $mensagem = "Erro ao atualizar perfil: " . $e->getMessage();  
            $tipo_mensagem = "danger";  
            error_log("Erro ao atualizar perfil do usuário ID $usuario_id: " . $e->getMessage());  
        }  
    }  
}  

// Processar alteração de senha  
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_senha'])) {  
    $senha_atual = $_POST['senha_atual'];  
    $nova_senha = $_POST['nova_senha'];  
    $confirmar_senha = $_POST['confirmar_senha'];  
    
    // Validar campos  
    if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {  
        $mensagem_senha = "Todos os campos de senha são obrigatórios.";  
        $tipo_mensagem_senha = "danger";  
    } elseif (strlen($nova_senha) < 8) {  
        $mensagem_senha = "A nova senha deve ter pelo menos 8 caracteres.";  
        $tipo_mensagem_senha = "danger";  
    } elseif ($nova_senha !== $confirmar_senha) {  
        $mensagem_senha = "A nova senha e a confirmação não coincidem.";  
        $tipo_mensagem_senha = "danger";  
    } else {  
        try {  
            // Verificar se a senha atual está correta  
            if (password_verify($senha_atual, $usuario['senha'])) {  
                // Atualizar a senha  
                $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);  
                
                $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");  
                $stmt->execute([$senha_hash, $usuario_id]);  
                
                $mensagem_senha = "Senha alterada com sucesso!";  
                $tipo_mensagem_senha = "success";  
            } else {  
                $mensagem_senha = "Senha atual incorreta.";  
                $tipo_mensagem_senha = "danger";  
            }  
        } catch (PDOException $e) {  
            $mensagem_senha = "Erro ao alterar senha: " . $e->getMessage();  
            $tipo_mensagem_senha = "danger";  
            error_log("Erro ao alterar senha do usuário ID $usuario_id: " . $e->getMessage());  
        }  
    }  
}  

// Processar upload de foto do perfil  
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_foto'])) {  
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {  
        $arquivo = $_FILES['foto_perfil'];  
        $nome_arquivo = $arquivo['name'];  
        $tamanho_arquivo = $arquivo['size'];  
        $tmp_nome = $arquivo['tmp_name'];  
        $erro = $arquivo['error'];  
        
        // Verificar extensão do arquivo  
        $extensao = strtolower(pathinfo($nome_arquivo, PATHINFO_EXTENSION));  
        $extensoes_permitidas = ['jpg', 'jpeg', 'png'];  
        
        if (!in_array($extensao, $extensoes_permitidas)) {  
            $mensagem_foto = "Apenas arquivos JPG, JPEG e PNG são permitidos.";  
            $tipo_mensagem_foto = "danger";  
        } elseif ($tamanho_arquivo > 2097152) { // 2 MB  
            $mensagem_foto = "O arquivo deve ter no máximo 2 MB.";  
            $tipo_mensagem_foto = "danger";  
        } else {  
            // Criar diretório de uploads se não existir  
            $diretorio_upload = 'uploads/perfil/';  
            if (!is_dir($diretorio_upload)) {  
                mkdir($diretorio_upload, 0755, true);  
            }  
            
            // Gerar nome único para o arquivo  
            $novo_nome = 'usuario_' . $usuario_id . '_' . time() . '.' . $extensao;  
            $caminho_arquivo = $diretorio_upload . $novo_nome;  
            
            // Mover o arquivo  
            if (move_uploaded_file($tmp_nome, $caminho_arquivo)) {  
                try {  
                    // Excluir foto anterior se existir  
                    if (!empty($usuario['foto_perfil']) && file_exists($usuario['foto_perfil'])) {  
                        unlink($usuario['foto_perfil']);  
                    }  
                    
                    // Atualizar no banco de dados  
                    $stmt = $pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");  
                    $stmt->execute([$caminho_arquivo, $usuario_id]);  
                    
                    $mensagem_foto = "Foto de perfil atualizada com sucesso!";  
                    $tipo_mensagem_foto = "success";  
                    
                    // Atualizar dados do usuário  
                    $usuario['foto_perfil'] = $caminho_arquivo;  
                } catch (PDOException $e) {  
                    $mensagem_foto = "Erro ao atualizar foto de perfil: " . $e->getMessage();  
                    $tipo_mensagem_foto = "danger";  
                    error_log("Erro ao atualizar foto de perfil do usuário ID $usuario_id: " . $e->getMessage());  
                }  
            } else {  
                $mensagem_foto = "Erro ao fazer upload da foto.";  
                $tipo_mensagem_foto = "danger";  
            }  
        }  
    } else {  
        $mensagem_foto = "Selecione uma foto para upload.";  
        $tipo_mensagem_foto = "danger";  
    }  
}  

// Incluir o cabeçalho  
include 'includes/header.php';  
?>  

<div class="container-fluid py-4">  
    <div class="row mb-4">  
        <div class="col-12">  
            <h1 class="fw-bold">Meu Perfil</h1>  
            <p class="text-muted">Visualize e atualize suas informações pessoais</p>  
        </div>  
    </div>  
    
    <?php if (!empty($mensagem)): ?>  
    <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">  
        <?php echo $mensagem; ?>  
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>  
    </div>  
    <?php endif; ?>  
    
    <div class="row">  
        <!-- Coluna da Foto de Perfil e Informações Básicas -->  
        <div class="col-md-4 mb-4">  
            <div class="card border-0 shadow-sm">  
                <div class="card-body text-center">  
                    <div class="mb-4">  
                        <?php if (!empty($usuario['foto_perfil']) && file_exists($usuario['foto_perfil'])): ?>  
                            <img src="<?php echo $usuario['foto_perfil']; ?>" alt="Foto de Perfil" class="rounded-circle img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;">  
                        <?php else: ?>  
                            <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center" style="width: 150px; height: 150px;">  
                                <span class="display-4 text-white"><?php echo strtoupper(substr($usuario['nome'], 0, 1)); ?></span>  
                            </div>  
                        <?php endif; ?>  
                    </div>  
                    
                    <h4 class="fw-bold"><?php echo htmlspecialchars($usuario['nome']); ?></h4>  
                    <p class="text-muted"><?php echo htmlspecialchars($usuario['email']); ?></p>  
                    
                    <?php if (!empty($mensagem_foto)): ?>  
                    <div class="alert alert-<?php echo $tipo_mensagem_foto; ?> alert-dismissible fade show mt-3" role="alert">  
                        <?php echo $mensagem_foto; ?>  
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>  
                    </div>  
                    <?php endif; ?>  
                    
                    <form method="POST" action="perfil.php" enctype="multipart/form-data" class="mt-3">  
                        <div class="mb-3">  
                            <label for="foto_perfil" class="form-label">Atualizar foto de perfil</label>  
                            <input type="file" class="form-control" id="foto_perfil" name="foto_perfil" accept="image/jpeg,image/png">  
                            <small class="form-text text-muted">Tamanho máximo: 2MB. Formatos: JPG, JPEG, PNG</small>  
                        </div>  
                        <button type="submit" name="upload_foto" class="btn btn-outline-primary">Atualizar Foto</button>  
                    </form>  
                    
                    <hr class="my-4">  
                    
                    <div class="text-start">  
                        <p><strong>Data de Cadastro:</strong> <?php echo date('d/m/Y', strtotime($usuario['data_cadastro'])); ?></p>  
                        <p><strong>Último Acesso:</strong>   
                            <?php   
                            echo (!empty($usuario['ultimo_acesso']))   
                                ? date('d/m/Y H:i', strtotime($usuario['ultimo_acesso']))   
                                : 'Nunca';   
                            ?>  
                        </p>  
                        <p><strong>Status da Conta:</strong>   
                            <?php   
                            switch ($usuario['status']) {  
                                case 'aprovado':  
                                    echo '<span class="badge bg-success">Aprovado</span>';  
                                    break;  
                                case 'pendente':  
                                    echo '<span class="badge bg-warning">Pendente</span>';  
                                    break;  
                                case 'rejeitado':  
                                    echo '<span class="badge bg-danger">Rejeitado</span>';  
                                    break;  
                                default:  
                                    echo '<span class="badge bg-secondary">Desconhecido</span>';  
                            }  
                            ?>  
                        </p>  
                    </div>  
                </div>  
            </div>  
        </div>  
        
                <!-- Coluna de Informações Pessoais e Alteração de Senha -->  
                <div class="col-md-8">  
            <!-- Informações Pessoais -->  
            <div class="card border-0 shadow-sm mb-4">  
                <div class="card-header bg-white">  
                    <h5 class="mb-0">Informações Pessoais</h5>  
                </div>  
                <div class="card-body">  
                    <form method="POST" action="perfil.php">  
                        <div class="row">  
                            <div class="col-md-6 mb-3">  
                                <label for="nome" class="form-label">Nome Completo</label>  
                                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>  
                            </div>  
                            <div class="col-md-6 mb-3">  
                                <label for="email" class="form-label">Email</label>  
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>  
                            </div>  
                        </div>  
                        
                        <div class="row">  
                            <div class="col-md-6 mb-3">  
                                <label for="telefone" class="form-label">Telefone</label>  
                                <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo htmlspecialchars($usuario['telefone'] ?? ''); ?>">  
                            </div>  
                            <div class="col-md-6 mb-3">  
                                <label for="cep" class="form-label">CEP</label>  
                                <input type="text" class="form-control" id="cep" name="cep" value="<?php echo htmlspecialchars($usuario['cep'] ?? ''); ?>">  
                            </div>  
                        </div>  
                        
                        <div class="mb-3">  
                            <label for="endereco" class="form-label">Endereço</label>  
                            <input type="text" class="form-control" id="endereco" name="endereco" value="<?php echo htmlspecialchars($usuario['endereco'] ?? ''); ?>">  
                        </div>  
                        
                        <div class="row">  
                            <div class="col-md-8 mb-3">  
                                <label for="cidade" class="form-label">Cidade</label>  
                                <input type="text" class="form-control" id="cidade" name="cidade" value="<?php echo htmlspecialchars($usuario['cidade'] ?? ''); ?>">  
                            </div>  
                            <div class="col-md-4 mb-3">  
                                <label for="estado" class="form-label">Estado</label>  
                                <select class="form-select" id="estado" name="estado">  
                                    <option value="">Selecione...</option>  
                                    <?php  
                                    $estados = [  
                                        'AC'=>'Acre', 'AL'=>'Alagoas', 'AP'=>'Amapá',   
                                        'AM'=>'Amazonas', 'BA'=>'Bahia', 'CE'=>'Ceará',   
                                        'DF'=>'Distrito Federal', 'ES'=>'Espírito Santo',   
                                        'GO'=>'Goiás', 'MA'=>'Maranhão', 'MT'=>'Mato Grosso',   
                                        'MS'=>'Mato Grosso do Sul', 'MG'=>'Minas Gerais',   
                                        'PA'=>'Pará', 'PB'=>'Paraíba', 'PR'=>'Paraná',   
                                        'PE'=>'Pernambuco', 'PI'=>'Piauí', 'RJ'=>'Rio de Janeiro',   
                                        'RN'=>'Rio Grande do Norte', 'RS'=>'Rio Grande do Sul',   
                                        'RO'=>'Rondônia', 'RR'=>'Roraima', 'SC'=>'Santa Catarina',   
                                        'SP'=>'São Paulo', 'SE'=>'Sergipe', 'TO'=>'Tocantins'  
                                    ];  
                                    
                                    foreach ($estados as $sigla => $nome) {  
                                        $selecionado = ($usuario['estado'] ?? '') === $sigla ? 'selected' : '';  
                                        echo "<option value=\"$sigla\" $selecionado>$nome</option>";  
                                    }  
                                    ?>  
                                </select>  
                            </div>  
                        </div>  
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">  
                            <button type="submit" name="atualizar_perfil" class="btn btn-primary">Salvar Alterações</button>  
                        </div>  
                    </form>  
                </div>  
            </div>  
            
            <!-- Alteração de Senha -->  
            <div class="card border-0 shadow-sm mb-4">  
                <div class="card-header bg-white">  
                    <h5 class="mb-0">Alterar Senha</h5>  
                </div>  
                <div class="card-body">  
                    <?php if (!empty($mensagem_senha)): ?>  
                    <div class="alert alert-<?php echo $tipo_mensagem_senha; ?> alert-dismissible fade show" role="alert">  
                        <?php echo $mensagem_senha; ?>  
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>  
                    </div>  
                    <?php endif; ?>  
                    
                    <form method="POST" action="perfil.php">  
                        <div class="mb-3">  
                            <label for="senha_atual" class="form-label">Senha Atual</label>  
                            <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>  
                        </div>  
                        
                        <div class="mb-3">  
                            <label for="nova_senha" class="form-label">Nova Senha</label>  
                            <input type="password" class="form-control" id="nova_senha" name="nova_senha" minlength="8" required>  
                            <small class="form-text text-muted">A senha deve ter pelo menos 8 caracteres.</small>  
                        </div>  
                        
                        <div class="mb-3">  
                            <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>  
                            <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>  
                        </div>  
                        
                        <div class="progress mb-3">  
                            <div id="forca-senha" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>  
                        </div>  
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">  
                            <button type="submit" name="alterar_senha" class="btn btn-primary">Alterar Senha</button>  
                        </div>  
                    </form>  
                </div>  
            </div>  
            
            <!-- Informações da Conta -->  
            <div class="card border-0 shadow-sm">  
                <div class="card-header bg-white">  
                    <h5 class="mb-0">Informações da Conta</h5>  
                </div>  
                <div class="card-body">  
                    <div class="row">  
                        <div class="col-md-6">  
                            <p><strong>Tipo de Conta:</strong>   
                                <?php  
                                if ($usuario['tipo'] == 'admin') {  
                                    echo 'Administrador';  
                                } else {  
                                    echo 'Usuário Padrão';  
                                }  
                                ?>  
                            </p>  
                        </div>  
                        <div class="col-md-6">  
                            <p><strong>Status:</strong>   
                                <?php  
                                switch ($usuario['status']) {  
                                    case 'aprovado':  
                                        echo '<span class="badge bg-success">Aprovado</span>';  
                                        break;  
                                    case 'pendente':  
                                        echo '<span class="badge bg-warning">Pendente</span>';  
                                        break;  
                                    case 'rejeitado':  
                                        echo '<span class="badge bg-danger">Rejeitado</span>';  
                                        break;  
                                    default:  
                                        echo '<span class="badge bg-secondary">Desconhecido</span>';  
                                }  
                                ?>  
                            </p>  
                        </div>  
                    </div>  
                    
                    <div class="row">  
                        <div class="col-md-6">  
                            <p><strong>Data de Cadastro:</strong> <?php echo date('d/m/Y', strtotime($usuario['data_cadastro'])); ?></p>  
                        </div>  
                        <div class="col-md-6">  
                            <p><strong>Último Acesso:</strong>   
                                <?php  
                                echo (!empty($usuario['ultimo_acesso']))   
                                    ? date('d/m/Y H:i', strtotime($usuario['ultimo_acesso']))   
                                    : 'Nunca';  
                                ?>  
                            </p>  
                        </div>  
                    </div>  
                    
                    <hr>  
                    
                    <div class="d-flex justify-content-between align-items-center">  
                        <a href="#" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalConfirmacao">  
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> Desativar Minha Conta  
                        </a>  
                        <a href="index.php" class="btn btn-secondary">Voltar ao Dashboard</a>  
                    </div>  
                </div>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- Modal de Confirmação para Desativar Conta -->  
<div class="modal fade" id="modalConfirmacao" tabindex="-1" aria-labelledby="modalConfirmacaoLabel" aria-hidden="true">  
    <div class="modal-dialog">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="modalConfirmacaoLabel">Confirmar Desativação</h5>  
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>  
            </div>  
            <div class="modal-body">  
                <div class="alert alert-danger">  
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>  
                    <strong>Atenção!</strong> Esta ação não pode ser desfeita.  
                </div>  
                <p>Ao desativar sua conta:</p>  
                <ul>  
                    <li>Você perderá acesso ao sistema</li>  
                    <li>Seus dados pessoais serão mantidos por 30 dias e depois excluídos</li>  
                    <li>Seus selos e documentos ficarão inativos</li>  
                </ul>  
                <p>Tem certeza que deseja continuar?</p>  
            </div>  
            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>  
                <a href="desativar_conta.php" class="btn btn-danger">Sim, desativar minha conta</a>  
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
        // Script para verificar força da senha  
        const novaSenhaInput = document.getElementById('nova_senha');  
        const confirmarSenhaInput = document.getElementById('confirmar_senha');  
        const forcaSenhaBar = document.getElementById('forca-senha');  
        
        if (novaSenhaInput) {  
            novaSenhaInput.addEventListener('input', function() {  
                const senha = this.value;  
                const forca = verificarForcaSenha(senha);  
                
                if (forca === 'fraca') {  
                    forcaSenhaBar.style.width = '33%';  
                    forcaSenhaBar.className = 'progress-bar bg-danger';  
                    forcaSenhaBar.textContent = 'Fraca';  
                } else if (forca === 'media') {  
                    forcaSenhaBar.style.width = '66%';  
                    forcaSenhaBar.className = 'progress-bar bg-warning';  
                    forcaSenhaBar.textContent = 'Média';  
                } else {  
                    forcaSenhaBar.style.width = '100%';  
                    forcaSenhaBar.className = 'progress-bar bg-success';  
                    forcaSenhaBar.textContent = 'Forte';  
                }  
            });  
        }  
        
        if (confirmarSenhaInput && novaSenhaInput) {  
            confirmarSenhaInput.addEventListener('input', function() {  
                if (this.value === novaSenhaInput.value) {  
                    this.classList.remove('is-invalid');  
                    this.classList.add('is-valid');  
                } else {  
                    this.classList.remove('is-valid');  
                    this.classList.add('is-invalid');  
                }  
            });  
        }  
        
        // Script para preenchimento automático do endereço via CEP  
        const cepInput = document.getElementById('cep');  
        if (cepInput) {  
            cepInput.addEventListener('blur', function() {  
                const cep = this.value.replace(/\D/g, '');  
                
                if (cep.length === 8) {  
                    fetch(`https://viacep.com.br/ws/${cep}/json/`)  
                        .then(response => response.json())  
                        .then(data => {  
                            if (!data.erro) {  
                                document.getElementById('endereco').value = `${data.logradouro}, ${data.bairro}`;  
                                document.getElementById('cidade').value = data.localidade;  
                                document.getElementById('estado').value = data.uf;  
                            }  
                        })  
                        .catch(error => console.error('Erro:', error));  
                }  
            });  
        }  
        
        // Função para verificar força da senha  
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
    });  
</script>  

<?php include 'includes/footer.php'; ?>