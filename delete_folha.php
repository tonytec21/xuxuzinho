<?php
/**************************************************************************
 * DELETE_FOLHA.PHP
 * -----------------------------------------------------------------------
 * • POST : livro_id , pagina_id
 * • Remove a página da tabela *paginas_livro* **sem** recalc-sequência
 *   e move o arquivo para  …/uploads/livro_<ID>/imagens_excluidas/
 * • Retorna JSON { success:bool , message?:str }
 *************************************************************************/
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

header('Content-Type: application/json');

/* ─── 1. Validação --------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Método não permitido']); exit;
}
$livro_id  = filter_input(INPUT_POST,'livro_id', FILTER_VALIDATE_INT);
$pagina_id = filter_input(INPUT_POST,'pagina_id',FILTER_VALIDATE_INT);
if(!$livro_id || !$pagina_id){
    echo json_encode(['success'=>false,'message'=>'Parâmetros inválidos']); exit;
}

try{
    /* ─── 2. Seleciona a página / caminho ---------------------------- */
    $sql = "
        SELECT pl.id, pl.caminho, al.livro_id
          FROM paginas_livro pl
          JOIN anexos_livros al ON al.id = pl.anexo_id
         WHERE pl.id = ? AND al.livro_id = ?";
    $st  = $pdo->prepare($sql);
    $st->execute([$pagina_id,$livro_id]);
    $pg  = $st->fetch(PDO::FETCH_ASSOC);
    if(!$pg) throw new Exception('Página não encontrada');

    $origPath = $pg['caminho'];
    if(!file_exists($origPath))   // se já sumiu, prossegue só com BD
        $origPath = null;

    /* ─── 3. Move arquivo para …/imagens_excluidas ------------------- */
    if($origPath){
        $baseDir  = dirname(dirname($origPath));          // …/uploads/livro_<ID>
        $trashDir = $baseDir.'/imagens_excluidas';

        if(!is_dir($trashDir)){
            mkdir($trashDir,0777,true);
        }
        $dest = $trashDir.'/'.basename($origPath);

        /* evita colisão de nome */
        if(file_exists($dest)){
            $dest = $trashDir.'/'.time().'_'.basename($origPath);
        }
        rename($origPath,$dest);          // move p/ “lixeira”
    }

    /* ─── 4. Apaga o registro --------------------------------------- */
    $del = $pdo->prepare("DELETE FROM paginas_livro WHERE id=?");
    $del->execute([$pagina_id]);

    echo json_encode(['success'=>true]);

}catch(Exception $e){
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
