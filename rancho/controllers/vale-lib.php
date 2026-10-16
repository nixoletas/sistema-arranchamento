<?php
// Vale de Rações: mesma lógica da planilha de aprovisionamento (Vale Diário, Vale Total,
// Nota para Boletim e Mapa de Gêneros), alimentada pelo arranchamento e com ajuste manual.
//
// Vale Diário: por dia e por Cia, café / almoço / jantar em Oficiais, ST/SGT e CB/SD.
// "Etapa completa" (QR) = maior valor entre as 3 refeições, por categoria (MAX(C,A,J) da planilha).

require_once __DIR__ . '/../../controllers/config/conexao.php';

const VALE_CATS = ['of', 'sgt', 'cb'];
const VALE_REFS = ['1' => 'cafe', '2' => 'almoco', '3' => 'jantar'];
const VALE_TPS = ['cafe' => 1, 'almoco' => 2, 'jantar' => 3];
// Ordem das Cias no vale (1ª, 2ª, 3ª, Cmdo Ap); demais Cias depois.
const VALE_ORDEM_CIAS = [5, 2, 4, 3];
const VALE_MAX_DIAS = 93;
const VALE_PARAMS_INT = ['gu_of', 'gu_sgt', 'gu_cb', 'outras_of', 'outras_sgt', 'outras_cb',
	'outras_cafe', 'outras_almoco', 'outras_jantar', 'c_hosp', 'c_esc', 'cf60'];
const VALE_CONFIG_CHAVES = ['om_nome', 'om_sigla', 'aprovisionador', 'local', 'gu_padrao_of', 'gu_padrao_sgt', 'gu_padrao_cb'];

// ---------------------------------------------------------------- acesso

function vale_exige_acesso($json = true)
{
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}
	$id = $_SESSION['usuario']['id'] ?? null;
	$tipo = (int)($_SESSION['usuario']['tipo'] ?? 0);
	if (!$id || $tipo !== 1) {
		http_response_code(401);
		if ($json) {
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode(['error' => 'Acesso negado. Faça login novamente.']);
		} else {
			echo 'Acesso negado.';
		}
		exit;
	}
}

function vale_usuario()
{
	return (string)($_SESSION['usuario']['login'] ?? '');
}

function vale_csrf()
{
	if (empty($_SESSION['vale_csrf'])) {
		$_SESSION['vale_csrf'] = bin2hex(random_bytes(16));
	}
	return $_SESSION['vale_csrf'];
}

// ---------------------------------------------------------------- entrada

function vale_data_valida($d)
{
	$dt = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$d);
	if (!$dt || $dt->format('Y-m-d') !== $d) {
		throw new InvalidArgumentException('Data inválida.');
	}
	return $d;
}

// Valida o período pedido. Retorna [ini, fim, cia|null] ou lança InvalidArgumentException.
function vale_parametros(array $get)
{
	$hoje = new DateTimeImmutable('today');
	$ini = $get['ini'] ?? $hoje->modify('first day of this month')->format('Y-m-d');
	$fim = $get['fim'] ?? $hoje->modify('last day of this month')->format('Y-m-d');

	vale_data_valida($ini);
	vale_data_valida($fim);
	if ($fim < $ini) {
		throw new InvalidArgumentException('A data final deve ser igual ou posterior à inicial.');
	}
	$dias = (new DateTimeImmutable($ini))->diff(new DateTimeImmutable($fim))->days + 1;
	if ($dias > VALE_MAX_DIAS) {
		throw new InvalidArgumentException('Período máximo: ' . VALE_MAX_DIAS . ' dias.');
	}

	$cia = null;
	if (isset($get['cia']) && $get['cia'] !== '' && $get['cia'] !== 'todas') {
		if (!ctype_digit((string)$get['cia'])) {
			throw new InvalidArgumentException('Cia inválida.');
		}
		$cia = (int)$get['cia'];
	}
	return [$ini, $fim, $cia];
}

function vale_int($v, $rotulo = 'valor')
{
	if (is_string($v)) {
		$v = trim($v);
		if ($v === '') {
			return 0;
		}
	}
	if (is_bool($v) || filter_var($v, FILTER_VALIDATE_INT) === false || (int)$v < 0 || (int)$v > 99999) {
		throw new InvalidArgumentException('Número inválido em ' . $rotulo . ' (use inteiros de 0 a 99999).');
	}
	return (int)$v;
}

