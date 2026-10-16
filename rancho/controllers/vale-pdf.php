<?php
// PDF do dia: ?data=AAAA-MM-DD&doc=total|nota|mapa|todos  (usa o que está salvo).
error_reporting(0);

require_once __DIR__ . '/vale-lib.php';
vale_exige_acesso(false);
require_once __DIR__ . '/../../assets/TCPDF/tcpdf.php';

const MESES_PDF = ['', 'JANEIRO', 'FEVEREIRO', 'MARÇO', 'ABRIL', 'MAIO', 'JUNHO', 'JULHO', 'AGOSTO',
	'SETEMBRO', 'OUTUBRO', 'NOVEMBRO', 'DEZEMBRO'];

function h($s)
{
	return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function n2($n)
{
	return str_pad((string)(int)$n, 2, '0', STR_PAD_LEFT);
}

function dec1($x)
{
	return number_format((float)$x, 1, ',', '.');
}

// 1 casa quando termina em zero (10,0), senão 2 casas (2,25).
function dec12($x)
{
	$s = number_format((float)$x, 2, ',', '.');
	return substr($s, -1) === '0' ? substr($s, 0, -1) : $s;
}

function data_ext($data)
{
	$t = strtotime($data);
	return n2(date('j', $t)) . ' de ' . MESES_PDF[(int)date('n', $t)] . ' de ' . date('Y', $t);
}

function nome_cia($nome)
{
	return h($nome);
}

const CSS_PDF = '<style>
	th { background-color:#e6e6e6; font-weight:bold; text-align:center; }
	td { text-align:center; }
	td.esq { text-align:left; }
	td.tot { font-weight:bold; background-color:#f2f2f2; }
	h3 { text-align:center; }
</style>';

function assinatura($cfg, $data, $funcao)
{
	return '<p></p><p>Quartel em ' . h($cfg['local']) . ', ' . h(data_ext($data)) . '.</p>'
		. '<p></p><p style="text-align:center">___________________________________________<br>'
		. h($cfg['aprovisionador']) . '<br>' . h($funcao) . '</p>';
}

// ---------------------------------------------------------------- Vale Total

function html_vale_total($e)
{
	$d = $e['docs'];
	$cfg = $e['config'];
	$cias = $e['cias'];
	$nc = count($cias);

	$cab = '<th width="13%">%s</th>';
	$largCia = (int)floor(35 / max($nc, 1) * 10) / 10;

	$th = function ($titulo) use ($cias, $largCia) {
		$h = '<tr><th width="12%">' . $titulo . '</th>';
		foreach ($cias as $c) {
			$h .= '<th width="' . $largCia . '%">' . nome_cia($c['nome']) . '</th>';
		}
		return $h;
	};

	$linhaCias = function ($l) use ($cias) {
		$h = '';
		foreach ($cias as $c) {
			$h .= '<td>' . n2($l['cias'][$c['id']]) . '</td>';
		}
		return $h;
	};

	// Etapas completas
	$t = '<table border="1" cellpadding="3">';
	$t .= $th('ETAPAS COMPLETAS')
		. '<th width="6%">Gu Sv</th><th width="6%">SOMA</th><th width="10%">Alimentados de outras OM</th>'
		. '<th width="5%">Tipo</th><th width="6%">Quant</th><th width="6%">C Hosp</th><th width="6%">C Esc</th><th width="6%">CF 60%</th></tr>';
	$rot = ['of' => 'Oficiais', 'sgt' => 'St e Sgt', 'cb' => 'Cb e Sd'];
	foreach ($rot as $k => $nome) {
		$l = $d['qr'][$k];
		$t .= '<tr><td class="esq"><b>' . $nome . '</b></td>' . $linhaCias($l)
			. '<td>' . n2($l['gu']) . '</td><td>' . n2($l['soma']) . '</td><td>' . n2($l['outras']) . '</td>'
			. '<td>QR</td><td class="tot">' . n2($l['total']) . '</td><td></td><td></td><td></td></tr>';
	}
	$s = $d['qr']['soma'];
	$t .= '<tr><td class="esq"><b>SOMA</b></td>' . $linhaCias($s)
		. '<td class="tot">' . n2($s['gu']) . '</td><td class="tot">' . n2($s['soma']) . '</td><td class="tot">' . n2($s['outras']) . '</td>'
		. '<td>CF</td><td class="tot">' . n2($s['total']) . '</td>'
		. '<td>' . n2($d['compl']['hosp']) . '</td><td>' . n2($d['compl']['esc']) . '</td><td>' . n2($d['compl']['cf60']) . '</td></tr>';
	$t .= '</table>';

	// Etapas reduzidas
	$r = '<table border="1" cellpadding="3">';
	$r .= $th('ETAPAS REDUZIDAS')
		. '<th width="6%">Gu Sv</th><th width="6%">SOMA</th><th width="10%">Alimentados de outras OM</th>'
		. '<th width="5%">Tipo</th><th width="6%">Quant</th><th width="18%" colspan="3"></th></tr>';
	foreach (['cafe' => 'Café', 'almoco' => 'Almoço', 'jantar' => 'Jantar'] as $k => $nome) {
		$l = $d['qs'][$k];
		$r .= '<tr><td class="esq"><b>' . $nome . '</b></td>' . $linhaCias($l)
			. '<td>' . n2($l['gu']) . '</td><td>' . n2($l['soma']) . '</td><td>' . n2($l['outras']) . '</td>'
			. '<td>QS</td><td class="tot">' . n2($l['total']) . '</td><td colspan="3"></td></tr>';
	}
	$r .= '</table>';

	return CSS_PDF
		. '<table width="100%"><tr><td width="20%" style="text-align:left"><b>V I S T O</b><br>_____________<br>Fisc Adm</td>'
		. '<td width="80%" style="text-align:center"><b>' . h($cfg['om_nome']) . '</b><br>'
		. '<b>Vale Total de Rações para dia ' . h(data_ext($e['data'])) . '</b></td></tr></table>'
		. '<p></p>' . $t . '<p></p>' . $r
		. '<p style="font-size:8pt">QR - Quantitativo de Rancho &nbsp;&nbsp; CF - Complemento Financeiro &nbsp;&nbsp; QS - Quantitativo de Subsistência</p>'
		. assinatura($cfg, $e['data'], 'Aprovisionador');
}

// ---------------------------------------------------------------- Nota para Boletim

function html_nota($e)
{
	$d = $e['docs'];
	$cfg = $e['config'];
	$nr = $d['nota']['nr'] !== '' ? h($d['nota']['nr']) : '____';

	$linhas = [
		['Oficiais', $d['qr']['of']['total'], 'CF', $d['qr']['soma']['total']],
		['St/Sgt', $d['qr']['sgt']['total'], 'C Esc', $d['compl']['esc']],
		['Cb/Sd', $d['qr']['cb']['total'], 'C Hosp', $d['compl']['hosp']],
		['', '', 'CF 60%', $d['compl']['cf60']],
	];
	$tb = '<table border="1" cellpadding="3"><tr><th colspan="3" width="50%">a. Quantitativos</th><th width="6%" style="border:none;background-color:#ffffff"></th><th colspan="2" width="44%">b. Complementos</th></tr>'
		. '<tr><th>Classes de Efetivos</th><th>Tipo</th><th>Quant</th><th style="background-color:#ffffff"></th><th>Tipo</th><th>Quant</th></tr>';
	foreach ($linhas as $l) {
		$tb .= '<tr><td class="esq">' . h($l[0]) . '</td><td>' . ($l[0] !== '' ? 'QR' : '') . '</td><td>' . ($l[0] !== '' ? n2($l[1]) : '') . '</td>'
			. '<td></td><td>' . h($l[2]) . '</td><td>' . n2($l[3]) . '</td></tr>';
	}
	$tb .= '</table>';

	$m = $d['mapa'];
	$refs = '<table border="1" cellpadding="4"><tr>'
		. '<td class="esq"><b>CAFÉ:</b></td><td class="tot">' . n2($m['cafe']) . '</td>'
		. '<td class="esq"><b>ALMOÇO:</b></td><td class="tot">' . n2($m['almoco']) . '</td>'
		. '<td class="esq"><b>JANTAR:</b></td><td class="tot">' . n2($m['jantar']) . '</td></tr></table>';

	return CSS_PDF
		. '<table width="100%"><tr><td width="50%" style="text-align:left"><b>PUBLIQUE-SE</b><br>______________<br>OD</td>'
		. '<td width="50%" style="text-align:right"><b>' . h($cfg['om_sigla']) . '</b></td></tr></table>'
		. '<p></p><h3>Nota para Boletim Nr ' . $nr . ' sobre arranchamento<br>para o dia ' . h(data_ext($e['data'])) . '</h3><p></p>'
		. '<p>1. A Fiscalização Administrativa providencie o saque dos seguintes Quantitativos e complementos referentes às Etapas Completas:</p>'
		. $tb
		. '<p></p><p>2. O Serviço de Aprovisionamento confeccione as refeições correspondentes às seguintes Etapas Reduzidas (QS):</p>'
		. $refs
		. '<p></p><p>3. Fiscal de Sobras e Resíduos: Adj Of Dia</p>'
		. '<p></p><p>Quartel em ' . h($cfg['local']) . ', ' . h(data_ext($e['data'])) . '.</p>'
		. '<p></p><p style="text-align:right">________________<br>Fisc Adm&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>';
}

// ---------------------------------------------------------------- Mapa de Gêneros

function html_mapa($e)
{
	$d = $e['docs'];
	$cfg = $e['config'];
	$m = $d['mapa'];

	$t = '<table border="1" cellpadding="3"><tr>'
		. '<th width="34%">A R T I G O S</th><th width="13%">QUANT.<br>TABELAR</th><th width="13%">EFETIVO A<br>ALIMENTAR</th>'
		. '<th width="13%">QUANT.<br>CALCULADA</th><th width="13%">QUANT.<br>FORNECIDA</th><th width="14%">OBS</th></tr>';
	foreach ($m['itens'] as $i) {
		$t .= '<tr><td class="esq">' . h($i['nome']) . '</td>'
			. '<td>' . number_format($i['tabelar'], 3, ',', '') . ' ' . h($i['unidade']) . '</td>'
			. '<td>' . (int)$i['efetivo'] . '</td>'
			. '<td>' . dec1($i['calculada']) . '</td>'
			. '<td>' . ($i['fornecida'] === null ? '' : dec12($i['fornecida'])) . '</td>'
			. '<td class="esq" style="font-size:7pt">' . h($i['obs']) . '</td></tr>';
	}
	$t .= '</table>';

	return CSS_PDF
		. '<table width="100%"><tr><td width="20%" style="text-align:left"><b>V I S T O</b><br>_________________<br>Fisc Adm</td>'
		. '<td width="80%" style="text-align:center"><b>' . h($cfg['om_nome']) . '</b><br>'
		. '<b>Mapa de gêneros para o dia ' . h(data_ext($e['data'])) . '</b></td></tr></table>'
		. '<p>a. Sobras de Alimentos do dia: ' . h($m['sobras']) . '</p>'
		. '<p>b. Destinação: Recolhido por contrato não oneroso à União.</p>'
		. '<p>c. Gêneros a serem fornecidos para um dia: ' . n2(date('j', strtotime($e['data']))) . ' ( abatidas as sobras )</p>'
		. $t
		. '<p style="font-size:8pt">Efetivo: café ' . (int)$m['cafe'] . ' &nbsp;|&nbsp; almoço ' . (int)$m['almoco'] . ' &nbsp;|&nbsp; jantar ' . (int)$m['jantar'] . '</p>'
		. assinatura($cfg, $e['data'], 'Aprovisionador');
}

// ---------------------------------------------------------------- montagem

try {
	$data = vale_data_valida($_GET['data'] ?? '');
	$doc = $_GET['doc'] ?? 'todos';
	if (!in_array($doc, ['total', 'nota', 'mapa', 'todos'], true)) {
		throw new InvalidArgumentException('Documento inválido.');
	}
	$e = vale_estado_dia($cnt, $data);
} catch (InvalidArgumentException $ex) {
	http_response_code(400);
	echo h($ex->getMessage());
	exit;
} catch (Throwable $ex) {
	http_response_code(500);
	echo 'Não foi possível gerar o PDF agora.';
	exit;
}

$titulos = ['total' => 'Vale Total', 'nota' => 'Nota para Boletim', 'mapa' => 'Mapa de Gêneros', 'todos' => 'Vale de Rações'];

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setCreator('Sistema de Arranchamento');
$pdf->setAuthor($e['config']['om_sigla']);
$pdf->setTitle($titulos[$doc] . ' ' . date('d/m/Y', strtotime($data)));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->setMargins(12, 12, 12);
$pdf->setAutoPageBreak(true, 10);
$pdf->setFontSubsetting(true);

$paginas = [];
if ($doc === 'total' || $doc === 'todos') {
	$paginas[] = ['L', html_vale_total($e)];
}
if ($doc === 'nota' || $doc === 'todos') {
	$paginas[] = ['P', html_nota($e)];
}
if ($doc === 'mapa' || $doc === 'todos') {
	$paginas[] = ['P', html_mapa($e)];
}
foreach ($paginas as [$orient, $html]) {
	$pdf->AddPage($orient);
	$pdf->setFont('dejavusans', '', 9);
	$pdf->writeHTML($html, true, false, true, false, '');
}

$nomes = ['total' => 'vale-total', 'nota' => 'nota-boletim', 'mapa' => 'mapa-generos', 'todos' => 'vale-racoes'];
$nomeArq = $nomes[$doc] . '-' . $data . '.pdf';
header('Cache-Control: no-store');
$pdf->Output($nomeArq, 'I');
