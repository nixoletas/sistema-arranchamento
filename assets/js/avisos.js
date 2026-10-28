// Avisos na própria tela (no lugar das janelas de alerta do navegador, que travam a página).
// Uso: avisar('Salvo!', 'sucesso' | 'erro' | 'aviso' | 'info').
// Se a página for recarregada ou trocada logo depois do aviso, ele aparece na página seguinte.
(function () {
	'use strict';
	var CHAVE = 'avisoPendente';
	var CORES = {
		sucesso: ['#dff0d8', '#3c763d', '#b2d8a3', 'fa-circle-check'],
		erro: ['#f2dede', '#a94442', '#e4b9b9', 'fa-circle-exclamation'],
		aviso: ['#fcf8e3', '#8a6d3b', '#ecd9a6', 'fa-triangle-exclamation'],
		info: ['#d9edf7', '#31708f', '#a9d4e8', 'fa-circle-info']
	};

	// Mensagens do servidor às vezes vêm com HTML: mostra só o texto.
	var soTexto = function (msg) {
		var d = document.createElement('div');
		d.innerHTML = msg == null ? '' : String(msg);
		return (d.textContent || '').trim();
	};

	var area = null;
	var pegarArea = function () {
		if (area && document.body.contains(area)) { return area; }
		area = document.createElement('div');
		area.setAttribute('aria-live', 'polite');
		area.style.cssText = 'position:fixed;top:12px;left:50%;transform:translateX(-50%);z-index:20000;' +
			'width:min(520px,calc(100% - 24px));display:flex;flex-direction:column;gap:8px;pointer-events:none;';
		document.body.appendChild(area);
		return area;
	};

	var mostrar = function (msg, tipo) {
		var c = CORES[tipo] || CORES.info;
		var el = document.createElement('div');
		el.setAttribute('role', tipo === 'erro' ? 'alert' : 'status');
		el.style.cssText = 'pointer-events:auto;display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border-radius:6px;' +
			'box-shadow:0 4px 14px rgba(0,0,0,.18);font-size:14px;line-height:1.4;cursor:pointer;' +
			'background:' + c[0] + ';color:' + c[1] + ';border:1px solid ' + c[2] + ';';
		var icone = document.createElement('i');
		icone.className = 'fa-solid ' + c[3];
		icone.style.marginTop = '3px';
		var texto = document.createElement('div');
		texto.style.flex = '1';
		texto.textContent = soTexto(msg);
		var fechar = document.createElement('span');
		fechar.setAttribute('aria-label', 'Fechar');
		fechar.innerHTML = '&times;';
		fechar.style.cssText = 'font-size:18px;line-height:1;opacity:.6;';
		el.appendChild(icone); el.appendChild(texto); el.appendChild(fechar);
		el.addEventListener('click', function () { el.remove(); });
		pegarArea().appendChild(el);
		setTimeout(function () { el.remove(); }, tipo === 'erro' || tipo === 'aviso' ? 9000 : 5000);
	};

	window.avisar = function (msg, tipo) {
		tipo = tipo || 'info';
		mostrar(msg, tipo);
		// Guarda por um instante: se a página for trocada em seguida, o aviso reaparece na próxima.
		try {
			sessionStorage.setItem(CHAVE, JSON.stringify({ msg: soTexto(msg), tipo: tipo, t: Date.now() }));
			setTimeout(function () { try { sessionStorage.removeItem(CHAVE); } catch (e) {} }, 1500);
		} catch (e) {}
	};

	var pendente = null;
	try { pendente = JSON.parse(sessionStorage.getItem(CHAVE) || 'null'); sessionStorage.removeItem(CHAVE); } catch (e) {}
	if (pendente && Date.now() - pendente.t < 15000) {
		var exibir = function () { mostrar(pendente.msg, pendente.tipo); };
		if (document.body) { exibir(); } else { document.addEventListener('DOMContentLoaded', exibir); }
	}
})();
