<?php  
require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  
require_once 'includes/functions.php';  

$titulo_pagina = "Gerenciamento de Livros";  

// Filtros  
$filtro_tipo = $_GET['tipo'] ?? 'todos';  
$filtro_numero = $_GET['numero_livro'] ?? '';  
$filtro_termo = $_GET['termo'] ?? '';  

// Processar cadastro de novo livro  
if (isset($_POST['cadastrar_livro'])) {  
    $tipo = $_POST['tipo'];  
    $numero = $_POST['numero'];  
    $qtd_folhas = $_POST['qtd_folhas'];  
    $contagem_frente_verso = isset($_POST['contagem_frente_verso']) ? 1 : 0;  
    $termo_inicial = $_POST['termo_inicial'];  
    $modo_termo = $_POST['modo_termo'];
    $termos_por_pagina = ($modo_termo === 'termos_por_pagina') ? intval($_POST['termos_por_pagina']) : null;
    $paginas_por_termo = ($modo_termo === 'paginas_por_termo') ? intval($_POST['paginas_por_termo']) : null;
    $notas = $_POST['notas'] ?? '';  

    try {  
        $stmt = $pdo->prepare("INSERT INTO livros 
            (tipo, numero, qtd_folhas, contagem_frente_verso, termo_inicial, termos_por_pagina, paginas_por_termo, notas, usuario_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");  

        $stmt->execute([$tipo, $numero, $qtd_folhas, $contagem_frente_verso, $termo_inicial, $termos_por_pagina, $paginas_por_termo, $notas, $_SESSION['user_id']]);  

        $_SESSION['mensagem'] = "Livro cadastrado com sucesso!";  
        $_SESSION['tipo_mensagem'] = "success";  

        header("Location: livros.php");  
        exit;  
    } catch (PDOException $e) {  
        $_SESSION['mensagem'] = "Erro ao cadastrar livro: " . $e->getMessage();  
        $_SESSION['tipo_mensagem'] = "danger";  
    }  
}  

// Consulta com filtros  
$sql = "SELECT l.*,   
        (SELECT COUNT(*) FROM anexos_livros WHERE livro_id = l.id) as total_anexos,  
        (SELECT COUNT(*) FROM paginas_livro p JOIN anexos_livros a ON p.anexo_id = a.id WHERE a.livro_id = l.id) as total_paginas  
        FROM livros l";  

$where = [];  
$params = [];  

if ($filtro_tipo !== 'todos') {  
    $where[] = 'l.tipo = :tipo';  
    $params[':tipo'] = $filtro_tipo;  
}  

if (!empty($filtro_numero)) {  
    $where[] = 'l.numero LIKE :numero';  
    $params[':numero'] = '%' . $filtro_numero . '%';  
}  

if (!empty($filtro_termo) && is_numeric($filtro_termo)) {  
    $where[] = '(CAST(:termo AS UNSIGNED) BETWEEN l.termo_inicial AND (
                    CASE 
                        WHEN l.termos_por_pagina IS NOT NULL THEN l.termo_inicial + (l.qtd_folhas * l.termos_por_pagina) - 1
                        WHEN l.paginas_por_termo IS NOT NULL THEN l.termo_inicial + FLOOR(l.qtd_folhas / l.paginas_por_termo) - 1
                        ELSE l.termo_inicial
                    END))';  
    $params[':termo'] = $filtro_termo;  
}  

if (!empty($where)) {  
    $sql .= ' WHERE ' . implode(' AND ', $where);  
}  

$sql .= " ORDER BY l.data_cadastro DESC";  

$livros = [];

if (!empty($_GET['tipo']) || !empty($_GET['numero_livro']) || !empty($_GET['termo'])) {
    $stmt = $pdo->prepare($sql);  
    foreach ($params as $key => $val) {  
        $stmt->bindValue($key, $val);  
    }  
    $stmt->execute();  
    $livros = $stmt->fetchAll(PDO::FETCH_ASSOC);
}  

// Verificar se estamos visualizando um livro específico  
$livro_atual = null;  
$anexos = [];  

if (isset($_GET['id']) && is_numeric($_GET['id'])) {  
    $livro_id = $_GET['id'];  

    $stmt = $pdo->prepare("SELECT * FROM livros WHERE id = ?");  
    $stmt->execute([$livro_id]);  
    $livro_atual = $stmt->fetch(PDO::FETCH_ASSOC);  

    if ($livro_atual) {  
        $stmt = $pdo->prepare("SELECT * FROM anexos_livros WHERE livro_id = ? ORDER BY data_upload DESC");  
        $stmt->execute([$livro_id]);  
        $anexos = $stmt->fetchAll(PDO::FETCH_ASSOC);  
    }  
}  

$livro_anterior = null;  
$livro_proximo = null;  

if (!empty($livro_atual) && is_array($livro_atual)) {  
    $livro_id_atual = $livro_atual['id'];  
    $livro_tipo = $livro_atual['tipo'];  
    $livro_numero = $livro_atual['numero'];  

    $stmt = $pdo->prepare("SELECT id FROM livros WHERE tipo = ? AND CAST(numero AS UNSIGNED) < CAST(? AS UNSIGNED) ORDER BY CAST(numero AS UNSIGNED) DESC LIMIT 1");  
    $stmt->execute([$livro_tipo, $livro_numero]);  
    $livro_anterior = $stmt->fetch(PDO::FETCH_ASSOC);  

    $stmt = $pdo->prepare("SELECT id FROM livros WHERE tipo = ? AND CAST(numero AS UNSIGNED) > CAST(? AS UNSIGNED) ORDER BY CAST(numero AS UNSIGNED) ASC LIMIT 1");  
    $stmt->execute([$livro_tipo, $livro_numero]);  
    $livro_proximo = $stmt->fetch(PDO::FETCH_ASSOC);  
}  

include 'includes/header.php';
  
?>