// Decimal com vírgula ou ponto; vazio = null.
function vale_decimal($v, $rotulo = 'quantidade')
{
	if ($v === null) {
		return null;
	}
	$v = str_replace(',', '.', trim((string)$v));
	if ($v === '') {
		return null;
	}
	if (!is_numeric($v) || (float)$v < 0 || (float)$v > 99999999) {
		throw new InvalidArgumentException('Número inválido em ' . $rotulo . '.');
	}
	return round((float)$v, 2);
}

function vale_texto($v, $max)
{
	$v = trim((string)$v);
	if (mb_strlen($v, 'UTF-8') > $max) {
		throw new InvalidArgumentException('Texto muito longo (máximo ' . $max . ' caracteres).');
	}
	return $v;
}

// ---------------------------------------------------------------- contagem (Vale Diário)

function vale_zerado()
{
	return ['cafe' => [0, 0, 0], 'almoco' => [0, 0, 0], 'jantar' => [0, 0, 0], 'qr' => [0, 0, 0]];
}

function vale_soma(array &$dest, array $orig)
{
	foreach (['cafe', 'almoco', 'jantar', 'qr'] as $k) {
		foreach ([0, 1, 2] as $i) {
			$dest[$k][$i] += $orig[$k][$i];
		}
	}
}

function vale_calcular(PDO $cnt, $ini, $fim, $ciaFiltro = null, $aplicarAjustes = true)
{
	// Contagem única de militares por dia/refeição/Cia/categoria.
	$sql = "SELECT r.data, r.tp, COALESCE(c.id, 0) AS cia,
			CASE WHEN p.ordem <= 11 THEN 'of'
			     WHEN p.ordem BETWEEN 12 AND 15 THEN 'sgt'
			     WHEN p.ordem BETWEEN 16 AND 17 THEN 'cb'
			     ELSE 'outro' END AS cat,
			COUNT(DISTINCT r.ident_militar) AS n
		FROM registros r
		LEFT JOIN cad_militar m ON m.ident_militar = r.ident_militar
		LEFT JOIN cad_cia c ON c.id = m.om_id
		LEFT JOIN cad_posto p ON p.id = m.posto
		WHERE r.data BETWEEN :ini AND :fim AND r.tp IN ('1','2','3')
		GROUP BY r.data, r.tp, cia, cat";
	$st = $cnt->prepare($sql);
	$st->execute([':ini' => $ini, ':fim' => $fim]);

	$conta = [];        // [data][cia][refeicao][cat] = n
	$foraDoVale = 0;    // civis ou militares sem P/G cadastrado
	$ciasComDados = [];
	while ($l = $st->fetch(PDO::FETCH_ASSOC)) {
		$cia = (int)$l['cia'];
		if ($ciaFiltro !== null && $cia !== $ciaFiltro) {
			continue;
		}
		if ($l['cat'] === 'outro') {
			$foraDoVale += (int)$l['n'];
			continue;
		}
		$conta[$l['data']][$cia][VALE_REFS[$l['tp']]][$l['cat']] = (int)$l['n'];
		$ciasComDados[$cia] = true;
	}

	// Correções manuais sobrepõem a contagem automática.
	$editado = [];
	if ($aplicarAjustes) {
		$sa = $cnt->prepare('SELECT data, cia, tp, cat, valor FROM vale_ajuste WHERE data BETWEEN :ini AND :fim');
		$sa->execute([':ini' => $ini, ':fim' => $fim]);
		while ($a = $sa->fetch(PDO::FETCH_ASSOC)) {
			$cia = (int)$a['cia'];
			if ($ciaFiltro !== null && $cia !== $ciaFiltro) {
				continue;
			}
			$conta[$a['data']][$cia][VALE_REFS[$a['tp']]][$a['cat']] = (int)$a['valor'];
			$editado[$a['data']] = true;
			$ciasComDados[$cia] = true;
		}
	}

	// Lista de Cias (nome vindo do cadastro).
	$nomes = [];
	foreach ($cnt->query("SELECT id, nome FROM cad_cia") as $c) {
		$nomes[(int)$c['id']] = $c['nome'];
	}
	$nomes[0] = 'Sem Cia';

	$ids = [];
	foreach (VALE_ORDEM_CIAS as $id) {
		if (isset($nomes[$id])) {
			$ids[] = $id;
		}
	}
	foreach (array_keys($ciasComDados) as $id) {
		if (!in_array($id, $ids, true)) {
			$ids[] = $id;
		}
	}
	if ($ciaFiltro !== null) {
		$ids = [$ciaFiltro];
	}

	$cias = [];
	foreach ($ids as $id) {
		$cias[] = ['id' => $id, 'nome' => $nomes[$id] ?? ('Cia ' . $id)];
	}

	$resumo = ['total' => vale_zerado(), 'cias' => []];
	foreach ($ids as $id) {
		$resumo['cias'][$id] = vale_zerado();
	}

	$dias = [];
	$dt = new DateTimeImmutable($ini);
	$fimDt = new DateTimeImmutable($fim);
	for (; $dt <= $fimDt; $dt = $dt->modify('+1 day')) {
		$data = $dt->format('Y-m-d');
		$dia = ['data' => $data, 'dow' => (int)$dt->format('w'), 'editado' => isset($editado[$data]),
			'cias' => [], 'total' => vale_zerado()];

		foreach ($ids as $id) {
			$linha = vale_zerado();
			foreach (['cafe', 'almoco', 'jantar'] as $ref) {
				foreach (VALE_CATS as $i => $cat) {
					$linha[$ref][$i] = $conta[$data][$id][$ref][$cat] ?? 0;
				}
			}
			foreach ([0, 1, 2] as $i) {
				$linha['qr'][$i] = max($linha['cafe'][$i], $linha['almoco'][$i], $linha['jantar'][$i]);
			}
			$dia['cias'][$id] = $linha;
			// O total do dia soma as Cias (o QR total é a soma dos QR de cada Cia, como no V1 da planilha).
			vale_soma($dia['total'], $linha);
			vale_soma($resumo['cias'][$id], $linha);
		}
		vale_soma($resumo['total'], $dia['total']);
		$dias[] = $dia;
	}

	return [
		'ini' => $ini,
		'fim' => $fim,
		'cia' => $ciaFiltro,
		'cias' => $cias,
		'dias' => $dias,
		'resumo' => $resumo,
		'foraDoVale' => $foraDoVale,
		'gerado' => date('d/m/Y H:i:s'),
	];
}

