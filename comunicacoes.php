<?php  
/**  
 * comunicações.php  
 * ------------------------------------------------------------------  
 * Módulo para gerenciar as comunicações recebidas pela CRC (casamento,  
 * óbito, interdição, curatela etc.).  Reaproveita o layout de selos.php  
 * e adiciona:  
 *   • cadastro rápido (cola-e-salva) com extração automática de campos  
 *   • filtros por nome, livro/folha/termo, código, período  
 *   • upload de PDF com OCR (linka para upload_pdf_crc.php)  
 * ------------------------------------------------------------------*/  
date_default_timezone_set('America/Sao_Paulo');  

require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  
require_once 'includes/functions.php';  
require_once 'includes/parse_crc.php';          // helper de extração  

/* ------------------------------------------------------------------  
   0. CADASTRO RÁPIDO – POST  
------------------------------------------------------------------*/  
if (isset($_POST['cadastrar_comunicacao']) && !empty($_POST['texto_integral'])) {  

    $todos = splitComunicacoesCRC($_POST['texto_integral']);   // <-- NOVO  
    if (!$todos) {  
        $erro_cadastro = 'Nenhuma comunicação reconhecida no texto colado.';  
    } else {  
        $pdo->beginTransaction();  
        try {  
            $inseridos   = 0;  
            $duplicados  = 0;  
            $falhos      = 0;  

            foreach ($todos as $raw) {  
                $dados = parseComunicacaoCRC($raw);  
                if (!$dados) { $falhos++; continue; }  

                /* evita duplicidade */  
                $stmt = $pdo->prepare("SELECT id FROM comunicacoes_crc WHERE codigo_crc = ?");  
                $stmt->execute([$dados['codigo_crc']]);  
                if ($stmt->fetch()) { $duplicados++; continue; }  

                /* INSERT */  
                $dados['status'] = 'pendente'; // Define o status padrão para novas comunicações  
                $cols = array_keys($dados);  
                $sql  = 'INSERT INTO comunicacoes_crc (' . implode(',', $cols) . ')'  
                      . ' VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';  
                $pdo->prepare($sql)->execute(array_values($dados));  
                $inseridos++;  
            }  

            $pdo->commit();  
            header('Location: comunicacoes.php?success=1'  
                   .'&ins='.$inseridos.'&dup='.$duplicados.'&fal='.$falhos);  
            exit;  

        } catch (Exception $e) {  
            $pdo->rollBack();  
            $erro_cadastro = 'Erro: '.$e->getMessage();  
        }  
    }  
}  

/* ------------------------------------------------------------------  
   ATUALIZAR STATUS - POST AJAX  
------------------------------------------------------------------*/  
if (isset($_POST['atualizar_status']) && isset($_POST['id']) && isset($_POST['status'])) {  
    $id = intval($_POST['id']);  
    $status = $_POST['status'];  
    
    if (!in_array($status, ['pendente', 'anotada', 'recusada', 'excluido'])) {  
        echo json_encode(['success' => false, 'message' => 'Status inválido']);  
        exit;  
    }  
    
    try {  
        $stmt = $pdo->prepare("UPDATE comunicacoes_crc SET status = ? WHERE id = ?");  
        $result = $stmt->execute([$status, $id]);  
        echo json_encode(['success' => $result]);  
    } catch (Exception $e) {  
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);  
    }  
    exit;  
}  

/* ------------------------------------------------------------------  
   1. FILTROS  
------------------------------------------------------------------*/  
$filtro_nome   = trim($_GET['nome']   ?? '');  
$filtro_livro  = trim($_GET['livro']  ?? '');  
$filtro_folha  = trim($_GET['folha']  ?? '');  
$filtro_termo  = trim($_GET['termo']  ?? '');  
$filtro_codigo = trim($_GET['codigo'] ?? '');  
$filtro_tipo   = $_GET['tipo'] ?? '';  
$filtro_status = $_GET['status'] ?? 'pendente'; // Por padrão, apenas pendentes  
$periodo_ini   = $_GET['ini'] ?? date('Y-m-01');  
$periodo_fim   = $_GET['fim'] ?? date('Y-m-d');  

// Se houver filtros ativos (exceto status), permitir busca em todos os status  
$tem_filtros = !empty($filtro_nome) || !empty($filtro_livro) || !empty($filtro_folha) ||   
               !empty($filtro_termo) || !empty($filtro_codigo) || !empty($filtro_tipo) ||  
               $periodo_ini != date('Y-m-01') || $periodo_fim != date('Y-m-d');  

$where  = ['c.id > 0'];  
$params = [];  

// Aplicar filtro de status  
if (!$tem_filtros && $filtro_status == 'pendente') {  
    // Apenas pendentes se não houver outros filtros  
    $where[] = 'c.status = ?';  
    $params[] = 'pendente';  
} elseif ($filtro_status && $filtro_status != 'todos') {  
    $where[] = 'c.status = ?';  
    $params[] = $filtro_status;  
} elseif ($filtro_status != 'todos') {  
    // Excluir apenas os excluídos se não for "todos"  
    $where[] = 'c.status != ?';  
    $params[] = 'excluido';  
}  

if ($filtro_nome)   { $where[] = 'c.nome_principal LIKE ?'; $params[] = "%$filtro_nome%"; }  
if ($filtro_livro)  { $where[] = 'c.livro_numero = ?';      $params[] = $filtro_livro;     }  
if ($filtro_folha)  { $where[] = 'c.folha = ?';             $params[] = $filtro_folha;     }  
if ($filtro_termo)  { $where[] = 'c.termo = ?';             $params[] = $filtro_termo;     }  
if ($filtro_codigo) { $where[] = 'c.codigo_crc = ?';        $params[] = $filtro_codigo;    }  
if ($filtro_tipo)   { $where[] = 'c.tipo = ?';              $params[] = $filtro_tipo;      }  

$where[] = 'c.data_registro BETWEEN ? AND ?';  
$params[] = $periodo_ini;  
$params[] = $periodo_fim;  

/* ------------------------------------------------------------------  
   2. CONSULTA  
------------------------------------------------------------------*/  
$sql = "  
    SELECT c.*, p.arquivo AS pdf_original  
    FROM comunicacoes_crc c  
    LEFT JOIN anexos_crc_pdf p ON c.pdf_id = p.id  
    WHERE " . implode(' AND ', $where) . "  
    ORDER BY c.data_registro DESC  
";  
$stmt = $pdo->prepare($sql);  
$stmt->execute($params);  
$comunicacoes = $stmt->fetchAll();  

// Contar totais por status  
$sqlCount = "SELECT status, COUNT(*) as total FROM comunicacoes_crc WHERE status != 'excluido' GROUP BY status";  
$stmtCount = $pdo->query($sqlCount);  
$contagemStatus = [];  
while ($row = $stmtCount->fetch()) {  
    $contagemStatus[$row['status']] = $row['total'];  
}  
$totalPendentes = $contagemStatus['pendente'] ?? 0;  
$totalAnotadas = $contagemStatus['anotada'] ?? 0;  
$totalRecusadas = $contagemStatus['recusada'] ?? 0;  


// Função auxiliar para gerar classe de badge por tipo
function getTipoBadgeClass($tipo) {
    $tipo = strtolower(trim($tipo));
    switch ($tipo) {
        case 'casamento': return 'badge-tipo-casamento';
        case 'nascimento': return 'badge-tipo-nascimento';
        case 'obito': 
        case 'óbito': return 'badge-tipo-obito';
        case 'alteracao':
        case 'alteração':
        case 'alteracao de estado civil':
        case 'alteração de estado civil': return 'badge-tipo-alteracao';
        case 'interdicao':
        case 'interdição': return 'badge-tipo-interdicao';
        case 'curatela': return 'badge-tipo-curatela';
        case 'emancipacao':
        case 'emancipação': return 'badge-tipo-emancipacao';
        case 'adocao':
        case 'adoção': return 'badge-tipo-adocao';
        case 'divorcio':
        case 'divórcio': return 'badge-tipo-divorcio';
        case 'retificacao':
        case 'retificação': return 'badge-tipo-retificacao';
        case 'conversao':
        case 'conversão':
        case 'conversao de uniao estavel':
        case 'conversão de união estável': return 'badge-tipo-conversao';
        default: return 'badge-tipo-outros';
    }
}

/* ------------------------------------------------------------------  
   3. INTERFACE  
------------------------------------------------------------------*/  
include 'includes/header.php';  
?>  
<?php include(__DIR__ . '/css/style-comunicacoes.php');?>   
<!-- DataTables CSS - ANTES do header -->  
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">  
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">  
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">  

