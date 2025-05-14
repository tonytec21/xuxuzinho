<?php  
date_default_timezone_set('America/Sao_Paulo');  
require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  
require_once 'includes/functions.php';  

/* ------------------------------------------------------------------  
   0. Variáveis de sessão  
------------------------------------------------------------------*/  
$usuario_id   = $_SESSION['usuario_id'];  
$usuario_nome = $_SESSION['nome'] ?? 'Usuário';  

/* ------------------------------------------------------------------  
   1. Detectar modo edição e buscar registro + anexos  
------------------------------------------------------------------*/  
$modo_edicao    = false;  
$registro_atual = null;  
$anexos         = [];  

if (isset($_GET['id']) && is_numeric($_GET['id'])) {  
    $stmt = $pdo->prepare("SELECT * FROM triagem_registros WHERE id = ?");  
    $stmt->execute([$_GET['id']]);  
    $registro_atual = $stmt->fetch();  

    if ($registro_atual) {  
        $modo_edicao = true;  
        $stmt = $pdo->prepare("  
            SELECT * FROM triagem_anexos  
            WHERE registro_id = ? AND status = 'ativo'  
            ORDER BY data_upload DESC  
        ");  
        $stmt->execute([$registro_atual['id']]);  
        $anexos = $stmt->fetchAll();  
    }  
}  

/* ------------------------------------------------------------------  
   2. Protocolo automático para novo cadastro  
------------------------------------------------------------------*/  
$proximo   = $pdo->query("SELECT MAX(id)+1 FROM triagem_registros")->fetchColumn() ?? 1;  
$protocolo = 'TRG-' . str_pad($proximo, 6, '0', STR_PAD_LEFT);  

/* ------------------------------------------------------------------  
   3. Buscar registros para a lista  
------------------------------------------------------------------*/  
$registros = $pdo->query("SELECT * FROM triagem_registros ORDER BY data_cadastro DESC")->fetchAll();  

/* helper ------------------------------------------------------------------*/
function maskCpf($raw){
    $cpf = preg_replace('/\D/','',$raw??'');
    return strlen($cpf)===11 ? preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/','$1.$2.$3-$4',$cpf) : $raw;
}

include 'includes/header.php';  
?>  
<?php include(__DIR__ . '/css/style-triagem.php');?>  

<!-- SweetAlert2 – feedback (via query-string) -->  
<?php if (isset($_GET['success']) || isset($_GET['error'])): ?>  
<script>  
document.addEventListener('DOMContentLoaded',()=>{  
<?php if (isset($_GET['success'])): ?>  
    Swal.fire({  
        icon:'success',  
        title:'Sucesso!',  
        text:'<?= htmlspecialchars($_GET["success"]) ?>',  
        timer:3000,  
        showConfirmButton:false,  
        backdrop: `rgba(0,0,0,0.4)`,  
        customClass: {  
            popup: 'animate__animated animate__fadeInDown'  
        }  
    });  
<?php else: ?>  
    Swal.fire({  
        icon:'error',  
        title:'Erro',  
        text:'<?= htmlspecialchars($_GET["error"]) ?>',  
        confirmButtonColor: '#e74a3b'  
    });  
<?php endif; ?>  
});  
</script>  
<?php endif; ?>  

<!-- Modal para busca de cidades -->  
<div class="modal fade" id="cidadeModal" tabindex="-1" aria-labelledby="cidadeModalLabel" aria-hidden="true">  
  <div class="modal-dialog modal-lg">  
    <div class="modal-content">  
      <div class="modal-header">  
        <h5 class="modal-title" id="cidadeModalLabel">  
            <i data-feather="map-pin" class="me-2 text-primary"></i>  
            Buscar Cidade  
        </h5>  
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>  
      </div>  
      <div class="modal-body">  
        <div class="mb-4">  
            <div class="input-group">  
                <span class="input-group-text"><i data-feather="search"></i></span>  
                <input type="text" class="form-control" id="cidadeBusca" placeholder="Digite o nome da cidade...">  
                <button class="btn btn-primary" id="btnBuscarCidades">Buscar</button>  
            </div>  
            <div class="form-text">Digite ao menos 3 letras para buscar cidades</div>  
        </div>  
        
        <div id="cidadeLoading" class="text-center my-4 d-none">  
            <div class="spinner-border text-primary" role="status">  
                <span class="visually-hidden">Carregando...</span>  
            </div>  
            <p class="mt-2">Consultando cidades...</p>  
        </div>  
        
        <div id="cidadeResults" class="city-search-results mt-3">  
            <div class="text-center text-muted py-4" id="emptyMessage">  
                <i data-feather="map" style="width:48px;height:48px;opacity:0.3"></i>  
                <p class="mt-3">Busque por cidades utilizando o campo acima</p>  
            </div>  
            <div id="resultList" class="d-none"></div>  
        </div>  
      </div>  
      <div class="modal-footer">  
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>  
      </div>  
    </div>  
  </div>  
</div>  

<div class="container-fluid py-4 animate-fadeIn">  
    <!-- Cabeçalho aprimorado -->  
    <div class="row mb-4">  
        <div class="col-12 d-flex flex-wrap justify-content-between align-items-center">  
            <div class="mb-3 mb-md-0">  
                <h1 class="fw-bold text-gray-800">  
                    <i data-feather="clipboard" class="me-2 text-primary"></i>  
                    <?= $modo_edicao ? 'Gerenciar Triagem' : 'Triagem - Registre-se!' ?>  
                </h1>  
                <p class="text-muted lead fs-6">  
                    <?= $modo_edicao ? 'Adicione documentos ao protocolo selecionado' : 'Cadastre e gerencie os pedidos de certidão' ?>  
                </p>  
            </div>  
            <?php if ($modo_edicao): ?>  
            <a href="triagem.php" class="btn btn-outline-secondary">  
                <i data-feather="arrow-left" class="me-1"></i> Voltar  
            </a>  
            <?php endif; ?>  
        </div>  
    </div>  

<?php /* ============================================================  
   FORMULÁRIO – NOVO CADASTRO (MELHORADO)  
================================================================ */ ?>  
<?php if (!$modo_edicao): ?>  
    <div class="card mb-4 shadow-sm animate-fadeIn">  
        <div class="card-header bg-white">  
            <h5 class="mb-0">  
                <i data-feather="user-plus" class="me-2 text-primary"></i>  
                Novo Cadastro de Solicitante  
            </h5>  
        </div>  
        <form method="POST" action="salvar_triagem.php" id="formTriagem">  
            <div class="card-body row g-3">  
                <!-- Seção 1: Dados do Requerente -->  
                <div class="col-12 mb-2">  
                    <h6 class="text-primary mb-3 border-bottom pb-2">  
                        <i data-feather="user" class="me-2"></i>Dados do Requerente  
                    </h6>  
                </div>  
                
                <div class="col-md-6">  
                    <label class="form-label">Nome do Requerente</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="user" style="width:18px;height:18px"></i></span>  
                        <input type="text" class="form-control" name="nome_requerente" required placeholder="Nome completo do requerente">  
                    </div>  
                </div>  
                <div class="col-md-3">  
                    <label class="form-label">Documento (RG/CNH)</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="credit-card" style="width:18px;height:18px"></i></span>  
                        <input type="text" class="form-control" name="documento_identificacao" placeholder="Nº do documento">  
                    </div>  
                </div>  
                <div class="col-md-3">  
                    <label class="form-label">CPF</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="hash" style="width:18px;height:18px"></i></span>  
                        <input type="text" class="form-control" name="cpf" placeholder="000.000.000-00" id="cpfInput">  
                    </div>  
                </div>  

                <!-- Seção 2: Dados da Certidão -->  
                <div class="col-12 mt-4 mb-2">  
                    <h6 class="text-primary mb-3 border-bottom pb-2">  
                        <i data-feather="file-text" class="me-2"></i>Dados da Certidão  
                    </h6>  
                </div>  

                <div class="col-md-3">  
                    <label class="form-label">Tipo de Certidão</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="file" style="width:18px;height:18px"></i></span>  
                        <select class="form-select" name="tipo_certidao" id="tipoCertidao" required>  
                            <option value="">Selecione</option>  
                            <option value="nascimento">Nascimento</option>  
                            <option value="casamento">Casamento</option>  
                        </select>  
                    </div>  
                </div>  
                <div class="col-md-3">  
                    <label class="form-label">Cartório</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="home" style="width:18px;height:18px"></i></span>  
                        <input type="text" class="form-control" name="serventia_nome" placeholder="Nome do cartório">  
                    </div>  
                </div>  
                <div class="col-md-4">  
                    <label class="form-label">Cidade</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="map-pin" style="width:18px;height:18px"></i></span>  
                        <input type="text" class="form-control" id="cidadeInput" name="serventia_cidade" placeholder="Cidade do cartório" readonly>  
                        <button class="btn btn-outline-secondary" type="button" id="btnOpenCityModal">  
                            <i data-feather="search"></i>  
                        </button>  
                    </div>  
                </div>  
                <div class="col-md-2">  
                    <label class="form-label">UF</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="map" style="width:18px;height:18px"></i></span>  
                        <input type="text" maxlength="2" class="form-control" id="ufInput" name="serventia_uf" placeholder="SP" readonly>  
                    </div>  
                </div>  

                <!-- Seção 3: Dados do Registro (inicialmente oculto) -->  
                <div class="col-12 mt-4 mb-2 section-fade hidden" id="dadosRegistroSection">  
                    <h6 class="text-primary mb-3 border-bottom pb-2">  
                        <i data-feather="bookmark" class="me-2"></i>Dados do Registro  
                    </h6>  
                
                    <div class="row g-3">  
                        <div class="col-md-4">  
                            <label class="form-label">Nome do Registrado</label>  
                            <div class="input-group">  
                                <span class="input-group-text"><i data-feather="user" style="width:18px;height:18px"></i></span>  
                                <input type="text" class="form-control" name="nome_registrado" placeholder="Nome da pessoa registrada">  
                            </div>  
                        </div>  
                        <div class="col-md-3">  
                            <label class="form-label"><span id="labelEvento">Nascimento/Casamento</span></label>  
                            <div class="input-group">  
                                <span class="input-group-text"><i data-feather="calendar" style="width:18px;height:18px"></i></span>  
                                <input type="date" class="form-control" name="data_evento">  
                            </div>  
                        </div>  
                        <div class="col-md-5">  
                            <label class="form-label" id="labelFiliacaoConjuge">Filiação / Cônjuge</label>  
                            <div class="input-group">  
                                <span class="input-group-text"><i data-feather="users" style="width:18px;height:18px"></i></span>  
                                <input type="text" class="form-control" name="filiacao_conjuge" placeholder="Nome dos pais ou cônjuge">  
                            </div>  
                        </div>  

                        <div class="col-md-2">  
                            <label class="form-label">Livro</label>  
                            <div class="input-group">  
                                <span class="input-group-text"><i data-feather="book" style="width:18px;height:18px"></i></span>  
                                <input type="text" class="form-control" name="livro" placeholder="Ex: A-123">  
                            </div>  
                        </div>  
                        <div class="col-md-2">  
                            <label class="form-label">Folha</label>  
                            <div class="input-group">  
                                <span class="input-group-text"><i data-feather="file" style="width:18px;height:18px"></i></span>  
                                <input type="text" class="form-control" name="folha" placeholder="Ex: 045">  
                            </div>  
                        </div>  
                        <div class="col-md-2">  
                            <label class="form-label">Termo</label>  
                            <div class="input-group">  
                                <span class="input-group-text"><i data-feather="hash" style="width:18px;height:18px"></i></span>  
                                <input type="text" class="form-control" name="termo" placeholder="Ex: 12345">  
                            </div>  
                        </div>  
                    </div>  
                </div>  

                <div class="col-12 d-flex justify-content-end mt-4">  
                    <button type="reset" class="btn btn-outline-secondary me-2">  
                        <i data-feather="refresh-cw" class="me-1"></i> Limpar  
                    </button>  
                    <button type="submit" class="btn btn-primary">  
                        <i data-feather="save" class="me-1"></i> Cadastrar  
                    </button>  
                </div>  
            </div>  
        </form>  
    </div>  
<?php endif; ?>  

<?php /* ============================================================  
   MODO EDIÇÃO – DETALHES + ANEXOS (MELHORADO)  
================================================================ */ ?>  
<?php if ($modo_edicao && $registro_atual): ?>  
    <!-- Detalhes -->  
    <div class="card border-0 shadow-sm mb-4 animate-fadeIn">  
        <div class="card-header bg-white d-flex justify-content-between align-items-center">  
            <h5 class="mb-0">  
                <i data-feather="info" class="me-2 text-primary"></i>  
                Detalhes do Registro  
            </h5>  
            <div class="d-flex gap-2 flex-wrap">  
                <a href="editar_triagem.php?id=<?= $registro_atual['id'] ?>" class="btn btn-outline-warning">  
                    <i data-feather="edit-3" class="me-1"></i> Editar Cadastro  
                </a>  
                <!-- <?php if ($anexos): ?>  
                <a href="baixar_documento_triagem.php?id=<?= $registro_atual['id'] ?>" class="btn btn-outline-success">  
                    <i data-feather="download" class="me-1"></i> Documento Comprobatório  
                </a>  
                <?php endif; ?>   -->
            </div>  
        </div>  

        <div class="card-body">  
            <!-- Status Badge grande no topo -->  
            <div class="text-center mb-4">  
                <?php  
                $statusClass = match($registro_atual['status']) {  
                    'pendente'  => 'border-warning text-warning',  
                    'aprovado'  => 'border-info text-info',  
                    'emitido'   => 'border-success text-success',
                    'entregue' => 'border-primary text-primary',  
                    'rejeitado' => 'border-danger text-danger',  
                    default     => 'border-secondary text-secondary'  
                };  
                
                $statusBg = match($registro_atual['status']) {  
                    'pendente'  => 'bg-warning-subtle',  
                    'aprovado'  => 'bg-info-subtle',  
                    'emitido'   => 'bg-success-subtle', 
                    'entregue' => 'bg-primary-subtle', 
                    'rejeitado' => 'bg-danger-subtle',  
                    default     => 'bg-secondary-subtle'  
                };  
                
                $statusIcon = match($registro_atual['status']) {  
                    'pendente'  => 'clock',  
                    'aprovado'  => 'check',  
                    'emitido'   => 'check-circle', 
                    'entregue' => 'check-square', 
                    'rejeitado' => 'x-circle',  
                    default     => 'help-circle'  
                };  
                ?>  
                <div class="card border <?= $statusClass ?> shadow-sm mb-4">  
                    <div class="card-body d-flex align-items-center justify-content-center <?= $statusBg ?> py-3">  
                        <div class="d-flex align-items-center">  
                            <div class="rounded-circle <?= str_replace('text', 'bg', $statusClass) ?> p-2 me-3 d-flex justify-content-center align-items-center" style="width: 48px; height: 48px;">  
                                <i data-feather="<?= $statusIcon ?>" class="text-white" style="width: 24px; height: 24px;"></i>  
                            </div>  
                            <div class="text-start">  
                                <div class="small text-muted">Status do Protocolo</div>  
                                <div class="fs-5 fw-bold <?= $statusClass ?>">  
                                    <?= ucfirst($registro_atual['status']) ?>  
                                </div>  
                            </div>  
                        </div>  
                    </div>  
                </div>  
            </div>
            
            <div class="row g-3">
                <!-- Seção 1: Informações do Protocolo -->
                <div class="col-12 mb-2">
                    <h6 class="text-primary mb-3 border-bottom pb-2">
                        <i data-feather="file-text" class="me-2"></i>Informações do Protocolo
                    </h6>
                </div>

                <?php if (!empty($registro_atual['protocolo'])): ?>
                    <div class="col-md-4">
                        <label class="form-label text-muted">Protocolo</label>
                        <div class="form-control bg-light fw-bold"><?= htmlspecialchars($registro_atual['protocolo']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($registro_atual['tipo_certidao'])): ?>
                    <div class="col-md-4">
                        <label class="form-label text-muted">Tipo de Certidão</label>
                        <div class="form-control bg-light text-capitalize"><?= htmlspecialchars($registro_atual['tipo_certidao']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($registro_atual['data_cadastro'])): ?>
                    <div class="col-md-4">
                        <label class="form-label text-muted">Data de Cadastro</label>
                        <div class="form-control bg-light"><?= date('d/m/Y', strtotime($registro_atual['data_cadastro'])) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Seção 2: Dados do Requerente -->
                <div class="col-12 mt-4 mb-2">
                    <h6 class="text-primary mb-3 border-bottom pb-2">
                        <i data-feather="user" class="me-2"></i>Dados do Requerente
                    </h6>
                </div>

                <?php if (!empty($registro_atual['nome_requerente'])): ?>
                    <div class="col-md-6">
                        <label class="form-label text-muted">Nome do Requerente</label>
                        <div class="form-control bg-light"><?= htmlspecialchars($registro_atual['nome_requerente']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($registro_atual['documento_identificacao'])): ?>
                    <div class="col-md-3">
                        <label class="form-label text-muted">Documento (RG/CNH)</label>
                        <div class="form-control bg-light"><?= htmlspecialchars($registro_atual['documento_identificacao']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($registro_atual['cpf'])): ?>
                    <div class="col-md-3">
                        <label class="form-label text-muted">CPF</label>
                        <div class="form-control bg-light"><?= maskCpf($registro_atual['cpf']) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Seção 3: Dados do Cartório -->
                <div class="col-12 mt-4 mb-2">
                    <h6 class="text-primary mb-3 border-bottom pb-2">
                        <i data-feather="home" class="me-2"></i>Dados do Cartório
                    </h6>
                </div>

                <?php if (!empty($registro_atual['serventia_nome'])): ?>
                    <div class="col-md-5">
                        <label class="form-label text-muted">Cartório</label>
                        <div class="form-control bg-light"><?= htmlspecialchars($registro_atual['serventia_nome']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($registro_atual['serventia_cidade'])): ?>
                    <div class="col-md-5">
                        <label class="form-label text-muted">Cidade</label>
                        <div class="form-control bg-light"><?= htmlspecialchars($registro_atual['serventia_cidade']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($registro_atual['serventia_uf'])): ?>
                    <div class="col-md-2">
                        <label class="form-label text-muted">UF</label>
                        <div class="form-control bg-light"><?= htmlspecialchars($registro_atual['serventia_uf']) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Seção 4: Dados do Registro -->
                <div class="col-12 mt-4 mb-2">
                    <h6 class="text-primary mb-3 border-bottom pb-2">
                        <i data-feather="bookmark" class="me-2"></i>Dados do Registro
                    </h6>
                </div>

                <?php if (!empty($registro_atual['nome_registrado'])): ?>
                    <div class="col-md-6">
                        <label class="form-label text-muted">Nome do Registrado</label>
                        <div class="form-control bg-light"><?= htmlspecialchars($registro_atual['nome_registrado']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($registro_atual['data_evento'])): ?>  
                    <div class="col-md-3">  
                        <label class="form-label text-muted">  
                            <?php   
                            if ($registro_atual['tipo_certidao'] == 'nascimento') {  
                                echo "Data de Nascimento";  
                            } elseif ($registro_atual['tipo_certidao'] == 'casamento') {  
                                echo "Data de Casamento";  
                            } else {  
                                echo "Data do Evento";  
                            }  
                            ?>  
                        </label>  
                        <div class="form-control bg-light"><?= date('d/m/Y', strtotime($registro_atual['data_evento'])) ?></div>  
                    </div>  
                <?php endif; ?>

                <?php if (!empty($registro_atual['filiacao_conjuge'])): ?>
                    <div class="col-md-9">
                        <label class="form-label text-muted">
                            <?= $registro_atual['tipo_certidao'] === 'nascimento' ? 'Filiação' : 'Cônjuge' ?>
                        </label>
                        <div class="form-control bg-light"><?= htmlspecialchars($registro_atual['filiacao_conjuge']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($registro_atual['livro'])): ?>
                    <div class="col-md-2">
                        <label class="form-label text-muted">Livro</label>
                        <div class="form-control bg-light"><?= htmlspecialchars($registro_atual['livro']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($registro_atual['folha'])): ?>
                    <div class="col-md-2">
                        <label class="form-label text-muted">Folha</label>
                        <div class="form-control bg-light"><?= htmlspecialchars($registro_atual['folha']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($registro_atual['termo'])): ?>
                    <div class="col-md-2">
                        <label class="form-label text-muted">Termo</label>
                        <div class="form-control bg-light"><?= htmlspecialchars($registro_atual['termo']) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Ações -->
                <div class="col-12 mt-4 text-center">
                    <div class="card shadow-sm border-0 mb-4">  
                        <div class="card-header bg-white">  
                            <h6 class="mb-0 fw-bold">  
                                <i data-feather="activity" class="text-primary me-2" style="width: 18px; height: 18px;"></i>  
                                Ações Disponíveis  
                            </h6>  
                        </div>  
                        <div class="card-body p-4">  
                            <?php if ($registro_atual['status'] === 'pendente'): ?>  
                                <div class="d-grid gap-3">  
                                    <button class="btn btn-success btn-lg btn-aprovar d-flex align-items-center justify-content-center" data-id="<?= $registro_atual['id'] ?>">  
                                        <div class="rounded-circle bg-white p-1 me-2">  
                                            <i data-feather="check" class="text-success" style="width: 20px; height: 20px;"></i>  
                                        </div>  
                                        <span>Aprovar Solicitação</span>  
                                    </button>  
                                    <button class="btn btn-danger btn-lg btn-rejeitar d-flex align-items-center justify-content-center" data-id="<?= $registro_atual['id'] ?>">  
                                        <div class="rounded-circle border border-danger p-1 me-2">  
                                            <i data-feather="x" class="text-danger" style="width: 20px; height: 20px;"></i>  
                                        </div>  
                                        <span>Rejeitar Solicitação</span>  
                                    </button>  
                                </div>  

                            <?php elseif ($registro_atual['status'] === 'aprovado'): ?>  
                                <div class="d-grid">  
                                    <button class="btn btn-success btn-lg btn-emitir-certidao d-flex align-items-center justify-content-center" data-id="<?= $registro_atual['id'] ?>">  
                                        <div class="rounded-circle bg-white p-1 me-2">  
                                            <i data-feather="check-circle" class="text-success" style="width: 20px; height: 20px;"></i>  
                                        </div>  
                                        <span>Certidão Emitida</span>  
                                    </button>  
                                </div>  

                            <?php elseif ($registro_atual['status'] === 'emitido'): ?>  
                                <div class="d-grid gap-3">  
                                    <!-- Status header - Centralizado -->  
                                    <div class="text-center mb-3">  
                                        <div class="d-inline-flex align-items-center justify-content-center">  
                                            <div class="rounded-circle bg-success p-2 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">  
                                                <i data-feather="check-circle" class="text-white" style="width: 24px; height: 24px;"></i>  
                                            </div>  
                                            <div class="fs-5 fw-bold text-success">Certidão Emitida</div>  
                                        </div>  
                                    </div>  
                                    
                                    <!-- Número do selo com botão de cópia - Responsivo -->  
                                    <?php if (!empty($registro_atual['numero_selo'])): ?>  
                                        <div class="mb-3">  
                                            <label class="form-label text-muted mb-1">Selo da Certidão</label>  
                                            <div class="input-group">  
                                                <input type="text" class="form-control bg-light border-end-0"   
                                                    id="numeroSelo" value="<?= htmlspecialchars($registro_atual['numero_selo']) ?>"   
                                                    readonly style="font-family: monospace;">  
                                                <button class="btn bg-light border border-start-0" type="button" id="btnCopiarSelo"   
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Copiar">  
                                                    <i data-feather="copy" style="width: 18px; height: 18px;" class="text-secondary"></i>  
                                                </button>  
                                            </div>  
                                        </div>  
                                    <?php endif; ?>  
                            
                                    <!-- Botão marcar como entregue -->  
                                    <button class="btn btn-primary btn-lg btn-marcar-entregue d-flex align-items-center justify-content-center"   
                                            data-id="<?= $registro_atual['id'] ?>">  
                                        <div class="rounded-circle bg-white p-1 me-2">  
                                            <i data-feather="check-square" class="text-primary" style="width: 20px; height: 20px;"></i>  
                                        </div>  
                                        <span>Marcar como Entregue</span>  
                                    </button>  
                                </div> 

                            <?php elseif ($registro_atual['status'] === 'emitido'): ?>  
                                <div class="py-3 d-flex align-items-center justify-content-center">  
                                    <div class="rounded-circle bg-success p-2 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">  
                                        <i data-feather="check-circle" class="text-white" style="width: 24px; height: 24px;"></i>  
                                    </div>  
                                    <div class="fs-5 fw-bold text-success">Certidão Emitida</div>  
                                </div>  

                            <?php elseif ($registro_atual['status'] === 'rejeitado'): ?>  
                                <div class="py-3 d-flex align-items-center justify-content-center">  
                                    <div class="rounded-circle bg-danger p-2 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">  
                                        <i data-feather="x-circle" class="text-white" style="width: 24px; height: 24px;"></i>  
                                    </div>  
                                    <div class="fs-5 fw-bold text-danger">Solicitação Rejeitada</div>  
                                </div>  

                                <div class="d-grid mt-3">
                                    <button class="btn btn-warning btn-lg btn-reavaliar d-flex align-items-center justify-content-center" data-id="<?= $registro_atual['id'] ?>">
                                        <div class="rounded-circle bg-white p-1 me-2">
                                            <i data-feather="refresh-cw" class="text-warning" style="width: 20px; height: 20px;"></i>
                                        </div>
                                        <span>Reavaliar Solicitação</span>
                                    </button>
                                </div>

                                <?php if (!empty($registro_atual['motivo_rejeicao'])): ?>  
                                    <div class="mt-4 card border-danger">  
                                        <div class="card-body bg-danger-subtle">  
                                            <div class="d-flex align-items-start">  
                                                <i data-feather="alert-circle" class="text-danger me-3" style="width: 20px; height: 20px;"></i>  
                                                <div>  
                                                    <div class="fw-bold text-danger mb-1">Motivo da rejeição:</div>  
                                                    <div><?= htmlspecialchars($registro_atual['motivo_rejeicao']) ?></div>  
                                                </div>  
                                            </div>  
                                        </div>  
                                    </div>  
                                <?php endif; ?>  

                            <?php endif; ?>  
                        </div>  
                    </div>
                </div>
            </div>
  
        </div>  
    </div>  

    <!-- Upload de anexos -->  
    <div class="card border-0 shadow-sm mb-4 animate-fadeIn">  
        <div class="card-header bg-white">  
            <div class="row align-items-center">  
                <div class="col-md-4 mb-2 mb-md-0">  
                    <h5 class="mb-0">  
                        <i data-feather="paperclip" class="me-2 text-primary"></i>  
                        Anexos do Registro  
                    </h5>  
                </div>  
                <div class="col-md-8">  
                    <div class="d-flex flex-wrap gap-2 justify-content-md-end">  
                        <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#uploadCollapse">  
                            <i data-feather="upload" class="me-1"></i>  
                            <span class="d-none d-sm-inline">Adicionar Anexos</span>  
                            <span class="d-inline d-sm-none">Adicionar</span>  
                        </button>  
                        <?php if ($anexos): ?>  
                            <a href="baixar_documento_triagem.php?id=<?= $registro_atual['id'] ?>" class="btn btn-outline-success">  
                                <i data-feather="download" class="me-1"></i>  
                                <span class="d-none d-sm-inline">Documento Comprobatório</span>  
                                <span class="d-inline d-sm-none">Documento</span>  
                            </a>  
                        <?php endif; ?>  
                    </div>  
                </div>  
            </div>  
        </div> 

        <!-- área de upload -->  
        <div class="collapse" id="uploadCollapse">  
            <div class="card-body border-bottom">  
                <form id="uploadForm" action="upload_triagem.php" method="POST" enctype="multipart/form-data">  
                    <input type="hidden" name="registro_id" value="<?= $registro_atual['id'] ?>">  
                    <div class="upload-area mb-3">  
                        <div class="dropzone-container" id="dropzoneUpload">  
                            <div class="dz-message text-center">  
                                <i data-feather="upload-cloud" style="width:64px;height:64px;color:#6c757d;"></i>  
                                <h5 class="mt-3">Arraste e solte arquivos aqui</h5>  
                                <p class="text-muted">ou clique no botão abaixo</p>  
                                <button type="button" class="btn btn-primary browse-btn">  
                                    <i data-feather="folder" class="me-2"></i> Selecionar Arquivos  
                                </button>  
                                <p class="mt-3 small text-muted">  
                                    <span class="badge bg-light text-dark me-1">PDF</span>  
                                    <span class="badge bg-light text-dark me-1">JPG</span>  
                                    <span class="badge bg-light text-dark me-1">JPEG</span>  
                                    <span class="badge bg-light text-dark">PNG</span>  
                                    – máx. 10 MB cada  
                                </p>  
                            </div>  
                        </div>  
                    </div>  

                    <div id="preview-container" class="mb-3 d-none">  
                        <h6 class="mb-2">  
                            <i data-feather="file-text" class="me-2"></i>  
                            Arquivos Selecionados  
                        </h6>  
                        <div id="file-preview-list" class="file-preview-list"></div>  
                    </div>  

                    <div id="progressContainer" class="mt-3 d-none">  
                        <label class="form-label"><i data-feather="loader" class="me-2"></i>Progresso do Upload</label>  
                        <div class="progress" style="height:20px">  
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div>  
                        </div>  
                        <p id="uploadStatus" class="mt-2 small text-muted"></p>  
                    </div>  

                    <div class="d-flex justify-content-end mt-3">  
                        <button type="button" class="btn btn-outline-secondary me-2" data-bs-toggle="collapse" data-bs-target="#uploadCollapse">  
                            <i data-feather="x" class="me-1"></i> Cancelar  
                        </button>  
                        <button type="submit" id="submitUpload" class="btn btn-primary upload-btn" disabled>  
                            <i data-feather="upload" class="me-1"></i> Enviar Arquivos  
                        </button>  
                    </div>  
                </form>  
            </div>  
        </div>  

        <!-- lista de anexos -->  
        <div class="card-body">  
<?php if ($anexos): ?>  
            <div class="table-responsive">  
                <table class="table table-hover align-middle">  
                    <thead class="table-light">  
                        <tr>  
                            <th><i data-feather="file" class="me-2" style="width:16px;height:16px"></i>Arquivo</th>  
                            <th><i data-feather="file-text" class="me-2" style="width:16px;height:16px"></i>Tipo</th>  
                            <th><i data-feather="hard-drive" class="me-2" style="width:16px;height:16px"></i>Tamanho</th>  
                            <th><i data-feather="calendar" class="me-2" style="width:16px;height:16px"></i>Enviado em</th>  
                            <th class="text-center"><i data-feather="settings" class="me-2" style="width:16px;height:16px"></i>Ações</th>  
                        </tr>  
                    </thead>  
                    <tbody>  
<?php foreach ($anexos as $ax): ?>  
                        <tr>  
                            <td>  
                                <div class="d-flex align-items-center">  
                                    <?php   
                                    $ext = strtolower(pathinfo($ax['nome_arquivo'], PATHINFO_EXTENSION));  
                                    $iconMap = [  
                                        'pdf' => 'file-text',  
                                        'jpg' => 'image',  
                                        'jpeg' => 'image',  
                                        'png' => 'image'  
                                    ];  
                                    $icon = $iconMap[$ext] ?? 'file';  
                                    ?>  
                                    <i data-feather="<?= $icon ?>" class="me-2 text-muted"></i>  
                                    <span class="text-truncate" style="max-width:300px;"><?= htmlspecialchars($ax['nome_arquivo']) ?></span>  
                                </div>  
                            </td>  
                            <td><span class="badge bg-light text-dark"><?= strtoupper(pathinfo($ax['nome_arquivo'], PATHINFO_EXTENSION)) ?></span></td>  
                            <td><?= number_format($ax['tamanho']/1024,2) ?> KB</td>  
                            <td><?= date('d/m/Y H:i',strtotime($ax['data_upload'])) ?></td>  
                            <td class="text-center">  
                                <a href="<?= $ax['caminho'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Visualizar">  
                                    <i data-feather="eye"></i>  
                                </a>  
                                <!-- <a href="download_anexo.php?id=<?= $ax['id'] ?>" class="btn btn-sm btn-outline-success" title="Download">  
                                    <i data-feather="download"></i>  
                                </a>   -->
                                <a href="javascript:void(0)" onclick="confirmarExclusao(<?= $ax['id'] ?>)" class="btn btn-sm btn-outline-danger" title="Excluir">  
                                    <i data-feather="trash-2"></i>  
                                </a>  
                            </td>  
                        </tr>  
<?php endforeach; ?>  
                    </tbody>  
                </table>  
            </div>  
<?php else: ?>  
            <div class="text-center py-5 text-muted">  
                <i data-feather="file-text" style="width:64px;height:64px;opacity:.3;"></i>  
                <p class="mt-3 lead">Nenhum anexo disponível.</p>  
                <p>Clique no botão "Adicionar Anexos" para fazer upload de documentos.</p>  
            </div>  
<?php endif; ?>  
        </div>  
    </div>  
<?php endif; ?>  

<?php /* ============================================================  
   LISTA GERAL – quando não em modo edição (MELHORADA)  
================================================================ */ ?>  
<?php if (!$modo_edicao): ?>  
    <div class="card shadow-sm animate-fadeIn">  
        <div class="card-header bg-white d-flex justify-content-between align-items-center">  
            <h5 class="mb-0">  
                <i data-feather="list" class="me-2 text-primary"></i>  
                Registros Triados  
            </h5>  
            
            <!-- Adicionar filtro e busca -->  
            <div class="d-flex gap-2">  
                <div class="input-group">  
                    <input type="text" id="searchInput" class="form-control" placeholder="Buscar...">  
                    <button class="btn btn-outline-secondary" type="button" id="searchButton">  
                        <i data-feather="search"></i>  
                    </button>  
                </div>  
            </div>  
        </div>  
        <div class="card-body">  
<?php if ($registros): ?>  
            <div class="table-responsive">  
                <table class="table table-hover align-middle" id="registrosTable">  
                    <thead class="table-light">  
                        <tr>  
                            <th><i data-feather="hash" class="me-1" style="width:14px;height:14px"></i>Protocolo</th>  
                            <th><i data-feather="user" class="me-1" style="width:14px;height:14px"></i>Requerente</th>  
                            <th><i data-feather="file" class="me-1" style="width:14px;height:14px"></i>Tipo</th>  
                            <th><i data-feather="user-check" class="me-1" style="width:14px;height:14px"></i>Registrado</th>  
                            <th><i data-feather="check-circle" class="me-1" style="width:14px;height:14px"></i>Status</th>  
                            <th><i data-feather="calendar" class="me-1" style="width:14px;height:14px"></i>Data</th>  
                            <th class="text-center"><i data-feather="settings" class="me-1" style="width:14px;height:14px"></i>Ações</th>  
                        </tr>  
                    </thead>  
                    <tbody>  
<?php foreach ($registros as $r): ?>  
                        <tr>  
                            <td><?= $r['protocolo'] ?></td>  
                            <td>  
                                <?= htmlspecialchars($r['nome_requerente']) ?>  
                            </td>  
                            <td>  
                            <?php  
                            $tipoClass = strtolower($r['tipo_certidao']) === 'nascimento'   
                            ? 'badge-nascimento'   
                            : (strtolower($r['tipo_certidao']) === 'casamento'   
                                ? 'badge-casamento'   
                                : 'bg-light text-dark');  

                            echo "<span class='badge $tipoClass'>" . ucfirst($r['tipo_certidao']) . "</span>";  
                            ?>  
                            </td>  
                            <td>
                                <?= htmlspecialchars($r['nome_registrado']) ?>
                            </td>  
                            <td>  
<?php  
$statusClass = match($r['status']){  
    'pendente'  => 'bg-warning text-dark',  
    'aprovado'  => 'bg-info text-white',  
    'emitido'   => 'bg-success text-white', 
    'entregue'  => 'bg-primary text-white', 
    'rejeitado' => 'bg-danger text-white',  
    default     => 'bg-secondary'  
};  

$statusIcon = match($r['status']){  
    'pendente'  => 'clock',  
    'aprovado'  => 'check',  
    'emitido'   => 'check-circle',
    'entregue'  => 'check-square',  
    'rejeitado' => 'x-circle',  
    default     => 'help-circle'  
};  

echo "<span class='badge $statusClass'><i data-feather='$statusIcon' style='width:14px;height:14px;margin-right:4px'></i>" . ucfirst($r['status']) . "</span>";  
?>  
                            </td>  
                            <td><?= date('d/m/Y',strtotime($r['data_cadastro'])) ?></td>  
                            <td>  
                                <div class="d-flex justify-content-center gap-1">  
                                    <a href="triagem.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-primary" title="Gerenciar">  
                                        <i data-feather="edit"></i>  
                                    </a>  

                                    <a href="uploads/Declaracao_de_hipossuficiencia.pdf" class="btn btn-sm btn-info" title="Declaração de Hipossuficiência" target="_blank">  
                                        <i data-feather="file-text"></i>  
                                    </a>

                                    <?php if ($r['status'] === 'pendente'): ?>  
                                        <button class="btn btn-sm btn-success btn-aprovar" data-id="<?= $r['id'] ?>" title="Aprovar">  
                                            <i data-feather="check"></i>  
                                        </button>  
                                        <button class="btn btn-sm btn-danger btn-rejeitar" data-id="<?= $r['id'] ?>" title="Rejeitar">  
                                            <i data-feather="x"></i>  
                                        </button>  

                                    <?php elseif ($r['status'] === 'rejeitado'): ?>
                                        <button class="btn btn-sm btn-warning btn-reavaliar" data-id="<?= $r['id'] ?>" title="Reavaliar">
                                            <i data-feather="refresh-cw"></i>
                                        </button>

                                    <?php elseif ($r['status'] === 'aprovado'): ?>  
                                        <button class="btn btn-sm btn-success btn-emitir-certidao" data-id="<?= $r['id'] ?>" title="Certidão Emitida">  
                                            <i data-feather="check-circle"></i>  
                                        </button>  

                                    <?php elseif ($r['status'] === 'emitido'): ?>  
                                        <button class="btn btn-sm btn-info text-white btn-marcar-entregue" data-id="<?= $r['id'] ?>" title="Marcar como Entregue">  
                                            <i data-feather="package"></i>  
                                        </button>  
                                    <?php endif; ?>  
                                </div>  
                            </td>
                        </tr>  
<?php endforeach; ?>  
                    </tbody>  
                </table>  
            </div>  
<?php else: ?>  
            <div class="text-center py-5 text-muted">  
                <i data-feather="inbox" style="width:64px;height:64px;opacity:.3;"></i>  
                <p class="mt-3 lead">Nenhum registro encontrado.</p>  
                <p>Utilize o formulário acima para criar novos registros de triagem.</p>  
            </div>  
<?php endif; ?>  
        </div>  
    </div>  
<?php endif; ?>  
</div>

<!-- ================================================================  
   SCRIPTS MELHORADOS  
================================================================ -->  
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>  
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>  
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>  
<script src="https://unpkg.com/feather-icons"></script>  

<script>  
// Inicializar ícones  
feather.replace();  

// Máscara e validação para campos  
$(document).ready(function(){  
    // Máscara de CPF implementada manualmente sem depender do plugin  
    // Máscara e validação para todos os <input name="cpf">
    $('input[name="cpf"]').each(function(){
        $(this).on('input', aplicaMascara);
    });
    $('form').on('submit',function(e){
        const $cpf = $(this).find('input[name="cpf"]');
        if(!$cpf.length) return;
        const num = $cpf.val().replace(/\D/g,'');
        if(num && !validarCPF(num)){
            e.preventDefault();
            Swal.fire({icon:'error',title:'CPF inválido',text:'Informe um CPF válido.'});
            $cpf.addClass('is-invalid').focus();
        }
    });
    function aplicaMascara(){
        let v = $(this).val().replace(/\D/g,'').slice(0,11);
        v = v.replace(/^(\d{3})(\d)/,'$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/,'$1.$2.$3')
            .replace(/\.(\d{3})(\d)/,'.$1-$2');
        $(this).val(v);
    }
  
    // Função para validar CPF  
    function validarCPF(cpf) {  
        // Elimina CPFs inválidos conhecidos  
        if (cpf.length !== 11 ||   
            cpf === "00000000000" ||   
            cpf === "11111111111" ||   
            cpf === "22222222222" ||   
            cpf === "33333333333" ||   
            cpf === "44444444444" ||   
            cpf === "55555555555" ||   
            cpf === "66666666666" ||   
            cpf === "77777777777" ||   
            cpf === "88888888888" ||   
            cpf === "99999999999") {  
            return false;  
        }  
        
        // Valida 1º dígito  
        let add = 0;  
        for (let i = 0; i < 9; i++) {  
            add += parseInt(cpf.charAt(i)) * (10 - i);  
        }  
        let rev = 11 - (add % 11);  
        if (rev === 10 || rev === 11) {  
            rev = 0;  
        }  
        if (rev !== parseInt(cpf.charAt(9))) {  
            return false;  
        }  
        
        // Valida 2º dígito  
        add = 0;  
        for (let i = 0; i < 10; i++) {  
            add += parseInt(cpf.charAt(i)) * (11 - i);  
        }  
        rev = 11 - (add % 11);  
        if (rev === 10 || rev === 11) {  
            rev = 0;  
        }  
        if (rev !== parseInt(cpf.charAt(10))) {  
            return false;  
        }  
        
        return true;  
    }  
    
    // Inicializar tooltips  
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));  
    tooltipTriggerList.map(function (tooltipTriggerEl) {  
        return new bootstrap.Tooltip(tooltipTriggerEl);  
    });  
    
    // Filtro para tabela  
    $("#searchInput").on("keyup", function() {  
        var value = $(this).val().toLowerCase();  
        $("#registrosTable tbody tr").filter(function() {  
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)  
        });  
    });  
    
    // -------------------------------------------------------------------------  
    // Funcionalidade para campo de cidade pesquisável  
    // -------------------------------------------------------------------------  
    const cidadeModal = new bootstrap.Modal(document.getElementById('cidadeModal'), {  
        backdrop: 'static'  
    });  
    
    // Abrir modal ao clicar no campo cidade ou no botão de busca  
    $("#cidadeInput, #btnOpenCityModal").on("click", function() {  
        cidadeModal.show();  
        setTimeout(() => {  
            $("#cidadeBusca").focus();  
        }, 500);  
    });  
    
    // ---- BUSCA DINÂMICA (debounce 400 ms) ----
    let debounceTimer=null;

    $("#cidadeBusca").on("input",function(){
        clearTimeout(debounceTimer);
        const valor=$(this).val();
        debounceTimer=setTimeout(()=>buscarCidades(valor),400);
    });

    // botão “Buscar” continua funcional (caso o usuário clique)
    $("#btnBuscarCidades").on("click",function(){
        buscarCidades($("#cidadeBusca").val());
    });
  
    function removerAcentos(str){
        return (str||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'');
    }
    
    // Busca em BrasilAPI  ➜ fallback IBGE
    function buscarCidades(q){
        const termo = (q||'').trim();
        if(termo.length < 3){
            Swal.fire({icon:'warning',title:'Ops!',text:'Digite pelo menos 3 letras',confirmButtonColor:'#4e73df'});
            return;
        }

        $("#cidadeLoading").removeClass("d-none");
        $("#resultList").addClass("d-none");
        $("#emptyMessage").addClass("d-none");

        // 1ª tentativa – BrasilAPI
        fetch(`https://brasilapi.com.br/api/ibge/municipios/v1/${encodeURIComponent(termo)}`)
            .then(r => r.ok ? r.json() : Promise.reject())
            .catch(()=>{ // fallback – IBGE
                return fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/municipios?nome=${encodeURIComponent(termo)}&orderBy=nome`)
                    .then(r=>r.ok?r.json():Promise.reject());
            })
            .then(data=>{
                $("#cidadeLoading").addClass("d-none");
                if(data && data.length){
                    // normaliza ► converte em {nome, uf}
                    const listaRaw = data.map(m=>({
                        nome:m.nome,
                        uf  :m.estado ? m.estado.sigla
                                    :(m.microrregiao ? m.microrregiao.mesorregiao.UF.sigla : '')
                    }));

                    // FILTRA no cliente (insensível a acentos)
                    const termoNormal = removerAcentos(termo).toLowerCase();
                    const lista = listaRaw.filter(({nome}) =>
                        removerAcentos(nome).toLowerCase().includes(termoNormal)
                    );

                    if(lista.length){
                        renderResultados(lista,termo);
                    }else{
                        mostrarVazio(termo);
                    }
                }else{
                    mostrarVazio(termo);
                }

            })
            .catch(()=>{
                $("#cidadeLoading").addClass("d-none");
                $("#emptyMessage").html(`
                    <i data-feather="alert-triangle" style="width:48px;height:48px;opacity:0.3"></i>
                    <p class="mt-3 text-danger">Erro ao consultar serviço de municípios</p>
                    <p class="small text-muted">Tente novamente mais tarde</p>
                `).removeClass("d-none");
                feather.replace();
            });
    }
  
    
    function renderResultados(municipios, termo){
        // Ordena por nome + UF
        municipios.sort((a,b)=>{
            if(a.nome===b.nome) return a.uf.localeCompare(b.uf);
            return a.nome.localeCompare(b.nome);
        });

        let html='<div class="list-group">';
        municipios.forEach(({nome,uf})=>{
            const destaque = highlightText(nome,termo);
            html += `
                <a href="#" class="list-group-item list-group-item-action city-item"
                data-cidade="${nome}" data-uf="${uf}">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong>${destaque}</strong>
                        <span class="badge bg-light text-dark">${uf}</span>
                    </div>
                </a>`;
        });
        html+='</div>';

        $("#resultList").html(html).removeClass("d-none");

        // clique ➜ preencher campos
        $(".city-item").on("click",function(e){
            e.preventDefault();
            $("#cidadeInput").val($(this).data("cidade"));
            $("#ufInput").val($(this).data("uf"));
            cidadeModal.hide();
        });
    }

    function mostrarVazio(termo){
        $("#emptyMessage").html(`
            <i data-feather="alert-circle" style="width:48px;height:48px;opacity:0.3"></i>
            <p class="mt-3">Nenhuma cidade encontrada com "<strong>${termo}</strong>"</p>
            <p class="small text-muted">Tente outro termo</p>
        `).removeClass("d-none");
        feather.replace();
    }
 
    
    function highlightText(text, query) {  
        // Função para destacar termo de busca no texto  
        const regex = new RegExp('(' + query + ')', 'gi');  
        return text.replace(regex, '<span class="search-highlight">$1</span>');  
    }  
    
    // -------------------------------------------------------------------------  
    // Funcionalidade para campo Filiação/Cônjuge e exibição condicional  
    // -------------------------------------------------------------------------  
    const tipoCertidaoSelect = document.getElementById('tipoCertidao');  
    const dadosRegistroSection = document.getElementById('dadosRegistroSection');  
    const labelFiliacaoConjuge = document.getElementById('labelFiliacaoConjuge');  
    const labelEvento = document.getElementById('labelEvento');  
    
    if (tipoCertidaoSelect && dadosRegistroSection) {  
        tipoCertidaoSelect.addEventListener('change', function() {  
            const tipoCertidao = this.value;  
            
            if (tipoCertidao) {  
                // Mostrar seção de dados do registro  
                dadosRegistroSection.classList.remove('hidden');  
                dadosRegistroSection.classList.add('visible');  
                
                // Atualizar label do campo Filiação/Cônjuge  
                if (tipoCertidao === 'nascimento') {  
                    labelFiliacaoConjuge.textContent = 'Filiação';  
                    labelEvento.textContent = 'Data de Nascimento';  
                    document.querySelector('[name="filiacao_conjuge"]').placeholder = 'Nome dos pais';  
                } else if (tipoCertidao === 'casamento') {  
                    labelFiliacaoConjuge.textContent = 'Cônjuge';  
                    labelEvento.textContent = 'Data de Casamento';  
                    document.querySelector('[name="filiacao_conjuge"]').placeholder = 'Nome do cônjuge';  
                }  
                
                // Inicializar ícones novamente para a seção exibida  
                feather.replace();  
            } else {  
                // Esconder seção  
                dadosRegistroSection.classList.add('hidden');  
                dadosRegistroSection.classList.remove('visible');  
            }  
        });  
    }  
});  

/* ---------------- Upload drag & drop melhorado -------------- */  
document.addEventListener('DOMContentLoaded',()=>{  
    const dz=document.getElementById('dropzoneUpload');  
    const submit=document.getElementById('submitUpload');  
    if(!dz||!submit) return;  

    const fi=Object.assign(document.createElement('input'),{type:'file',multiple:true,name:'arquivos[]',accept:'.pdf,.jpg,.jpeg,.png',style:'display:none'});  
    fi.setAttribute('form','uploadForm'); document.getElementById('uploadForm')?.appendChild(fi);  
    document.querySelector('.browse-btn')?.addEventListener('click',()=>fi.click());  

    const preview=document.getElementById('preview-container');  
    const list=document.getElementById('file-preview-list');  
    const handle=files=>{  
        submit.disabled=files.length === 0;  
        preview.classList.toggle('d-none', files.length === 0);  
        
        list.innerHTML = '';  
        [...files].forEach(file => {  
            // Extensão e ícone apropriado  
            const ext = file.name.split('.').pop().toLowerCase();  
            let icon = 'file';  
            if(['jpg','jpeg','png'].includes(ext)) icon = 'image';  
            if(ext === 'pdf') icon = 'file-text';  
            
            // Tamanho formatado  
            const size = file.size < 1024*1024   
                ? (file.size/1024).toFixed(2) + ' KB'   
                : (file.size/(1024*1024)).toFixed(2) + ' MB';  
            
            // Criar elemento de preview  
            const item = document.createElement('div');  
            item.className = 'd-flex align-items-center p-2 mb-2 bg-white rounded shadow-sm';  
            item.innerHTML = `  
                <i data-feather="${icon}" class="text-primary me-3"></i>  
                <div class="flex-grow-1 text-truncate">  
                    <div class="text-truncate">${file.name}</div>  
                    <small class="text-muted">${size}</small>  
                </div>  
            `;  
            list.appendChild(item);  
        });  
        
        // Re-inicializar feather icons para os novos ícones  
        feather.replace();  
    };  

    ['dragenter','dragover','dragleave','drop'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault();e.stopPropagation();}));  
    ['dragenter','dragover'].forEach(ev=>dz.addEventListener(ev,()=>dz.classList.add('highlight')));  
    ['dragleave','drop'].forEach(ev=>dz.addEventListener(ev,()=>dz.classList.remove('highlight')));  
    dz.addEventListener('drop',e=>handle(e.dataTransfer.files));  
    fi.addEventListener('change',e=>handle(e.target.files));  

    // Configurar upload com progresso  
    document.getElementById('uploadForm').addEventListener('submit', function(e) {  
        e.preventDefault();  
        
        if (fi.files.length === 0) {  
            Swal.fire({  
                icon: 'warning',  
                title: 'Nenhum arquivo selecionado',  
                text: 'Por favor, selecione pelo menos um arquivo para enviar.',  
                confirmButtonColor: '#4e73df'  
            });  
            return;  
        }  

        // Mostrar barra de progresso  
        const progressContainer = document.getElementById('progressContainer');  
        const progressBar = document.getElementById('progressBar');  
        const uploadStatus = document.getElementById('uploadStatus');  
        progressContainer.classList.remove('d-none');  
        
        // Preparar FormData  
        const formData = new FormData(this);  
        
        // Desabilitar botão durante upload  
        const submitBtn = document.getElementById('submitUpload');  
        submitBtn.disabled = true;  
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Enviando...';  
        
        // Iniciar upload via AJAX com rastreamento de progresso  
        const xhr = new XMLHttpRequest();  
        xhr.open('POST', this.action);  
        
        xhr.upload.addEventListener('progress', function(e) {  
            if (e.lengthComputable) {  
                const percentComplete = Math.round((e.loaded / e.total) * 100);  
                progressBar.style.width = percentComplete + '%';  
                progressBar.textContent = percentComplete + '%';  
                uploadStatus.textContent = `Enviando: ${Math.round(e.loaded / 1024)} KB de ${Math.round(e.total / 1024)} KB`;  
            }  
        });  
        
        xhr.addEventListener('load', function() {  
            if (xhr.status === 200) {  
                try {  
                    const response = JSON.parse(xhr.responseText);  
                    if (response.success) {  
                        progressBar.classList.remove('progress-bar-animated');  
                        progressBar.classList.remove('bg-primary');  
                        progressBar.classList.add('bg-success');  
                        uploadStatus.textContent = 'Upload concluído com sucesso!';  
                        
                        // Mensagem de sucesso  
                        Swal.fire({  
                            icon: 'success',  
                            title: 'Upload realizado!',  
                            text: response.message,  
                            timer: 2000,  
                            showConfirmButton: false  
                        }).then(() => {  
                            // Recarregar a página após upload  
                            window.location.reload();  
                        });  
                    } else {  
                        showUploadError(response.message || 'Erro ao enviar arquivos.');  
                    }  
                } catch (e) {  
                    showUploadError('Erro ao processar resposta do servidor.');  
                }  
            } else {  
                showUploadError('Falha na comunicação com o servidor.');  
            }  
        });  
        
        xhr.addEventListener('error', function() {  
            showUploadError('Erro de conexão. Verifique sua internet.');  
        });  
        
        xhr.send(formData);  
        
        function showUploadError(message) {  
            progressBar.classList.remove('progress-bar-animated');  
            progressBar.classList.remove('bg-primary');  
            progressBar.classList.add('bg-danger');  
            uploadStatus.textContent = message;  
            uploadStatus.classList.add('text-danger');  
            
            // Habilitar botão novamente  
            submitBtn.disabled = false;  
            submitBtn.innerHTML = '<i data-feather="upload" class="me-1"></i> Tentar Novamente';  
            feather.replace();  
            
            Swal.fire({  
                icon: 'error',  
                title: 'Erro no Upload',  
                text: message,  
                confirmButtonColor: '#4e73df'  
            });  
        }  
    });  
});  

// Confirmar exclusão de anexo  
function confirmarExclusao(id) {  
    Swal.fire({  
        title: 'Tem certeza?',  
        text: "Esta ação não poderá ser revertida!",  
        icon: 'warning',  
        showCancelButton: true,  
        confirmButtonColor: '#e74a3b',  
        cancelButtonColor: '#858796',  
        confirmButtonText: 'Sim, excluir!',  
        cancelButtonText: 'Cancelar'  
    }).then((result) => {  
        if (result.isConfirmed) {  
            window.location.href = `excluir_anexo_triagem.php?id=${id}&redirect=<?= isset($_GET['id']) ? 'triagem.php?id='.$_GET['id'] : 'triagem.php' ?>`;  
        }  
    });  
}  

/* ---------- APROVAR SOLICITAÇÃO (AJAX) ---------- */
$(document).on('click', '.btn-aprovar', function () {
    const id = $(this).data('id');

    Swal.fire({
        title: 'Aprovar Solicitação',
        text: 'Tem certeza que deseja aprovar esta solicitação?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1cc88a',
        cancelButtonColor: '#858796',
        confirmButtonText: 'Sim, aprovar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (!result.isConfirmed) return;

        /* envia via POST (JSON) para aprovar_triagem.php */
        $.post('aprovar_triagem.php', { id: id }, function (resp) {
            if (resp.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Solicitação aprovada!',
                    timer: 1800,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: resp.message || 'Não foi possível aprovar.'
                });
            }
        }, 'json').fail(() => {
            Swal.fire({
                icon: 'error',
                title: 'Erro de conexão',
                text: 'Tente novamente mais tarde.'
            });
        });
    });
});


/* ---------- REJEITAR SOLICITAÇÃO (AJAX) ---------- */
$(document).on('click', '.btn-rejeitar', function () {
    const id = $(this).data('id');

    Swal.fire({
        title: 'Rejeitar Solicitação',
        html: `
            <div class="text-start mb-3">
                <label class="form-label">Motivo da rejeição:</label>
                <textarea id="motivoRejeicao" class="form-control" rows="3"
                          placeholder="Descreva o motivo da rejeição..."></textarea>
            </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74a3b',
        cancelButtonColor: '#858796',
        confirmButtonText: 'Rejeitar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const motivo = document.getElementById('motivoRejeicao').value.trim();
            if (!motivo) {
                Swal.showValidationMessage('Por favor, informe o motivo da rejeição');
            }
            return motivo;          // ← devolve string
        }
    }).then((result) => {
        if (!result.isConfirmed) return;

        /* envia via POST para rejeitar_triagem.php */
        $.post('rejeitar_triagem.php',
            { id: id, motivo: result.value },
            function (resp) {
                if (resp.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Solicitação rejeitada!',
                        timer: 1800,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: resp.message || 'Não foi possível rejeitar.'
                    });
                }
            }, 'json'
        ).fail(() => {
            Swal.fire({
                icon: 'error',
                title: 'Erro de conexão',
                text: 'Tente novamente mais tarde.'
            });
        });
    });
});


