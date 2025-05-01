<?php  
date_default_timezone_set('America/Sao_Paulo'); 
require_once 'includes/auth_check.php';  
require_once 'includes/db_connection.php';  

// Definir o tipo de relatório (padrão: geral)  
$tipo_relatorio = $_GET['tipo'] ?? 'geral';  
$periodo = $_GET['periodo'] ?? 'mes';  
$formato = $_GET['formato'] ?? '';  
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');   
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');   

// Verificar se é uma requisição de exportação  
if (!empty($formato)) {
    $hoje = date('Ymd');
    $nome_arquivo = "relatorio_selos_{$tipo_relatorio}_{$hoje}";

    if ($formato === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename={$nome_arquivo}.csv");
        $output = fopen('php://output', 'w');

        // Cabeçalhos CSV
        if (!empty($dados_relatorio)) {
            fputcsv($output, array_keys($dados_relatorio[0]));
            foreach ($dados_relatorio as $linha) {
                fputcsv($output, $linha);
            }
        }

        fclose($output);
        exit; // <- ESSENCIAL!
    }

    if ($formato === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment; filename={$nome_arquivo}.xls");

        echo "<table border='1'>";
        if (!empty($dados_relatorio)) {
            echo "<tr>";
            foreach (array_keys($dados_relatorio[0]) as $cabecalho) {
                echo "<th>" . htmlspecialchars($cabecalho) . "</th>";
            }
            echo "</tr>";

            foreach ($dados_relatorio as $linha) {
                echo "<tr>";
                foreach ($linha as $valor) {
                    echo "<td>" . htmlspecialchars($valor) . "</td>";
                }
                echo "</tr>";
            }
        }
        echo "</table>";
        exit; // <- ESSENCIAL!
    }

    if ($formato === 'pdf') {
        header('Content-Type: application/pdf');
        header("Content-Disposition: attachment; filename={$nome_arquivo}.pdf");
        echo "Simulação de PDF"; // Substitua com geração real depois
        exit; // <- ESSENCIAL!
    }
}


// Verificar a estrutura da tabela downloads_selo para identificar a coluna de data  
function obterColunaDataDownload($pdo) {  
    // Lista de possíveis nomes de coluna para data de download  
    $possiveis_colunas = ['data_download', 'data_hora', 'created_at', 'data', 'data_cadastro'];  
    
    // Obter as colunas da tabela  
    $stmt = $pdo->query("SHOW COLUMNS FROM downloads_selo");  
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);  
    
    // Verificar qual coluna existe  
    foreach ($possiveis_colunas as $coluna) {  
        if (in_array($coluna, $colunas)) {  
            return $coluna;  
        }  
    }  
    
    // Se nenhuma das colunas esperadas existir, retornar a primeira coluna (como fallback)  
    // Isso pode não ser ideal, mas evita erros imediatos  
    return $colunas[0] ?? 'id';  
}  

// Obter a coluna correta para data de download  
$coluna_data_download = obterColunaDataDownload($pdo);  