<div class="container-fluid py-4">  
    <div class="d-flex justify-content-between align-items-center mb-4">  
        <h1 class="h3 mb-0 text-gray-800">  
            <?php if ($livro_atual): ?>  
                Livro <?php echo htmlspecialchars($livro_atual['tipo'] . ' - ' . $livro_atual['numero']); ?>  
            <?php else: ?>  
                Gerenciamento de Livros  
            <?php endif; ?>  
        </h1>  
        
        <?php if (!$livro_atual): ?>  
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novoLivroModal">  
                <i data-feather="plus" class="me-1" style="width: 14px; height: 14px;"></i>  
                Cadastrar Novo Livro  
            </button>  
        <?php else: ?>  
            <div>  
                <a href="livros.php" class="btn btn-outline-secondary me-2">  
                    <i data-feather="list" class="me-1" style="width: 14px; height: 14px;"></i>  
                    Listar Todos  
                </a>  
                <a href="editar_livro.php?id=<?php echo $livro_atual['id']; ?>" class="btn btn-outline-primary">
                    <i data-feather="edit" class="me-1" style="width:14px;height:14px;"></i>
                    Editar Livro
                </a>
            </div>  
        <?php endif; ?>  
    </div>  

    <?php if (isset($_SESSION['mensagem'])): ?>  
        <div class="alert alert-<?php echo $_SESSION['tipo_mensagem']; ?> alert-dismissible fade show" role="alert">  
            <?php echo $_SESSION['mensagem']; ?>  
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>  
        </div>  
        <?php unset($_SESSION['mensagem']); unset($_SESSION['tipo_mensagem']); ?>  
    <?php endif; ?>  

    <?php if ($livro_atual): ?>  
        <!-- Detalhes do livro - Design Melhorado -->  
        <div class="row mb-4">  
            <div class="col-md-12">  
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">  
                    <div class="card-header bg-white py-3">  
                        <div class="row align-items-center">  
                            <!-- Título e ID -->  
                            <div class="col-md-6 mb-2 mb-md-0">  
                                <div class="d-flex align-items-center flex-wrap">  
                                    <h5 class="mb-0 text-primary d-flex align-items-center me-3">  
                                        <i data-feather="book" class="me-2 text-primary" style="width: 20px; height: 20px;"></i>  
                                        Detalhes do Livro  
                                    </h5>  
                                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 mt-2 mt-sm-0">  
                                        ID: <?php echo htmlspecialchars($livro_atual['id']); ?>  
                                    </span>  
                                </div>  
                            </div>  
                            
                            <!-- Botões de navegação -->  
                            <div class="col-md-6">  
                                <div class="d-flex justify-content-md-end justify-content-start flex-wrap gap-2 mt-2 mt-md-0">  
                                    <?php if ($livro_anterior): ?>  
                                        <a href="?id=<?php echo $livro_anterior['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm">  
                                            <i data-feather="chevron-left" class="me-1" style="width: 14px; height: 14px;"></i> Livro Anterior  
                                        </a>  
                                    <?php endif; ?>  

                                    <?php if ($livro_proximo): ?>  
                                        <a href="?id=<?php echo $livro_proximo['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm">  
                                            Próximo Livro <i data-feather="chevron-right" class="ms-1" style="width: 14px; height: 14px;"></i>  
                                        </a>  
                                    <?php endif; ?>  
                                </div>  
                            </div>  
                        </div>  
                    </div>  
                    <div class="card-body p-4">  
                        <div class="row g-4">  
                            <div class="col-md-3 mb-2">  
                                <div class="detail-card p-3 rounded-3 bg-light h-100">  
                                    <p class="mb-1 text-muted small text-uppercase fw-semibold">Tipo do Livro</p>  
                                    <h5 class="text-capitalize fw-bold mb-0">  
                                        <i data-feather="bookmark" class="me-1 text-primary" style="width: 16px; height: 16px;"></i>  
                                        <?php echo htmlspecialchars($livro_atual['tipo']); ?>  
                                    </h5>  
                                </div>  
                            </div>  
                            <div class="col-md-3 mb-2">  
                                <div class="detail-card p-3 rounded-3 bg-light h-100">  
                                    <p class="mb-1 text-muted small text-uppercase fw-semibold">Número do Livro</p>  
                                    <h5 class="fw-bold mb-0">  
                                        <i data-feather="hash" class="me-1 text-primary" style="width: 16px; height: 16px;"></i>  
                                        <?php echo htmlspecialchars($livro_atual['numero']); ?>  
                                    </h5>  
                                </div>  
                            </div>  
                            <div class="col-md-3 mb-2">  
                                <div class="detail-card p-3 rounded-3 bg-light h-100">  
                                    <p class="mb-1 text-muted small text-uppercase fw-semibold">Quantidade de Folhas</p>  
                                    <h5 class="fw-bold mb-0">  
                                        <i data-feather="layers" class="me-1 text-primary" style="width: 16px; height: 16px;"></i>  
                                        <?php echo htmlspecialchars($livro_atual['qtd_folhas']); ?>  
                                    </h5>  
                                </div>  
                            </div>  
                            <div class="col-md-3 mb-2">  
                                <div class="detail-card p-3 rounded-3 bg-light h-100">  
                                    <p class="mb-1 text-muted small text-uppercase fw-semibold">Contagem</p>  
                                    <h5 class="fw-bold mb-0">  
                                        <i data-feather="repeat" class="me-1 text-primary" style="width: 16px; height: 16px;"></i>  
                                        <?php echo $livro_atual['contagem_frente_verso'] ? 'Frente e Verso' : 'Somente Frente'; ?>  
                                    </h5>  
                                </div>  
                            </div>  
                            <div class="col-md-3 mb-2">  
                                <div class="detail-card p-3 rounded-3 bg-light h-100">  
                                    <p class="mb-1 text-muted small text-uppercase fw-semibold">Termo Inicial</p>  
                                    <h5 class="fw-bold mb-0">  
                                        <i data-feather="play" class="me-1 text-primary" style="width: 16px; height: 16px;"></i>  
                                        <?php echo htmlspecialchars($livro_atual['termo_inicial']); ?>  
                                    </h5>  
                                </div>  
                            </div>  
                            <div class="col-md-3 mb-2">  
                                <div class="detail-card p-3 rounded-3 bg-light h-100">  
                                    <p class="mb-1 text-muted small text-uppercase fw-semibold">Termos por Página</p>  
                                    <h5 class="fw-bold mb-0">  
                                        <i data-feather="list" class="me-1 text-primary" style="width: 16px; height: 16px;"></i>  
                                        <?php echo htmlspecialchars($livro_atual['termos_por_pagina']); ?>  
                                    </h5>  
                                </div>  
                            </div>  
                            <div class="col-md-3 mb-2">  
                                <div class="detail-card p-3 rounded-3 bg-light h-100">  
                                    <p class="mb-1 text-muted small text-uppercase fw-semibold">Páginas Anexadas</p>  
                                    <h5 class="fw-bold mb-0" id="totalPaginas">  
                                        <i data-feather="file-text" class="me-1 text-primary" style="width: 16px; height: 16px;"></i>  
                                        <?php   
                                        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM paginas_livro p JOIN anexos_livros a ON p.anexo_id = a.id WHERE a.livro_id = ?");  
                                        $stmt->execute([$livro_atual['id']]);  
                                        $total_paginas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];  
                                        echo $total_paginas;  
                                        ?>  
                                    </h5>  
                                </div>  
                            </div>  
                            <div class="col-md-3 mb-2">  
                                <div class="detail-card p-3 rounded-3 bg-warning bg-opacity-10 h-100">  
                                    <p class="mb-1 text-muted small text-uppercase fw-semibold">Status</p>  
                                    <h5 class="fw-bold mb-0">  
                                        <i data-feather="check-circle" class="me-1 text-warning" style="width: 16px; height: 16px;"></i>  
                                        Digitalizado  
                                    </h5>  
                                </div>  
                            </div>  
                            <?php if (!empty($livro_atual['notas'])): ?>  
                            <div class="col-md-12 mt-1">  
                                <div class="detail-card p-3 rounded-3 bg-light">  
                                    <p class="mb-2 text-muted small text-uppercase fw-semibold">  
                                        <i data-feather="message-square" class="me-1 text-primary" style="width: 14px; height: 14px;"></i>  
                                        Notas  
                                    </p>  
                                    <div class="p-3 bg-white rounded-3 border shadow-sm">  
                                        <?php echo nl2br(htmlspecialchars($livro_atual['notas'])); ?>  
                                    </div>  
                                </div>  
                            </div>  
                            <?php endif; ?>  
                        </div>  
                    </div>  
                </div>  
            </div>  
        </div>  

        <!-- Área de Visualização de Páginas - Design Melhorado -->  
        <div class="row mb-4">  
            <div class="col-md-12">  
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">  
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">  
                        <h5 class="mb-0 text-primary">  
                            <i data-feather="book-open" class="me-2 text-primary" style="width: 20px; height: 20px;"></i>  
                            Visualização do Livro  
                        </h5>  
                    </div>  
                    <div class="card-body p-0">  
                        <!-- Navegação do Livro - Design Melhorado -->  
                        <div class="p-4 bg-light">  
                            <div class="row g-4">  
                                <div class="col-md-6">  
                                    <div class="card h-100 border-0 shadow-sm rounded-3 hover-lift">  
                                        <div class="card-body p-4">  
                                            <label for="inputTermoBusca" class="form-label fw-semibold text-primary mb-3">  
                                                <i data-feather="search" class="me-2 text-primary" style="width: 16px; height: 16px;"></i>  
                                                Ir para Termo  
                                            </label>  
                                            <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">  
                                                <input type="number" class="form-control border-0" id="inputTermoBusca"   
                                                    placeholder="Número do termo" min="1">  
                                                <button class="btn btn-primary px-4" id="btnIrParaTermo">  
                                                    <i data-feather="arrow-right" class="me-1" style="width: 16px; height: 16px;"></i>  
                                                    Ir  
                                                </button>  
                                            </div>  
                                        </div>  
                                    </div>  
                                </div>  
                                <div class="col-md-6">  
                                    <div class="card h-100 border-0 shadow-sm rounded-3 hover-lift">  
                                        <div class="card-body p-4">  
                                            <label for="inputFolhaBusca" class="form-label fw-semibold text-primary mb-3">  
                                                <i data-feather="file" class="me-2 text-primary" style="width: 16px; height: 16px;"></i>  
                                                Ir para Folha  
                                            </label>  
                                            <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">  
                                                <input type="number" class="form-control border-0" id="inputFolhaBusca"   
                                                    placeholder="Número da folha" min="1">  
                                                <button class="btn btn-primary px-4" id="btnIrParaFolha">  
                                                    <i data-feather="arrow-right" class="me-1" style="width: 16px; height: 16px;"></i>  
                                                    Ir  
                                                </button>  
                                            </div>  
                                        </div>  
                                    </div>  
                                </div>  
                            </div>  
                        </div> 

                        <!-- Seção do Visualizador de Página - Design Melhorado -->  
                        <div class="p-4 pt-0">  
                            <div class="card border-0 rounded-3 shadow-sm mb-0 overflow-hidden">  
                                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">  
                                    <h5 class="mb-0 text-primary">  
                                        <i data-feather="image" class="me-2 text-primary" style="width: 18px; height: 18px;"></i>  
                                        Visualizador de Página  
                                    </h5>  
                                    <div class="btn-group">  
                                        <button class="btn btn-sm btn-outline-secondary" id="btn-fullscreen">  
                                            <i data-feather="maximize" style="width: 16px; height: 16px;"></i>  
                                        </button>  
                                    </div>  
                                </div>  
                                <div class="card-body p-2 bg-light">  
                                    <div class="d-flex justify-content-between align-items-center mb-3 px-3 py-2">  
                                        <button class="btn btn-primary px-4 py-2 shadow-sm rounded-pill" id="btn-pagina-anterior" disabled>  
                                            <i data-feather="chevron-left" class="me-1" style="width: 18px; height: 18px;"></i> Anterior  
                                        </button>  
                                        <div class="text-center navigation-display">  
                                            <div class="navigation-label">  
                                                <span id="folha-atual" class="badge bg-primary bg-opacity-10 text-primary px-4 py-2 rounded-pill fw-bold fs-6">  
                                                    Folha não selecionada  
                                                </span>  
                                            </div>  
                                            <div class="navigation-label mt-2">  
                                                <span id="termo-atual" class="badge bg-primary bg-opacity-10 text-primary px-4 py-2 rounded-pill fw-bold fs-6">  
                                                    Termo não selecionado  
                                                </span>  
                                            </div>  
                                        </div>  
                                        <button class="btn btn-primary px-4 py-2 shadow-sm rounded-pill" id="btn-proxima-pagina" disabled>  
                                            Próxima <i data-feather="chevron-right" class="ms-1" style="width: 18px; height: 18px;"></i>  
                                        </button>  
                                    </div>  
                                </div>   
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> 
          
        <!-- Visualizador com imagem maior - Design Melhorado -->  
        <!-- Informações da página atual -->  
        <div class="card border-0 rounded-3 shadow-sm mt-3">  
            <div class="card-body p-3">  
                
            
                <div id="visualizador-pagina" class="text-center position-relative rounded-3 bg-white p-1 shadow-inner"   
                    style="min-height: 800px; max-height: 90vh; overflow: auto;">  
                    
                    <div class="placeholder-content d-flex justify-content-center align-items-center h-100 w-100"   
                        style="min-height: 800px;">  
                        <div class="text-center p-5">  
                            <div class="mb-4">  
                                <i data-feather="book-open" style="width: 100px; height: 100px; color: #e9ecef;"></i>  
                            </div>  
                            <h5 class="mt-4 text-muted fw-normal">Nenhuma página selecionada</h5>  
                            <p class="text-muted mb-4">Selecione uma página do livro para visualizar o conteúdo</p>  
                            <button class="btn btn-primary px-4 py-2 shadow-sm rounded-pill" id="btn-selecionar-pagina">  
                                <i data-feather="file-text" class="me-2" style="width: 16px; height: 16px;"></i>  
                                Selecionar Página  
                            </button>  
                        </div>  
                    </div>  
                    
                    <!-- Imagem maior com tamanho ajustado -->  
                    <img id="imagem-pagina" class="d-none rounded-3 shadow-lg mx-auto"   
                        style="max-width: 95%; min-height: 780px; max-height: 85vh; object-fit: contain;"   
                        alt="Página do livro">  
                        
                    <!-- Overlay de carregamento -->  
                    <div id="loading-overlay" class="position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 d-none justify-content-center align-items-center rounded-3">  
                        <div class="text-center">  
                            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">  
                                <span class="visually-hidden">Carregando...</span>  
                            </div>  
                            <p class="fw-semibold text-primary">Carregando imagem...</p>  
                        </div>  
                    </div>  
                </div> 


            </div>  
        </div>

        <!-- Área de Upload de Anexos - Design Melhorado -->  
        <div class="row mb-4">  
            <div class="col-md-12">  
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">  
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">  
                        <h5 class="mb-0 text-primary">  
                            <i data-feather="upload-cloud" class="me-2 text-primary" style="width: 20px; height: 20px;"></i>  
                            Anexar Livro Digitalizado  
                        </h5>  
                        <button id="toggleUploadArea" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm">  
                            <i data-feather="chevron-down" class="me-1"></i> Exibir Área de Upload  
                        </button>  
                    </div>  
                </div>  

                <div id="uploadAreaContainer" style="display: none;">  
                    <div class="card border-0 shadow-sm rounded-3 mt-2">  
                        <div class="card-body p-4">  
                            <form id="uploadForm" action="upload_livro.php" method="post" enctype="multipart/form-data">  
                                <input type="hidden" name="livro_id" value="<?php echo $livro_atual['id']; ?>">  

                                <div id="dropzoneUpload" class="dropzone-area text-center p-5 mb-4 border-dashed rounded-3 bg-light">  
                                    <div class="dropzone-message">  
                                        <div class="mb-3">  
                                            <i data-feather="upload-cloud" style="width: 64px; height: 64px; color: #6c757d;"></i>  
                                        </div>  
                                        <h5 class="mt-3 fw-semibold">Arraste arquivos para aqui ou</h5>  
                                        <button type="button" class="btn btn-primary browse-btn mt-3 px-4 shadow-sm">  
                                            <i data-feather="folder" class="me-2" style="width: 16px; height: 16px;"></i>  
                                            Selecionar Arquivos  
                                        </button>  
                                        <div class="mt-3 text-muted small">  
                                            <span class="badge bg-light text-secondary me-1">PDF</span>  
                                            <span class="badge bg-light text-secondary me-1">JPG</span>  
                                            <span class="badge bg-light text-secondary me-1">PNG</span>  
                                            <span class="d-block mt-1">Tamanho máximo: 10MB por arquivo</span>  
                                        </div>  
                                    </div>  
                                </div>  

                                <div id="preview-container" class="d-none mb-4">  
                                    <div class="d-flex justify-content-between align-items-center mb-3">  
                                        <h6 class="mb-0 fw-semibold text-primary">  
                                            <i data-feather="file" class="me-2" style="width: 18px; height: 18px;"></i>  
                                            Arquivos Selecionados  
                                        </h6>  
                                        <span id="fileCount" class="badge bg-primary rounded-pill">0</span>  
                                    </div>  
                                    <div id="file-preview-list" class="border rounded-3 p-3 bg-white"></div>  
                                </div>  

                                <div id="progressContainer" class="d-none mb-4">  
                                    <div class="d-flex justify-content-between align-items-center mb-2">  
                                        <h6 id="uploadStatus" class="mb-0 fw-semibold">Enviando arquivos...</h6>  
                                        <span class="badge bg-primary rounded-pill" id="progressPercentage">0%</span>  
                                    </div>  
                                    <div class="progress" style="height: 10px; border-radius: 5px;">  
                                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated"  
                                            role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"  
                                            style="width: 0%"></div>  
                                    </div>  
                                </div>  

                                <div class="d-flex justify-content-end">  
                                    <button id="submitUpload" type="submit" class="btn btn-primary px-4 shadow-sm" disabled>  
                                        <i data-feather="upload" class="me-1" style="width: 16px; height: 16px;"></i>  
                                        Enviar Anexos  
                                    </button>  
                                </div>  
                            </form>  
                        </div>  
                    </div>  
                </div>  
            </div>  
        </div>  

        <!-- Lista de Anexos - Design Melhorado -->  
        <div class="row mb-4">  
            <div class="col-md-12">  
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">  
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">  
                        <h5 class="mb-0 text-primary">  
                            <i data-feather="paperclip" class="me-2 text-primary" style="width: 20px; height: 20px;"></i>  
                            Anexos do Livro  
                        </h5>  
                        <button class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm" id="toggleAnexosBtn">  
                            <i data-feather="chevron-down" class="me-1"></i> Exibir Lista de Anexos  
                        </button>  
                    </div>  
                </div>  

                <!-- Container que será expandido -->  
                <div id="listaAnexosContainer" style="display: none;">  
                    <div class="card border-0 shadow-sm rounded-3 mt-2">  
                        <div class="card-body p-0"> <!-- Padding removido para maximizar espaço da tabela -->  
                            <?php if (empty($anexos)): ?>  
                                <div class="text-center py-5">  
                                    <div class="empty-state mb-3">  
                                        <i data-feather="file" style="width: 64px; height: 64px; color: #e9ecef;"></i>  
                                    </div>   
                                    <h6 class="fw-semibold text-muted">Nenhum anexo cadastrado para este livro</h6>  
                                    <p class="text-muted small">Os arquivos anexados aparecerão aqui após o upload</p>  
                                    <button class="btn btn-sm btn-outline-primary mt-2" id="showUploadAreaBtn">  
                                        <i data-feather="upload" class="me-1"></i> Fazer Upload Agora  
                                    </button>  
                                </div>  
                            <?php else: ?>  
                                <div class="table-responsive">  
                                    <table class="table table-hover mb-0 border-top">  
                                        <thead class="bg-light">  
                                            <tr>  
                                                <th class="py-3 px-4">Nome do Arquivo</th>  
                                                <th class="py-3">Tamanho</th>  
                                                <th class="py-3">Páginas Extraídas</th>  
                                                <th class="py-3 text-end pe-4">Ações</th>  
                                            </tr>  
                                        </thead>  
                                        <tbody>  
                                            <?php foreach ($anexos as $anexo): ?>  
                                                <tr>  
                                                    <td class="py-3 px-4">  
                                                        <div class="d-flex align-items-center">  
                                                            <?php   
                                                            $ext = pathinfo($anexo['caminho'], PATHINFO_EXTENSION);  
                                                            $icon = 'file';  
                                                            $iconClass = 'text-secondary';  
                                                            
                                                            if ($ext == 'pdf') {  
                                                                $icon = 'file-text';  
                                                                $iconClass = 'text-danger';  
                                                            }  
                                                            elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) {  
                                                                $icon = 'image';  
                                                                $iconClass = 'text-primary';  
                                                            }  
                                                            ?>  
                                                            <div class="rounded-circle bg-light p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">  
                                                                <i data-feather="<?php echo $icon; ?>" class="<?php echo $iconClass; ?>" style="width: 18px; height: 18px;"></i>  
                                                            </div>  
                                                            <div>  
                                                                <h6 class="mb-0 text-truncate" style="max-width: 300px;"><?php echo htmlspecialchars($anexo['nome_arquivo']); ?></h6>  
                                                                <span class="badge bg-light text-secondary"><?php echo strtoupper($ext); ?></span>  
                                                            </div>  
                                                        </div>  
                                                    </td>  
                                                    <td class="py-3">  
                                                        <span class="badge bg-light text-secondary">  
                                                            <?php  
                                                            $size = $anexo['tamanho'];  
                                                            if ($size < 1024) echo $size . ' bytes';  
                                                            elseif ($size < 1024 * 1024) echo round($size / 1024, 2) . ' KB';  
                                                            else echo round($size / (1024 * 1024), 2) . ' MB';  
                                                            ?>  
                                                        </span>  
                                                    </td>  
                                                    <td class="py-3">  
                                                        <span class="badge bg-primary bg-opacity-10 text-primary">  
                                                            <?php  
                                                            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM paginas_livro WHERE anexo_id = ?");  
                                                            $stmt->execute([$anexo['id']]);  
                                                            $paginas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];  
                                                            echo $paginas . ' páginas';  
                                                            ?>  
                                                        </span>  
                                                    </td>  
                                                    <td class="text-end py-3 pe-4">  
                                                        <div class="btn-group">  
                                                            <a href="<?php echo $anexo['caminho']; ?>" class="btn btn-sm btn-outline-primary" target="_blank" title="Visualizar">  
                                                                <i data-feather="eye" style="width: 16px; height: 16px;"></i>  
                                                            </a>  
                                                            <a href="<?php echo $anexo['caminho']; ?>" class="btn btn-sm btn-outline-success" download title="Baixar">  
                                                                <i data-feather="download" style="width: 16px; height: 16px;"></i>  
                                                            </a>  
                                                        </div>  
                                                    </td>  
                                                </tr>  
                                            <?php endforeach; ?>  
                                        </tbody>  
                                    </table>  
                                </div>  
                            <?php endif; ?>  
                        </div>  
                    </div>  
                </div>  
            </div>  
        </div>  

    <?php else: ?>  
        <!-- Lista de livros -->  
        <form method="get" class="card shadow-sm mb-4">  
            <div class="card-body">  
                <div class="row g-3">  
                    <!-- Cabeçalho do formulário -->  
                    <div class="col-12">  
                        <h5 class="mb-0 d-flex align-items-center text-primary">  
                            <i data-feather="filter" class="me-2" style="width: 18px; height: 18px;"></i>  
                            Filtrar Livros  
                        </h5>  
                        <hr class="my-2">  
                    </div>  
                    
                    <!-- Campos de filtro -->  
                    <div class="col-md-4 col-sm-6">  
                        <label for="filtro_tipo" class="form-label">Tipo do Livro</label>  
                        <div class="input-group">  
                            <span class="input-group-text bg-light">  
                                <i data-feather="book" style="width: 16px; height: 16px;"></i>  
                            </span>  
                            <select name="tipo" id="filtro_tipo" class="form-select">  
                                <option value="todos">Todos os tipos</option>  
                                <option value="nascimento" <?= $filtro_tipo === 'nascimento' ? 'selected' : '' ?>>Nascimento</option>  
                                <option value="casamento" <?= $filtro_tipo === 'casamento' ? 'selected' : '' ?>>Casamento</option>  
                                <option value="óbito" <?= $filtro_tipo === 'óbito' ? 'selected' : '' ?>>Óbito</option>  
                                <!-- adicione mais tipos se necessário -->  
                            </select>  
                        </div>  
                    </div>  

                    <div class="col-md-4 col-sm-6">  
                        <label for="numero_livro" class="form-label">Número do Livro</label>  
                        <div class="input-group">  
                            <span class="input-group-text bg-light">  
                                <i data-feather="hash" style="width: 16px; height: 16px;"></i>  
                            </span>  
                            <input type="text" name="numero_livro" id="numero_livro" class="form-control"   
                                placeholder="Ex: 35" value="<?= htmlspecialchars($_GET['numero_livro'] ?? '') ?>">  
                        </div>  
                    </div>  

                    <div class="col-md-4 col-sm-6">  
                        <label for="termo" class="form-label">Número do Termo</label>  
                        <div class="input-group">  
                            <span class="input-group-text bg-light">  
                                <i data-feather="file-text" style="width: 16px; height: 16px;"></i>  
                            </span>  
                            <input type="number" name="termo" id="termo" class="form-control"   
                                placeholder="Ex: 12345" value="<?= htmlspecialchars($_GET['termo'] ?? '') ?>">  
                        </div>  
                    </div>  

                    <!-- Botões de ação -->  
                    <div class="col-12 mt-4 d-flex gap-2 justify-content-end">  
                        <?php if (!empty($_GET['tipo']) || !empty($_GET['numero_livro']) || !empty($_GET['termo'])): ?>  
                        <a href="?" class="btn btn-outline-secondary">  
                            <i data-feather="x" class="me-1" style="width: 16px; height: 16px;"></i> Limpar Filtros  
                        </a>  
                        <?php endif; ?>  
                        
                        <button type="submit" class="btn btn-primary">  
                            <i data-feather="search" class="me-1" style="width: 16px; height: 16px;"></i> Aplicar Filtros  
                        </button>  
                    </div>  
                </div>  
            </div>  
        </form>

        <div class="row mb-4">  
            <div class="col-md-12">  
                <div class="card border-0 shadow-sm">  
                    <div class="card-body">  
                        <?php if (!empty($_GET['tipo']) || !empty($_GET['numero_livro']) || !empty($_GET['termo'])): ?>
                            <?php if (empty($livros)): ?>  
                                <div class="text-center py-5">  
                                    <i data-feather="book" style="width: 48px; height: 48px; color: #ccc;"></i>  
                                    <p class="mt-3 text-muted">Nenhum livro encontrado com os filtros aplicados</p>  
                                </div>  
                            <?php else: ?>   
                            <div class="table-responsive">  
                                <table  id="tabelaLivros" class="table table-striped table-bordered dt-responsive nowrap" style="width:100%">  
                                    <thead>  
                                        <tr>  
                                            <th>Tipo</th>  
                                            <th>Número</th>  
                                            <th>Folhas</th>  
                                            <th>Termos</th>  
                                            <th>Notas</th>  
                                            <th>Anexos</th>  
                                            <th>Páginas</th>  
                                            <th class="text-end">Ações</th>  
                                        </tr>  
                                    </thead>  
                                    <tbody>  
                                        <?php foreach ($livros as $livro): ?>  
                                            <tr>  
                                                <td>  
                                                    <span class="badge bg-<?php   
                                                        switch($livro['tipo']) {  
                                                            case 'nascimento': echo 'success'; break;  
                                                            case 'casamento': echo 'info'; break;  
                                                            case 'obito': echo 'warning'; break;  
                                                            default: echo 'secondary';  
                                                        }  
                                                    ?> text-capitalize">  
                                                        <?php echo $livro['tipo']; ?>  
                                                    </span>  
                                                </td>  
                                                <td><?php echo htmlspecialchars($livro['numero']); ?></td>  
                                                <td><?php echo $livro['qtd_folhas']; ?> <?php echo $livro['contagem_frente_verso'] ? '(F/V)' : '(Frente)'; ?></td>  
                                                <td>  
                                                    <?php   
                                                    // Calcular termo final com base no termo inicial, qtd folhas e termos por página  
                                                    $paginas_totais = $livro['qtd_folhas'];  
                                                    if ($livro['contagem_frente_verso']) {  
                                                        $paginas_totais *= 2;  
                                                    }  
                                                    $paginas_totais = $livro['qtd_folhas'];
                                                    if ($livro['contagem_frente_verso']) {
                                                        $paginas_totais *= 2;
                                                    }

                                                    if (!is_null($livro['termos_por_pagina'])) {
                                                        $termos_totais = $paginas_totais * $livro['termos_por_pagina'];
                                                    } elseif (!is_null($livro['paginas_por_termo']) && $livro['paginas_por_termo'] > 0) {
                                                        $termos_totais = floor($paginas_totais / $livro['paginas_por_termo']);
                                                    } else {
                                                        $termos_totais = 0;
                                                    }

                                                    $termo_final = $livro['termo_inicial'] + $termos_totais - 1;
                                                    echo $livro['termo_inicial'] . ' - ' . $termo_final;  
                                                    ?>  
                                                </td> 
                                                <td><?php echo htmlspecialchars($livro['notas']); ?></td>  
                                                <td>  
                                                    <span class="badge bg-light text-dark">  
                                                        <?php echo $livro['total_anexos']; ?> anexos  
                                                    </span>  
                                                </td>  
                                                <td>  
                                                    <span class="badge bg-light text-dark">  
                                                        <?php echo $livro['total_paginas']; ?> páginas  
                                                    </span>  
                                                </td>  
                                                <td class="text-end">  
                                                    <div class="btn-group">  
                                                    <a href="livros.php?id=<?php echo $livro['id']; ?>" class="btn btn-sm btn-outline-primary">  
                                                            <i data-feather="eye" style="width: 16px; height: 16px;"></i>  
                                                        </a>  
                                                    </div>  
                                                </td>  
                                            </tr>  
                                        <?php endforeach; ?>  
                                    </tbody>  
                                </table>  
                            </div>  
                        <?php endif; ?> 
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i data-feather="search" style="width: 48px; height: 48px; color: #ccc;"></i>  
                                <p class="mt-3 text-muted">Use os filtros acima para buscar livros cadastrados</p>  
                            </div>
                        <?php endif; ?> 
                    </div>  
                </div>  
            </div>  
        </div>  
    <?php endif; ?>  