// Emitir certidão  
// Emitir certidão (novo fluxo)
$(document).on('click','.btn-emitir-certidao',function(){
    const id = $(this).data('id');
    Swal.fire({
        title:'Certidão Emitida',
        input:'text',
        inputPlaceholder:'Número do selo eletrônico',
        showCancelButton:true,
        confirmButtonText:'Emitir',
        preConfirm:(numero)=>{
            numero = (numero||'').trim();
            if(!numero) Swal.showValidationMessage('Informe o número do selo');
            return numero;
        }
    }).then(r=>{
        if(!r.isConfirmed) return;
        $.post('emitir_certidao.php',{id:id,numero_selo:r.value},function(resp){
            if(resp.success){
                Swal.fire({icon:'success',title:'Certidão emitida!'}).then(()=>location.reload());
            }else{
                Swal.fire({icon:'error',title:'Erro',text:resp.message||'Falha desconhecida'});
            }
        },'json')
        .fail(()=>Swal.fire({icon:'error',title:'Erro de conexão'}));
    });
});


/* ---------- MARCAR COMO ENTREGUE (AJAX) ---------- */
$(document).on('click', '.btn-marcar-entregue', function () {
    const id = $(this).data('id');
    Swal.fire({
        title: 'Confirmar Entrega?',
        text: 'Deseja marcar esta certidão como entregue ao solicitante?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4e73df',
        cancelButtonColor: '#858796',
        confirmButtonText: 'Sim, marcar como entregue',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (!result.isConfirmed) return;

        $.post('entregar_certidao.php', { id: id }, function (resp) {
            if (resp.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Entregue!',
                    text: 'A certidão foi marcada como entregue.',
                    timer: 1800,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: resp.message || 'Não foi possível marcar como entregue.'
                });
            }
        }, 'json').fail(() => {
            Swal.fire({
                icon: 'error',
                title: 'Erro de conexão',
                text: 'Tente novamente mais tarde.'
            });
        });
    });
});

