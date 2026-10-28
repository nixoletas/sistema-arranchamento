<?php
// Login do militar. Pelo site direto (rede da OM): só o CPF, como sempre foi.
// Pelo app (de qualquer lugar): CPF + senha de 6 números, com bloqueio depois de 5 erros (acesso-app.php).

error_reporting(0);
session_start();
header('Content-Type: application/json; charset=utf-8');

$identidade = isset($_POST['identidade']) ? trim((string)$_POST['identidade']) : null;

if ($identidade) {

	require_once("config/conexao.php");

	$con = $cnt->prepare('SELECT * FROM cad_militar WHERE ident_militar = :identidade');
	$con->bindValue(':identidade', $identidade, PDO::PARAM_STR);
	$con->execute();
	$res = $con->fetch(PDO::FETCH_ASSOC);

	if (acesso_pelo_app()) {
		$senha = (string)($_POST['senha'] ?? '');
		// CPF inexistente, sem senha ou senha errada: mesma resposta (não revela quais CPFs existem).
		$conferido = app_conferir_senha($cnt, $identidade, $senha);
		if ($conferido === true && !$res) {
			$conferido = APP_MSG_ERRO;
		}
		if ($conferido !== true) {
			echo json_encode(['erro' => '<p class="alert alert-danger">' . htmlspecialchars($conferido) . '</p>'], JSON_UNESCAPED_UNICODE);
			exit;
		}
		app_entrar($cnt, $res);
		if (!empty($_POST['lembrar'])) {
			app_lembrar($cnt, $identidade);
		}
		echo json_encode(['url' => 'index.php?pasta=home&pagina=inicio']);
		exit;
	}

	if ($res) {
		$_SESSION['auth']['usuario_logado'] = $res;
		$_SESSION['auth']['usuario_logado']['id'] = $res['id'];
		echo '{"url":"index.php?pasta=home&pagina=inicio"}';
	} else {
		echo '{"erro":"<p class=\"alert alert-danger\">Número de CPF não encontrado <i class=\"fa fa-exclamation\"></i></p>"}';
	}
}
