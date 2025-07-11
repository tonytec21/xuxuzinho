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
  <select name="categoria_id" class="form-select" required>
    <option value="">— Selecione —</option>
    <?php foreach($categorias as $c): ?>
      <option data-tipo="<?= $c['tipo_id'] ?>"
              value="<?= $c['id'] ?>">
        <?= htmlspecialchars($c['nome']) ?>
      </option>
    <?php endforeach; ?>
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
