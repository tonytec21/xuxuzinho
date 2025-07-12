<?php
/**
 * relatorio-inventario-bens.php
 * Gera um PDF (TCPDF) com o inventário completo de bens,
 * aproveitando o mesmo “esqueleto” visual do relatório de caixa.
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
/* FUNÇÃO DE SANITIZAÇÃO – MOSTRA ASPAS NORMALMENTE                   */
/* ------------------------------------------------------------------ */
function safeText(string $str): string
{
    // 1) converte &quot; &apos; &#39; etc. para seus caracteres
    $decoded = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // 2) escapa apenas &, < e >   (deixa aspas “vivas”)
    return htmlspecialchars($decoded, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ------------------------------------------------------------------ */
/* BUSCA DE DADOS (cards, hierarquia)                                 */
/* ------------------------------------------------------------------ */
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

$tipos = $pdo->query("
    SELECT id, nome
      FROM tipos_bem
  ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------------------------------------------------ */
/* PREPARAÇÃO DOS “CARDS”                                             */
/* ------------------------------------------------------------------ */
$cards = ['TOTAL DE BENS' => (int)$totalBens];
foreach ($qtyPorTipo as $q) $cards[$q['nome']] = (int)$q['qtde'];

$basePalette = [
    '#007bff','#6f42c1','#fd7e14','#17a2b8',
    '#dc3545','#20c997','#ffc107','#0d6efd',
    '#6610f2','#198754'
];
$cardColors = ['TOTAL DE BENS' => '#0a5d0a'];
foreach (array_keys($cards) as $i => $nome) {
    if ($nome === 'TOTAL DE BENS') continue;
    $cardColors[$nome] = $basePalette[($i-1) % count($basePalette)];
}

/* ------------------------------------------------------------------ */
/* SUB-CLASSE TCPDF                                                   */
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
        /* conteúdo começa 40 mm abaixo do topo */
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
            'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(),
            0, 0, 'L'
        );
    }
}

/* ------------------------------------------------------------------ */
/* FUNÇÃO-GENÉRICA DE TABELA                                          */
/* ------------------------------------------------------------------ */
function renderTable(
    TCPDF $pdf,
    string $title,
    array  $headers,
    array  $dataRows,
    array  $colWidths=[]
){
    if (!$dataRows) return;

    $pdf->SetFont('helvetica','B',10);
    $pdf->Cell(0,8,mb_strtoupper($title,'UTF-8'),0,1,'L');
    $pdf->SetFont('helvetica','',9);

    $html = '<table border="1" cellpadding="3">
              <thead>
                <tr style="background:#e6e6e6;font-weight:bold;">';

    foreach ($headers as $h){
        $w = $colWidths[$h] ?? '';
        $html .= '<th'.($w ? ' style="width:'.$w.';"':'').'>'.$h.'</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($dataRows as $row){
        $html .= '<tr>';
        foreach ($row as $i=>$cell){
            $w = $colWidths[$headers[$i]] ?? '';
            $html .= '<td'.($w ? ' style="width:'.$w.';"':'').'>'.$cell.'</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table><br>';
    $pdf->writeHTML($html, true, false, true, false, '');
}

/* ------------------------------------------------------------------ */
/* GERAÇÃO DO PDF                                                     */
/* ------------------------------------------------------------------ */
$pdf = new PDFInventario();
$pdf->SetCreator('BCLOUD');
$pdf->SetAuthor($_SESSION['usuario_nome']);
$pdf->SetTitle('Inventário de Bens');
$pdf->AddPage();

/* título */
$pdf->SetFont('helvetica','B',14);
$pdf->Cell(0,10,'RELATÓRIO DE INVENTÁRIO DE BENS',0,1,'C');
$pdf->Ln(1);
$pdf->SetFont('helvetica','',11);
$pdf->Cell(0,8,'Gerado em: '.date('d/m/Y H:i'),0,1,'C');
$pdf->Ln(1);

/* ----------- DETALHAMENTO ---------------------------------------- */
foreach ($tipos as $tipo){
    $pdf->SetFont('helvetica','B',13);
    $pdf->Cell(0,8,'Tipo: '.safeText($tipo['nome']),0,1,'L');
    $pdf->Ln(2);

    $catStmt = $pdo->prepare("
        SELECT id,nome
          FROM categorias_bem
         WHERE tipo_id = ?
      ORDER BY nome
    ");
    $catStmt->execute([$tipo['id']]);

    foreach ($catStmt->fetchAll(PDO::FETCH_ASSOC) as $cat){

        $bensStmt = $pdo->prepare("
            SELECT modelo,
                   configuracao,
                   quantidade,
                   localizacao,
                   IFNULL(DATE_FORMAT(data_aquisicao,'%d/%m/%Y'),'–') AS aq
              FROM bens
             WHERE tipo_id      = ?
               AND categoria_id = ?
               AND status       = 'ativo'
          ORDER BY modelo
        ");
        $bensStmt->execute([$tipo['id'],$cat['id']]);
        $bens = $bensStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$bens) continue;

        $pdf->SetFont('helvetica','B',11);
        $pdf->Cell(0,6,'  Categoria: '.safeText($cat['nome']),0,1,'L');
        $pdf->Ln(-6);

        $rows = array_map(static function($b){
            return [
                safeText($b['modelo']),
                nl2br(safeText($b['configuracao'])),
                $b['quantidade'],
                safeText($b['localizacao']),
                $b['aq']
            ];
        }, $bens);

        renderTable(
            $pdf,
            '',     
            ['MODELO','CONFIGURAÇÃO','QTD','LOCALIZAÇÃO','AQUISIÇÃO'],
            $rows,
            ['MODELO'=>'26%','CONFIGURAÇÃO'=>'34%','QTD'=>'6%',
             'LOCALIZAÇÃO'=>'22%','AQUISIÇÃO'=>'12%']
        );
    }
}

/* ----------- GRÁFICO --------------------------------------------- */
$pdf->AddPage();
$pdf->SetFont('helvetica','B',14);
$pdf->Cell(0,10,'GRÁFICO DE DISTRIBUIÇÃO POR TIPO',0,1,'C');
$pdf->Ln(3);

$labels = array_column($qtyPorTipo,'nome');
$values = array_column($qtyPorTipo,'qtde');
$colors = array_values(array_diff_key($cardColors,['TOTAL DE BENS'=>true]));

$chartCfg = [
    'type'=>'bar',
    'data'=>[
        'labels'=>$labels,
        'datasets'=>[[ 'data'=>$values,'backgroundColor'=>$colors ]]
    ],
    'options'=>['plugins'=>['legend'=>false]]
];
$url='https://quickchart.io/chart?c='.urlencode(json_encode($chartCfg)).'&format=png&backgroundColor=white';
$tmp=tempnam(sys_get_temp_dir(),'chart_').'.png';
@file_put_contents($tmp,file_get_contents($url));
if (file_exists($tmp)){
    $pdf->Image($tmp,25,60,160,90);
    unlink($tmp);
}

/* ------------------------------------------------------------------ */
ob_clean();
$pdf->Output('Inventario_Bens_'.date('Ymd_His').'.pdf','I');
?>