// Função para obter dados com base no tipo de relatório  
function obterDadosRelatorio($pdo, $tipo, $data_inicio, $data_fim, $coluna_data_download) {  
    $params = [  
        ':data_inicio' => $data_inicio . ' 00:00:00',  
        ':data_fim' => $data_fim . ' 23:59:59'  
    ];  
    
    if ($tipo === 'selos') {  
        // Relatório detalhado de selos  
        $sql = "  
            SELECT   
                s.id,   
                s.numero,   
                s.data_cadastro,   
                CASE   
                    WHEN s.enviado_portal = 'sim' THEN 'Enviado'   
                    ELSE 'Pendente'   
                END as status_envio,  
                (SELECT COUNT(*) FROM anexos a WHERE a.selo_id = s.id AND a.status = 'ativo') as total_anexos,  
                (SELECT COUNT(*) FROM downloads_selo d WHERE d.selo_id = s.id) as total_downloads,  
                u.nome as cadastrado_por  
            FROM selos s  
            JOIN usuarios u ON s.usuario_id = u.id  
            WHERE s.status = 'ativo'  
            AND s.data_cadastro BETWEEN :data_inicio AND :data_fim  
            ORDER BY s.data_cadastro DESC  
        ";  
    } elseif ($tipo === 'atividade') {  
        // Relatório de atividade (cadastros, envios, downloads)  
        $sql = "  
            SELECT   
                DATE(s.data_cadastro) as data,  
                COUNT(DISTINCT s.id) as total_cadastros,  
                SUM(CASE WHEN s.enviado_portal = 'sim' THEN 1 ELSE 0 END) as total_enviados,  
                (  
                    SELECT COUNT(*)   
                    FROM downloads_selo d   
                    WHERE DATE(d.{$coluna_data_download}) = DATE(s.data_cadastro)  
                ) as total_downloads  
            FROM selos s  
            WHERE s.status = 'ativo'  
            AND s.data_cadastro BETWEEN :data_inicio AND :data_fim  
            GROUP BY DATE(s.data_cadastro)  
            ORDER BY data DESC  
        ";  
    } elseif ($tipo === 'pendencias') {  
        // Relatório de pendências  
        $sql = "  
            SELECT   
                s.id,   
                s.numero,   
                s.data_cadastro,  
                CASE   
                    WHEN (SELECT COUNT(*) FROM anexos a WHERE a.selo_id = s.id AND a.status = 'ativo') = 0 THEN 'Sem Anexos'  
                    WHEN s.enviado_portal IS NULL OR s.enviado_portal != 'sim' THEN 'Não Enviado ao Portal'  
                    ELSE 'OK'  
                END as situacao,  
                u.nome as cadastrado_por  
            FROM selos s  
            JOIN usuarios u ON s.usuario_id = u.id  
            WHERE s.status = 'ativo'  
            AND (  
                s.enviado_portal IS NULL   
                OR s.enviado_portal != 'sim'  
                OR NOT EXISTS (SELECT 1 FROM anexos a WHERE a.selo_id = s.id AND a.status = 'ativo')  
            )  
            AND s.data_cadastro BETWEEN :data_inicio AND :data_fim  
            ORDER BY s.data_cadastro DESC  
        ";  
    } else {  
        // Relatório geral (resumo)  
        // Corrigido para usar a coluna correta de data de download  
        $sql = "  
            SELECT   
                DATE_FORMAT(s.data_cadastro, '%Y-%m') as mes,  
                COUNT(DISTINCT s.id) as total_cadastros,  
                SUM(CASE WHEN s.enviado_portal = 'sim' THEN 1 ELSE 0 END) as total_enviados,  
                SUM(CASE WHEN (SELECT COUNT(*) FROM anexos a WHERE a.selo_id = s.id AND a.status = 'ativo') = 0 THEN 1 ELSE 0 END) as sem_anexos,  
                (  
                    SELECT COUNT(*)   
                    FROM downloads_selo d   
                    WHERE DATE_FORMAT(d.{$coluna_data_download}, '%Y-%m') = DATE_FORMAT(s.data_cadastro, '%Y-%m')  
                ) as total_downloads  
            FROM selos s  
            WHERE s.status = 'ativo'  
            AND s.data_cadastro BETWEEN :data_inicio AND :data_fim  
            GROUP BY DATE_FORMAT(s.data_cadastro, '%Y-%m')  
            ORDER BY mes DESC  
        ";  
    }  
    
    $stmt = $pdo->prepare($sql);  
    $stmt->execute($params);  
    return $stmt->fetchAll(PDO::FETCH_ASSOC);  
}  

// Obter dados do relatório  
$dados_relatorio = obterDadosRelatorio($pdo, $tipo_relatorio, $data_inicio, $data_fim, $coluna_data_download);  

// Dados para os gráficos  
$dados_grafico = [];  
$labels_grafico = [];  