/* ---------- REAVALIAR SOLICITAÇÃO ---------- */
$(document).on('click', '.btn-reavaliar', function () {
    const id = $(this).data('id');

    Swal.fire({
        title: 'Reavaliar Solicitação',
        text: 'Deseja reavaliar e aprovar esta solicitação anteriormente rejeitada?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1cc88a',
        cancelButtonColor: '#858796',
        confirmButtonText: 'Sim, aprovar novamente',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (!result.isConfirmed) return;

        $.post('aprovar_triagem.php', { id: id }, function (resp) {
            if (resp.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Solicitação reavaliada!',
                    text: 'A solicitação foi aprovada novamente.',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: resp.message || 'Não foi possível reavaliar.'
                });
            }
        }, 'json').fail(() => {
            Swal.fire({
                icon: 'error',
                title: 'Erro de conexão',
                text: 'Tente novamente mais tarde.'
            });
        });
    });
});

/* ---------- COPIA DADOS DO SELO ---------- */  
document.addEventListener('DOMContentLoaded', function() {  
    // Inicializa os ícones Feather  
    if (typeof feather !== 'undefined') {  
        feather.replace();  
    }  
    
    // Inicializa tooltips do Bootstrap  
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));  
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {  
        return new bootstrap.Tooltip(tooltipTriggerEl);  
    });  
    
    // Funcionalidade do botão copiar  
    const btnCopiarSelo = document.getElementById('btnCopiarSelo');  
    
    if (btnCopiarSelo) {  
        btnCopiarSelo.addEventListener('click', function() {  
            const numeroSelo = document.getElementById('numeroSelo');  
            
            if (!numeroSelo) {  
                console.error('Elemento numeroSelo não encontrado');  
                return;  
            }  
            
            // Método de cópia mais robusto  
            const texto = numeroSelo.value;  
            
            // Método 1: Usando clipboard API moderna  
            function copiarComAPI() {  
                return navigator.clipboard.writeText(texto);  
            }  
            
            // Método 2: Usando método tradicional de seleção e execCommand  
            function copiarComExecCommand() {  
                // Seleciona o texto do campo  
                numeroSelo.select();  
                numeroSelo.setSelectionRange(0, 99999); // Para dispositivos móveis  
                
                // Tenta executar o comando de cópia  
                return document.execCommand('copy');  
            }  
            
            // Tenta copiar usando diferentes métodos  
            let sucesso = false;  
            
            try {  
                if (navigator.clipboard) {  
                    copiarComAPI()  
                        .then(() => atualizarUI(true))  
                        .catch(err => {  
                            console.warn('Falha ao copiar com Clipboard API:', err);  
                            sucesso = copiarComExecCommand();  
                            atualizarUI(sucesso);  
                        });  
                } else {  
                    sucesso = copiarComExecCommand();  
                    atualizarUI(sucesso);  
                }  
            } catch (err) {  
                console.error('Erro ao copiar:', err);  
                sucesso = false;  
                atualizarUI(sucesso);  
            }  
            
            // Função para atualizar a UI após a cópia  
            function atualizarUI(sucesso) {  
                if (sucesso) {  
                    console.log('Texto copiado com sucesso:', texto);  
                    
                    // 1. Muda o ícone para check  
                    const iconElement = btnCopiarSelo.querySelector('i');  
                    if (iconElement) {  
                        iconElement.setAttribute('data-feather', 'check');  
                        iconElement.classList.remove('text-secondary');  
                        iconElement.classList.add('text-success');  
                        
                        if (typeof feather !== 'undefined') {  
                            feather.replace();  
                        }  
                    }  
                    
                    // 2. Atualiza o tooltip para mostrar "Copiado"  
                    const tooltip = bootstrap.Tooltip.getInstance(btnCopiarSelo);  
                    if (tooltip) {  
                        // Esconde o tooltip atual  
                        tooltip.hide();  
                        
                        // Atualiza o texto do tooltip  
                        // btnCopiarSelo.setAttribute('title', 'Copiado!');  
                        btnCopiarSelo.setAttribute('data-bs-original-title', 'Copiado!');  
                        
                        // Mostra o tooltip atualizado  
                        tooltip.show();  
                    }  
                    
                    // 3. Após 2 segundos, volta ao estado original  
                    setTimeout(() => {  
                        // Restaura o ícone  
                        if (iconElement) {  
                            iconElement.setAttribute('data-feather', 'copy');  
                            iconElement.classList.remove('text-success');  
                            iconElement.classList.add('text-secondary');  
                            
                            if (typeof feather !== 'undefined') {  
                                feather.replace();  
                            }  
                        }  
                        
                        // Restaura o tooltip original  
                        if (tooltip) {  
                            tooltip.hide();  
                            btnCopiarSelo.setAttribute('title', 'Copiar');  
                            btnCopiarSelo.setAttribute('data-bs-original-title', 'Copiar');  
                        }  
                    }, 2000);  
                } else {  
                    console.error('Falha ao copiar o texto');  
                    // Feedback visual de falha (opcional)  
                    alert('Não foi possível copiar o texto. Por favor, selecione e copie manualmente.');  
                }  
            }  
        });  
    } else {  
        console.warn('Botão de copiar não encontrado na página');  
    }  
});

</script>

<?php include 'includes/footer.php'; ?>