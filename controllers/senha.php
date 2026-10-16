<?php
// Verificação e geração de hash de senha, com migração automática de MD5 (formato antigo,
// fraco e rápido de quebrar por força bruta) para bcrypt (password_hash/password_verify do PHP).
//
// Nenhuma senha precisa ser trocada pelo usuário: no primeiro login com sucesso após esta
// atualização, o hash MD5 da conta é silenciosamente regravado em bcrypt. Contas que não
// fizerem login continuam em MD5 até o fazerem — ainda funcionam, só não estão migradas.

// Um hash MD5 é sempre 32 caracteres hexadecimais; bcrypt começa com $2y$ e tem ~60 caracteres.
function senha_e_md5_antigo($hash)
{
	return (bool) preg_match('/^[a-f0-9]{32}$/i', (string)$hash);
}

function senha_confere($senhaDigitada, $hashArmazenado)
{
	if ($hashArmazenado === null || $hashArmazenado === '') {
		return false;
	}
	if (senha_e_md5_antigo($hashArmazenado)) {
		return hash_equals($hashArmazenado, md5($senhaDigitada));
	}
	return password_verify($senhaDigitada, $hashArmazenado);
}

function senha_hash($senha)
{
	return password_hash($senha, PASSWORD_BCRYPT);
}

// Chame depois de um login válido: se a conta ainda estava em MD5, regrava em bcrypt.
function senha_migrar_se_preciso(PDO $cnt, $usuarioId, $senhaDigitada, $hashAtual)
{
	if (!senha_e_md5_antigo($hashAtual)) {
		return;
	}
	try {
		$up = $cnt->prepare('UPDATE usuarios SET senha = :senha WHERE id = :id');
		$up->bindValue(':senha', senha_hash($senhaDigitada), PDO::PARAM_STR);
		$up->bindValue(':id', $usuarioId, PDO::PARAM_INT);
		$up->execute();
	} catch (Throwable $e) {
		// Login já foi liberado; se a regravação falhar, tenta de novo no próximo login.
		error_log('Falha ao migrar senha para bcrypt (usuario id ' . $usuarioId . '): ' . $e->getMessage());
	}
}
