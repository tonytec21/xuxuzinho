<?php
/**
 * relatorio-inventario-bens.php
 * Gera um PDF (TCPDF) com o inventário completo de bens,
 * mantendo a mesma “carcaça” visual do relatório de caixa.
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
/* BUSCA DE DADOS – cards + hierarquia                                */
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
/* PREPARA “CARDS”                                                    */
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
/* TCPDF SUB-CLASS                                                    */
/* ------------------------------------------------------------------ */
class PDFInventario extends TCPDF
{
    public function Header()
    {
        $logo = __DIR__ . '/../atlas/style/img/timbrado.png';
        $this->SetAutoPageBreak(false, 0);
        $this->SetMargins(0,0,0);

        if (file_exists($logo))
            $this->Image($logo, 0, 0, 210, 297, 'PNG');

        /* conteúdo começa 40 mm abaixo do topo */
        $this->SetAutoPageBreak(true, 25);
        $this->SetMargins(25, 40, 25);
        $this->SetY(35);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica','I',8);
        $this->Cell(0,10,
            'Página '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(),
            0,0,'L');
    }
}

/* ------------------------------------------------------------------ */
/* FUNÇÃO DE TABELA (cabeçalho cinza + negrito + aspas preservadas)    */
/* ------------------------------------------------------------------ */
function renderTable(
    TCPDF $pdf,
    string $title,
    array  $headers,
    array  $dataRows,
    array  $colWidths=[]
){
    if (!$dataRows) return;

    $pdf->SetFont('helvetica','B',11);
    $pdf->Cell(0,8,mb_strtoupper($title,'UTF-8'),0,1,'L');
    $pdf->SetFont('helvetica','',9);

    $html = '<table border="1" cellpadding="3">
              <thead>
                <tr style="background:#e6e6e6;font-weight:bold;">';

    foreach ($headers as $h){
        $w = $colWidths[$h] ?? '';
        $html .= '<th'.($w?' style="width:'.$w.';"':'').'>'.$h.'</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($dataRows as $row){
        $html .= '<tr>';
        foreach ($row as $i=>$cell){
            $w = $colWidths[$headers[$i]] ?? '';
            $html .= '<td'.($w?' style="width:'.$w.';"':'').'>'.$cell.'</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table><br>';

    $pdf->writeHTML($html,true,false,true,false,'');
}

/* ------------------------------------------------------------------ */
/* GERAR PDF                                                          */
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
$pdf->Ln(2);

/* cards */
// $htmlCards = '<table cellspacing="5" cellpadding="5" width="100%"><tr>';
// $c=0;
// foreach ($cards as $nome=>$valor){
//     $cor = $cardColors[$nome];
//     if ($c && $c%3==0) $htmlCards.='</tr><tr>';
//     $htmlCards .= '
//       <td width="33%" style="
//           background:'.$cor.';
//           color:#fff;
//           border-radius:10px;
//           text-align:center;">
//         <div style="font-size:10px;font-weight:bold">'.mb_strtoupper($nome,'UTF-8').'</div>
//         <div style="font-size:18px;font-weight:bold">'.$valor.'</div>
//       </td>';
//     $c++;
// }
// if ($c%3) $htmlCards .= str_repeat('<td width="33%"></td>', 3-($c%3));
// $htmlCards .= '</tr></table><br>';
// $pdf->writeHTML($htmlCards,true,false,true,false,'');

/* tipos / categorias / bens ---------------------------------------- */
foreach ($tipos as $tipo){
    $pdf->SetFont('helvetica','B',14);
    $pdf->Cell(0,8,'TIPO: '.$tipo['nome'],0,1,'L');
    $pdf->Ln(2);

    $catStmt = $pdo->prepare(
        "SELECT id,nome FROM categorias_bem
          WHERE tipo_id = ? ORDER BY nome"
    );
    $catStmt->execute([$tipo['id']]);

    foreach ($catStmt->fetchAll(PDO::FETCH_ASSOC) as $cat){

        $bensStmt = $pdo->prepare(
            "SELECT modelo,
                    configuracao,
                    quantidade,
                    localizacao,
                    IFNULL(DATE_FORMAT(data_aquisicao,'%d/%m/%Y'),'–') AS aq
               FROM bens
              WHERE tipo_id=? AND categoria_id=? AND status='ativo'
           ORDER BY modelo"
        );
        $bensStmt->execute([$tipo['id'],$cat['id']]);
        $bens = $bensStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$bens) continue;

        $pdf->SetFont('helvetica','B',12);
        $pdf->Cell(0,6,'  CATEGORIA: '.$cat['nome'],0,1,'L');
        $pdf->Ln(1);

        $rows = array_map(static function($b){
            return [
                htmlspecialchars($b['modelo'],       ENT_NOQUOTES,'UTF-8'),
                nl2br(htmlspecialchars($b['configuracao'], ENT_NOQUOTES,'UTF-8')),
                $b['quantidade'],
                htmlspecialchars($b['localizacao'], ENT_NOQUOTES,'UTF-8'),
                $b['aq']
            ];
        }, $bens);

        renderTable(
            $pdf,
            'Itens da Categoria',
            ['MODELO','CONFIGURAÇÃO','QTD','LOCALIZAÇÃO','AQUISIÇÃO'],
            $rows,
            ['MODELO'=>'28%','CONFIGURAÇÃO'=>'30%','QTD'=>'8%','LOCALIZAÇÃO'=>'20%','AQUISIÇÃO'=>'14%']
        );
    }
}

/* gráfico ---------------------------------------------------------- */
$pdf->AddPage();
$pdf->SetFont('helvetica','B',14);
$pdf->Cell(0,10,'GRÁFICO DE DISTRIBUIÇÃO POR TIPO',0,1,'C');
$pdf->Ln(3);

$labels = array_column($qtyPorTipo,'nome');
$values = array_column($qtyPorTipo,'qtde');
$colors = array_values($cardColors); // usa mesmas cores dos cards
array_shift($colors);                // retira cor do “total”

$chartCfg=[
    'type'=>'bar',
    'data'=>[
        'labels'=>$labels,
        'datasets'=>[[
            'data'=>$values,
            'backgroundColor'=>$colors
        ]]
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
