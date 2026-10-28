<?php
error_reporting(0);
require_once("../controllers/config/conexao.php");
session_start();

if (!empty($_SESSION['usuario']['id']) && is_numeric($_SESSION['usuario']['id'])) {

	$usuario = $_SESSION['usuario']['id'];

	$con = $cnt->prepare('SELECT u.*,
		c.id AS companhia_id,
		c.nome AS companhia_nome,
		c.descricao AS companhia_desc
		FROM usuarios u
		JOIN cad_cia c ON c.id = u.om
		WHERE u.id = :usuario
		ORDER BY u.id DESC
		LIMIT 1');
	$con->bindValue(':usuario', $usuario, PDO::PARAM_STR);
	$con->execute();
	$total = $con->rowCount();
	if ($total) {
		$usuario_logado = $con->fetch(PDO::FETCH_ASSOC);
	}
} else {
	header("location: index.php");
	exit;
}

?>
<!DOCTYPE html>
<html>

<head>
	<title>Adm | Dashboard</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="icon" type="image/png" href="assets/img/logo_arranchamento.png">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel='stylesheet' href='../assets/css/bootstrap.css'>
	<link rel="stylesheet" href="../assets/css/style.css">
	<link rel="stylesheet" href="../assets/bootstrap-datepicker/dist/css/bootstrap-datepicker.css">
	<link rel="stylesheet" href="../assets/select2-4.0.3/dist/css/select2.css">
	<link rel="stylesheet" href="../assets/bootstrap-select-1.12.4/dist/css/bootstrap-select.css">
	<link rel="stylesheet" href="../assets/dataTables/datatables.css">

</head>
<style>
    @import url('https://fonts.cdnfonts.com/css/rawline');

	body {
    font-family: 'Rawline', sans-serif;
}

h1, h2, h3, h4, h5, h6 {
    font-family: 'Rawline', sans-serif;
}

	/* Cartões do painel: mesma altura e sem buracos na grade */
	.cards-admin { display: flex; flex-wrap: wrap; }
	.cards-admin:before, .cards-admin:after { display: none; }
	.cards-admin > [class*="col-"] { display: flex; margin-bottom: 20px; }
	.cards-admin .panel { width: 100%; margin-bottom: 0; display: flex; flex-direction: column; }
	.cards-admin .panel-heading { flex: 1 1 auto; min-height: 118px; }
	.cards-admin .panel > a { display: block; }
	.caixa-usuario { margin-left: 15px; margin-right: 15px; }
	.caixa-usuario p { margin-bottom: 6px; }

	/* Celular: dois cartões por linha, todos com a mesma largura, ícone menor ao lado do nome. */
	@media (max-width: 767px) {
		.cards-admin > [class*="col-"] { width: 50%; padding-left: 7px; padding-right: 7px; margin-bottom: 14px; }
		.cards-admin { margin-left: 8px; margin-right: 8px; }
		.cards-admin .panel-heading { min-height: 96px; padding: 10px; }
		.cards-admin .panel-heading .row { display: flex; align-items: center; margin: 0; }
		.cards-admin .panel-heading .row > [class*="col-"] { width: auto; padding: 0; }
		.cards-admin .panel-heading .row > .text-right { flex: 1 1 auto; padding-left: 8px; }
		.cards-admin .fa-5x { font-size: 36px; }
		.cards-admin .announcement-heading { margin: 0; font-size: 13px; line-height: 1.3; }
		.cards-admin .announcement-text { margin: 0; font-size: 13px; line-height: 1.3; }
		.cards-admin .panel-footer { padding: 8px 10px; }
		h2.text-center { font-size: 24px; margin-top: 10px; }
		/* Tabelas largas dentro das janelas rolam para o lado, sem empurrar a janela para fora da tela. */
		.modal-body .dataTables_wrapper, .modal-body .tabela-rolavel { overflow-x: auto; -webkit-overflow-scrolling: touch; }
		.modal-body .dataTables_wrapper .dataTables_filter input { width: 160px; }
	}

	/* Caixas de marcar nos campos de seleção múltipla.
	   bootstrap-select (Relatórios: Cia e P/G; Arranchar: Militares): troca o "✓" pequeno à direita por uma caixa à esquerda. */
	.bootstrap-select.show-tick .dropdown-menu li a { position: relative; padding-left: 34px; }
	.bootstrap-select.show-tick .dropdown-menu li a::before {
		content: ""; position: absolute; left: 10px; top: 50%; margin-top: -8px;
		width: 16px; height: 16px; border: 1px solid #8a8a8a; border-radius: 3px; background: #fff;
	}
	.bootstrap-select.show-tick .dropdown-menu li.selected a::before { background: #337ab7; border-color: #337ab7; }
	.bootstrap-select.show-tick .dropdown-menu li.selected a::after {
		content: ""; position: absolute; left: 15px; top: 50%; margin-top: -6px;
		width: 6px; height: 10px; border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg);
	}
	.bootstrap-select.show-tick .dropdown-menu li a span.check-mark { display: none !important; }
	.bootstrap-select.show-tick .dropdown-menu li a span.text { margin-right: 0; }

	/* select2 (Bloquear dias: Cia e P/G): mesma caixa em cada opção da lista. */
	.select2-caixas .select2-results__option[aria-selected] { position: relative; padding-left: 32px; }
	.select2-caixas .select2-results__option[aria-selected]::before {
		content: ""; position: absolute; left: 8px; top: 50%; margin-top: -8px;
		width: 16px; height: 16px; border: 1px solid #8a8a8a; border-radius: 3px; background: #fff;
	}
	.select2-caixas .select2-results__option[aria-selected=true] { background-color: #eef4fb; color: #333; }
	.select2-caixas .select2-results__option[aria-selected=true]::before { background: #337ab7; border-color: #337ab7; }
	.select2-caixas .select2-results__option[aria-selected=true]::after {
		content: ""; position: absolute; left: 13px; top: 50%; margin-top: -6px;
		width: 6px; height: 10px; border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg);
	}
	.select2-caixas .select2-results__option--highlighted[aria-selected] { background-color: #d6e6f7; color: #333; }
	.acoes-multipla { font-size: 12px; margin-top: 4px; }
	/* select2 sem nada marcado: o texto de ajuda ("Selecione as Cias") ocupa a largura toda, sem ficar cortado. */
	.select2-selection--multiple .select2-selection__rendered > .select2-search--inline:only-child { width: 100%; }
	.select2-selection--multiple .select2-search--inline:only-child .select2-search__field { width: 100% !important; }

	/* "Marcar todos / Desmarcar todos" do bootstrap-select: dois botões lado a lado que cabem na largura da lista. */
	.bootstrap-select .dropdown-menu { min-width: 100%; }
	.bootstrap-select .bs-actionsbox { padding: 6px 8px; margin-bottom: 4px; border-bottom: 1px solid #e5e5e5; }
	.bootstrap-select .bs-actionsbox .btn-group { display: flex; width: 100%; }
	.bootstrap-select .bs-actionsbox .btn-group button {
		flex: 1 1 0; width: auto; float: none; min-width: 0; margin: 0;
		padding: 4px 6px; font-size: 12px; line-height: 1.4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
		color: #337ab7; background: #f5f8fc; border: 1px solid #c9d8ea;
	}
	.bootstrap-select .bs-actionsbox .btn-group button + button { margin-left: 6px; }
	.bootstrap-select .bs-actionsbox .btn-group button,
	.bootstrap-select .bs-actionsbox .btn-group button:first-child,
	.bootstrap-select .bs-actionsbox .btn-group button:last-child { border-radius: 3px; }
	.bootstrap-select .bs-actionsbox .btn-group button:hover,
	.bootstrap-select .bs-actionsbox .btn-group button:focus { background: #e3edf8; border-color: #9fbbdc; color: #23527c; outline: none; }

	/* Filtros das tabelas de Gerenciar: cabem nas colunas estreitas sem cortar o "Filtrar". */
	thead th.pesquisa .form-control { padding: 4px 3px; font-size: 12px; min-width: 0; }
	.tbl td:nth-child(4) { white-space: nowrap; } /* CPF numa linha só */

	/* Arranchar: resultados da busca de militar */
	.busca-militar-caixa { position: relative; }
	.busca-resultados { display: none; position: absolute; left: 15px; right: 15px; z-index: 30; max-height: 240px; overflow-y: auto;
		margin: 2px 0 0; box-shadow: 0 6px 16px rgba(0,0,0,.15); }
	.busca-resultados .list-group-item { padding: 7px 10px; }

	/* Relatórios */
	.form-relatorio .form-group { margin-bottom: 12px; }
	.form-relatorio .checkbox { padding-top: 7px; }
	.form-relatorio .bootstrap-select.disabled > .btn { background: #f2f2f2; }
</style>

<body>
	<div class="container">
		<div class="row">
			<h2 class="text-center" style="padding-bottom: 10px;"> ADMINISTRAÇÃO </h2>
			<div class="well well-sm caixa-usuario">
				<p><b>Usuário:</b> <?php print_r($usuario_logado['usuario']); ?></p>
				<p><b>Cia:</b> <?php print_r($usuario_logado['companhia_desc']); ?></p>
				<p><b>Perfil:</b> <?php if ($usuario_logado['tipo'] == 1) {
										echo "Administrador";
									} else echo "Operador"; ?></p>
				<a class="btn btn-default btn-sm" href="logout.php" title="Sair">Sair <i class="fa fa-sign-out"></i></a>
				<a class="btn btn-success btn-sm" href="/app/" target="_blank" rel="noopener" title="Página para baixar e divulgar o aplicativo aos militares"><i class="fa-solid fa-mobile-screen-button"></i> Aplicativo do militar: baixar e divulgar</a>
			</div>
		</div>
		<hr>
		<div class="row cards-admin">

			<div class="col-lg-3 col-md-4 col-sm-6">
				<div class="panel panel-warning">
					<div class="panel-heading">
						<div class="row">
							<div class="col-xs-6">
								<i class="fa fa-bar-chart fa-5x"></i>
							</div>
							<div class="col-xs-6 text-right">
								<p class="announcement-heading">&nbsp;</p>
								<p class="announcement-text">Relatórios</p>
							</div>
						</div>
					</div>
					<a href="#" data-remote="false" data-toggle="modal" data-target="#gerar-relatorio">
						<div class="panel-footer announcement-bottom">
							<div class="row">
								<div class="col-xs-6">
									Acessar
								</div>
								<div class="col-xs-6 text-right">
									<i class="fa fa-arrow-circle-right"></i>
								</div>
							</div>
						</div>
					</a>
				</div>
			</div>

			<?php if ($usuario_logado['tipo'] == 1) { ?>
				<div class="col-lg-3 col-md-4 col-sm-6">
					<div class="panel panel-info">
						<div class="panel-heading">
							<div class="row">
								<div class="col-xs-6">
									<i class="fa fa-utensils fa-5x"></i>
								</div>
								<div class="col-xs-6 text-right">
									<p class="announcement-heading">&nbsp;</p>
									<p class="announcement-text">Vale de Rações</p>
								</div>
							</div>
						</div>
						<a href="vale.php">
							<div class="panel-footer announcement-bottom">
								<div class="row">
									<div class="col-xs-6">
										Acessar
									</div>
									<div class="col-xs-6 text-right">
										<i class="fa fa-arrow-circle-right"></i>
									</div>
								</div>
							</div>
						</a>
					</div>
				</div>
			<?php  } ?>

			<?php if ($usuario_logado['tipo'] == 1) { ?>
				<div class="col-lg-3 col-md-4 col-sm-6">
					<div class="panel panel-danger">
						<div class="panel-heading">
							<div class="row">
								<div class="col-xs-6">
									<i class="fa fa fa-bullhorn fa-5x"></i>
								</div>
								<div class="col-xs-6 text-right">
									<p class="announcement-heading">&nbsp;</p>
									<p class="announcement-text">Avisos</p>
								</div>
							</div>
						</div>
						<a href="#" id="btn-aviso" data-toggle="modal" data-target="#modalAvisos">
							<div class="panel-footer announcement-bottom">
								<div class="row">
									<div class="col-xs-6">
										Acessar
									</div>
									<div class="col-xs-6 text-right">
										<i class="fa fa-arrow-circle-right"></i>
									</div>
								</div>
							</div>
						</a>
					</div>
				</div>
			<?php  } ?>
			<?php if ($usuario_logado['tipo'] == 1) { ?>
				<div class="col-lg-3 col-md-4 col-sm-6">
					<div class="panel panel-success">
						<div class="panel-heading">
							<div class="row">
								<div class="col-xs-6">
									<i class="fa fa-cogs fa-5x"></i>
								</div>
								<div class="col-xs-6 text-right">
									<p class="announcement-heading">&nbsp;</p>
									<p class="announcement-text">Bloquear dias</p>
								</div>
							</div>
						</div>
						<a href="#" data-toggle="modal" data-target="#modalConfig">
							<div class="panel-footer announcement-bottom">
								<div class="row">
									<div class="col-xs-6">
										Acessar
									</div>
									<div class="col-xs-6 text-right">
										<i class="fa fa-arrow-circle-right"></i>
									</div>
								</div>
							</div>
						</a>
					</div>
				</div>
			<?php  } ?>
<!-- militares de Outra OM (desativado; se reativar, ponha o card dentro de <div class="col-lg-3 col-md-4 col-sm-6"> e de um if de tipo 1)
					<div class="panel panel-default">
						<div class="panel-heading">
							<div class="row">
								<div class="col-xs-6">
									<i class="fa fa-cutlery fa-5x"></i>
								</div>
								<div class="col-xs-6 text-right">
									<p class="announcement-heading">Arranchar</p>
									<p class="announcement-text">(Outra OM)</p>
								</div>
							</div>
						</div>
						<a href="#" data-remote="false" data-toggle="modal" data-target="#modalArrancharOutraOm">
							<div class="panel-footer announcement-bottom">
								<div class="row">
									<div class="col-xs-6">
										Acessar
									</div>
									<div class="col-xs-6 text-right">
										<i class="fa fa-arrow-circle-right"></i>
									</div>
								</div>
							</div>
						</a>
					</div>
miltares de Outra OM ------------------------------------ -->

			<div class="col-lg-3 col-md-4 col-sm-6">
				<div class="panel panel-default">
					<div class="panel-heading">
						<div class="row">
							<div class="col-xs-6">
								<i class="fa fa-cutlery fa-5x"></i>
							</div>
							<div class="col-xs-6 text-right">
								<p class="announcement-heading">Arranchar</p>
								<p class="announcement-text">(Militares)</p>
							</div>
						</div>
					</div>
					<a href="#" data-remote="false" data-toggle="modal" data-target="#modalArranchar">
						<div class="panel-footer announcement-bottom">
							<div class="row">
								<div class="col-xs-6">
									Acessar
								</div>
								<div class="col-xs-6 text-right">
									<i class="fa fa-arrow-circle-right"></i>
								</div>
							</div>
						</div>
					</a>
				</div>
			</div>


			<div class="col-lg-3 col-md-4 col-sm-6">
				<div class="panel panel-danger">
					<div class="panel-heading">
						<div class="row">
							<div class="col-xs-6">
								<i class="fa fa-users fa-5x"></i>
							</div>
							<div class="col-xs-6 text-right">
								<p class="announcement-heading">Gerenciar</p>
								<p class="announcement-text">(Militares)</p>
							</div>
						</div>
					</div>
					<a href="#" data-remote="false" data-toggle="modal" data-target="#modalGerenciarMilitares" id="GerenciarMilitares">
						<div class="panel-footer announcement-bottom">
							<div class="row">
								<div class="col-xs-6">
									Acessar
								</div>
								<div class="col-xs-6 text-right">
									<i class="fa fa-arrow-circle-right"></i>
								</div>
							</div>
						</div>
					</a>
				</div>
			</div>

			<?php if ($usuario_logado['tipo'] == 1) { ?>
				<div class="col-lg-3 col-md-4 col-sm-6">
					<div class="panel panel-danger">
						<div class="panel-heading">
							<div class="row">
								<div class="col-xs-6">
									<i class="fa fa-users fa-5x"></i>
								</div>
								<div class="col-xs-6 text-right">
									<p class="announcement-heading">Gerenciar</p>
									<p class="announcement-text">(Usuários do Sistema)</p>
								</div>
							</div>
						</div>
						<a href="#" data-remote="false" data-toggle="modal" data-target="#modalGerenciarUsuario" id="GerenciarUsuarios">
							<div class="panel-footer announcement-bottom">
								<div class="row">
									<div class="col-xs-6">
										Acessar
									</div>
									<div class="col-xs-6 text-right">
										<i class="fa fa-arrow-circle-right"></i>
									</div>
								</div>
							</div>
						</a>
					</div>
				</div>
			<?php  } ?>

			<?php if ($usuario_logado['tipo'] == 1) { ?>
				<div class="col-lg-3 col-md-4 col-sm-6">
					<div class="panel panel-warning">
						<div class="panel-heading">
							<div class="row">
								<div class="col-xs-6">
									<i class="fa fa-university fa-5x"></i>
								</div>
								<div class="col-xs-6 text-right">
									<p class="announcement-heading">Gerenciar</p>
									<p class="announcement-text">(Organização)</p>
								</div>
							</div>
						</div>
						<a href="#" data-remote="false" data-toggle="modal" data-target="#modalGerenciarOrg" id="GerenciarOrg">
							<div class="panel-footer announcement-bottom">
								<div class="row">
									<div class="col-xs-6">
										Acessar
									</div>
									<div class="col-xs-6 text-right">
										<i class="fa fa-arrow-circle-right"></i>
									</div>
								</div>
							</div>
						</a>
					</div>
				</div>
			<?php  } ?>

			<?php if ($usuario_logado['tipo'] == 1 || $usuario_logado['tipo'] == 2) { ?>
				<div class="col-lg-3 col-md-4 col-sm-6">
					<div class="panel panel-primary">
						<div class="panel-heading">
							<div class="row">
								<div class="col-xs-6">
									<i class="fa fa-unlock-alt fa-5x"></i>
								</div>
								<div class="col-xs-6 text-right">
									<p class="announcement-heading">Alterar Senha</p>
									<p class="announcement-text"></p>
								</div>
							</div>
						</div>
						<a href="#" data-remote="false" data-toggle="modal" data-target="#modalAlterarSenha">
							<div class="panel-footer announcement-bottom">
								<div class="row">
									<div class="col-xs-6">
										Acessar
									</div>
									<div class="col-xs-6 text-right">
										<i class="fa fa-arrow-circle-right"></i>
									</div>
								</div>
							</div>
						</a>
					</div>
				</div>
	
			<?php  } ?>
		</div>
	</div>
	<?php require_once "views/modals.php"; ?>
</body>
<script src='../assets/js/jquery.js'></script>
<script src='../assets/js/bootstrap.js'></script>
<script src='../assets/js/avisos.js?v=<?php echo (int)@filemtime(__DIR__ . '/../assets/js/avisos.js'); ?>'></script>
<script src='../assets/js/mask.js'></script>
<script src='../assets/js/jquery.validate-1.15.0.js'></script>
<script src='../assets/bootstrap-datepicker/dist/js/bootstrap-datepicker.js'></script>
<script src='../assets/bootstrap-datepicker/dist/locales/bootstrap-datepicker.pt-BR.min.js'></script>
<script src='../assets/bootstrap-select-1.12.4/dist/js/bootstrap-select.js'></script>
<script src='../assets/bootstrap-select-1.12.4/dist/js/i18n/defaults-pt_BR.min.js'></script>
<script src='../assets/select2-4.0.3/dist/js/select2.full.js'></script>
<script src='../assets/ckeditor/ckeditor.js'></script>
<script src='../assets/dataTables/datatables.js'></script>
<script src='../assets/ckeditor/config.js'></script>
<script src='../assets/js/app-rancho.js?v=<?php echo (int)@filemtime(__DIR__ . '/../assets/js/app-rancho.js'); ?>'></script>
<script src='../assets/js/jquery.validate-1.15.0.js'></script>

<?php

if (isset($_GET['status']) &&  $_GET['status'] == "success") { ?>
	<script>
		$(function() {
			$('#modalConfig').modal('show');
		});
	</script>
<?php
}
?>

</html>
