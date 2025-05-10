<?php  
// editar_triagem.php  

// Configuração e verificações iniciais  

date_default_timezone_set('America/Sao_Paulo');  
require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {  
    header("Location: triagem.php?error=registro_invalido");  
    exit;  
}  

$id = intval($_GET['id']);  
$stmt = $pdo->prepare("SELECT * FROM triagem_registros WHERE id = ?");  
$stmt->execute([$id]);  
$reg = $stmt->fetch();  

if (!$reg) {  
    header("Location: triagem.php?error=registro_nao_encontrado");  
    exit;  
}  

// Atualização via POST  
if ($_SERVER['REQUEST_METHOD'] === 'POST') {  
    try {  
        $campos = [  
            'nome_requerente','documento_identificacao','cpf','tipo_certidao',  
            'serventia_nome','serventia_cidade','serventia_uf',  
            'livro','folha','termo','nome_registrado','data_evento','filiacao_conjuge'  
        ];  
        $setSql = implode(', ', array_map(fn($c)=>"$c = ?", $campos));  
        $valores = array_map(fn($c)=>$_POST[$c] ?? null, $campos);  
        $valores[] = $id;  

        $pdo->prepare("UPDATE triagem_registros SET $setSql WHERE id = ?")->execute($valores);  

        header("Location: triagem.php?id=$id&success=Cadastro atualizado");  
        exit;  
    } catch (Exception $e) {  
        header("Location: editar_triagem.php?id=$id&error=" . urlencode($e->getMessage()));  
        exit;  
    }  
}  

include 'includes/header.php';  
?>  

<?php include(__DIR__ . '/css/style-triagem.php'); ?>  

