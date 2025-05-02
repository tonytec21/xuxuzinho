<?php  
date_default_timezone_set('America/Sao_Paulo'); 
require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  

// Buscar estatísticas do usuário atual  
$usuario_id = $_SESSION['usuario_id'];  

// Total de selos cadastrados (apenas ativos)  
$stmt = $pdo->query("SELECT COUNT(*) as total FROM selos WHERE status = 'ativo'");  
$total_selos = $stmt->fetch()['total'];  

// Total de selos enviados ao portal  
$stmt = $pdo->query("  
    SELECT COUNT(*) as total   
    FROM selos   
    WHERE status = 'ativo' AND enviado_portal = 'sim'  
");  
$selos_enviados = $stmt->fetch()['total'];  

// Total de selos pendentes de envio  
$stmt = $pdo->query("  
    SELECT COUNT(*) as total   
    FROM selos   
    WHERE status = 'ativo' AND (enviado_portal = 'nao' OR enviado_portal IS NULL)  
");  
$selos_pendentes = $stmt->fetch()['total'];  

// Total de selos sem anexos  
$stmt = $pdo->query("  
    SELECT COUNT(*) as total   
    FROM selos s   
    WHERE s.status = 'ativo'   
    AND NOT EXISTS (  
        SELECT 1 FROM anexos a   
        WHERE a.selo_id = s.id AND a.status = 'ativo'  
    )  
");  
$selos_sem_anexos = $stmt->fetch()['total'];  

// Mantendo estas variáveis para compatibilidade com o código existente  
$stmt = $pdo->query("  
    SELECT COUNT(*) as total   
    FROM anexos a   
    JOIN selos s ON a.selo_id = s.id   
    WHERE a.status = 'ativo' AND s.status = 'ativo'  
");  
$total_anexos = $stmt->fetch()['total'];  

$stmt = $pdo->query("  
    SELECT COUNT(*) as total   
    FROM downloads_selo d  
    JOIN selos s ON d.selo_id = s.id  
");  
$total_downloads = $stmt->fetch()['total'];  

// Últimos selos cadastrados (apenas ativos)  
$stmt = $pdo->query("  
    SELECT s.*,   
           COUNT(DISTINCT a.id) as total_anexos,  
           (SELECT COUNT(*) FROM downloads_selo d WHERE d.selo_id = s.id) as total_downloads  
    FROM selos s  
    LEFT JOIN anexos a ON s.id = a.selo_id AND a.status = 'ativo'  
    WHERE s.status = 'ativo'  
    GROUP BY s.id  
    ORDER BY s.data_cadastro DESC  
    LIMIT 5  
");   
$ultimos_selos = $stmt->fetchAll();  

// ==== INÍCIO DAS NOVAS FUNCIONALIDADES PARA OS GRÁFICOS ====  

// Obter os últimos 6 meses para o gráfico de atividade  
function obterUltimosSeisMeses() {  
    $meses = [];  
    $labels = [];  
    
    // Array com os nomes abreviados dos meses em português  
    $nomes_meses_pt = [  
        1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',  
        7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'  
    ];  
    
    for ($i = 5; $i >= 0; $i--) {  
        $data = date('Y-m-01', strtotime("-$i months"));  
        $meses[] = $data;  
        
        // Obter o número do mês (1-12) e usar o nome em português  
        $mes_numero = (int)date('n', strtotime($data));  
        $labels[] = $nomes_meses_pt[$mes_numero];  
    }  
    
    return ['meses' => $meses, 'labels' => $labels];  
}

// Buscar dados de atividade dos últimos 6 meses  
function buscarDadosAtividade($pdo, $meses) {  
    $cadastros = [];  
    $envios = [];  
    
    foreach ($meses as $mes) {  
        $inicio_mes = $mes;  
        $fim_mes = date('Y-m-t', strtotime($mes));  
        
        // Consulta SQL para o mês atual  
        $sql = "  
            SELECT   
                COUNT(*) as total_cadastros,  
                SUM(CASE WHEN enviado_portal = 'sim' THEN 1 ELSE 0 END) as total_enviados  
            FROM selos  
            WHERE status = 'ativo'   
            AND data_cadastro BETWEEN :inicio AND :fim  
        ";  
        
        $stmt = $pdo->prepare($sql);  
        $stmt->execute([  
            ':inicio' => $inicio_mes . ' 00:00:00',  
            ':fim' => $fim_mes . ' 23:59:59'  
        ]);  
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);  
        
        $cadastros[] = (int)$resultado['total_cadastros'];  
        $envios[] = (int)$resultado['total_enviados'];  
    }  
    
    return [  
        'cadastros' => $cadastros,  
        'envios' => $envios  
    ];  
}  

// Obter dados para o gráfico de atividade  
$periodos = obterUltimosSeisMeses();  
$labels_meses = $periodos['labels'];  
$meses_completos = $periodos['meses'];  
$dados_atividade = buscarDadosAtividade($pdo, $meses_completos);  

// Total de cadastros e envios no mês atual (último mês do array)  
$selos_mes = end($dados_atividade['cadastros']) ?: 0;  
$envios_mes = end($dados_atividade['envios']) ?: 0;  

// Resetar o ponteiro dos arrays após usar end()  
reset($dados_atividade['cadastros']);  
reset($dados_atividade['envios']);  

// Incluir o cabeçalho  
include 'includes/header.php';  
?>

<div class="container-fluid py-4">  
    <div class="row mb-4">  
        <div class="col-12">  
            <h1 class="fw-bold">Painel de Controle</h1>  
            <p class="text-muted">Bem-vindo(a) de volta, <?php   
                // Pega apenas o primeiro nome do usuário  
                $nome_completo = $_SESSION['usuario_nome'];  
                $primeiro_nome = explode(' ', $nome_completo)[0];  
                
                // Garante que a primeira letra esteja em maiúscula  
                echo ucfirst(strtolower($primeiro_nome));   
            ?>!</p> 
        </div>  
    </div>  
    
    <div class="row mb-4">  
        <!-- Card 1: Total de Selos -->  
        <div class="col-md-6 col-lg-3 mb-3">  
            <a href="selos.php?status=todos" class="text-decoration-none">  
                <div class="card border-0 shadow-sm h-100 hover-effect">  
                    <div class="card-body d-flex align-items-center">  
                        <div class="rounded-circle bg-primary p-3 me-3">  
                            <i data-feather="file-text" class="text-white"></i>  
                        </div>  
                        <div>  
                            <h3 class="mb-0"><?php echo $total_selos; ?></h3>  
                            <p class="text-muted mb-0">Total de Selos</p>  
                        </div>  
                    </div>  
                </div>  
            </a>  
        </div>  

        <!-- Card 2: Enviados ao Portal -->  
        <div class="col-md-6 col-lg-3 mb-3">  
            <a href="selos.php?status=enviados" class="text-decoration-none">  
                <div class="card border-0 shadow-sm h-100 hover-effect">  
                    <div class="card-body d-flex align-items-center">  
                        <div class="rounded-circle bg-success p-3 me-3">  
                            <i data-feather="check-circle" class="text-white"></i>  
                        </div>  
                        <div>  
                            <h3 class="mb-0"><?php echo $selos_enviados; ?></h3>  
                            <p class="text-muted mb-0">Enviados ao Portal</p>  
                        </div>  
                    </div>  
                </div>  
            </a>  
        </div>   

        <!-- Card 3: Pendentes de Envio -->  
        <div class="col-md-6 col-lg-3 mb-3">  
            <a href="selos.php?status=pendentes" class="text-decoration-none">  
                <div class="card border-0 shadow-sm h-100 hover-effect">  
                    <div class="card-body d-flex align-items-center">  
                        <div class="rounded-circle bg-warning p-3 me-3">  
                            <i data-feather="clock" class="text-white"></i>  
                        </div>  
                        <div>  
                            <h3 class="mb-0"><?php echo $selos_pendentes; ?></h3>  
                            <p class="text-muted mb-0">Pendentes de Envio</p>  
                        </div>  
                    </div>  
                </div>  
            </a>  
        </div>  

        <!-- Card 4: Sem Anexos -->  
        <div class="col-md-6 col-lg-3 mb-3">  
            <a href="selos.php?status=sem_anexos" class="text-decoration-none">  
                <div class="card border-0 shadow-sm h-100 hover-effect">  
                    <div class="card-body d-flex align-items-center">  
                        <div class="rounded-circle bg-danger p-3 me-3">  
                            <i data-feather="file-minus" class="text-white"></i>  
                        </div>  
                        <div>  
                            <h3 class="mb-0"><?php echo $selos_sem_anexos; ?></h3>  
                            <p class="text-muted mb-0">Sem Anexos</p>  
                        </div>  
                    </div>  
                </div>  
            </a>  
        </div>  
    </div>  

    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Últimos Selos Cadastrados</h5>
                            <div class="d-flex gap-2">  
                            <a href="selos.php" class="btn btn-sm btn-secondary">   
                                <i data-feather="file-text" class="me-1" style="width: 14px; height: 14px;"></i>      
                                Ver Todos  
                            </a>  
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#novoSeloModal">  
                                <i data-feather="plus" class="me-1" style="width: 14px; height: 14px;"></i>  
                                Novo Selo  
                            </button>  
                        </div>  
                    </div>  
                </div>  
                <div class="card-body">  
                <?php if (count($ultimos_selos) > 0): ?>  
                        <div class="table-responsive">  
                        <table class="table table-hover">  
                            <thead>  
                                <tr>  
                                    <th>Número do Selo</th>  
                                    <th>Data de Cadastro</th>  
                                    <th>Anexos</th>  
                                    <th>Downloads</th>  
                                    <th>Situação</th>  
                                    <th>Ações</th>  
                                </tr>  
                            </thead>  
                            <tbody>  
                                <?php foreach ($ultimos_selos as $selo): ?>  
                                    <tr>  
                                        <td><?php echo htmlspecialchars($selo['numero']); ?></td>  
                                        <td><?php echo date('d/m/Y H:i', strtotime($selo['data_cadastro'])); ?></td>  
                                        <td>  
                                            <span class="badge bg-info">  
                                                <i data-feather="paperclip" style="width: 14px; height: 14px;" class="me-1"></i>  
                                                <?php echo $selo['total_anexos']; ?> anexos  
                                            </span>  
                                        </td>  
                                        <td>  
                                            <span class="badge bg-<?php echo ($selo['total_downloads'] > 0) ? 'success' : 'secondary'; ?>">  
                                                <i data-feather="download" style="width: 14px; height: 14px;" class="me-1"></i>  
                                                <?php echo $selo['total_downloads']; ?> download<?php echo $selo['total_downloads'] == 1 ? '' : 's'; ?>  
                                            </span>  
                                        </td>  
                                        <td>  
                                            <?php   
                                            // Determinar a situação do selo  
                                            if ($selo['total_anexos'] == 0): ?>  
                                                <span class="badge bg-danger">  
                                                    <i data-feather="alert-circle" style="width: 14px; height: 14px;" class="me-1"></i>  
                                                    Sem anexo  
                                                </span>  
                                            <?php elseif (isset($selo['enviado_portal']) && ($selo['enviado_portal'] == 1 || $selo['enviado_portal'] == 'sim')): ?>  
                                                <span class="badge bg-success">  
                                                    <i data-feather="check-circle" style="width: 14px; height: 14px;" class="me-1"></i>  
                                                    Enviado ao Portal  
                                                </span>  
                                            <?php else: ?>  
                                                <span class="badge bg-warning text-dark">  
                                                    <i data-feather="clock" style="width: 14px; height: 14px;" class="me-1"></i>  
                                                    Pendente de envio  
                                                </span>  
                                            <?php endif; ?>  
                                        </td>  
                                        <td>  
                                            <a href="selos.php?id=<?php echo $selo['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">  
                                                <i data-feather="edit" style="width: 16px; height: 16px;"></i>  
                                            </a>  
                                            <?php if ($selo['total_anexos'] > 0): ?>  
                                                <a href="baixar_documento.php?id=<?php echo $selo['id']; ?>" class="btn btn-sm btn-outline-success" title="Baixar Documento Comprobatório">  
                                                    <i data-feather="download" style="width: 16px; height: 16px;"></i>  
                                                </a>  
                                            <?php endif; ?>  
                                            <?php if ($selo['total_anexos'] > 0 && (!isset($selo['enviado_portal']) || ($selo['enviado_portal'] != 1 && $selo['enviado_portal'] != 'sim'))): ?>  
                                                <form method="POST" action="marcar_enviado.php" class="d-inline form-marcar-enviado">  
                                                    <input type="hidden" name="selo_id" value="<?php echo $selo['id']; ?>">  
                                                    <button type="submit" class="btn btn-sm btn-outline-info" title="Marcar como Enviado ao Portal do Selo">  
                                                        <i data-feather="check-circle" style="width: 16px; height: 16px;"></i>  
                                                    </button>  
                                                </form>  
                                            <?php endif; ?>  
                                        </td>  
                                    </tr>  
                                <?php endforeach; ?>  
                            </tbody>  
                        </table>  
                        </div>  
                    <?php else: ?>  
                        <div class="text-center py-4">  
                            <i data-feather="file-text" style="width: 48px; height: 48px; opacity: 0.2;"></i>  
                            <p class="mt-2 text-muted">Você ainda não possui selos ativos cadastrados.</p>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#novoSeloModal">  
                                <i data-feather="plus" class="me-1" style="width: 14px; height: 14px;"></i>  
                                Cadastrar Novo Selo  
                            </button> 
                        </div>  
                    <?php endif; ?>  
                </div>  
            </div>  
        </div>  
    </div>
     
    <div class="row">  
        <div class="col-md-6 mb-4">  
            <div class="card border-0 shadow-sm h-100">  
                <div class="card-header bg-white">  
                    <h5 class="mb-0">Atividade Mensal</h5>  
                </div>  
                <div class="card-body">  
                    <canvas id="atividadeChart" height="200"></canvas>  
                </div>  
                <div class="card-footer bg-white border-0">  
                    <a href="relatorios.php?tipo=atividade" class="btn btn-sm btn-outline-primary">  
                        <i data-feather="bar-chart-2" class="me-1" style="width: 14px; height: 14px;"></i>  
                        Análise Detalhada  
                    </a>  
                </div>  
            </div>  
        </div>  

        <div class="col-md-6 mb-4">  
            <div class="card border-0 shadow-sm h-100">  
                <div class="card-header bg-white">  
                    <h5 class="mb-0">Estatísticas de Selos</h5>  
                </div>  
                <div class="card-body">  
                    <canvas id="selosChart" height="200"></canvas>  
                </div>  
                <div class="card-footer bg-white border-0">  
                    <a href="relatorios.php?tipo=selos" class="btn btn-sm btn-outline-primary">  
                        <i data-feather="file-text" class="me-1" style="width: 14px; height: 14px;"></i>  
                        Ver Relatório Completo  
                    </a>  
                </div>  
            </div>  
        </div> 
    </div>  

    <div class="row">  
        <div class="col-12 mb-4">  
            <div class="card border-0 shadow-sm">  
                <div class="card-header bg-white d-flex justify-content-between align-items-center">  
                    <h5 class="mb-0">Resumo de Relatórios</h5>  
                    <a href="relatorios.php" class="btn btn-sm btn-primary">  
                        <i data-feather="eye" class="me-1" style="width: 16px; height: 16px;"></i>  
                        Relatórios  
                    </a>  
                </div>  
                <div class="card-body">  
                    <div class="row">  
                        <div class="col-md-3 mb-3 mb-md-0">  
                            <div class="d-flex align-items-center p-3 border rounded h-100">  
                                <div class="rounded-circle bg-primary-light p-2 me-3">  
                                    <i data-feather="activity" style="width: 18px; height: 18px;" class="text-primary"></i>  
                                </div>  
                                <div>  
                                    <h6 class="mb-0">Taxa de Envio</h6>  
                                    <h4 class="mb-0 mt-1"><?php echo round(($selos_enviados / ($total_selos ?: 1)) * 100); ?>%</h4>  
                                    <small class="text-<?php echo ($selos_enviados / ($total_selos ?: 1)) > 0.7 ? 'success' : 'warning'; ?>">  
                                        <?php echo $selos_enviados; ?> de <?php echo $total_selos; ?> selos  
                                    </small>  
                                </div>  
                            </div>  
                        </div>  
                        <div class="col-md-3 mb-3 mb-md-0">  
                            <div class="d-flex align-items-center p-3 border rounded h-100">  
                                <div class="rounded-circle bg-success-light p-2 me-3">  
                                    <i data-feather="calendar" style="width: 18px; height: 18px;" class="text-success"></i>  
                                </div>  
                                <div>  
                                    <h6 class="mb-0">Este Mês</h6>  
                                    <?php  
                                    // Selos cadastrados no mês atual  
                                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM selos WHERE status = 'ativo' AND MONTH(data_cadastro) = MONTH(CURRENT_DATE()) AND YEAR(data_cadastro) = YEAR(CURRENT_DATE())");  
                                    $selos_mes = $stmt->fetch()['total'];  
                                    ?>  
                                    <h4 class="mb-0 mt-1"><?php echo $selos_mes; ?></h4>  
                                    <small class="text-muted">selos cadastrados</small>  
                                </div>  
                            </div>  
                        </div>  
                        <div class="col-md-3 mb-3 mb-md-0">  
                            <div class="d-flex align-items-center p-3 border rounded h-100">  
                                <div class="rounded-circle bg-warning-light p-2 me-3">  
                                    <i data-feather="alert-triangle" style="width: 18px; height: 18px;" class="text-warning"></i>  
                                </div>  
                                <div>  
                                    <h6 class="mb-0">Pendências</h6>  
                                    <h4 class="mb-0 mt-1"><?php echo $selos_pendentes + $selos_sem_anexos; ?></h4>  
                                    <small class="text-muted">itens requerem atenção</small>  
                                </div>  
                            </div>  
                        </div>  
                        <div class="col-md-3">  
                            <div class="d-flex align-items-center p-3 border rounded h-100">  
                                <div class="rounded-circle bg-info-light p-2 me-3">  
                                    <i data-feather="download-cloud" style="width: 18px; height: 18px;" class="text-info"></i>  
                                </div>  
                                <div>  
                                    <h6 class="mb-0">Downloads</h6>  
                                    <h4 class="mb-0 mt-1"><?php echo $total_downloads; ?></h4>  
                                    <small class="text-muted">documentos baixados</small>  
                                </div>  
                            </div>  
                        </div>  
                    </div>  
                </div>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- Modal para cadastrar novo selo -->  
<div class="modal fade" id="novoSeloModal" tabindex="-1" aria-labelledby="novoSeloModalLabel" aria-hidden="true">  
    <div class="modal-dialog">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="novoSeloModalLabel">Cadastrar Novo Selo</h5>  
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>  
            </div>  
            <form method="POST" action="selos.php">  
                <div class="modal-body">  
                    <div class="mb-3">  
                        <label for="numero_selo" class="form-label">Número do Selo Eletrônico</label>  
                        <input type="text" class="form-control" id="numero_selo" name="numero_selo" required>  
                        <div class="form-text">Digite o número do selo eletrônico conforme consta no documento oficial.</div>  
                    </div>  
                </div>  
                <div class="modal-footer">  
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>  
                    <button type="submit" name="cadastrar_selo" class="btn btn-primary">Cadastrar</button>  
                </div>  
            </form>  
        </div>  
    </div>  
</div>  

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/feather-icons"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>  
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>  
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>  
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>  
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>  
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>  
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
<script>  
document.addEventListener("DOMContentLoaded", function() {  
    // Dados para o gráfico de distribuição de selos  
    const ctxSelos = document.getElementById('selosChart').getContext('2d');  
    const selosChart = new Chart(ctxSelos, {  
        type: 'doughnut',  
        data: {  
            labels: ['Enviados ao Portal', 'Pendentes de Envio', 'Sem Anexos'],  
            datasets: [{  
                data: [<?php echo $selos_enviados; ?>, <?php echo $selos_pendentes; ?>, <?php echo $selos_sem_anexos; ?>],  
                backgroundColor: [  
                    'rgba(40, 167, 69, 0.8)',  // Verde para enviados  
                    'rgba(255, 193, 7, 0.8)',  // Amarelo para pendentes  
                    'rgba(220, 53, 69, 0.8)'   // Vermelho para sem anexos  
                ],  
                borderColor: [  
                    'rgba(40, 167, 69, 1)',  
                    'rgba(255, 193, 7, 1)',  
                    'rgba(220, 53, 69, 1)'  
                ],  
                borderWidth: 1  
            }]  
        },  
        options: {  
            responsive: true,  
            plugins: {  
                legend: {  
                    position: 'bottom',  
                },  
                tooltip: {  
                    callbacks: {  
                        label: function(context) {  
                            const total = <?php echo ($selos_enviados + $selos_pendentes + $selos_sem_anexos) ?: 1; ?>;  
                            const value = context.raw;  
                            const percentage = Math.round((value / total) * 100);  
                            return context.label + ': ' + value + ' (' + percentage + '%)';  
                        }  
                    }  
                }  
            },  
            cutout: '65%'  
        }  
    });  

    // Gráfico de Atividade Mensal usando dados reais do banco  
    const ctxAtividade = document.getElementById('atividadeChart').getContext('2d');  
    const atividadeChart = new Chart(ctxAtividade, {  
        type: 'bar',  
        data: {  
            labels: <?php echo json_encode($labels_meses); ?>,  
            datasets: [{  
                label: 'Cadastros',  
                data: <?php echo json_encode($dados_atividade['cadastros']); ?>,  
                backgroundColor: 'rgba(13, 110, 253, 0.6)',  
                borderColor: 'rgba(13, 110, 253, 1)',  
                borderWidth: 1  
            }, {  
                label: 'Envios ao Portal',  
                data: <?php echo json_encode($dados_atividade['envios']); ?>,  
                backgroundColor: 'rgba(40, 167, 69, 0.6)',  
                borderColor: 'rgba(40, 167, 69, 1)',  
                borderWidth: 1  
            }]  
        },  
        options: {  
            responsive: true,  
            plugins: {  
                legend: {  
                    position: 'bottom',  
                },  
                tooltip: {  
                    callbacks: {  
                        title: function(tooltipItems) {  
                            // Formatar o título do tooltip para mostrar mês e ano completos  
                            const monthIndex = tooltipItems[0].dataIndex;  
                            const months = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',   
                                          'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];  
                            
                            // Obter o mês a partir do label abreviado  
                            const monthAbbr = tooltipItems[0].label;  
                            // Obter o ano do mês correspondente no PHP  
                            const yearMonthData = <?php echo json_encode(array_map(function($m) {   
                                return ['year' => date('Y', strtotime($m)), 'month' => date('n', strtotime($m))-1];   
                            }, $meses_completos)); ?>;  
                            
                            const yearMonth = yearMonthData[monthIndex];  
                            return months[yearMonth.month] + '/' + yearMonth.year;  
                        },  
                        label: function(context) {  
                            let label = context.dataset.label || '';  
                            if (label) {  
                                label += ': ';  
                            }  
                            label += context.parsed.y;  
                            return label;  
                        }  
                    }  
                }  
            },  
            scales: {  
                y: {  
                    beginAtZero: true,  
                    ticks: {  
                        precision: 0 // Garante que os valores no eixo Y sejam inteiros  
                    }  
                }  
            }  
        }  
    });  
});  

$('.form-marcar-enviado').submit(function(e) {
    e.preventDefault();
    const form = $(this);

    Swal.fire({
        title: 'Confirmar envio?',
        text: 'Deseja marcar este documento como enviado ao Portal do Selo?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, marcar como enviado',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('marcar_enviado.php', form.serialize(), function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Marcado como enviado!',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', response.message || 'Não foi possível marcar como enviado.', 'error');
                }
            }, 'json').fail(() => {
                Swal.fire('Erro', 'Falha na comunicação com o servidor.', 'error');
            });
        }
    });
});

$('#formMarcarEnviado').submit(function(e) {
    e.preventDefault();
    const form = $(this);

    Swal.fire({
        title: 'Confirmar envio?',
        text: 'Deseja marcar este documento como enviado ao Portal do Selo?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, marcar como enviado',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('marcar_enviado.php', form.serialize(), function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Marcado como enviado!',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', response.message, 'error');
                }
            }, 'json').fail(() => {
                Swal.fire('Erro', 'Erro na comunicação com o servidor.', 'error');
            });
        }
    });
});
</script> 

<?php include 'includes/footer.php'; ?>