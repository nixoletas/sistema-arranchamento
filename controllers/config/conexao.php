<?php

// Horário de Brasília (Itaituba-PA, UTC-3). Sem isso o PHP usa UTC e, das 21h à meia-noite,
// o sistema enxerga o dia seguinte (prazos de cancelamento, dia atual, auditoria).
date_default_timezone_set('America/Santarem');

// Registro de erros: grava em logs/php-erros.log, sem mostrar nada na tela (as páginas e as
// respostas JSON continuam iguais). A pasta logs/ é bloqueada para o navegador em
// docker/block-sensitive.conf. Os argumentos das funções ficam fora dos rastros de erro para
// não gravar senhas ou CPFs no log. Avisos de código obsoleto (E_DEPRECATED) e E_NOTICE ficam
// de fora para o log não encher de ruído.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/php-erros.log');
ini_set('zend.exception_ignore_args', '1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

$options 	= array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8');

$uRancho = "root";
$pRancho = "password";

try{
	$cnt = new PDO('mysql:host=sisrancho-db;dbname=sisrancho', $uRancho, $pRancho, $options);

}catch(PDOException $e)
{
	throw new PDOException($e);
}

// Acesso pelo app (porta 8081 / Tailscale Funnel): sessão só vale se entrou com senha; "manter conectado".
require_once __DIR__ . '/../acesso-app.php';
app_verificar_sessao($cnt);

?>
