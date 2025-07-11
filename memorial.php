<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>OCR – Memorial Descritivo SIGEF</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body      { background:#f8f9fa; }
    .dropzone { border:2px dashed #0d6efd; border-radius:.5rem; padding:2.5rem; text-align:center; cursor:pointer; color:#6c757d; transition:.2s; }
    .dropzone.dragover { background:#e9f2ff; }
    textarea  { resize:vertical; }
  </style>
</head>
<body>
  <div class="container py-5">
    <h1 class="mb-4 text-center fw-bold">Extrair Memorial Descritivo (SIGEF)</h1>

    <!-- Área de upload -->
    <form id="uploadForm" class="mb-4" enctype="multipart/form-data">
      <input type="file" name="memorial" id="fileInput" accept="application/pdf" hidden>

      <div id="dropzone" class="dropzone">
        <p class="lead m-0">
          Arraste o PDF aqui ou <span class="text-primary text-decoration-underline">clique para selecionar</span>
        </p>
        <small class="d-block mt-2 text-muted">Apenas arquivos .pdf</small>
      </div>

      <div class="d-flex justify-content-center mt-3">
        <button id="submitBtn" type="submit" class="btn btn-primary" disabled>
          Processar arquivo
        </button>
      </div>
    </form>

    <!-- Resultado -->
    <div id="resultBox" class="d-none">
      <label for="resultado" class="form-label fw-semibold">Memorial descritivo (texto corrido):</label>
      <textarea id="resultado" class="form-control" rows="6" readonly></textarea>
    </div>
  </div>

  <!-- Bootstrap bundle (JS + Popper) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
  (() => {
    const dropzone   = document.getElementById('dropzone');
    const fileInput  = document.getElementById('fileInput');
    const submitBtn  = document.getElementById('submitBtn');
    const form       = document.getElementById('uploadForm');
    const resultBox  = document.getElementById('resultBox');
    const resultado  = document.getElementById('resultado');

    // ----- util -----
    const enableSubmit = () => submitBtn.disabled = false;
    const disableSubmit = () => submitBtn.disabled = true;

    // ----- eventos de drag & drop -----
    ['dragenter','dragover'].forEach(evt =>
      dropzone.addEventListener(evt, e => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.add('dragover');
      })
    );
    ['dragleave','drop'].forEach(evt =>
      dropzone.addEventListener(evt, e => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove('dragover');
      })
    );

    dropzone.addEventListener('drop', e => {
      if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        enableSubmit();
      }
    });

    // clique para selecionar
    dropzone.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', () => {
      if (fileInput.files.length) enableSubmit();
    });

    // ----- submit -----
    form.addEventListener('submit', async e => {
      e.preventDefault();
      if (!fileInput.files.length) return;

      disableSubmit();
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando…';

      const fd = new FormData(form);

      try {
        const resp = await fetch('memorial_ocr.php', { method:'POST', body:fd });
        if (!resp.ok) throw new Error(`Erro ${resp.status}`);
        const text = await resp.text();

        resultado.value = text.trim();
        resultBox.classList.remove('d-none');
      } catch (err) {
        alert('Falha ao processar o arquivo: ' + err.message);
      } finally {
        submitBtn.innerHTML = 'Processar arquivo';
        enableSubmit();
      }
    });
  })();
  </script>
</body>
</html>
