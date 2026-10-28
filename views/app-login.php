<?php
// Tela de entrada do app (acesso pelo endereço do app / APK). O site interno continua com views/index.php.
// Usa os mesmos ids do formulário do site (#form-login, #identidade, #senha-app, #lembrar-app, #box-login):
// o envio é feito por assets/js/app.js.
?>
<div class="app-login">
	<div class="app-login-topo">
		<img src="assets/img/logo.png" alt="Brasão do 53º BIS">
		<h1>Arranchamento</h1>
		<p>53º Batalhão de Infantaria de Selva</p>
	</div>

	<div class="app-login-cartao">
		<h2>Entrar</h2>
		<form method="POST" id="form-login" autocomplete="on">
			<label for="identidade">CPF</label>
			<input type="text" name="identidade" id="identidade" class="maskIdentidade form-control" placeholder="000.000.000-00" inputmode="numeric" autocomplete="username" required>

			<label for="senha-app">Senha do app</label>
			<div class="app-senha">
				<input type="password" name="senha" id="senha-app" class="form-control" placeholder="6 números" inputmode="numeric" maxlength="6" autocomplete="current-password" required>
				<button type="button" class="app-ver-senha" aria-label="Mostrar a senha" onclick="var i=document.getElementById('senha-app');var v=i.type==='password';i.type=v?'text':'password';this.innerHTML=v?'<i class=&quot;fa-regular fa-eye-slash&quot;></i>':'<i class=&quot;fa-regular fa-eye&quot;></i>';"><i class="fa-regular fa-eye"></i></button>
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
			<span><strong>Primeiro acesso?</strong> Crie a senha no sistema de arranchamento dentro da OM, no botão "Senha do app". Esqueceu? Peça ao furriel da sua Cia para zerar.</span>
		</div>
	</div>

	<p class="app-login-rodape">Sistema de Arranchamento · 53º BIS</p>
</div>
