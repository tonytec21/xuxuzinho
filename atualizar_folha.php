<?php
/**************************************************************************
 * ATUALIZAR_FOLHA.PHP
 * ---------------------------------------------------------------
 *  • POST:  livro_id, pagina_id, termo_inicial, termo_final
 *  • Atualiza a página editada; só RESEQUENCIA as posteriores
 *    ─ Se termo_inicial == termo_final  ➜  trata como “duplicado”
 *      → grava apenas essa linha, não recalcula nada depois.
 *  • Retorna JSON   { success:bool, message?:str, paginas?:array }
 *************************************************************************/
ob_start();
date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ERROR | E_PARSE);          // só erros fatais na saída

require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

header('Content-Type: application/json');

/* ───────────────────── 1) VALIDAÇÃO ───────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Método não permitido.']); exit;
}

$livro_id   = filter_input(INPUT_POST,'livro_id',      FILTER_VALIDATE_INT);
$pagina_id  = filter_input(INPUT_POST,'pagina_id',     FILTER_VALIDATE_INT);
$termo_ini  = filter_input(INPUT_POST,'termo_inicial', FILTER_VALIDATE_INT);
$termo_fim  = filter_input(INPUT_POST,'termo_final',   FILTER_VALIDATE_INT);

if(!$livro_id || !$pagina_id || $termo_ini===false || $termo_fim===false){
    echo json_encode(['success'=>false,'message'=>'Parâmetros inválidos.']); exit;
}

try{
    $pdo->beginTransaction();

    /* ───────────────────── 2) CONFIG. DO LIVRO ───────────────────── */
    $cfg = $pdo->prepare("
        SELECT termos_por_pagina, contagem_frente_verso
          FROM livros WHERE id = ?");
    $cfg->execute([$livro_id]);
    $livro = $cfg->fetch(PDO::FETCH_ASSOC);
    if(!$livro) throw new Exception('Livro não encontrado.');

    $Tpp = (int)$livro['termos_por_pagina'];       // termos por página
    $FV  = (int)$livro['contagem_frente_verso'];   // 1 = frente/verso

    /* ───────────────────── 3) ATUALIZA PÁGINA EDITADA ───────────────────── */
    $qEdit = $pdo->prepare("
        UPDATE paginas_livro pl
           JOIN anexos_livros al ON al.id = pl.anexo_id AND al.livro_id = ?
           SET pl.termo_inicial = ?, pl.termo_final = ?
         WHERE pl.id = ?");

    /* • Caso DUPLICADO (ini == fim) → grava e SAI sem recalcular */
    if($termo_ini == $termo_fim){
        $qEdit->execute([$livro_id,$termo_ini,$termo_fim,$pagina_id]);
        if(!$qEdit->rowCount()) throw new Exception('Página não pertence ao livro.');

        $pdo->commit();
        /* snapshot para a interface */
        $snap = $pdo->prepare("
            SELECT pl.id, pl.numero_pagina, pl.numero_folha,
                   pl.termo_inicial, pl.termo_final
              FROM paginas_livro pl
              JOIN anexos_livros al ON al.id = pl.anexo_id
             WHERE al.livro_id = ?
          ORDER BY pl.numero_pagina");
        $snap->execute([$livro_id]);

        echo json_encode(['success'=>true,'paginas'=>$snap->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    /* • Sequência NORMAL (ini ≠ fim) → força consistência */
    $termo_fim = $termo_ini + $Tpp - 1;              // garante tamanho exato
    $qEdit->execute([$livro_id,$termo_ini,$termo_fim,$pagina_id]);
    if(!$qEdit->rowCount()) throw new Exception('Página não pertence ao livro.');

    /* ───────────────────── 4) BUSCA TODAS AS PÁGINAS ───────────────────── */
    $sel = $pdo->prepare("
        SELECT pl.id, pl.numero_pagina, pl.numero_folha,
               pl.termo_inicial,  pl.termo_final
          FROM paginas_livro pl
          JOIN anexos_livros al ON al.id = pl.anexo_id
         WHERE al.livro_id = ?
      ORDER BY pl.numero_pagina");
    $sel->execute([$livro_id]);
    $pages = $sel->fetchAll(PDO::FETCH_ASSOC);

    /* ───────────────────── 5) PREP UPDATE GLOBAL ───────────────────── */
    $qUpd = $pdo->prepare("
        UPDATE paginas_livro
           SET termo_inicial = ?, termo_final = ?, numero_folha = ?
         WHERE id = ?");

    /* ───────────────────── 6) RESEQUENCIAMENTO ───────────────────── */
    $found   = false;
    $prevFim = null;
    $idx     = 0;                                     // índice 0-based

    foreach($pages as $pg){
        $isEdit = ($pg['id'] == $pagina_id);

        /*  A) Antes da folha editada – não mexe  */
        if(!$found && !$isEdit){
            $prevFim = $pg['termo_final'];
            $idx++; continue;
        }

        /*  B) Folha editada – já temos valores corretos */
        if($isEdit){
            $newIni = $termo_ini;
            $newFim = $termo_fim;
            $prevFim = $newFim;
            $found = true;
        }else{  /*  C) Posteriors – recalcular em cadeia */
            $newIni = $prevFim + 1;
            $newFim = $newIni + $Tpp - 1;
            $prevFim = $newFim;
        }

        /* número da folha considerando frente/verso */
        $newFolha = $FV ? (intdiv($idx,2) + 1) : ($idx + 1);

        if($newIni != $pg['termo_inicial'] ||
           $newFim != $pg['termo_final']   ||
           $newFolha != $pg['numero_folha']){
            $qUpd->execute([$newIni,$newFim,$newFolha,$pg['id']]);
        }
        $idx++;
    }

    $pdo->commit();

    /* ───────────────────── 7) SNAPSHOT FINAL ───────────────────── */
    $sel->execute([$livro_id]);
    echo json_encode(['success'=>true,'paginas'=>$sel->fetchAll(PDO::FETCH_ASSOC)]);

}catch(Exception $e){
    $pdo->rollBack();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
