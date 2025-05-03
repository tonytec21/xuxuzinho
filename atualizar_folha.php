<?php
/**************************************************************************
 * ATUALIZAR_FOLHA.PHP
 * -----------------------------------------------------------------------
 *  • POST:  livro_id, pagina_id, termo_inicial, termo_final,
 *           numero_folha, eh_verso   (eh_verso: 0-Frente | 1-Verso)
 *
 *  • Grava página editada e reajusta APENAS as seguintes:
 *        – Sempre renumera folhas e lados
 *        – Renumera termos só quando termo_inicial ≠ termo_final
 *  • JSON de saída: { success, message?, paginas? }
 *************************************************************************/
ob_start();
date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ERROR | E_PARSE);

require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

header('Content-Type: application/json');

/* ───── 1. Validação rápida ───────────────────────────────────────── */
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

$eh_verso = $eh_verso ? 1 : 0;                        // normaliza

try{
    $pdo->beginTransaction();

    /* ───── 2. Metadados do livro ────────────────────────────────── */
    $meta = $pdo->prepare("
        SELECT termos_por_pagina, contagem_frente_verso
          FROM livros WHERE id=?");
    $meta->execute([$livro_id]);
    $livro = $meta->fetch(PDO::FETCH_ASSOC);
    if(!$livro) throw new Exception('Livro não encontrado');

    $TPP = (int)$livro['termos_por_pagina'];          // termos/página
    $FV  = (int)$livro['contagem_frente_verso'];      // 1=usa verso

    /* Quando o livro NÃO conta verso, força eh_verso = 0            */
    if(!$FV) $eh_verso = 0;

    /* ───── 3. Atualiza a página editada ─────────────────────────── */
    $edit = $pdo->prepare("
        UPDATE paginas_livro pl
           JOIN anexos_livros al ON al.id = pl.anexo_id AND al.livro_id = ?
           SET pl.termo_inicial=?, pl.termo_final=?, pl.numero_folha=?, pl.eh_verso=?
         WHERE pl.id = ?");
    $edit->execute([$livro_id,$termo_ini,$termo_fim,$num_folha,$eh_verso,$pagina_id]);
    if(!$edit->rowCount()) throw new Exception('Página não pertence ao livro');

    /* ----- flag: recalcular termos? (duplicado não) --------------- */
    $recalcTerms = ($termo_ini != $termo_fim);

    if($recalcTerms){
        /* garante consistência do termo_final */
        $termo_fim = $termo_ini + $TPP - 1;
        $edit->execute([$livro_id,$termo_ini,$termo_fim,$num_folha,$eh_verso,$pagina_id]);
    }

    /* ───── 4. Carrega TODAS as páginas do livro em ordem ────────── */
    $sel = $pdo->prepare("
        SELECT pl.id, pl.numero_pagina, pl.numero_folha,
               pl.eh_verso, pl.termo_inicial, pl.termo_final
          FROM paginas_livro pl
          JOIN anexos_livros al ON al.id = pl.anexo_id
         WHERE al.livro_id = ?
      ORDER BY pl.numero_pagina");
    $sel->execute([$livro_id]);
    $pages = $sel->fetchAll(PDO::FETCH_ASSOC);

    /* ───── 5. Prepare UPDATE para loop ──────────────────────────── */
    $upd = $pdo->prepare("
        UPDATE paginas_livro
           SET numero_folha=?, eh_verso=?, termo_inicial=?, termo_final=?
         WHERE id=?");

    /* ───── 6. Resequenciar após a página editada ────────────────── */
    $inSequence = false;
    $prevFim = $termo_fim;                            // último termo válido
    $currFolha = $num_folha;                          // folha da pág editada
    $currSide  = $eh_verso;                           // 0=frente,1=verso

    foreach($pages as $pg){
        if($pg['id'] == $pagina_id){                  // página editada
            $inSequence = true;
            continue;
        }

        if(!$inSequence) continue;                    // páginas antes: ignora

        /* Próxima página — define lado e folha -------------------- */
        if($FV){
            // alterna lado; folha sobe somente quando volta ao 'frente'
            if($currSide==0){         // estava em frente → próximo é verso
                $currSide = 1;
            }else{                    // estava verso  → volta a frente
                $currSide = 0;
                $currFolha++;
            }
        }else{
            $currSide = 0;
            $currFolha++;
        }

        /* Termos --------------------------------------------------- */
        $newIni = $pg['termo_inicial'];
        $newFim = $pg['termo_final'];
        if($recalcTerms){
            $newIni = $prevFim + 1;
            $newFim = $newIni + $TPP - 1;
            $prevFim = $newFim;
        }

        /* Grava se houver mudança ---------------------------------- */
        if($currFolha != $pg['numero_folha'] ||
           $currSide  != $pg['eh_verso']     ||
           $newIni    != $pg['termo_inicial']||
           $newFim    != $pg['termo_final']){
            $upd->execute([$currFolha,$currSide,$newIni,$newFim,$pg['id']]);
        }
    }

    $pdo->commit();

    /* ───── 7. Snapshot final para interface ─────────────────────── */
    $sel->execute([$livro_id]);
    $snapshot = $sel->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true,'paginas'=>$snapshot]);

}catch(Exception $e){
    $pdo->rollBack();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
