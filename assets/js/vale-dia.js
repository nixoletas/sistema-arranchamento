// Painel "Documentos do dia" (rancho/vale.php): Vale Diário editável + Vale Total, Nota para Boletim e Mapa de Gêneros.
// Todo cálculo é feito no servidor (controllers/vale-dia.php), o mesmo usado no PDF: aqui só se digita e se mostra.
(function() {
	'use strict';

	var API = 'controllers/vale-dia.php';
	var PDF = 'controllers/vale-pdf.php';
	var REFS = [['cafe', 'Café'], ['almoco', 'Almoço'], ['jantar', 'Jantar']];
	var CATS = ['Of', 'ST/SGT', 'CB/SD'];
	var MESES = ['JANEIRO', 'FEVEREIRO', 'MARÇO', 'ABRIL', 'MAIO', 'JUNHO', 'JULHO', 'AGOSTO', 'SETEMBRO', 'OUTUBRO', 'NOVEMBRO', 'DEZEMBRO'];

	var raiz = document.getElementById('painel-dia');
	if (!raiz) { return; }

	var E = null;            // estado carregado do servidor
	var csrf = '';
	var sujo = false;        // há alterações não salvas
	var timerCalc = null;
	var seq = 0;             // descarta respostas de cálculo antigas

	function el(id) { return document.getElementById(id); }
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function pad2(n) { n = parseInt(n, 10) || 0; return (n < 10 ? '0' : '') + n; }
	function dec1(x) { return (Math.round((+x || 0) * 10) / 10).toFixed(1).replace('.', ','); }
	function brData(iso) { var p = iso.split('-'); return p[2] + '/' + p[1] + '/' + p[0]; }
	function dataExt(iso) { var p = iso.split('-'); return pad2(p[2]) + ' de ' + MESES[+p[1] - 1] + ' de ' + p[0]; }
	function hojeISO() {
		var d = new Date();
		return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
	}
	function somaDias(iso, n) {
		var p = iso.split('-');
		var d = new Date(+p[0], +p[1] - 1, +p[2] + n);
		return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
	}

	// ------------------------------------------------------------ chamadas ao servidor

	function chamar(metodo, url, corpo) {
		var opt = { method: metodo, credentials: 'same-origin', cache: 'no-store', headers: {} };
		if (corpo) {
			opt.headers['Content-Type'] = 'application/json';
			opt.headers['X-CSRF-Token'] = csrf;
			opt.body = JSON.stringify(corpo);
		}
		return fetch(url, opt).then(function(r) {
			return r.json().then(function(j) { return { status: r.status, ok: r.ok, j: j }; });
		}).then(function(r) {
			if (r.status === 401) { window.location.href = 'index.php'; throw new Error('Sessão expirada.'); }
			if (!r.ok || r.j.error) { throw new Error(r.j.error || 'Falha na comunicação.'); }
			return r.j;
		});
	}

	function aviso(txt, tipo) {
		var b = el('dia-aviso');
		b.className = 'alert alert-' + (tipo || 'info');
		b.textContent = txt;
		b.style.display = txt ? 'block' : 'none';
	}

	function estadoBarra() {
		var s = el('dia-estado');
		if (sujo) { s.innerHTML = '<span class="label label-warning">Alterações não salvas</span>'; }
		else { s.innerHTML = '<span class="label label-success">Salvo</span>'; }
	}

	// ------------------------------------------------------------ carregar / desenhar

	function carregar(data) {
		aviso('');
		return chamar('GET', API + '?data=' + encodeURIComponent(data)).then(function(j) {
			E = j;
			csrf = j.csrf;
			sujo = false;
			desenhar();
			marcarAtualizado();
			return j;
		}).catch(function(e) { aviso(e.message, 'danger'); });
	}

	function marcarAtualizado() {
		var h = new Date();
		var pad = function(n) { return (n < 10 ? '0' : '') + n; };
		el('dia-atualizado').textContent = 'Atualizado às ' + pad(h.getHours()) + ':' + pad(h.getMinutes()) + ':' + pad(h.getSeconds());
	}

	// Atualiza sozinho os números do dia (arranchamento de outras pessoas não aparece na tela
	// até alguém trocar a data ou recarregar a página, a não ser por isto). Não atualiza se houver
	// alteração não salva, nem se o usuário estiver com o foco em algum campo do painel — para não
	// atrapalhar quem está digitando.
	function atualizarSozinho() {
		if (document.hidden || sujo || !E) { return; }
		var ativo = document.activeElement;
		if (ativo && raiz.contains(ativo) && ativo !== document.body) { return; }
		carregar(E.data);
	}

	function inputNum(cls, attrs, valor, titulo) {
		return '<input type="number" min="0" max="99999" step="1" class="form-control input-sm vi ' + cls + '" ' + attrs +
			' value="' + esc(valor) + '"' + (titulo ? ' title="' + esc(titulo) + '"' : '') + '>';
	}

	function desenhar() {
		var i, j;
		var cias = E.cias;
		var out = '<div id="dia-avisos"></div>';

		// ---- 1. Vale Diário (editável)
		out += '<h4>1. Vale Diário <small>— militares arranchados em ' + brData(E.data) + ', por Cia. Clique num número para corrigir; em amarelo = corrigido à mão.</small></h4>';
		out += '<div class="tabela-vale"><table class="table table-bordered table-condensed" id="tb-vd"><thead><tr><th rowspan="2">Cia</th>';
		REFS.forEach(function(r, k) { out += '<th colspan="3" class="cab-' + r[0] + '">' + r[1] + '</th>'; });
		out += '<th colspan="3" class="cab-qr">Etapas completas (maior valor)</th></tr><tr>';
		for (i = 0; i < 4; i++) { CATS.forEach(function(c) { out += '<th>' + c + '</th>'; }); }
		out += '</tr></thead><tbody>';
		cias.forEach(function(c) {
			out += '<tr><td style="text-align:left"><b>' + esc(c.nome) + '</b></td>';
			REFS.forEach(function(r) {
				for (var k = 0; k < 3; k++) {
					var ed = E.edit[c.id][r[0]][k];
					var auto = E.auto[c.id][r[0]][k];
					out += '<td>' + inputNum('vd' + (ed ? ' ed' : ''), 'data-cia="' + c.id + '" data-ref="' + r[0] + '" data-k="' + k + '" data-auto="' + auto + '"',
						E.matriz[c.id][r[0]][k], ed ? 'Corrigido à mão. Automático: ' + auto : 'Automático: ' + auto) + '</td>';
				}
			});
			for (j = 0; j < 3; j++) { out += '<td class="tot"><span data-cq="' + c.id + '.' + j + '">0</span></td>'; }
			out += '</tr>';
		});
		out += '<tr class="total-linha"><td style="text-align:left"><b>TOTAL</b></td>';
		REFS.forEach(function(r) { for (var k = 0; k < 3; k++) { out += '<td class="tot"><span data-vt="' + r[0] + '.' + k + '">0</span></td>'; } });
		for (j = 0; j < 3; j++) { out += '<td class="tot"><span data-vt="qr.' + j + '">0</span></td>'; }
		out += '</tr></tbody></table></div>';

		// ---- 2. Vale Total
		out += '<h4>2. Vale Total de Rações para o dia ' + brData(E.data) + '</h4>';
		out += '<div class="tabela-vale"><table class="table table-bordered table-condensed"><thead><tr><th>ETAPAS COMPLETAS</th>';
		cias.forEach(function(c) { out += '<th>' + esc(c.nome) + '</th>'; });
		out += '<th>Gu Sv</th><th>SOMA</th><th>Alimentados de outras OM</th><th>Tipo</th><th>Quant</th><th>C Hosp</th><th>C Esc</th><th>CF 60%</th></tr></thead><tbody>';
		[['of', 'Oficiais'], ['sgt', 'St e Sgt'], ['cb', 'Cb e Sd']].forEach(function(r) {
			out += '<tr><td style="text-align:left"><b>' + r[1] + '</b></td>';
			cias.forEach(function(c) { out += '<td><span data-v="qr.' + r[0] + '.cias.' + c.id + '">0</span></td>'; });
			out += '<td>' + inputNum('vp', 'data-p="gu_' + r[0] + '"', E.params['gu_' + r[0]]) + '</td>'
				+ '<td><span data-v="qr.' + r[0] + '.soma">0</span></td>'
				+ '<td>' + inputNum('vp', 'data-p="outras_' + r[0] + '"', E.params['outras_' + r[0]]) + '</td>'
				+ '<td>QR</td><td class="tot"><span data-v="qr.' + r[0] + '.total">0</span></td><td></td><td></td><td></td></tr>';
		});
		out += '<tr class="total-linha"><td style="text-align:left"><b>SOMA</b></td>';
		cias.forEach(function(c) { out += '<td><span data-v="qr.soma.cias.' + c.id + '">0</span></td>'; });
		out += '<td><span data-v="qr.soma.gu">0</span></td><td><span data-v="qr.soma.soma">0</span></td><td><span data-v="qr.soma.outras">0</span></td>'
			+ '<td>CF</td><td class="tot"><span data-v="qr.soma.total">0</span></td>'
			+ '<td>' + inputNum('vp', 'data-p="c_hosp"', E.params.c_hosp) + '</td>'
			+ '<td>' + inputNum('vp', 'data-p="c_esc"', E.params.c_esc) + '</td>'
			+ '<td>' + inputNum('vp', 'data-p="cf60"', E.params.cf60) + '</td></tr></tbody></table></div>';

		// Gu Sv padrão: fica logo abaixo do Gu Sv do dia, onde ele é usado.
		out += '<div class="well well-sm" id="gu-padrao" style="margin:-6px 0 14px">'
			+ '<b><i class="fa fa-user-shield"></i> Gu Sv padrão</b> '
			+ '<span class="text-muted">— preenche sozinho o Gu Sv dos dias que ainda não foram salvos (vale para todos os dias). '
			+ 'Deixe em branco para não usar: aí o sistema copia o Gu Sv do último dia salvo.</span>'
			+ '<div class="form-inline" style="margin-top:6px">'
			+ campoGuPadrao('of', 'Oficiais') + campoGuPadrao('sgt', 'St e Sgt') + campoGuPadrao('cb', 'Cb e Sd')
			+ '<span class="text-muted" style="font-size:12px">Para gravar, clique em "Salvar alterações".</span>'
			+ '</div></div>';

		out += '<div class="tabela-vale"><table class="table table-bordered table-condensed"><thead><tr><th>ETAPAS REDUZIDAS</th>';
		cias.forEach(function(c) { out += '<th>' + esc(c.nome) + '</th>'; });
		out += '<th>Gu Sv</th><th>SOMA</th><th>Alimentados de outras OM</th><th>Tipo</th><th>Quant</th></tr></thead><tbody>';
		REFS.forEach(function(r) {
			out += '<tr><td style="text-align:left"><b>' + r[1] + '</b></td>';
			cias.forEach(function(c) { out += '<td><span data-v="qs.' + r[0] + '.cias.' + c.id + '">0</span></td>'; });
			out += '<td><span data-v="qs.' + r[0] + '.gu">0</span></td><td><span data-v="qs.' + r[0] + '.soma">0</span></td>'
				+ '<td>' + inputNum('vp', 'data-p="outras_' + r[0] + '"', E.params['outras_' + r[0]]) + '</td>'
				+ '<td>QS</td><td class="tot"><span data-v="qs.' + r[0] + '.total">0</span></td></tr>';
		});
		out += '</tbody></table></div>';

		// ---- 3. Nota para Boletim
		out += '<h4>3. Nota para Boletim</h4>';
		out += '<div class="well well-sm nota">'
			+ '<p><b>' + esc(E.config.om_sigla) + '</b> — Nota para Boletim Nr <input type="text" maxlength="20" class="form-control input-sm vp-txt" style="display:inline-block;width:90px" data-p="nota_nr" value="' + esc(E.params.nota_nr) + '"> sobre arranchamento, para o dia ' + dataExt(E.data) + '.</p>'
			+ '<p>1. A Fiscalização Administrativa providencie o saque dos seguintes Quantitativos e complementos referentes às Etapas Completas: '
			+ '<b>Oficiais</b> QR <b data-v="qr.of.total">0</b>; <b>St/Sgt</b> QR <b data-v="qr.sgt.total">0</b>; <b>Cb/Sd</b> QR <b data-v="qr.cb.total">0</b>. '
			+ 'Complementos: CF <b data-v="qr.soma.total">0</b>; C Esc <b data-v="compl.esc">0</b>; C Hosp <b data-v="compl.hosp">0</b>; CF 60% <b data-v="compl.cf60">0</b>.</p>'
			+ '<p>2. O Serviço de Aprovisionamento confeccione as refeições correspondentes às seguintes Etapas Reduzidas (QS): '
			+ 'CAFÉ <b data-v="mapa.cafe">0</b>; ALMOÇO <b data-v="mapa.almoco">0</b>; JANTAR <b data-v="mapa.jantar">0</b>.</p>'
			+ '<p>3. Fiscal de Sobras e Resíduos: Adj Of Dia</p></div>';

		// ---- 4. Mapa de Gêneros
		out += '<h4>4. Mapa de Gêneros para o dia ' + brData(E.data) + '</h4>';
		out += '<p>a. Sobras de Alimentos do dia: <input type="text" maxlength="255" class="form-control input-sm vp-txt" style="display:inline-block;width:60%" data-p="sobras" value="' + esc(E.params.sobras) + '"></p>';
		out += '<div id="mapa-explica"></div>';
		out += '<div class="tabela-vale"><table class="table table-bordered table-condensed"><thead><tr><th>ARTIGOS</th><th>Quant. tabelar</th><th>Efetivo a alimentar</th><th>Quant. calculada</th><th>Cálculo</th><th>Quant. fornecida</th><th>Obs</th></tr></thead><tbody>';
		E.artigos.forEach(function(a, idx) {
			var f = (E.fornecido && E.fornecido[a.id]) || { fornecida: null, obs: '' };
			out += '<tr><td style="text-align:left">' + esc(a.nome) + '</td><td>' + a.tabelar.toFixed(3).replace('.', ',') + ' ' + esc(a.unidade) + '</td>'
				+ '<td><span data-v="mapa.itens.' + idx + '.efetivo">0</span></td>'
				+ '<td><span data-v="mapa.itens.' + idx + '.calculada" data-fmt="dec1">0</span></td>'
				+ '<td data-calc="' + idx + '" style="text-align:left;white-space:normal;min-width:240px;font-size:11px;color:#555"></td>'
				+ '<td><input type="text" inputmode="decimal" class="form-control input-sm vi vf" style="width:80px" data-f="' + a.id + '" value="' + (f.fornecida == null ? '' : String(f.fornecida).replace('.', ',')) + '"></td>'
				+ '<td><input type="text" maxlength="255" class="form-control input-sm vf-obs" data-o="' + a.id + '" value="' + esc(f.obs) + '"></td></tr>';
		});
		out += '</tbody></table></div>';

		// ---- 5. Dados da OM (sempre à vista)
		out += '<h4>5. Cabeçalho e assinatura dos documentos <small>— valem para todos os dias e saem nos PDFs</small></h4>'
			+ '<div class="well well-sm" id="cfg-om"><div class="row">'
			+ campoCfg('om_nome', 'Nome da OM') + campoCfg('om_sigla', 'Sigla da OM') + campoCfg('aprovisionador', 'Aprovisionador (nome e posto)') + campoCfg('local', 'Local')
			+ '</div></div>';

		el('dia-corpo').innerHTML = out;
		el('dia-data').value = E.data;
		estadoBarra();
		preencher(E.docs);
	}

	function campoCfg(chave, rotulo) {
		return '<div class="col-sm-6"><label>' + rotulo + '</label><input type="text" maxlength="120" class="form-control input-sm vcfg" data-cfg="' + chave + '" value="' + esc(E.config[chave]) + '"></div>';
	}

	function campoGuPadrao(cat, rotulo) {
		var chave = 'gu_padrao_' + cat;
		return '<div class="form-group" style="margin-right:14px"><label style="font-weight:normal">' + rotulo + '</label> '
			+ '<input type="number" min="0" max="99999" step="1" class="form-control input-sm vcfg" style="width:90px;text-align:center" data-cfg="' + chave + '" value="' + esc(E.config[chave]) + '" placeholder="vazio"></div>';
	}

	// ------------------------------------------------------------ avisos de conferência
	// Mostrados no topo do dia: Gu Sv zerado (some do café/almoço/jantar), de onde veio o Gu Sv,
	// e quem se arranchou mas fica fora das colunas de Cia (sem cadastro, sem P/G, sem Cia).

	function lerGuTela() {
		return ['of', 'sgt', 'cb'].map(function(c) {
			var i = raiz.querySelector('[data-p="gu_' + c + '"]');
			return i ? (parseInt(i.value, 10) || 0) : 0;
		});
	}

	function tabelaPendencias(lista, comMotivo) {
		var h = '<table class="table table-condensed table-bordered" style="background:#fff;margin:6px 0 4px;font-size:12px"><thead><tr>'
			+ '<th>CPF</th><th>Nome de guerra</th><th>P/G</th><th>Refeições</th>' + (comMotivo ? '<th>Motivo</th>' : '') + '</tr></thead><tbody>';
		lista.forEach(function(p) {
			h += '<tr><td>' + esc(p.cpf) + '</td><td>' + (p.nome ? esc(p.nome) : '<span class="text-muted">—</span>') + '</td>'
				+ '<td>' + (p.posto ? esc(p.posto) : '<span class="text-muted">—</span>') + '</td><td>' + esc(p.refeicoes) + '</td>'
				+ (comMotivo ? '<td>' + esc(p.motivo) + '</td>' : '') + '</tr>';
		});
		return h + '</tbody></table>';
	}

	function atualizarAvisos() {
		var caixa = el('dia-avisos');
		if (!caixa || !E) { return; }
		var alertas = [];
		var infos = [];

		var gu = lerGuTela();
		var origem = E.params.origem;
		if (gu[0] + gu[1] + gu[2] === 0) {
			alertas.push('<b>Gu Sv (guarnição de serviço) está zerado neste dia.</b> O Gu Sv entra somado no café, no almoço e no jantar '
				+ 'do Vale Total, da Nota e do Mapa de Gêneros. Se houver guarnição de serviço, preencha no quadro 2 e salve.'
				+ (origem !== 'padrao' ? ' Para não precisar digitar todo dia, configure um <a href="#gu-padrao" class="abrir-cfg">Gu Sv padrão</a>.' : ''));
		} else if (!sujo && origem === 'padrao') {
			infos.push('Gu Sv preenchido com o <b>padrão configurado</b> (' + gu.join(' / ') + '). Confira e salve.');
		} else if (!sujo && origem === 'herdado') {
			infos.push('Gu Sv <b>copiado do dia ' + brData(E.params.herdado_de) + '</b>, o último dia salvo (' + gu.join(' / ') + '). Confira e salve.');
		}

		var sc = E.semCadastro || [];
		if (sc.length) {
			alertas.push('<b>' + sc.length + (sc.length === 1 ? ' pessoa arranchada neste dia fica' : ' pessoas arranchadas neste dia ficam')
				+ ' fora da conta</b> (não entram no Vale Diário, no Vale Total nem no Mapa). '
				+ 'Se forem militares da OM, cadastre-os em "Gerenciar (Militares)" com P/G e Cia: a conta se corrige sozinha.'
				+ tabelaPendencias(sc, true));
		}
		var sci = E.semCia || [];
		if (sci.length) {
			infos.push('<b>' + sci.length + (sci.length === 1 ? ' militar sem Cia cadastrada' : ' militares sem Cia cadastrada')
				+ '</b>: ' + (sci.length === 1 ? 'conta' : 'contam') + ' na coluna "Sem Cia". Corrija o cadastro para entrarem na Cia certa.'
				+ tabelaPendencias(sci, false));
		}

		var h = '';
		if (alertas.length) {
			h += '<div class="alert alert-warning" style="margin-bottom:8px"><b><i class="fa fa-triangle-exclamation"></i> Conferir antes de gerar os documentos</b>'
				+ '<ul style="margin:6px 0 0;padding-left:20px">' + alertas.map(function(a) { return '<li style="margin-bottom:6px">' + a + '</li>'; }).join('') + '</ul></div>';
		}
		if (infos.length) {
			h += '<div class="alert alert-info" style="margin-bottom:8px"><ul style="margin:0;padding-left:20px">'
				+ infos.map(function(a) { return '<li style="margin-bottom:4px">' + a + '</li>'; }).join('') + '</ul></div>';
		}
		caixa.innerHTML = h;
		var link = caixa.querySelector('.abrir-cfg');
		if (link) {
			link.addEventListener('click', function(ev) {
				ev.preventDefault();
				var d = el('gu-padrao');
				d.scrollIntoView({ behavior: 'smooth', block: 'center' });
				var primeiro = d.querySelector('input');
				if (primeiro) { setTimeout(function() { primeiro.focus(); }, 400); }
			});
		}
	}

	// ------------------------------------------------------------ explicação do Mapa de Gêneros
	// As 3 regras são as das colunas "FÓRMULA" do Mapa de Gêneros da planilha de aprovisionamento.
	// A conta é feita no servidor (vale-lib.php); aqui só se mostra, com os números do dia, de onde ela vem.

	var REGRAS_MAPA = {
		cafe: { n: 1, formula: 'C' },
		cafe_leite: { n: 2, formula: 'C ÷ 2 + (A + J) ÷ 4' },
		refeicao: { n: 3, formula: '(A + J) ÷ 2' }
	};

	// Número com vírgula, até "casas" decimais e sem zeros sobrando (26,160 -> 26,16; 218,0 -> 218).
	function fmtNum(x, casas) {
		var s = (+x || 0).toFixed(casas);
		if (s.indexOf('.') >= 0) { s = s.replace(/0+$/, '').replace(/\.$/, ''); }
		return s.replace('.', ',');
	}

	// Conta do efetivo a alimentar de uma regra, com os números do dia. Ex.: "(432 + 5) ÷ 2 = 218,5 → 218".
	function contaEfetivo(base, m) {
		var c = m.cafe, a = m.almoco, j = m.jantar, bruto, txt;
		var final = m.bases[base];
		if (base === 'cafe') { return 'C = ' + c; }
		if (base === 'cafe_leite') {
			bruto = c / 2 + (a + j) / 4;
			txt = c + ' ÷ 2 + (' + a + ' + ' + j + ') ÷ 4 = ' + fmtNum(c / 2, 2) + ' + ' + fmtNum((a + j) / 4, 2) + ' = ' + fmtNum(bruto, 2);
		} else {
			bruto = (a + j) / 2;
			txt = '(' + a + ' + ' + j + ') ÷ 2 = ' + fmtNum(bruto, 2);
		}
		if (Math.abs(bruto - final) > 1e-9) { txt += ' → ' + final; }
		return txt;
	}

	function explicarMapa(docs) {
		var m = docs.mapa;
		var caixa = el('mapa-explica');
		if (!caixa || !m) { return; }

		var baseDe = {};
		var nomesPorRegra = { cafe: [], cafe_leite: [], refeicao: [] };
		E.artigos.forEach(function(a) {
			var b = REGRAS_MAPA[a.base] ? a.base : 'refeicao';
			baseDe[a.id] = b;
			nomesPorRegra[b].push(a.nome.toLowerCase());
		});
		var lista = function(b) { return nomesPorRegra[b].length ? ' (' + esc(nomesPorRegra[b].join(', ')) + ')' : ''; };

		caixa.innerHTML = '<details open class="well well-sm" style="margin-bottom:10px">'
			+ '<summary style="cursor:pointer"><b>Como o Mapa de Gêneros é calculado</b></summary>'
			+ '<p style="margin-top:8px">Ponto de partida: o total de refeições do dia, das <b>Etapas Reduzidas</b> do Vale Total (já com Gu Sv e alimentados de outras OM): '
			+ '<b>C</b> = café <b>' + m.cafe + '</b> · <b>A</b> = almoço <b>' + m.almoco + '</b> · <b>J</b> = jantar <b>' + m.jantar + '</b>.</p>'
			+ '<p>A <b>quantidade tabelar</b> é a porção de <b>um dia inteiro</b> de alimentação de uma pessoa. Como nem todos fazem todas as refeições, '
			+ 'a planilha converte as refeições servidas em "dias completos": esse é o <b>efetivo a alimentar</b>. Há 3 regras, conforme a refeição em que o gênero é usado:</p>'
			+ '<ol style="padding-left:20px">'
			+ '<li style="margin-bottom:6px"><b>Só no café da manhã</b>' + lista('cafe') + ': efetivo = <b>C</b>. '
			+ 'A porção inteira é consumida no café, então cada pessoa arranchada no café conta 1.<br>Hoje: <b>' + contaEfetivo('cafe', m) + '</b></li>'
			+ '<li style="margin-bottom:6px"><b>No café e também no almoço e no jantar</b>' + lista('cafe_leite') + ': efetivo = <b>C ÷ 2 + (A + J) ÷ 4</b>. '
			+ 'Metade da porção do dia vai no café; a outra metade se divide entre almoço e jantar (um quarto em cada).<br>Hoje: <b>' + contaEfetivo('cafe_leite', m) + '</b></li>'
			+ '<li style="margin-bottom:6px"><b>No almoço e no jantar</b>' + lista('refeicao') + ': efetivo = <b>(A + J) ÷ 2</b>. '
			+ 'A porção do dia cobre as duas refeições principais, então quem faz só uma delas consome metade. '
			+ 'Somar A + J conta as refeições servidas; dividir por 2 transforma em dias completos. '
			+ 'Exemplo: 2 pessoas que só almoçam = 2 refeições = 1 porção diária.<br>Hoje: <b>' + contaEfetivo('refeicao', m) + '</b></li>'
			+ '</ol>'
			+ '<p><b>Arredondamento:</b> o efetivo é sempre arredondado <b>para baixo</b> (sem casas decimais), como na planilha. '
			+ '<b>Quantidade calculada</b> = quantidade tabelar × efetivo, exibida com 1 casa decimal.</p>'
			+ '<p class="text-muted" style="margin-bottom:0">Essas são as regras das colunas "FÓRMULA" do Mapa de Gêneros da planilha de aprovisionamento. '
			+ 'Se a OM adotar outra regra, ela pode ser ajustada no sistema.</p>'
			+ '</details>';

		// Conta de cada linha: de onde vem o efetivo e a quantidade calculada.
		(m.itens || []).forEach(function(it, idx) {
			var td = raiz.querySelector('[data-calc="' + idx + '"]');
			if (!td) { return; }
			var b = baseDe[it.id] || 'refeicao';
			var bruta = it.tabelar * it.efetivo;
			var q = it.tabelar.toFixed(3).replace('.', ',') + ' × ' + it.efetivo + ' = ' + fmtNum(bruta, 3) + ' ' + esc(it.unidade);
			if (Math.abs(Math.round(bruta * 10) / 10 - bruta) > 1e-9) { q += ' → ' + dec1(it.calculada); }
			td.innerHTML = 'Efetivo (regra ' + REGRAS_MAPA[b].n + ' · ' + REGRAS_MAPA[b].formula + '): ' + contaEfetivo(b, m)
				+ '<br>Quantidade: ' + q;
		});
	}

	// Preenche todos os campos calculados (data-v="caminho.no.objeto") a partir dos documentos do servidor.
	function preencher(docs) {
		var nos = raiz.querySelectorAll('[data-v]');
		for (var i = 0; i < nos.length; i++) {
			var v = docs;
			var partes = nos[i].getAttribute('data-v').split('.');
			for (var k = 0; k < partes.length && v != null; k++) { v = v[partes[k]]; }
			nos[i].textContent = nos[i].getAttribute('data-fmt') === 'dec1' ? dec1(v) : (v == null ? '' : v);
		}
		explicarMapa(docs);
		atualizarAvisos();
		// Etapas completas por Cia e totais do Vale Diário
		var ids = E.cias.map(function(c) { return c.id; });
		var tot = { qr: [0, 0, 0] };
		REFS.forEach(function(r) { tot[r[0]] = [0, 0, 0]; });
		ids.forEach(function(id) {
			for (var k = 0; k < 3; k++) {
				var mx = 0;
				REFS.forEach(function(r) {
					var inp = raiz.querySelector('.vd[data-cia="' + id + '"][data-ref="' + r[0] + '"][data-k="' + k + '"]');
					var v = inp ? (parseInt(inp.value, 10) || 0) : 0;
					tot[r[0]][k] += v;
					if (v > mx) { mx = v; }
				});
				var sp = raiz.querySelector('[data-cq="' + id + '.' + k + '"]');
				if (sp) { sp.textContent = mx; }
				tot.qr[k] += mx;
			}
		});
		var vt = raiz.querySelectorAll('[data-vt]');
		for (var t = 0; t < vt.length; t++) {
			var p = vt[t].getAttribute('data-vt').split('.');
			vt[t].textContent = tot[p[0]][+p[1]];
		}
	}

	// ------------------------------------------------------------ montar o que será enviado

	function coletar(acao) {
		var matriz = {};
		raiz.querySelectorAll('.vd').forEach(function(inp) {
			var c = inp.getAttribute('data-cia'), r = inp.getAttribute('data-ref'), k = +inp.getAttribute('data-k');
			matriz[c] = matriz[c] || {};
			matriz[c][r] = matriz[c][r] || [0, 0, 0];
			matriz[c][r][k] = inp.value;
		});
		var params = {};
		raiz.querySelectorAll('[data-p]').forEach(function(inp) { params[inp.getAttribute('data-p')] = inp.value; });
		var fornecido = {};
		raiz.querySelectorAll('[data-f]').forEach(function(inp) {
			var id = inp.getAttribute('data-f');
			fornecido[id] = fornecido[id] || {};
			fornecido[id].fornecida = inp.value;
		});
		raiz.querySelectorAll('[data-o]').forEach(function(inp) {
			var id = inp.getAttribute('data-o');
			fornecido[id] = fornecido[id] || {};
			fornecido[id].obs = inp.value;
		});
		var config = {};
		raiz.querySelectorAll('[data-cfg]').forEach(function(inp) { config[inp.getAttribute('data-cfg')] = inp.value; });
		return { acao: acao, data: E.data, matriz: matriz, params: params, fornecido: fornecido, config: config };
	}

	function recalcular() {
		var minha = ++seq;
		chamar('POST', API, coletar('calcular')).then(function(j) {
			if (minha !== seq) { return; }
			aviso('');
			preencher(j.docs);
		}).catch(function(e) { aviso(e.message, 'danger'); });
	}

	function alterou(ev) {
		var t = ev.target;
		if (!t.matches || !t.matches('.vd, .vp, .vp-txt, .vf, .vf-obs, .vcfg')) { return; }
		sujo = true;
		estadoBarra();
		if (t.classList.contains('vd')) {
			var auto = t.getAttribute('data-auto');
			t.classList.toggle('ed', String(parseInt(t.value, 10) || 0) !== auto);
		}
		clearTimeout(timerCalc);
		timerCalc = setTimeout(recalcular, 300);
	}

	function salvar() {
		clearTimeout(timerCalc);
		el('btn-salvar').disabled = true;
		return chamar('POST', API, coletar('salvar')).then(function(j) {
			E = j;
			csrf = j.csrf;
			sujo = false;
			desenhar();
			aviso('Salvo em ' + j.salvo.em + '.', 'success');
			setTimeout(function() { if (!sujo) { aviso(''); } }, 4000);
			if (window.valeAtualizarMes) { window.valeAtualizarMes(); }
		}).catch(function(e) { aviso(e.message, 'danger'); }).then(function() {
			el('btn-salvar').disabled = false;
		});
	}

	function abrirPdf(doc) {
		var w = window.open('', '_blank');   // abre já, senão o navegador bloqueia o pop-up depois do salvar
		var ir = function() {
			var url = PDF + '?data=' + encodeURIComponent(E.data) + '&doc=' + doc;
			if (w) { w.location = url; } else { window.location = url; }
		};
		if (sujo) {
			salvar().then(function() { if (!sujo) { ir(); } else if (w) { w.close(); } });
		} else {
			ir();
		}
	}

	function restaurarAutomatico() {
		if (!window.confirm('Voltar todos os números do Vale Diário de ' + brData(E.data) + ' para o valor automático do arranchamento? (Gu Sv, complementos e o restante não mudam.)')) { return; }
		raiz.querySelectorAll('.vd').forEach(function(inp) {
			inp.value = inp.getAttribute('data-auto');
			inp.classList.remove('ed');
		});
		sujo = true;
		estadoBarra();
		recalcular();
	}

	function irPara(data) {
		if (sujo && !window.confirm('Há alterações não salvas neste dia. Sair sem salvar?')) {
			el('dia-data').value = E.data;
			return;
		}
		carregar(data);
	}

	// ------------------------------------------------------------ eventos

	raiz.addEventListener('input', alterou);
	raiz.addEventListener('change', alterou);
	el('btn-salvar').addEventListener('click', salvar);
	el('btn-restaurar').addEventListener('click', restaurarAutomatico);
	el('dia-ant').addEventListener('click', function() { irPara(somaDias(E.data, -1)); });
	el('dia-prox').addEventListener('click', function() { irPara(somaDias(E.data, 1)); });
	el('dia-hoje').addEventListener('click', function() { irPara(hojeISO()); });
	el('dia-data').addEventListener('change', function() { if (this.value) { irPara(this.value); } });
	el('btn-atualizar-dia').addEventListener('click', function() { if (E) { carregar(E.data); } });
	raiz.querySelectorAll('[data-pdf]').forEach(function(b) {
		b.addEventListener('click', function() { abrirPdf(b.getAttribute('data-pdf')); });
	});
	window.addEventListener('beforeunload', function(ev) {
		if (sujo) { ev.preventDefault(); ev.returnValue = ''; }
	});
	// Atualiza sozinho a cada 1 minuto, e também assim que a aba volta a ficar visível
	// (quem deixa a tela aberta o dia todo vê os arranchamentos de outras pessoas sem precisar
	// trocar a data nem recarregar a página).
	setInterval(atualizarSozinho, 60000);
	document.addEventListener('visibilitychange', atualizarSozinho);

	window.valeAbrirDia = function(data) { irPara(data); };
	carregar(hojeISO());
})();
