// App do arranchamento (tela do militar): senha do app, botão "Instalar app" e service worker.
(function () {
	'use strict';

	// Service worker: só existe em HTTPS (endereço do app); no site interno (http) o navegador ignora.
	if ('serviceWorker' in navigator && window.isSecureContext) {
		navigator.serviceWorker.register('/sw.js').catch(function () {});
	}

	var avisarTela = function (msg, tipo) { if (window.avisar) { window.avisar(msg, tipo); } else { window.alert(msg); } };

	// ---------- Instalar app ----------
	var btnInstalar = document.getElementById('btn-instalar-app');
	var dicaIos = document.getElementById('dica-ios');
	var instalado = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
	var pedido = null;
	window.addEventListener('beforeinstallprompt', function (ev) {
		ev.preventDefault();
		pedido = ev;
		if (btnInstalar && !instalado) { btnInstalar.style.display = ''; }
	});
	if (btnInstalar) {
		btnInstalar.addEventListener('click', function () {
			if (!pedido) { return; }
			pedido.prompt();
			pedido.userChoice.then(function () { pedido = null; btnInstalar.style.display = 'none'; });
		});
	}
	// iPhone não tem o botão automático: mostra como adicionar à tela de início.
	if (dicaIos && !instalado && window.isSecureContext && /iphone|ipad|ipod/i.test(navigator.userAgent)) {
		dicaIos.style.display = '';
	}

	// ---------- Senha do app ----------
	var status = document.getElementById('app-status');
	var btnSenha = document.getElementById('btn-senha-app');
	if (!status || !btnSenha) { return; }
	var viaApp = false;

	var carregar = function () {
		fetch('controllers/senha-app.php', { credentials: 'same-origin', cache: 'no-store' })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (d.error) { return; }
				viaApp = !!d.pelo_app;
				document.getElementById('grupo-senha-atual').style.display = viaApp ? '' : 'none';
				if (d.tem_senha) {
					status.innerHTML = 'senha criada em ' + d.criada_em + '.';
					btnSenha.innerHTML = '<i class="fa-solid fa-key"></i> Trocar senha do app';
				} else {
					status.innerHTML = '<strong>você ainda não tem senha.</strong> Crie uma para entrar pelo app fora da OM.';
					btnSenha.innerHTML = '<i class="fa-solid fa-key"></i> Criar senha do app';
				}
			})
			.catch(function () {});
	};

	document.getElementById('form-senha-app').addEventListener('submit', function (ev) {
		ev.preventDefault();
		var corpo = new URLSearchParams();
		['atual', 'nova', 'confirma'].forEach(function (c) { corpo.append(c, document.getElementById('senha-app-' + c).value); });
		var botao = document.getElementById('salvar-senha-app');
		botao.disabled = true;
		fetch('controllers/senha-app.php', { method: 'POST', body: corpo, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (d.error) { document.getElementById('senha-app-erro').textContent = d.error; return; }
				avisarTela(d.msg, 'sucesso');
				$('#modalSenhaApp').modal('hide');
				carregar();
			})
			.catch(function () { document.getElementById('senha-app-erro').textContent = 'Não foi possível salvar. Tente de novo.'; })
			.then(function () { botao.disabled = false; });
	});
	$('#modalSenhaApp').on('show.bs.modal', function () {
		document.getElementById('form-senha-app').reset();
		document.getElementById('senha-app-erro').textContent = '';
	});

	carregar();
})();
