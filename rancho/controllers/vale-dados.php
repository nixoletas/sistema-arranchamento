<?php
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/vale-lib.php';
vale_exige_acesso(true);

try {
	[$ini, $fim, $cia] = vale_parametros($_GET);
	echo json_encode(vale_calcular($cnt, $ini, $fim, $cia), JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
	http_response_code(400);
	echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['error' => 'Não foi possível calcular o vale agora.'], JSON_UNESCAPED_UNICODE);
}
