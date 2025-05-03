<?php
/**************************************************************************
 * EDITAR_LIVRO.PHP  –  edição inline das folhas / termos de um livro
 *************************************************************************/
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
$titulo_pagina = "Edição de Livro";

// ────────────── parâmetro obrigatório ──────────────
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: livros.php');  // fallback
    exit;
}
$livro_id = (int)$_GET['id'];

// ────────────── dados do livro ──────────────
$stmt = $pdo->prepare("SELECT * FROM livros WHERE id = ?");
$stmt->execute([$livro_id]);
$livro = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$livro) {
    $_SESSION['mensagem'] = "Livro não encontrado.";
    $_SESSION['tipo_mensagem'] = "danger";
    header('Location: livros.php');
    exit;
}

// ────────────── páginas / folhas ──────────────
$sqlPaginas = "
    SELECT p.id,
           p.numero_pagina,
           p.numero_folha,
           p.eh_verso,
           p.termo_inicial,
           p.termo_final,
           p.caminho
      FROM paginas_livro p
      JOIN anexos_livros a ON p.anexo_id = a.id
     WHERE a.livro_id = :livro
  ORDER BY p.numero_pagina ASC";
$stmt = $pdo->prepare($sqlPaginas);
$stmt->execute(['livro' => $livro_id]);
$paginas = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            Editar Livro <?php echo htmlspecialchars($livro['tipo']." – ".$livro['numero']); ?>
        </h1>
        <a href="livros.php?id=<?php echo $livro_id; ?>" class="btn btn-outline-secondary">
            <i data-feather="arrow-left" class="me-1" style="width:14px;height:14px;"></i> Voltar
        </a>
    </div>

    <!-- Resumo rápido do livro -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap gap-3">
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                <?php echo ucfirst($livro['tipo']); ?>
            </span>
            <span class="badge bg-light text-dark px-3 py-2">
                Folhas: <?php echo $livro['qtd_folhas']; ?>
                <?php echo $livro['contagem_frente_verso'] ? '(F/V)' : '(F)'; ?>
            </span>
            <span class="badge bg-light text-dark px-3 py-2">
                <?php echo $livro['termos_por_pagina']; ?> termo(s)/página
            </span>
        </div>
    </div>

    <!-- Tabela de páginas com edição inline -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-primary">
                <i data-feather="edit" class="me-2 text-primary" style="width:18px;height:18px;"></i>
                Editar Folhas / Termos
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelaFolhas" class="table table-hover align-middle">
                    <thead class="bg-light">
                    <tr>
                        <th>#</th>
                        <th>Folha</th>
                        <th>Lado</th>
                        <th>Termo&nbsp;Inicial</th>
                        <th>Termo&nbsp;Final</th>
                        <th>Imagem</th>
                        <th class="text-end">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($paginas as $pg): ?>
                        <tr data-id="<?php echo $pg['id']; ?>">
                            <td><?php echo $pg['numero_pagina']; ?></td>
                            <td>
                                <input type="number"
                                       class="form-control form-control-sm numero-folha"
                                    value="<?php echo $pg['numero_folha']; ?>">
                            </td>
                            <td><?php echo $pg['eh_verso'] ? 'Verso' : 'Frente'; ?></td>
                            <!-- inputs inline -->
                            <td>
                                <input type="number"
                                       class="form-control form-control-sm termo-inicial"
                                       value="<?php echo $pg['termo_inicial']; ?>">
                            </td>
                            <td>
                                <input type="number"
                                       class="form-control form-control-sm termo-final"
                                       value="<?php echo $pg['termo_final']; ?>">
                            </td>
                            <td>
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary visualizar-img"
                                        data-img="<?= htmlspecialchars($pg['caminho']); ?>">
                                    <i data-feather="eye" style="width:14px;height:14px;"></i>
                                </button>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-primary salvar-linha">
                                    <i data-feather="save" style="width:14px;height:14px;"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <small class="text-muted">Alterar apenas as linhas necessárias; as seguintes serão recalculadas automaticamente.</small>
        </div>
    </div>
</div>


<!-- Modal de visualização -->
<div class="modal fade" id="modalImagem" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0">
      <div class="modal-body p-0">
        <img id="imgPreview" class="w-100 h-auto d-block"/>
      </div>
    </div>
  </div>
</div>


<script>
document.addEventListener('DOMContentLoaded',()=>{feather.replace();});

const termosPorPagina   = <?php echo (int)$livro['termos_por_pagina']; ?>;
const contagemFrenteVerso = <?php echo (int)$livro['contagem_frente_verso']; ?>;
const livroId = <?php echo $livro_id; ?>;

// botão Salvar linha
document.querySelectorAll('.salvar-linha').forEach(btn=>{
    btn.addEventListener('click',async e=>{
        const tr = e.target.closest('tr');
        const paginaId   = tr.dataset.id;
        const termoIni   = tr.querySelector('.termo-inicial').value;
        const termoFim   = tr.querySelector('.termo-final').value;
        const numFolha   = tr.querySelector('.numero-folha').value;

        if(!termoIni || !termoFim){Swal.fire('Erro','Preencha ambos os termos.','error');return;}

        try{
            const resp = await fetch('atualizar_folha.php',{
                method:'POST',
                headers:{'X-Requested-With':'fetch'},
                body:new URLSearchParams({
                    pagina_id:paginaId,
                    livro_id:livroId,
                    termo_inicial:termoIni,
                    termo_final:termoFim,
                    numero_folha:numFolha
                })
            });
            const data = await resp.json();
            if(data.success){
                Swal.fire('Sucesso','Sequência atualizada!','success');
                atualizarTabela(data.paginas);      // lista recalculada devolvida
            }else{
                Swal.fire('Erro',data.message || 'Falha ao gravar.','error');
            }
        }catch(err){
            console.error(err);
            Swal.fire('Erro','Falha de comunicação.','error');
        }
    });
});

// substitui rapidamente valores exibidos sem recarregar
function atualizarTabela(paginas){
    paginas.forEach(p=>{
        const row=document.querySelector(`tr[data-id="${p.id}"]`);
        if(!row)return;
        row.querySelector('.termo-inicial').value  = p.termo_inicial;
        row.querySelector('.termo-final').value    = p.termo_final;
        if (row.querySelector('.numero-folha'))    // segurança
        row.querySelector('.numero-folha').value = p.numero_folha;
    });
}

/* ────────  C)  JS: abrir modal com a imagem ──────── */
document.querySelectorAll('.visualizar-img').forEach(btn=>{
    btn.addEventListener('click',()=>{
        const src = btn.dataset.img;
        const img = document.getElementById('imgPreview');
        img.src = src + '?t=' + Date.now();       // evita cache
        const modal = new bootstrap.Modal(document.getElementById('modalImagem'));
        modal.show();
    });
});

</script>
<?php include 'includes/footer.php'; ?>
