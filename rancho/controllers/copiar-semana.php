<?php
// "Copiar semana anterior" (painel, janela Arranchar): repete na semana escolhida os arranchamentos da
// semana anterior para os militares de uma Cia. Primeiro mostra a prévia (acao=previa), depois copia
// (acao=copiar). Não apaga nada: só cria o que falta. Pula dias que já passaram e dias bloqueados.
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once "../../controllers/config/conexao.php";
require_once "../../controllers/regras-arranchamento.php";
include "auditoria.php";

function responder($dados, $codigo = 200)
{
	http_response_code($codigo);
	echo json_encode($dados, JSON_UNESCAPED_UNICODE);
	exit;
}

if (empty($_SESSION['usuario']['id'])) {
	responder(['error' => 'Sessão expirada, faça login novamente.'], 401);
}
$tipoUsuario = (int)($_SESSION['usuario']['tipo'] ?? 0);

// O Operador (furriel) só copia a própria Cia; o Administrador escolhe.
$om = $tipoUsuario === 1 ? (int)($_POST['om'] ?? 0) : (int)$_SESSION['usuario']['om'];
if ($om <= 0) {
	responder(['error' => 'Selecione a Cia.']);
}

// Semana de destino: a segunda-feira informada (aceita dd/mm/aaaa ou aaaa-mm-dd).
$destino = regra_data_iso($_POST['semana'] ?? '');
if (!$destino || (int)date('N', strtotime($destino)) !== 1) {
	responder(['error' => 'Escolha a semana (segunda-feira) de destino.']);
}
$amanha = date('Y-m-d', strtotime('+1 day'));
if (date('Y-m-d', strtotime($destino . ' +6 days')) < $amanha) {
	responder(['error' => 'Essa semana já passou.']);
}

// Arranchamentos da semana de origem (a anterior) dos militares que hoje estão na Cia.
$origemIni = date('Y-m-d', strtotime($destino . ' -7 days'));
$origemFim = date('Y-m-d', strtotime($destino . ' -1 day'));
$con = $cnt->prepare("SELECT r.data, r.tp, r.ident_militar, m.posto
	FROM registros r JOIN cad_militar m ON m.ident_militar = r.ident_militar
	WHERE m.om_id = :om AND r.data BETWEEN :ini AND :fim");
$con->execute([':om' => $om, ':ini' => $origemIni, ':fim' => $origemFim]);
$origem = $con->fetchAll(PDO::FETCH_ASSOC);

$verificar = $cnt->prepare("SELECT 1 FROM registros WHERE ident_militar = :ident AND tp = :tp AND data = :data LIMIT 1");
$bloqueiosPorPosto = [];
$siglas = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];

// Monta o que seria copiado, dia a dia.
$plano = [];       // lista de [data, tp, ident]
$porDia = [];      // [data][tp] => quantidade a copiar
$jaExistem = 0;
$diasPassados = 0;
$bloqueados = 0;
foreach ($origem as $r) {
	$novaData = date('Y-m-d', strtotime($r['data'] . ' +7 days'));
	$tp = (int)$r['tp'];
	if ($novaData < $amanha) {
		$diasPassados++;
		continue;
	}
	if (!isset($bloqueiosPorPosto[$r['posto']])) {
		$bloqueiosPorPosto[$r['posto']] = regra_bloqueios($cnt, $om, $r['posto']);
	}
	if (isset($bloqueiosPorPosto[$r['posto']][$novaData][$tp])) {
		$bloqueados++;
		continue;
	}
	$verificar->execute([':ident' => $r['ident_militar'], ':tp' => $tp, ':data' => $novaData]);
	if ($verificar->fetchColumn()) {
		$jaExistem++;
		continue;
	}
	$plano[] = [$novaData, $tp, $r['ident_militar']];
	$porDia[$novaData][$tp] = ($porDia[$novaData][$tp] ?? 0) + 1;
}

$acao = $_POST['acao'] ?? 'previa';

if ($acao === 'copiar') {
	$inserir = $cnt->prepare("INSERT IGNORE INTO registros (data, tp, ident_militar) VALUES (:data, :tp, :ident)");
	$criados = 0;
	foreach ($plano as [$data, $tp, $ident]) {
		$inserir->execute([':data' => $data, ':tp' => $tp, ':ident' => $ident]);
		if ($inserir->rowCount() > 0) {
			auditoria('COPIAR_SEMANA', $cnt->lastInsertId(), $cnt, $_SESSION['usuario']['id'] ?? 0);
			$criados++;
		}
	}
	responder(['criados' => $criados, 'ja_existiam' => $jaExistem, 'dias_passados' => $diasPassados, 'bloqueados' => $bloqueados]);
}

// Prévia: uma linha por dia da semana de destino.
$linhas = [];
for ($i = 0; $i < 7; $i++) {
	$d = date('Y-m-d', strtotime($destino . " +$i days"));
	$linhas[] = [
		'dia'    => $siglas[$i + 1] . ' ' . date('d/m', strtotime($d)),
		'cafe'   => $porDia[$d][1] ?? 0,
		'almoco' => $porDia[$d][2] ?? 0,
		'jantar' => $porDia[$d][3] ?? 0,
		'passou' => $d < $amanha,
	];
}
responder([
	'origem'        => date('d/m', strtotime($origemIni)) . ' a ' . date('d/m', strtotime($origemFim)),
	'destino'       => date('d/m', strtotime($destino)) . ' a ' . date('d/m', strtotime($destino . ' +6 days')),
	'linhas'        => $linhas,
	'total'         => count($plano),
	'ja_existiam'   => $jaExistem,
	'dias_passados' => $diasPassados,
	'bloqueados'    => $bloqueados,
]);