// Processar dados para o gráfico conforme o tipo de relatório  
if ($tipo_relatorio === 'selos') {  
    // Contagem de status para o gráfico de pizza  
    $contagem_status = [  
        'Enviado' => 0,  
        'Pendente' => 0  
    ];  
    
    foreach ($dados_relatorio as $selo) {  
        $contagem_status[$selo['status_envio']]++;  
    }  
    
    $dados_grafico = array_values($contagem_status);  
    $labels_grafico = array_keys($contagem_status);  
} elseif ($tipo_relatorio === 'atividade') {  
    // Dados para o gráfico de linha/barra  
    $dados_cadastros = [];  
    $dados_enviados = [];  
    
    foreach ($dados_relatorio as $atividade) {  
        $labels_grafico[] = date('d/m', strtotime($atividade['data']));  
        $dados_cadastros[] = $atividade['total_cadastros'];  
        $dados_enviados[] = $atividade['total_enviados'];  
    }  
    
    // Inverter arrays para ordem cronológica  
    $labels_grafico = array_reverse($labels_grafico);  
    $dados_cadastros = array_reverse($dados_cadastros);  
    $dados_enviados = array_reverse($dados_enviados);  
    
    $dados_grafico = [  
        'cadastros' => $dados_cadastros,  
        'enviados' => $dados_enviados  
    ];  
}  

// Exportação para CSV  
if (!empty($formato) && $formato === 'csv' && isset($output)) {  
    // Cabeçalhos CSV  
    $cabecalhos = array_keys($dados_relatorio[0] ?? []);  
    fputcsv($output, $cabecalhos);  
    
    // Dados  
    foreach ($dados_relatorio as $linha) {  
        fputcsv($output, $linha);  
    }  
    
    fclose($output);  
    exit;  
}  

// Exportação para Excel simples (HTML)  
if (!empty($formato) && $formato === 'excel') {  
    echo "<table border='1'>";  
    
    // Cabeçalhos  
    if (!empty($dados_relatorio)) {  
        echo "<tr>";  
        foreach (array_keys($dados_relatorio[0]) as $cabecalho) {  
            echo "<th>" . htmlspecialchars($cabecalho) . "</th>";  
        }  
        echo "</tr>";  
        
        // Dados  
        foreach ($dados_relatorio as $linha) {  
            echo "<tr>";  
            foreach ($linha as $valor) {  
                echo "<td>" . htmlspecialchars($valor) . "</td>";  
            }  
            echo "</tr>";  
        }  
    }  
    
    echo "</table>";  
    exit;  
}  

// Incluir o cabeçalho HTML  
include 'includes/header.php';  
?>

