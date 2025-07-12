<!-- partials/_form_bem.php  (com filtragem dinâmica) -->
<div class="mb-3">
  <label class="form-label">Tipo</label>
  <!-- classe .sel-tipo usada pelo JS para popular as categorias -->
  <select name="tipo_id"
          class="form-select sel-tipo"
          required>
    <option value="">— Selecione —</option>
    <?php foreach ($tipos as $t): ?>
      <option value="<?= $t['id'] ?>">
        <?= htmlspecialchars($t['nome']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>

<div class="mb-3">
  <label class="form-label">Categoria</label>
  <!-- começa apenas com o placeholder; JS preenche depois -->
  <select name="categoria_id"
          class="form-select sel-categoria"
          required>
    <option value="">— Selecione —</option>
  </select>
</div>

<div class="mb-3">
  <label class="form-label">Modelo</label>
  <input type="text" name="modelo" class="form-control" required>
</div>

<div class="mb-3">
  <label class="form-label">Configuração</label>
  <textarea name="configuracao" class="form-control" rows="2"></textarea>
</div>

<div class="mb-3">
  <label class="form-label">Quantidade</label>
  <input type="number" name="quantidade" class="form-control" min="1" value="1">
</div>

<div class="mb-3">
  <label class="form-label">Localização</label>
  <input type="text" name="localizacao" class="form-control">
</div>

<div class="mb-3">
  <label class="form-label">Data de Aquisição</label>
  <input type="date" name="data_aquisicao" class="form-control">
</div>

<div class="mb-3">
  <label class="form-label">Observações</label>
  <textarea name="observacoes" class="form-control" rows="2"></textarea>
</div>
