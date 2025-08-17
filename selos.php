<?php 
date_default_timezone_set('America/Sao_Paulo');  
require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  
require_once 'includes/functions.php';  

$mensagem = '';  
$sucesso = false;  

/* ------------------------------------------------------------------
   0. Variáveis de sessão
------------------------------------------------------------------*/
$usuario_id   = $_SESSION['usuario_id'] ?? 0;
$usuario_nome = $_SESSION['nome']       ?? 'Usuário';

/* ------------------------------------------------------------------
   1. Filtros (status / número)
------------------------------------------------------------------*/
$filtro_status = $_GET['status'] ?? 'pendentes';  
$filtro_numero = trim($_GET['numero'] ?? '');  

$condicoes = ["s.status = 'ativo'"];  
$parametros = [];  

if ($filtro_status === 'pendentes') {  
    $condicoes[] = "(s.enviado_portal IS NULL OR s.enviado_portal != 'sim')";  
} elseif ($filtro_status === 'enviados') {  
    $condicoes[] = "s.enviado_portal = 'sim'";  
} elseif ($filtro_status === 'sem_anexos') {  
    $condicoes[] = "NOT EXISTS (SELECT 1 FROM anexos a WHERE a.selo_id = s.id AND a.status = 'ativo')";  
} // 'todos' não adiciona condição extra além de status=ativo

if ($filtro_numero !== '') {  
    $condicoes[] = "s.numero LIKE ?";  
    $parametros[] = "%$filtro_numero%";  
}

/* ------------------------------------------------------------------
   2. Consulta lista de selos
------------------------------------------------------------------*/
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

/* ------------------------------------------------------------------
   3. Modo de edição (detalhes de um selo + anexos)
------------------------------------------------------------------*/
$modo_edicao = false;
$prevSeloId  = null; 
$nextSeloId  = null;
$selo_atual  = null;
$anexos      = [];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $selo_id = (int) $_GET['id'];

    // 3.1 - Selo atual
    $stmt = $pdo->prepare("
        SELECT s.*, u.nome AS nome_usuario
        FROM selos s
        LEFT JOIN usuarios u ON s.usuario_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$selo_id]);
    $selo_atual = $stmt->fetch();

    if ($selo_atual) {
        // 3.2 - Selo anterior
        $stmt = $pdo->prepare("
            SELECT id FROM selos
            WHERE id < ? AND status = 'ativo'
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$selo_id]);
        $prevSeloId = $stmt->fetchColumn() ?: null;

        // 3.3 - Próximo selo
        $stmt = $pdo->prepare("
            SELECT id FROM selos
            WHERE id > ? AND status = 'ativo'
            ORDER BY id ASC LIMIT 1
        ");
        $stmt->execute([$selo_id]);
        $nextSeloId = $stmt->fetchColumn() ?: null;

        $modo_edicao = true;

        // 3.4 - Anexos
        $stmt = $pdo->prepare("
            SELECT * FROM anexos
            WHERE selo_id = ? AND status = 'ativo'
            ORDER BY data_upload DESC
        ");
        $stmt->execute([$selo_id]);
        $anexos = $stmt->fetchAll();
    }
}

// Incluir cabeçalho  
include 'includes/header.php';  
?>    

<style>
/* ====== Ajustes de UI/UX e responsividade (cards no mobile) ====== */
@media (max-width: 767.98px){
  .desktop-only { display: none !important; }
}
@media (min-width: 768px){
  .mobile-only  { display: none !important; }
}