</div>  

<!-- Modal Novo Livro -->  
<div class="modal fade" id="novoLivroModal" tabindex="-1" aria-labelledby="novoLivroModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-lg">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="novoLivroModalLabel">Cadastrar Novo Livro</h5>  
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>  
            </div>  
            <form method="post" action="livros.php">  
                <div class="modal-body">  
                    <div class="row">  
                        <div class="col-md-6 mb-3">  
                            <label for="tipo" class="form-label">Tipo do Livro</label>  
                            <select class="form-select" id="tipo" name="tipo" required>  
                                <option value="" selected disabled>Selecione o tipo</option>  
                                <option value="nascimento">Nascimento</option>  
                                <option value="casamento">Casamento</option>  
                                <option value="obito">Óbito</option>  
                                <option value="outros">Outros</option>  
                            </select>  
                        </div>  
                        <div class="col-md-6 mb-3">  
                            <label for="numero" class="form-label">Número do Livro</label>  
                            <input type="text" class="form-control" id="numero" name="numero" required>  
                        </div>  
                        <div class="col-md-6 mb-3">  
                            <label for="qtd_folhas" class="form-label">Quantidade de Folhas</label>  
                            <input type="number" class="form-control" id="qtd_folhas" name="qtd_folhas" min="1" required>  
                        </div>  
                        <div class="col-md-6 mb-3">  
                            <label for="contagem_frente_verso" class="form-label d-block">Contagem de Folhas</label>  
                            <div class="form-check form-check-inline mt-2">  
                                <input class="form-check-input" type="checkbox" id="contagem_frente_verso" name="contagem_frente_verso" value="1" checked>  
                                <label class="form-check-label" for="contagem_frente_verso">Contém frente e verso</label>  
                            </div>  
                        </div>  
                        <div class="col-md-6 mb-3">  
                            <label for="termo_inicial" class="form-label">Termo Inicial</label>  
                            <input type="number" class="form-control" id="termo_inicial" name="termo_inicial" min="1" required>  
                        </div>  
                        <div class="row mb-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label">Modo de contagem dos termos</label>
                                <select class="form-select" id="modo_termo" name="modo_termo" required>
                                    <option value="termos_por_pagina" selected>Termos por página</option>
                                    <option value="paginas_por_termo">Páginas por termo</option>
                                </select>
                            </div>

                            <div class="col-md-6" id="campo_termos_por_pagina">
                                <label for="termos_por_pagina" class="form-label">Qtd. de termos por página</label>
                                <input type="number" class="form-control" id="termos_por_pagina" name="termos_por_pagina" min="1" value="1">
                            </div>

                            <div class="col-md-6 d-none" id="campo_paginas_por_termo">
                                <label for="paginas_por_termo" class="form-label">Qtd. de páginas por termo</label>
                                <input type="number" class="form-control" id="paginas_por_termo" name="paginas_por_termo" min="1" value="2">
                            </div>
                        </div>

                        <div class="col-md-12 mb-3">  
                            <label for="notas" class="form-label">Notas (opcional)</label>  
                            <textarea class="form-control" id="notas" name="notas" rows="3"></textarea>  
                        </div>  
                    </div>  
                </div>  
                <div class="modal-footer">  
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>  
                    <button type="submit" name="cadastrar_livro" class="btn btn-primary">Cadastrar</button>  
                </div>  
            </form>  
        </div>  
    </div>  