// ---------------------------------------------------------------- dados do dia

function vale_params_padrao()
{
	// origem do Gu Sv: 'salvo' (dia já salvo), 'padrao' (Gu Sv padrão configurado),
	// 'herdado' (copiado do último dia salvo, em herdado_de) ou 'zero' (nada encontrado).
	$p = ['nota_nr' => '', 'sobras' => '', 'herdado' => false, 'origem' => 'zero', 'herdado_de' => null];
	foreach (VALE_PARAMS_INT as $k) {
		$p[$k] = 0;
	}
	return $p;
}

function vale_params_dia(PDO $cnt, $data)
{
	$p = vale_params_padrao();
	$st = $cnt->prepare('SELECT * FROM vale_dia WHERE data = :d');
	$st->execute([':d' => $data]);
	if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
		foreach (VALE_PARAMS_INT as $k) {
			$p[$k] = (int)$r[$k];
		}
		$p['nota_nr'] = (string)$r['nota_nr'];
		$p['sobras'] = (string)$r['sobras'];
		$p['origem'] = 'salvo';
		return $p;
	}

	// Dia ainda sem dados. 1º: Gu Sv padrão, se o aprovisionador configurou um.
	$cfg = vale_config($cnt);
	if (vale_gu_padrao_configurado($cfg)) {
		foreach (VALE_CATS as $cat) {
			$p['gu_' . $cat] = (int)$cfg['gu_padrao_' . $cat];
		}
		$p['origem'] = 'padrao';
		return $p;
	}

	// 2º: a guarnição costuma se repetir, então copia a do último dia salvo.
	$st = $cnt->prepare('SELECT data, gu_of, gu_sgt, gu_cb FROM vale_dia WHERE data < :d ORDER BY data DESC LIMIT 1');
	$st->execute([':d' => $data]);
	if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
		foreach (['gu_of', 'gu_sgt', 'gu_cb'] as $k) {
			$p[$k] = (int)$r[$k];
		}
		$p['herdado'] = true;
		$p['origem'] = 'herdado';
		$p['herdado_de'] = $r['data'];
	}
	return $p;
}