<div class="container-fluid py-4">  
    <div class="row mb-4">  
        <div class="col-12">  
            <div class="d-flex justify-content-between align-items-center">  
                <div>  
                    <h1 class="fw-bold">Relatórios</h1>  
                    <p class="text-muted">  
                        <?php   
                        $titulos = [  
                            'geral' => 'Relatório Geral de Selos',  
                            'selos' => 'Relatório Detalhado de Selos',  
                            'atividade' => 'Relatório de Atividade',  
                            'pendencias' => 'Relatório de Pendências'  
                        ];  
                        echo $titulos[$tipo_relatorio] ?? 'Relatórios e Estatísticas';   
                        ?>  
                    </p>  
                </div>  
                <div class="d-flex">  
                    <a href="painel.php" class="btn btn-sm btn-outline-secondary me-2">  
                        <i data-feather="arrow-left" class="me-1" style="width: 16px; height: 16px;"></i>  
                        Voltar ao Painel  
                    </a>  
                    <!-- <div class="dropdown">  
                        <button class="btn btn-sm btn-primary dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">  
                            <i data-feather="download" class="me-1" style="width: 16px; height: 16px;"></i>  
                            Exportar  
                        </button>  
                        <ul class="dropdown-menu" aria-labelledby="exportDropdown">  
                            <li>  
                                <a class="dropdown-item" href="?tipo=<?php echo $tipo_relatorio; ?>&periodo=<?php echo $periodo; ?>&data_inicio=<?php echo $data_inicio; ?>&data_fim=<?php echo $data_fim; ?>&formato=csv">  
                                    <i data-feather="file-text" class="me-2" style="width: 14px; height: 14px;"></i>  
                                    Exportar CSV  
                                </a>  
                            </li>  
                            <li>  
                                <a class="dropdown-item" href="?tipo=<?php echo $tipo_relatorio; ?>&periodo=<?php echo $periodo; ?>&data_inicio=<?php echo $data_inicio; ?>&data_fim=<?php echo $data_fim; ?>&formato=excel">  
                                    <i data-feather="file" class="me-2" style="width: 14px; height: 14px;"></i>  
                                    Exportar Excel  
                                </a>  
                            </li>  
                            <li>  
                                <a class="dropdown-item" href="?tipo=<?php echo $tipo_relatorio; ?>&periodo=<?php echo $periodo; ?>&data_inicio=<?php echo $data_inicio; ?>&data_fim=<?php echo $data_fim; ?>&formato=pdf">  
                                    <i data-feather="file" class="me-2" style="width: 14px; height: 14px;"></i>  
                                    Exportar PDF  
                                </a>  
                            </li>  
                        </ul>  
                    </div>   -->
                </div>  
            </div>  
        </div>  
    </div>  

    <!-- Filtros e Opções -->  
    <div class="row mb-4">  
        <div class="col-12">  
            <div class="card border-0 shadow-sm">  
                <div class="card-body">  
                    <form action="" method="GET" class="row align-items-end g-3">  
                        <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo_relatorio); ?>">  
                        
                        <div class="col-md-3">  
                            <label for="periodo" class="form-label">Tipo de Relatório</label>  
                            <select class="form-select" id="tipo_relatorio" name="tipo" onchange="this.form.submit()">  
                                <option value="geral" <?php echo $tipo_relatorio === 'geral' ? 'selected' : ''; ?>>Relatório Geral</option>  
                                <option value="selos" <?php echo $tipo_relatorio === 'selos' ? 'selected' : ''; ?>>Relatório de Selos</option>  
                                <option value="atividade" <?php echo $tipo_relatorio === 'atividade' ? 'selected' : ''; ?>>Relatório de Atividade</option>  
                                <option value="pendencias" <?php echo $tipo_relatorio === 'pendencias' ? 'selected' : ''; ?>>Relatório de Pendências</option>  
                            </select>  
                        </div>  
                        
                        <div class="col-md-3">  
                            <label for="periodo" class="form-label">Período</label>  
                            <select class="form-select" id="periodo" name="periodo" onchange="toggleDateInputs()">  
                                <option value="mes" <?php echo $periodo === 'mes' ? 'selected' : ''; ?>>Mês Atual</option>  
                                <option value="semana" <?php echo $periodo === 'semana' ? 'selected' : ''; ?>>Última Semana</option>  
                                <option value="trimestre" <?php echo $periodo === 'trimestre' ? 'selected' : ''; ?>>Último Trimestre</option>  
                                <option value="ano" <?php echo $periodo === 'ano' ? 'selected' : ''; ?>>Ano Atual</option>  
                                <option value="personalizado" <?php echo $periodo === 'personalizado' ? 'selected' : ''; ?>>Personalizado</option>  
                            </select>  
                        </div>  
                        
                        <div class="col-md-2 data-input" style="display: <?php echo $periodo === 'personalizado' ? 'block' : 'none'; ?>">  
                            <label for="data_inicio" class="form-label">Data Inicial</label>  
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($data_inicio); ?>">  
                        </div>  
                        
                        <div class="col-md-2 data-input" style="display: <?php echo $periodo === 'personalizado' ? 'block' : 'none'; ?>">  
                            <label for="data_fim" class="form-label">Data Final</label>  
                            <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>">  
                        </div>  
                        
                        <div class="col-md-2">  
                            <button type="submit" class="btn btn-primary w-100">  
                                <i data-feather="filter" class="me-1" style="width: 16px; height: 16px;"></i>  
                                Filtrar  
                            </button>  
                        </div>  
                    </form>  
                </div>  
            </div>  
        </div>  
    </div>  

    <!-- Gráficos -->  
    <?php if (in_array($tipo_relatorio, ['selos', 'atividade'])): ?>  
    <div class="row mb-4">  
        <div class="col-md-6 mb-3 mb-md-0">  
            <div class="card border-0 shadow-sm h-100">  
                <div class="card-header bg-white">  
                    <h5 class="mb-0">  
                        <?php echo $tipo_relatorio === 'selos' ? 'Distribuição de Selos' : 'Atividade do Período'; ?>  
                    </h5>  
                </div>  
                <div class="card-body d-flex justify-content-center align-items-center">  
                    <canvas id="mainChart" style="max-height: 300px;"></canvas>  
                </div>  
            </div>  
        </div>  
        
        <div class="col-md-6">  
            <div class="card border-0 shadow-sm h-100">  
                <div class="card-header bg-white">  
                    <h5 class="mb-0">Estatísticas do Período</h5>  
                </div>  
                <div class="card-body">  
                    <div class="row">  
                        <?php if ($tipo_relatorio === 'selos'): ?>  
                            <?php   
                            $total_selos = count($dados_relatorio);  
                            $selos_com_anexos = 0;  
                            $selos_sem_anexos = 0;  
                            
                            foreach ($dados_relatorio as $selo) {  
                                if ($selo['total_anexos'] > 0) {  
                                    $selos_com_anexos++;  
                                } else {  
                                    $selos_sem_anexos++;  
                                }  
                            }  
                            
                            $selos_enviados = array_sum(array_map(function($selo) {  
                                return $selo['status_envio'] === 'Enviado' ? 1 : 0;  
                            }, $dados_relatorio));  
                            
                            $selos_pendentes = $total_selos - $selos_enviados;  
                            $total_downloads = array_sum(array_column($dados_relatorio, 'total_downloads'));  
                            ?>  
                            
                            <div class="col-6 mb-3">  
                                <div class="border rounded p-3 h-100">  
                                    <h6>Total de Selos</h6>  
                                    <h2 class="fw-bold"><?php echo $total_selos; ?></h2>  
                                    <span class="badge bg-primary">Período Selecionado</span>  
                                </div>  
                            </div>  
                            
                            <div class="col-6 mb-3">  
                                <div class="border rounded p-3 h-100">  
                                    <h6>Taxa de Envio</h6>  
                                    <h2 class="fw-bold"><?php echo $total_selos > 0 ? round(($selos_enviados / $total_selos) * 100) : 0; ?>%</h2>  
                                    <span class="badge bg-<?php echo $total_selos > 0 && ($selos_enviados / $total_selos) > 0.7 ? 'success' : 'warning'; ?>">  
                                        <?php echo $selos_enviados; ?> de <?php echo $total_selos; ?> selos  
                                    </span>  
                                </div>  
                            </div>  
                            
                            <div class="col-6">  
                                <div class="border rounded p-3 h-100">  
                                    <h6>Selos com Anexos</h6>  
                                    <h2 class="fw-bold"><?php echo $selos_com_anexos; ?></h2>  
                                    <div class="progress mt-2" style="height: 8px;">  
                                        <div class="progress-bar bg-success" role="progressbar"   
                                             style="width: <?php echo $total_selos > 0 ? ($selos_com_anexos / $total_selos) * 100 : 0; ?>%"   
                                             aria-valuenow="<?php echo $selos_com_anexos; ?>"   
                                             aria-valuemin="0"   
                                             aria-valuemax="<?php echo $total_selos; ?>">  
                                        </div>  
                                    </div>  
                                </div>  
                            </div>  
                            
                            <div class="col-6">  
                                <div class="border rounded p-3 h-100">  
                                    <h6>Total de Downloads</h6>  
                                    <h2 class="fw-bold"><?php echo $total_downloads; ?></h2>  
                                    <small class="text-muted">  
                                        <?php echo $total_selos > 0 ? round($total_downloads / $total_selos, 1) : 0; ?> downloads por selo  
                                    </small>  
                                </div>  
                            </div>  
                            
                        <?php elseif ($tipo_relatorio === 'atividade'): ?>  
                            <?php   
                            $total_cadastros = isset($dados_grafico['cadastros']) ? array_sum($dados_grafico['cadastros']) : 0;  
                            $total_enviados = isset($dados_grafico['enviados']) ? array_sum($dados_grafico['enviados']) : 0;  
                            $total_dias = count($labels_grafico);  
                            
                            // Calcular médias  
                            $media_cadastros = $total_dias > 0 ? round($total_cadastros / $total_dias, 1) : 0;  
                            $media_envios = $total_dias > 0 ? round($total_enviados / $total_dias, 1) : 0;  
                            
                            // Calcular day-over-day  
                            if ($total_dias >= 2) {  
                                $dod_cadastros = $dados_grafico['cadastros'][count($dados_grafico['cadastros'])-1] -   
                                                $dados_grafico['cadastros'][count($dados_grafico['cadastros'])-2];  
                                
                                $dod_enviados = $dados_grafico['enviados'][count($dados_grafico['enviados'])-1] -   
                                              $dados_grafico['enviados'][count($dados_grafico['enviados'])-2];  
                            } else {  
                                $dod_cadastros = 0;  
                                $dod_enviados = 0;  
                            }  
                            ?>  
                            
                            <div class="col-6 mb-3">  
                                <div class="border rounded p-3 h-100">  
                                    <h6>Total de Cadastros</h6>  
                                    <div class="d-flex align-items-end">  
                                        <h2 class="fw-bold mb-0"><?php echo $total_cadastros; ?></h2>  
                                        <span class="ms-2 <?php echo $dod_cadastros >= 0 ? 'text-success' : 'text-danger'; ?>">  
                                            <i data-feather="<?php echo $dod_cadastros >= 0 ? 'arrow-up' : 'arrow-down'; ?>"   
                                               style="width: 16px; height: 16px;"></i>  
                                            <?php echo abs($dod_cadastros); ?>  
                                        </span>  
                                    </div>  
                                    <small class="text-muted">Média: <?php echo $media_cadastros; ?> por dia</small>  
                                </div>  
                            </div>  
                            
                            <div class="col-6 mb-3">  
                                <div class="border rounded p-3 h-100">  
                                    <h6>Total de Envios</h6>  
                                    <div class="d-flex align-items-end">  
                                        <h2 class="fw-bold mb-0"><?php echo $total_enviados; ?></h2>  
                                        <span class="ms-2 <?php echo $dod_enviados >= 0 ? 'text-success' : 'text-danger'; ?>">  
                                            <i data-feather="<?php echo $dod_enviados >= 0 ? 'arrow-up' : 'arrow-down'; ?>"   
                                               style="width: 16px; height: 16px;"></i>  
                                            <?php echo abs($dod_enviados); ?>  
                                        </span>  
                                    </div>  
                                    <small class="text-muted">Média: <?php echo $media_envios; ?> por dia</small>  
                                </div>  
                            </div>  
                            
                            <div class="col-6">  
                                <div class="border rounded p-3 h-100">  
                                    <h6>Taxa de Conversão</h6>  
                                    <h2 class="fw-bold">  
                                        <?php echo $total_cadastros > 0 ? round(($total_enviados / $total_cadastros) * 100) : 0; ?>%  
                                    </h2>  
                                    <div class="progress mt-2" style="height: 8px;">  
                                        <div class="progress-bar bg-success" role="progressbar"   
                                             style="width: <?php echo $total_cadastros > 0 ? ($total_enviados / $total_cadastros) * 100 : 0; ?>%"   
                                             aria-valuenow="<?php echo $total_enviados; ?>"   
                                             aria-valuemin="0"   
                                             aria-valuemax="<?php echo $total_cadastros; ?>">  
                                        </div>  
                                    </div>  
                                </div>  
                            </div>  
                            
                            <div class="col-6">  
                                <div class="border rounded p-3 h-100">  
                                    <h6>Período</h6>  
                                    <p class="mb-0">  
                                        <i data-feather="calendar" style="width: 16px; height: 16px;" class="me-1"></i>  
                                        <?php   
                                        // Formatar datas de início e fim  
                                        $data_inicio_fmt = date('d/m/Y', strtotime($data_inicio));  
                                        $data_fim_fmt = date('d/m/Y', strtotime($data_fim));  
                                        echo "{$data_inicio_fmt} até {$data_fim_fmt}";   
                                        ?>  
                                    </p>  
                                    <small class="text-muted">Total: <?php echo $total_dias; ?> dias</small>  
                                </div>  
                            </div>  
                        <?php endif; ?>  
                    </div>  
                </div>  
            </div>  
        </div>  
    </div>  
    <?php endif; ?>  

    <!-- Tabela de Resultados -->  
    <div class="row">  
        <div class="col-12">  
            <div class="card border-0 shadow-sm">  
                <div class="card-header bg-white d-flex justify-content-between align-items-center">  
                    <h5 class="mb-0">Resultados do Relatório</h5>  
                    <span class="badge bg-primary"><?php echo count($dados_relatorio); ?> registros encontrados</span>  
                </div>  
                <div class="card-body">  
                    <?php if (!empty($dados_relatorio)): ?>  
                        <div class="table-responsive">  
                            <table id="tabelaResultados" class="table table-striped table-bordered dt-responsive nowrap" style="zoom:90%">   
                                <thead>  
                                    <tr>  
                                        <?php foreach (array_keys($dados_relatorio[0]) as $cabecalho): ?>  
                                            <?php if (($tipo_relatorio === 'pendencias' || $tipo_relatorio === 'selos') && $cabecalho === 'id'): ?>  
                                                <th>Ações</th>  
                                            <?php else: ?>  
                                                <th><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $cabecalho))); ?></th>  
                                            <?php endif; ?>  
                                        <?php endforeach; ?>  
                                    </tr>  
                                </thead>  
                                <tbody>  
                                    <?php foreach ($dados_relatorio as $linha): ?>  
                                        <tr>  
                                            <?php foreach ($linha as $chave => $valor): ?>  
                                                <td>  
                                                    <?php  
                                                    // Substituir ID por botão "Ver" no relatório de pendências e selos  
                                                    if (($tipo_relatorio === 'pendencias' || $tipo_relatorio === 'selos') && $chave === 'id') {  
                                                        echo "<a href='selos.php?id={$valor}' class='btn btn-sm btn-primary' title='Ver detalhes'>  
                                                                <i data-feather='eye' class='icon-sm'></i>  
                                                            </a>";  
                                                    }  
                                                    // Formatação específica para certos tipos de dados  
                                                    elseif ($chave === 'data_cadastro' || $chave === 'data' || $chave === 'mes') {  
                                                        if (strpos($valor, '-') !== false) {  
                                                            if (strlen($valor) > 10) {  
                                                                // Data e hora completa  
                                                                echo date('d/m/Y H:i', strtotime($valor));  
                                                            } elseif (strlen($valor) === 7) {  
                                                                // Apenas mês e ano (YYYY-MM)  
                                                                if ($tipo_relatorio === 'geral') {  
                                                                    // Mostrar mês em português para relatório geral  
                                                                    $meses_pt = [  
                                                                        '01' => 'Janeiro',  
                                                                        '02' => 'Fevereiro',  
                                                                        '03' => 'Março',  
                                                                        '04' => 'Abril',  
                                                                        '05' => 'Maio',  
                                                                        '06' => 'Junho',  
                                                                        '07' => 'Julho',  
                                                                        '08' => 'Agosto',  
                                                                        '09' => 'Setembro',  
                                                                        '10' => 'Outubro',  
                                                                        '11' => 'Novembro',  
                                                                        '12' => 'Dezembro'  
                                                                    ];  
                                                                    $partes = explode('-', $valor);  
                                                                    $ano = $partes[0];  
                                                                    $mes = $partes[1];  
                                                                    echo $meses_pt[$mes] . '/' . $ano;  
                                                                } else {  
                                                                    echo date('M/Y', strtotime($valor . '-01'));  
                                                                }  
                                                            } else {  
                                                                // Apenas data  
                                                                echo date('d/m/Y', strtotime($valor));  
                                                            }  
                                                        } else {  
                                                            echo htmlspecialchars($valor);  
                                                        }  
                                                    }   
                                                    // Status com cores  
                                                    elseif ($chave === 'status_envio' || $chave === 'situacao') {  
                                                        $badge_class = 'bg-secondary';  
                                                        
                                                        if ($valor === 'Enviado' || $valor === 'OK') {  
                                                            $badge_class = 'bg-success';  
                                                        } elseif ($valor === 'Pendente' || $valor === 'Não Enviado ao Portal') {  
                                                            $badge_class = 'bg-warning text-dark';  
                                                        } elseif ($valor === 'Sem Anexos') {  
                                                            $badge_class = 'bg-danger';  
                                                        }  
                                                        
                                                        echo "<span class='badge {$badge_class}'>{$valor}</span>";  
                                                    }  
                                                    // IDs com link (para outros relatórios que não sejam de pendências ou selos)  
                                                    elseif ($chave === 'id' && $tipo_relatorio !== 'pendencias' && $tipo_relatorio !== 'selos') {  
                                                        echo "<a href='selos.php?id={$valor}' class='text-primary'>{$valor}</a>";  
                                                    }  
                                                    // Números de selo com formatação  
                                                    elseif ($chave === 'numero') {  
                                                        echo "<strong>" . htmlspecialchars($valor) . "</strong>";  
                                                    }  
                                                    // Valores percentuais  
                                                    elseif (strpos($chave, 'porcentagem') !== false || strpos($chave, 'taxa') !== false) {  
                                                        echo htmlspecialchars($valor) . '%';  
                                                    }  
                                                    // Valores numéricos  
                                                    elseif (is_numeric($valor) && strpos($chave, 'total') !== false) {  
                                                        echo "<span class='fw-bold'>" . number_format($valor, 0, ',', '.') . "</span>";  
                                                    }  
                                                    // Outros valores  
                                                    else {  
                                                        echo htmlspecialchars($valor);  
                                                    }  
                                                    ?>  
                                                </td>  
                                            <?php endforeach; ?>  
                                        </tr>  
                                    <?php endforeach; ?>  
                                </tbody>  
                            </table>  
                        </div>  
                    <?php else: ?>  
                        <div class="text-center py-5">  
                            <i data-feather="inbox" style="width: 48px; height: 48px;" class="text-muted mb-3"></i>  
                            <h4>Nenhum resultado encontrado</h4>  
                            <p class="text-muted">Tente ajustar os filtros ou selecionar outro período.</p>  
                        </div>  
                    <?php endif; ?>  
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
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>  
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>  
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>  
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>  
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>  
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>  
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>  
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>  
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>  
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>  
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>  
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>  
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>  
<script src="https://unpkg.com/feather-icons"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar Feather icons
    if (typeof feather !== 'undefined') {
        feather.replace();
    }

    // Inicializar DataTables
    function initializeDataTableRelatorios() {
        console.log('Tentando inicializar DataTable...');

        const tabela = $('#tabelaResultados');
        if (tabela.length === 0 || typeof $.fn.DataTable === 'undefined') {
            console.warn('Tabela não encontrada ou DataTable não carregado.');
            return;
        }

        try {
            tabela.DataTable({
                responsive: true,
                pageLength: 10,
                lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Todos"]],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                },
                dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>' +
                    '<"row"<"col-sm-12"tr>>' +
                    '<"row"<"col-sm-5"i><"col-sm-7"p>>',
                order: [],
                stateSave: true,
                drawCallback: function() {
                    feather.replace();
                }
            });
            console.log('DataTable inicializado com sucesso.');
        } catch (e) {
            console.error('Erro ao inicializar DataTable:', e);
        }
    }

    document.addEventListener('DOMContentLoaded', initializeDataTableRelatorios);

    // Função para alternar datas
    window.toggleDateInputs = function () {
        const periodo = document.getElementById('periodo').value;
        const dataInputs = document.querySelectorAll('.data-input');
        dataInputs.forEach(input => {
            input.style.display = (periodo === 'personalizado') ? 'block' : 'none';
        });
        if (periodo !== 'personalizado') {
            document.querySelector('form').submit();
        }
    };

    // Gráficos
    <?php if (in_array($tipo_relatorio, ['selos', 'atividade']) && !empty($labels_grafico)): ?>
    const ctx = document.getElementById('mainChart').getContext('2d');
    <?php if ($tipo_relatorio === 'selos'): ?>
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($labels_grafico); ?>,
            datasets: [{
                data: <?php echo json_encode($dados_grafico); ?>,
                backgroundColor: ['rgba(40, 167, 69, 0.8)', 'rgba(255, 193, 7, 0.8)'],
                borderColor: ['rgba(40, 167, 69, 1)', 'rgba(255, 193, 7, 1)'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = <?php echo array_sum($dados_grafico) ?: 1; ?>;
                            const value = context.raw;
                            const percentage = Math.round((value / total) * 100);
                            return context.label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });
    <?php elseif ($tipo_relatorio === 'atividade'): ?>
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labels_grafico); ?>,
            datasets: [
                {
                    label: 'Cadastros',
                    data: <?php echo json_encode($dados_grafico['cadastros'] ?? []); ?>,
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Envios',
                    data: <?php echo json_encode($dados_grafico['enviados'] ?? []); ?>,
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
    <?php endif; ?>
    <?php endif; ?>
});
</script>


<?php include 'includes/footer.php'; ?>