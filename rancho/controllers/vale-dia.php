<?php
// Vale do dia: GET carrega o estado; POST {acao: "calcular"|"salvar"} recalcula ou grava as alterações.
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/vale-lib.php';
vale_exige_acesso(true);

function vale_responder($dados, $codigo = 200)
{
	http_response_code($codigo);
	echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
	exit;
}

try {
	if ($_SERVER['REQUEST_METHOD'] === 'GET') {
		$data = vale_data_valida($_GET['data'] ?? date('Y-m-d'));
		$estado = vale_estado_dia($cnt, $data);
		$estado['csrf'] = vale_csrf();
		vale_responder($estado);
	}

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		vale_responder(['error' => 'Método não permitido.'], 405);
	}

	// Proteção contra requisições vindas de outros sites: exige o token da sessão.
	$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
	if (empty($_SESSION['vale_csrf']) || !hash_equals($_SESSION['vale_csrf'], $token)) {
		vale_responder(['error' => 'Sessão de edição expirada. Recarregue a página.'], 403);
	}

	$in = json_decode(file_get_contents('php://input'), true);
	if (!is_array($in)) {
		vale_responder(['error' => 'Requisição inválida.'], 400);
	}
	$data = vale_data_valida($in['data'] ?? '');
	$acao = $in['acao'] ?? '';

	// As Cias do dia vêm do banco, nunca do navegador.
	$base = vale_calcular($cnt, $data, $data, null, false);
	$cias = $base['cias'];
	$artigos = vale_artigos($cnt);
	[$matriz, $p, $forn, $cfg] = vale_ler_entrada($in, $cias, $artigos);

	if ($acao === 'calcular') {
		vale_responder(['docs' => vale_documentos($cias, $matriz, $p, $artigos, $forn)]);
	}

	if ($acao === 'salvar') {
		$mudou = vale_salvar_dia($cnt, $data, $cias, $matriz, $p, $forn, $cfg, vale_usuario());
		try {
			require_once __DIR__ . '/auditoria.php';
			auditoria('VALE_SALVAR', (int)str_replace('-', '', $data), $cnt, vale_usuario());
		} catch (Throwable $e) {
			// A trilha de auditoria não deve impedir o salvamento.
		}
		$estado = vale_estado_dia($cnt, $data);
		$estado['csrf'] = vale_csrf();
		$estado['salvo'] = ['celulas_alteradas' => $mudou, 'em' => date('d/m/Y H:i:s')];
		vale_responder($estado);
	}

	vale_responder(['error' => 'Ação inválida.'], 400);
} catch (InvalidArgumentException $e) {
	vale_responder(['error' => $e->getMessage()], 400);
} catch (Throwable $e) {
	vale_responder(['error' => 'Não foi possível concluir agora.'], 500);
}