// Há Gu Sv padrão quando pelo menos uma das 3 categorias foi preenchida na configuração.
function vale_gu_padrao_configurado(array $cfg)
{
	foreach (VALE_CATS as $cat) {
		if (($cfg['gu_padrao_' . $cat] ?? '') !== '') {
			return true;
		}
	}
	return false;
}

// Quem se arranchou no dia mas não entra nas colunas de Cia do vale, com o motivo.
// semCadastro: fica fora da conta (sem cadastro de militar, sem P/G ou P/G fora de Of/Sgt/Cb/Sd).
// semCia: entra na conta, mas na coluna "Sem Cia" (P/G válido, Cia não cadastrada).
function vale_pendencias_dia(PDO $cnt, $data)
{
	$st = $cnt->prepare("SELECT r.ident_militar,
			GROUP_CONCAT(DISTINCT r.tp ORDER BY r.tp) AS tps,
			m.id AS mid, m.nome_guerra, p.nome AS posto, p.ordem, c.id AS cia
		FROM registros r
		LEFT JOIN cad_militar m ON m.ident_militar = r.ident_militar
		LEFT JOIN cad_posto p ON p.id = m.posto
		LEFT JOIN cad_cia c ON c.id = m.om_id
		WHERE r.data = :d AND r.tp IN ('1','2','3')
		  AND (m.id IS NULL OR p.id IS NULL OR p.ordem > 17 OR c.id IS NULL)
		GROUP BY r.ident_militar, m.id, m.nome_guerra, p.nome, p.ordem, c.id
		ORDER BY r.ident_militar");
	$st->execute([':d' => $data]);

	$nomesRef = ['1' => 'café', '2' => 'almoço', '3' => 'jantar'];
	$semCadastro = [];
	$semCia = [];
	while ($l = $st->fetch(PDO::FETCH_ASSOC)) {
		$refeicoes = implode(', ', array_map(function ($t) use ($nomesRef) {
			return $nomesRef[$t] ?? $t;
		}, explode(',', (string)$l['tps'])));
		$item = ['cpf' => $l['ident_militar'], 'nome' => (string)$l['nome_guerra'],
			'posto' => (string)$l['posto'], 'refeicoes' => $refeicoes];

		if ($l['mid'] === null) {
			$item['motivo'] = 'Sem cadastro de militar';
		} elseif ($l['ordem'] === null) {
			$item['motivo'] = 'Cadastro sem P/G';
		} elseif ((int)$l['ordem'] > 17) {
			$item['motivo'] = 'P/G "' . $l['posto'] . '" não entra no vale (só Of, ST/Sgt e Cb/Sd)';
		} else {
			$semCia[] = $item;
			continue;
		}
		$semCadastro[] = $item;
	}
	return ['semCadastro' => $semCadastro, 'semCia' => $semCia];
}

function vale_artigos(PDO $cnt)
{
	$out = [];
	foreach ($cnt->query('SELECT id, nome, tabelar, unidade, base FROM vale_artigos ORDER BY ordem, id') as $a) {
		$out[] = ['id' => (int)$a['id'], 'nome' => $a['nome'], 'tabelar' => (float)$a['tabelar'],
			'unidade' => $a['unidade'], 'base' => $a['base']];
	}
	return $out;
}

function vale_fornecido(PDO $cnt, $data)
{
	$st = $cnt->prepare('SELECT artigo_id, fornecida, obs FROM vale_fornecido WHERE data = :d');
	$st->execute([':d' => $data]);
	$out = [];
	while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
		$out[(int)$r['artigo_id']] = [
			'fornecida' => $r['fornecida'] === null ? null : (float)$r['fornecida'],
			'obs' => (string)$r['obs'],
		];
	}
	return $out;
}

function vale_config(PDO $cnt)
{
	// gu_padrao_*: Gu Sv padrão para os dias ainda não salvos ('' = não configurado).
	$cfg = ['om_nome' => '53° BATALHÃO DE INFANTARIA DE SELVA', 'om_sigla' => '53° BIS',
		'aprovisionador' => '', 'local' => 'Itaituba-PA',
		'gu_padrao_of' => '', 'gu_padrao_sgt' => '', 'gu_padrao_cb' => ''];
	foreach ($cnt->query('SELECT chave, valor FROM vale_config') as $r) {
		$cfg[$r['chave']] = $r['valor'];
	}
	return $cfg;
}

