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
    // Cabeçalho com timbrado
    public function Header()
    {
        $img = __DIR__ . '/../atlas/style/img/timbrado.png';
        $this->SetAutoPageBreak(false, 0);
        $this->SetMargins(0, 0, 0);
        if (file_exists($img)) {
            $this->Image($img, 0, 0, 210, 297, 'PNG', '', '', false, 300);
        }
        // Restaura margens para o conteúdo
        $this->SetAutoPageBreak(true, 20);
        $this->SetMargins(15, 30, 15);
        // ▶ Ajuste: começa 50mm abaixo da margem superior (5cm)
        $this->SetY(50);
    }

    // Rodapé com número de páginas
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

// Busca dados de tipos, categorias e bens
$tipos = $pdo
    ->query("SELECT id, nome FROM tipos_bem ORDER BY nome")
    ->fetchAll(PDO::FETCH_ASSOC);

$dataHora = date('d/m/Y H:i');

// Cria o PDF
$pdf = new PDFInventario('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('BCLOUD');
$pdf->SetAuthor($_SESSION['usuario_nome']);
$pdf->SetTitle('Relatório de Inventário de Bens');
$pdf->SetSubject('Inventário');
$pdf->SetMargins(15, 30, 15);
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 0, 'RELATÓRIO DE INVENTÁRIO DE BENS', 0, 1, 'C');
$pdf->Ln(5);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 0, "Data/hora: $dataHora", 0, 1, 'R');
$pdf->Ln(5);

// Para cada tipo, exibe suas categorias e bens
foreach ($tipos as $tipo) {
    // Cabeçalho do tipo
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(0, 0, 64);
    $pdf->Cell(0, 8, 'Tipo: ' . $tipo['nome'], 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);

    // Busca categorias deste tipo
    $stmtCat = $pdo->prepare("SELECT id,nome FROM categorias_bem WHERE tipo_id = ? ORDER BY nome");
    $stmtCat->execute([$tipo['id']]);
    $categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

    foreach ($categorias as $cat) {
        // Cabeçalho da categoria
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, '  Categoria: ' . $cat['nome'], 0, 1, 'L');

        // Cabeçalho da tabela de bens
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(50, 7, 'Modelo',       1, 0, 'L', 1);
        $pdf->Cell(40, 7, 'Configuração', 1, 0, 'L', 1);
        $pdf->Cell(15, 7, 'Qtd.',         1, 0, 'C', 1);
        $pdf->Cell(50, 7, 'Localização',  1, 0, 'L', 1);
        $pdf->Cell(25, 7, 'Aquisição',    1, 1, 'C', 1);

        // Busca bens desta categoria
        $stmtBem = $pdo->prepare("
            SELECT modelo, configuracao, quantidade, localizacao, data_aquisicao
            FROM bens
            WHERE tipo_id = ? AND categoria_id = ? AND status = 'ativo'
            ORDER BY modelo
        ");
        $stmtBem->execute([$tipo['id'], $cat['id']]);
        $bens = $stmtBem->fetchAll(PDO::FETCH_ASSOC);

        // Exibe cada bem
        $pdf->SetFont('helvetica', '', 8);
        foreach ($bens as $bem) {
            if ($pdf->GetY() > 270) {
                $pdf->AddPage();
            }

            // Modelo (wrap)
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $pdf->MultiCell(50, 6, $bem['modelo'], 1, 'L', false, 0, $x, $y, true, 0, false, true, 6, 'M');

            // Configuração (wrap)
            $x += 50;
            $pdf->MultiCell(40, 6, $bem['configuracao'], 1, 'L', false, 0, $x, $y, true, 0, false, true, 6, 'M');

            // Quantidade
            $x += 40;
            $pdf->MultiCell(15, 6, $bem['quantidade'], 1, 'C', false, 0, $x, $y, true, 0, false, true, 6, 'M');

            // Localização (wrap)
            $x += 15;
            $pdf->MultiCell(50, 6, $bem['localizacao'], 1, 'L', false, 0, $x, $y, true, 0, false, true, 6, 'M');

            // Aquisição
            $x += 50;
            $dataAqu = $bem['data_aquisicao']
                      ? date('d/m/Y', strtotime($bem['data_aquisicao']))
                      : '–';
            $pdf->MultiCell(25, 6, $dataAqu, 1, 'C', false, 1, $x, $y, true, 0, false, true, 6, 'M');
        }

        $pdf->Ln(2);
    }

    $pdf->Ln(4);
}

// Gera o arquivo para o browser
$pdf->Output('Relatorio_Inventario_Bens.pdf', 'I');
