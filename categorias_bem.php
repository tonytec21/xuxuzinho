<?php
date_default_timezone_set('America/Sao_Paulo');
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Feedback
$mensagem      = $_GET['success']      ?? ($_GET['success_edit'] ?? ($_GET['success_del'] ?? ''));
if (isset($_GET['success'])) {
    $mensagem_tipo = 'success';
} elseif (isset($_GET['success_edit'])) {
    $mensagem_tipo = 'info';
} elseif (isset($_GET['success_del'])) {
    $mensagem_tipo = 'warning';
} else {
    $mensagem_tipo = '';
}

// Fetch
$tipos      = $pdo->query("SELECT * FROM tipos_bem ORDER BY nome")->fetchAll();
$categorias = $pdo->query("
  SELECT c.*, t.nome AS tipo_nome
  FROM categorias_bem c
  JOIN tipos_bem t ON c.tipo_id = t.id
  ORDER BY t.nome, c.nome
")->fetchAll();

include 'includes/header.php';
?>

<div class="container-fluid py-4">
  <div class="row mb-3">
    <div class="col d-flex justify-content-between align-items-center">
      <h1 class="h3">Gerenciar Categorias de Bem</h1>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novaCatModal">
        <i data-feather="plus" class="me-1"></i> Nova Categoria
      </button>
    </div>
  </div>

  <?php if($mensagem): ?>
    <div class="alert alert-<?= $mensagem_tipo ?>">
      <?= htmlspecialchars($mensagem) ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <table id="tabelaCats" class="table table-striped nowrap" style="width:100%">
        <thead>
          <tr>
            <th>Tipo</th>
            <th>Categoria</th>
            <th>Descrição</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($categorias as $c): ?>
          <tr>
            <td><?= htmlspecialchars($c['tipo_nome']) ?></td>
            <td><?= htmlspecialchars($c['nome']) ?></td>
            <td><?= htmlspecialchars($c['descricao']) ?></td>
            <td>
              <button class="btn btn-sm btn-outline-primary btn-editar"
                      data-id="<?= $c['id'] ?>"
                      data-tipo="<?= $c['tipo_id'] ?>"
                      data-nome="<?= htmlspecialchars($c['nome'],ENT_QUOTES) ?>"
                      data-descricao="<?= htmlspecialchars($c['descricao'],ENT_QUOTES) ?>"
                      title="Editar"><i data-feather="edit"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger btn-excluir"
                      data-id="<?= $c['id'] ?>"
                      data-nome="<?= htmlspecialchars($c['nome'],ENT_QUOTES) ?>"
                      title="Excluir"><i data-feather="trash-2"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
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
        <button type="submit" class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Editar Categoria -->
<div class="modal fade" id="editarCatModal" tabindex="-1">
  <div class="modal-dialog">
    <form action="editar_categoria.php" method="POST" class="modal-content">
      <input type="hidden" name="id" id="edit-cat-id">
      <div class="modal-header">
        <h5 class="modal-title">Editar Categoria</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Tipo</label>
          <select name="tipo_id" id="edit-cat-tipo" class="form-select" required>
            <option value="">— Selecione —</option>
            <?php foreach($tipos as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Categoria</label>
          <input name="nome" id="edit-cat-nome" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Descrição</label>
          <textarea name="descricao" id="edit-cat-descricao" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Atualizar</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
feather.replace();
$(function(){
  $('#tabelaCats').DataTable({ responsive:true, language:{ url:'//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'} });

  // preencher edição
  $('.btn-editar').click(function(){
    const b = $(this);
    $('#edit-cat-id').val(b.data('id'));
    $('#edit-cat-tipo').val(b.data('tipo'));
    $('#edit-cat-nome').val(b.data('nome'));
    $('#edit-cat-descricao').val(b.data('descricao'));
    $('#editarCatModal').modal('show');
  });
  // excluir
  $('.btn-excluir').click(function(){
    const id   = $(this).data('id'),
          nome = $(this).data('nome');
    Swal.fire({
      title:'Excluir categoria?',
      text:`Excluir "${nome}"?`,
      icon:'warning', showCancelButton:true, confirmButtonText:'Sim, excluir'
    }).then(r=> r.isConfirmed && location.href=`excluir_categoria.php?id=${id}`);
  });
});
</script>
