<?php 
date_default_timezone_set('America/Sao_Paulo');  
require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  
require_once 'includes/functions.php';  

$usuario_id = $_SESSION['usuario_id'];  
$mensagem = '';  
$sucesso = false;  
$modo_edicao = false;  
// Filtro de status e busca por número  
$filtro_status = $_GET['status'] ?? 'pendentes';  
$filtro_numero = $_GET['numero'] ?? '';  

$condicoes = ["s.status = 'ativo'"];  
$parametros = [];  

if ($filtro_status === 'pendentes') {  
    $condicoes[] = "(s.enviado_portal IS NULL OR s.enviado_portal != 'sim')";  
} elseif ($filtro_status === 'enviados') {  
    $condicoes[] = "s.enviado_portal = 'sim'";  
} elseif ($filtro_status === 'sem_anexos') {  
    $condicoes[] = "NOT EXISTS (SELECT 1 FROM anexos a WHERE a.selo_id = s.id AND a.status = 'ativo')";  
}  

if (!empty($filtro_numero)) {  
    $condicoes[] = "s.numero LIKE ?";  
    $parametros[] = "%$filtro_numero%";  
}

$sql = "
    SELECT s.*, 
        COUNT(DISTINCT a.id) AS total_anexos,
        (SELECT COUNT(*) FROM downloads_selo d WHERE d.selo_id = s.id) AS total_downloads
    FROM selos s
    LEFT JOIN anexos a ON s.id = a.selo_id AND a.status = 'ativo'
    WHERE " . implode(' AND ', $condicoes) . "
    GROUP BY s.id
    ORDER BY s.data_cadastro DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$selos = $stmt->fetchAll();

$selo_atual = null;  

// Verificar se está em modo de edição (pelo ID do selo)  
if (isset($_GET['id']) && is_numeric($_GET['id'])) {  
    $selo_id = $_GET['id'];  
    
    // Buscar selo (verificando se pertence ao usuário)  
    $stmt = $pdo->prepare("
        SELECT s.*, u.nome AS nome_usuario
        FROM selos s
        LEFT JOIN usuarios u ON s.usuario_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$selo_id]);
    $selo_atual = $stmt->fetch();
    
    if ($selo_atual) {  
        $modo_edicao = true;  
        
        // Buscar anexos deste selo  
        $stmt = $pdo->prepare("SELECT * FROM anexos WHERE selo_id = ? AND status = 'ativo' ORDER BY data_upload DESC");  
        $stmt->execute([$selo_id]);  
        $anexos = $stmt->fetchAll();  
    }  
}  

// Incluir cabeçalho  
include 'includes/header.php';  
?>  