<div class="container-fluid py-4">  
  <!-- Cabeçalho -->  
  <div class="row mb-4">  
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center">  
      <div class="mb-3 mb-md-0">  
        <h1 class="fw-bold text-gray-800">  
          <i data-feather="inbox" class="me-2 text-primary"></i>  
          Comunicações da CRC  
        </h1>  
        <p class="text-muted lead fs-6">  
          Cadastre, pesquise e gerencie comunicações de casamento, óbito, interdição, curatela…  
        </p>  
      </div>  

      <div class="d-flex gap-2">  
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadPDFModal">  
          <i data-feather="upload" class="me-1" style="width:16px;height:16px;"></i> PDF + OCR  
        </button>  

        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novoComModal">  
          <i data-feather="plus" class="me-1" style="width:16px;height:16px;"></i> Nova Comunicação  
        </button>  
      </div>  
    </div>  
  </div>  

  <?php if (isset($_GET['success'])): ?>  
    <div class="alert alert-success alert-dismissible fade show" role="alert">  
      <i data-feather="check-circle" class="me-2"></i>  
      Comunicação cadastrada com sucesso!  
      <?php if (isset($_GET['ins']) || isset($_GET['dup']) || isset($_GET['fal'])): ?>  
        <div class="mt-1 small">  
          <?php if (isset($_GET['ins'])): ?><span class="me-2"><strong><?= $_GET['ins'] ?></strong> inserido(s)</span><?php endif; ?>  
          <?php if (isset($_GET['dup'])): ?><span class="me-2"><strong><?= $_GET['dup'] ?></strong> duplicado(s)</span><?php endif; ?>  
          <?php if (isset($_GET['fal'])): ?><span><strong><?= $_GET['fal'] ?></strong> falho(s)</span><?php endif; ?>  
        </div>  
      <?php endif; ?>  
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>  
    </div>  
  <?php elseif (!empty($erro_cadastro)): ?>  
    <div class="alert alert-danger alert-dismissible fade show" role="alert">  
      <i data-feather="alert-circle" class="me-2"></i>  
      <?= htmlspecialchars($erro_cadastro) ?>  
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>  
    </div>  
  <?php endif; ?>  

  <!-- Cards de Estatísticas -->  
  <div class="row mb-4">  
    <div class="col-md-4 col-lg-3 mb-3">  
      <div class="stats-card text-center" onclick="filtrarPorStatus('pendente')">  
        <i data-feather="clock" class="text-warning mb-2" style="width:32px;height:32px;"></i>  
        <p class="stats-number text-warning"><?= $totalPendentes ?></p>  
        <p class="stats-label">Pendentes</p>  
      </div>  
    </div>  
    <div class="col-md-4 col-lg-3 mb-3">  
      <div class="stats-card text-center" onclick="filtrarPorStatus('anotada')">  
        <i data-feather="check-circle" class="text-success mb-2" style="width:32px;height:32px;"></i>  
        <p class="stats-number text-success"><?= $totalAnotadas ?></p>  
        <p class="stats-label">Anotadas</p>  
      </div>  
    </div>  
    <div class="col-md-4 col-lg-3 mb-3">  
      <div class="stats-card text-center" onclick="filtrarPorStatus('recusada')">  
        <i data-feather="x-circle" class="text-danger mb-2" style="width:32px;height:32px;"></i>  
        <p class="stats-number text-danger"><?= $totalRecusadas ?></p>  
        <p class="stats-label">Recusadas</p>  
      </div>  
    </div>  
    <div class="col-md-4 col-lg-3 mb-3">  
      <div class="stats-card text-center" onclick="filtrarPorStatus('todos')">  
        <i data-feather="layers" class="text-primary mb-2" style="width:32px;height:32px;"></i>  
        <p class="stats-number text-primary"><?= $totalPendentes + $totalAnotadas + $totalRecusadas ?></p>  
        <p class="stats-label">Total</p>  
      </div>  
    </div>  
  </div>  

  <!-- FILTROS MODERNOS -->  
  <div class="filter-card mb-4">  
    <div class="filter-header d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#filtrosCollapse" aria-expanded="true">  
      <h5><i data-feather="filter" class="me-2"></i>Filtros de Pesquisa</h5>  
      <i data-feather="chevron-down"></i>  
    </div>  
    <div class="collapse show" id="filtrosCollapse">  
      <div class="filter-body">  
        <form class="row g-3" method="get" id="formFiltros">  
          <!-- Primeira linha -->  
          <div class="col-md-6 col-lg-3">  
            <label class="form-label">Nome do Registrado</label>  
            <input name="nome" value="<?= htmlspecialchars($filtro_nome) ?>" class="form-control" placeholder="Digite o nome...">  
          </div>  
          
          <div class="col-md-6 col-lg-3">  
            <label class="form-label">Código CRC</label>  
            <input name="codigo" value="<?= htmlspecialchars($filtro_codigo) ?>" class="form-control" placeholder="Ex: 123456">  
          </div>  
          
          <div class="col-md-6 col-lg-3">  
            <label class="form-label">Tipo de Comunicação</label>  
            <select name="tipo" class="form-select">  
              <option value="">Todos os tipos</option>  
              <option value="casamento" <?= $filtro_tipo == 'casamento' ? 'selected' : '' ?>>Casamento</option>  
              <option value="obito" <?= $filtro_tipo == 'obito' ? 'selected' : '' ?>>Óbito</option>  
              <option value="interdicao" <?= $filtro_tipo == 'interdicao' ? 'selected' : '' ?>>Interdição</option>  
              <option value="curatela" <?= $filtro_tipo == 'curatela' ? 'selected' : '' ?>>Curatela</option>  
              <option value="outros" <?= $filtro_tipo == 'outros' ? 'selected' : '' ?>>Outros</option>  
            </select>  
          </div>  
          
          <div class="col-md-6 col-lg-3">  
            <label class="form-label">Status</label>  
            <select name="status" class="form-select">  
              <option value="pendente" <?= $filtro_status == 'pendente' ? 'selected' : '' ?>>Pendentes</option>  
              <option value="anotada" <?= $filtro_status == 'anotada' ? 'selected' : '' ?>>Anotadas</option>  
              <option value="recusada" <?= $filtro_status == 'recusada' ? 'selected' : '' ?>>Recusadas</option>  
              <option value="todos" <?= $filtro_status == 'todos' ? 'selected' : '' ?>>Todos</option>  
            </select>  
          </div>  
          
          <!-- Segunda linha -->  
          <div class="col-md-4 col-lg-2">  
            <label class="form-label">Livro</label>  
            <input name="livro" value="<?= htmlspecialchars($filtro_livro) ?>" class="form-control" placeholder="Nº do livro">  
          </div>  
          
          <div class="col-md-4 col-lg-2">  
            <label class="form-label">Folha</label>  
            <input name="folha" value="<?= htmlspecialchars($filtro_folha) ?>" class="form-control" placeholder="Nº da folha">  
          </div>  
          
          <div class="col-md-4 col-lg-2">  
            <label class="form-label">Termo</label>  
            <input name="termo" value="<?= htmlspecialchars($filtro_termo) ?>" class="form-control" placeholder="Nº do termo">  
          </div>  
          
          <div class="col-md-6 col-lg-3">  
            <label class="form-label">Data Inicial</label>  
            <input type="date" name="ini" value="<?= $periodo_ini ?>" class="form-control">  
          </div>  
          
          <div class="col-md-6 col-lg-3">  
            <label class="form-label">Data Final</label>  
            <input type="date" name="fim" value="<?= $periodo_fim ?>" class="form-control">  
          </div>  

          <!-- Botões -->  
          <div class="col-12 d-flex justify-content-end gap-2 mt-4">  
            <button type="button" class="btn btn-outline-secondary" onclick="limparFiltros()">  
              <i data-feather="x" class="me-1" style="width:16px;height:16px;"></i> Limpar  
            </button>  
            <button type="submit" class="btn btn-primary">  
              <i data-feather="search" class="me-1" style="width:16px;height:16px;"></i> Filtrar  
            </button>  
          </div>  
        </form>  
      </div>  
    </div>  
  </div>  

  <!-- TABELA -->  
  <div class="table-container">  
    <h5 class="mb-3">  
      <i data-feather="list" class="me-2"></i>  
      <?php if (!$tem_filtros && $filtro_status == 'pendente'): ?>  
        Comunicações Pendentes  
      <?php else: ?>  
        Resultados da Pesquisa  
      <?php endif; ?>  
      <span class="badge bg-secondary ms-2"><?= count($comunicacoes) ?> registro(s)</span>  
    </h5>  
    
    <div class="table-responsive">  
      <table id="tabelaComunicacoes" class="table table-hover table-striped dt-responsive nowrap" style="width:100%">  
        <thead class="table-light">  
          <tr>  
            <th>Tipo</th>  
            <th>Nome</th>  
            <th>Livro/F/T</th>  
            <th>Código</th>  
            <th>Data</th>  
            <th>Status</th>  
            <th>PDF</th>  
            <th>Ações</th>  
          </tr>  
        </thead>  
        <tbody>  
          <?php foreach ($comunicacoes as $c): ?>  
            <tr>  
              <td>  
                <span class="badge <?= getTipoBadgeClass($c['tipo']) ?>">  
                    <?= ucfirst($c['tipo']) ?>  
                </span>  
              </td>  
              <td><?= htmlspecialchars($c['nome_principal']) ?></td>  
              <td>  
                <small class="text-muted">  
                  <?= $c['livro_tipo'] . ' ' . $c['livro_numero'] . ' / ' . $c['folha'] . ' / ' . $c['termo'] ?>  
                </small>  
              </td>  
              <td><code><?= $c['codigo_crc'] ?></code></td>  
              <td data-order="<?= $c['data_registro'] ?>">  
                <?= date('d/m/Y', strtotime($c['data_registro'])) ?>  
              </td>  
              <td>  
                <div class="dropdown">  
                  <button class="btn btn-sm dropdown-toggle badge badge-status-<?= $c['status'] ?? 'pendente' ?>"   
                          type="button"   
                          data-bs-toggle="dropdown"   
                          aria-expanded="false">  
                    <?= getStatusLabel($c['status'] ?? 'pendente') ?>  
                  </button>  
                  <ul class="dropdown-menu">  
                    <li><a class="dropdown-item alterar-status" href="#" data-id="<?= $c['id'] ?>" data-status="pendente">  
                      <i data-feather="clock" class="me-2 text-warning" style="width:14px;height:14px;"></i> Pendente  
                    </a></li>  
                    <li><a class="dropdown-item alterar-status" href="#" data-id="<?= $c['id'] ?>" data-status="anotada">  
                      <i data-feather="check-circle" class="me-2 text-success" style="width:14px;height:14px;"></i> Anotada  
                    </a></li>  
                    <li><a class="dropdown-item alterar-status" href="#" data-id="<?= $c['id'] ?>" data-status="recusada">  
                      <i data-feather="x-circle" class="me-2 text-danger" style="width:14px;height:14px;"></i> Recusada  
                    </a></li>  
                  </ul>  
                </div>  
              </td>  
              <td class="text-center">  
                <?php if ($c['pdf_original']): ?>  
                  <a href="<?= $c['pdf_original'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Ver PDF">  
                    <i data-feather="file-text" style="width:14px;height:14px;"></i>  
                  </a>  
                <?php else: ?>  
                  <span class="text-muted">-</span>  
                <?php endif; ?>  
              </td>  
              <td class="text-center">  
                <button type="button" class="btn btn-sm btn-outline-info ver-com" data-id="<?= $c['id'] ?>" title="Detalhes">  
                  <i data-feather="eye" style="width:14px;height:14px;"></i>  
                </button>  
                <button type="button" class="btn btn-sm btn-outline-danger del-com" data-id="<?= $c['id'] ?>" data-cod="<?= $c['codigo_crc'] ?>" title="Excluir">  
                  <i data-feather="trash-2" style="width:14px;height:14px;"></i>  
                </button>  
              </td>  
            </tr>  
          <?php endforeach; ?>  
        </tbody>  
      </table>  
    </div>  
  </div>  
