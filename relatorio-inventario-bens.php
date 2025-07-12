<?php
// relatorio-inventario-bens.php

require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once __DIR__ . '/tcpdf/tcpdf.php';

date_default_timezone_set('America/Sao_Paulo');

class PDFInventario extends TCPDF
{
    public function Header()
    {
        $image = __DIR__ . '/../atlas/style/img/timbrado.png';
        $this->SetAutoPageBreak(false, 0);
        $this->SetMargins(0, 0, 0);
        if (file_exists($image)) {
            $this->Image($image, 0, 0, 210, 297, 'PNG', '', '', false, 300);
        }
        // Conteúdo 80 mm abaixo
        $this->SetAutoPageBreak(true, 20);
        $this->SetMargins(15, 40, 15);
        $this->SetY(40);
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

// Função genérica para tabelas com largura customizada
function renderTable(TCPDF $pdf, string $title, array $headers, array $rows, array $widths = [])
{
    if (empty($rows)) return;
    // título da tabela
    $pdf->SetFont('helvetica','B',11);
    $pdf->Cell(0,8, mb_strtoupper($title,'UTF-8'), 0,1,'L');
    // monta HTML
    $pdf->SetFont('helvetica','',10);
    $html = '<table border="1" cellpadding="4" cellspacing="0" style="table-layout:fixed;width:100%;">';
    // colgroup
    $html .= '<colgroup>';
    foreach ($headers as $h) {
        $w = isset($widths[$h]) ? $widths[$h] : (100/count($headers)).'%';
        $html .= "<col style=\"width:{$w};\">";
    }
    $html .= '</colgroup>';
    // header row
    $html .= '<thead style="background-color:#E6E6E6;">';
    $html .= '<tr>';
    foreach ($headers as $h) {
        $html .= '<th style="font-weight:bold;font-size:11px;text-transform:uppercase;">'
               . htmlspecialchars($h)
               . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    // data rows
    foreach ($rows as $r) {
        $html .= '<tr>';
        foreach ($r as $cell) {
            $html .= '<td style="font-size:10px;">' . $cell . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table><br>';
    // imprime
    $pdf->writeHTML($html, true, false, true, false, '');
}

// 1) quantidade total por tipo
$typeCounts = $pdo->query("
    SELECT t.nome, COALESCE(SUM(b.quantidade),0) AS qtde
      FROM tipos_bem t
 LEFT JOIN bens b
        ON b.tipo_id = t.id AND b.status = 'ativo'
  GROUP BY t.nome
  ORDER BY t.nome
")->fetchAll(PDO::FETCH_ASSOC);

// 2) lista de tipos
$tipos = $pdo->query("SELECT id,nome FROM tipos_bem ORDER BY nome")
             ->fetchAll(PDO::FETCH_ASSOC);

$dataHora = date('d/m/Y H:i');

// instância do PDF
$pdf = new PDFInventario('P','mm','A4',true,'UTF-8',false);
$pdf->SetCreator('BCLOUD');
$pdf->SetAuthor($_SESSION['usuario_nome']);
$pdf->SetTitle('RELATÓRIO DE INVENTÁRIO DE BENS');
$pdf->SetSubject('Inventário');
$pdf->SetMargins(15,80,15);
$pdf->AddPage();

// título principal
$pdf->SetFont('helvetica','B',14);
$pdf->Cell(0,0,'RELATÓRIO DE INVENTÁRIO DE BENS',0,1,'C');
$pdf->Ln(4);
$pdf->SetFont('helvetica','',9);
$pdf->Cell(0,0,"Data/hora: $dataHora",0,1,'R');
$pdf->Ln(6);

// 3) cards de contagem
$html = '<table cellpadding="5" cellspacing="5" border="0" width="100%"><tr>';
$col = 0;
foreach ($typeCounts as $tc) {
    if ($col && $col % 3 === 0) $html .= '</tr><tr>';
    $nome = htmlspecialchars($tc['nome']);
    $qtde = $tc['qtde'];
    $cor  = '#007bff';
    $html .= "
      <td width=\"33%\" style=\"
        background-color:{$cor}; color:#fff;
        border-radius:8px; text-align:center;
      \">
        <div style=\"font-size:10px;font-weight:bold;\">
          ".mb_strtoupper($nome,'UTF-8')."
        </div>
        <div style=\"font-size:18px;font-weight:bold;\">
          {$qtde}
        </div>
      </td>";
    $col++;
}
if ($col % 3 !== 0) {
    $empty = 3 - ($col % 3);
    $html .= str_repeat('<td width="33%"></td>', $empty);
}
$html .= '</tr></table><br>';
$pdf->writeHTML($html,true,false,true,false,'');

// 4) para cada tipo e categoria
foreach ($tipos as $tipo) {
    // cabeçalho do tipo
    $pdf->SetFont('helvetica','B',11);
    $pdf->SetTextColor(0,0,64);
    $pdf->Cell(0,8,'Tipo: '.$tipo['nome'],0,1,'L');
    $pdf->SetTextColor(0,0,0);
    $pdf->Ln(2);

    // busca categorias
    $stmtCat = $pdo->prepare("
        SELECT id,nome
          FROM categorias_bem
         WHERE tipo_id = ?
         ORDER BY nome
    ");
    $stmtCat->execute([$tipo['id']]);
    $categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

    foreach ($categorias as $cat) {
        // cabeçalho da categoria
        $pdf->SetFont('helvetica','B',10);
        $pdf->Cell(0,6,'  Categoria: '.$cat['nome'],0,1,'L');
        $pdf->Ln(2);

        // busca bens
        $stmtBem = $pdo->prepare("
            SELECT modelo, configuracao, quantidade, localizacao, data_aquisicao
              FROM bens
             WHERE tipo_id = ?
               AND categoria_id = ?
               AND status = 'ativo'
             ORDER BY modelo
        ");
        $stmtBem->execute([$tipo['id'],$cat['id']]);
        $bens = $stmtBem->fetchAll(PDO::FETCH_ASSOC);
        if (empty($bens)) continue;

        // prepara linhas para renderTable
        $rows = [];
        foreach ($bens as $bem) {
            $modelo = htmlspecialchars(
              html_entity_decode($bem['modelo'],ENT_QUOTES,'UTF-8')
            );
            $config = nl2br(htmlspecialchars(
              html_entity_decode($bem['configuracao'],ENT_QUOTES,'UTF-8')
            ));
            $loc    = htmlspecialchars(
              html_entity_decode($bem['localizacao'],ENT_QUOTES,'UTF-8')
            );
            $qtd    = $bem['quantidade'];
            $data   = $bem['data_aquisicao']
                      ? date('d/m/Y',strtotime($bem['data_aquisicao']))
                      : '–';
            $rows[] = [
              $modelo,
              $config,
              $qtd,
              $loc,
              $data
            ];
        }

        // largura de colunas
        $widths = [
          'MODELO'        => '30%',
          'CONFIGURAÇÃO'  => '30%',
          'QTD.'          => '10%',
          'LOCALIZAÇÃO'   => '20%',
          'AQUISIÇÃO'     => '10%'
        ];

        renderTable(
          $pdf,
          'Categoria: '.$cat['nome'],
          ['MODELO','CONFIGURAÇÃO','QTD.','LOCALIZAÇÃO','AQUISIÇÃO'],
          $rows,
          $widths
        );
    }

    $pdf->Ln(4);
}

// saída
$pdf->Output('Relatorio_Inventario_Bens.pdf','I');