// ---------------------------------------------------------------- Vale Total, Nota e Mapa

// $matriz[cia][refeicao] = [of, sgt, cb]. Reproduz as fórmulas das abas Vale Total, Nota para Boletim e Mapa de Gêneros.
function vale_documentos(array $cias, array $matriz, array $p, array $artigos, array $forn)
{
	$ids = array_map(function ($c) { return (int)$c['id']; }, $cias);
	$mat = function ($id, $ref, $i) use ($matriz) {
		return (int)($matriz[$id][$ref][$i] ?? 0);
	};

	// Etapas completas (QR): maior valor entre café, almoço e jantar, por Cia e categoria.
	$qr = [];
	$somaQr = ['cias' => array_fill_keys($ids, 0), 'gu' => 0, 'soma' => 0, 'outras' => 0, 'total' => 0];
	foreach (VALE_CATS as $i => $cat) {
		$l = ['cias' => [], 'gu' => (int)$p['gu_' . $cat], 'outras' => (int)$p['outras_' . $cat]];
		$s = 0;
		foreach ($ids as $id) {
			$v = max($mat($id, 'cafe', $i), $mat($id, 'almoco', $i), $mat($id, 'jantar', $i));
			$l['cias'][$id] = $v;
			$s += $v;
			$somaQr['cias'][$id] += $v;
		}
		$l['soma'] = $s + $l['gu'];
		$l['total'] = $l['soma'] + $l['outras'];
		$qr[$cat] = $l;
		$somaQr['gu'] += $l['gu'];
		$somaQr['soma'] += $l['soma'];
		$somaQr['outras'] += $l['outras'];
		$somaQr['total'] += $l['total'];
	}
	$qr['soma'] = $somaQr;

	// Etapas reduzidas (QS): total de cada refeição por Cia; Gu Sv = total da guarnição (G15 da planilha).
	$qs = [];
	foreach (['cafe', 'almoco', 'jantar'] as $ref) {
		$l = ['cias' => [], 'gu' => $somaQr['gu'], 'outras' => (int)$p['outras_' . $ref]];
		$s = 0;
		foreach ($ids as $id) {
			$v = $mat($id, $ref, 0) + $mat($id, $ref, 1) + $mat($id, $ref, 2);
			$l['cias'][$id] = $v;
			$s += $v;
		}
		$l['soma'] = $s + $l['gu'];
		$l['total'] = $l['soma'] + $l['outras'];
		$qs[$ref] = $l;
	}

	$cafe = $qs['cafe']['total'];
	$almoco = $qs['almoco']['total'];
	$jantar = $qs['jantar']['total'];

	// Efetivo a alimentar (Mapa de Gêneros).
	$bases = [
		'cafe_leite' => (int)floor($cafe / 2 + ($almoco + $jantar) / 4),   // C/2+((A+J)/4)
		'cafe' => $cafe,
		'refeicao' => (int)floor(($almoco + $jantar) / 2),                // (A+J)/2
	];
	$itens = [];
	foreach ($artigos as $a) {
		$efetivo = $bases[$a['base']] ?? $bases['refeicao'];
		$f = $forn[$a['id']] ?? ['fornecida' => null, 'obs' => ''];
		$itens[] = [
			'id' => $a['id'], 'nome' => $a['nome'], 'tabelar' => $a['tabelar'], 'unidade' => $a['unidade'],
			'efetivo' => $efetivo, 'calculada' => round($a['tabelar'] * $efetivo, 4),
			'fornecida' => $f['fornecida'], 'obs' => $f['obs'],
		];
	}

	return [
		'ids' => $ids,
		'qr' => $qr,
		'qs' => $qs,
		'compl' => ['hosp' => (int)$p['c_hosp'], 'esc' => (int)$p['c_esc'], 'cf60' => (int)$p['cf60']],
		'nota' => ['nr' => (string)$p['nota_nr'], 'cf' => $qr['soma']['total']],
		'mapa' => ['cafe' => $cafe, 'almoco' => $almoco, 'jantar' => $jantar, 'bases' => $bases,
			'sobras' => (string)$p['sobras'], 'itens' => $itens],
	];
}

