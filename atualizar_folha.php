<?php
/**************************************************************************
 * ATUALIZAR_FOLHA.PHP
 * -----------------------------------------------------------------------
 * • POST: livro_id, pagina_id, termo_inicial, termo_final,
 *         numero_folha, eh_verso
 * • Regra nova:
 *     – Se o livro tiver 1 termo/página ➜ sempre recalcula termos.
 *     – Se tiver >1 termo/página ➜ só recalcula quando
 *       termo_inicial ≠ termo_final (evita “páginas duplicadas”).
 *************************************************************************/
ob_start();
date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ERROR | E_PARSE);

require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

header('Content-Type: application/json');

/* ───── 1. Validação -------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Método não permitido']); exit;
}

$livro_id   = filter_input(INPUT_POST,'livro_id',      FILTER_VALIDATE_INT);
$pagina_id  = filter_input(INPUT_POST,'pagina_id',     FILTER_VALIDATE_INT);
$termo_ini  = filter_input(INPUT_POST,'termo_inicial', FILTER_VALIDATE_INT);
$termo_fim  = filter_input(INPUT_POST,'termo_final',   FILTER_VALIDATE_INT);
$num_folha  = filter_input(INPUT_POST,'numero_folha',  FILTER_VALIDATE_INT);
$eh_verso   = filter_input(INPUT_POST,'eh_verso',      FILTER_VALIDATE_INT);

if($livro_id===false||$pagina_id===false||$termo_ini===false||
   $termo_fim===false||$num_folha===false||$eh_verso===false){
    echo json_encode(['success'=>false,'message'=>'Parâmetros inválidos']); exit;
}

$eh_verso = $eh_verso ? 1 : 0;   // normaliza

try{
    $pdo->beginTransaction();

    /* ───── 2. Metadados do livro ----------------------------------- */
    $meta = $pdo->prepare("
        SELECT termos_por_pagina, contagem_frente_verso
          FROM livros WHERE id=?");
    $meta->execute([$livro_id]);
    $livro = $meta->fetch(PDO::FETCH_ASSOC);
    if(!$livro) throw new Exception('Livro não encontrado');

    $TPP = (int)$livro['termos_por_pagina'];      // termos/página
    $FV  = (int)$livro['contagem_frente_verso'];  // 1 = frente/verso

    if(!$FV) $eh_verso = 0;                       // livro sem verso → força 0

    /* ───── 3. Atualiza a página editada ----------------------------- */
    $edit = $pdo->prepare("
        UPDATE paginas_livro pl
           JOIN anexos_livros al ON al.id = pl.anexo_id AND al.livro_id = ?
           SET pl.termo_inicial=?, pl.termo_final=?,
               pl.numero_folha=?, pl.eh_verso=?
         WHERE pl.id = ?");
    $edit->execute([$livro_id,$termo_ini,$termo_fim,$num_folha,$eh_verso,$pagina_id]);
    if(!$edit->rowCount()) throw new Exception('Página não pertence ao livro');

    /* ───── 4. Decidir se recalcula termos --------------------------- */
    $skipTermRecalc = ($TPP > 1 && $termo_ini == $termo_fim);

    if(!$skipTermRecalc){
        /* força consistência do termo_final */
        $termo_fim = $termo_ini + $TPP - 1;
        $edit->execute([$livro_id,$termo_ini,$termo_fim,$num_folha,$eh_verso,$pagina_id]);
    }

    /* ───── 5. Carrega páginas em ordem ------------------------------ */
    $sel = $pdo->prepare("
        SELECT pl.id, pl.numero_pagina, pl.numero_folha,
               pl.eh_verso, pl.termo_inicial, pl.termo_final
          FROM paginas_livro pl
          JOIN anexos_livros al ON al.id = pl.anexo_id
         WHERE al.livro_id = ?
      ORDER BY pl.numero_pagina");
    $sel->execute([$livro_id]);
    $pages = $sel->fetchAll(PDO::FETCH_ASSOC);

    $upd = $pdo->prepare("
        UPDATE paginas_livro
           SET numero_folha=?, eh_verso=?, termo_inicial=?, termo_final=?
         WHERE id=?");

    /* ───── 6. Resequenciar posteriores ------------------------------ */
    $sequence = false;
    $prevFim   = $termo_fim;
    $curFolha  = $num_folha;
    $curSide   = $eh_verso;

    foreach($pages as $pg){
        if($pg['id'] == $pagina_id){ $sequence = true; continue; }
        if(!$sequence) continue;

        /* lado e folha */
        if($FV){
            if($curSide == 0){ $curSide = 1; }
            else { $curSide = 0; $curFolha++; }
        }else{
            $curSide = 0;
            $curFolha++;
        }

        /* termos */
        $newIni = $pg['termo_inicial'];
        $newFim = $pg['termo_final'];
        if(!$skipTermRecalc){
            $newIni = $prevFim + 1;
            $newFim = $newIni + $TPP - 1;
            $prevFim = $newFim;
        }

        if($curFolha != $pg['numero_folha'] ||
           $curSide  != $pg['eh_verso']     ||
           $newIni   != $pg['termo_inicial']||
           $newFim   != $pg['termo_final']){
            $upd->execute([$curFolha,$curSide,$newIni,$newFim,$pg['id']]);
        }
    }

    $pdo->commit();

    /* ───── 7. Snapshot --------------------------------------------- */
    $sel->execute([$livro_id]);
    echo json_encode(['success'=>true,'paginas'=>$sel->fetchAll(PDO::FETCH_ASSOC)]);

}catch(Exception $e){
    $pdo->rollBack();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
