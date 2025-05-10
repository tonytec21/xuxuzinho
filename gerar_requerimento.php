<?php
date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_TIME, 'pt_BR.UTF-8');

require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'tcpdf/tcpdf.php';

/* ---------- validar ID ------------------------------------------- */
$id = intval($_GET['id'] ?? 0);
if (!$id) die('ID inválido.');

$stmt = $pdo->prepare("SELECT * FROM triagem_registros WHERE id = ?");
$stmt->execute([$id]);
$reg = $stmt->fetch();
if (!$reg) die('Registro não encontrado.');

/* ---------- helpers ---------------------------------------------- */
function dataExtenso($dataSQL){
    $meses=['','janeiro','fevereiro','março','abril','maio','junho',
            'julho','agosto','setembro','outubro','novembro','dezembro'];
    $ts=strtotime($dataSQL);
    return strftime('%d',$ts).' de '.$meses[intval(date('n',$ts))].' de '.date('Y',$ts);
}
$cidadeData = ($reg['serventia_cidade'] && $reg['serventia_uf'])
    ? "{$reg['serventia_cidade']}/{$reg['serventia_uf']}, ".dataExtenso(date('Y-m-d'))
    : "______, ______";

/* ---------- variáveis para template ------------------------------ */
$requerente   = trim($reg['nome_requerente']);
$doc          = trim($reg['documento_identificacao']);
$cpf          = trim($reg['cpf']);
$tipoLegivel  = strtoupper($reg['tipo_certidao'] ?? '');
$cartorio     = $reg['serventia_nome'];
$cidadeCart   = $reg['serventia_cidade'];
$ufCart       = $reg['serventia_uf'];
$livro        = $reg['livro'] ?: '___';
$folha        = $reg['folha'] ?: '___';
$termo        = $reg['termo'] ?: '___';

$nomeReg  = $reg['nome_registrado'] ?: '_____________________';
$dataReg  = $reg['data_evento'] ? date('d/m/Y',strtotime($reg['data_evento'])) : '___/___/____';
$filiacao = $reg['filiacao_conjuge'] ?: '__________________________';

if ($reg['tipo_certidao'] === 'casamento'){
    $qualif = "O(a) registrado(a) é {$nomeReg}, casado(a) em {$dataReg}, com {$filiacao}.";
} else {
    $qualif = "O(a) registrado(a) é {$nomeReg}, nascido(a) em {$dataReg}, filho(a) de {$filiacao}.";
}

/* ---------- montar HTML ------------------------------------------ */
$html = <<<HTML
<style>
p{font-size:12pt; text-align:justify; margin:0 0 8pt 0;}
.titulo{font-weight:bold; font-size:14pt; text-align:center; margin-bottom:10pt;}
.centro{text-align:center;}
.assin{margin-top:40pt; text-align:center;}
</style>

<div class="titulo">REQUERIMENTO</div>

<p>Eu, {$requerente}, portador(a) do documento {$doc} e CPF {$cpf},
venho por meio deste requerer minha certidão de <strong>{$tipoLegivel}</strong>.</p>

<p>Referente ao registro constante no Livro {$livro}, Folha {$folha}, Termo {$termo},
do(a) {$cartorio}, situado na cidade de {$cidadeCart}/{$ufCart}.</p>

<p>{$qualif}</p>

<p><b>DECLARO</b>, para os devidos fins de direito e sob as penas da Lei, 
que sou pessoa pobre na acepção da palavra, e que não possuo meios 
para arcar com as custas e emolumentos da certidão solicitada sem 
prejuízo do meu próprio sustento.</p>

<p class="centro" style="margin-top:20pt;">{$cidadeData}</p>

<div class="assin">
    <hr style="width:60%; border:0; border-top:1px solid #000; margin-bottom:4pt;">
    <p>Assinatura do(a) Requerente ou Testemunha a Rogo</p>
</div>
HTML;

/* ---------- TCPDF ------------------------------------------------- */
$pdf = new TCPDF('P','mm','A4',true,'UTF-8',false);
$pdf->SetMargins(25,25,25);
$pdf->SetAutoPageBreak(true,20);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();
$pdf->writeHTML($html, true, false, true, false, '');

/* ---------- exibir inline (para imprimir) ------------------------ */
$pdf->Output("Requerimento_{$reg['protocolo']}.pdf", 'I');  // 'I' = inline
