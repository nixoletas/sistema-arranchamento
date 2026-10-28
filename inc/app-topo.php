<?php
// Barra do topo do app (no lugar da coluna lateral do site). Abre as mesmas 3 divs que inc/skeleton.php
// (container > row > coluna), que o rodapé fecha.
$app_pg   = htmlspecialchars($usuario_logado['posto_desc'] ?? '');
$app_nome = htmlspecialchars($usuario_logado['nome_guerra'] ?? '');
$app_cia  = htmlspecialchars($usuario_logado['companhia_desc'] ?? '');
?>
<header class="app-barra">
	<img src="assets/img/logo.png" alt="">
	<div class="app-barra-texto">
		<strong><?php echo trim($app_pg . ' ' . $app_nome); ?></strong>
		<span><?php echo $app_cia; ?> · Arranchamento</span>
	</div>
	<a href="logout.php" class="app-sair" aria-label="Sair"><i class="fa-solid fa-right-from-bracket"></i> Sair</a>
</header>
	<div class="container app-conteudo">
		<div class="row">
			<div class="col-xs-12">
