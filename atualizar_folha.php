<?php
/**************************************************************************
 * ATUALIZAR_FOLHA.PHP  –  corrige termos e/ou número de folha
 * ---------------------------------------------------------------
 *  • POST: livro_id, pagina_id, termo_inicial, termo_final, numero_folha
 *  • Recalcula apenas as páginas posteriores
 *      → Se termo_inicial == termo_final   ➜   NÃO mexe nos termos seguintes
 *      → Número da folha SEMPRE é recalculado a partir da folha editada
 *  • Retorna JSON { success, message?, paginas? }
 *************************************************************************/
ob_start();
date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ERROR | E_PARSE);

require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

header('Content-Type: application/json');

/* ───── 1. Validação rápida ────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Método não permitido']); exit;
}

$livro_id     = filter_input(INPUT_POST,'livro_id',      FILTER_VALIDATE_INT);
$pagina_id    = filter_input(INPUT_POST,'pagina_id',     FILTER_VALIDATE_INT);
$termo_ini    = filter_input(INPUT_POST,'termo_inicial', FILTER_VALIDATE_INT);
$termo_fim_in = filter_input(INPUT_POST,'termo_final',   FILTER_VALIDATE_INT);
$num_folha_in = filter_input(INPUT_POST,'numero_folha',  FILTER_VALIDATE_INT);

if(!$livro_id||!$pagina_id||$termo_ini===false||$termo_fim_in===false||$num_folha_in===false){
    echo json_encode(['success'=>false,'message'=>'Parâmetros inválidos']); exit;
}

try{
    $pdo->beginTransaction();

    /* ───── 2. Metadados do livro ─────────────────────────────────── */
    $meta = $pdo->prepare("
        SELECT termos_por_pagina, contagem_frente_verso
          FROM livros WHERE id = ?");
    $meta->execute([$livro_id]);
    $livro = $meta->fetch(PDO::FETCH_ASSOC);
    if(!$livro) throw new Exception('Livro não encontrado');

    $TPP = (int) $livro['termos_por_pagina'];      // termos/página
    $FV  = (int) $livro['contagem_frente_verso'];  // 1 = frente/verso

    /* ───── 3. Atualiza a página editada ──────────────────────────── */
    $qEdit = $pdo->prepare("
        UPDATE paginas_livro pl
           JOIN anexos_livros al ON al.id = pl.anexo_id AND al.livro_id = ?
           SET pl.termo_inicial = ?, pl.termo_final = ?, pl.numero_folha = ?
         WHERE pl.id = ?");
    $qEdit->execute([$livro_id,$termo_ini,$termo_fim_in,$num_folha_in,$pagina_id]);
    if(!$qEdit->rowCount()) throw new Exception('Página não pertence ao livro');

    /* ───── 4. Carrega todas as páginas em ordem ──────────────────── */
    $sel = $pdo->prepare("
        SELECT pl.id, pl.numero_pagina, pl.numero_folha,
               pl.termo_inicial, pl.termo_final
          FROM paginas_livro pl
          JOIN anexos_livros al ON al.id = pl.anexo_id
         WHERE al.livro_id = ?
      ORDER BY pl.numero_pagina");
    $sel->execute([$livro_id]);
    $pages = $sel->fetchAll(PDO::FETCH_ASSOC);

    /* recalcTerms = TRUE se termos devem ser propagados               */
    $recalcTerms = ($termo_ini != $termo_fim_in);

    /* ───── 5. Preparação do UPDATE para loop ─────────────────────── */
    $qUpd = $pdo->prepare("
        UPDATE paginas_livro
           SET numero_folha = ?, termo_inicial = ?, termo_final = ?
         WHERE id = ?");

    /* ───── 6. Loop de resequência ────────────────────────────────── */
    $found = false;
    $prevFim = null;
    $k = 0;                             // índice relativo à página editada

    foreach($pages as $pg){
        $isEdit = ($pg['id'] == $pagina_id);

        /* antes da página editada – nada muda */
        if(!$found && !$isEdit){
            $prevFim = $pg['termo_final'];
            continue;
        }

        /* define folha corrente de acordo com frente/verso            */
        $newFolha = $FV ? (intdiv($k,2) + $num_folha_in) : ($k + $num_folha_in);

        /* valores de termos */
        if($isEdit){
            $newIni = $termo_ini;
            $newFim = $termo_fim_in;
            $prevFim = $newFim;
            $found = true;
            $k++;                                     // próxima posição
            continue;                                 // já salvo acima
        }

        /* páginas posteriores                                         */
        $newIni = $pg['termo_inicial'];
        $newFim = $pg['termo_final'];

        if($recalcTerms){
            $newIni = $prevFim + 1;
            $newFim = $newIni + $TPP - 1;
            $prevFim = $newFim;
        }

        if($newFolha != $pg['numero_folha'] ||
           $newIni   != $pg['termo_inicial'] ||
           $newFim   != $pg['termo_final']){
            $qUpd->execute([$newFolha,$newIni,$newFim,$pg['id']]);
        }
        $k++;
    }

    $pdo->commit();

    /* ───── 7. Snapshot final ─────────────────────────────────────── */
    $sel->execute([$livro_id]);
    echo json_encode(['success'=>true,'paginas'=>$sel->fetchAll(PDO::FETCH_ASSOC)]);

}catch(Exception $e){
    $pdo->rollBack();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