/* Cards da lista (mobile) */
.selo-card{
  background: var(--card, #fff);
  border: 1px solid rgba(0,0,0,.06);
  border-radius: 1rem;
  padding: 1rem;
  box-shadow: 0 6px 24px rgba(0,0,0,.05);
}
.selo-card .title{
  font-weight: 700;
  font-size: 1rem;
  color: var(--text, #111827);
}
.selo-card .meta{
  font-size: .9rem;
  color: var(--muted, #64748b);
}
.badge-pill{
  border-radius: 999px;
  padding: .35rem .6rem;
  font-weight: 600;
  font-size: .75rem;
}
.selo-card .actions .btn{
  border-radius: .75rem;
}

/* Dropzone highlight (reutilizada nos modais) */
.dropzone-container.highlight{
  outline: 2px dashed #2563eb;
  outline-offset: 6px;
  background: rgba(37,99,235,.05);
}

/* Copy tooltip */
.copy-button{
  background: transparent;
  border: 0;
  padding: .25rem .5rem;
  margin-right: .25rem;
  cursor: pointer;
}
.copy-button .copy-tooltip{
  position: absolute;
  top: -28px;
  right: 8px;
  background: #111827;
  color: #fff;
  padding: .2rem .5rem;
  border-radius: .4rem;
  font-size: .75rem;
  opacity: 0;
  transform: translateY(4px);
  transition: .2s;
  pointer-events: none;
}
.copy-button.copied .copy-tooltip{
  opacity: 1;
  transform: translateY(0);
}

/* Tabela: manter espaçamento elegante no desktop */
table.dataTable tbody td{
  vertical-align: middle;
}
</style>

<div class="container-fluid py-4">  
    <div class="row mb-4">  
        <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">  
            <div>
                <h1 class="fw-bold text-gray-800 mb-1">
                    <i data-feather="bookmark" class="me-2 text-primary"></i>
                    <?= $modo_edicao ? 'Gerenciar Selo' : 'Selos Eletrônicos' ?>
                </h1>
                <p class="text-muted lead fs-6 mb-0">
                    <?= $modo_edicao ? 'Adicione documentos ao selo selecionado' : 'Cadastre e gerencie seus selos eletrônicos' ?>
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">  
                <?php if ($modo_edicao): ?>  
                    <div class="btn-group" role="group">  
                        <?php if (!empty($prevSeloId)): ?>  
                        <a href="selos.php?id=<?= $prevSeloId ?>" class="btn btn-outline-primary" title="Selo anterior">  
                            <i data-feather="chevron-left"></i>  
                        </a>  
                        <?php endif; ?>  

                        <a href="selos.php" class="btn btn-outline-secondary" title="Voltar à lista">  
                            <i data-feather="list"></i>  
                        </a>  

                        <?php if (!empty($nextSeloId)): ?>  
                        <a href="selos.php?id=<?= $nextSeloId ?>" class="btn btn-outline-primary" title="Próximo selo">  
                            <i data-feather="chevron-right"></i>  
                        </a>  
                        <?php endif; ?>  
                    </div>  
                <?php endif; ?>  
                
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novoSeloModal">  
                    <i data-feather="plus" class="me-1" style="width: 16px; height: 16px;"></i> Novo Selo  
                </button>  

                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importarSelosModal">
                    <i data-feather="upload" class="me-1" style="width:16px;height:16px;"></i> Importar XLSX
                </button>
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
        <!-- =====================  STATUS DO SELO  ===================== -->
        <?php
        if (count($anexos) == 0) {
            $selo_status_key   = 'sem_anexo';
            $selo_status_label = 'Sem Anexo';
        } elseif ($selo_atual['enviado_portal'] === 'sim') {
            $selo_status_key   = 'enviado';
            $selo_status_label = 'Enviado ao Portal do Selo';
        } else {
            $selo_status_key   = 'pendente';
            $selo_status_label = 'Pendente de Envio';
        }

        $statusClass = match($selo_status_key) {
            'pendente'   => 'border-warning text-warning',
            'enviado'    => 'border-success text-success',
            'sem_anexo'  => 'border-danger text-danger',
            default      => 'border-secondary text-secondary'
        };

        $statusBg = match($selo_status_key) {
            'pendente'   => 'bg-warning-subtle',
            'enviado'    => 'bg-success-subtle',
            'sem_anexo'  => 'bg-danger-subtle',
            default      => 'bg-secondary-subtle'
        };

        $statusIcon = match($selo_status_key) {
            'pendente'   => 'clock',
            'enviado'    => 'check-circle',
            'sem_anexo'  => 'alert-circle',
            default      => 'help-circle'
        };
        ?>

        <div class="card border <?= $statusClass ?> shadow-sm mb-4">
            <div class="card-body d-flex align-items-center justify-content-center <?= $statusBg ?> py-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle <?= str_replace('text','bg',$statusClass) ?> p-2 me-3 d-flex justify-content-center align-items-center" style="width:48px;height:48px;">
                        <i data-feather="<?= $statusIcon ?>" class="text-white" style="width:24px;height:24px;"></i>
                    </div>
                    <div class="text-start">
                        <div class="fs-5 fw-bold <?= $statusClass ?>">
                            <?= $selo_status_label ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
                
        <!-- ====== Modo de edição de selo ====== -->  
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
                        <?php if ($selo_atual['enviado_portal'] !== 'sim'): ?>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#uploadCollapse" aria-expanded="false" aria-controls="uploadCollapse">  
                            <i data-feather="upload" class="me-1" style="width: 14px; height: 14px;"></i>  
                            Adicionar Anexos  
                        </button>  
                        <?php endif; ?>
                    </div>  
                    
                    <!-- Área de upload colapsável -->  
                    <div class="collapse" id="uploadCollapse">  
                        <div class="card-body border-bottom">  
                            <form id="uploadForm" action="upload_anexo.php" method="POST" enctype="multipart/form-data">  
                                <input type="hidden" name="selo_id" value="<?php echo $selo_atual['id']; ?>">  
                                
                                <div class="upload-area mb-3">  
                                    <div class="dropzone-container" id="dropzoneUpload">  
                                        <div class="dz-message text-center p-3">  
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
                    
                    <!-- Lista de anexos (ID exclusivo) -->  
                    <div class="card-body">  
                        <?php if (count($anexos) > 0): ?>  
                            <div class="table-responsive">  
                                <table id="tabelaAnexos" class="table table-striped table-bordered dt-responsive nowrap" style="width:100%">  
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
                                        <?php foreach ($anexos as $anexo): 
                                            $isoUpload = date('Y-m-d H:i:s', strtotime($anexo['data_upload']));
                                        ?>  
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
                                                <td data-order="<?php echo $isoUpload; ?>"><?php echo date('d/m/Y H:i', strtotime($anexo['data_upload'])); ?></td>  
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
        <!-- ====== Lista de selos ====== -->  
        <div class="card border-0 shadow-sm">  
            <div class="card-body">  

                <?php if (count($selos) > 0): ?>  
                <!-- Filtros de pesquisa (desktop + mobile) -->  
                <div class="card-header bg-white p-3 mb-3">  
                    <form class="row g-2 align-items-center" method="get" action="selos.php">
                        <div class="col-12 col-md-6">
                            <div class="btn-group w-100" role="group" aria-label="Filtro de status">  
                                <?php
                                // Helper para classe "active"
                                $is = fn($s) => ($filtro_status === $s) ? 'active' : '';
                                ?>
                                <a href="selos.php?status=todos"      class="btn btn-outline-secondary <?= $is('todos'); ?>">Todos</a>  
                                <a href="selos.php?status=enviados"   class="btn btn-outline-secondary <?= $is('enviados'); ?>">Enviados</a>  
                                <a href="selos.php?status=pendentes"  class="btn btn-outline-secondary <?= $is('pendentes'); ?>">Pendentes</a>  
                                <a href="selos.php?status=sem_anexos" class="btn btn-outline-secondary <?= $is('sem_anexos'); ?>">Sem Anexos</a>
                            </div>  
                        </div>  
                        <div class="col-12 col-md-6">
                            <div class="input-group">
                                <input type="hidden" name="status" value="<?= htmlspecialchars($filtro_status) ?>">
                                <input type="text" name="numero" id="filtroNumeroSelo" class="form-control" placeholder="Pesquisar por número do selo..." value="<?= htmlspecialchars($filtro_numero) ?>">
                                <button class="btn btn-outline-secondary" type="button" id="btnLimparFiltro" title="Limpar">
                                    <i data-feather="x" style="width: 16px; height: 16px;"></i>
                                </button>
                                <button class="btn btn-primary" type="submit" title="Aplicar filtro servidor">
                                    <i data-feather="search" style="width:16px;height:16px;"></i>
                                </button>
                            </div>
                            <div class="form-text">No desktop, a busca também filtra a tabela em tempo real.</div>
                        </div>
                    </form>
                </div>  
                
                <!-- Desktop (>= md): Tabela -->
                <div class="table-responsive desktop-only">
                    <table id="tabelaListaSelos" class="table table-striped table-bordered nowrap w-100">
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
                            <?php foreach ($selos as $selo): 
                                $iso = date('Y-m-d H:i:s', strtotime($selo['data_cadastro']));
                                $temAnexo = ((int)$selo['total_anexos'] > 0);
                                $foiEnviado = ($selo['enviado_portal'] === 'sim');

                                if (!$temAnexo) {
                                    $sitBadge = '<span class="badge bg-danger"><i data-feather="alert-circle" class="me-1" style="width:14px;height:14px;"></i>Sem anexo</span>';
                                } elseif ($foiEnviado) {
                                    $sitBadge = '<span class="badge bg-success"><i data-feather="check-circle" class="me-1" style="width:14px;height:14px;"></i>Enviado ao Portal</span>';
                                } else {
                                    $sitBadge = '<span class="badge bg-warning text-dark"><i data-feather="clock" class="me-1" style="width:14px;height:14px;"></i>Pendente de envio</span>';
                                }
                            ?>  
                                <tr>  
                                    <td><?php echo htmlspecialchars($selo['numero']); ?></td>  
                                    <td data-order="<?php echo $iso; ?>"><?php echo date('d/m/Y H:i', strtotime($selo['data_cadastro'])); ?></td>  
                                    <td>
                                        <span class="badge bg-<?php echo $temAnexo ? 'info' : 'secondary'; ?>">
                                            <?php echo (int)$selo['total_anexos']; ?> anexos
                                        </span>
                                    </td>  
                                    <td>
                                        <span class="badge bg-<?php echo ((int)$selo['total_downloads'] > 0) ? 'success' : 'secondary'; ?>">
                                            <?php 
                                            $d = (int)$selo['total_downloads'];
                                            echo $d . ' download' . ($d === 1 ? '' : 's'); 
                                            ?>
                                        </span>
                                    </td>  
                                    <td><?php echo $sitBadge; ?></td>  
                                    <td class="text-nowrap">  
                                        <a href="selos.php?id=<?php echo $selo['id']; ?>" class="btn btn-sm btn-outline-primary" title="Gerenciar Selo">  
                                            <i data-feather="edit" style="width: 16px; height: 16px;"></i>  
                                        </a>  

                                        <?php if ($temAnexo): ?>  
                                            <a href="baixar_documento.php?id=<?php echo $selo['id']; ?>" class="btn btn-sm btn-outline-success" title="Baixar Documento Comprobatório">  
                                                <i data-feather="download" style="width: 16px; height: 16px;"></i>  
                                            </a>  
                                        <?php endif; ?>  

                                        <?php if (!$foiEnviado && $temAnexo): ?>  
                                            <form method="POST" action="marcar_enviado.php" class="d-inline form-marcar-enviado">  
                                                <input type="hidden" name="selo_id" value="<?php echo $selo['id']; ?>">  
                                                <button type="submit" class="btn btn-sm btn-outline-info" title="Marcar como Enviado ao Portal do Selo">  
                                                    <i data-feather="check-circle" style="width: 16px; height: 16px;"></i>  
                                                </button>  
                                            </form>  
                                        <?php endif; ?>  

                                        <?php if (!$foiEnviado): ?>  
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

                <!-- Mobile (< md): Cards -->
                <div id="cardsSelos" class="mobile-only d-flex flex-column gap-3">
                    <?php foreach ($selos as $selo): 
                        $temAnexo   = ((int)$selo['total_anexos'] > 0);
                        $foiEnviado = ($selo['enviado_portal'] === 'sim');
                        $iso        = date('Y-m-d H:i:s', strtotime($selo['data_cadastro']));
                        $dataBr     = date('d/m/Y H:i', strtotime($selo['data_cadastro']));

                        $statusCor  = $foiEnviado ? 'success' : ($temAnexo ? 'warning' : 'danger');
                        $statusTxt  = $foiEnviado ? 'Enviado ao Portal' : ($temAnexo ? 'Pendente de envio' : 'Sem anexo');
                        $statusIcon = $foiEnviado ? 'check-circle' : ($temAnexo ? 'clock' : 'alert-circle');
                    ?>
                    <div class="selo-card" data-numero="<?php echo htmlspecialchars(strtolower($selo['numero'])); ?>" data-order="<?php echo $iso; ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="title"><?php echo htmlspecialchars($selo['numero']); ?></div>
                                <div class="meta">
                                    <i data-feather="calendar" class="me-1" style="width:14px;height:14px;"></i>
                                    <?php echo $dataBr; ?>
                                </div>
                            </div>
                            <span class="badge badge-pill bg-<?php echo $statusCor; ?>">
                                <i data-feather="<?php echo $statusIcon; ?>" style="width:14px;height:14px;" class="me-1"></i>
                                <?php echo $statusTxt; ?>
                            </span>
                        </div>

                        <div class="mt-2 d-flex flex-wrap gap-2">
                            <span class="badge bg-<?php echo $temAnexo ? 'info' : 'secondary'; ?>">
                                <?php echo (int)$selo['total_anexos']; ?> anexos
                            </span>
                            <span class="badge bg-<?php echo ((int)$selo['total_downloads'] > 0) ? 'success' : 'secondary'; ?>">
                                <?php 
                                $d = (int)$selo['total_downloads'];
                                echo $d . ' download' . ($d === 1 ? '' : 's'); 
                                ?>
                            </span>
                        </div>

                        <div class="actions mt-3 d-flex flex-wrap gap-2">
                            <a href="selos.php?id=<?php echo $selo['id']; ?>" class="btn btn-outline-primary btn-sm">
                                <i data-feather="edit" class="me-1" style="width:16px;height:16px;"></i> Gerenciar
                            </a>

                            <?php if ($temAnexo): ?>
                            <a href="baixar_documento.php?id=<?php echo $selo['id']; ?>" class="btn btn-outline-success btn-sm">
                                <i data-feather="download" class="me-1" style="width:16px;height:16px;"></i> Baixar
                            </a>
                            <?php endif; ?>

                            <?php if (!$foiEnviado && $temAnexo): ?>
                            <form method="POST" action="marcar_enviado.php" class="d-inline form-marcar-enviado">
                                <input type="hidden" name="selo_id" value="<?php echo $selo['id']; ?>">
                                <button type="submit" class="btn btn-outline-info btn-sm">
                                    <i data-feather="check-circle" class="me-1" style="width:16px;height:16px;"></i> Enviar
                                </button>
                            </form>
                            <?php endif; ?>

                            <?php if (!$foiEnviado): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm excluir-selo" 
                                    data-id="<?php echo $selo['id']; ?>" 
                                    data-numero="<?php echo htmlspecialchars($selo['numero']); ?>">
                                <i data-feather="trash-2" class="me-1" style="width:16px;height:16px;"></i> Excluir
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php else: ?>  
                    <div class="text-center py-4">  
                        <i data-feather="file-text" style="width: 48px; height: 48px; opacity: 0.2;"></i>  
                        <p class="mt-2 text-muted">Nenhum resultado.</p>  
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

<!-- Modal para cadastrar novo selo (AGORA SUPORTA MÚLTIPLOS COM ';' E ANEXOS COMUNS) -->  
<div class="modal fade" id="novoSeloModal" tabindex="-1" aria-labelledby="novoSeloModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-lg">  
        <form id="formCadastrarSelosManual" class="modal-content" method="POST" action="cadastrar_selos_manual.php" enctype="multipart/form-data">  
            <div class="modal-header">  
                <h5 class="modal-title" id="novoSeloModalLabel">Cadastrar Selos (manual)</h5>  
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>  
            </div>  
            <div class="modal-body">  
                <div class="mb-3">  
                    <label for="numerosSelos" class="form-label">Números dos Selos (separe por <strong>;</strong> — sem espaços)</label>  
                    <input type="text" class="form-control" id="numerosSelos" name="numeros_selos" required
                           placeholder="EX.: AVERBA031542UOUO...;AVERBA031542IG7OZV...;AVERBA0315429HZ43..." autocomplete="off">  
                    <div class="form-text">
                        Use <code>;</code> para separar. Espaços não são permitidos. Qualquer <strong>,</strong> digitada será substituída por <strong>;</strong>.
                        <span id="contadorSelos" class="ms-2 fw-semibold"></span>
                    </div>
                </div>

                <hr>
                <div class="mb-2">
                    <label class="form-label">Anexos em comum (opcional)</label>
                    <div class="dropzone-container" id="dropzoneCadastro">
                        <div class="dz-message text-center p-3">  
                            <div class="upload-icon mb-2">
                                <i data-feather="upload-cloud" style="width: 36px; height: 36px; color: #6c757d;"></i>
                            </div>
                            <div class="mb-1">Arraste e solte arquivos aqui</div>
                            <div class="text-muted small">ou</div>
                            <button class="btn btn-outline-primary btn-sm mt-2" type="button" id="btnBrowseCadastro">Selecionar Arquivos</button>
                            <div class="mt-2 small text-muted">Formatos: PDF, JPG, JPEG, PNG (até 10MB por arquivo)</div>
                        </div>
                    </div>
                    <input type="file" name="anexos_comuns[]" id="inputAnexosComuns" class="d-none" multiple accept=".pdf,.jpg,.jpeg,.png">
                </div>
                <div id="cadastroPreview" class="mb-3 d-none">
                    <h6 class="mb-2">Arquivos selecionados</h6>
                    <div id="cadFilePreviewList"></div>
                </div>

                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="cadAnexarExistentes" name="anexar_em_existentes">
                    <label class="form-check-label" for="cadAnexarExistentes">
                        Também anexar aos selos já existentes (ativos) listados acima
                    </label>
                </div>
                <input type="hidden" name="cadastrar_selos_manual" value="1">
            </div>  
            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>  
                <button type="submit" class="btn btn-primary" id="btnCadastrarSelos">
                    <i data-feather="check-circle" class="me-1" style="width:16px;height:16px;"></i> Cadastrar
                </button>  
            </div>  
        </form>  
    </div>  
</div>  

<!-- Modal importar selos em lote -->
<div class="modal fade" id="importarSelosModal" tabindex="-1" aria-labelledby="importarSelosModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="formImportarSelos" class="modal-content" method="POST" action="importar_selos.php"
          enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title" id="importarSelosModalLabel">Importar Selos via XLSX</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Arquivo XLSX</label>
          <input type="file" name="xlsx_file" accept=".xlsx,.xls" class="form-control" required>
          <div class="form-text">Tamanho máximo 10 MB.</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Coluna do número do selo (1 = A, 2 = B…)</label>
          <input type="number" name="coluna_selo" class="form-control" value="3" min="1">
        </div>

        <div class="form-check mb-3">
          <input type="checkbox" class="form-check-input" id="skipHeader" name="pular_cabecalho" checked>
          <label class="form-check-label" for="skipHeader">Pular primeira linha (cabeçalho)</label>
        </div>

        <!-- ===== NOVO: anexos comuns ===== -->
        <hr>
        <div class="mb-2">
          <label class="form-label">Anexos em comum (opcional)</label>
          <input type="file" name="anexos_comuns[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple>
          <div class="form-text">
            Esses arquivos serão <strong>copiados para cada selo processado</strong> (novos e restaurados).
            Tamanho máximo: 10 MB por arquivo. Tipos: PDF, JPG, JPEG, PNG.
          </div>
        </div>
        <div class="form-check">
          <input type="checkbox" class="form-check-input" id="applyExisting" name="anexar_em_existentes">
          <label class="form-check-label" for="applyExisting">
            Também anexar aos selos já existentes (ativos) presentes no arquivo
          </label>
        </div>

        <input type="hidden" name="importar_selos" value="1">
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-success" id="btnImportarSubmit">
          <i data-feather="check-circle" class="me-1" style="width:16px;height:16px;"></i> Importar
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal de progresso da importação (XLSX) -->
<div class="modal fade" id="importProgressModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Importando selos...</h5>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center mb-3">
          <div class="spinner-border me-2" role="status" aria-hidden="true"></div>
          <div id="importStageText" class="fw-semibold">Preparando...</div>
        </div>
        <div class="progress">
          <div id="importProgressBar" class="progress-bar progress-bar-striped" role="progressbar" style="width: 0%">0%</div>
        </div>
        <div id="importDetailText" class="small text-muted mt-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" id="btnImportCancel" class="btn btn-outline-secondary" disabled>Cancelar</button>
      </div>
    </div>
  </div>
</div>

<!-- NOVO: Modal de progresso do cadastro manual -->
<div class="modal fade" id="cadastroProgressModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Cadastrando selos...</h5>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center mb-3">
          <div class="spinner-border me-2" role="status" aria-hidden="true"></div>
          <div id="cadastroStageText" class="fw-semibold">Preparando...</div>
        </div>
        <div class="progress">
          <div id="cadastroProgressBar" class="progress-bar progress-bar-striped" role="progressbar" style="width: 0%">0%</div>
        </div>
        <div id="cadastroDetailText" class="small text-muted mt-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" id="btnCadastroCancel" class="btn btn-outline-secondary" disabled>Cancelar</button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Bloco Único de Scripts (sem duplicar jQuery) ===== -->
<script>
/* Utilitários para carregar CSS/JS sob demanda */
function loadCSS(href){
  return new Promise((res, rej) => {
    if ([...document.styleSheets].some(s => s.href && s.href.includes(href))) return res();
    const l = document.createElement('link');
    l.rel = 'stylesheet'; l.href = href;
    l.onload = () => res(); l.onerror = () => rej(new Error('Falha CSS: '+href));
    document.head.appendChild(l);
  });
}
function loadScript(src){
  return new Promise((res, rej) => {
    if (document.querySelector('script[src="'+src+'"]')) return res();
    const s = document.createElement('script');
    s.src = src; s.async = false;
    s.onload = () => res(); s.onerror = () => rej(new Error('Falha JS: '+src));
    document.head.appendChild(s);
  });
}

/* ====== Inicializações que dependem de jQuery+DataTables ====== */
async function initWithjQueryAndDataTables(){
  // Se não houver jQuery (caso seu footer não carregue), carrega fallback:
  if (!window.jQuery) {
    await loadScript('https://code.jquery.com/jquery-3.6.0.min.js');
  }
  const $ = window.jQuery;

  // CSS do DataTables (para visual elegante com Bootstrap 5)
  await Promise.all([
    loadCSS('https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css'),
    loadCSS('https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css'),
    loadCSS('https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css')
  ]);

  // JS do DataTables (depois do jQuery definitivo)
  await loadScript('https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js');
  await loadScript('https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js');
  await loadScript('https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js');
  await loadScript('https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js');
  await loadScript('https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js');
  await loadScript('https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js');

  if (window.feather) feather.replace();

  // === Tabela de anexos no modo edição ===
  if ($('#tabelaAnexos').length) {
    $('#tabelaAnexos').DataTable({
      responsive: true,
      pageLength: 10,
      order: [[4, 'desc']], // coluna "Data" usa data-order ISO
      language: dtLang()
    });
  }

  // === Lista (desktop) ===
  if ($('#tabelaListaSelos').length) {
    const table = $('#tabelaListaSelos').DataTable({
      responsive: true,
      pageLength: 10,
      lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Todos"]],
      order: [[1, 'desc']], // Data (usa data-order ISO)
      language: dtLang(),
      columnDefs: [
        { orderable: false, targets: -1 } // ações
      ],
      initComplete: function(){
        const inicial = $('#filtroNumeroSelo').val();
        if (inicial) table.search(inicial).draw();
      }
    });

    // Busca em tempo real + sincroniza com cards mobile
    $('#filtroNumeroSelo').on('keyup input', function(){
      table.search(this.value).draw();
      filtrarCards(this.value);
    });
    $('#btnLimparFiltro').on('click', function(){
      $('#filtroNumeroSelo').val('');
      table.search('').draw();
      filtrarCards('');
    });

    table.on('draw', function(){ if (window.feather) feather.replace(); });
  } else {
    // Página sem tabela (ex.: cards/mobile-only)
    const filtro = document.getElementById('filtroNumeroSelo');
    const limpar = document.getElementById('btnLimparFiltro');
    if (filtro) filtro.addEventListener('input', e => filtrarCards(e.target.value));
    if (limpar) limpar.addEventListener('click', () => { if (filtro) filtro.value=''; filtrarCards(''); });
  }

  // === Ações comuns (jQuery) ===
  $(document).on('click', '.excluir-anexo', function(){
    const id   = $(this).data('id');
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
    }).then(r => { if (r.isConfirmed) location.href = `excluir_anexo.php?id=${id}`; });
  });

  $(document).on('click', '.excluir-selo', function(){
    const id     = $(this).data('id');
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
    }).then(r => { if (r.isConfirmed) location.href = `excluir_selo.php?id=${id}`; });
  });

  $(document).on('submit', '.form-marcar-enviado, #formMarcarEnviado', function(e){
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
            Swal.fire({ icon:'success', title:'Marcado como enviado!', timer:1500, showConfirmButton:false })
              .then(() => location.reload());
          } else {
            Swal.fire('Erro', response.message || 'Não foi possível marcar como enviado.', 'error');
          }
        }, 'json').fail(() => {
          Swal.fire('Erro', 'Falha na comunicação com o servidor.', 'error');
        });
      }
    });
  });
}

