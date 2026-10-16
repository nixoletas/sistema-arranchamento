<?php

date_default_timezone_set('America/Santarem');

// Grava a trilha de auditoria (quem arranchou/desarranchou o quê, de onde e com qual navegador).
// Uma falha aqui nunca pode desfazer nem esconder o arranchamento que já foi gravado: o erro só
// vai para o log (logs/php-erros.log). Os textos são cortados no tamanho das colunas — o MySQL 8
// recusa texto maior que a coluna, e navegadores de celular mandam identificação longa.
function auditoria($acao,$registro, $cnt, $usuario)
{
	$corta = function ($texto, $max) {
		return mb_substr((string)$texto, 0, $max, 'UTF-8');
	};

	try {
		$con = $cnt->prepare('INSERT INTO auditoria (id_registro, data_hora, operador, agent, ip, acao) VALUES (:id_registro, :data_hora, :operador, :agent, :ip, :acao)');
		$con->bindValue(':id_registro', $registro, PDO::PARAM_INT);
		$con->bindValue(':data_hora', date('Y-m-d H:i'), PDO::PARAM_STR);
		$con->bindValue(':operador', $corta($usuario, 45), PDO::PARAM_STR);
		$con->bindValue(':agent', $corta($_SERVER['HTTP_USER_AGENT'] ?? '', 255), PDO::PARAM_STR);
		$con->bindValue(':ip', $corta($_SERVER['REMOTE_ADDR'] ?? '', 45), PDO::PARAM_STR);
		$con->bindValue(':acao', $corta($acao, 45), PDO::PARAM_STR);
		$con->execute();
	} catch (Throwable $e) {
		error_log('Erro na auditoria (' . $acao . ', registro ' . $registro . '): ' . $e->getMessage());
	}
}

?>