<div class="container-fluid py-4">  
    <div class="row mb-4">  
        <div class="col-12 d-flex justify-content-between align-items-center">  
            <div>  
                <h1 class="fw-bold"><?php echo $modo_edicao ? 'Gerenciar Selo' : 'Selos Eletrônicos'; ?></h1>  
                <p class="text-muted">  
                    <?php echo $modo_edicao ? 'Adicione documentos ao selo selecionado' : 'Cadastre e gerencie seus selos eletrônicos'; ?>  
                </p>  
            </div>  
            <div class="d-flex gap-2">  
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#novoSeloModal">  
                    <i data-feather="plus" class="me-1" style="width: 16px; height: 16px;"></i> Novo Selo  
                </button>  
                <a href="selos.php" class="btn btn-outline-secondary">  
                    <i data-feather="arrow-left" class="me-1" style="width: 16px; height: 16px;"></i> Voltar  
                </a>  
            </div>  
        </div>  
    </div> 
    <?php if (!empty($mensagem) && !$sucesso): ?>  
        <div class="alert alert-danger"><?php echo $mensagem; ?></div>  
    <?php elseif (!empty($mensagem) && $sucesso): ?>  
        <div class="alert alert-success"><?php echo $mensagem; ?></div>  
    <?php endif; ?>  
    
    <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>  
        <div class="alert alert-success">Selo cadastrado com sucesso!</div>  
    <?php endif; ?>  
    
    <?php if ($modo_edicao && $selo_atual): ?>  
        <!-- Modo de edição de selo -->  
        <div class="row">  
            <div class="col-md-4 mb-4">  
                <div class="card border-0 shadow-sm">  
                    <div class="card-header bg-white">  
                        <h5 class="mb-0">Detalhes do Selo</h5>  
                    </div>  
                    <div class="card-body">  
                        <div class="mb-3">  
                            <label class="form-label text-muted">Número do Selo</label>  
                            <div class="position-relative">  
                                <div class="form-control bg-light pe-5"><?php echo htmlspecialchars($selo_atual['numero']); ?></div>  
                                <button type="button" class="copy-button position-absolute top-50 end-0 translate-middle-y"   
                                        data-clipboard-text="<?php echo htmlspecialchars($selo_atual['numero']); ?>">  
                                    <i data-feather="copy" class="me-1"></i>  
                                    <span class="copy-tooltip">Copiado!</span>  
                                </button>  
                            </div>  
                        </div>  
                        <div class="mb-3">  
                            <label class="form-label text-muted">Data de Cadastro</label>  
                            <div class="form-control bg-light"><?php echo date('d/m/Y H:i', strtotime($selo_atual['data_cadastro'])); ?></div>  
                        </div> 
                        <?php if (!empty($selo_atual['nome_usuario'])): ?>
                        <div class="mb-3">  
                            <label class="form-label text-muted">Usuário Responsável</label>  
                            <div class="form-control bg-light"><?php echo htmlspecialchars($selo_atual['nome_usuario']); ?></div>  
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">  
                            <label class="form-label text-muted">Total de Anexos</label>  
                            <div class="form-control bg-light"><?php echo count($anexos); ?> anexos</div>  
                        </div>  

                        <div class="mb-3">  
                            <label class="form-label text-muted">Envio ao Portal do Selo</label>  
                            <?php if ($selo_atual['enviado_portal'] === 'sim'): ?>  
                                <div class="form-control bg-success text-white fw-bold">  
                                    Enviado em <?php echo date('d/m/Y H:i', strtotime($selo_atual['data_envio_portal'])); ?><br>
                                    <span class="text-white-50">Por <?php echo htmlspecialchars($selo_atual['enviado_por'] ?? 'usuário desconhecido'); ?></span>  
                                </div>  
                            <?php else: ?>  
                                <form method="POST" action="marcar_enviado.php" id="formMarcarEnviado">  
                                    <input type="hidden" name="selo_id" value="<?php echo $selo_atual['id']; ?>">  
                                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100 mt-1">  
                                        <i data-feather="check-circle" class="me-1"></i> Marcar como Enviado  
                                    </button>  
                                </form>  
                            <?php endif; ?>  
                        </div>  

                        <?php if (count($anexos) > 0): ?>  
                            <div class="d-grid gap-2">  
                                <a href="baixar_documento.php?id=<?php echo $selo_atual['id']; ?>" class="btn btn-success">  
                                    <i data-feather="download" class="me-1" style="width: 16px; height: 16px;"></i>  
                                    Baixar Documento Comprobatório  
                                </a>  
                            </div>  
                        <?php endif; ?>  
                    </div>
                </div>  
            </div>  
            
            <div class="col-md-8">  
                <div class="card border-0 shadow-sm mb-4">  
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">  
                        <h5 class="mb-0">Anexos do Selo</h5>  
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#uploadCollapse" aria-expanded="false" aria-controls="uploadCollapse">  
                            <i data-feather="upload" class="me-1" style="width: 14px; height: 14px;"></i>  
                            Adicionar Anexos  
                        </button>  
                    </div>  
                    
                    <!-- Área de upload colapsável -->  
                    <div class="collapse" id="uploadCollapse">  
                        <div class="card-body border-bottom">  
                            <form id="uploadForm" action="upload_anexo.php" method="POST" enctype="multipart/form-data">  
                                <input type="hidden" name="selo_id" value="<?php echo $selo_atual['id']; ?>">  
                                
                                <div class="upload-area mb-3">  
                                    <div class="dropzone-container" id="dropzoneUpload">  
                                        <div class="dz-message">  
                                            <div class="upload-icon mb-3">  
                                                <i data-feather="upload-cloud" style="width: 48px; height: 48px; color: #6c757d;"></i>  
                                            </div>  
                                            <h5>Arraste e solte arquivos aqui</h5>  
                                            <p class="text-muted">ou</p>  
                                            <button type="button" class="btn btn-outline-primary browse-btn">Selecionar Arquivos</button>  
                                            <p class="mt-2 small text-muted">Formatos aceitos: PDF, JPG, JPEG, PNG (máx. 10MB por arquivo)</p>  
                                        </div>  
                                    </div>  
                                </div>  
                                
                                <div id="preview-container" class="mb-3 d-none">  
                                    <h6 class="mb-2">Arquivos selecionados</h6>  
                                    <div id="file-preview-list" class="file-preview-list"></div>  
                                </div>  
                                
                                <div id="progressContainer" class="mt-3 d-none">  
                                    <label class="form-label">Progresso do Upload</label>  
                                    <div class="progress">  
                                        <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>  
                                    </div>  
                                    <p id="uploadStatus" class="mt-2 small text-muted"></p>  
                                </div>  
                                
                                <div class="d-flex justify-content-end mt-3">  
                                    <button type="button" class="btn btn-outline-secondary me-2" data-bs-toggle="collapse" data-bs-target="#uploadCollapse">  
                                        Cancelar  
                                    </button>  
                                    <button type="submit" id="submitUpload" class="btn btn-primary upload-btn" disabled>  
                                        <i data-feather="upload" class="me-1" style="width: 16px; height: 16px;"></i>  
                                        Enviar Arquivos  
                                    </button>  
                                </div>  
                            </form>  
                        </div>  
                    </div>  
                    
                    <!-- Lista de anexos -->  
                    <div class="card-body">  
                        <?php if (count($anexos) > 0): ?>  
                            <div class="table-responsive">  
                                <table id="tabelaSelos" class="table table-striped table-bordered dt-responsive nowrap" style="width:100%">  
                                    <thead>  
                                        <tr>  
                                            <th style="width: 50px;"></th>  
                                            <th>Nome do Arquivo</th>  
                                            <th>Tipo</th>  
                                            <th>Tamanho</th>  
                                            <th>Data</th>  
                                            <th>Ações</th>  
                                        </tr>  
                                    </thead>  
                                    <tbody>  
                                        <?php foreach ($anexos as $anexo): ?>  
                                            <tr>  
                                                <td>  
                                                    <?php if (strpos($anexo['tipo'], 'image') !== false): ?>  
                                                        <i data-feather="image" style="width: 18px; height: 18px;"></i>  
                                                    <?php else: ?>  
                                                        <i data-feather="file-text" style="width: 18px; height: 18px;"></i>  
                                                    <?php endif; ?>  
                                                </td>  
                                                <td><?php echo htmlspecialchars($anexo['nome_arquivo']); ?></td>  
                                                <td>  
                                                    <?php  
                                                    $tipo_exibicao = '';  
                                                    if ($anexo['tipo'] == 'application/pdf') {  
                                                        $tipo_exibicao = 'PDF';  
                                                    } else if (strpos($anexo['tipo'], 'image/jpeg') !== false) {  
                                                        $tipo_exibicao = 'JPEG';  
                                                    } else if (strpos($anexo['tipo'], 'image/png') !== false) {  
                                                        $tipo_exibicao = 'PNG';  
                                                    } else {  
                                                        $tipo_exibicao = $anexo['tipo'];  
                                                    }  
                                                    echo $tipo_exibicao;  
                                                    ?>  
                                                </td>  
                                                <td><?php echo number_format($anexo['tamanho'] / 1024, 2) . ' KB'; ?></td>  
                                                <td><?php echo date('d/m/Y H:i', strtotime($anexo['data_upload'])); ?></td>  
                                                <td>  
                                                    <a href="<?php echo $anexo['caminho']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Visualizar">  
                                                        <i data-feather="eye" style="width: 14px; height: 14px;"></i>  
                                                    </a>  
                                                    <?php if ($selo_atual['enviado_portal'] !== 'sim'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger excluir-anexo"   
                                                                data-id="<?php echo $anexo['id']; ?>"   
                                                                data-nome="<?php echo htmlspecialchars($anexo['nome_arquivo']); ?>"  
                                                                title="Excluir">  
                                                            <i data-feather="trash-2" style="width: 14px; height: 14px;"></i>  
                                                        </button>
                                                    <?php endif; ?>
                                                </td>  
                                            </tr>  
                                        <?php endforeach; ?>  
                                    </tbody>  
                                </table>  
                            </div>  
                        <?php else: ?>  
                            <div class="text-center py-4">  
                                <i data-feather="paperclip" style="width: 48px; height: 48px; opacity: 0.2;"></i>  
                                <p class="mt-2 text-muted">Não há anexos para este selo.</p>  
                                <p class="text-muted small">Adicione documentos clicando no botão "Adicionar Anexos" acima.</p>  
                            </div>  
                        <?php endif; ?>  
                    </div>  
                </div>  
            </div>  
        </div>  
        <?php else: ?>  
            <!-- Lista de selos -->  
            <div class="card border-0 shadow-sm">  
                <div class="card-body">  
                    <?php  
                    // Código da consulta SQL modificado (como mostrado no item 1)  
                    
                    if (count($selos) > 0):  
                    ?>  
                    
                    <!-- Filtros de pesquisa -->  
                    <div class="card-header bg-white p-3 mb-3">  
                        <div class="row">  
                            <div class="col-md-6 mb-2 mb-md-0">  
                                <div class="btn-group" role="group" aria-label="Filtro de status">  
                                    <a href="selos.php?status=todos" class="btn btn-outline-secondary <?php echo $filtro_status === 'todos' ? 'active' : ''; ?>">  
                                        Todos  
                                    </a>  
                                    <a href="selos.php?status=enviados" class="btn btn-outline-secondary <?php echo $filtro_status === 'enviados' ? 'active' : ''; ?>">  
                                        Enviados  
                                    </a>  
                                    <a href="selos.php?status=pendentes" class="btn btn-outline-secondary <?php echo $filtro_status === 'pendentes' ? 'active' : ''; ?>">  
                                        Pendentes  
                                    </a>  
                                    <a href="selos.php?status=sem_anexos" class="btn btn-outline-secondary <?php echo $filtro_status === 'sem_anexos' ? 'active' : ''; ?>">
                                        Sem Anexos
                                    </a>
                                </div>  
                            </div>  
                            <div class="col-md-6">  
                                <div class="input-group">  
                                    <input type="text" id="filtroNumeroSelo" class="form-control" placeholder="Pesquisar por número do selo...">  
                                    <button class="btn btn-outline-secondary" type="button" id="btnLimparFiltro">  
                                        <i data-feather="x" style="width: 16px; height: 16px;"></i>  
                                    </button>  
                                </div>  
                            </div>  
                        </div>  
                    </div>  
                    
                    <div class="table-responsive">
                        <table id="tabelaSelos" class="table table-striped table-bordered dt-responsive nowrap">
                            <thead>  
                                <tr>  
                                    <th>Número do Selo</th>  
                                    <th>Data de Cadastro</th>  
                                    <th>Anexos</th>  
                                    <th>Downloads</th>  
                                    <th>Situação</th>  
                                    <th>Ações</th>  
                                </tr>  
                            </thead>  
                            <tbody>  
                                <?php foreach ($selos as $selo): ?>  
                                    <tr>  
                                        <td><?php echo htmlspecialchars($selo['numero']); ?></td>  
                                        <td><?php echo date('d/m/Y H:i', strtotime($selo['data_cadastro'])); ?></td>  
                                        <td>  
                                            <span class="badge bg-<?php echo ($selo['total_anexos'] > 0) ? 'info' : 'secondary'; ?>">  
                                                <?php echo $selo['total_anexos']; ?> anexos  
                                            </span>  
                                        </td>  
                                        <td>  
                                            <span class="badge bg-<?php echo ($selo['total_downloads'] > 0) ? 'success' : 'secondary'; ?>">  
                                                <?php echo $selo['total_downloads']; ?> download<?php echo $selo['total_downloads'] == 1 ? '' : 's'; ?>  
                                            </span>  
                                        </td>  
                                        <td>  
                                            <?php if ($selo['total_anexos'] == 0): ?>  
                                                <span class="badge bg-danger">  
                                                    <i data-feather="alert-circle" style="width: 14px; height: 14px;" class="me-1"></i>  
                                                    Sem anexo  
                                                </span>  
                                            <?php elseif ($selo['enviado_portal'] === 'sim'): ?>  
                                                <span class="badge bg-success">  
                                                    <i data-feather="check-circle" style="width: 14px; height: 14px;" class="me-1"></i>  
                                                    Enviado ao Portal  
                                                </span>  
                                            <?php else: ?>  
                                                <span class="badge bg-warning text-dark">  
                                                    <i data-feather="clock" style="width: 14px; height: 14px;" class="me-1"></i>  
                                                    Pendente de envio  
                                                </span>  
                                            <?php endif; ?>  
                                        </td>  
                                        <td>  
                                            <a href="selos.php?id=<?php echo $selo['id']; ?>" class="btn btn-sm btn-outline-primary" title="Gerenciar Selo">  
                                                <i data-feather="edit" style="width: 16px; height: 16px;"></i>  
                                            </a>  

                                            <?php if ($selo['total_anexos'] > 0): ?>  
                                                <a href="baixar_documento.php?id=<?php echo $selo['id']; ?>" class="btn btn-sm btn-outline-success" title="Baixar Documento Comprobatório">  
                                                    <i data-feather="download" style="width: 16px; height: 16px;"></i>  
                                                </a>  
                                            <?php endif; ?>  

                                            <?php if ($selo['enviado_portal'] !== 'sim' && $selo['total_anexos'] > 0): ?>  
                                                <form method="POST" action="marcar_enviado.php" class="d-inline form-marcar-enviado">  
                                                    <input type="hidden" name="selo_id" value="<?php echo $selo['id']; ?>">  
                                                    <button type="submit" class="btn btn-sm btn-outline-info" title="Marcar como Enviado ao Portal do Selo">  
                                                        <i data-feather="check-circle" style="width: 16px; height: 16px;"></i>  
                                                    </button>  
                                                </form>  
                                            <?php endif; ?>  

                                            <?php if ($selo['enviado_portal'] !== 'sim'): ?>  
                                                <button type="button" class="btn btn-sm btn-outline-danger excluir-selo"  
                                                        data-id="<?php echo $selo['id']; ?>"  
                                                        data-numero="<?php echo htmlspecialchars($selo['numero']); ?>"  
                                                        title="Excluir Selo">  
                                                    <i data-feather="trash-2" style="width: 16px; height: 16px;"></i>  
                                                </button>  
                                            <?php endif; ?>  
                                        </td>  
                                    </tr>  
                                <?php endforeach; ?>  
                            </tbody>  
                        </table>  
                    </div>  
                    <?php else: ?>  
                        <div class="text-center py-4">  
                            <i data-feather="file-text" style="width: 48px; height: 48px; opacity: 0.2;"></i>  
                            <p class="mt-2 text-muted">Você ainda não cadastrou nenhum selo.</p>  
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novoSeloModal">  
                                <i data-feather="plus" class="me-1" style="width: 14px; height: 14px;"></i>    
                                Cadastrar Novo Selo  
                            </button>  
                        </div>  
                    <?php endif; ?>  
                </div>  
            </div>  
        <?php endif; ?>  
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

<!-- jQuery e scripts necessários -->
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
feather.replace();

document.addEventListener('DOMContentLoaded', function() {  
    // Função para inicializar o DataTables  
    function initializeDataTable() {  
        console.log('Tentando inicializar DataTable...');  
        
        // Verificar se a tabela existe  
        if ($('.table').length === 0) {  
            console.error('Nenhuma tabela encontrada na página');  
            return;  
        }  
        
        // Adicionar ID à tabela se necessário  
        if ($('#tabelaSelos').length === 0) {  
            $('.table').first().attr('id', 'tabelaSelos');  
        }  
        
        try {  
            // Inicializar o DataTable com configurações melhoradas  
            var table = $('#tabelaSelos').DataTable({  
            responsive: true,  
            pageLength: 10,  
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Todos"]],  
            language: {  
                "emptyTable": "Nenhum registro encontrado",  
                "info": "Mostrando de _START_ até _END_ de _TOTAL_ registros",  
                "infoEmpty": "Mostrando 0 até 0 de 0 registros",  
                "infoFiltered": "(Filtrados de _MAX_ registros)",  
                "infoThousands": ".",  
                "lengthMenu": "Mostrar _MENU_ registros por página",  
                "loadingRecords": "Carregando...",  
                "processing": "Processando...",  
                "zeroRecords": "Nenhum registro encontrado",  
                "search": "Pesquisar",  
                "paginate": {  
                    "next": "Próximo",  
                    "previous": "Anterior",  
                    "first": "Primeiro",  
                    "last": "Último"  
                },  
                "aria": {  
                    "sortAscending": ": Ordenar colunas de forma ascendente",  
                    "sortDescending": ": Ordenar colunas de forma descendente"  
                }  
            },  
            dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>' +  
                '<"row"<"col-sm-12"tr>>' +  
                '<"row"<"col-sm-5"i><"col-sm-7"p>>',  
            columnDefs: [  
                { orderable: false, targets: -1 }, // Ações  
                { width: "35%", targets: 0 },      // Número do Selo  
                { width: "20%", targets: 1 },      // Data de Cadastro  
                { width: "15%", targets: 2 },      // Anexos  
                { width: "15%", targets: 3 },      // Downloads  
                { width: "15%", targets: 4 }       // Ações  
            ],  
            order: [[1, 'desc']],  
            stateSave: true,  
            drawCallback: function() {  
                $('#tabelaSelos').removeClass('d-none');  
                $('#loadingTableMessage').hide();  
                feather.replace();  
            },  
            initComplete: function() {  
                // Esconder a busca padrão do DataTables para usar nosso filtro personalizado  
                $('.dataTables_filter').hide();  
                
                // Configurar evento de busca personalizada  
                $('#filtroNumeroSelo').on('keyup', function() {  
                    table.search(this.value).draw();  
                });  
                
                // Botão para limpar o filtro  
                $('#btnLimparFiltro').on('click', function() {  
                    $('#filtroNumeroSelo').val('');  
                    table.search('').draw();  
                });  
            }  
        });  

        // Mostrar/esconder o botão limpar filtro  
        $('#filtroNumeroSelo').on('input', function() {  
            if ($(this).val().length > 0) {  
                $('#btnLimparFiltro').show();  
            } else {  
                $('#btnLimparFiltro').hide();  
            }  
        });  

        // Esconder o botão limpar inicialmente  
        $(document).ready(function() {  
            $('#btnLimparFiltro').hide();  
        });

        // Mostrar/esconder o botão limpar filtro  
        $('#filtroNumeroSelo').on('input', function() {  
            if ($(this).val().length > 0) {  
                $('#btnLimparFiltro').show();  
            } else {  
                $('#btnLimparFiltro').hide();  
            }  
        });  

        // Esconder o botão limpar inicialmente  
        $(document).ready(function() {  
            $('#btnLimparFiltro').hide();  
        });
            console.log('DataTable inicializado com sucesso');  
        } catch (e) {  
            console.error('Erro ao inicializar DataTable:', e);  
        }  
    }  

    // Verificar se jQuery e DataTables estão disponíveis  
    if (typeof jQuery !== 'undefined') {  
        console.log('jQuery está disponível, versão:', jQuery.fn.jquery);  
        
        // Aguardar um pouco para garantir que todos os scripts foram carregados  
        setTimeout(function() {  
            if (typeof jQuery.fn.DataTable !== 'undefined') {  
                console.log('DataTables está disponível, inicializando...');  
                initializeDataTable();  
            } else {  
                console.log('DataTables não disponível, carregando script...');  
                
                // Carregar o script do DataTables  
                var script = document.createElement('script');  
                script.src = 'https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js';  
                script.onload = function() {  
                    console.log('Script do DataTables carregado, inicializando...');  
                    
                    // Carregar o script do Bootstrap para DataTables  
                    var bootstrapScript = document.createElement('script');  
                    bootstrapScript.src = 'https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js';  
                    bootstrapScript.onload = initializeDataTable;  
                    document.head.appendChild(bootstrapScript);  
                };  
                document.head.appendChild(script);  
            }  
        }, 500);  
    } else {  
        console.error('jQuery não está disponível');  
    }  
});  

$(document).ready(function () {
    // Excluir anexo
    $('.excluir-anexo').click(function () {
        const id = $(this).data('id');
        const nome = $(this).data('nome');

        Swal.fire({
            title: 'Excluir anexo?',
            text: `Você tem certeza que deseja excluir o anexo "${nome}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `excluir_anexo.php?id=${id}`;
            }
        });
    });

    // Excluir selo
    $('.excluir-selo').click(function () {
        const id = $(this).data('id');
        const numero = $(this).data('numero');

        Swal.fire({
            title: 'Excluir selo?',
            text: `Você tem certeza que deseja excluir o selo "${numero}"? Todos os anexos relacionados serão excluídos.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `excluir_selo.php?id=${id}`;
            }
        });
    });
});

