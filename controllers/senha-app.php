<?php
// Senha do app do militar logado: GET devolve se já tem senha; POST cria ou troca.
// Pelo site da OM basta estar logado (CPF). Pelo app é preciso informar também a senha atual.
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once "config/conexao.php";

function responder($dados, $codigo = 200)
{
	http_response_code($codigo);
	echo json_encode($dados, JSON_UNESCAPED_UNICODE);
	exit;
}

$ident = $_SESSION['auth']['usuario_logado']['ident_militar'] ?? null;
if (!$ident) {
	responder(['error' => 'Sessão expirada, faça login novamente.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	$r = app_registro($cnt, $ident);
	responder([
		'tem_senha' => (bool)($r['senha_hash'] ?? null),
		'criada_em' => !empty($r['senha_criada_em']) ? date('d/m/Y', strtotime($r['senha_criada_em'])) : null,
		'pelo_app'  => acesso_pelo_app(),
	]);
}

$nova = (string)($_POST['nova'] ?? '');
$confirma = (string)($_POST['confirma'] ?? '');

if (acesso_pelo_app()) {
	$ok = app_conferir_senha($cnt, $ident, (string)($_POST['atual'] ?? ''));
	if ($ok !== true) {
		responder(['error' => strpos($ok, 'Muitas tentativas') === 0 ? $ok : 'A senha atual está incorreta.']);
	}
}
$fraca = app_senha_fraca($nova);
if ($fraca) {
	responder(['error' => $fraca]);
}
if ($nova !== $confirma) {
	responder(['error' => 'A confirmação não é igual à senha.']);
}

app_definir_senha($cnt, $ident, $nova);
include "../rancho/controllers/auditoria.php";
auditoria('SENHA_APP', 0, $cnt, $ident);
responder(['ok' => true, 'msg' => 'Senha do app salva. Use seu CPF e essa senha para entrar pelo app.']);
