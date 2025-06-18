<?php
/***************************************************************************
 * salvar_mandado.php
 * ------------------------------------------------------------------------
 * Recebe o POST do formulário “Novo Mandado”, faz saneamento dos campos,
 * garante unicidade do Código de Rastreabilidade (mesmo com concorrência),
 * grava no banco e redireciona ao gerenciamento do registro.
 * --------------------------------------------------------------------- */
date_default_timezone_set('America/Sao_Paulo');

require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

/* ------------------------------------------------------------------ 1.
   Somente POST válido
-------------------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mandados.php?erro=Requisição+inválida');
    exit;
}

/* ------------------------------------------------------------------ 2.
   Limpeza e validação de campos
-------------------------------------------------------------------*/
function limparTexto(string $txt): string
{
    $txt = str_replace(["\r", "\n"], ' ', $txt);   // quebras ⇒ espaço
    $txt = preg_replace('/\s+/', ' ', $txt);       // espaços múltiplos
    $txt = preg_replace('/\s+([,.])/', '$1', $txt);// espaço antes de , .
    return trim($txt);
}

$codigo   = limparTexto($_POST['codigo_rastreabilidade'] ?? '');
$remet    = limparTexto($_POST['remetente']            ?? '');
$motivo   = limparTexto($_POST['motivo_envio']         ?? '');
$dataEnv  = $_POST['data_envio']                       ?? date('Y-m-d');
$assunto  = limparTexto($_POST['assunto']              ?? '');
$origem   = $_POST['origem']                           ?? '';

if ($codigo === '' || $origem === '') {
    header('Location: mandados.php?erro=Preencha+os+campos+obrigatórios');
    exit;
}

/* ------------------------------------------------------------------ 3.
   Verificação prévia – já existe o código?
-------------------------------------------------------------------*/
$stmt = $pdo->prepare("
    SELECT id FROM mandados
     WHERE codigo_rastreabilidade = ?
       AND status != 'excluido'
     LIMIT 1
");
$stmt->execute([$codigo]);
$existe = $stmt->fetchColumn();

if ($existe) {
    // evita trabalho desnecessário – já existe
    header("Location: mandados.php?dup=1&cod=".urlencode($codigo)."&id=".$existe);
    exit;
}

/* ------------------------------------------------------------------ 4.
   Inserção segura – protege contra corrida duplicada
-------------------------------------------------------------------*/
try {
    $pdo->prepare("
        INSERT INTO mandados
              (codigo_rastreabilidade, remetente, motivo_envio,
               data_envio, origem, assunto,
               usuario_id, status, data_cadastro)
        VALUES (?,?,?,?,?,?,?,'pendente',NOW())
    ")->execute([
        $codigo,
        $remet,
        $motivo,
        $dataEnv,
        $origem,
        $assunto,
        $_SESSION['usuario_id']
    ]);

    $novoId = $pdo->lastInsertId();
    header("Location: mandados.php?id=$novoId&success=1");
    exit;

} catch (PDOException $e) {

    /* Caso dois usuários submetam o mesmo código *quase* ao mesmo tempo,
       a verificação prévia acima pode não pegar; o índice ÚNICO lança
       erro 23000 (integrity constraint). Trate e redirecione. */
    if ($e->getCode() === '23000') {
        $idExistente = $pdo->prepare("
            SELECT id FROM mandados
             WHERE codigo_rastreabilidade = ?
               AND status!='excluido' LIMIT 1
        ");
        $idExistente->execute([$codigo]);
        $id = $idExistente->fetchColumn();
        header("Location: mandados.php?dup=1&cod=".urlencode($codigo)."&id=".$id);
        exit;
    }

    // Erro inesperado
    error_log('[mandados] salvar_mandado falhou: '.$e->getMessage());
    header('Location: mandados.php?erro=Erro+ao+salvar');
    exit;
}
?>
