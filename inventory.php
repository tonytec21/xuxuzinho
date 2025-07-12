<?php
date_default_timezone_set('America/Sao_Paulo');
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

$pdo->exec("
  CREATE TABLE IF NOT EXISTS tipos_bem (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
  CREATE TABLE IF NOT EXISTS categorias_bem (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tipo_id) REFERENCES tipos_bem(id)
      ON UPDATE CASCADE
      ON DELETE RESTRICT
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
  CREATE TABLE IF NOT EXISTS bens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_id INT NOT NULL,
    categoria_id INT NOT NULL,
    modelo VARCHAR(255) NOT NULL,
    configuracao TEXT,
    quantidade INT NOT NULL DEFAULT 1,
    localizacao VARCHAR(255),
    status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
    data_aquisicao DATE,
    usuario_id INT,
    observacoes TEXT,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tipo_id)      REFERENCES tipos_bem(id)
      ON UPDATE CASCADE
      ON DELETE RESTRICT,
    FOREIGN KEY (categoria_id) REFERENCES categorias_bem(id)
      ON UPDATE CASCADE
      ON DELETE RESTRICT
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Usuário logado
$usuario_id = $_SESSION['usuario_id'];

// Mensagem de feedback via URL
$mensagem = $_GET['success']      ??
            $_GET['success_edit'] ??
            $_GET['success_del']  ??
            '';

// Tipo de alerta (bootstrap) para a mensagem
if (isset($_GET['success'])) {
    $mensagem_tipo = 'success';
} elseif (isset($_GET['success_edit'])) {
    $mensagem_tipo = 'info';
} elseif (isset($_GET['success_del'])) {
    $mensagem_tipo = 'warning';
} else {
    $mensagem_tipo = '';
}

// Buscar todos os tipos e categorias
$tipos      = $pdo->query("SELECT * FROM tipos_bem ORDER BY nome")->fetchAll();
$categorias = $pdo->query("SELECT * FROM categorias_bem ORDER BY nome")->fetchAll();

// Contagens para cards
$total_bens = $pdo
  ->query("SELECT COALESCE(SUM(quantidade),0) FROM bens WHERE status = 'ativo'")
  ->fetchColumn();
$tipo_counts = $pdo->query("
    SELECT
      t.nome,
      COALESCE(SUM(b.quantidade),0) AS qtde
    FROM tipos_bem t
    LEFT JOIN bens b
      ON b.tipo_id = t.id
     AND b.status = 'ativo'
    GROUP BY t.nome
    ORDER BY t.nome
")->fetchAll(PDO::FETCH_ASSOC);

// Filtros recebidos via GET
$filtro_tipo      = $_GET['tipo']      ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';

// Montar condições da query
$conds  = ["b.status = 'ativo'"];
$params = [];

if ($filtro_tipo !== '') {
    $conds[]  = "b.tipo_id = ?";
    $params[] = $filtro_tipo;
}
if ($filtro_categoria !== '') {
    $conds[]  = "b.categoria_id = ?";
    $params[] = $filtro_categoria;
}

// Consulta principal
$sql = "
  SELECT b.*, t.nome AS tipo_nome, c.nome AS categoria_nome
  FROM bens b
  JOIN tipos_bem t ON b.tipo_id = t.id
  JOIN categorias_bem c ON b.categoria_id = c.id
  WHERE " . implode(' AND ', $conds) . "
  ORDER BY b.data_cadastro DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bens = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container-fluid py-4">

  <!-- Cabeçalho e botões -->
  <div class="row mb-3">
    <div class="col d-flex flex-wrap gap-2 align-items-center">
      <h1 class="h3 me-auto">Inventário de Bens</h1>
      <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#novoTipoModal">
        <i data-feather="layers" class="me-1"></i> Novo Tipo
      </button>
      <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#novaCatModal">
        <i data-feather="tag" class="me-1"></i> Nova Categoria
      </button>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novoBemModal">
        <i data-feather="plus" class="me-1"></i> Novo Bem
      </button>
    </div>
  </div>

  <!-- Cards de estatísticas -->
  <div class="row mb-4">
    <div class="col-md-3 mb-3">
      <div class="card text-white bg-primary h-100">
        <div class="card-body">
          <h5 class="card-title">Total de Bens</h5>
          <p class="card-text fs-2"><?= $total_bens ?></p>
        </div>
      </div>
    </div>
    <?php foreach ($tipo_counts as $tc): ?>
      <div class="col-md-3 mb-3">
        <div class="card h-100">
          <div class="card-body">
            <h6 class="card-title"><?= htmlspecialchars($tc['nome']) ?></h6>
            <p class="card-text fs-3"><?= $tc['qtde'] ?></p>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Mensagem de feedback -->
  <?php if ($mensagem): ?>
    <div class="alert alert-<?= $mensagem_tipo ?>">
      <?= htmlspecialchars($mensagem) ?>
    </div>
  <?php endif; ?>

  <!-- Filtros -->
  <div class="card mb-4">
    <div class="card-body">
      <form id="filtrosForm" class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Filtrar por Tipo</label>
          <select id="filtroTipo" name="tipo" class="form-select">
            <option value="">— Todos —</option>
            <?php foreach ($tipos as $t): ?>
              <option value="<?= $t['id'] ?>" <?= $filtro_tipo == $t['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($t['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Filtrar por Categoria</label>
          <select id="filtroCategoria" name="categoria" class="form-select">
            <option value="">— Todas —</option>
            <?php foreach ($categorias as $c): ?>
              <option data-tipo="<?= $c['tipo_id'] ?>"
                      value="<?= $c['id'] ?>"
                      <?= $filtro_categoria == $c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <button type="submit" class="btn btn-outline-secondary me-2">Aplicar</button>
          <a href="inventory.php" class="btn btn-outline-secondary">Limpar</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Tabela de bens -->
  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table id="tabelaBens" class="table table-striped table-hover nowrap" style="width:100%">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Categoria</th>
              <th>Modelo</th>
              <th>Qtd.</th>
              <th>Localização</th>
              <th>Data Aquisição</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($bens as $b): ?>
              <tr>
                <td><?= htmlspecialchars($b['tipo_nome']) ?></td>
                <td><?= htmlspecialchars($b['categoria_nome']) ?></td>
                <td><?= htmlspecialchars($b['modelo']) ?></td>
                <td><?= $b['quantidade'] ?></td>
                <td>
                  <?php
                    $loc = htmlspecialchars($b['localizacao']);
                    echo mb_strlen($loc) > 30
                        ? mb_substr($loc, 0, 30) . '…'
                        : $loc;
                  ?>
                </td>
                <td><?= $b['data_aquisicao'] ? date('d/m/Y', strtotime($b['data_aquisicao'])) : '–' ?></td>
                <td class="d-flex gap-1">
                  <button class="btn btn-sm btn-outline-secondary btn-visualizar"
                          data-tipo="<?= htmlspecialchars($b['tipo_nome'], ENT_QUOTES) ?>"
                          data-categoria="<?= htmlspecialchars($b['categoria_nome'], ENT_QUOTES) ?>"
                          data-modelo="<?= htmlspecialchars($b['modelo'], ENT_QUOTES) ?>"
                          data-configuracao="<?= htmlspecialchars($b['configuracao'], ENT_QUOTES) ?>"
                          data-quantidade="<?= $b['quantidade'] ?>"
                          data-localizacao="<?= htmlspecialchars($b['localizacao'], ENT_QUOTES) ?>"
                          data-dataaqu="<?= $b['data_aquisicao'] ?>"
                          data-observacoes="<?= htmlspecialchars($b['observacoes'], ENT_QUOTES) ?>"
                          data-cadastro="<?= date('d/m/Y H:i', strtotime($b['data_cadastro'])) ?>"
                          title="Visualizar">
                    <i data-feather="eye"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-primary btn-editar"
                          data-id="<?= $b['id'] ?>"
                          data-tipo="<?= $b['tipo_id'] ?>"
                          data-categoria="<?= $b['categoria_id'] ?>"
                          data-modelo="<?= htmlspecialchars($b['modelo'], ENT_QUOTES) ?>"
                          data-configuracao="<?= htmlspecialchars($b['configuracao'], ENT_QUOTES) ?>"
                          data-quantidade="<?= $b['quantidade'] ?>"
                          data-localizacao="<?= htmlspecialchars($b['localizacao'], ENT_QUOTES) ?>"
                          data-dataaqu="<?= $b['data_aquisicao'] ?>"
                          data-observacoes="<?= htmlspecialchars($b['observacoes'], ENT_QUOTES) ?>"
                          title="Editar">
                    <i data-feather="edit"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger btn-excluir"
                          data-id="<?= $b['id'] ?>"
                          data-modelo="<?= htmlspecialchars($b['modelo'], ENT_QUOTES) ?>"
                          title="Excluir">
                    <i data-feather="trash-2"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal Visualizar Bem -->
<div class="modal fade" id="visualizarBemModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalhes do Bem</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <dl class="row">
          <dt class="col-sm-3">Tipo</dt>             <dd class="col-sm-9" id="view-tipo"></dd>
          <dt class="col-sm-3">Categoria</dt>       <dd class="col-sm-9" id="view-categoria"></dd>
          <dt class="col-sm-3">Modelo</dt>          <dd class="col-sm-9" id="view-modelo"></dd>
          <dt class="col-sm-3">Configuração</dt>    <dd class="col-sm-9" id="view-configuracao"></dd>
          <dt class="col-sm-3">Quantidade</dt>      <dd class="col-sm-9" id="view-quantidade"></dd>
          <dt class="col-sm-3">Localização</dt>     <dd class="col-sm-9" id="view-localizacao"></dd>
          <dt class="col-sm-3">Data Aquisição</dt>  <dd class="col-sm-9" id="view-dataaqu"></dd>
          <dt class="col-sm-3">Observações</dt>     <dd class="col-sm-9" id="view-observacoes"></dd>
          <dt class="col-sm-3">Cadastrado em</dt>   <dd class="col-sm-9" id="view-cadastro"></dd>
        </dl>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Cadastrar Bem -->
<div class="modal fade" id="novoBemModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="formNovoBem" action="salvar_bem.php" method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Cadastrar Novo Bem</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php include 'partials/_form_bem.php'; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Editar Bem -->
<div class="modal fade" id="editarBemModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="formEditarBem" action="editar_bem.php" method="POST" class="modal-content">
      <input type="hidden" name="id" id="edit-id">
      <div class="modal-header">
        <h5 class="modal-title">Editar Bem</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php include 'partials/_form_bem.php'; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Atualizar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Novo Tipo -->
<div class="modal fade" id="novoTipoModal" tabindex="-1">
  <div class="modal-dialog">
    <form action="salvar_tipo.php" method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Novo Tipo de Bem</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nome</label>
          <input name="nome" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Descrição</label>
          <textarea name="descricao" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Salvar Tipo</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Nova Categoria -->
<div class="modal fade" id="novaCatModal" tabindex="-1">
  <div class="modal-dialog">
    <form action="salvar_categoria.php" method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Nova Categoria</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Tipo</label>
          <select name="tipo_id" class="form-select" required>
            <option value="">— Selecione —</option>
            <?php foreach($tipos as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Categoria</label>
          <input name="nome" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Descrição</label>
          <textarea name="descricao" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Salvar Categoria</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- DataTables JS (após footer) -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>  
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>  
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>  
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>  

<script>
feather.replace();

$(document).ready(function(){
  $('#tabelaBens').DataTable({
    responsive: true,
    language: {
      lengthMenu: "Mostrar _MENU_ registros por página",
      zeroRecords: "Nenhum registro encontrado",
      info: "Mostrando _START_ até _END_ de _TOTAL_ registros",
      infoEmpty: "Mostrando 0 até 0 de 0 registros",
      infoFiltered: "(filtrado de _MAX_ registros)",
      loadingRecords: "Carregando...",
      processing: "Processando...",
      search: "Pesquisar:",
      paginate: {
        first:    "Primeiro",
        previous: "Anterior",
        next:     "Próximo",
        last:     "Último"
      },
      aria: {
        sortAscending:  ": ativar para ordenar coluna de forma ascendente",
        sortDescending: ": ativar para ordenar coluna de forma descendente"
      }
    }
  });

  // Filtrar categorias conforme tipo selecionado
  $('#filtroTipo').on('change', function(){
    const tipo = this.value;
    $('#filtroCategoria option').each(function(){
      $(this).toggle(!tipo || $(this).data('tipo') == tipo);
    });
  }).trigger('change');

  // Visualizar
  $('.btn-visualizar').on('click', function(){
    const btn = $(this);
    $('#view-tipo').text(btn.data('tipo'));
    $('#view-categoria').text(btn.data('categoria'));
    $('#view-modelo').text(btn.data('modelo'));
    $('#view-configuracao').text(btn.data('configuracao'));
    $('#view-quantidade').text(btn.data('quantidade'));
    $('#view-localizacao').text(btn.data('localizacao'));
    $('#view-dataaqu').text(
      btn.data('dataaqu')
        ? new Date(btn.data('dataaqu')).toLocaleDateString('pt-BR')
        : '–'
    );
    $('#view-observacoes').text(btn.data('observacoes'));
    $('#view-cadastro').text(btn.data('cadastro'));
    $('#visualizarBemModal').modal('show');
  });

  // Editar
  $('.btn-editar').on('click', function(){
    const btn = $(this), form = $('#formEditarBem');
    $('#edit-id').val(btn.data('id'));
    form.find('[name="tipo_id"]').val(btn.data('tipo')).trigger('change');
    form.find('[name="categoria_id"]').val(btn.data('categoria'));
    form.find('[name="modelo"]').val(btn.data('modelo'));
    form.find('[name="configuracao"]').val(btn.data('configuracao'));
    form.find('[name="quantidade"]').val(btn.data('quantidade'));
    form.find('[name="localizacao"]').val(btn.data('localizacao'));
    form.find('[name="data_aquisicao"]').val(btn.data('dataaqu'));
    form.find('[name="observacoes"]').val(btn.data('observacoes'));
    $('#editarBemModal').modal('show');
  });

  // Excluir
  $('.btn-excluir').on('click', function(){
    const id = $(this).data('id'),
          modelo = $(this).data('modelo');
    Swal.fire({
      title: 'Excluir bem?',
      text: `Deseja realmente excluir "${modelo}"?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sim, excluir'  
    }).then(r => {
      if (r.isConfirmed) {
        window.location.href = `excluir_bem.php?id=${id}`;
      }
    });
  });
});
</script>
