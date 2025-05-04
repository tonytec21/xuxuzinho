<?php
/**************************************************************************
 * API/GET_PAGINAS.PHP
 * -----------------------------------------------------------------------
 *  Retorna a lista de páginas de um livro já NA ORDEM CORRETA DE LEITURA
 *  (folha crescente; frente(0) antes de verso(1)).
 *  Saída: { sucesso:bool, paginas?:array, mensagem?:str }
 *************************************************************************/
require_once '../includes/auth_check.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

/* ───── 1) Segurança ──────────────────────────────────────────────── */
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['sucesso'=>false,'mensagem'=>'Usuário não autenticado']);
    exit;
}

$livro_id = filter_input(INPUT_GET,'livro_id',FILTER_VALIDATE_INT);
if(!$livro_id){
    echo json_encode(['sucesso'=>false,'mensagem'=>'ID do livro não fornecido ou inválido']);
    exit;
}

/* ───── 2) Confirma se o livro existe ─────────────────────────────── */
try{
    $chk = $pdo->prepare("SELECT id FROM livros WHERE id=?");
    $chk->execute([$livro_id]);
    if(!$chk->fetch()){
        echo json_encode(['sucesso'=>false,'mensagem'=>'Livro não encontrado']);
        exit;
    }

    /* ───── 3) Busca páginas em ordem de FOLHA/LADO ───────────────── */
    /*  ⚠️  Caso ‘paginas_livro’ não tenha coluna livro_id,
        basta trocar pelo JOIN já usado em outras rotas:
          FROM paginas_livro pl
          JOIN anexos_livros al ON al.id = pl.anexo_id
         WHERE al.livro_id = ?
    */
    $sql = "
        SELECT id, livro_id, anexo_id,
               numero_pagina, numero_folha, eh_verso,
               caminho, termo_inicial, termo_final
          FROM paginas_livro
         WHERE livro_id = ?
      ORDER BY numero_folha ASC,
               eh_verso     ASC";        // frente(0) → verso(1)
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$livro_id]);
    $paginas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['sucesso'=>true,'paginas'=>$paginas]);

}catch(PDOException $e){
    echo json_encode(['sucesso'=>false,'mensagem'=>'Erro ao buscar páginas: '.$e->getMessage()]);
}
?>