</div>  

<!-- Modal Editar Livro -->  
<?php if ($livro_atual): ?>  
<div class="modal fade" id="editarLivroModal" tabindex="-1" aria-labelledby="editarLivroModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-lg">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="editarLivroModalLabel">Editar Livro</h5>  
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>  
            </div>  
            <form method="post" action="editar_livro.php">  
                <input type="hidden" name="livro_id" value="<?php echo $livro_atual['id']; ?>">  
                <div class="modal-body">  
                    <div class="row">  
                        <div class="col-md-6 mb-3">  
                            <label for="edit_tipo" class="form-label">Tipo do Livro</label>  
                            <select class="form-select" id="edit_tipo" name="tipo" required>  
                                <option value="nascimento" <?php echo $livro_atual['tipo'] == 'nascimento' ? 'selected' : ''; ?>>Nascimento</option>  
                                <option value="casamento" <?php echo $livro_atual['tipo'] == 'casamento' ? 'selected' : ''; ?>>Casamento</option>  
                                <option value="obito" <?php echo $livro_atual['tipo'] == 'obito' ? 'selected' : ''; ?>>Óbito</option>  
                                <option value="outros" <?php echo $livro_atual['tipo'] == 'outros' ? 'selected' : ''; ?>>Outros</option>  
                            </select>  
                        </div>  
                        <div class="col-md-6 mb-3">  
                            <label for="edit_numero" class="form-label">Número do Livro</label>  
                            <input type="text" class="form-control" id="edit_numero" name="numero" value="<?php echo htmlspecialchars($livro_atual['numero']); ?>" required>  
                        </div>  
                        <div class="col-md-6 mb-3">  
                            <label for="edit_qtd_folhas" class="form-label">Quantidade de Folhas</label>  
                            <input type="number" class="form-control" id="edit_qtd_folhas" name="qtd_folhas" value="<?php echo $livro_atual['qtd_folhas']; ?>" min="1" required>  
                        </div>  
                        <div class="col-md-6 mb-3">  
                            <label for="edit_contagem_frente_verso" class="form-label d-block">Contagem de Folhas</label>  
                            <div class="form-check form-check-inline mt-2">  
                                <input class="form-check-input" type="checkbox" id="edit_contagem_frente_verso" name="contagem_frente_verso" value="1" <?php echo $livro_atual['contagem_frente_verso'] ? 'checked' : ''; ?>>  
                                <label class="form-check-label" for="edit_contagem_frente_verso">Contém frente e verso</label>  
                            </div>  
                        </div>  
                        <div class="col-md-6 mb-3">  
                            <label for="edit_termo_inicial" class="form-label">Termo Inicial</label>  
                            <input type="number" class="form-control" id="edit_termo_inicial" name="termo_inicial" value="<?php echo $livro_atual['termo_inicial']; ?>" min="1" required>  
                        </div>  
                        <div class="col-md-6 mb-3">  
                            <label for="edit_termos_por_pagina" class="form-label">Termos por Página</label>  
                            <input type="number" class="form-control" id="edit_termos_por_pagina" name="termos_por_pagina" value="<?php echo $livro_atual['termos_por_pagina']; ?>" min="1" required>  
                        </div>  
                        <div class="col-md-12 mb-3">  
                            <label for="edit_notas" class="form-label">Notas (opcional)</label>  
                            <textarea class="form-control" id="edit_notas" name="notas" rows="3"><?php echo htmlspecialchars($livro_atual['notas']); ?></textarea>  
                        </div>  
                    </div>  
                </div>  
                <div class="modal-footer">  
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>  
                    <button type="submit" name="editar_livro" class="btn btn-primary">Salvar Alterações</button>  
                </div>  
            </form>  
        </div>  
    </div>  
