<?php
// session_check.php: verifica se o usuário está logado
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Inclui a biblioteca TCPDF
require_once __DIR__ . '/tcpdf/tcpdf.php';

// Seta o fuso horário
date_default_timezone_set('America/Sao_Paulo');

class PDFInventario extends TCPDF
{
    public function Header()
    {
        $img = __DIR__ . '/../atlas/style/img/timbrado.png';
        $this->SetAutoPageBreak(false, 0);
        $this->SetMargins(0, 0, 0);
        if (file_exists($img)) {
            $this->Image($img, 0, 0, 210, 297, 'PNG', '', '', false, 300);
        }
        // Restaura margens, começa 50 mm abaixo
        $this->SetAutoPageBreak(true, 20);
        $this->SetMargins(15, 30, 15);
        $this->SetY(50);
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

// consulta de tipos
$tipos = $pdo
    ->query("SELECT id, nome FROM tipos_bem ORDER BY nome")
    ->fetchAll(PDO::FETCH_ASSOC);

// monta PDF
$dataHora = date('d/m/Y H:i');
$pdf = new PDFInventario('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('BCLOUD');
$pdf->SetAuthor($_SESSION['usuario_nome']);
$pdf->SetTitle('Relatório de Inventário de Bens');
$pdf->SetSubject('Inventário');
$pdf->SetMargins(15, 30, 15);
$pdf->AddPage();

// título
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 0, 'RELATÓRIO DE INVENTÁRIO DE BENS', 0, 1, 'C');
$pdf->Ln(5);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 0, "Data/hora: $dataHora", 0, 1, 'R');
$pdf->Ln(5);

// para cada tipo
foreach ($tipos as $tipo) {
    // cabeçalho do tipo
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(0, 0, 64);
    $pdf->Cell(0, 8, 'Tipo: ' . $tipo['nome'], 0, 1);
    $pdf->SetTextColor(0, 0, 0);

    // buscar categorias
    $stmtCat = $pdo->prepare("
        SELECT id, nome 
        FROM categorias_bem 
        WHERE tipo_id = ? 
        ORDER BY nome
    ");
    $stmtCat->execute([$tipo['id']]);
    $categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

    foreach ($categorias as $cat) {
        // cabeçalho da categoria
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, '  Categoria: ' . $cat['nome'], 0, 1);

        // buscar bens
        $stmtBem = $pdo->prepare("
            SELECT modelo, configuracao, quantidade, localizacao, data_aquisicao
            FROM bens
            WHERE tipo_id = ? AND categoria_id = ? AND status = 'ativo'
            ORDER BY modelo
        ");
        $stmtBem->execute([$tipo['id'], $cat['id']]);
        $bens = $stmtBem->fetchAll(PDO::FETCH_ASSOC);

        if (count($bens)) {
            // monta tabela em HTML
            // Cabeçalho da tabela de bens
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(230, 230, 230);
            // monta tabela em HTML com layout fixo e colgroup
            $html = '<table border="1" cellpadding="4" cellspacing="0" style="table-layout:fixed; width:100%;">
                <colgroup>
                <col style="width:30%;">
                <col style="width:30%;">
                <col style="width:10%;">
                <col style="width:20%;">
                <col style="width:10%;">
                </colgroup>
                <thead style="background-color:#E6E6E6;">
                    <tr>
                        <th>Modelo</th>
                        <th>Configuração</th>
                        <th style="text-align:center;">Qtd.</th>
                        <th>Localização</th>
                        <th style="text-align:center;">Aquisição</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($bens as $bem) {
                $dataAqu = $bem['data_aquisicao']
                          ? date('d/m/Y', strtotime($bem['data_aquisicao']))
                          : '–';
                $html .= '<tr>
                    <td>' . htmlspecialchars($bem['modelo']) . '</td>
                    <td>' . nl2br(htmlspecialchars($bem['configuracao'])) . '</td>
                    <td align="center">' . $bem['quantidade'] . '</td>
                    <td>' . htmlspecialchars($bem['localizacao']) . '</td>
                    <td align="center">' . $dataAqu . '</td>
                </tr>';
            }
            $html .= '</tbody></table>';

            // imprime tabela
            $pdf->SetFont('helvetica', '', 8);
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Ln(2);
        }
    }

    $pdf->Ln(4);
}

$pdf->Output('Relatorio_Inventario_Bens.pdf', 'I');
