<?php
/***************************************************************************
 *  mandados.php  ·  Gestão de Mandados Judiciais
 *  -----------------------------------------------------------------------
 *  • Cadastro, filtros e gerenciamento
 *  • Coluna Status interativa (Pendente | Cumprido | Excluir) – AJAX
 *  • Upload múltiplo de anexos + preview em modal
 *  • Código único (verificação em tempo-real)
 *  © 2025 – Seu Projeto
 ***************************************************************************/

date_default_timezone_set('America/Sao_Paulo');
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

/* ------------------------------------------------------------------------
 * HELPERS
 * ---------------------------------------------------------------------- */
function badgeMandado(string $s): array
{
    return match ($s) {
        'cumprido' => ['bg-success text-white', 'check-circle'],
        'pendente' => ['bg-warning text-dark', 'clock'],
        default    => ['bg-secondary text-white', 'help-circle']
    };
}

/* ------------------------------------------------------------------------
 * 0. AJAX – alterar status
 * ---------------------------------------------------------------------- */
if (
    isset($_POST['atualizar_status'], $_POST['id'], $_POST['status'])
    && in_array($_POST['status'], ['pendente', 'cumprido', 'excluido'], true)
) {
    header('Content-Type: application/json');

    $ok = $pdo->prepare("
        UPDATE mandados SET
               status        = ?,
               cumprido_por  = IF(?='cumprido', ?, NULL),
               data_cumprido = IF(?='cumprido', NOW(), NULL)
         WHERE id = ? AND status != 'excluido'
    ")->execute([
        $_POST['status'],
        $_POST['status'], $_SESSION['usuario_id'],
        $_POST['status'],
        (int) $_POST['id']
    ]);

    echo json_encode(['success' => $ok]);
    exit;
}

/* ------------------------------------------------------------------------
 * 1. POST – novo mandado
 * ---------------------------------------------------------------------- */
if (isset($_POST['cadastrar_mandado'])) {

    $codigo  = trim($_POST['codigo_rastreabilidade'] ?? '');
    $remet   = trim($_POST['remetente'] ?? '');
    $motivo  = trim($_POST['motivo_envio'] ?? '');
    $dataEnv = $_POST['data_envio'] ?: date('Y-m-d');
    $origem  = $_POST['origem'] ?? '';
    $assunto = trim($_POST['assunto'] ?? '');

    /* normalizar assunto */
    $assunto = preg_replace(['/[\r\n]+/', '/\s{2,}/', '/\s+,/', '/\s+\./'], [' ', ' ', ',', '.'], $assunto);

    if ($codigo === '' || $origem === '') {
        header('Location: mandados.php?erro=1');
        exit;
    }

    try {
        $pdo->prepare("
            INSERT INTO mandados
                  (codigo_rastreabilidade, remetente, motivo_envio,
                   data_envio, origem, assunto, usuario_id,
                   status, data_cadastro)
            VALUES (?,?,?,?,?,?,?,'pendente',NOW())
        ")->execute([
            $codigo, $remet, $motivo, $dataEnv,
            $origem, $assunto, $_SESSION['usuario_id']
        ]);

        header('Location: mandados.php?id=' . $pdo->lastInsertId() . '&success=1');
        exit;

    } catch (PDOException $e) {
        /* 23000 → chave única violada */
        if ($e->getCode() === '23000') {
            $idDup = $pdo->prepare("SELECT id FROM mandados
                                     WHERE codigo_rastreabilidade=? AND status!='excluido'");
            $idDup->execute([$codigo]);
            $idDup = $idDup->fetchColumn();
            header('Location: mandados.php?dup=1&cod=' . urlencode($codigo) . '&id=' . $idDup);
            exit;
        }
        header('Location: mandados.php?erro=db');
        exit;
    }
}

/* ------------------------------------------------------------------------
 * 2.  MODO EDIÇÃO
 * ---------------------------------------------------------------------- */
$modo_edicao = false;
$mandado     = null;
$anexos      = [];
$prevId = $nextId = null;