</div>  
<?php endif; ?>  

<script>  
document.addEventListener('DOMContentLoaded', function() {  
    if (typeof feather !== 'undefined') {  
        feather.replace();  
    }  

    // Elementos de upload  
    const inputElement = document.createElement('input');  
    inputElement.type = 'file';  
    inputElement.multiple = true;  
    inputElement.accept = '.pdf,.jpg,.jpeg,.png';  
    inputElement.style.display = 'none';  
    document.body.appendChild(inputElement);  

    const dropzoneArea = document.getElementById('dropzoneUpload');  
    const browseBtn = document.querySelector('.browse-btn');  
    const filePreviewContainer = document.getElementById('preview-container');  
    const filePreviewList = document.getElementById('file-preview-list');  
    const uploadForm = document.getElementById('uploadForm');  
    const submitBtn = document.getElementById('submitUpload');  
    const progressContainer = document.getElementById('progressContainer');  
    const progressBar = document.getElementById('progressBar');  
    const uploadStatus = document.getElementById('uploadStatus');  

    // Elementos do visualizador de página  
    const btnAnterior = document.getElementById('btn-pagina-anterior');  
    const btnProxima = document.getElementById('btn-proxima-pagina');  
    const paginaAtual = document.getElementById('pagina-atual');  
    const visualizadorDiv = document.getElementById('visualizador-pagina');  
    const btnFullscreen = document.getElementById('btn-fullscreen');  
    const toggleAnexosBtn = document.getElementById('toggleAnexosBtn');  
    const listaAnexosContainer = document.getElementById('listaAnexosContainer');  
    
    // Variáveis de estado para o visualizador  
    let paginaAtualId = null;  
    let paginasList = [];  
    let livroAtualId = null;  
    const livroIdElement = document.querySelector('input[name="livro_id"]');  
    if (livroIdElement) {  
        livroAtualId = livroIdElement.value;  
    }  
    
    // Variáveis para controle de zoom e rotação  
    let currentZoom = 100; // porcentagem de zoom atual  
    let currentRotation = 0; // graus de rotação atual  
    
    // Função para aplicar transformações combinadas  
    function applyTransform(imgElement) {  
        if (imgElement) {  
            imgElement.style.transform = `rotate(${currentRotation}deg) scale(${currentZoom/100})`;  
        }  
    }  

    // Inicializar o visualizador de páginas se estiver na página  
    if (livroAtualId && visualizadorDiv) {  
        carregarPaginas();  
    }  

    // Função para carregar a lista de páginas do livro  
    function carregarPaginas() {  
        fetch(`api/get_paginas.php?livro_id=${livroAtualId}`)  
            .then(response => {  
                if (!response.ok) {  
                    throw new Error('Erro ao carregar páginas');  
                }  
                return response.json();  
            })  
            .then(data => {  
                if (data.sucesso && data.paginas && data.paginas.length > 0) {  
                    paginasList = data.paginas;  
                    console.log('Páginas carregadas:', paginasList); // Debugging  
                    habilitarNavegacao();  
                } else {  
                    console.error('Nenhuma página encontrada ou erro:', data);  
                }  
            })  
            .catch(error => {  
                console.error('Erro ao carregar páginas:', error);  
            });  
    }  
    
    // Função para exibir uma página específica  
    function exibirPagina(paginaId) {  
        if (!paginaId) return;  
        
        // Encontrar a página na lista  
        const pagina = paginasList.find(p => p.id == paginaId);  
        if (!pagina) {  
            console.error('Página não encontrada:', paginaId);  
            return;  
        }  
        
        // Mostrar carregando  
        if (visualizadorDiv) {  
            visualizadorDiv.innerHTML = '<div class="d-flex justify-content-center align-items-center h-100"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div></div>';  
        }  
        
        // Carregar a imagem com timestamp para evitar cache  
        const timestamp = new Date().getTime();  
        const imagemUrl = `${pagina.caminho}?t=${timestamp}`;  
        
        console.log('Carregando imagem:', imagemUrl); // Debugging  
        
        const img = new Image();  
        img.onload = function() {  
            if (!visualizadorDiv) return;  
            
            // Atualizar visualizador  
            visualizadorDiv.innerHTML = '';  
            visualizadorDiv.appendChild(img);  
            img.id = 'imagem-pagina';  
            img.className = 'img-fluid';  
            img.alt = `Página ${pagina.numero_pagina}`;  
            
            // Reconfigurar os eventos de zoom e rotação para a nova imagem  
            currentZoom = 100;  
            currentRotation = 0;  
            
            // Aplicar eventos aos botões para a nova imagem  
            configurarControlesImagem(img);  
            
            // Atualizar estado  
            paginaAtualId = pagina.id;  

            // ----- Folha + lado -----  
            const folhaEl = document.getElementById('folha-atual');  
            if (folhaEl) {  
                const folhaTxt = `Folha: ${pagina.numero_folha} (${pagina.eh_verso == 1 ? 'Verso' : 'Frente'})`;  
                folhaEl.textContent = folhaTxt;  
            }  

            // ----- Termo(s) -----  
            const termoBox = document.getElementById('termo-atual');  
            if (termoBox) {  
                if (pagina.termo_inicial == pagina.termo_final) {  
                    termoBox.textContent = `Termo: ${pagina.termo_inicial}`;  
                } else {  
                    termoBox.textContent = `Termos ${pagina.termo_inicial} – ${pagina.termo_final}`;  
                }  
            }  

            // Botões de navegação  
            atualizarBotoesNavegacao();  
        };  
        
        img.onerror = function() {  
            console.error('Erro ao carregar imagem:', imagemUrl);  
            if (visualizadorDiv) {  
                visualizadorDiv.innerHTML = `  
                    <div class="d-flex justify-content-center align-items-center h-100 w-100">  
                        <div class="text-center">  
                            <i class="bx bx-error-circle" style="font-size: 3rem; color: #dc3545;"></i>  
                            <p class="mt-2">Erro ao carregar imagem</p>  
                            <small class="text-muted">${imagemUrl}</small>  
                        </div>  
                    </div>`;  
            }  
        };  
        
        img.src = imagemUrl;  
    }  
    
    // Função para atualizar botões de navegação  
    function atualizarBotoesNavegacao() {  
        if (!btnAnterior || !btnProxima) return;  
        
        if (paginasList.length === 0) {  
            btnAnterior.disabled = true;  
            btnProxima.disabled = true;  
            return;  
        }  
        
        const paginaAtualIndex = paginasList.findIndex(p => p.id == paginaAtualId);  
        
        btnAnterior.disabled = paginaAtualIndex <= 0;  
        btnProxima.disabled = paginaAtualIndex >= paginasList.length - 1 || paginaAtualIndex === -1;  
    }  
    
    // Função para habilitar navegação  
    function habilitarNavegacao() {  
        // Se tiver páginas, habilita a navegação  
        if (paginasList.length > 0) {  
            // Exibir primeira página se nenhuma estiver selecionada  
            if (!paginaAtualId) {  
                exibirPagina(paginasList[0].id);  
            }  
            
            atualizarBotoesNavegacao();  
        }  
    }  
    
    // Configurar os botões de zoom e rotação para uma imagem  
    function configurarControlesImagem(imgElement) {  
        if (!imgElement) return;  
        
        // Implementar botões de zoom  
        const zoomOut = document.querySelector('[title="Zoom out"]');  
        const zoomReset = document.querySelector('[title="Ajustar à tela"]');  
        const zoomIn = document.querySelector('[title="Zoom in"]');  
        
        if (zoomOut) {  
            zoomOut.onclick = function() {  
                currentZoom = Math.max(50, currentZoom - 25);  
                applyTransform(imgElement);  
            };  
        }  
        
        if (zoomReset) {  
            zoomReset.onclick = function() {  
                currentZoom = 100;  
                currentRotation = 0;  
                applyTransform(imgElement);  
            };  
        }  
        
        if (zoomIn) {  
            zoomIn.onclick = function() {  
                currentZoom = Math.min(200, currentZoom + 25);  
                applyTransform(imgElement);  
            };  
        }  
        
        // Implementação dos botões de rotação  
        const rotateLeft = document.querySelector('[title="Girar para esquerda"]');  
        const rotateRight = document.querySelector('[title="Girar para direita"]');  
        
        if (rotateLeft) {  
            rotateLeft.onclick = function() {  
                currentRotation = (currentRotation - 90) % 360;  
                applyTransform(imgElement);  
            };  
        }  
        
        if (rotateRight) {  
            rotateRight.onclick = function() {  
                currentRotation = (currentRotation + 90) % 360;  
                applyTransform(imgElement);  
            };  
        }  
    }  
    
    // Navegar por termo  
    const btnIrParaTermo = document.getElementById('btnIrParaTermo');  
    if (btnIrParaTermo) {  
        btnIrParaTermo.addEventListener('click', function() {  
            const inputTermoBusca = document.getElementById('inputTermoBusca');  
            if (!inputTermoBusca) return;  
            
            const termo = parseInt(inputTermoBusca.value);  
            if (isNaN(termo) || paginasList.length === 0) return;  

            const pagina = paginasList.find(p => termo >= p.termo_inicial && termo <= p.termo_final);  
            if (pagina) {  
                exibirPagina(pagina.id);  
            } else {  
                showErrorToast('Termo não encontrado em nenhuma página.');  
            }  
        });  
    }  

    // Navegar por folha  
    const btnIrParaFolha = document.getElementById('btnIrParaFolha');  
    if (btnIrParaFolha) {  
        btnIrParaFolha.addEventListener('click', function() {  
            const inputFolhaBusca = document.getElementById('inputFolhaBusca');  
            if (!inputFolhaBusca) return;  
            
            const folha = parseInt(inputFolhaBusca.value);  
            if (isNaN(folha) || paginasList.length === 0) return;  

            // Como cada folha pode ter frente (eh_verso = 0) e verso (eh_verso = 1), vamos tentar ir para o lado frente  
            const pagina = paginasList.find(p => p.numero_folha == folha && p.eh_verso == 0) ||   
                        paginasList.find(p => p.numero_folha == folha);  
            
            if (pagina) {  
                exibirPagina(pagina.id);  
            } else {  
                showErrorToast('Folha não encontrada.');  
            }  
        });  
    }  

    // Event listeners para navegação  
    if (btnAnterior) {  
        btnAnterior.addEventListener('click', function() {  
            if (!paginaAtualId) return;  
            
            const paginaAtualIndex = paginasList.findIndex(p => p.id == paginaAtualId);  
            if (paginaAtualIndex > 0) {  
                exibirPagina(paginasList[paginaAtualIndex - 1].id);  
            }  
        });  
    }  
    
    if (btnProxima) {  
        btnProxima.addEventListener('click', function() {  
            if (!paginaAtualId) return;  
            
            const paginaAtualIndex = paginasList.findIndex(p => p.id == paginaAtualId);  
            if (paginaAtualIndex < paginasList.length - 1) {  
                exibirPagina(paginasList[paginaAtualIndex + 1].id);  
            }  
        });  
    }  
    
    // Event listener para modo tela cheia  
    if (btnFullscreen && visualizadorDiv) {  
        btnFullscreen.addEventListener('click', function() {  
            if (visualizadorDiv.requestFullscreen) {  
                visualizadorDiv.requestFullscreen();  
            } else if (visualizadorDiv.webkitRequestFullscreen) {  
                visualizadorDiv.webkitRequestFullscreen();  
            } else if (visualizadorDiv.msRequestFullscreen) {  
                visualizadorDiv.msRequestFullscreen();  
            }  
        });  
    }  
    
    // Event listener para a tabela de páginas  
    document.addEventListener('click', function(e) {  
        const target = e.target.closest('[data-pagina-id]');  
        if (target && visualizadorDiv) {  
            const paginaId = target.getAttribute('data-pagina-id');  
            exibirPagina(paginaId);  
            
            // Scroll para o visualizador  
            visualizadorDiv.scrollIntoView({ behavior: 'smooth' });  
        }  
    });  

    // Código para o upload de arquivos  
    if (browseBtn) {  
        browseBtn.addEventListener('click', function() {  
            inputElement.click();  
        });  
    }  

    if (dropzoneArea) {  
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {  
            dropzoneArea.addEventListener(eventName, preventDefaults, false);  
        });  

        function preventDefaults(e) {  
            e.preventDefault();  
            e.stopPropagation();  
        }  

        ['dragenter', 'dragover'].forEach(eventName => {  
            dropzoneArea.addEventListener(eventName, highlight, false);  
        });  

        ['dragleave', 'drop'].forEach(eventName => {  
            dropzoneArea.addEventListener(eventName, unhighlight, false);  
        });  

        function highlight() {  
            dropzoneArea.classList.add('border-primary');  
        }  

        function unhighlight() {  
            dropzoneArea.classList.remove('border-primary');  
        }  

        dropzoneArea.addEventListener('drop', handleDrop, false);  

        function handleDrop(e) {  
            const dt = e.dataTransfer;  
            const files = dt.files;  
            handleFiles(files);  
        }  
    }  

    if (inputElement) {  
        inputElement.addEventListener('change', function() {  
            handleFiles(this.files);  
        });  
    }  

    function handleFiles(files) {  
        if (!filePreviewContainer || !filePreviewList || !submitBtn) return;  
        
        if (files.length > 0) {  
            filePreviewContainer.classList.remove('d-none');  
            filePreviewList.innerHTML = '';  
            submitBtn.disabled = false;  

            Array.from(files).forEach((file, index) => {  
                // Verificar tamanho máximo (2GB)  
                if (file.size > 2 * 1024 * 1024 * 1024) {  
                    showErrorToast('O arquivo ' + file.name + ' excede o tamanho máximo de 2GB.');  
                    return;  
                }  

                const validTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];  
                if (!validTypes.includes(file.type)) {  
                    showErrorToast('O arquivo ' + file.name + ' não é um tipo suportado.');  
                    return;  
                }  

                const fileItem = document.createElement('div');  
                fileItem.className = 'file-item d-flex align-items-center justify-content-between p-2 border-bottom';  

                const fileIcon = file.type === 'application/pdf' ? 'file-text' : 'image';  

                fileItem.innerHTML = `  
                    <div class="d-flex align-items-center">  
                        <i data-feather="${fileIcon}" class="me-2" style="width: 18px; height: 18px;"></i>  
                        <div>  
                            <div class="fw-semibold">${file.name}</div>  
                            <div class="text-muted small">${formatFileSize(file.size)}</div>  
                        </div>  
                    </div>  
                    <button type="button" class="btn btn-sm btn-outline-danger remove-file" data-index="${index}">  
                        <i data-feather="x" style="width: 14px; height: 14px;"></i>  
                    </button>  
                `;  

                filePreviewList.appendChild(fileItem);  
            });  

            if (typeof feather !== 'undefined') {  
                feather.replace();  
            }  

            setTimeout(() => {  
                document.querySelectorAll('.remove-file').forEach(btn => {  
                    btn.addEventListener('click', function() {  
                        const item = this.closest('.file-item');  
                        if (item) {  
                            item.remove();  
                            if (filePreviewList.children.length === 0) {  
                                filePreviewContainer.classList.add('d-none');  
                                submitBtn.disabled = true;  
                            }  
                        }  
                    });  
                });  
            }, 100);  
        }  
    }  

    function formatFileSize(bytes) {  
        if (bytes < 1024) return bytes + ' bytes';  
        else if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';  
        else if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(2) + ' MB';  
        else return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';  
    }  

    if (uploadForm) {  
        uploadForm.addEventListener('submit', function(e) {  
            e.preventDefault();  

            if (!inputElement || !filePreviewList || !progressContainer || !submitBtn) return;  

            const files = Array.from(inputElement.files).filter(file => {  
                const fileNames = Array.from(filePreviewList.querySelectorAll('.file-item')).map(item =>  
                    item.querySelector('.fw-semibold')?.textContent);  
                return fileNames.includes(file.name);  
            });  

            if (files.length === 0) {  
                showErrorToast('Selecione pelo menos um arquivo para enviar.');  
                return;  
            }  

            const maxFileSize = 2 * 1024 * 1024 * 1024;  
            for (const file of files) {  
                if (file.size > maxFileSize) {  
                    showErrorToast(`O arquivo ${file.name} excede o tamanho máximo de 2GB.`);  
                    return;  
                }  
            }  

            progressContainer.classList.remove('d-none');  
            submitBtn.disabled = true;  

            const formData = new FormData();  
            const livroIdInput = document.querySelector('input[name="livro_id"]');  
            if (livroIdInput) {  
                formData.append('livro_id', livroIdInput.value);  
            } else {  
                showErrorToast('ID do livro não encontrado.');  
                return;  
            }  

            files.forEach(file => {  
                formData.append('arquivos[]', file);  
            });  

            const xhr = new XMLHttpRequest();  

            xhr.upload.addEventListener('progress', function(e) {  
                if (e.lengthComputable && progressBar) {  
                    const percentComplete = Math.round((e.loaded / e.total) * 100);  
                    progressBar.style.width = percentComplete + '%';  
                    progressBar.textContent = percentComplete + '%';  
                    progressBar.setAttribute('aria-valuenow', percentComplete);  

                    if (uploadStatus) {  
                        if (percentComplete < 100) {  
                            uploadStatus.textContent = 'Enviando arquivos... ' + percentComplete + '%';  
                        } else {  
                            uploadStatus.textContent = 'Processando arquivos, aguarde...';  
                        }  
                    }  
                }  
            });  

            xhr.addEventListener('load', function() {  
                if (xhr.status >= 200 && xhr.status < 300) {  
                    try {  
                        const response = JSON.parse(xhr.responseText);  
                        if (response.success) {  
                            showSuccessToast(response.message);  
                            setTimeout(() => {  
                                window.location.reload();  
                            }, 2000);  
                        } else {  
                            if (response.erros && response.erros.length > 0) {  
                                response.erros.forEach(erro => {  
                                    showErrorToast(erro);  
                                });  
                            } else {  
                                showErrorToast(response.message || 'Erro ao processar os arquivos.');  
                            }  
                            if (progressContainer) progressContainer.classList.add('d-none');  
                            if (submitBtn) submitBtn.disabled = false;  
                        }  
                    } catch (e) {  
                        console.error('Erro ao processar resposta:', e, xhr.responseText);  
                        showErrorToast('Erro ao processar resposta do servidor.');  
                        if (progressContainer) progressContainer.classList.add('d-none');  
                        if (submitBtn) submitBtn.disabled = false;  
                    }  
                } else {  
                    showErrorToast('Erro ao enviar arquivos. Código: ' + xhr.status);  
                    if (progressContainer) progressContainer.classList.add('d-none');  
                    if (submitBtn) submitBtn.disabled = false;  
                }  
            });  

            xhr.addEventListener('error', function() {  
                showErrorToast('Erro de conexão. Verifique sua internet e tente novamente.');  
                if (progressContainer) progressContainer.classList.add('d-none');  
                if (submitBtn) submitBtn.disabled = false;  
            });  

            xhr.addEventListener('timeout', function() {  
                showErrorToast('A requisição demorou muito tempo. Verifique sua conexão e tente novamente.');  
                if (progressContainer) progressContainer.classList.add('d-none');  
                if (submitBtn) submitBtn.disabled = false;  
            });  

            xhr.open('POST', 'upload_livro.php', true);  
            xhr.send(formData);  
        });  
    }  

    function showSuccessToast(message) {  
        if (typeof Swal !== 'undefined') {  
            Swal.fire({  
                icon: 'success',  
                title: 'Sucesso',  
                text: message,  
                confirmButtonColor: '#4caf50'  
            });  
        } else {  
            alert('Sucesso: ' + message);  
        }  
    }  

    function showErrorToast(message) {  
        if (typeof Swal !== 'undefined') {  
            Swal.fire({  
                icon: 'error',  
                title: 'Erro',  
                text: message,  
                confirmButtonColor: '#f44336'  
            });  
        } else {  
            alert('Erro: ' + message);  
        }  
    }  

    // Função para verificar o caminho de uma imagem  
    function verificarImagem(url) {  
        return new Promise((resolve, reject) => {  
            const img = new Image();  
            img.onload = () => resolve(true);  
            img.onerror = () => resolve(false);  
            img.src = url;  
        });  
    }  

    // Debug helper para casos onde as imagens não aparecem  
    function debugImagemFalha(imagemUrl) {  
        console.log('Depurando imagem:', imagemUrl);  
        
        // Verificar se a URL é válida  
        if (!imagemUrl || typeof imagemUrl !== 'string') {  
            console.error('URL da imagem inválida:', imagemUrl);  
            return;  
        }  

        // Tentar carregar a imagem  
        const img = new Image();  
        img.onload = function() {  
            console.log('✅ Imagem carregada com sucesso:', imagemUrl);  
        };  
        
        img.onerror = function() {  
            console.error('❌ Falha ao carregar imagem:', imagemUrl);  
            
            // Verificar se o caminho é relativo ou absoluto  
            if (imagemUrl.startsWith('/')) {  
                console.log('Caminho absoluto detectado. Sugerindo verificar permissões do arquivo no servidor.');  
            } else {  
                console.log('Caminho relativo detectado. Tentando resolver caminho completo.');  
                const baseUrl = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);  
                const fullUrl = new URL(imagemUrl, baseUrl).href;  
                console.log('URL completa:', fullUrl);  
                
                // Tentar com caminho completo  
                const imgFull = new Image();  
                imgFull.onload = function() {  
                    console.log('✅ Imagem carregada com URL completa:', fullUrl);  
                };  
                imgFull.onerror = function() {  
                    console.error('❌ Também falhou com URL completa:', fullUrl);  
                    console.log('Sugestões de solução:');  
                    console.log('1. Verificar se o arquivo existe no caminho indicado');  
                    console.log('2. Verificar permissões do arquivo no servidor');  
                    console.log('3. Verificar se o diretório está acessível via web');  
                };  
                imgFull.src = fullUrl;  
            }  
        };  
        
        img.src = imagemUrl;  
    }  

    // Adicionar função de debug para o visualizador  
    window.debugVisualizador = function() {  
        console.log('Depuração do visualizador:');  
        console.log('Livro ID:', livroAtualId);  
        console.log('Páginas carregadas:', paginasList);  
        console.log('Página atual ID:', paginaAtualId);  
        
        if (paginasList.length === 0) {  
            console.log('❌ Nenhuma página carregada. Verificando endpoint API...');  
            fetch(`api/get_paginas.php?livro_id=${livroAtualId}`)  
                .then(response => {  
                    console.log('Status da resposta API:', response.status);  
                    console.log('Headers:', [...response.headers.entries()]);  
                    return response.text();  
                })  
                .then(text => {  
                    console.log('Resposta da API:', text);  
                    try {  
                        const data = JSON.parse(text);  
                        console.log('Dados JSON:', data);  
                    } catch (e) {  
                        console.error('Falha ao parsear JSON:', e);  
                    }  
                })  
                .catch(error => {  
                    console.error('Erro ao chamar API:', error);  
                });  
        } else {  
            console.log('✅ Páginas foram carregadas, verificando caminhos:');  
            paginasList.forEach(pagina => {  
                verificarImagem(pagina.caminho)  
                    .then(valido => {  
                        if (valido) {  
                            console.log(`✅ Caminho válido: ${pagina.caminho}`);  
                        } else {  
                            console.error(`❌ Caminho inválido: ${pagina.caminho}`);  
                            debugImagemFalha(pagina.caminho);  
                        }  
                    });  
            });  
        }  
    };  

    // Adicionar verificação automática se o visualizador estiver vazio por mais de 3 segundos  
    if (visualizadorDiv && livroAtualId) {  
        setTimeout(() => {  
            if (paginasList.length === 0) {  
                console.warn('O visualizador não carregou nenhuma página em 3 segundos. Executando diagnóstico...');  
                window.debugVisualizador();  
            }  
        }, 3000);  
    }  
    
    // Toggle para anexos  
    if (toggleAnexosBtn && listaAnexosContainer) {  
        toggleAnexosBtn.addEventListener('click', function() {  
            const visivel = listaAnexosContainer.style.display === 'block';  
            listaAnexosContainer.style.display = visivel ? 'none' : 'block';  
            toggleAnexosBtn.innerHTML = visivel  
                ? '<i data-feather="chevron-down" class="me-1"></i> Exibir Lista de Anexos'  
                : '<i data-feather="chevron-up" class="me-1"></i> Ocultar Lista de Anexos';  
            if (typeof feather !== 'undefined') {  
                feather.replace();  
            }  
        });  
    }  
    
    // Toggle para área de upload usando jQuery se disponível  
    if (typeof $ === 'function') {  
        $('#toggleUploadArea').on('click', function() {  
            const container = $('#uploadAreaContainer');  
            const btn = $(this);  
            const isVisible = container.is(':visible');  

            container.slideToggle(200, function() {  
                const icon = isVisible ? 'chevron-down' : 'chevron-up';  
                const label = isVisible ? 'Exibir Área de Upload' : 'Ocultar Área de Upload';  

                btn.html(`<i data-feather="${icon}" class="me-1"></i> ${label}`);  
                if (typeof feather !== 'undefined') {  
                    feather.replace();  
                }  
            });  
        });  
    }  
});  

