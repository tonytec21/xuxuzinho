<?php
ob_start();
date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ERROR);

require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

/* ---------- utilidades -------------------------------------------- */
function log_message($m){ error_log('['.date('Y-m-d H:i:s')."] $m"); }
function up_err($c){ return match($c){
    UPLOAD_ERR_INI_SIZE =>'Arquivo excede o limite permitido.',
    UPLOAD_ERR_FORM_SIZE=>'Arquivo muito grande.',
    UPLOAD_ERR_NO_FILE  =>'Nenhum arquivo enviado.',
    default             =>'Erro desconhecido no upload.',
};}
function convert_pdf_to_jpg($pdf,$dir){
    if(!file_exists($dir)) mkdir($dir,0777,true);
    $pat="$dir/page_%04d.jpg";
    exec("magick convert -density 150 -background white -alpha remove -alpha off -quality 90 \"$pdf\" \"$pat\"",$o,$code);
    if($code){ exec("convert -density 150 -background white -alpha remove -alpha off -quality 90 \"$pdf\" \"$pat\"",$o2,$code2); if($code2) return []; }
    $imgs=glob("$dir/page_*.jpg"); natsort($imgs); return $imgs;
}

/* ---------- resposta default -------------------------------------- */
$resp=['success'=>false,'message'=>'','erros'=>[],'arquivos_processados'=>0];

/* ---------- validações iniciais ----------------------------------- */
if($_SERVER['REQUEST_METHOD']!=='POST'){ finish('Requisição inválida.'); }
$registro_id=intval($_POST['registro_id']??0);
if(!$registro_id){ finish('Registro inválido.'); }

$stmt=$pdo->prepare("SELECT protocolo FROM triagem_registros WHERE id=?");
$stmt->execute([$registro_id]);
$reg=$stmt->fetch();
if(!$reg){ finish('Registro não encontrado.'); }

$protocolo=$reg['protocolo'];
$base_dir="uploads/triagem/$protocolo";
if(!file_exists($base_dir)) mkdir($base_dir,0755,true);

$files=$_FILES['arquivos']??null;
if(!$files || empty($files['name'][0])) finish('Nenhum arquivo recebido.');

$total=count($files['name']);
$ok=0; $total_imgs=0;

/* ---------- loop de arquivos -------------------------------------- */
for($i=0;$i<$total;$i++){
    $nome=$files['name'][$i];
    $tmp =$files['tmp_name'][$i];
    $tipo=$files['type'][$i];
    $err =$files['error'][$i];
    $tam =$files['size'][$i];

    if($err!==UPLOAD_ERR_OK){ $resp['erros'][]="$nome: ".up_err($err); continue; }

    $ext=strtolower(pathinfo($nome,PATHINFO_EXTENSION));
    $novo=uniqid().'.'.$ext;
    $dest="$base_dir/$novo";
    if(!move_uploaded_file($tmp,$dest)){ $resp['erros'][]="$nome: falha ao mover."; continue; }

    $dir_imgs=null; $imgs=[];
    if($ext==='pdf'){
        $dir_imgs="$base_dir/".pathinfo($novo,PATHINFO_FILENAME);
        $imgs    =convert_pdf_to_jpg($dest,$dir_imgs);
        $total_imgs+=count($imgs);
    }

    try{
        $pdo->beginTransaction();
        $pdo->prepare("
            INSERT INTO triagem_anexos (registro_id,nome_arquivo,caminho,tipo,tamanho,data_upload,diretorio_imagens)
            VALUES (?,?,?,?,?,NOW(),?)
        ")->execute([$registro_id,$nome,$dest,$tipo,$tam,$dir_imgs]);
        $anexo_id=$pdo->lastInsertId();

        if($imgs){
            $ins=$pdo->prepare("INSERT INTO triagem_imagens_anexo (anexo_id,caminho,ordem) VALUES (?,?,?)");
            foreach($imgs as $k=>$img) $ins->execute([$anexo_id,$img,$k+1]);
        }
        $pdo->commit(); $ok++;
    }catch(Exception $e){
        $pdo->rollBack();
        @unlink($dest);
        foreach($imgs as $im) @unlink($im);
        $resp['erros'][]="$nome: erro ao salvar.";
    }
}

/* ---------- preparar mensagem ------------------------------------- */
if($ok){
    $resp['success']=true;
    $resp['message']="$ok arquivo(s) enviado(s) com sucesso.";
    if($total_imgs) $resp['message'].=" $total_imgs imagem(ns) extraída(s) de PDF(s).";
}else{
    $resp['message']='Nenhum arquivo foi processado.';
}

/* ---------- retorna resultado em JSON ----------------------------- */  
$resp['arquivos_processados'] = $ok;  
header('Content-Type: application/json');  
echo json_encode($resp);  
exit;  

/* ---------- helper ------------------------------------------------ */  
function finish($msg){  
    header('Content-Type: application/json');  
    echo json_encode(['success' => false, 'message' => $msg, 'erros' => [$msg]]);  
    exit;  
}
