<?php
/* ------ caminhos -------------------- */
$PDFTOPPM_PATH = '"C:\\Program Files\\poppler-24.08.0\\Library\\bin\\pdftoppm.exe"';
$TESSERACT_PATH = '"C:\\Program Files\\Tesseract-OCR\\tesseract.exe"';
$tmpDir = sys_get_temp_dir();
/* ------------------------------------ */

date_default_timezone_set('America/Sao_Paulo');

/* 1. arquivo PDF ---------------------------------------------------- */
function getPdfPath(): string
{
    if (PHP_SAPI === 'cli') {
        global $argv;
        if (empty($argv[1])) exit("Uso: php memorial_ocr.php arquivo.pdf\n");
        if (!is_readable($argv[1])) exit("PDF não encontrado.\n");
        return realpath($argv[1]);
    }
    if (!isset($_FILES['memorial']) || $_FILES['memorial']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        exit('Falha no upload do PDF.');
    }
    return $_FILES['memorial']['tmp_name'];
}

/* 2. PDF → PNG ------------------------------------------------------ */
function pdfToPng(string $pdf, string $prefix, string $pdftoppm): string
{
    $cmd = "$pdftoppm -png -r 300 -singlefile ".escapeshellarg($pdf).' '.escapeshellarg($prefix);
    exec($cmd, $_, $s);
    if ($s) exit("Erro no pdftoppm.\n");
    return $prefix.'.png';
}

/* 3. OCR ------------------------------------------------------------ */
function runTesseract(string $png, string $prefix, string $tesseract): string
{
    $cmd = "$tesseract ".escapeshellarg($png).' '.escapeshellarg($prefix).' -l por';
    exec($cmd, $_, $s);
    if ($s || !file_exists($prefix.'.txt')) exit("Erro no Tesseract.\n");
    return file_get_contents($prefix.'.txt');
}

/* 4. PARSE ---------------------------------------------------------- */
function parseCabecalho(string $t): array
{
    $rx = [
        'denominacao'  => '/Denomin[aã]ção:\s*(.+)/iu',
        'proprietario' => '/Propriet[aá]rio:\s*(.+)/iu',
        'municipio'    => '/Munic[ií]pio\/UF:\s*(.+)/iu',
        'cpf'          => '/CPF:\s*([\d\.\-]+)/iu',
        'area'         => '/área .*?:\s*([\d\.,]+\s*ha)/iu',
        'perimetro'    => '/Per[ií]metro\s*\(m\):\s*([\d\.,]+\s*m)/iu',
        'responsavel'  => '/Respons[aá]vel T[eé]cnico:\s*(.+)/iu',
        'crea'         => '/CREA:\s*(.+)/iu',
        'art'          => '/A\.R\.T\.:?\s*(.+)/iu'
    ];
    $d = [];
    foreach ($rx as $k=>$r) if (preg_match($r,$t,$m)) $d[$k]=trim($m[1]);
    return $d;
}

function parseVertices(string $txt): array
{
    /* pega só o bloco que começa na palavra VÉRTICE (para evitar ruído) */
    if (!preg_match('/V[ÉE]RTICE(.+?)$/ismu', $txt, $m)) return [];
    $lines = preg_split('/\R/u', $m[1]);

    $out = [];
    foreach ($lines as $raw) {
        $raw = trim($raw);
        if (!preg_match('/^E[0-9A-Z\-]+/u', $raw)) continue; // só linhas que começam com código de vértice

        /* quebra por pelo menos 2 espaços ou tabulação   */
        $cols = preg_split('/[ \t]{2,}/u', $raw);
        /* se ainda ficou “colado”, cai para quebra simples */
        if (count($cols) < 8) $cols = preg_split('/\s+/u', $raw);
        if (count($cols) < 8) continue;  // linha fora do padrão

        [$v1,$lon,$lat,$alt,$v2,$az,$dist] = array_slice($cols,0,7);
        $conf = implode(' ', array_slice($cols,7));

        $out[] = [
            'v1'        => $v1,
            'long'      => $lon,
            'lat'       => $lat,
            'alt'       => str_replace(',', '.', $alt),
            'v2'        => $v2,
            'azimute'   => str_replace('º', '°', $az),
            'dist'      => str_replace(',', '.', $dist),
            'confronto' => $conf,
        ];
    }
    return $out;
}

/* 5. MEMORIAL ------------------------------------------------------- */
function buildMemorial(array $cab, array $v): string
{
    /* 5.1 cabeçalho */
    $h = [];
    if ($cab['denominacao']??null) $h[] = "denominado **{$cab['denominacao']}**";
    if ($cab['area']??null)        $h[] = "com área de {$cab['area']}";
    if ($cab['municipio']??null)   $h[] = "situado em {$cab['municipio']}";
    if ($cab['proprietario']??null)$h[] = "pertencente a {$cab['proprietario']}";
    if ($cab['cpf']??null)         $h[] = "(CPF {$cab['cpf']})";
    if ($cab['perimetro']??null)   $h[] = "perímetro {$cab['perimetro']}";
    $txt[] = ucfirst(implode(', ', $h)).'.';

    /* 5.2 responsável */
    if ($cab['responsavel']??null) {
        $r = "Responsável técnico: {$cab['responsavel']}";
        if ($cab['crea']??null) $r .= " – CREA {$cab['crea']}";
        if ($cab['art']??null)  $r .= " – ART {$cab['art']}";
        $txt[] = $r.'.';
    }

    /* 5.3 descrição da parcela */
    if ($v) {
        $seq = [];
        $primeiro = $v[0]['v1'];
        $seq[] = "Inicia-se no vértice {$primeiro} ({$v[0]['long']} WGr / {$v[0]['lat']} S).";

        foreach ($v as $seg) {
            $seq[] = "Do vértice {$seg['v1']}, segue-se pelo azimute {$seg['azimute']} na distância de "
                   . number_format($seg['dist'],2,',','.')." m até o vértice {$seg['v2']}, "
                   . "confrontando-se com {$seg['confronto']};";
        }
        /* garante que termina com ponto */
        $ult = array_pop($seq);
        $seq[] = rtrim($ult,';').'.';

        /* se último vértice não for o primeiro, fecha o polígono */
        $endVertex = end($v)['v2'];
        if ($endVertex !== $primeiro) {
            $seq[] = "Finalmente, retorna-se ao vértice {$primeiro}, fechando o perímetro.";
        }
        $txt[] = implode(' ', $seq);
    }

    return implode("\n\n", $txt);
}

/* 6. execução ------------------------------------------------------- */
$pdf   = getPdfPath();
$uid   = uniqid('sigef_');
$base  = $tmpDir.DIRECTORY_SEPARATOR.$uid;
$png   = pdfToPng($pdf,$base,$PDFTOPPM_PATH);
$ocr   = runTesseract($png,$base,$TESSERACT_PATH);

$cab   = parseCabecalho($ocr);
$verts = parseVertices($ocr);
$out   = buildMemorial($cab,$verts);

/* 7. saída */
if (PHP_SAPI==='cli') {
    echo "\n===== MEMORIAL DESCRITIVO =====\n\n$out\n";
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo $out;
}

/* 8. limpeza */
@unlink($png);
@unlink($base.'.txt');