function atualizarNavegacao(folha, termo) {  
    const folhaEl = document.getElementById('folha-atual');  
    const termoEl = document.getElementById('termo-atual');  
    
    if (!folhaEl || !termoEl) return;  
    
    // Remover classe de animação  
    folhaEl.classList.remove('highlight-change');  
    termoEl.classList.remove('highlight-change');  
    
    // Força um reflow para reiniciar a animação  
    void folhaEl.offsetWidth;  
    void termoEl.offsetWidth;  
    
    // Atualizar valores  
    folhaEl.textContent = folha || '-';  
    termoEl.textContent = termo || '-';  
    
    // Adicionar classe para animar  
    folhaEl.classList.add('highlight-change');  
    termoEl.classList.add('highlight-change');  
}  

// Verifica se o jQuery já está carregado  
if (typeof jQuery === 'undefined') {  
    const jqueryScript = document.createElement('script');  
    jqueryScript.src = 'https://code.jquery.com/jquery-3.6.0.min.js';  
    jqueryScript.onload = function() {  
        console.log('jQuery carregado dinamicamente');  
        // Carrega os scripts do DataTables após garantir que o jQuery está disponível  
        carregarScriptsDataTables();  
    };  
    document.head.appendChild(jqueryScript);  
} else {  
    // Se jQuery já estiver disponível, carrega os scripts do DataTables  
    carregarScriptsDataTables();  
}  

