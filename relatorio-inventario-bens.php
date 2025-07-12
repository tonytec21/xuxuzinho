<?php
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once __DIR__ . '/tcpdf/tcpdf.php';

date_default_timezone_set('America/Sao_Paulo');

// cria conexão PDO
$conn = getDatabaseConnection();

// helper para buscar dados
function fetchData(string $sql, array $params = []): array
{
    global $conn;
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 1) totais por tipo para os cards
$typeCounts = fetchData("
    SELECT t.nome, COALESCE(SUM(b.quantidade),0) AS qtde
      FROM tipos_bem t
 LEFT JOIN bens b
        ON b.tipo_id = t.id
       AND b.status = 'ativo'
  GROUP BY t.nome
  ORDER BY t.nome
");

// 2) lista de tipos e categorias
$tipos = fetchData("SELECT id,nome FROM tipos_bem ORDER BY nome");

$dataHora = date('d/m/Y H:i');

class PDFInventario extends TCPDF
{
    public function Header()
    {
        $img = __DIR__ . '/../atlas/style/img/timbrado.png';
        $this->SetAutoPageBreak(false,0);
        $this->SetMargins(0,0,0);
        if (file_exists($img)) {
            $this->Image($img,0,0,210,297,'PNG');
        }
        // conteúdo começa 80mm abaixo
        $this->SetAutoPageBreak(true,20);
        $this->SetMargins(15,80,15);
        $this->SetY(80);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica','I',8);
        $this->Cell(0,10,
            'Página '.$this->getAliasNumPage()
          .' de '.$this->getAliasNbPages(),
            0,0,'C'
        );
    }
}

// monta PDF
$pdf = new PDFInventario('P','mm','A4',true,'UTF-8',false);
$pdf->SetCreator('BCLOUD');
$pdf->SetAuthor($_SESSION['usuario_nome']);
$pdf->SetTitle('RELATÓRIO DE INVENTÁRIO DE BENS');
$pdf->SetSubject('Inventário');
$pdf->SetMargins(15,80,15);
$pdf->AddPage();

// título
$pdf->SetFont('helvetica','B',14);
$pdf->Cell(0,0,'RELATÓRIO DE INVENTÁRIO DE BENS',0,1,'C');
$pdf->Ln(4);
$pdf->SetFont('helvetica','',10);
$pdf->Cell(0,0,"Data/hora: $dataHora",0,1,'R');
$pdf->Ln(6);

// ================= CARDS =====================
$html = '<table cellspacing="5" cellpadding="5" border="0" width="100%"><tr>';
$count = 0;
foreach ($typeCounts as $tc) {
    if ($count && $count % 3 === 0) {
        $html .= '</tr><tr>';
    }
    $nome = htmlspecialchars($tc['nome']);
    $qtde = $tc['qtde'];
    $cor  = '#007bff';
    $html .= "
      <td width=\"33%\" style=\"
        background-color:{$cor}; color:#fff;
        border-radius:8px; text-align:center;
      \">
        <div style=\"font-size:10px;font-weight:bold; text-transform:uppercase;\">
          {$nome}
        </div>
        <div style=\"font-size:18px;font-weight:bold;\">
          {$qtde}
        </div>
      </td>";
    $count++;
}
if ($count % 3 !== 0) {
    $empty = 3 - ($count % 3);
    $html .= str_repeat('<td width="33%"></td>', $empty);
}
$html .= '</tr></table><br>';
$pdf->writeHTML($html, true, false, true, false, '');

// ================= FUNÇÃO DE TABELA =====================
function renderTable(
    TCPDF $pdf,
    string $title,
    array $headers,
    array $rows,
    array $widths = []
) {
    if (empty($rows)) return;
    // título
    $pdf->SetFont('helvetica','B',12);
    $pdf->Cell(0,8, mb_strtoupper($title,'UTF-8'),0,1,'L');
    // monta HTML
    $pdf->SetFont('helvetica','',10);
    $html = '<table border="1" cellpadding="4" cellspacing="0" style="table-layout:fixed;width:100%;">';
    // colgroup
    $html .= '<colgroup>';
    foreach ($headers as $h) {
        $w = $widths[$h] ?? (100/count($headers)).'%';
        $html .= "<col style=\"width:{$w};\">";
    }
    $html .= '</colgroup>';
    // cabeçalho
    $html .= '<thead style="background-color:#E6E6E6;"><tr>';
    foreach ($headers as $h) {
        $html .= '<th style="font-weight:bold;font-size:11px;text-transform:uppercase;">'
               . htmlspecialchars($h)
               . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    // linhas
    foreach ($rows as $r) {
        $html .= '<tr>';
        foreach ($r as $cell) {
            $html .= '<td style="font-size:10px;">'.$cell.'</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table><br>';
    $pdf->writeHTML($html, true, false, true, false, '');
}

// ================= TABELAS =====================
foreach ($tipos as $tipo) {
    // busca categorias
    $cats = fetchData(
        "SELECT id,nome FROM categorias_bem WHERE tipo_id = :tid ORDER BY nome",
        [':tid'=>$tipo['id']]
    );
    foreach ($cats as $cat) {
        // busca bens
        $bens = fetchData(
            "SELECT modelo, configuracao, quantidade, localizacao, data_aquisicao
               FROM bens
              WHERE tipo_id = :tid
                AND categoria_id = :cid
                AND status = 'ativo'
              ORDER BY modelo",
            [':tid'=>$tipo['id'], ':cid'=>$cat['id']]
        );
        if (empty($bens)) continue;
        $rows = [];
        foreach ($bens as $b) {
            $modelo = htmlspecialchars(
              html_entity_decode($b['modelo'],ENT_QUOTES,'UTF-8')
            );
            $config = nl2br(htmlspecialchars(
              html_entity_decode($b['configuracao'],ENT_QUOTES,'UTF-8')
            ));
            $loc    = htmlspecialchars(
              html_entity_decode($b['localizacao'],ENT_QUOTES,'UTF-8')
            );
            $qtd    = $b['quantidade'];
            $dat    = $b['data_aquisicao']
                      ? date('d/m/Y',strtotime($b['data_aquisicao']))
                      : '–';
            $rows[] = [$modelo, $config, $qtd, $loc, $dat];
        }
        // largura
        $widths = [
          'MODELO'       => '30%',
          'CONFIGURAÇÃO' => '30%',
          'QTD.'         => '10%',
          'LOCALIZAÇÃO'  => '20%',
          'AQUISIÇÃO'    => '10%',
        ];
        // renderiza
        renderTable(
          $pdf,
          "Tipo: {$tipo['nome']} / Categoria: {$cat['nome']}",
          ['MODELO','CONFIGURAÇÃO','QTD.','LOCALIZAÇÃO','AQUISIÇÃO'],
          $rows,
          $widths
        );
    }
}

$pdf->Output('Relatorio_Inventario_Bens.pdf','I');