$('.form-marcar-enviado').submit(function(e) {
    e.preventDefault();
    const form = $(this);

    Swal.fire({
        title: 'Confirmar envio?',
        text: 'Deseja marcar este documento como enviado ao Portal do Selo?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, marcar como enviado',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('marcar_enviado.php', form.serialize(), function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Marcado como enviado!',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', response.message || 'Não foi possível marcar como enviado.', 'error');
                }
            }, 'json').fail(() => {
                Swal.fire('Erro', 'Falha na comunicação com o servidor.', 'error');
            });
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {  
    // Verificar se estamos na página de edição de selo (com o dropzone)  
    const dropzone = document.getElementById('dropzoneUpload');  
    
    // Só executar o código do dropzone se ele existir na página  
    if (dropzone) {  
        const fileInput = document.createElement('input');  
        fileInput.type = 'file';  
        fileInput.multiple = true;  
        fileInput.name = 'arquivos[]';  
        fileInput.accept = '.pdf,.jpg,.jpeg,.png';  
        fileInput.style.display = 'none';  
        fileInput.setAttribute('form', 'uploadForm');  
        
        const uploadForm = document.getElementById('uploadForm');  
        if (uploadForm) {  
            uploadForm.appendChild(fileInput);  

            const browseBtn = document.querySelector('.browse-btn');  
            const submitBtn = document.getElementById('submitUpload');  
            const previewContainer = document.getElementById('preview-container');  
            const previewList = document.getElementById('file-preview-list');  
            
            // Evento para o botão de navegação  
            if (browseBtn) {  
                browseBtn.addEventListener('click', function() {  
                    fileInput.click();  
                });  
            }  
            
            // Eventos de arrastar e soltar  
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {  
                dropzone.addEventListener(eventName, preventDefaults, false);  
            });  
            
            function preventDefaults(e) {  
                e.preventDefault();  
                e.stopPropagation();  
            }  
            
            ['dragenter', 'dragover'].forEach(eventName => {  
                dropzone.addEventListener(eventName, highlight, false);  
            });  
            
            ['dragleave', 'drop'].forEach(eventName => {  
                dropzone.addEventListener(eventName, unhighlight, false);  
            });  
            
            function highlight() {  
                dropzone.classList.add('highlight');  
            }  
            
            function unhighlight() {  
                dropzone.classList.remove('highlight');  
            }  
            
            // Manipulador de soltar arquivos  
            dropzone.addEventListener('drop', handleDrop, false);  
            
            function handleDrop(e) {  
                const dt = e.dataTransfer;  
                const files = dt.files;  
                handleFiles(files);  
            }  
            
            // Manipulador de seleção de arquivos  
            fileInput.addEventListener('change', function() {  
                handleFiles(this.files);  
            });  
            
            function handleFiles(files) {  
                if (files.length > 0) {  
                    previewContainer.classList.remove('d-none');  
                    submitBtn.disabled = false;  
                    
                    // Limpar a lista de visualização se necessário  
                    // previewList.innerHTML = '';  
                    
                    Array.from(files).forEach(file => {  
                        // Verificar tipo de arquivo  
                        const validTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];  
                        if (!validTypes.includes(file.type)) {  
                            showToast('Tipo de arquivo não suportado: ' + file.name, 'error');  
                            return;  
                        }  
                        
                        // Verificar tamanho do arquivo (10MB)  
                        if (file.size > 10 * 1024 * 1024) {  
                            showToast('Arquivo muito grande: ' + file.name, 'error');  
                            return;  
                        }  
                        
                        addFilePreview(file);  
                    });  
                }  
            }  
            
            function addFilePreview(file) {  
                const item = document.createElement('div');  
                item.className = 'file-preview-item';  
                
                // Determinar o ícone com base no tipo de arquivo  
                let iconName = 'file';  
                if (file.type === 'application/pdf') {  
                    iconName = 'file-text';  
                } else if (file.type.startsWith('image/')) {  
                    iconName = 'image';  
                }  
                
                // Formatar o tamanho do arquivo  
                const fileSize = formatFileSize(file.size);  
                
                item.innerHTML = `  
                    <div class="file-icon">  
                        <i data-feather="${iconName}" style="width: 18px; height: 18px;"></i>  
                    </div>  
                    <div class="file-info">  
                        <div class="file-name">${file.name}</div>  
                        <div class="file-size">${fileSize}</div>  
                    </div>  
                    <div class="file-remove" data-filename="${file.name}">  
                        <i data-feather="x" style="width: 16px; height: 16px;"></i>  
                    </div>  
                `;  
                
                previewList.appendChild(item);  
                
                // Inicializar os ícones Feather  
                if (typeof feather !== 'undefined') {  
                    feather.replace({  
                        'stroke-width': 2,  
                        'width': 18,  
                        'height': 18  
                    });  
                }  
                
                // Adicionar evento para remover o arquivo  
                const removeBtn = item.querySelector('.file-remove');  
                removeBtn.addEventListener('click', function() {  
                    // Remover o arquivo do input  
                    const newFileList = new DataTransfer();  
                    Array.from(fileInput.files).forEach(f => {  
                        if (f.name !== this.dataset.filename) {  
                            newFileList.items.add(f);  
                        }  
                    });  
                    fileInput.files = newFileList.files;  
                    
                    // Remover a visualização  
                    item.remove();  
                    
                    // Verificar se ainda há arquivos  
                    if (fileInput.files.length === 0) {  
                        previewContainer.classList.add('d-none');  
                        submitBtn.disabled = true;  
                    }  
                });  
            }  
            
            function formatFileSize(bytes) {  
                if (bytes === 0) return '0 Bytes';  
                const k = 1024;  
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];  
                const i = Math.floor(Math.log(bytes) / Math.log(k));  
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];  
            }  
            
            // Manipulador de envio do formulário  
            uploadForm.addEventListener('submit', function(e) {  
                e.preventDefault();  

                if (fileInput.files.length === 0) {  
                    Swal.fire('Erro', 'Selecione pelo menos um arquivo para enviar.', 'error');  
                    return;  
                }  

                const progressContainer = document.getElementById('progressContainer');  
                const progressBar = document.getElementById('progressBar');  
                const uploadStatus = document.getElementById('uploadStatus');  

                progressContainer.classList.remove('d-none');  
                submitBtn.disabled = true;  

                const formData = new FormData(this);  

                const xhr = new XMLHttpRequest();  
                xhr.open('POST', 'upload_anexo.php', true);  

                // Progresso do upload  
                xhr.upload.addEventListener('progress', function(e) {  
                    if (e.lengthComputable) {  
                        const percentComplete = Math.round((e.loaded / e.total) * 100);  
                        progressBar.style.width = percentComplete + '%';  
                        progressBar.textContent = percentComplete + '%';  
                        uploadStatus.textContent = `Enviando arquivos... ${formatFileSize(e.loaded)} de ${formatFileSize(e.total)}`;  
                    }  
                });  

                // Resposta do servidor  
                xhr.onload = function () {  
                    console.log("Resposta bruta:", xhr.responseText);  
                    uploadStatus.textContent = 'Upload finalizado';  

                    if (xhr.status === 200) {  
                        let response;  
                        try {  
                            response = JSON.parse(xhr.responseText);  
                        } catch (e) {  
                            console.error('Resposta inválida:', xhr.responseText);  
                            Swal.fire('Erro', 'Resposta inválida do servidor.', 'error');  
                            submitBtn.disabled = false;  
                            return;  
                        }  

                        if (response.success) {  
                            Swal.fire({  
                                icon: 'success',  
                                title: 'Sucesso!',  
                                text: response.message || 'Upload realizado com sucesso!',  
                                timer: 1500,  
                                showConfirmButton: false  
                            }).then(() => {  
                                window.location.reload();  
                            });  
                        } else {  
                            Swal.fire('Erro no Upload', response.message || 'Falha ao enviar arquivos.', 'error');  
                            submitBtn.disabled = false;  
                        }  
                    } else {  
                        Swal.fire('Erro', 'Falha na comunicação com o servidor. Código: ' + xhr.status, 'error');  
                        submitBtn.disabled = false;  
                    }  
                };  

                // Erro de conexão  
                xhr.onerror = function() {  
                    Swal.fire('Erro', 'Falha na conexão com o servidor.', 'error');  
                    submitBtn.disabled = false;  
                    uploadStatus.textContent = 'Upload falhou';  
                };  

                // Enviar  
                xhr.send(formData);  
            });  
        }  
    }  
    
    // Função para exibir mensagens de toast  
    function showToast(message, type = 'info') {  
        // Verificar se o Bootstrap Toast está disponível  
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {  
            // Criar um elemento toast  
            const toastEl = document.createElement('div');  
            toastEl.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;  
            toastEl.setAttribute('role', 'alert');  
            toastEl.setAttribute('aria-live', 'assertive');  
            toastEl.setAttribute('aria-atomic', 'true');  
            
            toastEl.innerHTML = `  
                <div class="d-flex">  
                    <div class="toast-body">  
                        ${message}  
                    </div>  
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>  
                </div>  
            `;  
            
            // Adicionar o toast ao documento  
            const toastContainer = document.querySelector('.toast-container');  
            if (!toastContainer) {  
                const container = document.createElement('div');  
                container.className = 'toast-container position-fixed top-0 end-0 p-3';  
                document.body.appendChild(container);  
                container.appendChild(toastEl);  
            } else {  
                toastContainer.appendChild(toastEl);  
            }  
            
            // Inicializar e mostrar o toast  
            const toast = new bootstrap.Toast(toastEl, { delay: 5000 });  
            toast.show();  
            
            // Remover o toast após o fechamento  
            toastEl.addEventListener('hidden.bs.toast', function() {  
                toastEl.remove();  
            });  
        } else {  
            // Fallback para alert se o Bootstrap Toast não estiver disponível  
            if (type === 'error') {  
                alert('Erro: ' + message);  
            } else {  
                alert(message);  
            }  
        }  
    }  
});