// Estado completo de um dia: contagem automática, valores em uso, o que foi editado, parâmetros e documentos.
function vale_estado_dia(PDO $cnt, $data)
{
	$auto = vale_calcular($cnt, $data, $data, null, false);
	$fin = vale_calcular($cnt, $data, $data, null, true);

	$matriz = [];
	$autom = [];
	$edit = [];
	foreach ($fin['cias'] as $c) {
		$id = $c['id'];
		foreach (['cafe', 'almoco', 'jantar'] as $ref) {
			$f = $fin['dias'][0]['cias'][$id][$ref];
			$a = $auto['dias'][0]['cias'][$id][$ref] ?? [0, 0, 0];
			$matriz[$id][$ref] = $f;
			$autom[$id][$ref] = $a;
			$edit[$id][$ref] = [$f[0] !== $a[0], $f[1] !== $a[1], $f[2] !== $a[2]];
		}
	}

	$params = vale_params_dia($cnt, $data);
	$artigos = vale_artigos($cnt);
	$forn = vale_fornecido($cnt, $data);
	$pend = vale_pendencias_dia($cnt, $data);

	return [
		'semCadastro' => $pend['semCadastro'],
		'semCia' => $pend['semCia'],
		'data' => $data,
		'cias' => $fin['cias'],
		'matriz' => $matriz,
		'auto' => $autom,
		'edit' => $edit,
		'params' => $params,
		'artigos' => $artigos,
		'fornecido' => (object)$forn,
		'config' => vale_config($cnt),
		'foraDoVale' => $fin['foraDoVale'],
		'docs' => vale_documentos($fin['cias'], $matriz, $params, $artigos, $forn),
	];
}

// ---------------------------------------------------------------- entrada do navegador

// Converte o JSON recebido em estruturas validadas. $cias = Cias do dia (ids permitidos).
function vale_ler_entrada(array $in, array $cias, array $artigos)
{
	$ids = array_map(function ($c) { return (int)$c['id']; }, $cias);

	$matriz = [];
	foreach ($ids as $id) {
		foreach (['cafe', 'almoco', 'jantar'] as $ref) {
			$v = $in['matriz'][$id][$ref] ?? [0, 0, 0];
			if (!is_array($v) || count($v) !== 3) {
				throw new InvalidArgumentException('Tabela do Vale Diário incompleta.');
			}
			$matriz[$id][$ref] = [vale_int($v[0], 'Oficiais'), vale_int($v[1], 'ST/SGT'), vale_int($v[2], 'CB/SD')];
		}
	}

	$p = vale_params_padrao();
	$pin = $in['params'] ?? [];
	$rotulos = ['gu_of' => 'Gu Sv (Oficiais)', 'gu_sgt' => 'Gu Sv (St e Sgt)', 'gu_cb' => 'Gu Sv (Cb e Sd)',
		'outras_of' => 'Outras OM (Oficiais)', 'outras_sgt' => 'Outras OM (St e Sgt)', 'outras_cb' => 'Outras OM (Cb e Sd)',
		'outras_cafe' => 'Outras OM (Café)', 'outras_almoco' => 'Outras OM (Almoço)', 'outras_jantar' => 'Outras OM (Jantar)',
		'c_hosp' => 'C Hosp', 'c_esc' => 'C Esc', 'cf60' => 'CF 60%'];
	foreach (VALE_PARAMS_INT as $k) {
		$p[$k] = vale_int($pin[$k] ?? 0, $rotulos[$k] ?? $k);
	}
	$p['nota_nr'] = vale_texto($pin['nota_nr'] ?? '', 20);
	$p['sobras'] = vale_texto($pin['sobras'] ?? '', 255);

	$forn = [];
	$ok = array_column($artigos, 'id');
	foreach (($in['fornecido'] ?? []) as $aid => $f) {
		$aid = (int)$aid;
		if (!in_array($aid, $ok, true) || !is_array($f)) {
			continue;
		}
		$forn[$aid] = ['fornecida' => vale_decimal($f['fornecida'] ?? null, 'quantidade fornecida'),
			'obs' => vale_texto($f['obs'] ?? '', 255)];
	}

	$cfg = [];
	foreach (VALE_CONFIG_CHAVES as $k) {
		if (!isset($in['config'][$k])) {
			continue;
		}
		if (strpos($k, 'gu_padrao_') === 0) {
			// Gu Sv padrão: número, ou vazio para deixar de usar padrão.
			$v = trim((string)$in['config'][$k]);
			$cfg[$k] = $v === '' ? '' : vale_int($v, 'Gu Sv padrão');
		} else {
			$cfg[$k] = vale_texto($in['config'][$k], 120);
		}
	}
	return [$matriz, $p, $forn, $cfg];
}