// Função para carregar scripts do DataTables sequencialmente  
function carregarScriptsDataTables() {  
    const scripts = [  
        'https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js',  
        'https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js',  
        'https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js',  
        'https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js',  
        'https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js',  
        'https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js',  
        'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',  
        'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js',  
        'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js',  
        'https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js',  
        'https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js',  
        'js/buttons.colvis.min.js'  
    ];  
    
    let scriptIndex = 0;  
    
    function carregarProximoScript() {  
        if (scriptIndex >= scripts.length) {  
            // Todos os scripts foram carregados, initialize o DataTables  
            setTimeout(function() {  
                initDataTables();  
            }, 500);  
            return;  
        }  
        
        const script = document.createElement('script');  
        script.src = scripts[scriptIndex];  
        script.onload = function() {  
            console.log(`Script carregado: ${scripts[scriptIndex]}`);  
            scriptIndex++;  
            carregarProximoScript();  
        };  
        script.onerror = function() {  
            console.error(`Erro ao carregar script: ${scripts[scriptIndex]}`);  
            // Continua carregando os próximos scripts mesmo se um falhar  
            scriptIndex++;  
            carregarProximoScript();  
        };  
        document.head.appendChild(script);  
    }  
    
    // Inicia o carregamento sequencial  
    carregarProximoScript();  
}  

