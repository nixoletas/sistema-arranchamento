<!DOCTYPE html>
<html>
<head>
	<title>Adm | Login</title>
	<meta charset="utf-8">
	<link rel="icon" type="image/png" href="../assets/img/logo_arranchamento.png">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel='stylesheet' href='../assets/css/bootstrap.css'>
	<link rel="stylesheet" href="../assets/css/style-rancho.css">
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
<body>
	<div class="jumbotron">
		<div class="container">
			<img style="width:30%;padding-bottom:30px;" src="../assets/img/logo.png" alt="Logo" class="center" />
			<h3>Administração</h3>
			<form method="POST" id="form_login">
				<div class="box">
					<input class="form-control" type="text" name="usuario" id="usuario" placeholder="Usuário">
					<input class="form-control" type="password" name="senha" id="senha" placeholder="Senha">
					<button type="submit" class="btn btn-primary btn-block">Acesso <span class="fa fa-lock"></span></button>
				</div>
				<div class="text-center" id="box-login"></div>
			</form>
		</div>
	</div>
</body>
<script src='../assets/js/jquery.js'></script>
<script src='../assets/dataTables/datatables.js'></script>
<script src='../assets/js/bootstrap.js'></script>
<script src='../assets/js/jquery.validate-1.15.0.js'></script>
<script src='../assets/bootstrap-datepicker/dist/js/bootstrap-datepicker.js'></script>
<script src='../assets/js/mask.js'></script>
<script src='../assets/select2-4.0.3/dist/js/select2.full.js'></script>
<script src='../assets/bootstrap-select-1.12.4/dist/js/bootstrap-select.js'></script>
<script src='../assets/js/app-rancho.js'></script>
</html>
