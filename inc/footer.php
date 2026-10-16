					<hr>
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