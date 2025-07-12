<?php
/**
 * relatorio-inventario-bens.php  
 * Gera um PDF (TCPDF) com o inventário completo de bens,
 * mantendo **a mesma estrutura** do relatório de caixa.
 */

/* ------------------------------------------------------------------ */
/* INCLUDES / INICIALIZAÇÃO                                           */
/* ------------------------------------------------------------------ */
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once __DIR__ . '/tcpdf/tcpdf.php';

date_default_timezone_set('America/Sao_Paulo');

/* ------------------------------------------------------------------ */
/* BUSCA DE DADOS                                                     */
/* ------------------------------------------------------------------ */

/* 1. Quantidades p/ “cards” ---------------------------------------- */
$totalBens = $pdo->query("
        SELECT COALESCE(SUM(quantidade),0)
          FROM bens
         WHERE status = 'ativo'
")->fetchColumn();

$qtyPorTipo = $pdo->query("
    SELECT t.nome,
           COALESCE(SUM(b.quantidade),0) AS qtde
      FROM tipos_bem t
 LEFT JOIN bens b
        ON b.tipo_id = t.id
       AND b.status = 'ativo'
  GROUP BY t.nome
  ORDER BY t.nome
")->fetchAll(PDO::FETCH_ASSOC);

/* 2. Estrutura Tipo → Categoria → Bens ----------------------------- */
$tipos = $pdo->query("
    SELECT id, nome
      FROM tipos_bem
  ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------------------------------------------------ */
/* PREPARAÇÃO DOS CARDS                                               */
/* ------------------------------------------------------------------ */
$cards = ['TOTAL DE BENS' => (int)$totalBens];
foreach ($qtyPorTipo as $q) {
    $cards[$q['nome']] = (int)$q['qtde'];
}

/* Paleta de cores (cresce automaticamente) ------------------------- */
$basePalette = [
    '#007bff', '#6f42c1', '#fd7e14', '#17a2b8', '#dc3545',
    '#20c997', '#ffc107', '#0d6efd', '#6610f2', '#198754'
];

$cardColors = ['TOTAL DE BENS' => '#0a5d0a'];
$idx = 0;
foreach ($qtyPorTipo as $q) {
    $cardColors[$q['nome']] = $basePalette[$idx % count($basePalette)];
    $idx++;
}

/* ------------------------------------------------------------------ */
/* CLASSE PDF                                                         */
/* ------------------------------------------------------------------ */
class PDFInventario extends TCPDF
{
    public function Header()
    {
        $logo = __DIR__ . '/../atlas/style/img/timbrado.png';
        $this->SetAutoPageBreak(false, 0);
        $this->SetMargins(0, 0, 0);

        if (file_exists($logo)) {
            $this->Image($logo, 0, 0, 210, 297, 'PNG');
        }

        /* Conteúdo a partir de Y = 45 mm */
        $this->SetAutoPageBreak(true, 25);
        $this->SetMargins(25, 40, 25);
        $this->SetY(35);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(
            0, 10,
            'Página ' . $this->getAliasNumPage() .
            ' de ' . $this->getAliasNbPages(),
            0, 0, 'L'
        );
    }
}

/* ------------------------------------------------------------------ */
/* FUNÇÃO GENÉRICA PARA RENDERIZAR TABELAS                            */
/* ------------------------------------------------------------------ */
function renderTable(
    TCPDF $pdf,
    string $title,
    array  $headers,
    array  $dataRows,
    array  $colWidths = []
) {
    if (!$dataRows) return;

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, mb_strtoupper($title, 'UTF-8'), 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);

    $html = '<table border="1" cellpadding="3">
                <thead style="background:#e6e6e6;"><tr>';

    /* cabeçalhos */
    foreach ($headers as $h) {
        $w = $colWidths[$h] ?? '';
        $html .= '<th' . ($w ? ' style="width:' . $w . ';"' : '') . '>'
               . $h . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    /* dados */
    foreach ($dataRows as $row) {
        $html .= '<tr>';
        foreach ($row as $i => $cell) {
            $h = $headers[$i];
            $w = $colWidths[$h] ?? '';
            $html .= '<td' . ($w ? ' style="width:' . $w . ';"' : '') . '>'
                   . $cell . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table><br>';
    $pdf->writeHTML($html, true, false, true, false, '');
}

/* ------------------------------------------------------------------ */
/* GERAR PDF                                                          */
/* ------------------------------------------------------------------ */
$pdf = new PDFInventario();
$pdf->SetCreator('BCLOUD');
$pdf->SetAuthor($_SESSION['usuario_nome']);
$pdf->SetTitle('Inventário de Bens');
$pdf->AddPage();

/* -- Título -- */
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'RELATÓRIO DE INVENTÁRIO DE BENS', 0, 1, 'C');
$pdf->Ln(1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 8, 'Gerado em: ' . date('d/m/Y H:i'), 0, 1, 'C');
$pdf->Ln(2);

/* -- CARDS --------------------------------------------------------- */
$html = '<table cellspacing="5" cellpadding="5" border="0" width="100%">';
$col = 0;

foreach ($cards as $titulo => $valor) {
    $cor = $cardColors[$titulo] ?? '#007bff';

    if ($col % 3 === 0) $html .= '<tr>';

    $html .= '
        <td width="33%" style="
            background:' . $cor . ';
            color:#fff;
            border-radius:12px;
            text-align:center;
        ">
            <div style=\"font-size:10px;font-weight:bold;\">
                ' . mb_strtoupper($titulo, 'UTF-8') . '<br>
                <span style=\"font-size:16px;\">' . $valor . '</span>
            </div>
        </td>';

    $col++;
    if ($col % 3 === 0) $html .= '</tr>';
}
if ($col % 3 !== 0) {
    $html .= str_repeat('<td></td>', 3 - ($col % 3)) . '</tr>';
}
$html .= '</table><br>';
$pdf->writeHTML($html, true, false, true, false, '');

/* -- Tabelas detalhadas ------------------------------------------- */
foreach ($tipos as $tipo) {
    /* cabeçalho do tipo */
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Tipo: ' . $tipo['nome'], 0, 1, 'L');
    $pdf->Ln(2);

    /* categorias */
    $cats = $pdo->prepare("
        SELECT id, nome
          FROM categorias_bem
         WHERE tipo_id = ?
      ORDER BY nome
    ");
    $cats->execute([$tipo['id']]);

    foreach ($cats->fetchAll(PDO::FETCH_ASSOC) as $cat) {

        /* bens */
        $bensStm = $pdo->prepare("
            SELECT modelo,
                   configuracao,
                   quantidade,
                   localizacao,
                   IFNULL(
                     DATE_FORMAT(data_aquisicao,'%d/%m/%Y'),
                     '–'
                   ) AS data_aq
              FROM bens
             WHERE tipo_id      = ?
               AND categoria_id = ?
               AND status       = 'ativo'
          ORDER BY modelo
        ");
        $bensStm->execute([$tipo['id'], $cat['id']]);
        $bens = $bensStm->fetchAll(PDO::FETCH_ASSOC);
        if (!$bens) continue;

        /* título da categoria */
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 6, '  Categoria: ' . $cat['nome'], 0, 1, 'L');
        $pdf->Ln(1);

        /* linhas para a função renderTable */
        $rows = array_map(function ($b) {
            return [
                htmlspecialchars($b['modelo']),
                nl2br(htmlspecialchars($b['configuracao'])),
                $b['quantidade'],
                htmlspecialchars($b['localizacao']),
                $b['data_aq']
            ];
        }, $bens);

        renderTable(
            $pdf,
            'Itens da Categoria',
            ['MODELO', 'CONFIGURAÇÃO', 'QTD', 'LOCALIZAÇÃO', 'AQUISIÇÃO'],
            $rows,
            ['MODELO' => '28%',
             'CONFIGURAÇÃO' => '30%',
             'QTD' => '8%',
             'LOCALIZAÇÃO' => '20%',
             'AQUISIÇÃO' => '14%']
        );
    }
}

/* -- Gráfico de distribuição por tipo ----------------------------- */
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'GRÁFICO DE DISTRIBUIÇÃO POR TIPO', 0, 1, 'C');
$pdf->Ln(3);

$labels  = array_column($qtyPorTipo, 'nome');
$values  = array_column($qtyPorTipo, 'qtde');
$colors  = [];                 // cores na mesma lógica dos cards
for ($i = 0; $i < count($labels); $i++) {
    $colors[] = $basePalette[$i % count($basePalette)];
}

$chartCfg = [
    'type' => 'bar',
    'data' => [
        'labels'   => $labels,
        'datasets' => [[
            'data'            => $values,
            'backgroundColor' => $colors
        ]]
    ],
    'options' => ['plugins' => ['legend' => false]]
];

$chartURL = 'https://quickchart.io/chart?c=' .
            urlencode(json_encode($chartCfg)) .
            '&format=png&backgroundColor=white';

$tmpImg = tempnam(sys_get_temp_dir(), 'chart_') . '.png';
@file_put_contents($tmpImg, file_get_contents($chartURL));

if (file_exists($tmpImg)) {
    $pdf->Image($tmpImg, 25, 60, 160, 90);
    unlink($tmpImg);
}

/* ------------------------------------------------------------------ */
ob_clean();
$pdf->Output('Inventario_Bens_' . date('Ymd_His') . '.pdf', 'I');
?>
