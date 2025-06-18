<?php
ob_start();
date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ERROR);

require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

function log_msg($m){ error_log('[mandados] '.date('Y-m-d H:i:s')." $m"); }
function err_msg($c){ return match($c){
    UPLOAD_ERR_INI_SIZE=>'Excede o limite de upload.',
    UPLOAD_ERR_FORM_SIZE=>'Arquivo muito grande.',
    UPLOAD_ERR_NO_FILE  =>'Nenhum arquivo enviado.',
    default             =>'Erro desconhecido no upload.',
};}
function pdf2jpg($pdf,$destDir){
    if(!file_exists($destDir)) mkdir($destDir,0777,true);
    exec("magick convert -density 150 -alpha remove -alpha off -quality 90 \"$pdf\" \"$destDir/page_%04d.jpg\"",$o,$c);
    if($c) return [];
    $imgs=glob("$destDir/page_*.jpg"); natsort($imgs); return $imgs;
}

$json = ['success'=>false,'message'=>'','erros'=>[]];
if ($_SERVER['REQUEST_METHOD']!=='POST'){ finish('Método inválido.'); }

$id = intval($_POST['mandado_id'] ?? 0);
if(!$id) finish('Mandado inválido.');

$mand = $pdo->prepare("SELECT codigo_rastreabilidade FROM mandados WHERE id=?");
$mand->execute([$id]); $mand = $mand->fetch();
if(!$mand) finish('Mandado não encontrado.');

$base = "uploads/mandados/{$mand['codigo_rastreabilidade']}";
if(!file_exists($base)) mkdir($base,0755,true);

$f = $_FILES['arquivos'] ?? null;
if(!$f || empty($f['name'][0])) finish('Nenhum arquivo recebido.');

$total = count($f['name']); $ok=0;
for($i=0;$i<$total;$i++){
    if($f['error'][$i]!==UPLOAD_ERR_OK){ $json['erros'][]=$f['name'][$i].': '.err_msg($f['error'][$i]); continue; }

    $ext  = strtolower(pathinfo($f['name'][$i], PATHINFO_EXTENSION));
    $novo = uniqid().'.'.$ext; $dest="$base/$novo";
    if(!move_uploaded_file($f['tmp_name'][$i],$dest)){ $json['erros'][]=$f['name'][$i].': falha ao mover.'; continue;}

    $dirImgs=null; $imgs=[];
    if($ext==='pdf'){ $dirImgs="$base/".pathinfo($novo,PATHINFO_FILENAME); $imgs=pdf2jpg($dest,$dirImgs); }

    try{
        $pdo->beginTransaction();
        $pdo->prepare("
            INSERT INTO mandados_anexos
            (mandado_id,nome_arquivo,caminho,tipo,tamanho,diretorio_imagens)
            VALUES (?,?,?,?,?,?)
        ")->execute([$id,$f['name'][$i],$dest,$f['type'][$i],$f['size'][$i],$dirImgs]);

        $anexoId=$pdo->lastInsertId();
        if($imgs){
            $ins=$pdo->prepare("INSERT INTO mandados_imagens_anexo (anexo_id,caminho,ordem) VALUES (?,?,?)");
            foreach($imgs as $k=>$img) $ins->execute([$anexoId,$img,$k+1]);
        }
        $pdo->commit(); $ok++;
    }catch(Exception $e){
        $pdo->rollBack(); @unlink($dest); foreach($imgs as $im) @unlink($im);
        $json['erros'][]=$f['name'][$i].': '.$e->getMessage();
    }
}
if($ok){
    $json['success']=true;
    $json['message']="$ok arquivo(s) enviado(s) com sucesso.";
} else {
    $json['message']='Nenhum arquivo processado.';
}
header('Content-Type: application/json'); echo json_encode($json); exit;

function finish($m){ header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>$m]); exit; }
