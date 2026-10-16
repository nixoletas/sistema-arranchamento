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
#loading-overlay .box {
    text-align: center;
    color: #337ab7;
}
</style>

<div class="well well-sm">
    <p <?php echo "id='".$usuario_logado['posto_id']."'";?>><strong>P/G: </strong><?php echo isset($usuario_logado['posto_desc']) ? $usuario_logado['posto_desc'] : "Anônimo"; ?></p>
    <p><strong>Nome Guerra:</strong> <?php echo isset($usuario_logado['nome_guerra']) ? $usuario_logado['nome_guerra'] : "Anônimo"; ?></p>
    <p class="ident" <?php echo "id='".$usuario_logado['ident_militar']."'";?>><b>CPF:</b> <?php echo $usuario_logado['ident_militar']; ?></p>
    <p class="om" <?php echo "id='".$usuario_logado['companhia_id']."'";?>><b>Cia: </b><?php echo isset($usuario_logado['companhia_desc']) ? $usuario_logado['companhia_desc'] : "Anônimo"; ?></p>
    <!-- <a style="text-decoration: none;color:#ffffff;box-shadow: 6px 8px 7px -5px #276873;background-color:#0000006E;padding:5px 5px;border-radius:10px;" href="logout.php" title="Sair">Sair <i class="fa fa-sign-out"></i></a> -->
</div>

<div class="well well-sm" id="resumo-semana">
    <i class="fas fa-calendar-check"></i> <strong>Refeições confirmadas essa semana:</strong> <span id="resumo-semana-total">0</span>
</div>

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
