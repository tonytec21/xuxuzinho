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
$tipos = $pdo->query("SELECT * FROM tipos_bem ORDER BY nome")->fetchAll();

include 'includes/header.php';
?>

<div class="container-fluid py-4">
  <div class="row mb-3">
    <div class="col d-flex justify-content-between align-items-center">
      <h1 class="h3">Gerenciar Tipos de Bem</h1>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novoTipoModal">
        <i data-feather="plus" class="me-1"></i> Novo Tipo
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
      <table id="tabelaTipos" class="table table-striped nowrap" style="width:100%">
        <thead>
          <tr>
            <th>Nome</th>
            <th>Descrição</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($tipos as $t): ?>
          <tr>
            <td><?= htmlspecialchars($t['nome']) ?></td>
            <td><?= htmlspecialchars($t['descricao']) ?></td>
            <td>
              <button class="btn btn-sm btn-outline-primary btn-editar" 
                      data-id="<?= $t['id'] ?>"
                      data-nome="<?= htmlspecialchars($t['nome'],ENT_QUOTES) ?>"
                      data-descricao="<?= htmlspecialchars($t['descricao'],ENT_QUOTES) ?>"
                      title="Editar">
                <i data-feather="edit"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger btn-excluir"
                      data-id="<?= $t['id'] ?>"
                      data-nome="<?= htmlspecialchars($t['nome'],ENT_QUOTES) ?>"
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
        <button type="submit" class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Editar Tipo -->
<div class="modal fade" id="editarTipoModal" tabindex="-1">
  <div class="modal-dialog">
    <form action="editar_tipo.php" method="POST" class="modal-content">
      <input type="hidden" name="id" id="edit-tipo-id">
      <div class="modal-header">
        <h5 class="modal-title">Editar Tipo de Bem</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nome</label>
          <input name="nome" id="edit-tipo-nome" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Descrição</label>
          <textarea name="descricao" id="edit-tipo-descricao" class="form-control" rows="2"></textarea>
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
  $('#tabelaTipos').DataTable({ responsive: true, language:{ url:'//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'} });

  // editar
  $('.btn-editar').click(function(){
    const btn = $(this);
    $('#edit-tipo-id').val(btn.data('id'));
    $('#edit-tipo-nome').val(btn.data('nome'));
    $('#edit-tipo-descricao').val(btn.data('descricao'));
    $('#editarTipoModal').modal('show');
  });
  // excluir
  $('.btn-excluir').click(function(){
    const id   = $(this).data('id'),
          nome = $(this).data('nome');
    Swal.fire({
      title:'Excluir tipo?', text:`Excluir "${nome}"?`,
      icon:'warning', showCancelButton:true, confirmButtonText:'Sim, excluir'
    }).then(r=> r.isConfirmed && location.href=`excluir_tipo.php?id=${id}`);
  });
});
</script>
