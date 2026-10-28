<style>
.datepicker td.arranchado-bloqueado {
    background-color: #8a8a8a !important;
    color: #ffffff !important;
    border-radius: 4px;
    cursor: not-allowed;
}
#loading-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(255,255,255,0.75);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
/* Arranchar por semana */
.tabela-semana { margin-bottom: 0; }
.tabela-semana th { text-align: center; }
.tabela-semana th:first-child { text-align: left; }
.tabela-semana .linha-semana td { background: #eef4fb; color: #31708f; border-top: 2px solid #c9d8ea; }
.tabela-semana .acoes-semana { float: right; }
.tabela-semana .acoes-semana .btn-link { padding: 0 4px; }
.tabela-semana td.dia { white-space: nowrap; vertical-align: middle; }
.tabela-semana td.dia .prazo { display: block; color: #888; font-size: 11px; }
.tabela-semana tr.fim-semana td.dia strong { color: #a94442; }
.tabela-semana td.celula-ref { text-align: center; vertical-align: middle; width: 22%; }
.tabela-semana td.celula-ref label { display: block; margin: 0; padding: 6px 0; cursor: pointer; position: relative; }
.tabela-semana td.celula-ref input { width: 24px; height: 24px; margin: 0; cursor: pointer; accent-color: #0044cc; vertical-align: middle; }
.tabela-semana td.celula-ref input:disabled { cursor: not-allowed; }
.tabela-semana td.travada input { accent-color: #8a8a8a; }
.tabela-semana td.bloqueada { background: #f5f5f5; }
.tabela-semana td.bloqueada .fa-lock { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); color: #999; pointer-events: none; }
.tabela-semana td.bloqueada input { opacity: 0; }
.tabela-semana td.mudou { background: #fcf8e3; box-shadow: inset 0 0 0 2px #f0ad4e; }
#semana-barra { position: sticky; bottom: 0; z-index: 5; background: #fff; border-top: 2px solid #f0ad4e; }
#semana-barra .btn { margin-left: 6px; }
@media (max-width: 767px) {
    .tabela-semana .acoes-semana { float: none; display: block; margin-top: 2px; }
    #semana-barra { text-align: center; }
    #semana-barra .botoes { display: block; margin-top: 6px; }
}
#loading-overlay .box {
    text-align: center;
    color: #337ab7;
}
</style>

<div class="well well-sm" id="dados-militar">
    <p class="pg" <?php echo "id='".$usuario_logado['posto_id']."'";?>><strong>P/G: </strong><?php echo isset($usuario_logado['posto_desc']) ? $usuario_logado['posto_desc'] : "Anônimo"; ?></p>
    <p><strong>Nome Guerra:</strong> <?php echo isset($usuario_logado['nome_guerra']) ? $usuario_logado['nome_guerra'] : "Anônimo"; ?></p>
    <p class="ident" <?php echo "id='".$usuario_logado['ident_militar']."'";?>><b>CPF:</b> <?php echo $usuario_logado['ident_militar']; ?></p>
    <p class="om" <?php echo "id='".$usuario_logado['companhia_id']."'";?>><b>Cia: </b><?php echo isset($usuario_logado['companhia_desc']) ? $usuario_logado['companhia_desc'] : "Anônimo"; ?></p>
    <!-- <a style="text-decoration: none;color:#ffffff;box-shadow: 6px 8px 7px -5px #276873;background-color:#0000006E;padding:5px 5px;border-radius:10px;" href="logout.php" title="Sair">Sair <i class="fa fa-sign-out"></i></a> -->
</div>

<div class="well well-sm" id="resumo-semana">
    <i class="fas fa-calendar-check"></i> <strong>Refeições confirmadas essa semana:</strong> <span id="resumo-semana-total">0</span>
</div>

<div class="alert alert-info" id="regras-prazo" style="padding:10px 14px;">
    <i class="fa-solid fa-circle-info"></i> <strong>Como funciona:</strong>
    você pode se arranchar de <strong>2 a 14 dias</strong> à frente.
    Para <strong>desarranchar</strong>, o prazo é até <strong>2 dias antes</strong> da refeição;
    refeições de <strong>sábado, domingo e segunda</strong> só podem ser desarranchadas até a <strong>sexta-feira anterior</strong>.
    Dias com <i class="fa fa-lock"></i> foram bloqueados pelo aprovisionamento.
</div>

<div class="well well-sm" id="caixa-app">
    <i class="fa-solid fa-mobile-screen-button"></i> <strong>App do arranchamento:</strong> <span id="app-status">...</span>
    <div style="margin-top:8px;">
        <button type="button" class="btn btn-default btn-sm" id="btn-senha-app" data-toggle="modal" data-target="#modalSenhaApp"><i class="fa-solid fa-key"></i> Senha do app</button>
        <button type="button" class="btn btn-success btn-sm" id="btn-instalar-app" style="display:none;"><i class="fa-solid fa-download"></i> Instalar app</button>
        <a class="btn btn-default btn-sm" href="/app/" target="_blank" rel="noopener"><i class="fa-solid fa-share-nodes"></i> Aplicativo: baixar e divulgar</a>
    </div>
    <p id="dica-ios" class="text-muted" style="display:none;font-size:12px;margin:8px 0 0;">
        No iPhone: toque em <i class="fa-solid fa-arrow-up-from-bracket"></i> Compartilhar e depois em "Adicionar à Tela de Início".
    </p>
</div>

<div class="modal fade" id="modalSenhaApp" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <form id="form-senha-app" autocomplete="off">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="fa-solid fa-key"></i> Senha do app</h4>
                </div>
                <div class="modal-body">
                    <p class="text-muted" style="font-size:13px;">Senha de <strong>6 números</strong> para entrar pelo app fora da OM. Dentro da OM o site continua pedindo só o CPF.</p>
                    <div class="form-group" id="grupo-senha-atual" style="display:none;">
                        <label for="senha-app-atual">Senha atual</label>
                        <input type="password" class="form-control" id="senha-app-atual" inputmode="numeric" maxlength="6">
                    </div>
                    <div class="form-group">
                        <label for="senha-app-nova">Nova senha</label>
                        <input type="password" class="form-control" id="senha-app-nova" inputmode="numeric" maxlength="6" required>
                    </div>
                    <div class="form-group">
                        <label for="senha-app-confirma">Repita a nova senha</label>
                        <input type="password" class="form-control" id="senha-app-confirma" inputmode="numeric" maxlength="6" required>
                    </div>
                    <p class="text-danger" id="senha-app-erro" style="margin:0;"></p>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success" id="salvar-senha-app"><i class="fa-regular fa-floppy-disk"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="panel panel-primary" id="painel-semana">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fa-solid fa-calendar-week"></i> Arranchar por semana</h3>
    </div>
    <div class="panel-body" style="padding:0 0 0 0;">
        <p class="text-muted" style="padding:8px 12px 0;margin:0;font-size:13px;">
            Marque as refeições e toque em <strong>Salvar</strong>. "Marcar todas" marca a semana inteira de uma vez.
        </p>
        <div id="semana-tabela" class="table-responsive" style="border:0;"></div>
    </div>
    <div class="panel-footer" id="semana-barra" style="display:none;">
        <span id="semana-pendentes"></span>
        <span class="botoes pull-right-sm">
            <button type="button" class="btn btn-default btn-sm" id="semana-desfazer"><i class="fa-solid fa-rotate-left"></i> Desfazer</button>
            <button type="button" class="btn btn-success btn-sm" id="semana-salvar"><i class="fa-regular fa-floppy-disk"></i> Salvar</button>
        </span>
    </div>
</div>

<p class="text-center">
    <a class="btn btn-default btn-sm" data-toggle="collapse" href="#ver-calendarios" aria-expanded="false" aria-controls="ver-calendarios">
        <i class="fa fa-calendar"></i> Ver por calendário (café, almoço e jantar separados)
    </a>
</p>

<div class="collapse" id="ver-calendarios">
<div class="row">
    <!-- Painel cafe -->
    <div class="col-md-4">
        <div class="panel-group accordion" id="panel-cafe">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="text-center panel-title">
                        <a style="color:#337ab7;text-decoration: none;" data-toggle="collapse" href="#addCafeManha">CAFÉ DA MANHÃ
                            <i class="fa-solid fa-mug-saucer"></i>
                            <i class="indicator fa fa-arrow-circle-down pull-right"></i>
                        </a>
                    </h4>
                </div>
                <div id="addCafeManha" class="panel-collapse collapse in">
                    <div class="panel-body">
                        <div id="box-cafe" style="margin-left: auto;margin-right: auto;width: 100%;">
                            <div id="cafe-container" style="display: flex ;justify-content: center"></div>
                            <p><i style="color:#fdf59a" class="fa fa-square"></i> Hoje</p>
                            <p><i style="color:#0044cc" class="fa fa-square"></i> Dia Arranchado</p>
                            <p><i style="color:#8a8a8a" class="fa fa-square"></i> Arranchado (prazo p/ cancelar encerrado)</p>
                            <p><i style="color:#504F4F2B" class="fa fa-square"></i> Dia Bloqueado</p>
                        </div>
                        <div class="text-center" id="box-status-cafe"></div>
                    </div>
                    <div class="panel-footer">
                        <div class="text-center box-motivo-bloqueio-cafe"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Painel almoco -->
    <div class="col-md-4">
        <div class="panel-group accordion" id="panel-almoco">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title text-center">
                        <a style="color:#337ab7;text-decoration: none;" data-toggle="collapse" href="#addAlmoco">ALMOÇO
                            <i class="fa-solid fa-plate-wheat"></i>
                            <i class="indicator fa fa-arrow-circle-down pull-right"></i>
                        </a>
                    </h3>
                </div>
                <div id="addAlmoco" class="panel-collapse collapse in">
                    <div class="panel-body">
                        <div id="box-almoco" class="form-group" style="margin-left: auto;margin-right: auto;width: 100%;">
                            <div id="almoco-container" style="display: flex ;justify-content: center"></div>
                            <p><i style="color:#fdf59a" class="fa fa-square"></i> Hoje</p>
                            <p><i style="color:#0044cc" class="fa fa-square"></i> Dia Arranchado</p>
                            <p><i style="color:#8a8a8a" class="fa fa-square"></i> Arranchado (prazo p/ cancelar encerrado)</p>
                            <p><i style="color:#504F4F2B" class="fa fa-square"></i> Dia Bloqueado</p>
                        </div>
                        <div class="text-center" id="box-status-almoco"></div>
                    </div>
                    <div class="panel-footer">
                        <div class="text-center box-motivo-bloqueio-almoco"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Painel jantar -->
    <div class="col-md-4">
        <div class="panel-group accordion" id="panel-janta">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title text-center">
                        <a style="color:#337ab7;text-decoration: none;" data-toggle="collapse" href="#addJanta">JANTAR
                            <i class="fa-solid fa-bowl-food"></i>
                            <i class="indicator fa fa-arrow-circle-down pull-right"></i>
                        </a>
                    </h3>
                </div>
                <div id="addJanta" class="panel-collapse collapse in">
                    <div class="panel-body">
                        <div id="box-janta" class="form-group" style="margin-left: auto;margin-right: auto;width: 100%;">
                            <div id="janta-container" style="display: flex ;justify-content: center"></div>
                            <p><i style="color:#fdf59a" class="fa fa-square"></i> Hoje</p>
                            <p><i style="color:#0044cc" class="fa fa-square"></i> Dia Arranchado</p>
                            <p><i style="color:#8a8a8a" class="fa fa-square"></i> Arranchado (prazo p/ cancelar encerrado)</p>
                            <p><i style="color:#504F4F2B" class="fa fa-square"></i> Dia Bloqueado</p>
                        </div>
                        <div class="text-center" id="box-status-janta"></div>
                    </div>
                    <div class="panel-footer">
                        <div class="text-center box-motivo-bloqueio-janta"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- /#ver-calendarios -->

<div class="well well-sm">
    <h4 class="text-primary text-center"><i class="fas fa-utensils"></i> MEUS ARRANCHAMENTOS <i class="fa fa-calendar"></i></h4>
    <ul id="dias-arranchado-list" class="list-group">
        <!-- Os itens da lista serão adicionados aqui pelo JavaScript -->
    </ul>
</div>

<div id="loading-overlay">
    <div class="box">
        <i class="fa fa-spinner fa-spin fa-3x"></i>
        <p style="margin-top:10px;">Salvando...</p>
    </div>
</div>

<script src="assets/js/arranchar-semana.js?v=<?php echo (int)@filemtime(__DIR__ . '/../../assets/js/arranchar-semana.js'); ?>" defer></script>
<script src="assets/js/app-pwa.js?v=<?php echo (int)@filemtime(__DIR__ . '/../../assets/js/app-pwa.js'); ?>" defer></script>
