<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Arranchamento</title>
	<link rel="icon" type="image/png" href="assets/img/logo_arranchamento.png">
	<!-- App instalável (ícone na tela inicial do celular) -->
	<link rel="manifest" href="/manifest.webmanifest">
	<meta name="theme-color" content="#2f5f2f">
	<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-title" content="Arranchamento">
	<!-- Selecione o fontawesome da web, pois o local pode não funcionar não está funcionando -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel='stylesheet' href='/assets/css/bootstrap.css'>
	<link rel="stylesheet" href="/assets/css/style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
	<link rel="stylesheet" href="/assets/bootstrap-datepicker/dist/css/bootstrap-datepicker.css">
	<?php if (function_exists('acesso_pelo_app') && acesso_pelo_app()) { ?>
	<link rel="stylesheet" href="/assets/css/app.css?v=<?php echo (int)@filemtime(__DIR__ . '/../assets/css/app.css'); ?>">
	<?php } ?>
</head>
<style>
    @import url('https://fonts.cdnfonts.com/css/rawline');

	body {
    font-family: 'Rawline', sans-serif;
}

h1, h2, h3, h4, h5, h6 {
    font-family: 'Rawline', sans-serif;
}
</style>
<body<?php echo (function_exists('acesso_pelo_app') && acesso_pelo_app()) ? ' class="modo-app"' : ''; ?>>
