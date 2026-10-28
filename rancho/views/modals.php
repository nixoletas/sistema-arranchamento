<?php if($usuario_logado['tipo'] == 1){ ?>
	<!-- MODAL CONFIGURAÇÃO -->
	<div class="modal fade" id="modalConfig" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="myModalLabel">Bloquear dias <i class="fa fa-gear"></i></h4>
				</div>
				<form method="POST" class="form-horizontal" id="form-bloqueios">
					<div class="modal-body">
						<div class="form-group">
							<label class="col-sm-3 control-label">Bloquear Dias: </label>
							<div class="col-sm-7">
								<div class="input-daterange input-group date">
									<input type="text" id="bloq_dias" class="form-control"><span class="input-group-addon"><i class="fa fa-calendar"></i></span>
								</div>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">Bloquear Cia: </label>
							<div class="col-sm-7">
								<select style="width: 100%;" class="form-control bloqOm" multiple="multiple">
									<?php
									$con = $cnt->prepare("SELECT * FROM cad_cia");
									$con->execute();
									while ($result = $con->fetch(PDO::FETCH_ASSOC)){ 
										?>
										<option <?php echo "value='".$result['id']."'"; ?>><?php echo $result['nome']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">Bloquear P/G: </label>
							<div class="col-sm-7">
								<select style="width: 100%;" class="form-control bloqPg" multiple="multiple">
									<?php
									$con = $cnt->prepare("SELECT id, nome FROM cad_posto ORDER BY ordem ASC");
									$con->execute();
									while ($result = $con->fetch(PDO::FETCH_ASSOC)){ 
										?>
										<option <?php echo "value='".$result['id']."'"; ?>><?php echo $result['nome']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">Bloquear Refeição: </label>
							<div class="col-sm-7">
								<div class="checkbox checkbox-primary">
									<input id="checkbox-cafe" type="checkbox" value="1" class="refeicao">
									<label for="checkbox-cafe">
										<b>Café da Manhã</b>
									</label>
								</div>

								<div class="checkbox checkbox-primary">
									<input id="checkbox-almoco" type="checkbox" value="2" class="refeicao">
									<label for="checkbox-almoco">
										<b>Almoço</b>
									</label>
								</div>

								<div class="checkbox checkbox-primary">
									<input id="checkbox-jantar" type="checkbox" value="3" class="refeicao">
									<label for="checkbox-jantar">
										<b>Jantar</b>
									</label>
								</div>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">Motivo do Bloqueio: </label>
							<div class="col-sm-7">
								<textarea rows="3" cols="50" id='motivo_bloq' onkeyup="countChar(this)" class="form-control" required></textarea>
								<div style='padding-top:4px;font-weight:bold;' class="charNum"></div>
							</div>
						</div>
						<center><button type="button" id="btnRegra" class="btn btn-success active">Criar Regra <span class="fa-regular fa-square-check"></span></button></center>
					</form>
					<hr>
					<div style="display:block;padding-bottom:5px;" class="msg-status text-center"></div>
					<div class="tabela-rolavel">
					<table style="padding-top:10px;text-align:center;" width="100%" cellspacing="0" id="tbl-regras" class="table text-center table-compact table-condensed table-hover tb">
						<thead>
							<tr class="alert alert-info"> 
								<th style="text-align:center;">Dias Bloqueados</th>
								<th style="text-align:center;">Cia bloqueada</th>
								<th style="text-align:center;">P/G Bloqueados</th>
								<th style="text-align:center;">Refeições Bloqueadas</th>
								<th style="text-align:center;">Motivo Bloqueio</th>
								<th style="text-align:center;">Ação</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$con = $cnt->prepare("SELECT config.bloq_mot, config.id, REPLACE( REPLACE( REPLACE( config.bloq_ref, '3', 'Jantar' ) , '2', 'Almoço' ) , '1', 'Café' ) AS bloq_ref, config.bloq_dias, group_concat( DISTINCT om.nome ) AS 'nome_om', GROUP_CONCAT( DISTINCT posto.nome ) AS 'nome_posto'
								FROM config AS config
								LEFT JOIN cad_posto AS posto ON ( FIND_IN_SET( posto.id, config.bloq_pg ) )
								LEFT JOIN cad_cia AS om ON ( FIND_IN_SET( om.id, config.bloq_cia ) )
								GROUP BY config.id;");
							$con->execute();
							while($res = $con->fetch(PDO::FETCH_ASSOC)){
								?>
								<tr>
									<td style="max-width: 260px;"><?php echo $res['bloq_dias']; ?></td>
									<td><?php echo $res['nome_om']; ?></td>
									<td><?php echo $res['nome_posto']; ?></td>
									<td><?php echo $res['bloq_ref']; ?></td>
									<td style="max-width: 240px;"><?php echo $res['bloq_mot']; ?></td>
									<td>
										<a onclick="return confirm('Esta operação não poderá ser desfeita, deseja continuar?')" href="#" title="Exluir Regra" class="delRegra" id="<?php echo $res['id']; ?>">
											<span class="text-danger fa fa-times"></span>
										</a>
									</td>
								</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>
					</div>
				</div>
			</div>
		</div>
		<!-- FIM MODAL CONFIGURAÇÃO -->
		<?php } ?>

		<div class="modal fade" tabindex="-1" role="dialog" id="gerar-relatorio">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
						<h4 class="modal-title">Relatórios <i class="fa fa-bar-chart"></i> </h4>
					</div>
					<div class="modal-body">
						<?php if($usuario_logado['tipo'] == 1){ ?>
						<!-- <form class="form-horizontal" method="POST">
								<div class="form-group">
									<h5 class="text-center text-success"><b>Período</b></h5>
									<label class="col-sm-2 control-label"></label>
									<div class="col-sm-8">
										<div class="input-daterange input-group dt-relatorio">
											<span class="input-group-addon"><i class="fa fa-calendar"></i></span>
											<input type="text" class="input-sm form-control" id="data-inicio" />
											<span class="input-group-addon">Até</span>
											<input type="text" class="input-sm form-control" id="data-fim" /><span class="input-group-addon"><i class="fa fa-calendar"></i></span>
										</div>
									</div>
								</div>
								<center><button type="button" id="btnGerar-prd" class="btn btn-success">Gerar <i class="fa fa-download"></i> </button></center>
							</form>
							<hr>
						-->
						<?php } ?>
						
						<form class="form-horizontal form-relatorio" method="POST" onsubmit="return false;">
							<h5 class="text-center text-success"><b>Arranchados</b></h5>
							<div class="form-group">
								<label class="col-sm-3 control-label" for="data_ard">Data:</label>
								<div class="col-sm-5">
									<div class="input-group">
										<input type="text" id="data_ard" class="form-control dt-relatorio" autocomplete="off"><span class="input-group-addon"><i class="fa fa-calendar"></i></span>
									</div>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label" for="om_ard">Cia:</label>
								<div class="col-sm-7">
									<select data-width="100%" data-size="8" data-selected-text-format="count > 2" data-count-selected-text="{0} Cias selecionadas" data-actions-box="true" data-select-all-text="Marcar todas" data-deselect-all-text="Desmarcar todas" title="Selecione a Cia" multiple class="selectpicker form-control" name="om_ard" id="om_ard">
										<?php
										if($usuario_logado['tipo'] == 1){
											$con = $cnt->prepare("SELECT * FROM cad_cia WHERE id != 100");
										}else {
											$con = $cnt->prepare("SELECT * FROM cad_cia WHERE id = ".$usuario_logado['om']);
										}
										$con->execute();
										while ($result = $con->fetch(PDO::FETCH_ASSOC)){ 
											?>
											<option <?php echo "value='".$result['id']."'"; ?>><?php echo $result['nome']; ?></option>
											<?php } ?>
									</select>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label" for="pg_ard">P/G:</label>
								<div class="col-sm-7">
									<select data-width="100%" data-size="8" data-selected-text-format="count > 3" data-count-selected-text="{0} P/G selecionados" data-actions-box="true" data-select-all-text="Marcar todos" data-deselect-all-text="Desmarcar todos" title="Selecione o P/G" multiple class="selectpicker form-control" name="pg_ard" id="pg_ard">
										<?php
										$con = $cnt->prepare("SELECT id, nome FROM cad_posto ORDER BY ordem ASC");
										$con->execute();
										while ($result = $con->fetch(PDO::FETCH_ASSOC)){ 
											?>
											<option <?php echo "value='".$result['id']."'"; ?>><?php echo $result['nome']; ?></option>
											<?php } ?>
									</select>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">Ordem:</label>
								<div class="col-sm-7">
									<div class="radio radio-primary" style="margin-top:7px;">
										<input type="radio" name="ordem_ard" id="ordem-nome" class="ordem-rel" value="nome" checked>
										<label for="ordem-nome">P/G e nome de guerra</label>
									</div>
									<div class="radio radio-primary">
										<input type="radio" name="ordem_ard" id="ordem-antiguidade" class="ordem-rel" value="antiguidade">
										<label for="ordem-antiguidade">P/G e, entre os SD, antiguidade (ano de incorporação)</label>
									</div>
								</div>
							</div>
							<?php if($usuario_logado['tipo'] == 1){ ?>
								<div class="form-group">
									<label class="col-sm-3 control-label">Opção:</label>
									<div class="col-sm-7">
										<div class="checkbox checkbox-primary">
											<input type="checkbox" id="outrasOm" name="outrasOm" value="true">
											<label for="outrasOm">Outras OMs</label>
											<span class="help-block" style="margin:2px 0 0;font-size:12px;">Lista os militares de outras OM arranchados na data. Não usa Cia nem P/G.</span>
										</div>
									</div>
								</div>
							<?php } ?>
							<div id="rel-aviso" class="alert alert-warning" style="display:none;margin:0 0 15px;padding:8px 12px;"></div>
							<div class="text-center">
								<button type="button" id="btnGerar-ard" class="btn btn-success">Gerar <i class="fa fa-download"></i></button>
							</div>
						</form>
					</div>
				</div>
				</div>
			</div>
		</div>


		<?php if($usuario_logado['tipo'] == 1){ ?>
			<div class="modal fade" tabindex="-1" role="dialog" id="modalAvisos">
				<div class="modal-dialog" role="document">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
							<h4 class="modal-title">Avisos <i class="fa fa-bullhorn"></i> </h4>
						</div>
						<div class="modal-body">
							<form class="form-horizontal" method="POST">
								<div class="form-group">
									<div class="col-sm-12">
										<textarea rows="3" cols="50" class="form-control ckeditor" id="avisos" name="avisos"></textarea>
									</div>
								</div>
								<div class="modal-footer">
									<button type="button" id="btnSalvar-aviso" class="btn btn-success">Salvar <i class="fa-regular fa-floppy-disk"></i> </button>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
			<?php } ?>

			<?php if($usuario_logado['tipo'] == 1){ ?>
				<div class="modal fade" tabindex="-1" role="dialog" id="modalArrancharOutraOm">
					<div class="modal-dialog" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
								<h4 class="modal-title">Arranchar - Militar de Outra OM <i class="fa fa-cutlery"></i> </h4>
							</div>
							<div class="modal-body">
								<form class="form-horizontal form" method="POST" >
									<div class="form-group">
										<label class="col-sm-3 control-label">Cia: </label>
										<div class="col-sm-7">
											<input type="text" name="om" id="om" class="form-control" required>
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-3 control-label">P/G: </label>
										<div class="col-sm-7">
											<select style="width: 100%;" class="form-control" id="posto" name="posto">
												<?php
												$con = $cnt->prepare("SELECT id, nome FROM cad_posto WHERE ordem BETWEEN 2 AND 17 ORDER BY ordem ASC");
												$con->execute();
												while ($result = $con->fetch(PDO::FETCH_ASSOC)){ 
													?>
													<option <?php echo "value='".$result['id']."'"; ?>><?php echo $result['nome']; ?></option>
													<?php } ?>
												</select>
											</div>
										</div>
										<div class="form-group">
											<label class="col-sm-3 control-label">Nº CPF: </label>
											<div class="col-sm-7">
												<input type="text" name="identidade" id="identidade" class="form-control maskIdentidade" required>
											</div>
										</div>
										<div class="form-group">
											<label class="col-sm-3 control-label">Nome: </label>
											<div class="col-sm-7">
												<input type="text" name="nome" id="nome" class="form-control" required>
											</div>
										</div>
										<div class="form-group">
											<label class="col-sm-3 control-label">Data: </label>
											<div class="col-sm-7">
												<div class="input-date-rancho">
													<input type="hidden" id="data" class="form-control"></span>
												</div>
											</div>
										</div>
										<div class="form-group">
											<label class="col-sm-3 control-label">Refeições: </label>
											<div class="col-sm-7">
												<div class="checkbox checkbox-primary">
													<input id="checkbox-cafe-outraom" class="refeicao-arranchar-outraom" value="1" type="checkbox">
													<label for="checkbox-cafe-outraom">
														<b>Café da Manhã</b>
													</label>
												</div>
												<div class="checkbox checkbox-primary">
													<input id="checkbox-almoco-outraom" class="refeicao-arranchar-outraom" value="2" type="checkbox">
													<label for="checkbox-almoco-outraom">
														<b>Almoço</b>
													</label>
												</div>
												<div class="checkbox checkbox-primary">
													<input id="checkbox-jantar-outraom" class="refeicao-arranchar-outraom" value="3" type="checkbox">
													<label for="checkbox-jantar-outraom">
														<b>Jantar</b>
													</label>
												</div>
											</div>
										</div>
										<div class="modal-footer">
											<button type="button" id="btnSave-OutraOm" class="btn btn-success">Salvar <i class="fa-regular fa-floppy-disk"></i> </button>
										</div>
									</form>
								</div>
							</div>
						</div>
					</div>
					<?php } ?>

					<div class="modal fade" tabindex="-1" role="dialog" id="modalArranchar">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
									<h4 class="modal-title">Arranchar <i class="fa fa-cutlery"></i> </h4>
								</div>
								<div class="modal-body">
									<form class="form-horizontal" method="POST" id="form-arranchar">
										<div class="form-group">
											<label class="col-sm-3 control-label" for="busca-militar">Buscar: </label>
											<div class="col-sm-7 busca-militar-caixa">
												<input type="search" id="busca-militar" class="form-control" placeholder="Nome de guerra, nome ou CPF" autocomplete="off">
												<div id="busca-militar-resultados" class="list-group busca-resultados"></div>
												<span class="help-block" style="font-size:12px;margin-bottom:0;">Toque no militar para incluir. Ou escolha por Cia e P/G abaixo.</span>
											</div>
										</div>
										<div class="form-group">
											<label class="col-sm-3 control-label">Cia: </label>
											<div class="col-sm-7">
												<select style="width: 100%;" class="form-control" name="om_arranchar" id="om_arranchar">
													<option selected>- Selecione -</option>
													<?php
													if($usuario_logado['tipo'] == 1){
														$con = $cnt->prepare("SELECT * FROM cad_cia WHERE id < 100 ");
													}else {
														$con = $cnt->prepare("SELECT * FROM cad_cia WHERE id = ".$usuario_logado['om']);
													}
													$con->execute();
													while ($result = $con->fetch(PDO::FETCH_ASSOC)){ 
														?>
														<option <?php echo "value='".$result['id']."'"; ?>><?php echo $result['nome']; ?></option>
														<?php } ?>
													</select>
												</div>
											</div>
											<div class="form-group">
												<label class="col-sm-3 control-label">P/G: </label>
												<div class="col-sm-7">
													<select style="width: 100%;" class="form-control" name="pg_arranchar" id="pg_arranchar">
														<option selected>- Selecione -</option>
														<?php
														if($usuario_logado['tipo'] == 1){
															$con = $cnt->prepare("SELECT id, nome FROM cad_posto ORDER BY ordem ASC");
														}else {
															$con = $cnt->prepare("SELECT id, nome FROM cad_posto WHERE ordem BETWEEN 5 AND 17 ORDER BY ordem ASC");
														}
														$con->execute();
														while ($result = $con->fetch(PDO::FETCH_ASSOC)){ 
															?>
															<option <?php echo "value='".$result['id']."'"; ?>><?php echo $result['nome']; ?></option>
															<?php } ?>
														</select>
													</div>
												</div>
												<div class="form-group">
													<label class="col-sm-3 control-label">Militares: </label>
													<div class="col-sm-7">
														<select class="selectpicker form-control militares" multiple data-live-search="true" name="militares" id="militares" data-selected-text-format="count" title="Selecione os Militares" data-actions-box="true">
														</select>
													</div>
												</div>
												<div class="form-group">
													<label class="col-sm-3 control-label">Datas: </label>
													<div class="col-sm-7">
														<div class="input-date input-group date">
															<input type="text" id="dias-arranchar" class="form-control"><span class="input-group-addon"><i class="fa fa-calendar"></i></span>
														</div>
													</div>
												</div>
												<div class="form-group">
													<label class="col-sm-3 control-label">Refeições: </label>
													<div class="col-sm-7">
														<div class="checkbox checkbox-primary">
															<input id="checkbox-cafe-arranchar" value="1" class="refeicao-arranchar" type="checkbox">
															<label for="checkbox-cafe-arranchar">
																<b>Café da Manhã</b>
															</label>
														</div>
														<div class="checkbox checkbox-primary">
															<input id="checkbox-almoco-arranchar" value="2" class="refeicao-arranchar" type="checkbox">
															<label for="checkbox-almoco-arranchar">
																<b>Almoço</b>
															</label>
														</div>
														<div class="checkbox checkbox-primary">
															<input id="checkbox-jantar-arranchar" value="3" class="refeicao-arranchar" type="checkbox">
															<label for="checkbox-jantar-arranchar">
																<b>Jantar</b>
															</label>
														</div>
													</div>
												</div>
												<div class="modal-footer">
													<button type="button" id="btnAbrirCopiarSemana" class="btn btn-default pull-left"><i class="fa-regular fa-copy"></i> Copiar semana anterior</button>
													<button type="button" id="btnSave-arranchar" class="btn btn-success">Arranchar <i class="fa-regular fa-floppy-disk"></i> </button>
													<button type="button" id="btnDesarranchar-arranchar" class="btn btn-danger">Desarranchar <i class="fa fa-trash"></i> </button>
												</div>
											</form>
										</div>
									</div>
								</div>
							</div>
							<div class="modal fade" tabindex="-1" role="dialog" id="modalAlterarSenha">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
											<h4 class="modal-title">Alteração de senha <i class="fa fa-unlock-alt"></i> </h4>
										</div>
										<div class="modal-body">
											<form class="form-horizontal" method="POST" id="senhaForm">
												<div class="form-group">
													<label class="col-sm-3 control-label">Senha atual: </label>
													<div class="col-sm-7">
														<input type="password" id="senha_atual" name="senha_atual" class="form-control" required>
													</div>
												</div>
												<div class="form-group">
													<label class="col-sm-3 control-label">Nova senha: </label>
													<div class="col-sm-7">
														<input type="password" id="senha_nova" name="senha_nova" class="form-control" required>
													</div>
												</div>
												<div class="modal-footer">
													<button type="submit" id="btn-senha" class="btn btn-success">Salvar <i class="fa-regular fa-floppy-disk"></i> </button>
												</div>
											</form>
										</div>
									</div>
								</div>
							</div>
							
							<div class="modal fade" tabindex="-1" role="dialog" id="modalGerenciarMilitares">
								<div class="modal-dialog modal-lg" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
											<h4 class="modal-title">Gerenciar Militares <i class="fa fa-users"></i> </h4>
										</div>
										<div class="modal-body">
											<table style="width:100%;text-align:center;" cellspacing="0" class="table table-condensed table-hover table-responsive tbl">
												<thead>
													<tr style="background-color:#4682B4;color:#FFFFFF;"> 
														<th style="text-align:center;">P/G</th>
														<th style="text-align:center;">Nome de Guerra</th>
														<th style="text-align:center;">Nome</th>
														<th style="text-align:center;">CPF</th>
														<th style="text-align:center;">Cia</th>
														<th style="text-align:center;">Ano incorp.</th>
														<th style="text-align:center;">Opções</th>
													</tr>
													<tr id="filtro"> 
														<th style="text-align:center;" class="pesquisa">P/G</th>
														<th style="text-align:center;" class="pesquisa">Nome de Guerra</th>
														<th style="text-align:center;" class="pesquisa">Nome</th>
														<th style="text-align:center;" class="pesquisa">CPF</th>
														<th style="text-align:center;" class="pesquisa">Cia</th>
														<th style="text-align:center;" class="pesquisa">Ano incorp.</th>
														<th style="text-align:center;">
														</th>
													</tr>
												</thead>
											</table>
										</div>
										<div class="modal-footer">
											<button id="add_militar" style="float: left;" class="btn btn-success" data-toggle="modal" data-target="#militar"><i class="fa fa-user-plus"></i> Adicionar</button>
										</div>
									</div>
								</div>
							</div>

							<div class="modal fade" tabindex="-1" role="dialog" id="militar">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
											<h4 class="modal-title">Militar <i class="fa fa-user"></i> </h4>
										</div>
										<div class="modal-body">
											<form class="form-horizontal form" method="POST" id="militarForm">
												<input type="hidden" name="opt" id="opt" value="">
												<input type="hidden" name="id_mil" id="id_mil" value="">
												<div class="form-group">
													<label class="col-sm-3 control-label">Nome: </label>
													<div class="col-sm-7">
														<input type="text" name="nome_mil" id="nome_mil" class="form-control" required="">
													</div>
												</div>
												<div class="form-group">
													<label class="col-sm-3 control-label">Nome Guerra: </label>
													<div class="col-sm-5">
														<input type="text" name="nome_guerra" id="nome_guerra" class="form-control" required="">
													</div>
												</div>
												<div class="form-group">
													<label class="col-sm-3 control-label">Nº CPF: </label>
													<div class="col-sm-4">
														<input type="text" name="identidade" id="identidade" class="form-control maskIdentidade" required="">
													</div>
												</div>
												<div class="form-group">
													<label class="col-sm-3 control-label">P/G: </label>
													<div class="col-sm-4">
														<select style="width: 100%;" class="form-control selectpicker" name="pg_militar" id="pg_militar" required="">
															<option selected>- Selecione -</option>
															<?php
															$con = $cnt->prepare("SELECT id, nome FROM cad_posto ORDER BY ordem ASC");

															$con->execute();
															while ($result = $con->fetch(PDO::FETCH_ASSOC)){ 
																?>
																<option <?php echo "value='".$result['id']."'"; ?>><?php echo $result['nome']; ?></option>
																<?php } ?>
															</select>
														</div>
													</div>
													<div class="form-group">
														<label class="col-sm-3 control-label">Cia: </label>
														<div class="col-sm-6">
															<select style="width: 100%;" class="form-control selectpicker" name="om_militar" id="om_militar" required="">
																<option selected>- Selecione -</option>
																<?php
																if($usuario_logado['tipo'] == 1){
																	$con = $cnt->prepare("SELECT * FROM cad_cia WHERE id < 100 ");
																}else {
																	$con = $cnt->prepare("SELECT * FROM cad_cia WHERE id = ".$usuario_logado['om']);
																}
																$con->execute();
																while ($result = $con->fetch(PDO::FETCH_ASSOC)){ 
																	?>
																	<option <?php echo "value='".$result['id']."'"; ?>><?php echo $result['nome']; ?></option>
																	<?php } ?>
																</select>
															</div>
														</div>
														<div class="form-group" id="grupo-incorporacao" style="display:none;">
															<label class="col-sm-3 control-label" for="ano_incorporacao">Ano de incorporação: </label>
															<div class="col-sm-5">
																<input type="number" name="ano_incorporacao" id="ano_incorporacao" class="form-control" min="1950" max="<?php echo date('Y'); ?>" step="1" inputmode="numeric" placeholder="Ex.: <?php echo date('Y'); ?>">
																<span class="help-block" style="margin-bottom:0;font-size:12px;">Só para SD: ano em que a turma incorporou, usado para ordenar os soldados por antiguidade no relatório. Pode ficar em branco.</span>
															</div>
														</div>
														<div class="modal-footer">
															<button type="button" id="btn-zerar-senha-app" class="btn btn-default pull-left" style="display:none;" title="O militar esqueceu a senha do app"><i class="fa-solid fa-key"></i> Zerar senha do app</button>
															<button type="submit" id="btn-salvarMil" class="btn btn-success">Salvar<i class="fa-regular fa-floppy-disk"></i> </button>
														</div>
													</form>
												</div>
											</div>
										</div>
									</div>
									

									<?php if($usuario_logado['tipo'] == 1){ ?>
										<div class="modal fade" tabindex="-1" role="dialog" id="modalGerenciarUsuario">
											<div class="modal-dialog modal-lg" role="document">
												<div class="modal-content">
													<div class="modal-header">
														<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
														<h4 class="modal-title">Gerenciar Usuários <i class="fa fa-users"></i> </h4>
													</div>
													<div class="modal-body">
														<table style="width:100%;text-align:center;" cellspacing="0" class="table table-condensed table-hover table-responsive tbl_us">
															<thead>
																<tr style="background-color:#4682B4;color:#FFFFFF;"> 
																	<th style="text-align:center;">Nome</th>
																	<th style="text-align:center;">Usuário</th>
																	<th style="text-align:center;">Cia</th>
																	<th style="text-align:center;">Tipo</th>
																	<th style="text-align:center;">Opções</th>
																</tr>
																<tr class="filtro"> 
																	<th class="pesquisa" style="text-align:center;">Nome</th>
																	<th class="pesquisa" style="text-align:center;">Usuário</th>
																	<th class="pesquisa" style="text-align:center;">Cia</th>
																	<th class="pesquisa" style="text-align:center;">Tipo</th>
																	<th style="text-align:center;">
																	</th>
																</tr>
															</thead>
														</table>
													</div>
													<div class="modal-footer">
														<button id="add_usuario" style="float: left;" class="btn btn-success" data-toggle="modal" data-target="#usuario"><i class="fa fa-user-plus"></i> Adicionar</button>
													</div>
												</div>
											</div>
										</div>

										<div class="modal fade" tabindex="-1" role="dialog" id="usuario">
											<div class="modal-dialog" role="document">
												<div class="modal-content">
													<div class="modal-header">
														<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
														<h4 class="modal-title">Usuário <i class="fa fa-user"></i> </h4>
													</div>
													<div class="modal-body">
														<form class="form-horizontal" method="POST" id="usuarioForm">
															<input type="hidden" name="opt_us" id="opt_us" value="">
															<input type="hidden" name="id_us" id="id_us" value="">
															<div class="form-group">
																<label class="col-sm-3 control-label">Nome: </label>
																<div class="col-sm-7">
																	<input type="text" name="nome_us" id="nome_us" class="form-control" required="">
																</div>
															</div>
															<div class="form-group">
																<label class="col-sm-3 control-label">Usuário: </label>
																<div class="col-sm-5">
																	<input type="text" name="usuario_us" id="usuario_us" class="form-control" required="">
																</div>
															</div>
															<div class="form-group">
																<label class="col-sm-3 control-label">Senha: </label>
																<div class="col-sm-5">
																	<input type="password" name="senha_us" id="senha_us" class="form-control">
																</div>
															</div>
															<div class="form-group">
																<label class="col-sm-3 control-label">Cia: </label>
																<div class="col-sm-6">
																	<select style="width: 100%;" class="form-control selectpicker" name="om_us" id="om_us" required="">
																		<option selected>- Selecione -</option>
																		<?php
																		if($usuario_logado['tipo'] == 1){
																			$con = $cnt->prepare("SELECT * FROM cad_cia WHERE id < 100 ");
																		}else {
																			$con = $cnt->prepare("SELECT * FROM cad_cia WHERE id = ".$usuario_logado['om']);
																		}
																		$con->execute();
																		while ($result = $con->fetch(PDO::FETCH_ASSOC)){ 
																			?>
																			<option <?php echo "value='".$result['id']."'"; ?>><?php echo $result['nome']; ?></option>
																			<?php } ?>
																		</select>
																	</div>
																</div>
																<div class="form-group">
																	<label class="col-sm-3 control-label">Perfil: </label>
																	<div class="col-sm-4">
																		<select style="width: 100%;" class="form-control selectpicker" name="tipo_us" id="tipo_us" required="">
																			<option selected>- Selecione -</option>
																			<option value="1">Administrador</option>
																			<option value="2">Operador</option>
																		</select>
																	</div>
																</div>
																<div class="modal-footer">
																	<button type="submit" id="btn-salvarUsuario" class="btn btn-success">Salvar <i class="fa-regular fa-floppy-disk"></i> </button>
																</div>
															</form>
														</div>
													</div>
												</div>
											</div>
											<?php } ?>

											<?php if($usuario_logado['tipo'] == 1){ ?>
												<div class="modal fade" tabindex="-1" role="dialog" id="modalGerenciarOrg">
													<div class="modal-dialog modal-lg" role="document">
														<div class="modal-content">
															<div class="modal-header">
																<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
																<h4 class="modal-title">Gerenciar Organização <i class="fa fa-university "></i> </h4>
															</div>
															<div class="modal-body">
																<table style="width:100%;text-align:center;" cellspacing="0" class="table table-condensed table-hover table-responsive tbl_org">
																	<thead>
																		<tr style="background-color:#4682B4;color:#FFFFFF;"> 
																			<th style="text-align:center;">Sigla</th>
																			<th style="text-align:center;">Descrição</th>
																			<th style="text-align:center;">Opções</th>
																		</tr>
																		<tr class="filtro"> 
																			<th class="pesquisa" style="text-align:center;">Sigla</th>
																			<th class="pesquisa" style="text-align:center;">Descrição</th>
																			<th style="text-align:center;">
																			</th>
																		</tr>
																	</thead>
																</table>
															</div>
															<div class="modal-footer">
																<button id="add_organizacao" style="float: left;" class="btn btn-success" data-toggle="modal" data-target="#organizacao"><i class="fa fa-user-plus"></i> Adicionar</button>
															</div>
														</div>
													</div>
												</div>

												<div class="modal fade" tabindex="-1" role="dialog" id="organizacao">
													<div class="modal-dialog" role="document">
														<div class="modal-content">
															<div class="modal-header">
																<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
																<h4 class="modal-title">Organização <i class="fa fa-university"></i></h4>
															</div>
															<div class="modal-body">
																<form class="form-horizontal form" method="POST" id="organizacaoForm">
																	<input type="hidden" name="opt_org" id="opt_org" value="">
																	<input type="hidden" name="id_org" id="id_org" value="">
																	<div class="form-group">
																		<label class="col-sm-3 control-label">Sigla: </label>
																		<div class="col-sm-5">
																			<input type="text" name="sigla_org" id="sigla_org" class="form-control" required="">
																		</div>
																	</div>
																	<div class="form-group">
																		<label class="col-sm-3 control-label">Descrição: </label>
																		<div class="col-sm-7">
																			<input type="text" name="desc_org" id="desc_org" class="form-control" required="">
																		</div>
																	</div>
																</div>
																<div class="modal-footer">
																	<button type="submit" id="btn-salvarOrganizacao" class="btn btn-success">Salvar <i class="fa-regular fa-floppy-disk"></i> </button>
																</div>
															</form>
														</div>
													</div>
												</div>
											</div>
											<?php } ?>
											<div id="divLoading"></div>

<?php
	// Semanas que dá para preencher: a atual (só os dias que faltam) e as duas seguintes.
	$segundaAtual = date('Y-m-d', strtotime('monday this week'));
	$semanasCopia = [];
	for ($i = 0; $i < 3; $i++) {
		$seg = date('Y-m-d', strtotime($segundaAtual . ' +' . (7 * $i) . ' days'));
		$semanasCopia[$seg] = ($i === 0 ? 'Esta semana' : ($i === 1 ? 'Próxima semana' : 'Daqui a 2 semanas'))
			. ' (' . date('d/m', strtotime($seg)) . ' a ' . date('d/m', strtotime($seg . ' +6 days')) . ')';
	}
?>
<div class="modal fade" tabindex="-1" role="dialog" id="modalCopiarSemana">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title">Copiar semana anterior <i class="fa-regular fa-copy"></i></h4>
			</div>
			<div class="modal-body">
				<p class="text-muted" style="font-size:13px;">
					Repete na semana escolhida os arranchamentos da semana anterior, para os militares da Cia.
					Não apaga nada: só cria o que falta. Dias que já passaram e dias bloqueados ficam de fora.
				</p>
				<form class="form-horizontal" onsubmit="return false;">
					<div class="form-group">
						<label class="col-sm-3 control-label" for="copia-om">Cia:</label>
						<div class="col-sm-7">
							<select class="form-control" id="copia-om">
								<?php
								if ($usuario_logado['tipo'] == 1) {
									$con = $cnt->prepare("SELECT id, nome FROM cad_cia WHERE id < 100 ORDER BY nome");
									echo '<option value="">- Selecione -</option>';
								} else {
									$con = $cnt->prepare("SELECT id, nome FROM cad_cia WHERE id = " . (int)$usuario_logado['om']);
								}
								$con->execute();
								while ($result = $con->fetch(PDO::FETCH_ASSOC)) {
									echo '<option value="' . (int)$result['id'] . '">' . htmlspecialchars($result['nome']) . '</option>';
								}
								?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label" for="copia-semana">Preencher:</label>
						<div class="col-sm-7">
							<select class="form-control" id="copia-semana">
								<?php $i = 0; foreach ($semanasCopia as $seg => $rotulo) { ?>
									<option value="<?php echo $seg; ?>"<?php echo $i++ === 1 ? ' selected' : ''; ?>><?php echo $rotulo; ?></option>
								<?php } ?>
							</select>
							<span class="help-block" style="font-size:12px;margin-bottom:0;" id="copia-origem"></span>
						</div>
					</div>
				</form>
				<div id="copia-previa"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" id="btnPreviaCopia"><i class="fa fa-eye"></i> Ver prévia</button>
				<button type="button" class="btn btn-success" id="btnCopiarSemana" disabled><i class="fa-regular fa-copy"></i> Copiar</button>
			</div>
		</div>
	</div>
</div>
