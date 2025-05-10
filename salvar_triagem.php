<?php
ob_start();
date_default_timezone_set('America/Sao_Paulo');
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Coletar dados do formulário
        $protocolo           = $_POST['protocolo'] ?? '';
        $nome_requerente     = $_POST['nome_requerente'] ?? '';
        $documento           = $_POST['documento_identificacao'] ?? '';
        $cpf                 = $_POST['cpf'] ?? '';
        $tipo_certidao       = $_POST['tipo_certidao'] ?? '';
        $serventia_nome      = $_POST['serventia_nome'] ?? '';
        $serventia_cidade    = $_POST['serventia_cidade'] ?? '';
        $serventia_uf        = $_POST['serventia_uf'] ?? '';
        $livro               = $_POST['livro'] ?? '';
        $folha               = $_POST['folha'] ?? '';
        $termo               = $_POST['termo'] ?? '';
        $nome_registrado     = $_POST['nome_registrado'] ?? '';
        $data_evento         = $_POST['data_evento'] ?? null;
        $filiacao_conjuge    = $_POST['filiacao_conjuge'] ?? '';

        // Validação básica
        if (empty($nome_requerente) || empty($tipo_certidao)) {
            throw new Exception("Campos obrigatórios não preenchidos.");
        }

        // Inserir no banco
        $stmt = $pdo->prepare("
            INSERT INTO triagem_registros (
                protocolo, nome_requerente, documento_identificacao, cpf,
                tipo_certidao, serventia_nome, serventia_cidade, serventia_uf,
                livro, folha, termo, nome_registrado, data_evento,
                filiacao_conjuge, status, data_cadastro
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', NOW()
            )
        ");

        $stmt->execute([
            $protocolo, $nome_requerente, $documento, $cpf,
            $tipo_certidao, $serventia_nome, $serventia_cidade, strtoupper($serventia_uf),
            $livro, $folha, $termo, $nome_registrado, $data_evento,
            $filiacao_conjuge
        ]);

        header("Location: triagem.php?success=1");
        exit;

    } catch (Exception $e) {
        error_log("Erro ao salvar triagem: " . $e->getMessage());
        header("Location: triagem.php?error=1&msg=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    header("Location: triagem.php?error=invalid_method");
    exit;
}