<div class="container py-4 animate-fadeIn">  
    <div class="row mb-4">  
        <div class="col-12 d-flex justify-content-between align-items-center">  
            <div>  
                <h1 class="fw-bold text-gray-800">  
                    <i data-feather="edit" class="me-2 text-primary"></i>  
                    Editar Registro – <?= htmlspecialchars($reg['protocolo']) ?>  
                </h1>  
                <p class="text-muted lead fs-6">Atualize os dados da solicitação de certidão</p>  
            </div>  
            <a href="triagem.php" class="btn btn-outline-secondary">  
                <i data-feather="arrow-left" class="me-1"></i> Voltar  
            </a>  
        </div>  
    </div>  

    <?php if (isset($_GET['error'])): ?>  
        <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>  
    <?php endif; ?>  

    <form method="POST">  
        <div class="card mb-4 shadow-sm">  
            <div class="card-body row g-3">  
                <input type="hidden" name="protocolo" value="<?= $reg['protocolo'] ?>">  

                <div class="col-12 mb-2">  
                    <h6 class="text-primary mb-3 border-bottom pb-2">  
                        <i data-feather="user" class="me-2"></i>Dados do Requerente  
                    </h6>  
                </div>  

                <div class="col-md-6">  
                    <label class="form-label">Nome do Requerente</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="user" style="width:18px;height:18px"></i></span>  
                        <input type="text" class="form-control" name="nome_requerente" value="<?= htmlspecialchars($reg['nome_requerente']) ?>" required>  
                    </div>  
                </div>  
                <div class="col-md-3">  
                    <label class="form-label">Documento (RG/CNH)</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="credit-card" style="width:18px;height:18px"></i></span>  
                        <input type="text" class="form-control" name="documento_identificacao" value="<?= htmlspecialchars($reg['documento_identificacao']) ?>">  
                    </div>  
                </div>  
                <div class="col-md-3">  
                    <label class="form-label">CPF</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="hash" style="width:18px;height:18px"></i></span>  
                        <input type="text" class="form-control cpf-mask" name="cpf" id="cpfInput" value="<?= htmlspecialchars($reg['cpf']) ?>">  
                    </div>  
                </div>  

                <div class="col-12 mt-4 mb-2">  
                    <h6 class="text-primary mb-3 border-bottom pb-2">  
                        <i data-feather="file-text" class="me-2"></i>Dados da Certidão  
                    </h6>  
                </div>  

                <div class="col-md-3">  
                    <label class="form-label">Tipo de Certidão</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="file" style="width:18px;height:18px"></i></span>  
                        <select class="form-select" name="tipo_certidao" id="tipoCertidao" required>  
                            <option value="">Selecione</option>  
                            <option value="nascimento" <?= $reg['tipo_certidao']=='nascimento'?'selected':'' ?>>Nascimento</option>  
                            <option value="casamento" <?= $reg['tipo_certidao']=='casamento'?'selected':'' ?>>Casamento</option>  
                        </select>  
                    </div>  
                </div>  
                <div class="col-md-3">  
                    <label class="form-label">Cartório</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="home" style="width:18px;height:18px"></i></span>  
                        <input type="text" class="form-control" name="serventia_nome" value="<?= htmlspecialchars($reg['serventia_nome']) ?>">  
                    </div>  
                </div>  
                <div class="col-md-4">  
                    <label class="form-label">Cidade</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="map-pin" style="width:18px;height:18px"></i></span>  
                        <input type="text" class="form-control" id="cidadeInput" name="serventia_cidade" value="<?= htmlspecialchars($reg['serventia_cidade']) ?>">  
                        <button class="btn btn-outline-secondary" type="button" id="btnOpenCityModal">  
                            <i data-feather="search"></i>  
                        </button>  
                    </div>  
                </div>  
                <div class="col-md-2">  
                    <label class="form-label">UF</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="map" style="width:18px;height:18px"></i></span>  
                        <input type="text" maxlength="2" class="form-control" id="ufInput" name="serventia_uf" value="<?= htmlspecialchars($reg['serventia_uf']) ?>">  
                    </div>  
                </div>  

                <div class="col-12 mt-4 mb-2">  
                    <h6 class="text-primary mb-3 border-bottom pb-2">  
                        <i data-feather="bookmark" class="me-2"></i>Dados do Registro  
                    </h6>  
                </div>  

                <div class="col-md-4">  
                    <label class="form-label">Nome do Registrado</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="user" style="width:18px;height:18px"></i></span>  
                        <input type="text" class="form-control" name="nome_registrado" value="<?= htmlspecialchars($reg['nome_registrado']) ?>">  
                    </div>  
                </div>  
                <div class="col-md-3">  
                    <label class="form-label" id="labelEvento">Data do Evento</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="calendar" style="width:18px;height:18px"></i></span>  
                        <input type="date" class="form-control" name="data_evento" value="<?= $reg['data_evento'] ?>">  
                    </div>  
                </div>  
                <div class="col-md-5">  
                    <label class="form-label" id="labelFiliacaoConjuge">Filiação / Cônjuge</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="users" style="width:18px;height:18px"></i></span>  
                        <input type="text" class="form-control" name="filiacao_conjuge" value="<?= htmlspecialchars($reg['filiacao_conjuge']) ?>">  
                    </div>  
                </div>  

                <div class="col-md-2">  
                    <label class="form-label">Livro</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="book" style="width:18px;height:18px"></i></span>  
                        <input type="text" class="form-control" name="livro" value="<?= htmlspecialchars($reg['livro']) ?>">  
                    </div>  
                </div>  
                <div class="col-md-2">  
                    <label class="form-label">Folha</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="file" style="width:18px;height:18px"></i></span>  
                        <input type="text" class="form-control" name="folha" value="<?= htmlspecialchars($reg['folha']) ?>">  
                    </div>  
                </div>  
                <div class="col-md-2">  
                    <label class="form-label">Termo</label>  
                    <div class="input-group">  
                        <span class="input-group-text"><i data-feather="hash" style="width:18px;height:18px"></i></span>  
                        <input type="text" class="form-control" name="termo" value="<?= htmlspecialchars($reg['termo']) ?>">  
                    </div>  
                </div>  

                <div class="col-12 d-flex justify-content-end mt-4">  
                    <a href="triagem.php?id=<?= $id ?>" class="btn btn-outline-secondary me-2">  
                        <i data-feather="x" class="me-1"></i> Cancelar  
                    </a>  
                    <button type="submit" class="btn btn-primary">  
                        <i data-feather="save" class="me-1"></i> Salvar Alterações  
                    </button>  
                </div>  
            </div>  
        </div>  
    </form>  
</div>  

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>  
<script src="https://unpkg.com/feather-icons"></script>  
<script>  
// Inicializar ícones  
feather.replace();  