</div>  

<!-- MODAL: NOVA COMUNICAÇÃO -->  
<div class="modal fade" id="novoComModal" tabindex="-1" aria-labelledby="novoComModalLabel" aria-hidden="true">  
  <div class="modal-dialog modal-lg">  
    <form method="post" class="modal-content">  
      <div class="modal-header">  
        <h5 class="modal-title" id="novoComModalLabel">Nova Comunicação (colar texto)</h5>  
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>  
      </div>  
      <div class="modal-body">  
        <textarea name="texto_integral" class="form-control" rows="12" placeholder="Cole aqui o texto integral da comunicação recebida pela CRC" required></textarea>  
      </div>  
      <div class="modal-footer">  
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>  
        <button type="submit" name="cadastrar_comunicacao" class="btn btn-primary">  
          Cadastrar  
        </button>  
      </div>  
    </form>  
  </div>  
</div>  

<!-- MODAL: UPLOAD PDF + OCR -->  
<div class="modal fade" id="uploadPDFModal" tabindex="-1" aria-labelledby="uploadPDFModalLabel" aria-hidden="true">  
  <div class="modal-dialog">  
    <div class="modal-content">  
      <div class="modal-header">  
        <h5 class="modal-title" id="uploadPDFModalLabel">Enviar PDF para OCR</h5>  
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>  
      </div>  
      <div class="modal-body">  
        <form id="uploadPDFForm" action="upload_pdf_crc.php" method="post" enctype="multipart/form-data">  
          <div class="dropzone-container">  
            <div id="dropzone" class="dropzone-area">  
              <div class="dz-message">  
                <i data-feather="upload-cloud" style="width:48px;height:48px;"></i>  
                <p>Arraste e solte arquivos PDF aqui<br>ou clique para selecionar</p>  
                <span class="note">(Apenas arquivos .PDF são aceitos)</span>  
              </div>  
              <input type="file" name="arquivo_pdf" id="arquivo_pdf" accept=".pdf" class="file-input" required>  
            </div>  
          </div>  
          <div id="selected-file" class="mt-3" style="display:none;">  
            <div class="d-flex align-items-center">  
              <i data-feather="file" class="me-2 text-primary"></i>  
              <span id="file-name" class="text-truncate"></span>  
              <button type="button" id="remove-file" class="btn btn-sm btn-outline-danger ms-2">  
                <i data-feather="x" style="width:14px;height:14px;"></i>  
              </button>  
            </div>  
          </div>  
          <div class="form-text mb-3">Envie um PDF contendo várias comunicações; o sistema fará OCR e separará cada uma.</div>  
          <div class="text-end">  
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>  
            <button type="submit" id="submit-pdf" class="btn btn-primary" disabled>Enviar PDF</button>  
          </div>  
        </form>  
      </div>  
    </div>  
  </div>  
</div>  