// Idioma do DataTables
function dtLang(){
  return {
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
  };
}

/* ====== Código sem jQuery (pode rodar já) ====== */

// Dropzone do selo individual (de-duplicação + envio robusto)
document.addEventListener('DOMContentLoaded', function(){  
  if (window.feather) feather.replace();

  const dropzone = document.getElementById('dropzoneUpload');  
  if (dropzone){
    // input apenas para abrir o seletor (sem name, não vai no POST)
    const fileInput        = document.createElement('input');  
    fileInput.type         = 'file';  
    fileInput.multiple     = true;  
    fileInput.accept       = '.pdf,.jpg,.jpeg,.png';  
    fileInput.style.display= 'none';  
    document.body.appendChild(fileInput);  

    const uploadForm       = document.getElementById('uploadForm');  
    const browseBtn        = document.querySelector('.browse-btn');  
    const submitBtn        = document.getElementById('submitUpload');  
    const previewContainer = document.getElementById('preview-container');  
    const previewList      = document.getElementById('file-preview-list');  

    // cache + helpers
    let selectedUploadFiles = []; // File[]
    const validTypes = ['application/pdf','image/jpeg','image/jpg','image/png'];
    const MAX = 10*1024*1024;
    const sig = f => `${f.name}::${f.size}::${f.lastModified}`;

    function renderUploadPreview(){
      previewList.innerHTML = '';
      if (selectedUploadFiles.length === 0){
        previewContainer.classList.add('d-none');
        return;
      }
      previewContainer.classList.remove('d-none');
      selectedUploadFiles.forEach(file => {
        const item = document.createElement('div');
        item.className = 'file-preview-item d-flex align-items-center gap-2 border rounded p-2 mb-2';
        const iconName = file.type === 'application/pdf' ? 'file-text' : (file.type.startsWith('image/') ? 'image' : 'file');
        const fileSize = formatFileSize(file.size);
        const signature = sig(file);
        item.innerHTML = `
          <div class="file-icon"><i data-feather="${iconName}" style="width:18px;height:18px;"></i></div>
          <div class="file-info flex-fill">
            <div class="file-name fw-semibold">${file.name}</div>
            <div class="file-size small text-muted">${fileSize}</div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-secondary file-remove" data-sign="${signature}">
            <i data-feather="x" style="width:16px;height:16px;"></i>
          </button>
        `;
        previewList.appendChild(item);
      });
      if (window.feather) feather.replace();
      previewList.querySelectorAll('.file-remove').forEach(btn=>{
        btn.addEventListener('click', () => {
          const sign = btn.getAttribute('data-sign');
          selectedUploadFiles = selectedUploadFiles.filter(f => sig(f)!==sign);
          submitBtn.disabled = selectedUploadFiles.length === 0;
          renderUploadPreview();
        });
      });
    }

    function addUploadFiles(list){
      const arr = Array.from(list || []);
      let added = 0;
      arr.forEach(file => {
        if (!validTypes.includes(file.type)) { showToast('Tipo de arquivo não suportado: '+file.name,'error'); return; }
        if (file.size > MAX) { showToast('Arquivo muito grande: '+file.name,'error'); return; }
        if (!selectedUploadFiles.some(f => sig(f)===sig(file))) {
          selectedUploadFiles.push(file);
          added++;
        }
      });
      if (added>0){
        submitBtn.disabled = selectedUploadFiles.length === 0;
        renderUploadPreview();
      }
    }

    function formatFileSize(bytes){
      if (bytes === 0) return '0 Bytes';
      const k = 1024, sizes = ['Bytes','KB','MB','GB'];  
      const i = Math.floor(Math.log(bytes)/Math.log(k));  
      return parseFloat((bytes/Math.pow(k,i)).toFixed(2))+' '+sizes[i];
    }

    browseBtn?.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', function(){ addUploadFiles(this.files); this.value=''; });

    ['dragenter','dragover','dragleave','drop'].forEach(evt => {
      dropzone.addEventListener(evt, function(e){ e.preventDefault(); e.stopPropagation(); }, false);
    });
    ['dragenter','dragover'].forEach(evt => dropzone.addEventListener(evt, () => dropzone.classList.add('highlight'), false));
    ['dragleave','drop'].forEach(evt => dropzone.addEventListener(evt, () => dropzone.classList.remove('highlight'), false));
    dropzone.addEventListener('drop', (e) => addUploadFiles(e.dataTransfer.files), false);

    uploadForm.addEventListener('submit', function(e){
      e.preventDefault();

      if (selectedUploadFiles.length === 0) {
        Swal.fire('Erro', 'Selecione pelo menos um arquivo para enviar.', 'error');
        return;
      }

      const progressContainer = document.getElementById('progressContainer');  
      const progressBar       = document.getElementById('progressBar');  
      const uploadStatus      = document.getElementById('uploadStatus');  
      const seloId            = uploadForm.querySelector('input[name="selo_id"]').value;

      progressContainer.classList.remove('d-none');  
      submitBtn.disabled = true;  

      // monta FormData manualmente (evita campo vazio no $_FILES)
      const formData = new FormData();  
      formData.append('selo_id', seloId);
      selectedUploadFiles.forEach(f => formData.append('arquivos[]', f, f.name));

      const xhr = new XMLHttpRequest();  
      xhr.open('POST', 'upload_anexo.php', true);  

      xhr.upload.addEventListener('progress', function(e){
        if (e.lengthComputable){
          const pct = Math.round((e.loaded / e.total) * 100);
          progressBar.style.width = pct + '%';
          progressBar.textContent = pct + '%';
          uploadStatus.textContent = `Enviando arquivos... ${formatFileSize(e.loaded)} de ${formatFileSize(e.total)}`;
        }
      });

      xhr.onload = function(){
        uploadStatus.textContent = 'Upload finalizado';
        if (xhr.status === 200){
          let response;
          try { response = JSON.parse(xhr.responseText); }
          catch(e){
            console.error('Resposta inválida:', xhr.responseText);
            Swal.fire('Erro', 'Resposta inválida do servidor.', 'error');
            submitBtn.disabled = false;
            return;
          }

          if (response.success){
            Swal.fire({ icon:'success', title:'Sucesso!', text: response.message || 'Upload realizado com sucesso!', timer:1500, showConfirmButton:false })
                .then(() => { window.location.reload(); });
          } else {
            Swal.fire('Erro no Upload', response.message || 'Falha ao enviar arquivos.', 'error');
            submitBtn.disabled = false;
          }
        } else {
          Swal.fire('Erro', 'Falha na comunicação com o servidor. Código: ' + xhr.status, 'error');
          submitBtn.disabled = false;
        }
      };

      xhr.onerror = function(){
        Swal.fire('Erro', 'Falha na conexão com o servidor.', 'error');
        submitBtn.disabled = false;
        uploadStatus.textContent = 'Upload falhou';
      };

      xhr.send(formData);
    });
  }
});

