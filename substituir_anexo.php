<?php
/**************************************************************************
 * SUBSTITUIR_ANEXO.PHP
 *  – recebe: livro_id, pagina_id, novo_arquivo
 *  – move o antigo p/ .../imagens_antigas/  e grava o novo
 *************************************************************************/
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD']!=='POST'){
    echo json_encode(['success'=>false,'message'=>'Método não permitido']);exit;
}

$livro_id  = filter_input(INPUT_POST,'livro_id', FILTER_VALIDATE_INT);
$pagina_id = filter_input(INPUT_POST,'pagina_id',FILTER_VALIDATE_INT);
if(!$livro_id||!$pagina_id||empty($_FILES['novo_arquivo']['name'])){
    echo json_encode(['success'=>false,'message'=>'Parâmetros inválidos']);exit;
}

/* 1. Descobre o anexo / caminho atual */
$sql = "
    SELECT p.caminho, a.id AS anexo_id
      FROM paginas_livro p
      JOIN anexos_livros a ON a.id = p.anexo_id
     WHERE p.id = ? AND a.livro_id = ?";
$stmt=$pdo->prepare($sql);
$stmt->execute([$pagina_id,$livro_id]);
$info=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$info){echo json_encode(['success'=>false,'message'=>'Página não localiza­da']);exit;}

$oldPath = $info['caminho'];
$anexoId = $info['anexo_id'];

/* 2. Diretórios */
$dirLivro = dirname(dirname($oldPath)).'/';          // .../livro_x/
$dirPag   = $dirLivro.'paginas/';
$dirAnt   = $dirLivro.'imagens_antigas/';

if(!is_dir($dirAnt)) mkdir($dirAnt,0777,true);

/* 3. Move o antigo */
$basename = basename($oldPath);
$destAnt  = $dirAnt.$basename.'-'.time();
if(!rename($oldPath,$destAnt)){
    echo json_encode(['success'=>false,'message'=>'Falha ao mover arquivo antigo']);exit;
}

/* 4. Salva o novo arquivo */
$ext  = strtolower(pathinfo($_FILES['novo_arquivo']['name'],PATHINFO_EXTENSION));
$novoNome = 'pagina_'.sprintf("%04d", $pagina_id).'.'.$ext;
$novoPath = $dirPag.$novoNome;

if(!move_uploaded_file($_FILES['novo_arquivo']['tmp_name'],$novoPath)){
    /* tenta voltar o antigo */
    rename($destAnt,$oldPath);
    echo json_encode(['success'=>false,'message'=>'Falha ao gravar novo arquivo']);exit;
}
chmod($novoPath,0666);

/* 5. Atualiza banco  (caminho no anexo + página) */
$pdo->beginTransaction();
try{
    $q = $pdo->prepare("UPDATE anexos_livros SET caminho=? WHERE id=?");
    $q->execute([$novoPath,$anexoId]);

    $q = $pdo->prepare("UPDATE paginas_livro SET caminho=? WHERE id=?");
    $q->execute([$novoPath,$pagina_id]);

    $pdo->commit();
    echo json_encode([
        'success'=>true,
        'novo_caminho'=>$novoPath
    ]);
}catch(Exception $e){
    $pdo->rollBack();
    /* reverte arquivos */
    rename($novoPath,$dirAnt.'falho_'.basename($novoPath));
    rename($destAnt,$oldPath);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