<!-- MODAL: DETALHES DA COMUNICAÇÃO (REDESENHADO) -->  
<div class="modal fade" id="detalhesComModal" tabindex="-1" aria-labelledby="detalhesComModalLabel" aria-hidden="true">  
  <div class="modal-dialog modal-xl modal-dialog-centered">  
    <div class="modal-content border-0 shadow-lg">  
      <div class="modal-header bg-gradient-primary text-white border-0">  
        <h5 class="modal-title d-flex align-items-center" id="detalhesComModalLabel">  
          <i data-feather="file-text" class="me-2"></i>  
          Detalhes da Comunicação  
        </h5>  
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>  
      </div>  
      <div class="modal-body p-0">  
        <!-- Cabeçalho com informações principais -->  
        <div class="bg-light p-4 border-bottom">  
          <div class="row">  
            <div class="col-md-3">  
              <div class="info-card">  
                <h6 class="text-muted mb-1 fs-7">Código CRC</h6>  
                <p class="mb-0 fw-bold text-primary fs-5" id="detalhe-codigo">-</p>  
              </div>  
            </div>  
            <div class="col-md-3">  
              <div class="info-card">  
                <h6 class="text-muted mb-1 fs-7">Tipo de Comunicação</h6>  
                <p class="mb-0 fw-semibold" id="detalhe-tipo">-</p>  
              </div>  
            </div>  
            <div class="col-md-3">  
              <div class="info-card">  
                <h6 class="text-muted mb-1 fs-7">Data de Registro</h6>  
                <p class="mb-0 fw-semibold" id="detalhe-data">-</p>  
              </div>  
            </div>  
            <div class="col-md-3">  
              <div class="info-card">  
                <h6 class="text-muted mb-1 fs-7">Status Atual</h6>  
                <p class="mb-0"><span id="detalhe-status" class="badge fs-6">-</span></p>  
              </div>  
            </div>  
          </div>  
          
          <div class="row mt-3">  
            <div class="col-md-6">  
              <div class="info-card">  
                <h6 class="text-muted mb-1 fs-7">Nome Principal</h6>  
                <p class="mb-0 fw-semibold fs-6" id="detalhe-nome">-</p>  
              </div>  
            </div>  
            <div class="col-md-3">  
              <div class="info-card">  
                <h6 class="text-muted mb-1 fs-7">Livro / Folha / Termo</h6>  
                <p class="mb-0 fw-semibold" id="detalhe-lft">-</p>  
              </div>  
            </div>  
            <div class="col-md-3">  
              <div class="info-card">  
                <h6 class="text-muted mb-1 fs-7">Cartório Origem</h6>  
                <p class="mb-0 fw-semibold" id="detalhe-cartorio">-</p>  
              </div>  
            </div>  
          </div>  
        </div>  
        
        <!-- Navegação em abas -->  
        <ul class="nav nav-tabs px-4 pt-3" id="detalhesTab" role="tablist">  
          <li class="nav-item" role="presentation">  
            <button class="nav-link active" id="texto-integral-tab" data-bs-toggle="tab" data-bs-target="#texto-integral" type="button" role="tab">  
              <i data-feather="file-text" class="me-1" style="width:16px;height:16px;"></i>  
              Texto Integral  
            </button>  
          </li>  
          <li class="nav-item" role="presentation">  
            <button class="nav-link" id="texto-anotacao-tab" data-bs-toggle="tab" data-bs-target="#texto-anotacao" type="button" role="tab">  
              <i data-feather="edit" class="me-1" style="width:16px;height:16px;"></i>  
              Texto da Anotação  
            </button>  
          </li>  
          <li class="nav-item" role="presentation">  
            <button class="nav-link" id="etiqueta-tab" data-bs-toggle="tab" data-bs-target="#etiqueta" type="button" role="tab">  
              <i data-feather="printer" class="me-1" style="width:16px;height:16px;"></i>  
              Etiqueta de Anotação  
            </button>  
          </li>  
        </ul>  
        
        <!-- Conteúdo das abas -->  
        <div class="tab-content p-4" id="detalhesTabContent">  
          <!-- Aba: Texto Integral -->  
          <div class="tab-pane fade show active" id="texto-integral" role="tabpanel">  
            <div class="comunicacao-content p-4 rounded-3">  
              <div id="detalhe-texto"></div>  
            </div>  
          </div>  
          
          <!-- Aba: Texto da Anotação -->  
          <div class="tab-pane fade" id="texto-anotacao" role="tabpanel">  
            <div class="anotacao-content p-4 bg-light rounded-3">  
              <div id="detalhe-texto-anotacao"></div>  
              <button type="button" class="btn btn-sm btn-outline-secondary mt-3" onclick="copiarTextoAnotacao()">  
                <i data-feather="copy" class="me-1" style="width:14px;height:14px;"></i> Copiar Texto  
              </button>  
            </div>  
          </div>  
          
          <!-- Aba: Etiqueta de Anotação -->  
          <div class="tab-pane fade" id="etiqueta" role="tabpanel">  
            <div class="row">  
              <div class="col-md-6">  
                <h6 class="mb-3">Configurações da Etiqueta</h6>  
                
                <!-- Tamanho da Etiqueta -->  
                <div class="mb-3">  
                  <label class="form-label">Tamanho da Etiqueta</label>  
                  <select class="form-select" id="tamanho-etiqueta">  
                    <option value="9x3.5">9x3.5 cm</option>  
                    <option value="9x4">9x4 cm</option>  
                    <option value="10x4">10x4 cm</option>  
                    <option value="10x5" selected>10x5 cm</option>  
                  </select>  
                </div>  
                
                <!-- Tamanho da Fonte -->  
                <div class="mb-3">  
                  <label class="form-label">Tamanho da Fonte</label>  
                  <input type="range" class="form-range" id="tamanho-fonte" min="8" max="14" value="10">  
                  <small class="text-muted">Tamanho: <span id="fonte-preview">10</span>pt</small>  
                </div>  
                
                <!-- Margens -->  
                <h6 class="mb-2 mt-4">Margens (em mm)</h6>  
                <div class="row g-2">  
                  <div class="col-6">  
                    <label class="form-label small">Esquerda</label>  
                    <input type="number" class="form-control form-control-sm" id="margem-esquerda" min="0" max="20" value="5" step="1">  
                  </div>  
                  <div class="col-6">  
                    <label class="form-label small">Direita</label>  
                    <input type="number" class="form-control form-control-sm" id="margem-direita" min="0" max="20" value="5" step="1">  
                  </div>  
                  <div class="col-6">  
                    <label class="form-label small">Superior</label>  
                    <input type="number" class="form-control form-control-sm" id="margem-superior" min="0" max="20" value="5" step="1">  
                  </div>  
                  <div class="col-6">  
                    <label class="form-label small">Inferior</label>  
                    <input type="number" class="form-control form-control-sm" id="margem-inferior" min="0" max="20" value="5" step="1">  
                  </div>  
                </div>  
              </div>  
              
              <div class="col-md-6">  
                <h6 class="mb-3">Pré-visualização</h6>  
                <div id="preview-etiqueta" class="etiqueta-preview">  
                  <!-- Preview será renderizado aqui -->  
                </div>  
              </div>  
            </div>  
            
            <div class="text-end mt-3">  
              <button type="button" class="btn btn-primary" onclick="imprimirEtiqueta()">  
                <i data-feather="printer" class="me-1" style="width:16px;height:16px;"></i> Imprimir Etiqueta  
              </button>  
            </div>  
          </div>  
        </div>  
      </div>  
      <div class="modal-footer border-0 bg-light">  
        <div class="d-flex justify-content-between w-100">  
          <div>  
            <div class="btn-group" role="group">  
              <button type="button" class="btn btn-success alterar-status-modal" data-status="anotada">  
                <i data-feather="check" class="me-1" style="width:14px;height:14px;"></i> Marcar como Anotada  
              </button>  
              <button type="button" class="btn btn-danger alterar-status-modal" data-status="recusada">  
                <i data-feather="x" class="me-1" style="width:14px;height:14px;"></i> Recusar  
              </button>  
            </div>  
          </div>  
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>  
        </div>  
      </div>  
    </div>  
  </div>  
</div>  

<!-- jQuery e dependências principais -->  
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>  
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>  

<!-- DataTables Core JS -->  
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>  
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>  
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>  
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>  

<!-- DataTables Buttons Extension -->  
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>  
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>  
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>  
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>  
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>  

<!-- SweetAlert2 e Feather Icons -->  
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>  
<script src="https://unpkg.com/feather-icons"></script>  

<script>  
// Variável global para armazenar dados da comunicação atual  
let comunicacaoAtual = null;  

// Função auxiliar para classes de status  
function getStatusClass(status) {  
  switch (status) {  
    case 'anotada': return 'btn-status-anotada';  
    case 'recusada': return 'btn-status-recusada';  
    case 'excluido': return 'btn-status-excluido';  
    default: return 'btn-status-pendente';  
  }  
}  

// Função auxiliar para rótulos de status  
function getStatusLabel(status) {  
  switch (status) {  
    case 'anotada': return 'Anotada';  
    case 'recusada': return 'Recusada';  
    case 'excluido': return 'Excluído';  
    default: return 'Pendente';  
  }  
}  

// Função para filtrar por status  
function filtrarPorStatus(status) {  
  const url = new URL(window.location.href);  
  url.searchParams.set('status', status);  
  // Limpar outros filtros ao clicar no card  
  url.searchParams.delete('nome');  
  url.searchParams.delete('codigo');  
  url.searchParams.delete('tipo');  
  url.searchParams.delete('livro');  
  url.searchParams.delete('folha');  
  url.searchParams.delete('termo');  
  window.location.href = url.toString();  
}  

// Função para limpar filtros  
function limparFiltros() {  
  window.location.href = 'comunicacoes.php';  
}  