/* Toast helper */
function showToast(message, type = 'info') {  
  if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {  
    const toastEl = document.createElement('div');  
    toastEl.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;  
    toastEl.setAttribute('role','alert');  
    toastEl.setAttribute('aria-live','assertive');  
    toastEl.setAttribute('aria-atomic','true');  
    toastEl.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    `;
    let container = document.querySelector('.toast-container');
    if (!container){
      container = document.createElement('div');
      container.className = 'toast-container position-fixed top-0 end-0 p-3';
      document.body.appendChild(container);
    }
    container.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: 5000 });  
    toast.show();  
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());  
  } else {  
    if (type === 'error') alert('Erro: ' + message);
    else alert(message);
  }  
}

/* Cópia do número do selo (sem jQuery) */
document.addEventListener('DOMContentLoaded', function(){  
  const copyButtons = document.querySelectorAll('.copy-button');  
  copyButtons.forEach(button => {  
    button.addEventListener('click', function(){  
      const textToCopy = this.getAttribute('data-clipboard-text');  
      if (navigator.clipboard && navigator.clipboard.writeText) {  
        navigator.clipboard.writeText(textToCopy)
          .then(() => showCopiedFeedback(this))
          .catch(err => console.error('Erro ao copiar: ', err));  
      } else {
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

  function showCopiedFeedback(button){
    button.classList.add('copied');
    const iconElement = button.querySelector('[data-feather]');
    if (iconElement && window.feather){
      const originalIcon = iconElement.getAttribute('data-feather');
      iconElement.setAttribute('data-feather', 'check');
      feather.replace();
      setTimeout(() => {
        iconElement.setAttribute('data-feather', originalIcon);
        feather.replace();
        button.classList.remove('copied');
      }, 2000);
    } else {
      setTimeout(() => button.classList.remove('copied'), 2000);
    }
  }
});

/* Filtro para Cards (mobile) */
function filtrarCards(texto){
  const query = (texto || '').toLowerCase();
  document.querySelectorAll('#cardsSelos .selo-card').forEach(card => {
    const numero = card.getAttribute('data-numero') || '';
    card.style.display = numero.includes(query) ? '' : 'none';
  });
}

/* === Envio AJAX com barra de progresso para Importar XLSX === */
document.addEventListener('DOMContentLoaded', function(){
  const form = document.getElementById('formImportarSelos');
  if (!form) return;

  const progressModalEl = document.getElementById('importProgressModal');
  const progressModal = new bootstrap.Modal(progressModalEl, { backdrop:'static', keyboard:false });
  const bar = document.getElementById('importProgressBar');
  const stage = document.getElementById('importStageText');
  const detail = document.getElementById('importDetailText');
  const btnCancel = document.getElementById('btnImportCancel');
  const btnSubmit = document.getElementById('btnImportarSubmit');

  let currentXHR = null;

  function setProgress(pct, text){
    bar.style.width = pct + '%';
    bar.textContent = pct + '%';
    if (typeof text === 'string') stage.textContent = text;
  }
  function setIndeterminate(on){
    bar.classList.toggle('progress-bar-striped', on);
    bar.classList.toggle('progress-bar-animated', on);
  }

  form.addEventListener('submit', function(e){
    e.preventDefault();

    const fd = new FormData(form);
    const xhr = new XMLHttpRequest();
    currentXHR = xhr;

    // UI inicial
    setProgress(0, 'Iniciando envio...');
    setIndeterminate(false);
    detail.textContent = '';
    btnCancel.disabled = false;
    btnSubmit.disabled = true;

    progressModal.show();

    xhr.open('POST', form.getAttribute('action'), true);
    xhr.setRequestHeader('X-Requested-With','XMLHttpRequest'); // força retorno JSON

    xhr.upload.onprogress = function(ev){
      if (ev.lengthComputable){
        const pct = Math.round((ev.loaded/ev.total)*100);
        setProgress(Math.min(pct, 90), 'Enviando arquivos...');
        detail.textContent = `Enviado ${Math.round(ev.loaded/1024)} KB de ${Math.round(ev.total/1024)} KB`;
      }
    };
    xhr.upload.onload = function(){
      setProgress(90, 'Upload concluído. Processando planilha e anexos...');
      setIndeterminate(true);
      detail.textContent = 'Isso pode levar alguns minutos dependendo da quantidade de selos e anexos.';
    };
    xhr.onloadstart = function(){
      setProgress(5, 'Preparando envio...');
    };

    xhr.onreadystatechange = function(){
      if (xhr.readyState === 4){
        setIndeterminate(false);
        btnCancel.disabled = true;
        btnSubmit.disabled = false;

        if (xhr.status === 200){
          let resp;
          try { resp = JSON.parse(xhr.responseText); }
          catch(err){
            progressModal.hide();
            Swal.fire('Erro', 'Resposta inválida do servidor.', 'error');
            return;
          }

          setProgress(100, 'Finalizando...');
          progressModal.hide();

          if (resp.success){
            const msg = (resp.message || 'Importação concluída com sucesso!');
            Swal.fire({icon:'success', title:'Concluído!', html: `<div class="text-start">${msg}</div>`, confirmButtonText:'OK'})
              .then(()=>{ window.location.reload(); });
          } else {
            const errs = (resp.errors && resp.errors.length)
              ? `<div class="alert alert-warning mt-2" style="max-height:200px;overflow:auto;"><div class="small">${resp.errors.map(e=>`• ${e}`).join('<br>')}</div></div>`
              : '';
            Swal.fire({icon:'error', title:'Falha na importação', html: `<div class="text-start">${resp.message||'Tente novamente.'}${errs}</div>`});
          }
        } else {
          progressModal.hide();
          Swal.fire('Erro', `Falha na comunicação com o servidor (HTTP ${xhr.status}).`, 'error');
        }
      }
    };

    xhr.onerror = function(){
      setIndeterminate(false);
      btnCancel.disabled = true;
      btnSubmit.disabled = false;
      progressModal.hide();
      Swal.fire('Erro', 'Falha na conexão durante a importação.', 'error');
    };

    xhr.ontimeout = function(){
      setIndeterminate(false);
      btnCancel.disabled = true;
      btnSubmit.disabled = false;
      progressModal.hide();
      Swal.fire('Erro', 'Tempo de importação excedido.', 'error');
    };

    xhr.send(fd);
  });

  btnCancel.addEventListener('click', function(){
    if (currentXHR){
      try { currentXHR.abort(); } catch(e){}
      currentXHR = null;
    }
    btnCancel.disabled = true;
    setIndeterminate(false);
    stage.textContent = 'Cancelado pelo usuário';
    bar.classList.remove('bg-success');
    bar.classList.add('bg-secondary');
    progressModal.hide();
    Swal.fire('Importação cancelada', 'Nenhum dado foi alterado.', 'info');
  });
});

/* === Cadastro manual com validação, dropzone e barra de progresso === */
document.addEventListener('DOMContentLoaded', function(){
  const form = document.getElementById('formCadastrarSelosManual');
  if (!form) return;

  const inputNumeros = document.getElementById('numerosSelos');
  const contadorSelos = document.getElementById('contadorSelos');

  function normalizarEntrada(v){
    v = (v || '').replace(/,/g, ';'); // vírgula -> ;
    v = v.replace(/\s+/g, '');       // remove espaços
    v = v.replace(/;{2,}/g, ';');    // colapsa ;;;
    return v;
  }
  function validar(v){
    if (!v) return false;
    return /^[A-Za-z0-9]+(?:;[A-Za-z0-9]+)*$/.test(v);
  }
  function atualizarContador(v){
    const q = v ? v.split(';').filter(Boolean).length : 0;
    contadorSelos.textContent = q ? `(${q} selo${q>1?'s':''})` : '';
  }

  inputNumeros.addEventListener('input', function(){
    const norm = normalizarEntrada(this.value);
    if (this.value !== norm) this.value = norm;
    const ok = validar(this.value);
    this.classList.toggle('is-invalid', !ok && this.value.length>0);
    atualizarContador(this.value);
    document.getElementById('btnCadastrarSelos').disabled = !ok;
  });

  // Dropzone para anexos comuns (COM de-duplicação)
  const dz = document.getElementById('dropzoneCadastro');
  const inputFiles = document.getElementById('inputAnexosComuns');
  const btnBrowse = document.getElementById('btnBrowseCadastro');
  const prevWrap = document.getElementById('cadastroPreview');
  const prevList = document.getElementById('cadFilePreviewList');

  let selectedFiles = []; // File[]
  const validTypes = ['application/pdf','image/jpeg','image/jpg','image/png'];
  const MAX = 10*1024*1024;
  const sig = f => `${f.name}::${f.size}::${f.lastModified}`;

  btnBrowse.addEventListener('click', () => inputFiles.click());

  ['dragenter','dragover','dragleave','drop'].forEach(evt => {
    dz.addEventListener(evt, function(e){ e.preventDefault(); e.stopPropagation(); }, false);
  });
  ['dragenter','dragover'].forEach(evt => dz.addEventListener(evt, () => dz.classList.add('highlight'), false));
  ['dragleave','drop'].forEach(evt => dz.addEventListener(evt, () => dz.classList.remove('highlight'), false));
  dz.addEventListener('drop', e => addFiles(e.dataTransfer.files));
  inputFiles.addEventListener('change', e => { addFiles(e.target.files); e.target.value=''; });

  function addFiles(files){
    const arr = Array.from(files || []);
    let added = 0;
    arr.forEach(file => {
      const okType = validTypes.includes(file.type);
      if (!okType) { showToast('Tipo não suportado: '+file.name,'error'); return; }
      if (file.size > MAX) { showToast('Arquivo excede 10MB: '+file.name,'error'); return; }
      if (!selectedFiles.some(f => sig(f)===sig(file))) {
        selectedFiles.push(file);
        added++;
      }
    });
    if (added>0) syncInputFromCache();
  }

  function syncInputFromCache(){
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    inputFiles.files = dt.files;
    renderPreview();
  }

  function renderPreview(){
    prevList.innerHTML = '';
    if (!selectedFiles.length){ prevWrap.classList.add('d-none'); return; }
    prevWrap.classList.remove('d-none');
    selectedFiles.forEach(file => {
      const item = document.createElement('div');
      item.className = 'file-preview-item d-flex align-items-center gap-2 border rounded p-2 mb-2';
      const iconName = file.type === 'application/pdf' ? 'file-text' : (file.type.startsWith('image/') ? 'image' : 'file');
      const size = (file.size/1024/1024).toFixed(2)+' MB';
      const signature = sig(file);
      item.innerHTML = `
        <div class="file-icon"><i data-feather="${iconName}" style="width:18px;height:18px;"></i></div>
        <div class="file-info flex-fill">
          <div class="file-name fw-semibold">${file.name}</div>
          <div class="file-size small text-muted">${size}</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary file-remove" data-sign="${signature}"><i data-feather="x" style="width:16px;height:16px;"></i></button>
      `;
      prevList.appendChild(item);
    });
    if (window.feather) feather.replace();
    prevList.querySelectorAll('.file-remove').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const sign = btn.getAttribute('data-sign');
        selectedFiles = selectedFiles.filter(f => sig(f)!==sign);
        syncInputFromCache();
      });
    });
  }

  // Barra de progresso (cadastro manual)
  const progModalEl = document.getElementById('cadastroProgressModal');
  const progModal   = new bootstrap.Modal(progModalEl, { backdrop:'static', keyboard:false });
  const bar         = document.getElementById('cadastroProgressBar');
  const stage       = document.getElementById('cadastroStageText');
  const detail      = document.getElementById('cadastroDetailText');
  const btnCancel   = document.getElementById('btnCadastroCancel');
  const btnSubmit   = document.getElementById('btnCadastrarSelos');

  let currentXHR = null;
  function setProgress(p, t){
    bar.style.width = p + '%';
    bar.textContent = p + '%';
    if (typeof t === 'string') stage.textContent = t;
  }
  function setIndeterminate(on){
    bar.classList.toggle('progress-bar-striped', on);
    bar.classList.toggle('progress-bar-animated', on);
  }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    const norm = inputNumeros.value = (inputNumeros.value || '')
      .replace(/,/g,';').replace(/\s+/g,'').replace(/;{2,}/g,';').replace(/^;|;$/g,'');
    if (!/^[A-Za-z0-9]+(?:;[A-Za-z0-9]+)*$/.test(norm)) {
      inputNumeros.classList.add('is-invalid');
      inputNumeros.reportValidity?.();
      return;
    }

    // Monta o FormData e garante anexos comuns do cache (sem depender de input.files programático)
    const fd = new FormData(form);
    if (selectedFiles.length) {
      try { fd.delete('anexos_comuns[]'); } catch (e) {}
      selectedFiles.forEach(f => fd.append('anexos_comuns[]', f, f.name));
    }

    const xhr = new XMLHttpRequest();
    currentXHR = xhr;

    setProgress(0, 'Iniciando envio...');
    setIndeterminate(false);
    detail.textContent = '';
    btnCancel.disabled = false;
    btnSubmit.disabled = true;
    progModal.show();

    xhr.open('POST', form.getAttribute('action'), true);
    xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');

    xhr.upload.onprogress = function(ev){
      if (ev.lengthComputable){
        const pct = Math.round((ev.loaded/ev.total)*100);
        setProgress(Math.min(pct, 90), 'Enviando dados...');
        detail.textContent = `Enviado ${Math.round(ev.loaded/1024)} KB de ${Math.round(ev.total/1024)} KB`;
      }
    };
    xhr.upload.onload = function(){
      setProgress(90, 'Upload concluído. Processando selos e anexos...');
      setIndeterminate(true);
      detail.textContent = 'Aguarde enquanto criamos/restauramos os selos e copiamos os anexos.';
    };
    xhr.onloadstart = function(){ setProgress(5, 'Preparando...'); };

    xhr.onreadystatechange = function(){
      if (xhr.readyState === 4){
        setIndeterminate(false);
        btnCancel.disabled = true;
        btnSubmit.disabled = false;

        if (xhr.status === 200){
          let resp;
          try { resp = JSON.parse(xhr.responseText); }
          catch(err){
            progModal.hide();
            Swal.fire('Erro', 'Resposta inválida do servidor.', 'error');
            return;
          }

          setProgress(100, 'Finalizando...');
          progModal.hide();

          if (resp.success){
            const msg = resp.message || 'Cadastro concluído com sucesso!';
            const errs = (resp.errors && resp.errors.length)
              ? `<div class="alert alert-warning mt-2" style="max-height:200px;overflow:auto;"><div class="small">${resp.errors.map(e=>`• ${e}`).join('<br>')}</div></div>` : '';
            Swal.fire({icon:'success', title:'Concluído!', html:`<div class="text-start">${msg}${errs}</div>`})
              .then(()=> location.reload());
          } else {
            const errs = (resp.errors && resp.errors.length)
              ? `<div class="alert alert-warning mt-2" style="max-height:200px;overflow:auto;"><div class="small">${resp.errors.map(e=>`• ${e}`).join('<br>')}</div></div>` : '';
            Swal.fire({icon:'error', title:'Falha no cadastro', html:`<div class="text-start">${resp.message||'Tente novamente.'}${errs}</div>`});
          }
        } else {
          progModal.hide();
          Swal.fire('Erro', `Falha na comunicação com o servidor (HTTP ${xhr.status}).`, 'error');
        }
      }
    };

    xhr.onerror = function(){
      setIndeterminate(false);
      btnCancel.disabled = true;
      btnSubmit.disabled = false;
      progModal.hide();
      Swal.fire('Erro', 'Falha na conexão durante o cadastro.', 'error');
    };

    xhr.send(fd);
  });

  btnCancel.addEventListener('click', function(){
    if (currentXHR){
      try { currentXHR.abort(); } catch(e){}
      currentXHR = null;
    }
    btnCancel.disabled = true;
    setIndeterminate(false);
    stage.textContent = 'Cancelado pelo usuário';
    bar.classList.remove('bg-success');
    bar.classList.add('bg-secondary');
    progModal.hide();
    Swal.fire('Cadastro cancelado', 'Nenhum dado foi alterado.', 'info');
  });
});

/* Garante que DataTables carregue só após jQuery final da página */
window.addEventListener('load', function(){ 
  initWithjQueryAndDataTables().catch(err => {
    console.error(err);
    showToast('Falha ao carregar DataTables. Verifique a conexão de rede ou CSP.', 'error');
  });
});
</script>

<?php include 'includes/footer.php'; ?>   
