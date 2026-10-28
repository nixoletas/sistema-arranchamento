<?php
// "Arranchar por semana" (tela do militar): devolve os próximos dias com o estado de cada refeição e
// grava várias alterações de uma vez. As regras são as mesmas dos calendários (regras-arranchamento.php),
// e aqui também são conferidas no servidor: janela de dias, bloqueios e prazo para desarranchar.
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once "config/conexao.php";
require_once "regras-arranchamento.php";
include "../rancho/controllers/auditoria.php";

function responder($dados, $codigo = 200)
{
	http_response_code($codigo);
	echo json_encode($dados, JSON_UNESCAPED_UNICODE);
	exit;
}

$identidade = $_SESSION['auth']['usuario_logado']['ident_militar'] ?? null;
if (!$identidade) {
	responder(['error' => 'Sessão expirada, faça login novamente.'], 401);
}

$con = $cnt->prepare("SELECT om_id, posto FROM cad_militar WHERE ident_militar = :ident LIMIT 1");
$con->execute([':ident' => $identidade]);
$militar = $con->fetch(PDO::FETCH_ASSOC);
if (!$militar) {
	responder(['error' => 'Militar não encontrado. Procure o furriel da sua Cia.'], 403);
}

$bloqueios = regra_bloqueios($cnt, $militar['om_id'], $militar['posto']);
$primeiro  = date('Y-m-d', strtotime('+' . ARRANCHAR_DIAS_MIN . ' days'));
$ultimo    = date('Y-m-d', strtotime('+' . ARRANCHAR_DIAS_MAX . ' days'));

// Refeições já arranchadas na janela: [data][tp] => true
$lerArranchados = function () use ($cnt, $identidade, $primeiro, $ultimo) {
	$con = $cnt->prepare("SELECT data, tp FROM registros WHERE ident_militar = :ident AND data BETWEEN :ini AND :fim");
	$con->execute([':ident' => $identidade, ':ini' => $primeiro, ':fim' => $ultimo]);
	$feitos = [];
	while ($r = $con->fetch(PDO::FETCH_ASSOC)) {
		$feitos[$r['data']][(int)$r['tp']] = true;
	}
	return $feitos;
};

// "Versão" dos arranchamentos do militar (da semana passada em diante): muda sempre que alguém arrancha ou
// desarrancha, por qualquer aparelho ou pelo furriel. As telas abertas comparam e se atualizam sozinhas.
$versao = function () use ($cnt, $identidade) {
	$con = $cnt->prepare("SELECT COUNT(*) AS n, COALESCE(BIT_XOR(CRC32(CONCAT(data, '|', tp))), 0) AS x
		FROM registros WHERE ident_militar = :ident AND data >= CURDATE() - INTERVAL 7 DAY");
	$con->execute([':ident' => $identidade]);
	$r = $con->fetch(PDO::FETCH_ASSOC);
	return $r['n'] . '-' . $r['x'];
};
if (($_GET['acao'] ?? '') === 'versao') {
	responder(['versao' => $versao()]);
}

$acao = $_POST['acao'] ?? 'estado';

if ($acao === 'salvar') {
	$mudancas = json_decode($_POST['mudancas'] ?? '[]', true);
	if (!is_array($mudancas) || !$mudancas) {
		responder(['error' => 'Nenhuma alteração para salvar.']);
	}
	if (count($mudancas) > 3 * (ARRANCHAR_DIAS_MAX - ARRANCHAR_DIAS_MIN + 1)) {
		responder(['error' => 'Alterações demais de uma vez.']);
	}

	$verificar = $cnt->prepare("SELECT id FROM registros WHERE ident_militar = :ident AND tp = :tp AND data = :data LIMIT 1");
	$inserir   = $cnt->prepare("INSERT IGNORE INTO registros (data, tp, ident_militar) VALUES (:data, :tp, :ident)");
	$apagar    = $cnt->prepare("DELETE FROM registros WHERE id = :id");
	$nomes     = [1 => 'café', 2 => 'almoço', 3 => 'jantar'];

	$arranchados = 0;
	$desarranchados = 0;
	$erros = [];
	foreach ($mudancas as $m) {
		$data = regra_data_iso($m['data'] ?? '');
		$tp   = (int)($m['tipo'] ?? 0);
		$quer = !empty($m['arranchar']);
		if (!$data || !isset($nomes[$tp])) {
			$erros[] = 'Alteração inválida ignorada.';
			continue;
		}
		$rotulo = $nomes[$tp] . ' de ' . date('d/m', strtotime($data));
		if ($data < $primeiro || $data > $ultimo) {
			$erros[] = ucfirst($rotulo) . ': fora do período permitido.';
			continue;
		}

		$verificar->execute([':ident' => $identidade, ':tp' => $tp, ':data' => $data]);
		$id = $verificar->fetchColumn();

		if ($quer && $id === false) {
			if (isset($bloqueios[$data][$tp])) {
				$erros[] = ucfirst($rotulo) . ': dia bloqueado (' . $bloqueios[$data][$tp] . ').';
				continue;
			}
			$inserir->execute([':data' => $data, ':tp' => $tp, ':ident' => $identidade]);
			if ($inserir->rowCount() > 0) {   // 0 = outro aparelho gravou no mesmo instante
				auditoria('ARRANCHAR', $cnt->lastInsertId(), $cnt, $identidade);
				$arranchados++;
			}
		} elseif (!$quer && $id !== false) {
			$pode = regra_pode_cancelar($data);
			if ($pode !== true) {
				$erros[] = ucfirst($rotulo) . ': ' . $pode;
				continue;
			}
			$apagar->execute([':id' => $id]);
			if ($apagar->rowCount() > 0) {
				auditoria('DESARANCHAR', $id, $cnt, $identidade);
				$desarranchados++;
			}
		}
	}
	responder(['arranchados' => $arranchados, 'desarranchados' => $desarranchados, 'erros' => $erros]);
}

// Estado dos próximos dias (de ARRANCHAR_DIAS_MIN a ARRANCHAR_DIAS_MAX dias à frente).
$feitos = $lerArranchados();
$siglas = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];
$dias = [];
for ($d = $primeiro; $d <= $ultimo; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
	$prazo = regra_prazo_cancelar($d);
	$podeCancelar = regra_pode_cancelar($d) === true;
	$refeicoes = [];
	foreach ([1, 2, 3] as $tp) {
		$refeicoes[$tp] = [
			'arranchado'   => !empty($feitos[$d][$tp]),
			'bloqueio'     => $bloqueios[$d][$tp] ?? null,
			'pode_cancelar' => $podeCancelar,
		];
	}
	$dias[] = [
		'data'          => $d,
		'dia'           => $siglas[(int)date('N', strtotime($d))] . ' ' . date('d/m', strtotime($d)),
		'segunda'       => date('Y-m-d', strtotime($d . ' -' . ((int)date('N', strtotime($d)) - 1) . ' days')),
		'prazo_cancelar' => date('d/m', strtotime($prazo)),
		'prazo_dia'     => $siglas[(int)date('N', strtotime($prazo))],
		'refeicoes'     => $refeicoes,
	];
}
responder(['hoje' => date('Y-m-d'), 'dias' => $dias, 'versao' => $versao()]);