// Função para gerar texto da anotação  
function gerarTextoAnotacao(comunicacao, formatoHTML = false) {  
  const hoje = new Date().toLocaleDateString('pt-BR', {  
    day: '2-digit',  
    month: 'long',  
    year: 'numeric'  
  });  
  
  let texto = comunicacao.texto_integral || '';  
  
  // Extrair a cidade/estado do destinatário (entre "Ao" e "Código da comunicação:")  
  const padraoDestinatario = /\bAo\s+([^\n]+?)\s*Código da comunicação:/is;  
  const matchDestinatario = texto.match(padraoDestinatario);  
  const cidadeEstado = matchDestinatario ? matchDestinatario[1].trim() : 'Zé Doca - MA';  
  
  // Extrair o cartório de origem (primeira linha do texto)  
  const linhas = texto.split('\n');  
  let cartorioOrigem = '';  
  if (linhas.length > 0) {  
    const primeiraLinha = linhas[0].trim();  
    if (!primeiraLinha.toLowerCase().startsWith('comunicação')) {  
      cartorioOrigem = primeiraLinha;  
    } else if (linhas.length > 1) {  
      cartorioOrigem = linhas[1].trim();  
    }  
  }  
  
  // Extrair o parágrafo principal após "Código da comunicação:"  
  // Captura tudo até encontrar uma linha que indica informação de registro anterior ou observações  
  const padraoParagrafoPrincipal = /Código da comunicação:\s*[^\n]+\n+([^]+?)(?=\n\s*(?:Ele\s+(?:foi\s+)?(?:registrado|casado)|Ela\s+(?:foi\s+)?(?:registrada|casada)|O\s+(?:registrado|casado)|A\s+(?:registrada|casada)|(?:Ele|Ela|O|A)\s+[^,]+\s+nesse\s+registro\s+civil|OBSERVAÇÕES|Operador:|$))/is;  
  
  const matchParagrafo = texto.match(padraoParagrafoPrincipal);  
  
  let paragrafoPrincipal = '';  
  if (matchParagrafo) {  
    paragrafoPrincipal = matchParagrafo[1].trim();  
    
    // Limpar parágrafos internos que possam ter escapado  
    // Dividir em linhas e processar  
    const linhasParagrafo = paragrafoPrincipal.split(/\n\s*\n/);  
    let paragrafosValidos = [];  
    
    for (let linha of linhasParagrafo) {  
      linha = linha.trim();  
      // Parar se encontrar padrões de registro anterior  
      if (linha.match(/^(?:Ele|Ela|O|A)\s+(?:foi\s+)?(?:registrad[oa]|casad[oa])\s+(?:nesse|neste)\s+registro\s+civil/i)) {  
        break;  
      }  
      // Parar se encontrar referência a livro/folha/termo de registro anterior  
      if (linha.match(/^(?:Ele|Ela|O|A)\s+[^,]+\s+(?:no\s+livro|às\s+folhas|sob\s+número)/i)) {  
        break;  
      }  
      if (linha) {  
        paragrafosValidos.push(linha);  
      }  
    }  
    
    paragrafoPrincipal = paragrafosValidos.join(' ');  
  }  
  
  // Limpar e formatar o parágrafo principal  
  if (paragrafoPrincipal) {  
    // Substituir quebras de linha por espaço simples  
    paragrafoPrincipal = paragrafoPrincipal.replace(/\r\n/g, ' ');  
    paragrafoPrincipal = paragrafoPrincipal.replace(/\n/g, ' ');  
    paragrafoPrincipal = paragrafoPrincipal.replace(/\r/g, ' ');  
    
    // Remover espaços múltiplos  
    paragrafoPrincipal = paragrafoPrincipal.replace(/\s+/g, ' ');  
    
    // Remover espaços antes de pontuação  
    paragrafoPrincipal = paragrafoPrincipal.replace(/\s+\,/g, ',');  
    paragrafoPrincipal = paragrafoPrincipal.replace(/\s+\./g, '.');  
    paragrafoPrincipal = paragrafoPrincipal.replace(/\s+:/g, ':');  
    paragrafoPrincipal = paragrafoPrincipal.replace(/\s+;/g, ';');  
    
    // Garantir espaço após vírgulas e pontos (exceto no final)  
    paragrafoPrincipal = paragrafoPrincipal.replace(/,(?!\s)/g, ', ');  
    paragrafoPrincipal = paragrafoPrincipal.replace(/\.(?!\s|$)/g, '. ');  
    
    // Corrigir números de processo que possam ter sido separados  
    paragrafoPrincipal = paragrafoPrincipal.replace(/(\d+)\s*\.\s*(\d+)\s*\.\s*(\d+)\s*\.\s*(\d+)\s*\.\s*(\d+)/g, '$1-$2.$3.$4.$5');  
    
    // Trim final  
    paragrafoPrincipal = paragrafoPrincipal.trim();  
  }  
  
  // Montar o texto da anotação  
  let textoAnotacao = "ANOTAÇÃO: Procedo a presente anotação para fazer constar que: ";  
  
  if (paragrafoPrincipal) {  
    textoAnotacao += paragrafoPrincipal;  
    // Garantir que termine com ponto  
    if (!paragrafoPrincipal.endsWith('.')) {  
      textoAnotacao += '.';  
    }  
    textoAnotacao += ' ';  
  }  
  
  // Adicionar informações da comunicação  
  textoAnotacao += `Conforme comunicação recebida via CRC, Código da comunicação: ${comunicacao.codigo_crc || '—'}`;  
  
  if (cartorioOrigem) {  
    textoAnotacao += `, do Cartório de ${cartorioOrigem}`;  
  }  
  
  textoAnotacao += `. O referido é verdade e dou fé. ${cidadeEstado}, ${hoje}.`;  
  
  // Aplicar formatação final no texto completo  
  textoAnotacao = textoAnotacao.replace(/\s+\,/g, ',');  
  textoAnotacao = textoAnotacao.replace(/\s+\./g, '.');  
  textoAnotacao = textoAnotacao.replace(/\s+/g, ' ');  
  
  // Se solicitado formato HTML, aplicar formatação  
  if (formatoHTML) {  
    return formatarTextoAnotacaoHTML(textoAnotacao, comunicacao.codigo_crc, cartorioOrigem, cidadeEstado, hoje);  
  }  
  
  return textoAnotacao;  
}  

// Função para aplicar formatação HTML ao texto da anotação  
function formatarTextoAnotacaoHTML(texto, codigoCRC, cartorio, cidadeEstado, data) {  
  // Destacar início  
  texto = texto.replace(/^(Procedo a presente anotação para fazer constar que:)/, '<span class="texto-intro">$1</span>');  
  
  // Destacar datas  
  texto = texto.replace(/\b(\d{2}\/\d{2}\/\d{4})\b/g, '<span class="data-destaque">$1</span>');  
  
  // Destacar livro, folha e termo  
  texto = texto.replace(/\b(livro\s+[A-Z]?\s*número?\s*\d+)/gi, '<span class="livro-destaque">$1</span>');  
  texto = texto.replace(/\b(folhas?\s+\d+)/gi, '<span class="folha-destaque">$1</span>');  
  texto = texto.replace(/\b(termo\s+\d+)/gi, '<span class="termo-destaque">$1</span>');  
  
  // Destacar nomes em MAIÚSCULAS  
  texto = texto.replace(/\b([A-ZÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ]{2,}(?:\s+[A-ZÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ]{2,})+)\b/g, function(match) {  
    if (!match.match(/^(CRC|MM|DR|DRA)$/)) {  
      return '<span class="nome-destaque">' + match + '</span>';  
    }  
    return match;  
  });  
  
  // Destacar código da comunicação  
  texto = texto.replace(/(Código da comunicação:\s*)([^\s,]+)/, '$1<span class="codigo-destaque">$2</span>');  
  
  // Destacar cartório  
  if (cartorio) {  
    texto = texto.replace(/(do Cartório de\s*)([^.]+)/, '$1<span class="cartorio-destaque">$2</span>');  
  }  
  
  // Destacar parte final  
  texto = texto.replace(/(O referido é verdade e dou fé\.)/, '<span class="texto-final">$1</span>');  
  
  // Destacar cidade e data final  
  texto = texto.replace(new RegExp(`(${cidadeEstado},\\s*${data}\\.)`), '<span class="local-data">$1</span>');  
  
  // Destacar números de processo  
  texto = texto.replace(/(\d{7}-\d{2}\.\d{4}\.\d{1}\.\d{2}\.\d{4})/g, '<span class="processo-destaque">$1</span>');  
  
  return `<div class="texto-anotacao-formatado">${texto}</div>`;  
}  

// Função para copiar texto da anotação  
function copiarTextoAnotacao() {  
  // Gerar texto simples (sem HTML) para copiar  
  const textoSimples = gerarTextoAnotacao(comunicacaoAtual, false);  
  
  navigator.clipboard.writeText(textoSimples).then(() => {  
    const toast = Swal.mixin({  
      toast: true,  
      position: 'top-end',  
      showConfirmButton: false,  
      timer: 2000,  
      timerProgressBar: true  
    });  
    
    toast.fire({  
      icon: 'success',  
      title: 'Texto copiado para a área de transferência!'  
    });  
  });  
}  

// Função para atualizar preview da etiqueta  
function atualizarPreviewEtiqueta() {  
  if (!comunicacaoAtual) return;  
  
  const tamanho = document.getElementById('tamanho-etiqueta').value;  
  const fontSize = document.getElementById('tamanho-fonte').value;  
  const margemEsquerda = document.getElementById('margem-esquerda').value;  
  const margemDireita = document.getElementById('margem-direita').value;  
  const margemSuperior = document.getElementById('margem-superior').value;  
  const margemInferior = document.getElementById('margem-inferior').value;  
  
  const preview = document.getElementById('preview-etiqueta');  
  
  // Atualizar tamanho do preview  
  preview.className = `etiqueta-preview size-${tamanho}`;  
  preview.style.fontSize = `${fontSize}pt`;  
  
  // Gerar texto formatado para etiqueta  
  const textoEtiqueta = gerarTextoEtiquetaHTML(comunicacaoAtual, fontSize, margemEsquerda, margemDireita, margemSuperior, margemInferior);  
  preview.innerHTML = textoEtiqueta;  
}  

// Função para gerar HTML específico para etiqueta  
function gerarTextoEtiquetaHTML(comunicacao, fontSize, margemEsq, margemDir, margemSup, margemInf) {  
  const textoSimples = gerarTextoAnotacao(comunicacao, false);  
  
  // Aplicar formatação específica para etiqueta (mais simples e compacta)  
  let textoFormatado = textoSimples;  
  
  // Destacar elementos principais  
  textoFormatado = textoFormatado.replace(/\b(\d{2}\/\d{2}\/\d{4})\b/g, '<strong>$1</strong>');  
  textoFormatado = textoFormatado.replace(/\b(livro\s+[A-Z]?\s*número?\s*\d+)/gi, '<u>$1</u>');  
  textoFormatado = textoFormatado.replace(/\b(folhas?\s+\d+)/gi, '<u>$1</u>');  
  textoFormatado = textoFormatado.replace(/\b(termo\s+\d+)/gi, '<u>$1</u>');  
  textoFormatado = textoFormatado.replace(/(Código da comunicação:\s*)([^\s,]+)/, '$1<strong>$2</strong>');  
  
  // Destacar nomes principais  
  textoFormatado = textoFormatado.replace(/\b([A-ZÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ]{2,}(?:\s+[A-ZÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ]{2,})+)\b/g, function(match) {  
    if (!match.match(/^(CRC|MM|DR|DRA)$/)) {  
      return '<strong>' + match + '</strong>';  
    }  
    return match;  
  });  
  
  return `<div class="etiqueta-conteudo" style="padding: ${margemSup}mm ${margemDir}mm ${margemInf}mm ${margemEsq}mm; line-height: 1.4; font-size: ${fontSize}pt;">${textoFormatado}</div>`;  
}  

