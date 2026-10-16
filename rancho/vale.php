<?php
error_reporting(0);
session_start();

// Só perfil Administrador (inclui o usuário do Aprovisionamento).
if (!isset($_SESSION['usuario']['id']) || (int)($_SESSION['usuario']['tipo'] ?? 0) !== 1) {
	header('location: index.php');
	exit;
}

require_once __DIR__ . '/controllers/vale-lib.php';

$cias = [];
foreach (VALE_ORDEM_CIAS as $id) {
	$st = $cnt->prepare('SELECT id, nome FROM cad_cia WHERE id = :id');
	$st->execute([':id' => $id]);
	if ($c = $st->fetch(PDO::FETCH_ASSOC)) {
		$cias[] = $c;
	}
}
$hoje = new DateTimeImmutable('today');
$iniPadrao = $hoje->modify('first day of this month')->format('Y-m-d');
$fimPadrao = $hoje->modify('last day of this month')->format('Y-m-d');
$versaoJs = @filemtime(__DIR__ . '/../assets/js/vale-dia.js') ?: 1;
?>
<!DOCTYPE html>
<html>

<head>
	<title>Adm | Vale de Rações</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="icon" type="image/png" href="../assets/img/logo_arranchamento.png">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel="stylesheet" href="../assets/css/bootstrap.css">
	<style>
		body { height: auto; font-size: 13px; }
		.container-fluid { margin-top: 15px; }
		.filtros .form-group { margin-right: 10px; margin-bottom: 8px; }
		.status { font-size: 12px; color: #777; }
		.cards .panel { margin-bottom: 10px; }
		.cards .num { font-size: 26px; font-weight: bold; line-height: 1.1; }
		.tabela-vale { overflow-x: auto; }
		.tabela-vale table { font-size: 12px; }
		.tabela-vale th, .tabela-vale td { text-align: center; vertical-align: middle !important; white-space: nowrap; }
		.tabela-vale td.tot { font-weight: bold; background: #f5f5f5; }
		.tabela-vale tr.fds > td { background: #fff8e1; }
		.tabela-vale tr.fds > td.tot { background: #ffefb3; }
		.tabela-vale tr.zerado > td { color: #bbb; }
		.tabela-vale tr.cia-linha > td { font-size: 11px; color: #555; background: #fafafa; }
		.tabela-vale tr.cia-linha > td.nome { text-align: left; padding-left: 22px; }
		.tabela-vale tfoot td, .tabela-vale tr.total-linha td { font-weight: bold; background: #e8eef7; }
		.cab-cafe { background: #DEB887 !important; }
		.cab-almoco { background: #DAA520 !important; }
		.cab-jantar { background: #CD853F !important; }
		.cab-qr { background: #cfe2f3 !important; }
		.so-impressao { display: none; }
		.abas { margin-bottom: 15px; }
		.aba-conteudo { display: none; }
		.aba-conteudo.ativa { display: block; }
		#dia-barra { position: sticky; top: 0; z-index: 10; background: #fff; padding: 8px 0; border-bottom: 1px solid #ddd; margin-bottom: 10px; }
		#dia-barra .btn { margin-bottom: 4px; }
		.vi { width: 62px; padding: 2px 4px; text-align: center; display: inline-block; }
		.vi.ed { background: #fff3b0; font-weight: bold; border-color: #e0b400; }
		.vp { width: 72px; padding: 2px 4px; text-align: center; display: inline-block; }
		.nota p { margin-bottom: 6px; }
		.cfg-om { margin: 15px 0; }
		#painel-dia h4 { margin-top: 22px; }
		#painel-dia h4 small { font-size: 12px; }
		.mes-editado { color: #b58900; }
		a.abrir-dia { text-decoration: none; }

		@media print {
			@page { size: A4 landscape; margin: 10mm; }
			body { font-size: 10px; }
			.no-print { display: none !important; }
			.so-impressao { display: block; }
			.container-fluid { margin-top: 0; }
			.tabela-vale { overflow: visible; }
			.tabela-vale table { font-size: 9px; }
			.tabela-vale tr { page-break-inside: avoid; }
			thead { display: table-header-group; }
			.cards .panel { border: 1px solid #999; }
		}
	</style>
</head>

<body>
	<div class="container-fluid">

		<div class="so-impressao text-center">
			<h4 style="margin:0">53º BATALHÃO DE INFANTARIA DE SELVA</h4>
			<h5 style="margin:2px 0 8px">Vale de Rações &mdash; quantitativo de arranchados</h5>
		</div>

		<div class="row no-print">
			<div class="col-xs-12">
				<h3 style="margin-top:0"><i class="fa fa-utensils"></i> Vale de Rações
					<a href="home.php" class="btn btn-default btn-sm pull-right"><i class="fa fa-arrow-left"></i> Voltar</a>
				</h3>
				<p class="text-muted">Os números vêm do arranchamento e se atualizam sozinhos. Você pode corrigir qualquer número na hora e gerar o PDF do Vale Total, da Nota para Boletim e do Mapa de Gêneros.</p>
			</div>
		</div>

		<ul class="nav nav-tabs abas no-print" id="abas">
			<li class="active"><a href="#" data-aba="dia"><i class="fa fa-file-pen"></i> Documentos do dia</a></li>
			<li><a href="#" data-aba="mes"><i class="fa fa-calendar-days"></i> Visão do mês</a></li>
		</ul>

		<!-- ===================================== DOCUMENTOS DO DIA ===================================== -->
		<div class="aba-conteudo ativa" id="aba-dia">
			<div id="painel-dia">
				<div id="dia-barra" class="no-print">
					<div class="form-inline">
						<div class="btn-group btn-group-sm">
							<button type="button" class="btn btn-default" id="dia-ant" title="Dia anterior"><i class="fa fa-chevron-left"></i></button>
							<button type="button" class="btn btn-default" id="dia-hoje">Hoje</button>
							<button type="button" class="btn btn-default" id="dia-prox" title="Próximo dia"><i class="fa fa-chevron-right"></i></button>
						</div>
						<input type="date" class="form-control input-sm" id="dia-data" style="margin:0 8px">
						<span id="dia-estado" style="margin-right:6px"></span>
						<span id="dia-atualizado" class="status" style="margin-right:12px"></span>
						<button type="button" class="btn btn-default btn-sm" id="btn-atualizar-dia" title="Buscar números mais recentes agora"><i class="fa fa-rotate"></i></button>
						<button type="button" class="btn btn-success btn-sm" id="btn-salvar"><i class="fa fa-floppy-disk"></i> Salvar alterações</button>
						<button type="button" class="btn btn-default btn-sm" id="btn-restaurar" title="Volta o Vale Diário do dia ao número automático"><i class="fa fa-rotate-left"></i> Voltar ao automático</button>
						<span class="hidden-xs" style="margin-left:10px;color:#777">PDF:</span>
						<button type="button" class="btn btn-primary btn-sm" data-pdf="todos"><i class="fa fa-file-pdf"></i> Os 3 documentos</button>
						<button type="button" class="btn btn-default btn-sm" data-pdf="total">Vale Total</button>
						<button type="button" class="btn btn-default btn-sm" data-pdf="nota">Nota p/ Boletim</button>
						<button type="button" class="btn btn-default btn-sm" data-pdf="mapa">Mapa de Gêneros</button>
					</div>
					<div class="alert alert-info" id="dia-aviso" style="display:none;margin:8px 0 0"></div>
				</div>
				<div id="dia-corpo"><p class="text-muted">Carregando...</p></div>
			</div>
		</div>

		<!-- ===================================== VISÃO DO MÊS ===================================== -->
		<div class="aba-conteudo" id="aba-mes">
			<form class="form-inline filtros no-print" id="filtros" onsubmit="return false;">
				<div class="form-group">
					<label for="ini">De</label>
					<input type="date" class="form-control input-sm" id="ini" value="<?php echo $iniPadrao; ?>">
				</div>
				<div class="form-group">
					<label for="fim">Até</label>
					<input type="date" class="form-control input-sm" id="fim" value="<?php echo $fimPadrao; ?>">
				</div>
				<div class="form-group">
					<label for="cia">Cia</label>
					<select id="cia" class="form-control input-sm">
						<option value="todas">Todas</option>
						<?php foreach ($cias as $c) { ?>
							<option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['nome']); ?></option>
						<?php } ?>
					</select>
				</div>
				<div class="form-group">
					<label class="checkbox-inline"><input type="checkbox" id="detalhar"> Detalhar por Cia em cada dia</label>
				</div>
				<div class="form-group">
					<div class="btn-group btn-group-sm">
						<button type="button" class="btn btn-default" data-atalho="hoje">Hoje</button>
						<button type="button" class="btn btn-default" data-atalho="7dias">Próx. 7 dias</button>
						<button type="button" class="btn btn-default" data-atalho="mes">Mês atual</button>
						<button type="button" class="btn btn-default" data-atalho="proxmes">Próx. mês</button>
					</div>
				</div>
				<div class="form-group">
					<button type="button" class="btn btn-primary btn-sm" id="btn-atualizar"><i class="fa fa-rotate"></i> Atualizar</button>
					<button type="button" class="btn btn-success btn-sm" id="btn-csv"><i class="fa fa-file-excel"></i> Baixar para Excel (CSV)</button>
					<button type="button" class="btn btn-default btn-sm" id="btn-imprimir"><i class="fa fa-print"></i> Imprimir relatório</button>
				</div>
			</form>

			<p class="status no-print" id="status">Carregando...</p>
			<div class="alert alert-danger no-print" id="erro" style="display:none"></div>

			<p class="so-impressao" id="cab-impressao" style="text-align:center"></p>

			<div class="row cards" id="cards"></div>

			<h5><b>Resumo do período por Cia</b> <small>(soma das refeições de todos os dias)</small></h5>
			<div class="tabela-vale">
				<table class="table table-bordered table-condensed" id="tb-resumo">
					<thead></thead>
					<tbody></tbody>
				</table>
			</div>

			<h5><b>Dia a dia</b> <small>&mdash; "Etapas completas" = maior valor entre café, almoço e jantar de cada categoria (por Cia), como na aba VALE_DIÁRIO. <span class="mes-editado"><i class="fa fa-pen"></i> = dia com número corrigido à mão.</span> Clique no dia para abrir os documentos dele.</small></h5>
			<div class="tabela-vale">
				<table class="table table-bordered table-condensed" id="tb-dias">
					<thead></thead>
					<tbody></tbody>
					<tfoot></tfoot>
				</table>
			</div>

			<p class="text-muted" id="nota-fora" style="display:none"></p>
			<p class="so-impressao" style="margin-top:30px">Itaituba-PA, <span id="data-impressao"></span>.<br><br>___________________________________________<br>Aprovisionador</p>
		</div>
	</div>

	<script>
		(function() {
			'use strict';
			var DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
			var $ = function(id) { return document.getElementById(id); };
			var ultimo = null;

			function esc(s) {
				return String(s).replace(/[&<>"']/g, function(c) {
					return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
				});
			}
			function soma(a) { return a[0] + a[1] + a[2]; }
			function br(d) { var p = d.split('-'); return p[2] + '/' + p[1] + '/' + p[0]; }
			function iso(d) {
				return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
			}
			function query() {
				return 'ini=' + encodeURIComponent($('ini').value) + '&fim=' + encodeURIComponent($('fim').value) +
					'&cia=' + encodeURIComponent($('cia').value);
			}

			// ---- abas
			function abrirAba(nome) {
				document.querySelectorAll('#abas li').forEach(function(li) {
					li.classList.toggle('active', li.firstElementChild.getAttribute('data-aba') === nome);
				});
				$('aba-dia').classList.toggle('ativa', nome === 'dia');
				$('aba-mes').classList.toggle('ativa', nome === 'mes');
				if (nome === 'mes') { carregar(); }
			}
			document.querySelectorAll('#abas a').forEach(function(a) {
				a.addEventListener('click', function(ev) { ev.preventDefault(); abrirAba(a.getAttribute('data-aba')); });
			});

			// 3 categorias + total, para café/almoço/jantar/etapas.
			function celulas(x) {
				var h = '';
				['cafe', 'almoco', 'jantar', 'qr'].forEach(function(k) {
					h += '<td>' + x[k][0] + '</td><td>' + x[k][1] + '</td><td>' + x[k][2] + '</td><td class="tot">' + soma(x[k]) + '</td>';
				});
				return h;
			}
			function cabecalho(primeira) {
				var sub = '<th>Of</th><th>ST/SGT</th><th>CB/SD</th><th>Total</th>';
				return '<tr><th rowspan="2">' + primeira + '</th>' +
					'<th colspan="4" class="cab-cafe">Café</th><th colspan="4" class="cab-almoco">Almoço</th>' +
					'<th colspan="4" class="cab-jantar">Jantar</th><th colspan="4" class="cab-qr">Etapas completas (QR)</th></tr>' +
					'<tr>' + sub + sub + sub + sub + '</tr>';
			}

			function desenhar(v) {
				var multi = v.cias.length > 1;

				var t = v.resumo.total;
				var cards = [
					['Café', soma(t.cafe), 'panel-warning'],
					['Almoço', soma(t.almoco), 'panel-warning'],
					['Jantar', soma(t.jantar), 'panel-warning'],
					['Etapas completas', soma(t.qr), 'panel-primary']
				];
				$('cards').innerHTML = cards.map(function(c) {
					return '<div class="col-xs-6 col-md-3"><div class="panel ' + c[2] + '"><div class="panel-body text-center">' +
						'<div class="num">' + c[1] + '</div><div>' + c[0] + ' no período</div></div></div></div>';
				}).join('');

				$('tb-resumo').querySelector('thead').innerHTML = cabecalho('Cia');
				var linhas = v.cias.map(function(c) {
					return '<tr><td style="text-align:left">' + esc(c.nome) + '</td>' + celulas(v.resumo.cias[c.id]) + '</tr>';
				}).join('');
				if (multi) {
					linhas += '<tr class="total-linha"><td style="text-align:left"><b>TOTAL</b></td>' + celulas(v.resumo.total) + '</tr>';
				}
				$('tb-resumo').querySelector('tbody').innerHTML = linhas;

				$('tb-dias').querySelector('thead').innerHTML = cabecalho('Dia');
				var detalhar = $('detalhar').checked && multi;
				var corpo = '';
				v.dias.forEach(function(d) {
					var zerado = soma(d.total.cafe) + soma(d.total.almoco) + soma(d.total.jantar) === 0;
					var cls = (d.dow === 0 || d.dow === 6 ? 'fds ' : '') + (zerado ? 'zerado' : '');
					corpo += '<tr class="' + cls + '"><td style="text-align:left"><a href="#" class="abrir-dia" data-dia="' + d.data + '"><b>' +
						br(d.data).slice(0, 5) + '</b> ' + DIAS[d.dow] + '</a>' +
						(d.editado ? ' <i class="fa fa-pen mes-editado" title="Tem número corrigido à mão"></i>' : '') + '</td>' + celulas(d.total) + '</tr>';
					if (detalhar && !zerado) {
						v.cias.forEach(function(c) {
							corpo += '<tr class="cia-linha"><td class="nome">' + esc(c.nome) + '</td>' + celulas(d.cias[c.id]) + '</tr>';
						});
					}
				});
				$('tb-dias').querySelector('tbody').innerHTML = corpo;
				$('tb-dias').querySelector('tfoot').innerHTML = '<tr><td style="text-align:left">TOTAL</td>' + celulas(v.resumo.total) + '</tr>';

				var nota = $('nota-fora');
				if (v.foraDoVale > 0) {
					nota.style.display = 'block';
					nota.textContent = 'Obs.: ' + v.foraDoVale + ' arranchamento(s) no período não entram no vale por serem de civis ou de militares sem P/G cadastrado.';
				} else {
					nota.style.display = 'none';
				}

				var ciaTxt = $('cia').options[$('cia').selectedIndex].text;
				$('cab-impressao').innerHTML = 'Período: <b>' + br(v.ini) + '</b> a <b>' + br(v.fim) + '</b> &mdash; Cia: <b>' + esc(ciaTxt) + '</b> &mdash; gerado em ' + esc(v.gerado);
				$('data-impressao').textContent = v.gerado.slice(0, 10);
			}

			function carregar() {
				$('erro').style.display = 'none';
				fetch('controllers/vale-dados.php?' + query(), { credentials: 'same-origin', cache: 'no-store' })
					.then(function(r) { return r.json().then(function(j) { return { ok: r.ok, status: r.status, j: j }; }); })
					.then(function(r) {
						if (r.status === 401) { window.location.href = 'index.php'; return; }
						if (!r.ok || r.j.error) { throw new Error(r.j.error || 'Falha ao carregar.'); }
						ultimo = r.j;
						desenhar(r.j);
						$('status').textContent = 'Atualizado às ' + r.j.gerado.slice(11) + ' — atualiza sozinho a cada 60 segundos.';
					})
					.catch(function(e) {
						$('erro').textContent = e.message || 'Falha ao carregar.';
						$('erro').style.display = 'block';
						$('status').textContent = 'Não foi possível atualizar.';
					});
			}
			window.valeAtualizarMes = function() { if ($('aba-mes').classList.contains('ativa')) { carregar(); } };

			function definirPeriodo(a, b) { $('ini').value = iso(a); $('fim').value = iso(b); carregar(); }
			document.querySelectorAll('[data-atalho]').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var h = new Date(); h.setHours(0, 0, 0, 0);
					switch (btn.getAttribute('data-atalho')) {
						case 'hoje': definirPeriodo(h, h); break;
						case '7dias': definirPeriodo(h, new Date(h.getFullYear(), h.getMonth(), h.getDate() + 6)); break;
						case 'mes': definirPeriodo(new Date(h.getFullYear(), h.getMonth(), 1), new Date(h.getFullYear(), h.getMonth() + 1, 0)); break;
						case 'proxmes': definirPeriodo(new Date(h.getFullYear(), h.getMonth() + 1, 1), new Date(h.getFullYear(), h.getMonth() + 2, 0)); break;
					}
				});
			});
			['ini', 'fim', 'cia'].forEach(function(id) { $(id).addEventListener('change', carregar); });
			$('detalhar').addEventListener('change', function() { if (ultimo) { desenhar(ultimo); } });
			$('btn-atualizar').addEventListener('click', carregar);
			$('btn-csv').addEventListener('click', function() { window.location.href = 'controllers/vale-csv.php?' + query(); });
			$('btn-imprimir').addEventListener('click', function() { if (ultimo) { desenhar(ultimo); } window.print(); });

			// Clique num dia da visão do mês abre os documentos dele.
			$('tb-dias').addEventListener('click', function(ev) {
				var a = ev.target.closest ? ev.target.closest('a.abrir-dia') : null;
				if (!a) { return; }
				ev.preventDefault();
				abrirAba('dia');
				if (window.valeAbrirDia) { window.valeAbrirDia(a.getAttribute('data-dia')); }
			});

			// A visão do mês se atualiza sozinha enquanto estiver aberta.
			setInterval(function() { if (!document.hidden && $('aba-mes').classList.contains('ativa')) { carregar(); } }, 60000);
		})();
	</script>
	<script src="../assets/js/vale-dia.js?v=<?php echo (int)$versaoJs; ?>"></script>
</body>

</html>