if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $id = (int) $_GET['id'];

    $stmt = $pdo->prepare("
        SELECT m.*, u1.nome AS nome_usuario, u2.nome AS cumprido_por_nome
          FROM mandados m
     LEFT JOIN usuarios u1 ON u1.id = m.usuario_id
     LEFT JOIN usuarios u2 ON u2.id = m.cumprido_por
         WHERE m.id = ? AND m.status != 'excluido'
    ");
    $stmt->execute([$id]);
    $mandado = $stmt->fetch();

    if ($mandado) {
        $modo_edicao = true;

        $stmt = $pdo->prepare("
            SELECT *
              FROM mandados_anexos
             WHERE mandado_id = ? AND status = 'ativo'
          ORDER BY data_upload DESC
        ");
        $stmt->execute([$id]);
        $anexos = $stmt->fetchAll();

        $prevId = $pdo->query("
            SELECT id FROM mandados
             WHERE id < $id AND status!='excluido'
          ORDER BY id DESC LIMIT 1
        ")->fetchColumn();

        $nextId = $pdo->query("
            SELECT id FROM mandados
             WHERE id > $id AND status!='excluido'
          ORDER BY id ASC LIMIT 1
        ")->fetchColumn();
    }
}

/* ------------------------------------------------------------------------
 * 3.  LISTA (quando não edição)
 * ---------------------------------------------------------------------- */
$f_status  = $_GET['status']    ?? 'pendente';
$f_codigo  = trim($_GET['codigo']    ?? '');
$f_remet   = trim($_GET['remetente'] ?? '');
$f_origem  = $_GET['origem']    ?? '';

$where  = ["m.status!='excluido'"];
$params = [];

if ($f_status !== 'todos') { $where[] = 'm.status = ?';                      $params[] = $f_status; }
if ($f_codigo)             { $where[] = 'm.codigo_rastreabilidade LIKE ?';   $params[] = "%$f_codigo%"; }
if ($f_remet)              { $where[] = 'm.remetente LIKE ?';               $params[] = "%$f_remet%"; }
if ($f_origem)             { $where[] = 'm.origem = ?';                      $params[] = $f_origem; }

$lista = $pdo->prepare("
    SELECT m.*
      FROM mandados m
     WHERE " . implode(' AND ', $where) . "
  ORDER BY m.data_cadastro DESC
");
$lista->execute($params);
$mandados = $lista->fetchAll();

/*  contadores para os cards  */
[$contPend, $contCum] = [0, 0];
foreach ($pdo->query("
        SELECT status, COUNT(*) c FROM mandados
         WHERE status!='excluido'
      GROUP BY status") as $r) {
    if ($r['status'] == 'pendente') $contPend = $r['c'];
    if ($r['status'] == 'cumprido') $contCum = $r['c'];
}

/* ------------------------------------------------------------------------
 * 4.  INTERFACE
 * ---------------------------------------------------------------------- */
include 'includes/header.php';
?>
<?php include(__DIR__ . '/css/style-comunicacoes.php'); ?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">

<!-- ~~ status pill elegante ~~ -->
<style>
  .status-pill{border-radius:1.5rem;padding:1.25rem 1.75rem;}
  .status-pendente{background:linear-gradient(135deg,#fff8e1,#ffe08a);}
  .status-cumprido{background:linear-gradient(135deg,#e7f9f0,#b2f2d8);}
  .status-pill h3{margin-bottom:0}
</style>

<?php /* SweetAlert duplicidade / sucesso */ ?>
<?php if (isset($_GET['dup'], $_GET['cod']) && $_GET['dup'] == 1): ?>
<script>
Swal.fire({
  icon:'warning',
  title:'Código já cadastrado!',
  html:`O código <strong><?=htmlspecialchars($_GET['cod'])?></strong> já existe.<br>Deseja visualizá-lo?`,
  showCancelButton:true,
  confirmButtonText:'Visualizar'
}).then(r=>{if(r.isConfirmed)location='mandados.php?id=<?=intval($_GET['id'])?>';});
</script>
<?php elseif (isset($_GET['success'])): ?>
<script>
Swal.fire({icon:'success',title:'Mandado cadastrado!',timer:1500,showConfirmButton:false});
</script>
<?php endif; ?>

<div class="container-fluid py-4 animate-fadeIn">
<!-- ============================================================= -->
<!--  CABEÇALHO                                                    -->
<!-- ============================================================= -->
<div class="row mb-4">
  <div class="col-12 d-flex flex-wrap justify-content-between align-items-center">
    <div>
      <h1 class="fw-bold text-gray-800">
        <i data-feather="file" class="me-2 text-primary"></i>
        <?= $modo_edicao ? 'Gerenciar Mandado' : 'Mandados Judiciais' ?>
      </h1>
      <p class="text-muted lead fs-6">
        <?= $modo_edicao ? 'Adicione anexos ou marque como cumprido'
                         : 'Cadastre, pesquise e gerencie seus mandados judiciais' ?>
      </p>
    </div>

    <div class="d-flex gap-2">
      <?php if ($modo_edicao): ?>
        <div class="btn-group">
          <?php if ($prevId): ?>
            <a href="mandados.php?id=<?= $prevId ?>" class="btn btn-outline-primary"><i data-feather="chevron-left"></i></a>
          <?php endif; ?>
          <a href="mandados.php" class="btn btn-outline-secondary"><i data-feather="list"></i></a>
          <?php if ($nextId): ?>
            <a href="mandados.php?id=<?= $nextId ?>" class="btn btn-outline-primary"><i data-feather="chevron-right"></i></a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novoMandadoModal">
        <i data-feather="plus" class="me-1"></i> Novo Mandado
      </button>
    </div>
  </div>
</div>

<!-- ============================================================= -->
<!--  MODAL  ·  NOVO MANDADO                                       -->
<!-- ============================================================= -->
<div class="modal fade" id="novoMandadoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post">
      <div class="modal-header">
        <h5 class="modal-title">Novo Mandado Judicial</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Código *</label>
            <input name="codigo_rastreabilidade" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Remetente</label>
            <input name="remetente" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">Motivo</label>
            <input name="motivo_envio" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">Data de Envio</label>
            <input type="date" name="data_envio" value="<?= date('Y-m-d') ?>" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">Origem *</label>
            <select name="origem" class="form-select" required>
              <option value="">Selecione…</option>
              <option>Malote digital</option>
              <option>Balcão</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Assunto</label>
            <textarea name="assunto" rows="4" class="form-control"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" name="cadastrar_mandado"><i data-feather="save" class="me-1"></i>Cadastrar</button>
      </div>
    </form>
  </div>
</div>

<!-- ############################################################## -->
<!--  MODO EDIÇÃO                                                   -->
<!-- ############################################################## -->
<?php if ($modo_edicao && $mandado): ?>
<?php [$badge, $ico] = badgeMandado($mandado['status']); ?>
<div class="status-pill status-<?= $mandado['status'] ?> mb-4 shadow-sm d-flex align-items-center">
  <div class="display-5 me-3"><i data-feather="<?= $ico ?>"></i></div>
  <div>
    <h3 class="fw-bold mb-0"><?= ucfirst($mandado['status']) ?></h3>
    <small class="text-muted">
      <?= $mandado['status'] == 'pendente' ? 'Aguardando cumprimento' : 'Cumprido' ?>
    </small>
  </div>
</div>

<div class="row">
  <!-- DETALHES ---------------------------------------------------- -->
  <div class="col-md-4 mb-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white"><h5 class="mb-0">Detalhes</h5></div>
      <div class="card-body">
<?php
$campo = function ($l, $v) {
    echo "<div class='mb-3'><label class='form-label text-muted'>$l</label>
          <div class='form-control bg-light' style='white-space:pre-wrap'>$v</div></div>";
};
$campo('Código',            $mandado['codigo_rastreabilidade']);
$campo('Remetente',         $mandado['remetente']);
$campo('Motivo',            $mandado['motivo_envio']);
$campo('Assunto',           $mandado['assunto']);
$campo('Origem',            $mandado['origem']);
$campo('Data de Envio',     date('d/m/Y', strtotime($mandado['data_envio'])));
$campo('Cadastrado por',    $mandado['nome_usuario']);
if ($mandado['status'] == 'cumprido') {
    $campo('Cumprido em',   date('d/m/Y H:i', strtotime($mandado['data_cumprido'])));
    $campo('Cumprido por',  $mandado['cumprido_por_nome']);
}
?>
<?php if ($mandado['status'] == 'pendente'): ?>
        <button class="btn btn-success w-100 btn-definir-cumprido" data-id="<?= $mandado['id'] ?>">
          <i data-feather="check-circle" class="me-1"></i> Marcar como Cumprido
        </button>
<?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ANEXOS ------------------------------------------------------- -->
  <div class="col-md-8">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Anexos</h5>

            <div class="btn-group">
                <a href="baixar_anexos_mandado.php?id=<?= $mandado['id'] ?>" 
                class="btn btn-outline-secondary btn-sm" 
                title="Baixar todos os anexos em um único PDF">
                <i data-feather="download" class="me-1"></i> PDF Compilado
                </a>

                <?php if ($mandado['status'] !== 'cumprido'): ?>
                <button class="btn btn-sm btn-primary" 
                        data-bs-toggle="collapse" data-bs-target="#uploadCollapse">
                    <i data-feather="upload" class="me-1"></i> Adicionar
                </button>
                <?php endif; ?>
            </div>
        </div>

      <!-- área upload -->
      <div class="collapse" id="uploadCollapse">
        <div class="card-body border-bottom">
          <form id="uploadForm" action="upload_mandado.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="mandado_id" value="<?= $mandado['id'] ?>">
            <div class="upload-area mb-3">
              <div class="dropzone-container" id="dropzoneUpload">
                <div class="dz-message text-center">
                  <i data-feather="upload-cloud" style="width:64px;height:64px;color:#6c757d;"></i>
                  <h5 class="mt-3">Arraste &amp; solte arquivos aqui</h5>
                  <p class="text-muted">ou clique em <strong>Selecionar Arquivos</strong></p>
                  <button type="button" class="btn btn-outline-primary browse-btn">
                    <i data-feather="folder" class="me-1"></i> Selecionar Arquivos
                  </button>
                  <p class="mt-3 small text-muted">PDF, JPG, JPEG, PNG – máx. 10&nbsp;MB</p>
                </div>
              </div>
            </div>

            <div id="preview-container" class="mb-3 d-none">
              <h6 class="mb-2"><i data-feather="file-text" class="me-2"></i>Arquivos Selecionados</h6>
              <div id="file-preview-list"></div>
            </div>

            <div id="progressContainer" class="mt-3 d-none">
              <label class="form-label"><i data-feather="loader" class="me-2"></i>Upload</label>
              <div class="progress" style="height:20px">
                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div>
              </div>
              <p id="uploadStatus" class="mt-2 small text-muted"></p>
            </div>

            <div class="d-flex justify-content-end">
              <button class="btn btn-outline-secondary me-2" data-bs-toggle="collapse" data-bs-target="#uploadCollapse">Cancelar</button>
              <button id="submitUpload" class="btn btn-primary" disabled>
                <i data-feather="upload" class="me-1"></i> Enviar
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- lista anexos -->
      <div class="card-body">
<?php if ($anexos): ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr><th>Arquivo</th><th>Tipo</th><th>Tam.</th><th>Data</th><th class="text-center">Ações</th></tr>
            </thead>
            <tbody>
<?php foreach ($anexos as $ax):
  $ext = strtolower(pathinfo($ax['nome_arquivo'], PATHINFO_EXTENSION));
  $icon = in_array($ext, ['jpg', 'jpeg', 'png']) ? 'image' : ($ext == 'pdf' ? 'file-text' : 'file'); ?>
              <tr>
                <td><i data-feather="<?= $icon ?>" class="me-2 text-muted"></i><?= htmlspecialchars($ax['nome_arquivo']) ?></td>
                <td><?= strtoupper($ext) ?></td>
                <td><?= number_format($ax['tamanho'] / 1024, 2) ?> KB</td>
                <td><?= date('d/m/Y H:i', strtotime($ax['data_upload'])) ?></td>
                <td class="text-center">
                  <button class="btn btn-sm btn-outline-primary ver-anexo" data-src="<?= $ax['caminho'] ?>" data-ext="<?= $ext ?>">
                    <i data-feather="eye"></i>
                  </button>
<?php if ($mandado['status'] != 'cumprido'): ?>
                  <a href="javascript:void(0)" onclick="confirmarExclusao(<?= $ax['id'] ?>)" class="btn btn-sm btn-outline-danger">
                    <i data-feather="trash-2"></i>
                  </a>
<?php endif; ?>
                </td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
<?php else: ?>
        <div class="text-center py-5 text-muted">
          <i data-feather="file-text" style="width:64px;height:64px;opacity:.3;"></i>
          <p class="mt-3">Nenhum anexo.</p>
        </div>
<?php endif; ?>
      </div>
    </div>
  </div>
</div><!-- /row -->

<!-- modal viewer -->
<div class="modal fade" id="viewerModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-light">
        <h5 class="modal-title"><i data-feather="file" class="me-2"></i>Anexo</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" style="height:80vh;">
        <iframe id="viewerFrame" style="width:100%;height:100%;border:0;display:none"></iframe>
        <img id="viewerImg" style="width:100%;height:100%;object-fit:contain;display:none">
      </div>
      <div class="modal-footer bg-light">
        <a id="viewerNewTab" target="_blank" class="btn btn-outline-primary">Abrir em nova guia</a>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>
<?php else: /* ===================== LISTA / PESQUISA ===================== */ ?>

<!-- CARDS resumo -->
<div class="row mb-4">
  <div class="col-md-4 col-lg-3 mb-3">
    <div class="stats-card text-center" onclick="filtrarStatus('pendente')">
      <i data-feather="clock" class="text-warning mb-2" style="width:32px;height:32px;"></i>
      <p class="stats-number text-warning"><?= $contPend ?></p>
      <p class="stats-label">Pendentes</p>
    </div>
  </div>
  <div class="col-md-4 col-lg-3 mb-3">
    <div class="stats-card text-center" onclick="filtrarStatus('cumprido')">
      <i data-feather="check-circle" class="text-success mb-2" style="width:32px;height:32px;"></i>
      <p class="stats-number text-success"><?= $contCum ?></p>
      <p class="stats-label">Cumpridos</p>
    </div>
  </div>
  <div class="col-md-4 col-lg-3 mb-3">
    <div class="stats-card text-center" onclick="filtrarStatus('todos')">
      <i data-feather="layers" class="text-primary mb-2" style="width:32px;height:32px;"></i>
      <p class="stats-number text-primary"><?= $contPend + $contCum ?></p>
      <p class="stats-label">Total</p>
    </div>
  </div>
</div>

<!-- FILTROS -->
<div class="card shadow-sm mb-4">
  <div class="card-header bg-white">
    <form class="row g-3" method="get">
      <div class="col-md-3">
        <input class="form-control" name="codigo" placeholder="Código"
               value="<?= htmlspecialchars($f_codigo) ?>">
      </div>
      <div class="col-md-3">
        <input class="form-control" name="remetente" placeholder="Remetente"
               value="<?= htmlspecialchars($f_remet) ?>">
      </div>
      <div class="col-md-2">
        <select name="origem" class="form-select">
          <option value="">Todas origens</option>
          <option <?= $f_origem == 'Malote digital' ? 'selected' : '' ?>>Malote digital</option>
          <option <?= $f_origem == 'Balcão' ? 'selected' : '' ?>>Balcão</option>
        </select>
      </div>
      <div class="col-md-2">
        <select name="status" class="form-select">
          <option value="pendente" <?= $f_status == 'pendente' ? 'selected' : '' ?>>Pendentes</option>
          <option value="cumprido" <?= $f_status == 'cumprido' ? 'selected' : '' ?>>Cumpridos</option>
          <option value="todos"    <?= $f_status == 'todos'    ? 'selected' : '' ?>>Todos</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary"><i data-feather="search" class="me-1"></i>Filtrar</button>
      </div>
    </form>
  </div>
</div>

<!-- LISTA -->
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="table-responsive">
      <table id="tblMandados" class="table table-striped align-middle nowrap" style="width:100%">
        <thead class="table-light">
          <tr>
            <th>Código</th>
            <th>Remetente</th>
            <th>Motivo</th>
            <th>Assunto</th>
            <th>Origem</th>
            <th>Data</th>
            <th>Status</th>
            <th class="text-center">Ações</th>
          </tr>
        </thead>
        <tbody>
<?php foreach ($mandados as $m): [$bdg, $icn] = badgeMandado($m['status']); ?>
          <tr>
            <td><code><?= htmlspecialchars($m['codigo_rastreabilidade']) ?></code></td>
            <td><?= htmlspecialchars($m['remetente']) ?></td>
            <td><?= htmlspecialchars($m['motivo_envio']) ?></td>
            <td class="text-truncate" style="max-width:300px"><?= htmlspecialchars($m['assunto']) ?></td>
            <td><?= htmlspecialchars($m['origem']) ?></td>
            <td data-order="<?= $m['data_cadastro'] ?>"><?= date('d/m/Y', strtotime($m['data_cadastro'])) ?></td>
            <td>
              <div class="dropdown">
                <button class="btn btn-sm dropdown-toggle badge <?= $bdg ?>" data-bs-toggle="dropdown">
                  <i data-feather="<?= $icn ?>" style="width:14px;height:14px;"></i> <?= ucfirst($m['status']) ?>
                </button>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item alterar-status" data-id="<?= $m['id'] ?>" data-status="pendente">
                    <i data-feather="clock" class="me-2 text-warning" style="width:14px;"></i>Pendente</a></li>
                  <li><a class="dropdown-item alterar-status" data-id="<?= $m['id'] ?>" data-status="cumprido">
                    <i data-feather="check-circle" class="me-2 text-success" style="width:14px;"></i>Cumprido</a></li>
                  <li><a class="dropdown-item alterar-status text-danger" data-id="<?= $m['id'] ?>" data-status="excluido">
                    <i data-feather="trash-2" class="me-2" style="width:14px;"></i>Excluir</a></li>
                </ul>
              </div>
            </td>
            <td class="text-center">
              <a class="btn btn-sm btn-outline-primary" href="mandados.php?id=<?= $m['id'] ?>" title="Gerenciar">
                <i data-feather="edit"></i>
              </a>
            </td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; /* lista */ ?>
</div><!-- /.container -->

<!-- ============================================================= -->
<!--  SCRIPTS                                                      -->
<!-- ============================================================= -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
<!-- Bootstrap / SweetAlert / Feather -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/feather-icons"></script>

<script>
feather.replace();

/* ---------- DataTable ---------- */
$(function () {
  if ($.fn.DataTable) {
    $('#tblMandados').DataTable({
      responsive: true,
      pageLength: 25,
      order: [[5, 'desc']],        // data
      columnDefs: [
        { orderable: false, targets: [7] },
        { className: 'text-center', targets: [6, 7] }
      ],
      language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json' },
      drawCallback: () => feather.replace()
    });
  } else {
    console.error('DataTables não carregou – verifique as tags <script>.');
  }
});

/* ---------- filtro cards ---------- */
function filtrarStatus(st) {
  const u = new URL(location);
  u.searchParams.set('status', st);
  location = u;
}

/* ---------- alterar status AJAX ---------- */
function alteraStatus(id, st) {
  $.post('mandados.php', { atualizar_status: 1, id, status: st }, r => {
    if (r.success) location.reload();
    else Swal.fire('Erro', 'Não foi possível alterar.', 'error');
  }, 'json').fail(() => Swal.fire('Erro', 'Falha na comunicação', 'error'));
}

$(document).on('click', '.alterar-status', function (e) {
  e.preventDefault();
  alteraStatus($(this).data('id'), $(this).data('status'));
});

/* confirmação antes de cumprir */
$(document).on('click', '.btn-definir-cumprido', function () {
  const id = $(this).data('id');
  Swal.fire({
    title: 'Marcar como cumprido?',
    text: 'Você confirma que o mandado foi cumprido?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#198754',
    confirmButtonText: 'Sim, cumprir'
  }).then(r => { if (r.isConfirmed) alteraStatus(id, 'cumprido'); });
});

/* ---------- viewer anexo ---------- */
const viewer = new bootstrap.Modal('#viewerModal');
$(document).on('click', '.ver-anexo', function () {
  const src = $(this).data('src'),
        ext = $(this).data('ext');
  $('#viewerImg,#viewerFrame').hide();
  if (ext === 'pdf') $('#viewerFrame').attr('src', src).show();
  else $('#viewerImg').attr('src', src).show();
  $('#viewerNewTab').attr('href', src);
  viewer.show();
});

/* ---------- excluir anexo ---------- */
function confirmarExclusao(id) {
  Swal.fire({
    title: 'Excluir anexo?',
    text: 'Esta ação não poderá ser desfeita.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    confirmButtonText: 'Excluir'
  }).then(r => {
    if (r.isConfirmed) {
      location = 'excluir_anexo_mandado.php?id=' + id + '&redirect=' + encodeURIComponent(location);
    }
  });
}

/* ---------- verificador código único ---------- */
(function () {
  const input = document.querySelector('[name="codigo_rastreabilidade"]');
  if (!input) return;
  let timer, bloqueado = false;
  const check = () => {
    const v = input.value.trim();
    if (v.length < 3) return;
    fetch('check_codigo_mandado.php?codigo=' + encodeURIComponent(v))
      .then(r => r.json())
      .then(j => {
        if (!j.success) return;
        if (j.existe && !bloqueado) {
          bloqueado = true;
          Swal.fire({
            icon: 'warning',
            title: 'Código já existe!',
            text: 'Há um mandado com esse código.',
            showCancelButton: true,
            confirmButtonText: 'Visualizar'
          }).then(res => {
            if (res.isConfirmed && j.id) location = 'mandados.php?id=' + j.id;
            else { bloqueado = false; input.focus(); }
          });
        } else if (!j.existe) bloqueado = false;
      });
  };
  input.addEventListener('keyup', () => { clearTimeout(timer); timer = setTimeout(check, 400); });
  input.addEventListener('blur', check);
})();

/* ---------- drag-n-drop upload ---------- */
document.addEventListener('DOMContentLoaded', () => {
  const dz = document.getElementById('dropzoneUpload');
  const form = document.getElementById('uploadForm');
  if (!dz || !form) return;

  const fi = Object.assign(document.createElement('input'), {
    type: 'file', multiple: true, name: 'arquivos[]',
    accept: '.pdf,.jpg,.jpeg,.png', style: 'display:none'
  });
  fi.setAttribute('form', 'uploadForm');
  form.appendChild(fi);
  document.querySelector('.browse-btn').onclick = () => fi.click();

  const preview = document.getElementById('preview-container'),
        list    = document.getElementById('file-preview-list'),
        btnSend = document.getElementById('submitUpload');

  function render(files) {
    btnSend.disabled = files.length === 0;
    preview.classList.toggle('d-none', files.length === 0);
    list.innerHTML = '';
    [...files].forEach(f => {
      const ext = f.name.split('.').pop().toLowerCase();
      const ic  = ['jpg', 'jpeg', 'png'].includes(ext) ? 'image' : (ext === 'pdf' ? 'file-text' : 'file');
      const sz  = f.size > 1e6 ? (f.size / 1e6).toFixed(2) + ' MB' : (f.size / 1024).toFixed(1) + ' KB';
      list.insertAdjacentHTML('beforeend', `
        <div class="d-flex align-items-center p-2 mb-2 bg-white rounded shadow-sm">
          <i data-feather="${ic}" class="text-primary me-3"></i>
          <div class="flex-grow-1 text-truncate">
            <div>${f.name}</div><small class="text-muted">${sz}</small>
          </div>
        </div>`);
    });
    feather.replace();
  }

  ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(ev =>
    dz.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); })
  );
  ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, () => dz.classList.add('highlight')));
  ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, () => dz.classList.remove('highlight')));
  dz.addEventListener('drop', e => { fi.files = e.dataTransfer.files; render(fi.files); });
  fi.addEventListener('change', e => render(e.target.files));

  form.addEventListener('submit', e => {
    e.preventDefault();
    if (!fi.files.length) return;

    const pc = $('#progressContainer').removeClass('d-none'),
          pb = $('#progressBar'),
          st = $('#uploadStatus');

    btnSend.disabled = true;
    btnSend.innerHTML = 'Enviando…';

    const fd = new FormData(form);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', form.action);

    xhr.upload.onprogress = e => {
      if (e.lengthComputable) {
        const p = Math.round(e.loaded / e.total * 100);
        pb.css('width', p + '%').text(p + '%');
        st.text(`${(e.loaded / 1024).toFixed()} KB / ${(e.total / 1024).toFixed()} KB`);
      }
    };

    xhr.onload = () => {
      let r = {};
      try { r = JSON.parse(xhr.responseText); } catch {}
      if (xhr.status === 200 && r.success) {
        Swal.fire({ icon: 'success', title: 'Upload concluído!', timer: 1200, showConfirmButton: false })
          .then(() => location.reload());
      } else {
        Swal.fire('Erro', r.message || 'Falha no upload', 'error');
      }
      btnSend.disabled = false;
      btnSend.innerHTML = '<i data-feather="upload" class="me-1"></i> Enviar';
    };

    xhr.onerror = () => {
      Swal.fire('Erro', 'Falha de conexão', 'error');
      btnSend.disabled = false;
      btnSend.innerHTML = '<i data-feather="upload" class="me-1"></i> Enviar';
    };

    xhr.send(fd);
  });
});
</script>
<?php include 'includes/footer.php'; ?>