// Função para inicializar o DataTables corretamente  
function initDataTables() {  
    if (typeof $ === 'function' && typeof $.fn !== 'undefined' && typeof $.fn.DataTable === 'function') {  
        console.log('DataTables disponível, verificando inicialização...');  
        
        const tabelaLivros = document.getElementById('tabelaLivros');  
        if (!tabelaLivros) {  
            // Se a tabela não tiver um ID específico, tente selecionar a primeira tabela disponível  
            if ($('table').length > 0) {  
                // Adiciona o ID à primeira tabela encontrada  
                $('table').first().attr('id', 'tabelaLivros');  
                console.log('ID tabelaLivros adicionado à primeira tabela disponível');  
            } else {  
                console.warn('Nenhuma tabela encontrada na página');  
                return;  
            }  
        }  
        
        try {  
            // Verifica se a tabela já foi inicializada como DataTable  
            if ($.fn.dataTable.isDataTable('#tabelaLivros')) {  
                console.log('Destruindo instância anterior do DataTable');  
                $('#tabelaLivros').DataTable().destroy();  
            }  
            
            console.log('Inicializando DataTable com configuração completa...');  
            var table = $('#tabelaLivros').DataTable({  
                responsive: true,  
                language: {  
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'  
                },  
                // Configuração padrão para mostrar todos os controles  
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +  
                     '<"row"<"col-sm-12"tr>>' +  
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',  
                pageLength: 10,  
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],  
                order: [[1, 'desc']],  
                // Botões adicionados separadamente para evitar problemas  
                buttons: []  
            });  
            
            // Adiciona os botões após a inicialização, se o plugin de botões estiver disponível  
            if (typeof $.fn.DataTable.Buttons !== 'undefined') {  
                new $.fn.DataTable.Buttons(table, {  
                    buttons: [  
                        {  
                            extend: 'copy',  
                            text: 'Copiar',  
                            className: 'btn btn-sm btn-outline-secondary'  
                        },  
                        {  
                            extend: 'excel',  
                            text: 'Excel',  
                            className: 'btn btn-sm btn-outline-success'  
                        },  
                        {  
                            extend: 'pdf',  
                            text: 'PDF',  
                            className: 'btn btn-sm btn-outline-danger'  
                        },  
                        {  
                            extend: 'print',  
                            text: 'Imprimir',  
                            className: 'btn btn-sm btn-outline-primary'  
                        }  
                    ]  
                });  
                
                // Adiciona o botão 'colvis' apenas se o arquivo de script estiver carregado  
                if (typeof $.fn.DataTable.ext.buttons.colvis !== 'undefined') {  
                    new $.fn.DataTable.Buttons(table, {  
                        buttons: [  
                            {  
                                extend: 'colvis',  
                                text: 'Colunas',  
                                className: 'btn btn-sm btn-outline-info'  
                            }  
                        ]  
                    });  
                }  
            }  
            
            console.log('DataTable inicializado com sucesso');  
        } catch (e) {  
            console.error('Erro ao inicializar DataTable:', e);  
        }  
    } else {  
        console.error('DataTables não está disponível');  
        if (typeof $ !== 'function') console.error('jQuery não está disponível');  
        else if (typeof $.fn === 'undefined') console.error('jQuery.fn não está disponível');  
        else if (typeof $.fn.DataTable !== 'function') console.error('DataTable não está disponível em jQuery');  
    }  
}  

// Espera o DOM carregar e tenta inicializar o DataTables  
let dataTablesInitialized = false;  
document.addEventListener('DOMContentLoaded', function() {  
    console.log('DOM carregado, verificando existência do jQuery e DataTables');  
    
    // Verifica periodicamente se o DataTables já está disponível  
    let tentativas = 0;  
    const maxTentativas = 10;  
    const intervalo = setInterval(function() {  
        tentativas++;  
        console.log(`Tentativa ${tentativas} de inicializar o DataTables`);  
        
        if (typeof $ === 'function' && typeof $.fn !== 'undefined' && typeof $.fn.DataTable === 'function') {  
            console.log('DataTables disponível, inicializando...');  
            if (!dataTablesInitialized) {  
                initDataTables();  
                dataTablesInitialized = true;  
            }  
            clearInterval(intervalo);  
        } else if (tentativas >= maxTentativas) {  
            console.error('Número máximo de tentativas excedido. DataTables não inicializado.');  
            clearInterval(intervalo);  
        }  
    }, 500);  
});


document.addEventListener('DOMContentLoaded', function () {
    const selectModo = document.getElementById('modo_termo');
    const campoTermosPorPagina = document.getElementById('campo_termos_por_pagina');
    const campoPaginasPorTermo = document.getElementById('campo_paginas_por_termo');

    selectModo.addEventListener('change', function () {
        if (this.value === 'termos_por_pagina') {
            campoTermosPorPagina.classList.remove('d-none');
            campoPaginasPorTermo.classList.add('d-none');
        } else {
            campoPaginasPorTermo.classList.remove('d-none');
            campoTermosPorPagina.classList.add('d-none');
        }
    });
});

</script>

<?php include 'includes/footer.php'; ?>   