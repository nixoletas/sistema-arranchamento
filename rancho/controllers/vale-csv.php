<?php
error_reporting(0);
require_once __DIR__ . '/vale-lib.php';
vale_exige_acesso(false);

try {
	[$ini, $fim, $cia] = vale_parametros($_GET);
	$v = vale_calcular($cnt, $ini, $fim, $cia);
} catch (InvalidArgumentException $e) {
	http_response_code(400);
	echo $e->getMessage();
	exit;
} catch (Throwable $e) {
	http_response_code(500);
	echo 'Não foi possível gerar o arquivo agora.';
	exit;
}

$semana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="vale-racoes-' . $ini . '_a_' . $fim . '.csv"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM: o Excel abre com acentos corretos

// Separador ";" (padrão do Excel em português).
$fputs = function ($linha) use ($out) {
	fputcsv($out, $linha, ';', '"', '\\', "\r\n");
};

$fputs(['Data', 'Dia', 'Cia',
	'Café Of', 'Café ST/SGT', 'Café CB/SD',
	'Almoço Of', 'Almoço ST/SGT', 'Almoço CB/SD',
	'Jantar Of', 'Jantar ST/SGT', 'Jantar CB/SD',
	'Etapas completas Of', 'Etapas completas ST/SGT', 'Etapas completas CB/SD', 'Etapas completas Total']);

$linhaCsv = function ($data, $dow, $nome, $x) use ($semana) {
	return array_merge(
		[date('d/m/Y', strtotime($data)), $semana[$dow], $nome],
		$x['cafe'], $x['almoco'], $x['jantar'], $x['qr'], [array_sum($x['qr'])]
	);
};

$multi = count($v['cias']) > 1;
foreach ($v['dias'] as $dia) {
	foreach ($v['cias'] as $c) {
		$fputs($linhaCsv($dia['data'], $dia['dow'], $c['nome'], $dia['cias'][$c['id']]));
	}
	if ($multi) {
		$fputs($linhaCsv($dia['data'], $dia['dow'], 'TOTAL', $dia['total']));
	}
}
fclose($out);
