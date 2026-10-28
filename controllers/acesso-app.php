<?php
// Acesso pelo app (Tailscale Funnel, de qualquer lugar). O túnel entra no Apache pela porta 8081, que marca
// a requisição com ACESSO_EXTERNO=1 (docker/acesso-app.conf); quem vem de fora não consegue tirar essa marca.
// Pelo app o militar entra com CPF + senha de 6 números; pelo site direto (rede da OM) continua só o CPF.

const APP_MAX_TENTATIVAS = 5;      // erros seguidos de senha antes de bloquear
const APP_BLOQUEIO_MIN   = 15;     // minutos de bloqueio
const APP_LEMBRAR_DIAS   = 60;     // "manter conectado neste aparelho"
const APP_COOKIE         = 'app_lembrar';

function acesso_pelo_app()
{
	foreach (['ACESSO_EXTERNO', 'REDIRECT_ACESSO_EXTERNO'] as $k) {
		if (!empty($_SERVER[$k])) {
			return true;
		}
	}
	return getenv('ACESSO_EXTERNO') === '1';
}

function app_https()
{
	return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
}

// Senha do app: exatamente 6 números, sem sequência óbvia (123456, 000000...).
function app_senha_fraca($senha)
{
	if (!preg_match('/^\d{6}$/', $senha)) {
		return 'A senha deve ter exatamente 6 números.';
	}
	if (preg_match('/^(\d)\1{5}$/', $senha) || strpos('0123456789', $senha) !== false || strpos('9876543210', $senha) !== false) {
		return 'Senha fácil demais (números repetidos ou em sequência). Escolha outra.';
	}
	return null;
}

function app_registro($cnt, $ident)
{
	$con = $cnt->prepare("SELECT * FROM militar_acesso WHERE ident_militar = :ident");
	$con->execute([':ident' => $ident]);
	return $con->fetch(PDO::FETCH_ASSOC) ?: null;
}

function app_tem_senha($cnt, $ident)
{
	$r = app_registro($cnt, $ident);
	return $r && !empty($r['senha_hash']);
}

