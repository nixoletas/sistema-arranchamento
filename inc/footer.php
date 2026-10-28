					<hr>
					<?php if (!(function_exists('acesso_pelo_app') && acesso_pelo_app())) { ?>
					<!-- Atalho para a página de download/compartilhamento do app (só no site interno; no app não precisa). -->
					<a href="/app/" target="_blank" rel="noopener" class="botao-baixar-app" title="Baixar e divulgar o aplicativo de arranchamento">
						<i class="fa-solid fa-mobile-screen-button"></i> Baixar o aplicativo
					</a>
					<style>
						.botao-baixar-app { position: fixed; right: 16px; bottom: 16px; z-index: 1500; background: #2f5f2f; color: #fff;
							padding: 10px 16px; border-radius: 24px; font-weight: 600; box-shadow: 0 4px 14px rgba(0,0,0,.25); }
						.botao-baixar-app:hover, .botao-baixar-app:focus { color: #fff; background: #234723; text-decoration: none; }
						@media (max-width: 767px) { .botao-baixar-app { right: 12px; bottom: 12px; padding: 9px 14px; font-size: 14px; } }
					</style>
					<?php } ?>
					<div class='modal fade' id='avisos'>
						<div class='modal-dialog modal-lg'>
							<div class='modal-content'>
								<div class="modal-header">
									<h4 class="modal-title">Avisos <i class="fa fa-bullhorn"></i></h4>
								</div>
								<div class='modal-body'></div>
								<div class='modal-footer'>
									<button type="button" class="btn btn-default" data-dismiss="modal">Fechar</button>
								</div>
							</div>
						</div>
					</div>
					</body>
					<script src='/assets/js/jquery.js'></script>
					<script src='/assets/js/bootstrap.js'></script>
					<script src='/assets/js/avisos.js?v=<?php echo (int)@filemtime(__DIR__ . '/../assets/js/avisos.js'); ?>'></script>
					<script src='/assets/js/jquery.validate-1.15.0.js'></script>
					<script src='/assets/js/mask.js'></script>
					<script src='/assets/bootstrap-datepicker/dist/js/bootstrap-datepicker.js'></script>
					<script src='/assets/bootstrap-datepicker/dist/locales/bootstrap-datepicker.pt-BR.min.js'></script>
					<script src='/assets/js/app.js?v=<?php echo (int)@filemtime(__DIR__ . '/../assets/js/app.js'); ?>'></script>
					<?php
					if (isset($_GET['refeicao'])) {
						switch ($_GET['refeicao']) {
							case 'cafe':
					?>
								<script>
									$("#addCafeManha").collapse('show');
								</script>
							<?php
								break;
							case 'almoco':
							?>
								<script>
									$("#addAlmoco").collapse("show");
								</script>
							<?php
								break;
							case 'jantar':
							?>
								<script>
									$("#addJanta").collapse("show");
								</script>
					<?php
								break;
						}
					}
					?>

					</html>