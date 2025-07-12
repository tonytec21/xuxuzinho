<?php
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

require_once __DIR__ . '/tcpdf/tcpdf.php';
date_default_timezone_set('America/Sao_Paulo');

class PDFInventario extends TCPDF
{
    public function Header()
    {
        $img = __DIR__ . '/../atlas/style/img/timbrado.png';

        // imagem de fundo
        $this->SetAutoPageBreak(false, 0);
        $this->SetMargins(0, 0, 0);
        if (file_exists($img)) {
            $this->Image($img, 0, 0, 210, 297, 'PNG', '', '', false, 300);
        }

        // agora sim restauro margens e empurro o conteúdo para Y = 80 mm
        $this->SetAutoPageBreak(true, 20);
        $this->SetMargins(15, 80, 15);
        $this->SetY(80);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(
            0, 10,
            'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(),
            0, false, 'C', 0, '', 0, false, 'T', 'M'
        );
    }
}

// pega tipos existentes
$tipos = $pdo
    ->query("SELECT id, nome FROM tipos_bem ORDER BY nome")
    ->fetchAll(PDO::FETCH_ASSOC);

$dataHora = date('d/m/Y H:i');

// cria PDF
$pdf = new PDFInventario('P','mm','A4',true,'UTF-8',false);
$pdf->SetCreator('BCLOUD');
$pdf->SetAuthor($_SESSION['usuario_nome']);
$pdf->SetTitle('RELATÓRIO DE INVENTÁRIO DE BENS');
$pdf->SetSubject('Inventário');
$pdf->SetMargins(15, 80, 15);
$pdf->AddPage();

// cabeçalho de texto
$pdf->SetFont('helvetica','B',16);
$pdf->Cell(0, 0, 'RELATÓRIO DE INVENTÁRIO DE BENS', 0, 1, 'C');
$pdf->Ln(4);
$pdf->SetFont('helvetica','',10);
$pdf->Cell(0, 0, "Data/hora: $dataHora", 0, 1, 'R');
$pdf->Ln(6);

foreach ($tipos as $tipo) {
    // título do tipo
    $pdf->SetFont('helvetica','B',12);
    $pdf->SetTextColor(0,0,64);
    $pdf->Cell(0, 8, 'Tipo: ' . $tipo['nome'], 0, 1, 'L');
    $pdf->SetTextColor(0,0,0);

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
        // título da categoria
        $pdf->SetFont('helvetica','B',11);
        $pdf->Cell(0, 6, '  Categoria: ' . $cat['nome'], 0, 1, 'L');
        $pdf->Ln(2);

        // busca bens
        $stmtBem = $pdo->prepare("
            SELECT modelo, configuracao, quantidade, localizacao, data_aquisicao
            FROM bens
            WHERE tipo_id = ? AND categoria_id = ? AND status = 'ativo'
            ORDER BY modelo
        ");
        $stmtBem->execute([$tipo['id'], $cat['id']]);
        $bens = $stmtBem->fetchAll(PDO::FETCH_ASSOC);

        if (count($bens)>0) {
            // montagem do HTML com colgroup e estilos
            $html = '
            <table border="1" cellpadding="4" cellspacing="0" style="table-layout:fixed; width:100%;">
              <colgroup>
                <col style="width:30%;" />
                <col style="width:30%;" />
                <col style="width:10%;" />
                <col style="width:20%;" />
                <col style="width:10%;" />
              </colgroup>
              <thead style="background-color:#E6E6E6;">
                <tr>
                  <th style="font-weight:bold; font-size:11px; text-transform:uppercase;">MODELO</th>
                  <th style="font-weight:bold; font-size:11px; text-transform:uppercase;">CONFIGURAÇÃO</th>
                  <th style="font-weight:bold; font-size:11px; text-transform:uppercase; text-align:center;">QTD.</th>
                  <th style="font-weight:bold; font-size:11px; text-transform:uppercase;">LOCALIZAÇÃO</th>
                  <th style="font-weight:bold; font-size:11px; text-transform:uppercase; text-align:center;">AQUISIÇÃO</th>
                </tr>
              </thead>
              <tbody>';
            foreach ($bens as $bem) {
                $dataAqu = $bem['data_aquisicao']
                          ? date('d/m/Y', strtotime($bem['data_aquisicao']))
                          : '–';
                // decodifica as entidades para que as aspas apareçam corretas
                $modelo = html_entity_decode($bem['modelo'], ENT_QUOTES, 'UTF-8');
                $config = nl2br(html_entity_decode($bem['configuracao'], ENT_QUOTES, 'UTF-8'));
                $loc    = html_entity_decode($bem['localizacao'], ENT_QUOTES, 'UTF-8');

                $html .= '
                <tr>
                  <td style="font-size:10px;">' . htmlspecialchars($modelo) . '</td>
                  <td style="font-size:10px;">' . $config . '</td>
                  <td align="center" style="font-size:10px;">' . $bem['quantidade'] . '</td>
                  <td style="font-size:10px;">' . htmlspecialchars($loc) . '</td>
                  <td align="center" style="font-size:10px;">' . $dataAqu . '</td>
                </tr>';
            }
            $html .= '
              </tbody>
            </table>
            <br />';

            // imprime com font menor
            $pdf->SetFont('helvetica','',10);
            $pdf->writeHTML($html, true, false, true, false, '');
        }

        $pdf->Ln(4);
    }

    $pdf->Ln(6);
}

$pdf->Output('Relatorio_Inventario_Bens.pdf','I');
