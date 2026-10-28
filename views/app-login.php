<?php
// Tela de entrada do app (acesso pelo endereço do app / APK). O site interno continua com views/index.php.
// Aba "Entrar": mesmos ids do formulário do site (#form-login, #identidade, #senha-app, #lembrar-app, #box-login);
// o envio é feito por assets/js/app.js.
// Aba "Primeiro acesso": o militar que ainda não tem senha cria a senha e já entra (controllers/autentica.php,
// com primeiro=1). Esqueceu a senha: o furriel zera e ele faz o primeiro acesso de novo.
?>
<div class="app-login">
	<div class="app-login-topo">
		<img src="assets/img/logo.png" alt="Brasão do 53º BIS">
		<h1>Arranchamento</h1>
		<p>53º Batalhão de Infantaria de Selva</p>
	</div>

	<div class="app-login-cartao">
		<div class="app-abas" role="tablist">
			<button type="button" role="tab" id="aba-entrar" class="ativa" aria-selected="true" aria-controls="painel-entrar">Entrar</button>
			<button type="button" role="tab" id="aba-primeiro" aria-selected="false" aria-controls="painel-primeiro">Primeiro acesso</button>
		</div>

		<!-- Entrar -->
		<div id="painel-entrar" role="tabpanel" aria-labelledby="aba-entrar">
			<form method="POST" id="form-login" autocomplete="on">
				<label for="identidade">CPF</label>
				<input type="text" name="identidade" id="identidade" class="maskIdentidade form-control" placeholder="000.000.000-00" inputmode="numeric" autocomplete="username" required>

				<label for="senha-app">Senha do app</label>
				<div class="app-senha">
					<input type="password" name="senha" id="senha-app" class="form-control" placeholder="6 números" inputmode="numeric" maxlength="6" autocomplete="current-password" required>
					<button type="button" class="app-ver-senha" data-alvo="senha-app" aria-label="Mostrar a senha"><i class="fa-regular fa-eye"></i></button>
				</div>

				<div class="checkbox checkbox-primary app-lembrar">
					<input type="checkbox" id="lembrar-app" checked>
					<label for="lembrar-app">Manter conectado neste aparelho</label>
				</div>

				<button type="submit" class="btn btn-block app-btn-entrar">Entrar <i class="fa-solid fa-arrow-right-to-bracket"></i></button>
				<div id="box-login"></div>
			</form>

			<div class="app-ajuda">
				<i class="fa-solid fa-circle-info"></i>
				<span><strong>Ainda não tem senha?</strong> Toque em "Primeiro acesso". <strong>Esqueceu a senha?</strong> Procure o furriel da sua Cia para zerar e faça o primeiro acesso novamente.</span>
			</div>
		</div>

		<!-- Primeiro acesso -->
		<div id="painel-primeiro" role="tabpanel" aria-labelledby="aba-primeiro" hidden>
			<form id="form-primeiro" autocomplete="off" novalidate>
				<label for="primeiro-cpf">CPF</label>
				<input type="text" id="primeiro-cpf" class="maskIdentidade form-control" placeholder="000.000.000-00" inputmode="numeric" required>

				<label for="primeiro-nova">Crie uma senha de 6 números</label>
				<div class="app-senha">
					<input type="password" id="primeiro-nova" class="form-control" placeholder="6 números" inputmode="numeric" maxlength="6" autocomplete="new-password" required>
					<button type="button" class="app-ver-senha" data-alvo="primeiro-nova" aria-label="Mostrar a senha"><i class="fa-regular fa-eye"></i></button>
				</div>

				<label for="primeiro-confirma">Repita a senha</label>
				<input type="password" id="primeiro-confirma" class="form-control app-senha-simples" placeholder="6 números" inputmode="numeric" maxlength="6" autocomplete="new-password" required>

				<div class="checkbox checkbox-primary app-lembrar">
					<input type="checkbox" id="primeiro-lembrar" checked>
					<label for="primeiro-lembrar">Manter conectado neste aparelho</label>
				</div>

				<button type="submit" class="btn btn-block app-btn-entrar" id="btn-primeiro">Criar senha e entrar <i class="fa-solid fa-key"></i></button>
				<div id="box-primeiro"></div>
			</form>

			<div class="app-ajuda">
				<i class="fa-solid fa-circle-info"></i>
				<span>Use somente se ainda não tem senha. Não são aceitas sequências (123456) nem números repetidos (000000). A senha é pessoal: não a informe a ninguém.</span>
			</div>
		</div>
	</div>

	<p class="app-login-rodape">Sistema de Arranchamento · 53º BIS</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
	// Abas Entrar / Primeiro acesso
	var abas = { entrar: document.getElementById('aba-entrar'), primeiro: document.getElementById('aba-primeiro') };
	var paineis = { entrar: document.getElementById('painel-entrar'), primeiro: document.getElementById('painel-primeiro') };
	var mostrar = function (qual) {
		Object.keys(abas).forEach(function (k) {
			var ativa = k === qual;
			abas[k].classList.toggle('ativa', ativa);
			abas[k].setAttribute('aria-selected', ativa ? 'true' : 'false');
			paineis[k].hidden = !ativa;
		});
	};
	abas.entrar.addEventListener('click', function () { mostrar('entrar'); });
	abas.primeiro.addEventListener('click', function () { mostrar('primeiro'); });

	// Mostrar / ocultar senha
	document.querySelectorAll('.app-ver-senha').forEach(function (b) {
		b.addEventListener('click', function () {
			var i = document.getElementById(b.getAttribute('data-alvo'));
			var ver = i.type === 'password';
			i.type = ver ? 'text' : 'password';
			b.innerHTML = ver ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
		});
	});

	// Primeiro acesso: cria a senha e entra
	var form = document.getElementById('form-primeiro');
	var caixa = document.getElementById('box-primeiro');
	var botao = document.getElementById('btn-primeiro');
	var erro = function (msg) { caixa.innerHTML = '<p class="alert alert-danger"></p>'; caixa.firstChild.textContent = msg; };
	form.addEventListener('submit', function (ev) {
		ev.preventDefault();
		var cpf = document.getElementById('primeiro-cpf').value.trim();
		var nova = document.getElementById('primeiro-nova').value;
		var confirma = document.getElementById('primeiro-confirma').value;
		if (!/^\d{3}\.\d{3}\.\d{3}-\d{2}$/.test(cpf)) { erro('Digite o CPF completo.'); return; }
		if (!/^\d{6}$/.test(nova)) { erro('A senha deve ter exatamente 6 números.'); return; }
		if (nova !== confirma) { erro('A confirmação não é igual à senha.'); return; }
		var dados = new URLSearchParams();
		dados.append('identidade', cpf);
		dados.append('primeiro', '1');
		dados.append('nova', nova);
		dados.append('confirma', confirma);
		dados.append('lembrar', document.getElementById('primeiro-lembrar').checked ? '1' : '');
		botao.disabled = true;
		caixa.innerHTML = '';
		fetch('controllers/autentica.php', { method: 'POST', body: dados, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (d.url) {
					try { sessionStorage.setItem('avisoPendente', JSON.stringify({ msg: 'Senha do app criada. Guarde-a: ela é pessoal.', tipo: 'sucesso', t: Date.now() })); } catch (e) {}
					window.location.href = d.url;
					return;
				}
				caixa.innerHTML = d.erro || '';
				if (!d.erro) { erro('Não foi possível concluir. Tente novamente.'); }
			})
			.catch(function () { erro('Sem conexão com o servidor. Verifique a internet e tente novamente.'); })
			.then(function () { botao.disabled = false; });
	});
});
</script>