function vale_salvar_dia(PDO $cnt, $data, array $cias, array $matriz, array $p, array $forn, array $cfg, $usuario)
{
	$auto = vale_calcular($cnt, $data, $data, null, false);
	$agora = date('Y-m-d H:i:s');
	$mudou = 0;

	$cnt->beginTransaction();
	try {
		$del = $cnt->prepare('DELETE FROM vale_ajuste WHERE data = :d AND cia = :c AND tp = :t AND cat = :k');
		$ins = $cnt->prepare('INSERT INTO vale_ajuste (data, cia, tp, cat, valor, usuario, atualizado_em)
			VALUES (:d, :c, :t, :k, :v, :u, :a)
			ON DUPLICATE KEY UPDATE valor = VALUES(valor), usuario = VALUES(usuario), atualizado_em = VALUES(atualizado_em)');
		$atual = $cnt->prepare('SELECT valor FROM vale_ajuste WHERE data = :d AND cia = :c AND tp = :t AND cat = :k');

		foreach ($cias as $c) {
			$id = (int)$c['id'];
			foreach (VALE_TPS as $ref => $tp) {
				foreach (VALE_CATS as $i => $cat) {
					$novo = $matriz[$id][$ref][$i];
					$a = $auto['dias'][0]['cias'][$id][$ref][$i] ?? 0;
					$chave = [':d' => $data, ':c' => $id, ':t' => $tp, ':k' => $cat];
					$atual->execute($chave);
					$ja = $atual->fetchColumn();
					if ($novo === $a) {
						if ($ja !== false) {
							$del->execute($chave);
							$mudou++;
						}
					} elseif ($ja === false || (int)$ja !== $novo) {
						$ins->execute($chave + [':v' => $novo, ':u' => $usuario, ':a' => $agora]);
						$mudou++;
					}
				}
			}
		}

		$cols = array_merge(VALE_PARAMS_INT, ['nota_nr', 'sobras']);
		$sql = 'INSERT INTO vale_dia (data, ' . implode(', ', $cols) . ', usuario, atualizado_em) VALUES (:data, :'
			. implode(', :', $cols) . ', :usuario, :atualizado_em) ON DUPLICATE KEY UPDATE '
			. implode(', ', array_map(function ($c) { return "$c = VALUES($c)"; }, array_merge($cols, ['usuario', 'atualizado_em'])));
		$st = $cnt->prepare($sql);
		$bind = [':data' => $data, ':usuario' => $usuario, ':atualizado_em' => $agora];
		foreach ($cols as $c) {
			$bind[':' . $c] = $p[$c];
		}
		$st->execute($bind);

		$delF = $cnt->prepare('DELETE FROM vale_fornecido WHERE data = :d AND artigo_id = :a');
		$upF = $cnt->prepare('INSERT INTO vale_fornecido (data, artigo_id, fornecida, obs) VALUES (:d, :a, :f, :o)
			ON DUPLICATE KEY UPDATE fornecida = VALUES(fornecida), obs = VALUES(obs)');
		foreach ($forn as $aid => $f) {
			if ($f['fornecida'] === null && $f['obs'] === '') {
				$delF->execute([':d' => $data, ':a' => $aid]);
			} else {
				$upF->execute([':d' => $data, ':a' => $aid, ':f' => $f['fornecida'], ':o' => $f['obs']]);
			}
		}

		if ($cfg) {
			$upC = $cnt->prepare('INSERT INTO vale_config (chave, valor) VALUES (:k, :v) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
			$delC = $cnt->prepare('DELETE FROM vale_config WHERE chave = :k');
			foreach ($cfg as $k => $v) {
				if ($v !== '') {
					$upC->execute([':k' => $k, ':v' => (string)$v]);
				} elseif (strpos($k, 'gu_padrao_') === 0) {
					// Campo de Gu Sv padrão apagado: deixa de existir padrão para essa categoria.
					$delC->execute([':k' => $k]);
				}
			}
		}
		$cnt->commit();
	} catch (Throwable $e) {
		$cnt->rollBack();
		throw $e;
	}
	return $mudou;
}
