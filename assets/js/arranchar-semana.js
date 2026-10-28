// "Arranchar por semana" (tela do militar): uma tabela com os próximos dias (linhas) e as refeições
// (colunas). O militar marca e desmarca à vontade e grava tudo de uma vez em "Salvar".
// Quem decide o que pode é o servidor (controllers/arranchar-semana.php); aqui só se mostra e se marca.
(function () {
	'use strict';

	var API = 'controllers/arranchar-semana.php';
	var REFEICOES = [{ tp: 1, nome: 'Café' }, { tp: 2, nome: 'Almoço' }, { tp: 3, nome: 'Jantar' }];

	var caixa = document.getElementById('semana-tabela');
	if (!caixa) { return; }
	var barra = document.getElementById('semana-barra');
	var resumo = document.getElementById('semana-pendentes');
	var btnSalvar = document.getElementById('semana-salvar');
	var btnDesfazer = document.getElementById('semana-desfazer');

	var dias = [];
	var marcado = {};   // "aaaa-mm-dd|tp" -> true/false (o que está na tela)
	var original = {};  // o que veio do servidor

	var chave = function (data, tp) { return data + '|' + tp; };
	var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; };
	var avisarTela = function (msg, tipo) {
		if (window.avisar) { window.avisar(msg, tipo); } else { window.alert(msg); }
	};

	var editavel = function (dia, tp) {
		var r = dia.refeicoes[tp];
		if (original[chave(dia.data, tp)]) { return r.pode_cancelar; }  // já arranchado: só se ainda der para cancelar
		return !r.bloqueio;                                              // não arranchado: só se não estiver bloqueado
	};

	var pendentes = function () {
		var lista = [];
		Object.keys(marcado).forEach(function (k) {
			if (marcado[k] !== original[k]) {
				var p = k.split('|');
				lista.push({ data: p[0], tipo: +p[1], arranchar: marcado[k] });
			}
		});
		return lista;
	};

	var atualizarBarra = function () {
		var p = pendentes();
		var mais = p.filter(function (x) { return x.arranchar; }).length;
		var menos = p.length - mais;
		if (!p.length) { barra.style.display = 'none'; return; }
		var partes = [];
		if (mais) { partes.push(mais + (mais === 1 ? ' refeição para arranchar' : ' refeições para arranchar')); }
		if (menos) { partes.push(menos + (menos === 1 ? ' para desarranchar' : ' para desarranchar')); }
		resumo.textContent = partes.join(' e ') + ' — ainda não salvo.';
		barra.style.display = '';
	};

	var formatarSemana = function (segunda) {
		var ini = new Date(segunda + 'T12:00:00');
		var fim = new Date(ini); fim.setDate(fim.getDate() + 6);
		var f = function (d) { return ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2); };
		return 'Semana de ' + f(ini) + ' a ' + f(fim);
	};

	var desenhar = function () {
		var semanas = [];
		dias.forEach(function (dia) {
			var s = semanas[semanas.length - 1];
			if (!s || s.segunda !== dia.segunda) { s = { segunda: dia.segunda, dias: [] }; semanas.push(s); }
			s.dias.push(dia);
		});

		var html = '<table class="table table-condensed tabela-semana"><thead><tr><th>Dia</th>';
		REFEICOES.forEach(function (r) { html += '<th class="text-center">' + r.nome + '</th>'; });
		html += '</tr></thead>';
		semanas.forEach(function (s) {
			html += '<tbody><tr class="linha-semana"><td colspan="4"><strong>' + formatarSemana(s.segunda) + '</strong>' +
				'<span class="acoes-semana"><button type="button" class="btn btn-link btn-xs" data-semana="' + s.segunda + '" data-marcar="1">' +
				'<i class="fa-regular fa-square-check"></i> Marcar todas</button>' +
				'<button type="button" class="btn btn-link btn-xs" data-semana="' + s.segunda + '" data-marcar="0">Desmarcar</button></span></td></tr>';
			s.dias.forEach(function (dia) {
				var fimDeSemana = /^(Sáb|Dom)/.test(dia.dia);
				html += '<tr' + (fimDeSemana ? ' class="fim-semana"' : '') + '><td class="dia"><strong>' + esc(dia.dia) + '</strong>' +
					'<small class="prazo">cancelar até ' + esc(dia.prazo_dia) + ' ' + esc(dia.prazo_cancelar) + '</small></td>';
				REFEICOES.forEach(function (r) {
					var k = chave(dia.data, r.tp);
					var info = dia.refeicoes[r.tp];
					var pode = editavel(dia, r.tp);
					var mudou = marcado[k] !== original[k];
					var titulo = '';
					var classe = 'celula-ref';
					if (!pode && original[k]) { titulo = 'Arranchado — o prazo para cancelar já encerrou'; classe += ' travada'; }
					else if (!pode && info.bloqueio) { titulo = 'Dia bloqueado: ' + info.bloqueio; classe += ' bloqueada'; }
					if (mudou) { classe += ' mudou'; }
					html += '<td class="' + classe + '" title="' + esc(titulo) + '"><label>' +
						'<input type="checkbox" data-k="' + k + '"' + (marcado[k] ? ' checked' : '') + (pode ? '' : ' disabled') +
						' aria-label="' + esc(r.nome + ' ' + dia.dia) + '">' +
						(!pode && info.bloqueio && !original[k] ? '<i class="fa fa-lock" aria-hidden="true"></i>' : '') +
						'</label></td>';
				});
				html += '</tr>';
			});
			html += '</tbody>';
		});
		html += '</table>';
		caixa.innerHTML = html;
		atualizarBarra();
	};

	var carregar = function () {
		caixa.innerHTML = '<p class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Carregando...</p>';
		return fetch(API, { credentials: 'same-origin', cache: 'no-store' })
			.then(function (r) {
				if (r.status === 401) { window.location.href = 'index.php'; throw new Error('sessão'); }
				return r.json();
			})
			.then(function (d) {
				if (d.error) { caixa.innerHTML = '<div class="alert alert-danger">' + esc(d.error) + '</div>'; return; }
				dias = d.dias || [];
				marcado = {}; original = {};
				dias.forEach(function (dia) {
					REFEICOES.forEach(function (r) {
						var k = chave(dia.data, r.tp);
						original[k] = marcado[k] = !!dia.refeicoes[r.tp].arranchado;
					});
				});
				versaoAtual = d.versao || null;
				desenhar();
			})
			.catch(function (e) {
				if (e.message !== 'sessão') {
					caixa.innerHTML = '<div class="alert alert-danger">Não foi possível carregar os dias. Atualize a página.</div>';
				}
			});
	};

	// ---------- Sincronização entre aparelhos ----------
	// O mesmo militar pode estar com o app no celular e o site no computador, e o furriel também pode
	// arranchá-lo pelo painel. A cada 15 s (e quando a tela volta para a frente) pergunta ao servidor se algo
	// mudou. Mudou e não há marcação pendente: recarrega a página (tabela, calendários, lista e resumo).
	// Há marcação pendente: atualiza só o que o militar não mexeu e mantém as marcações dele.
	var versaoAtual = null;
	var salvando = false;
	var conferindo = false;

	var mesclar = function () {
		return fetch(API, { credentials: 'same-origin', cache: 'no-store' })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (d.error) { return; }
				dias = d.dias || [];
				dias.forEach(function (dia) {
					REFEICOES.forEach(function (r) {
						var k = chave(dia.data, r.tp);
						var novo = !!dia.refeicoes[r.tp].arranchado;
						if (!(k in original) || marcado[k] === original[k]) { marcado[k] = novo; }
						original[k] = novo;
					});
				});
				versaoAtual = d.versao || null;
				desenhar();
				avisarTela('Seus arranchamentos mudaram em outro aparelho ou pelo furriel. A tabela foi atualizada e suas marcações ainda não salvas foram mantidas.', 'aviso');
			});
	};

	var conferirMudancas = function () {
		if (salvando || conferindo || document.hidden || !versaoAtual) { return; }
		conferindo = true;
		fetch(API + '?acao=versao', { credentials: 'same-origin', cache: 'no-store' })
			.then(function (r) {
				if (r.status === 401) { window.location.reload(); throw new Error('sessão'); }
				return r.json();
			})
			.then(function (d) {
				if (!d.versao || d.versao === versaoAtual || salvando) { return; }
				if (!pendentes().length && !document.querySelector('.modal.in')) {
					avisarTela('Seus arranchamentos mudaram em outro aparelho ou pelo furriel. A tela foi atualizada.', 'info');
					window.location.reload();
					return;
				}
				return mesclar();
			})
			.catch(function () {})
			.then(function () { conferindo = false; });
	};
	setInterval(conferirMudancas, 15000);
	document.addEventListener('visibilitychange', function () { if (!document.hidden) { conferirMudancas(); } });
	window.addEventListener('focus', conferirMudancas);

	caixa.addEventListener('change', function (ev) {
		var k = ev.target.getAttribute('data-k');
		if (!k) { return; }
		marcado[k] = ev.target.checked;
		var td = ev.target.closest('td');
		if (td) { td.classList.toggle('mudou', marcado[k] !== original[k]); }
		atualizarBarra();
	});

	caixa.addEventListener('click', function (ev) {
		var b = ev.target.closest('button[data-semana]');
		if (!b) { return; }
		var semana = b.getAttribute('data-semana');
		var valor = b.getAttribute('data-marcar') === '1';
		dias.forEach(function (dia) {
			if (dia.segunda !== semana) { return; }
			REFEICOES.forEach(function (r) {
				if (editavel(dia, r.tp)) { marcado[chave(dia.data, r.tp)] = valor; }
			});
		});
		desenhar();
	});

	btnDesfazer.addEventListener('click', function () {
		Object.keys(original).forEach(function (k) { marcado[k] = original[k]; });
		desenhar();
	});

	btnSalvar.addEventListener('click', function () {
		var p = pendentes();
		if (!p.length) { return; }
		var menos = p.filter(function (x) { return !x.arranchar; }).length;
		if (menos && !window.confirm('Você vai desarranchar ' + menos + (menos === 1 ? ' refeição' : ' refeições') + '. Confirmar?')) { return; }

		btnSalvar.disabled = true;
		salvando = true;
		btnSalvar.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Salvando...';
		var corpo = new URLSearchParams();
		corpo.append('acao', 'salvar');
		corpo.append('mudancas', JSON.stringify(p));
		fetch(API, { method: 'POST', body: corpo, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) {
				if (r.status === 401) { window.location.href = 'index.php'; throw new Error('sessão'); }
				return r.json();
			})
			.then(function (d) {
				if (d.error) { avisarTela(d.error, 'erro'); return; }
				var partes = [];
				if (d.arranchados) { partes.push(d.arranchados + (d.arranchados === 1 ? ' refeição arranchada' : ' refeições arranchadas')); }
				if (d.desarranchados) { partes.push(d.desarranchados + (d.desarranchados === 1 ? ' desarranchada' : ' desarranchadas')); }
				var msg = partes.length ? 'Salvo: ' + partes.join(' e ') + '.' : 'Nada foi alterado.';
				if (d.erros && d.erros.length) {
					avisarTela(msg + ' Não foi possível: ' + d.erros.join(' '), 'aviso');
				} else {
					avisarTela(msg, 'sucesso');
				}
				// Recarrega a página para os calendários, a lista e o resumo da semana mostrarem o novo estado.
				window.location.reload();
			})
			.catch(function (e) {
				if (e.message !== 'sessão') {
					avisarTela('Não foi possível confirmar se salvou. A página vai mostrar o estado atual.', 'erro');
					window.location.reload();
				}
			})
			.then(function () {
				btnSalvar.disabled = false;
				salvando = false;
				btnSalvar.innerHTML = '<i class="fa-regular fa-floppy-disk"></i> Salvar';
			});
	});

	// Não deixa sair da página com alterações não salvas sem avisar.
	window.addEventListener('beforeunload', function (ev) {
		if (pendentes().length && btnSalvar && !btnSalvar.disabled) { ev.preventDefault(); ev.returnValue = ''; }
	});

	carregar();
})();