// Função para imprimir etiqueta
function imprimirEtiqueta() {
  const tamanho = document.getElementById('tamanho-etiqueta').value;
  const fontSize = document.getElementById('tamanho-fonte').value;
  const margemEsquerda = document.getElementById('margem-esquerda').value;
  const margemDireita = document.getElementById('margem-direita').value;
  const margemSuperior = document.getElementById('margem-superior').value;
  const margemInferior = document.getElementById('margem-inferior').value;
  
  const textoEtiqueta = gerarTextoEtiquetaHTML(comunicacaoAtual, fontSize, margemEsquerda, margemDireita, margemSuperior, margemInferior);
  
  // Criar janela de impressão
  const printWindow = window.open('', '_blank');
  
  const [width, height] = tamanho.split('x');
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Etiqueta de Anotação</title>
      <style>
        @page {
          size: ${width}cm ${height}cm;
          margin: 0;
        }
        body {
          margin: 0;
          padding: 0;
          font-family: Arial, sans-serif;
          font-size: ${fontSize}pt;
          line-height: 1.4;
          width: ${width}cm;
          height: ${height}cm;
          box-sizing: border-box;
          overflow: hidden;
        }
        .etiqueta-conteudo {
          padding: ${margemSuperior}mm ${margemDireita}mm ${margemInferior}mm ${margemEsquerda}mm;
          text-align: justify;
          word-break: break-word;
          height: 100%;
          box-sizing: border-box;
        }
        strong {
          font-weight: bold;
        }
        u {
          text-decoration: underline;
        }
      </style>
    </head>
    <body>
      ${textoEtiqueta}
    </body>
    </html>
  `);
  
  printWindow.document.close();
  
  // Aguardar carregamento e imprimir
  printWindow.onload = function() {
    printWindow.print();
    printWindow.close();
  };
}

// Função para formatar texto integral (versão genérica melhorada)
function formatarTextoIntegral(texto) {
  if (!texto) return '';
  
  // Converter quebras de linha para padronização
  texto = texto.replace(/\r\n/g, "\n").replace(/\r/g, "\n");
  
  // Processar a seção entre "Código da comunicação:" e "OBSERVAÇÕES:"
  const regexSecaoPrincipal = /(Código da comunicação:[^\n]*\n)([\s\S]*?)(\nOBSERVAÇÕES:)/i;
  const matchSecao = texto.match(regexSecaoPrincipal);
  
  if (matchSecao) {
    let parteAntes = texto.substring(0, matchSecao.index);
    let codigoComunicacao = matchSecao[1];
    let secaoPrincipal = matchSecao[2];
    let observacoes = matchSecao[3];
    let parteDepois = texto.substring(matchSecao.index + matchSecao[0].length);
    
    // Processar a seção principal
    // Preservar quebras duplas
    secaoPrincipal = secaoPrincipal.replace(/\n\s*\n/g, "{{PARAGRAFO}}");
    // Substituir quebras simples por espaço
    secaoPrincipal = secaoPrincipal.replace(/\n/g, " ");
    // Restaurar quebras duplas
    secaoPrincipal = secaoPrincipal.replace(/{{PARAGRAFO}}/g, "\n\n");
    // Limpar espaços múltiplos
    secaoPrincipal = secaoPrincipal.replace(/\s+/g, " ");
    // Remover espaços antes de pontuação
    secaoPrincipal = secaoPrincipal.replace(/\s+\,/g, ",");
    secaoPrincipal = secaoPrincipal.replace(/\s+\./g, ".");
    secaoPrincipal = secaoPrincipal.replace(/\s+:/g, ":");
    secaoPrincipal = secaoPrincipal.replace(/\s+;/g, ";");
    
    // Processar também a parte depois de OBSERVAÇÕES
    // Identificar as duas últimas linhas (cidade/data e operador)
    const linhasDepois = parteDepois.split('\n').filter(linha => linha.trim());
    let textoObservacoes = '';
    let duasUltimasLinhas = '';
    
    if (linhasDepois.length >= 2) {
      // Separar o conteúdo de OBSERVAÇÕES das duas últimas linhas
      const indexUltimasLinhas = linhasDepois.length - 2;
      textoObservacoes = linhasDepois.slice(0, indexUltimasLinhas).join('\n');
      duasUltimasLinhas = '\n\n' + linhasDepois.slice(indexUltimasLinhas).join('\n');
      
      // Processar o texto de observações (remover quebras simples)
      textoObservacoes = textoObservacoes.replace(/\n\s*\n/g, "{{PARAGRAFO}}");
      textoObservacoes = textoObservacoes.replace(/\n/g, " ");
      textoObservacoes = textoObservacoes.replace(/{{PARAGRAFO}}/g, "\n\n");
      textoObservacoes = textoObservacoes.replace(/\s+/g, " ");
      textoObservacoes = textoObservacoes.replace(/\s+\,/g, ",");
      textoObservacoes = textoObservacoes.replace(/\s+\./g, ".");
    } else {
      textoObservacoes = parteDepois;
      duasUltimasLinhas = '';
    }
    
    // Remontar o texto
    texto = parteAntes + codigoComunicacao + secaoPrincipal + observacoes + textoObservacoes + duasUltimasLinhas;
  }
  
  // Garantir espaço antes de OBSERVAÇÕES
  texto = texto.replace(/([^\n])\n*(OBSERVAÇÕES:)/gi, "$1\n\n$2");
  
  // Destacar elementos importantes
  // Código da comunicação
  texto = texto.replace(/Código da comunicação:\s*([^\n]*)/gi, "<strong class='text-primary'>Código da comunicação: $1</strong>");
  
  // Tipo de comunicação (primeira linha quando aplicável)
  texto = texto.replace(/^(Comunicação de[^\n]*)/mi, "<h6 class='text-muted mb-2'>$1</h6>");
  
  // Cartório de origem (segunda linha geralmente)
  texto = texto.replace(/\n([^\n]+-\s*[^\n]*-\s*[A-Z]{2})\s*\n/g, "\n<strong>$1</strong>\n");
  
  // Destacar "Ao" (destinatário)
  texto = texto.replace(/\n(Ao\s+[^\n]+)/gi, "\n<span class='text-info'>$1</span>");
  
  // Formatar o parágrafo principal (que começa com "Aos")
  texto = texto.replace(/\n(Aos\s+\d{2}\/\d{2}\/\d{4}[^\n]+)/gi, "\n\n<div class='alert alert-light p-2 mb-2'>$1</div>");
  
  // Adicionar quebra antes de informações sobre registros
  texto = texto.replace(/\n((?:Ele|Ela|O|A)\s+(?:foi\s+)?(?:registrad[oa]|casad[oa])[^\n]*)/gi, "\n\n<span class='text-secondary'>$1</span>");
  
  // Formatar informações sobre continuação de nomes
  texto = texto.replace(/\n([A-ZÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ\s]+,\s*continu(?:ou|aram)\s+com\s+o\s+mesmo\s+nome[^\n]*)/g, "\n<span class='fst-italic'>$1</span>");
  
  // Destacar OBSERVAÇÕES
  texto = texto.replace(/\n(OBSERVAÇÕES:)([^\n]*)/gi, "\n\n<strong>$1</strong><span class='text-muted'>$2</span>");
  
  // Formatar rodapé (cidade, data e operador)
  texto = texto.replace(/\n([^\n]+-\s*[A-Z]{2},\s*\d{2}\/\d{2}\/\d{4})\s*\n/g, "\n\n<small class='text-muted'>$1</small>\n");
  texto = texto.replace(/\n(Operador:\s*[^\n]+)/gi, "\n<small class='text-muted'>$1</small>");
  
  // Destacar números de processo judicial
  texto = texto.replace(/(\d{7}-\d{2}\.\d{4}\.\d{1}\.\d{2}\.\d{4})/g, "<code>$1</code>");
  
  // Destacar datas no formato DD/MM/AAAA
  texto = texto.replace(/\b(\d{2}\/\d{2}\/\d{4})\b/g, "<span class='fw-semibold'>$1</span>");
  
  // Destacar números de livro, folha e termo
  texto = texto.replace(/\b(livro\s+[A-Z]?\s*número\s*\d+)/gi, "<span class='text-decoration-underline'>$1</span>");
  texto = texto.replace(/\b(folhas?\s+\d+)/gi, "<span class='text-decoration-underline'>$1</span>");
  texto = texto.replace(/\b(termo\s+\d+)/gi, "<span class='text-decoration-underline'>$1</span>");
  
  // Destacar nomes em MAIÚSCULAS (pessoas envolvidas)
  texto = texto.replace(/\b([A-ZÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ]{2,}(?:\s+[A-ZÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ]{2,})+)\b/g, function(match) {
    // Verificar se é realmente um nome (evitar palavras como "OBSERVAÇÕES", "MM", etc)
    if (!match.match(/^(OBSERVAÇÕES|MM|DR|DRA|DOUTOR|DOUTORA|JUIZ|JUÍZA)$/)) {
      return "<strong>" + match + "</strong>";
    }
    return match;
  });
  
  // Destacar títulos e cargos
  texto = texto.replace(/\b(MM\.\s*Juí?z(?:a)?(?:\s+de\s+Direito)?|Dr(?:a)?\.\s*[^\n,]+)/g, "<em>$1</em>");
  
  // Converter quebras de linha restantes para HTML
  texto = texto.replace(/\n/g, "<br>");
  
  // Limpar múltiplas quebras de linha consecutivas
  texto = texto.replace(/(<br>){3,}/g, "<br><br>");
  
  // Adicionar wrapper com classe para estilização
  return `<div class="texto-integral-formatado">${texto}</div>`;
}

// Inicialização quando o DOM estiver pronto  
$(document).ready(function() {  
  // Inicializar ícones Feather  
  if (typeof feather !== 'undefined') {  
    feather.replace();  
  }  
  
  // Aguardar um momento para garantir que todas as bibliotecas foram carregadas  
  let tentativas = 0;  
  const maxTentativas = 10;  
  
  function inicializarDataTable() {  
    if (typeof $.fn.DataTable !== 'undefined') {  
      $('#tabelaComunicacoes').DataTable({  
        responsive: true,  
        pageLength: 25,  
        order: [[4, 'desc']],  
        columnDefs: [  
          { orderable: false, targets: [6,7] },  
          { className: 'text-center', targets: [5,6,7] }  
        ],  
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        language: {  
          emptyTable: 'Nenhum registro encontrado',  
          info: 'Mostrando de _START_ até _END_ de _TOTAL_ registros',  
          infoEmpty: 'Mostrando 0 até 0 de 0 registros',  
          infoFiltered: '(Filtrados de _MAX_ registros)',  
          lengthMenu: 'Mostrar _MENU_ registros por página',  
          loadingRecords: 'Carregando...',  
          processing: 'Processando...',  
          search: 'Pesquisar:',  
          zeroRecords: 'Nenhum registro encontrado',  
          paginate: {   
            next: 'Próximo',   
            previous: 'Anterior',   
            first: 'Primeiro',   
            last: 'Último'   
          }  
        },
        drawCallback: function() {
          // Reinicializar ícones Feather após cada redesenho da tabela
          if (typeof feather !== 'undefined') {
            feather.replace();
          }
        }
      });  
      console.log("DataTables inicializado com sucesso!");  
    } else if (tentativas < maxTentativas) {  
      tentativas++;  
      console.log(`Tentativa ${tentativas} de ${maxTentativas} - DataTables ainda não disponível`);  
      setTimeout(inicializarDataTable, 200);  
    } else {  
      console.error("DataTables não pôde ser inicializado após várias tentativas");  
    }  
  }  
  
  // Iniciar tentativa de carregar DataTables  
  inicializarDataTable();  
  
  /* VER detalhes - MODAL ELEGANTE */  
  $(document).on('click', '.ver-com', function() {  
    const id = $(this).data('id');  
    
    // Mostrar indicador de carregamento  
    Swal.fire({  
      title: 'Carregando detalhes...',  
      allowOutsideClick: false,  
      didOpen: () => {  
        Swal.showLoading();  
      }  
    });  
    
    // Requisição AJAX para obter os detalhes  
    $.ajax({  
      url: 'detalhes_comunicacao_json.php',  
      type: 'GET',  
      data: { id: id },  
      dataType: 'json',  
      success: function(data) {  
        Swal.close();  
        
        if (data.success) {  
          comunicacaoAtual = data.comunicacao;  
          
          // Formatação da data  
          const dataObj = new Date(comunicacaoAtual.data_registro);  
          const dataFormatada = dataObj.toLocaleDateString('pt-BR');  
          
          // Atualizar o modal com os dados  
          $('#detalhe-codigo').text(comunicacaoAtual.codigo_crc || '—');  
          $('#detalhe-tipo').text(comunicacaoAtual.tipo ? comunicacaoAtual.tipo.charAt(0).toUpperCase() + comunicacaoAtual.tipo.slice(1) : '—');  
          $('#detalhe-data').text(dataFormatada);  
          $('#detalhe-lft').text(`${comunicacaoAtual.livro_tipo} ${comunicacaoAtual.livro_numero} / ${comunicacaoAtual.folha} / ${comunicacaoAtual.termo}`);  
          $('#detalhe-nome').text(comunicacaoAtual.nome_principal || '—');  
          
          // Extrair cartório do texto  
          const matchCartorio = comunicacaoAtual.texto_integral?.match(/^([^\n]+?)\s+(?:Ao|ao)\s+/m);  
          $('#detalhe-cartorio').text(matchCartorio ? matchCartorio[1].trim() : '—');  
          
          // Status com badge  
          $('#detalhe-status')  
            .text(getStatusLabel(comunicacaoAtual.status || 'pendente'))  
            .removeClass()  
            .addClass(`badge badge-status-${comunicacaoAtual.status || 'pendente'}`);  
          
          // Texto integral formatado  
          if (comunicacaoAtual.texto_integral) {  
            const textoFormatado = formatarTextoIntegral(comunicacaoAtual.texto_integral);  
            $('#detalhe-texto').html(textoFormatado);  
          } else {  
            $('#detalhe-texto').text('Texto não disponível');  
          }  
          
          // Gerar texto da anotação formatado em HTML
          const textoAnotacao = gerarTextoAnotacao(comunicacaoAtual, true);  
          $('#detalhe-texto-anotacao').html(textoAnotacao);  
          
          // Atualizar preview da etiqueta  
          atualizarPreviewEtiqueta();  
          
          // Configurar botões de ação com o ID correto  
          $('.alterar-status-modal').data('id', comunicacaoAtual.id);  
          
          // Abrir o modal  
          $('#detalhesComModal').modal('show');  
          
          // Reinicializar ícones Feather dentro do modal  
          setTimeout(() => feather.replace(), 100);  
        } else {  
          Swal.fire({  
            icon: 'error',  
            title: 'Erro',  
            text: data.message || 'Não foi possível carregar os detalhes desta comunicação.'  
          });  
        }  
      },  
      error: function(xhr, status, error) {  
        Swal.close();  
        console.error("Erro na requisição AJAX:", error, xhr.responseText);  
        
        Swal.fire({  
          icon: 'error',  
          title: 'Erro de Comunicação',  
          text: 'Não foi possível contatar o servidor. Verifique sua conexão e tente novamente.'  
        });  
      }  
    });  
  });  

  // Event listeners para configurações da etiqueta  
  $(document).on('change', '#tamanho-etiqueta', atualizarPreviewEtiqueta);  
  $(document).on('input', '#tamanho-fonte', function() {  
    $('#fonte-preview').text(this.value);  
    atualizarPreviewEtiqueta();  
  });  
  
  // Event listeners para margens
  $(document).on('input', '#margem-esquerda, #margem-direita, #margem-superior, #margem-inferior', atualizarPreviewEtiqueta);

  /* EXCLUIR (soft delete) */  
  $(document).on('click', '.del-com', function() {  
    const id = $(this).data('id');  
    const cod = $(this).data('cod');  
    Swal.fire({  
      title: 'Excluir comunicação?',  
      text: `Tem certeza que deseja excluir o código ${cod}?`,  
      icon: 'warning',  
      showCancelButton: true,  
      confirmButtonColor: '#dc3545',  
      cancelButtonColor: '#6c757d',  
      confirmButtonText: 'Sim, excluir',  
      cancelButtonText: 'Cancelar'  
    }).then((result) => {  
      if (result.isConfirmed) {  
        $.post('comunicacoes.php', {  
          atualizar_status: 1,  
          id: id,  
          status: 'excluido'  
        }, function(resp) {  
          if (resp.success) {  
            Swal.fire({  
              icon: 'success',   
              title: 'Excluído com sucesso',   
              timer: 1500,   
              showConfirmButton: false  
            }).then(() => location.reload());  
          } else {  
            Swal.fire('Erro', resp.message || 'Não foi possível excluir.', 'error');  
          }  
        }, 'json').fail(() => Swal.fire('Erro', 'Falha na comunicação.', 'error'));  
      }  
    });  
  });  
  
  /* ALTERAR STATUS - Dropdown na tabela */  
  $(document).on('click', '.alterar-status', function(e) {  
    e.preventDefault();  
    const id = $(this).data('id');  
    const status = $(this).data('status');  
    const $btn = $(this).closest('.dropdown').find('.dropdown-toggle');  
    
    $.ajax({  
      url: 'comunicacoes.php',  
      type: 'POST',  
      data: {  
        atualizar_status: 1,  
        id: id,  
        status: status  
      },  
      dataType: 'json',  
      success: function(response) {  
        if (response.success) {  
          $btn.removeClass('badge-status-pendente badge-status-anotada badge-status-recusada badge-status-excluido')  
              .addClass('badge-status-' + status)  
              .text(getStatusLabel(status));  
              
          const toast = Swal.mixin({  
            toast: true,  
            position: 'top-end',  
            showConfirmButton: false,  
            timer: 3000,  
            timerProgressBar: true  
          });  
          
          toast.fire({  
            icon: 'success',  
            title: `Status alterado para ${getStatusLabel(status)}`  
          });  
        } else {  
          Swal.fire('Erro', response.message || 'Não foi possível alterar o status.', 'error');  
        }  
      },  
      error: function() {  
        Swal.fire('Erro', 'Falha na comunicação com o servidor.', 'error');  
      }  
    });  
  });  
  
  /* ALTERAR STATUS - A partir do modal de detalhes */  
  $(document).on('click', '.alterar-status-modal', function() {  
    const id = $(this).data('id');  
    const status = $(this).data('status');  
    
    $.ajax({  
      url: 'comunicacoes.php',  
      type: 'POST',  
      data: {  
        atualizar_status: 1,  
        id: id,  
        status: status  
      },  
      dataType: 'json',  
      success: function(response) {  
        if (response.success) {  
          const $row = $(`.ver-com[data-id="${id}"]`).closest('tr');  
          const $statusCell = $row.find('td:eq(5)');  
          const $statusBtn = $statusCell.find('.dropdown-toggle');  
          
          if ($statusBtn.length) {  
            $statusBtn.removeClass('badge-status-pendente badge-status-anotada badge-status-recusada badge-status-excluido')  
                .addClass('badge-status-' + status)  
                .text(getStatusLabel(status));  
          }  
          
          $('#detalhe-status')  
            .text(getStatusLabel(status))  
            .removeClass()  
            .addClass(`badge badge-status-${status}`);  
          
          $('#detalhesComModal').modal('hide');  
          
          Swal.fire({  
            icon: 'success',  
            title: 'Status Atualizado',  
            text: `A comunicação foi marcada como "${getStatusLabel(status)}"`,  
            timer: 2000,  
            showConfirmButton: false  
          });  
        } else {  
          Swal.fire('Erro', response.message || 'Não foi possível alterar o status.', 'error');  
        }  
      },  
      error: function() {  
        Swal.fire('Erro', 'Falha na comunicação com o servidor.', 'error');  
      }  
    });  
  });  

  /* Dropzone customizado para upload de PDF */  
  const dropzone = document.getElementById('dropzone');  
  const fileInput = document.getElementById('arquivo_pdf');  
  const selectedFile = document.getElementById('selected-file');  
  const fileName = document.getElementById('file-name');  
  const removeFile = document.getElementById('remove-file');  
  const submitBtn = document.getElementById('submit-pdf');  

  if (dropzone && fileInput) {  
    ['dragenter', 'dragover'].forEach(eventName => {  
      dropzone.addEventListener(eventName, (e) => {  
        e.preventDefault();  
        dropzone.classList.add('dropzone-active');  
      }, false);  
    });  

    ['dragleave', 'drop'].forEach(eventName => {  
      dropzone.addEventListener(eventName, (e) => {  
        e.preventDefault();  
        dropzone.classList.remove('dropzone-active');  
      }, false);  
    });  

    dropzone.addEventListener('drop', (e) => {  
      e.preventDefault();  
      if (e.dataTransfer.files.length) {  
        fileInput.files = e.dataTransfer.files;  
        updateFileInfo();  
      }  
    });  

    fileInput.addEventListener('change', updateFileInfo);  
  }  

  function updateFileInfo() {  
    if (fileInput.files.length > 0) {  
      const file = fileInput.files[0];  
      if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {  
        fileName.textContent = file.name;  
        selectedFile.style.display = 'block';  
        submitBtn.disabled = false;  
      } else {  
        Swal.fire({  
          icon: 'error',  
          title: 'Tipo de arquivo inválido',  
          text: 'Por favor, selecione apenas arquivos PDF.'  
        });  
        clearFileInput();  
      }  
    } else {  
      clearFileInput();  
    }  
  }  

  if (removeFile) {  
    removeFile.addEventListener('click', (e) => {  
      e.preventDefault();  
      clearFileInput();  
    });  
  }  

  function clearFileInput() {  
    if (fileInput) fileInput.value = '';  
    if (selectedFile) selectedFile.style.display = 'none';  
    if (submitBtn) submitBtn.disabled = true;  
  }  

  // Envio do formulário com feedback  
  $('#uploadPDFForm').on('submit', function(e) {  
    e.preventDefault();  
    
    const formData = new FormData(this);  
    
    Swal.fire({  
      title: 'Processando PDF',  
      text: 'Realizando OCR e analisando o documento...',  
      allowOutsideClick: false,  
      allowEscapeKey: false,  
      didOpen: () => {  
        Swal.showLoading();  
        
        $.ajax({  
          url: 'upload_pdf_crc.php',  
          type: 'POST',  
          data: formData,  
          processData: false,  
          contentType: false,  
          dataType: 'json',  
          success: function(response) {  
            if (response.success) {  
              console.log("Resposta do servidor:", response);  
              
              let falhasHtml = '';  
              if (response.falhas_detalhadas && response.falhas_detalhadas.length > 0) {  
                falhasHtml = '<p><strong>Detalhes das falhas:</strong></p><ul style="text-align:left; max-height:150px; overflow-y:auto;">';  
                response.falhas_detalhadas.forEach(falha => {  
                  falhasHtml += `<li>Comunicação #${falha.indice} - Código: ${falha.codigo} - ${falha.preview}</li>`;  
                });  
                falhasHtml += '</ul>';  
              }  
              
              const naoDetectadas = (response.total_estimado || 119) - response.comunicacoes;  
              let alertaNaoDetectadas = '';  
              if (naoDetectadas > 0) {  
                alertaNaoDetectadas = `<div class="alert alert-warning mb-3">  
                  <strong>Atenção:</strong> Aproximadamente ${naoDetectadas} comunicações não foram detectadas no PDF.  
                </div>`;  
              }  
              
              Swal.fire({  
                icon: 'success',  
                title: 'PDF processado com sucesso!',  
                html: `  
                  ${alertaNaoDetectadas}  
                  <div class="text-start">  
                    <p><strong>Resultado do processamento:</strong></p>  
                    <ul>  
                      <li>${response.comunicacoes || 0} comunicações identificadas</li>  
                      <li>${response.comunicacoes || 0} comunicações processadas</li>  
                      <li>${response.inseridos || 0} comunicações inseridas</li>  
                      <li>${response.duplicados || 0} comunicações duplicadas</li>  
                      <li>${response.falhos || 0} comunicações com falhas</li>  
                    </ul>  
                  </div>  
                  ${falhasHtml}  
                `,  
                confirmButtonText: 'Continuar',  
                width: '600px',  
                customClass: {  
                  htmlContainer: 'swal-html-container-custom'  
                }  
              }).then(() => {  
                location.reload();  
              });  
            } else {  
              Swal.fire({  
                icon: 'error',  
                title: 'Erro no processamento',  
                text: response.message || 'Ocorreu um erro ao processar o PDF.'  
              });  
            }  
          },  
          error: function(xhr, status, error) {  
            console.error("Erro na requisição AJAX:", error, xhr.responseText);  
            Swal.fire({  
              icon: 'error',  
              title: 'Erro na comunicação',  
              text: 'Não foi possível enviar o arquivo. Tente novamente.'  
            });  
          }  
        });  

        document.head.insertAdjacentHTML('beforeend', `  
          <style>  
            .swal-html-container-custom {  
              max-height: 70vh;  
              overflow-y: auto;  
            }  
          </style>  
        `);        
      }  
    });  
  });  

  <?php if (isset($_GET['success']) && isset($_GET['ins'])): ?>  
    Swal.fire({  
      icon: 'success',  
      title: 'Comunicações processadas',  
      html: `  
        <div class="text-start">  
          <p><strong>Resultado do processamento:</strong></p>  
          <ul>  
            <li>${parseInt(<?= intval($_GET['ins']) ?>)} comunicações inseridas</li>  
            <li>${parseInt(<?= intval($_GET['dup'] ?? 0) ?>)} comunicações duplicadas</li>  
            <li>${parseInt(<?= intval($_GET['fal'] ?? 0) ?>)} comunicações com falhas</li>  
          </ul>  
        </div>  
      `,  
      confirmButtonText: 'OK',  
    });  
  <?php endif; ?>  
});  
</script>  

<?php  
// Função auxiliar para gerar classes de botão conforme status  
function getStatusClass($status) {  
  switch ($status) {  
    case 'anotada': return 'btn-status-anotada';  
    case 'recusada': return 'btn-status-recusada';  
    case 'excluido': return 'btn-status-excluido';  
    default: return 'btn-status-pendente';  
  }  
}  

// Função auxiliar para gerar rótulos de status  
function getStatusLabel($status) {  
  switch ($status) {  
    case 'anotada': return 'Anotada';  
    case 'recusada': return 'Recusada';  
    case 'excluido': return 'Excluído';  
    default: return 'Pendente';  
  }  
}  
?>  

<?php include 'includes/footer.php'; ?>