function app_definir_senha($cnt, $ident, $senha)
{
	$con = $cnt->prepare("INSERT INTO militar_acesso (ident_militar, senha_hash, senha_criada_em, tentativas, bloqueado_ate)
		VALUES (:ident, :hash, NOW(), 0, NULL)
		ON DUPLICATE KEY UPDATE senha_hash = VALUES(senha_hash), senha_criada_em = NOW(), tentativas = 0, bloqueado_ate = NULL");
	$con->execute([':ident' => $ident, ':hash' => password_hash($senha, PASSWORD_DEFAULT)]);
	// Trocou a senha: desconecta os aparelhos que estavam com "manter conectado".
	$cnt->prepare("DELETE FROM militar_token WHERE ident_militar = :ident")->execute([':ident' => $ident]);
}

function app_zerar_senha($cnt, $ident)
{
	$cnt->prepare("DELETE FROM militar_acesso WHERE ident_militar = :ident")->execute([':ident' => $ident]);
	$cnt->prepare("DELETE FROM militar_token WHERE ident_militar = :ident")->execute([':ident' => $ident]);
}

const APP_MSG_ERRO = 'CPF ou senha incorretos. Se ainda não tem senha do app, crie no site dentro da OM (botão "Senha do app") ou peça ao furriel.';

// Confere a senha com bloqueio por tentativas. Retorna true ou a mensagem de erro.
// CPF não cadastrado, CPF sem senha e senha errada dão a MESMA resposta e contam tentativa do mesmo jeito:
// assim, de fora, não dá para descobrir quais CPFs são de militares da OM.
function app_conferir_senha($cnt, $ident, $senha)
{
	if (!preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', (string)$ident)) {
		return APP_MSG_ERRO;
	}
	$r = app_registro($cnt, $ident);
	if ($r && $r['bloqueado_ate'] && strtotime($r['bloqueado_ate']) > time()) {
		$min = max(1, (int)ceil((strtotime($r['bloqueado_ate']) - time()) / 60));
		return 'Muitas tentativas erradas. Tente de novo em ' . $min . ' minuto' . ($min > 1 ? 's' : '') . '.';
	}
	// Sempre roda o password_verify (com um hash qualquer quando não há senha) para o tempo de resposta ser igual.
	$hash = ($r && !empty($r['senha_hash'])) ? $r['senha_hash'] : '$2y$10$abcdefghijklmnopqrstuuJ0nFhW7Q3hGQ3dP3oG0rjWm5r3m5w2a';
	$certa = password_verify((string)$senha, $hash) && $r && !empty($r['senha_hash']);
	if ($certa) {
		$cnt->prepare("UPDATE militar_acesso SET tentativas = 0, bloqueado_ate = NULL WHERE ident_militar = :ident")
			->execute([':ident' => $ident]);
		return true;
	}
	$tentativas = ($r ? (int)$r['tentativas'] : 0) + 1;
	$bloquear = $tentativas >= APP_MAX_TENTATIVAS;
	$cnt->prepare("INSERT INTO militar_acesso (ident_militar, tentativas, bloqueado_ate)
		VALUES (:ident, :t, " . ($bloquear ? "DATE_ADD(NOW(), INTERVAL " . APP_BLOQUEIO_MIN . " MINUTE)" : "NULL") . ")
		ON DUPLICATE KEY UPDATE tentativas = VALUES(tentativas), bloqueado_ate = VALUES(bloqueado_ate)")
		->execute([':ident' => $ident, ':t' => $bloquear ? 0 : $tentativas]);
	if ($bloquear) {
		return 'Muitas tentativas erradas. Por segurança, o acesso pelo app ficou bloqueado por ' . APP_BLOQUEIO_MIN . ' minutos.';
	}
	return APP_MSG_ERRO;
}

// Põe o militar na sessão (mesmo formato do login pelo site), marcando que entrou pelo app.
function app_entrar($cnt, $militar)
{
	session_regenerate_id(true);
	$_SESSION['auth']['usuario_logado'] = $militar;
	$_SESSION['auth']['usuario_logado']['id'] = $militar['id'];
	$_SESSION['auth']['via_app'] = true;
}

function app_cookie($valor, $expira)
{
	setcookie(APP_COOKIE, $valor, [
		'expires'  => $expira,
		'path'     => '/',
		'secure'   => app_https(),
		'httponly' => true,
		'samesite' => 'Lax',
	]);
}

// "Manter conectado": token aleatório no aparelho; no banco fica só o hash.
function app_lembrar($cnt, $ident)
{
	$token = bin2hex(random_bytes(32));
	$cnt->exec("DELETE FROM militar_token WHERE expira < NOW()");
	$cnt->prepare("INSERT INTO militar_token (token_hash, ident_militar, expira) VALUES (:h, :ident, DATE_ADD(NOW(), INTERVAL " . APP_LEMBRAR_DIAS . " DAY))")
		->execute([':h' => hash('sha256', $token), ':ident' => $ident]);
	app_cookie($token, time() + APP_LEMBRAR_DIAS * 86400);
}

function app_esquecer($cnt)
{
	if (!empty($_COOKIE[APP_COOKIE])) {
		$cnt->prepare("DELETE FROM militar_token WHERE token_hash = :h")->execute([':h' => hash('sha256', $_COOKIE[APP_COOKIE])]);
	}
	app_cookie('', time() - 3600);
}

// Chamado em toda requisição (fim de conexao.php). Pelo app:
//  - sessão de militar que não entrou pelo app (só CPF) não vale;
//  - sem sessão, mas com "manter conectado" válido, entra de novo sozinho.
function app_verificar_sessao($cnt)
{
	if (!acesso_pelo_app() || session_status() !== PHP_SESSION_ACTIVE) {
		return;
	}
	if (!empty($_SESSION['auth']) && empty($_SESSION['auth']['via_app'])) {
		unset($_SESSION['auth']);
	}
	if (!empty($_SESSION['auth']['usuario_logado']['id']) || empty($_COOKIE[APP_COOKIE]) || !is_string($_COOKIE[APP_COOKIE])) {
		return;
	}
	$con = $cnt->prepare("SELECT m.* FROM militar_token t JOIN cad_militar m ON m.ident_militar = t.ident_militar
		JOIN militar_acesso a ON a.ident_militar = t.ident_militar AND a.senha_hash IS NOT NULL
		WHERE t.token_hash = :h AND t.expira > NOW() LIMIT 1");
	$con->execute([':h' => hash('sha256', $_COOKIE[APP_COOKIE])]);
	$militar = $con->fetch(PDO::FETCH_ASSOC);
	if ($militar) {
		app_entrar($cnt, $militar);
	} else {
		app_cookie('', time() - 3600);
	}
}