// Função para formatar CPF  
function formatarCPF(cpf) {  
    cpf = cpf.replace(/\D/g, '');  
    if (cpf.length !== 11) return cpf;  
    return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");  
}  

// Aplicar máscara de CPF  
function aplicarMascaraCPF() {  
    document.querySelectorAll('.cpf-mask').forEach(function(input) {  
        input.addEventListener('input', function() {  
            let value = this.value.replace(/\D/g, '');  
            if (value.length > 11) {  
                value = value.substring(0, 11);  
            }  
            
            // Formatar como XXX.XXX.XXX-XX  
            let formattedValue = '';  
            for (let i = 0; i < value.length; i++) {  
                if (i === 3 || i === 6) formattedValue += '.';  
                if (i === 9) formattedValue += '-';  
                formattedValue += value[i];  
            }  
            
            this.value = formattedValue;  
        });  
    });  
}  

// Função para atualizar labels conforme o tipo de certidão  
function atualizarLabels() {  
    const tipoCertidao = document.getElementById('tipoCertidao').value;  
    const labelFiliacaoConjuge = document.getElementById('labelFiliacaoConjuge');  
    const labelEvento = document.getElementById('labelEvento');  
    
    if (tipoCertidao === 'nascimento') {  
        labelFiliacaoConjuge.textContent = 'Filiação';  
        labelEvento.textContent = 'Data de Nascimento';  
    } else if (tipoCertidao === 'casamento') {  
        labelFiliacaoConjuge.textContent = 'Cônjuge';  
        labelEvento.textContent = 'Data de Casamento';  
    } else {  
        labelFiliacaoConjuge.textContent = 'Filiação / Cônjuge';  
        labelEvento.textContent = 'Data do Evento';  
    }  
}  

// Quando a página carrega, inicializar componentes  
document.addEventListener('DOMContentLoaded', function() {  
    aplicarMascaraCPF();  
    atualizarLabels();  
    
    // Adicionar evento para mudar os labels quando o tipo de certidão mudar  
    document.getElementById('tipoCertidao').addEventListener('change', atualizarLabels);  
    
    // Validação de CPF  
    document.getElementById('cpfInput').addEventListener('blur', function() {  
        const cpf = this.value.replace(/[^\d]+/g,'');  
        if (cpf && !validarCPF(cpf)) {  
            this.classList.add('is-invalid');  
            // Adicionar mensagem de erro se não existir  
            if (!this.parentNode.querySelector('.invalid-feedback')) {  
                const feedback = document.createElement('div');  
                feedback.className = 'invalid-feedback';  
                feedback.textContent = 'CPF inválido';  
                this.parentNode.appendChild(feedback);  
            }  
        } else {  
            this.classList.remove('is-invalid');  
            const feedback = this.parentNode.querySelector('.invalid-feedback');  
            if (feedback) feedback.remove();  
        }  
    });  
    
    // Função para validar CPF  
    function validarCPF(cpf) {  
        // Elimina CPFs inválidos conhecidos  
        if (cpf.length !== 11 ||   
            cpf === "00000000000" ||   
            cpf === "11111111111" ||   
            cpf === "22222222222" ||   
            cpf === "33333333333" ||   
            cpf === "44444444444" ||   
            cpf === "55555555555" ||   
            cpf === "66666666666" ||   
            cpf === "77777777777" ||   
            cpf === "88888888888" ||   
            cpf === "99999999999") {  
            return false;  
        }  
        
        // Valida 1º dígito  
        let add = 0;  
        for (let i = 0; i < 9; i++) {  
            add += parseInt(cpf.charAt(i)) * (10 - i);  
        }  
        let rev = 11 - (add % 11);  
        if (rev === 10 || rev === 11) {  
            rev = 0;  
        }  
        if (rev !== parseInt(cpf.charAt(9))) {  
            return false;  
        }  
        
        // Valida 2º dígito  
        add = 0;  
        for (let i = 0; i < 10; i++) {  
            add += parseInt(cpf.charAt(i)) * (11 - i);  
        }  
        rev = 11 - (add % 11);  
        if (rev === 10 || rev === 11) {  
            rev = 0;  
        }  
        if (rev !== parseInt(cpf.charAt(10))) {  
            return false;  
        }  
        
        return true;  
    }  
});  
</script>  
<?php include 'includes/footer.php'; ?>