$('#formMarcarEnviado').submit(function(e) {
    e.preventDefault();
    const form = $(this);

    Swal.fire({
        title: 'Confirmar envio?',
        text: 'Deseja marcar este documento como enviado ao Portal do Selo?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, marcar como enviado',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('marcar_enviado.php', form.serialize(), function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Marcado como enviado!',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', response.message, 'error');
                }
            }, 'json').fail(() => {
                Swal.fire('Erro', 'Erro na comunicação com o servidor.', 'error');
            });
        }
    });
});

</script>  

<script>  
document.addEventListener('DOMContentLoaded', function() {  
    // Inicializa os ícones Feather (se necessário)  
    if (typeof feather !== 'undefined') {  
        feather.replace();  
    }  
    
    // Seleciona todos os botões de cópia  
    const copyButtons = document.querySelectorAll('.copy-button');  
    
    // Adiciona evento de clique a cada botão  
    copyButtons.forEach(button => {  
        button.addEventListener('click', function() {  
            // Obtém o texto a ser copiado  
            const textToCopy = this.getAttribute('data-clipboard-text');  
            
            // Usa a API moderna de Clipboard quando disponível  
            if (navigator.clipboard && navigator.clipboard.writeText) {  
                navigator.clipboard.writeText(textToCopy)  
                    .then(() => showCopiedFeedback(this))  
                    .catch(err => console.error('Erro ao copiar: ', err));  
            } else {  
                // Fallback para método mais antigo  
                const tempInput = document.createElement('input');  
                tempInput.value = textToCopy;  
                document.body.appendChild(tempInput);  
                tempInput.select();  
                document.execCommand('copy');  
                document.body.removeChild(tempInput);  
                
                showCopiedFeedback(this);  
            }  
        });  
    });  
    
    // Função para mostrar feedback visual de cópia  
    function showCopiedFeedback(button) {  
        // Adiciona a classe para mostrar o tooltip  
        button.classList.add('copied');  
        
        // Altera o ícone para check se estiver usando o Feather  
        const iconElement = button.querySelector('[data-feather]');  
        if (iconElement && typeof feather !== 'undefined') {  
            // Salva o ícone original  
            const originalIcon = iconElement.getAttribute('data-feather');  
            
            // Muda para ícone de check  
            iconElement.setAttribute('data-feather', 'check');  
            feather.replace();  
            
            // Restaura após 2 segundos  
            setTimeout(() => {  
                iconElement.setAttribute('data-feather', originalIcon);  
                feather.replace();  
                button.classList.remove('copied');  
            }, 2000);  
        } else {  
            // Se não estiver usando Feather, apenas remove a classe após 2 segundos  
            setTimeout(() => {  
                button.classList.remove('copied');  
            }, 2000);  
        }  
    }  
});  
</script>

<?php include 'includes/footer.php'; ?>   