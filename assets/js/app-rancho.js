$(document).ready(function () {

	$('.selectpicker').selectpicker();

	// Bloquear dias (Cia e P/G): caixa de marcar em cada opção (CSS em home.php, classe select2-caixas),
	// lista aberta enquanto se marca, e atalhos "Marcar todos / Desmarcar todos".
	$('.bloqOm, .bloqPg').each(function() {
		var $sel = $(this);
		$sel.select2({
			closeOnSelect: false,
			width: '100%',
			dropdownCssClass: 'select2-caixas',
			placeholder: $sel.hasClass('bloqOm') ? 'Selecione as Cias' : 'Selecione os P/G'
		});
		var $acoes = $('<div class="acoes-multipla"><a href="#" data-acao="todos">Marcar todos</a> · <a href="#" data-acao="nenhum">Desmarcar todos</a></div>');
		$acoes.on('click', 'a', function(ev) {
			ev.preventDefault();
			$sel.find('option').prop('selected', $(this).data('acao') === 'todos');
			$sel.trigger('change');
		});
		$sel.next('.select2').after($acoes);
	});
	$(".maskIdentidade").mask("000.000.000-00");

	$(".form input").keyup(function(){
		$(this).val($(this).val().toUpperCase());
	});

	$('.dt-relatorio').datepicker({
		format: "dd/mm/yyyy",
		language: "pt-BR",
		orientation: "bottom right",
		todayHighlight: true,
		daysOfWeekHighlighted: [1, 2, 3, 4, 5],
		todayBtn: true
	});

	$('.input-daterange').datepicker({
		format: "dd/mm/yyyy",
		language: "pt-BR",
		multidate: true,
		orientation: "bottom right",
		startDate: '+1d',
		todayHighlight: true,
		todayBtn: true
	});

	$('.input-date').datepicker({
    format: "dd/mm/yyyy",
    language: "pt-BR",
    multidate: true,
    multidateSeparator: ",",
    autoclose: false,
    orientation: "bottom right",
    startDate: '+1d',
    endDate: '+7d', // de amanhã até 7 dias à frente
    todayHighlight: true,
    daysOfWeekHighlighted: [0, 6],
    todayBtn: true
});

	$('.input-date-rancho').datepicker({
		format: "yyyy-mm-dd",
		language: "pt-BR",
		multidate: true,
		orientation: "bottom right",
		startDate: '+1d',
		endDate: '+25d',
		todayHighlight: true,
		daysOfWeekHighlighted: [0, 6],
		todayBtn: true
	});

	function countChar(val) {
		var len = val.value.length;
		if (len >= 150) {
			val.value = val.value.substring(0, 150);
		} else {
			var ch = 150 - len;
			$('.charNum').text('Restam '+ch+' caracteres');
		}
	};

	$('#form_login').validate({ 
		rules: {
			usuario: {
				required: true,
			},
			senha: {
				required: true,
			}
		},
		errorElement: 'span',
		errorClass: 'help-block',
		errorPlacement: function(error, element) {
			if(element.length) {
				error.insertAfter(element);
			} else {
				error.insertAfter(element);
			}
		},
		submitHandler: function (form) {
			var formData = btoa($("#form_login").serialize());
			$.ajax({
				type: "POST",
				dataType: "json",
				url: "../rancho/controllers/autentica.php",
				data: { formData: formData},
				success: function(data){
					if(data.erro){
						$('#box-login').html(data.erro);
					}else
					window.location = data.url;
				}
			});
			return false;
		}
	});

	$('.delRegra').click(function (e) {
		var idRegra = $(this).attr('id');
		var rowElement = $(this).parent().parent();
		$.ajax({
			type: "POST",
			url: "../rancho/controllers/registros.php?opt=del",
			data: {idRegra:idRegra},
			success: function(data){
				rowElement.fadeOut(500).remove();
			}
		});
	});

	$('#btn-aviso').click(function (e) {
		$.ajax({
			type: "POST",
			url: "../controllers/avisos.php",
			success: function(html){
				CKEDITOR.instances['avisos'].setData(html);
			}
		});
	});

	$('#btnSalvar-aviso').click(function (e) {
		var avisos = CKEDITOR.instances['avisos'].getData()
		$.ajax({
			type: "POST",
			dataType: "json",
			url: "../rancho/controllers/processa-aviso.php",
			data: {avisos:avisos},
			success: function(data){
				avisar(data.msg, 'sucesso')
				window.location = "home.php";
			}
		});
	});

	$('#btnGerar-prd').on('click', function() {	
		var dt_ini = $('#data-inicio').val();
		var dt_fim = $('#data-fim').val();

		var ref = [];
		$("input:checkbox[class=refeicao-rel]:checked").each(function(){
			ref.push(this.value);
		});

		if(dt_ini && dt_fim){
			var req = new XMLHttpRequest();
			$('#gerar-relatorio').modal('hide');
			$("div#divLoading").addClass('show');
			req.open("GET", "../rancho/relatorios/periodo.php?ref="+encodeURIComponent(ref)+"&dt_ini="+encodeURIComponent(dt_ini)+"&dt_fim="+encodeURIComponent(dt_fim), true);
			req.responseType = "blob";
			req.onreadystatechange = function () {
				if (req.readyState === 4 && req.status === 200) {
					$("div#divLoading").removeClass('show');
					$('#gerar-relatorio').modal('show');
					var filename = "relatorio-por-periodo-" + new Date().getTime() + ".pdf";
					if (typeof window.chrome !== 'undefined') {
							//CHORME
							var link = document.createElement('a');
							link.href = window.URL.createObjectURL(req.response);
							link.download = "relatorio-por-periodo-" + new Date().getTime() + ".pdf";
							link.click();
						} else if (typeof window.navigator.msSaveBlob !== 'undefined') {
							//IE
							var blob = new Blob([req.response], { type: 'application/pdf' });
							window.navigator.msSaveBlob(blob, filename);
						} else {
							//FIREFOX
							var file = new File([req.response], filename, { type: 'application/pdf' });
							window.open(URL.createObjectURL(file));
							//application/force-download
						}
					}
				};
				req.send();
			}
		});

	// Relatórios: "Outras OMs" não usa Cia nem P/G, então os dois campos ficam desativados enquanto estiver marcado.
	var avisoRelatorio = function(msg) {
		if (msg) { $('#rel-aviso').html('<i class="fa fa-exclamation-triangle"></i> ' + msg).show(); }
		else { $('#rel-aviso').hide().empty(); }
	};
	$('#outrasOm').on('change', function() {
		$('#om_ard, #pg_ard').prop('disabled', this.checked).selectpicker('refresh');
		avisoRelatorio('');
	});
	$('#data_ard, #om_ard, #pg_ard').on('change', function() { avisoRelatorio(''); });
	$('#gerar-relatorio').on('show.bs.modal', function(e) { if (e.target === this) avisoRelatorio(''); });

	$('#btnGerar-ard').on('click', function() {	
		var dt = $('#data_ard').val();
		var om = $('#om_ard').val();
		var posto = $('#pg_ard').val();
		var outrasOm = $('#outrasOm').is(":checked");
		var req = new XMLHttpRequest();
		var refeicao = [];
		$("input:radio[class=refeicao-rel]:checked").each(function(){
			refeicao.push(this.value);
		});

		// Confere antes de fechar o modal: sem isso o "carregando" ficava girando para sempre.
		var faltando = [];
		if (!dt) faltando.push('a data');
		if (!outrasOm && !(om && om.length)) faltando.push('a Cia');
		if (!outrasOm && !(posto && posto.length)) faltando.push('o P/G');
		if (faltando.length) {
			avisoRelatorio('Selecione ' + faltando.join(', ').replace(/, ([^,]*)$/, ' e $1') + '.');
			return;
		}
		avisoRelatorio('');

		var url;
		if (outrasOm) {
			url = "../rancho/relatorios/arranchados-outra-om.php?dt="+encodeURIComponent(dt);
		} else {
			url = "../rancho/relatorios/arranchados.php?om="+encodeURIComponent(om)+"&ref="+encodeURIComponent(refeicao)+"&dt="+encodeURIComponent(dt)+"&posto="+encodeURIComponent(posto)
				+"&ordem="+encodeURIComponent($('input.ordem-rel:checked').val() || 'nome');
		}

		// Abre o PDF numa aba nova: dá para conferir e imprimir direto (antes ele só era baixado).
		// O relatório é gerado pelo servidor com a mesma sessão; a janela de Relatórios continua aberta.
		var aba = window.open(url, '_blank');
		if (!aba) {
			avisoRelatorio('O navegador bloqueou a nova aba. <a href="' + url + '" target="_blank" rel="noopener">Clique aqui para abrir o relatório</a>.');
		}
		});


	// ---------- Arranchar: buscar militar por nome de guerra, nome ou CPF ----------
	var buscaTimer = null;
	var $buscaRes = $('#busca-militar-resultados');
	var escHtml = function(t) { return $('<div>').text(t == null ? '' : t).html(); };
	$('#busca-militar').on('input', function() {
		var termo = $.trim(this.value);
		clearTimeout(buscaTimer);
		if (termo.length < 2) { $buscaRes.empty().hide(); return; }
		buscaTimer = setTimeout(function() {
			$.ajax({ url: 'controllers/pesquisa-militar.php', type: 'POST', dataType: 'json', data: { busca: termo } })
				.done(function(lista) {
					if (!lista.length) {
						$buscaRes.html('<span class="list-group-item text-muted">Nenhum militar encontrado.</span>').show();
						return;
					}
					$buscaRes.html($.map(lista, function(m) {
						return '<a href="#" class="list-group-item" data-cpf="' + escHtml(m.ident_militar) + '" data-nome="' + escHtml(m.nome_guerra) + '">'
							+ '<strong>' + escHtml(m.posto) + ' ' + escHtml(m.nome_guerra) + '</strong>'
							+ ' <small class="text-muted">' + escHtml(m.cia) + ' · ' + escHtml(m.ident_militar) + '</small></a>';
					}).join('')).show();
				});
		}, 300);
	});
	$buscaRes.on('click', 'a[data-cpf]', function(ev) {
		ev.preventDefault();
		var cpf = $(this).data('cpf'), nome = $(this).data('nome');
		var $sel = $('#militares');
		if (!$sel.find('option').filter(function() { return this.value === cpf; }).length) {
			$sel.append($('<option>').val(cpf).text('[' + cpf + '] ' + nome));
		}
		var atuais = $sel.val() || [];
		if ($.inArray(cpf, atuais) === -1) { atuais.push(cpf); }
		$sel.selectpicker('refresh').selectpicker('val', atuais);
		$('#busca-militar').val('').focus();
		$buscaRes.empty().hide();
		avisar(nome + ' incluído na lista de militares (' + atuais.length + ' selecionado' + (atuais.length > 1 ? 's' : '') + ').', 'sucesso');
	});
	$('#modalArranchar').on('hidden.bs.modal', function() { $('#busca-militar').val(''); $buscaRes.empty().hide(); });

	// ---------- Copiar semana anterior ----------
	$('#btnAbrirCopiarSemana').on('click', function() {
		$('#modalArranchar').one('hidden.bs.modal', function() { $('#modalCopiarSemana').modal('show'); }).modal('hide');
	});
	var textoOrigem = function() {
		var seg = $('#copia-semana').val();
		var d = new Date(seg + 'T12:00:00'); d.setDate(d.getDate() - 7);
		var f = new Date(d); f.setDate(f.getDate() + 6);
		var dm = function(x) { return ('0' + x.getDate()).slice(-2) + '/' + ('0' + (x.getMonth() + 1)).slice(-2); };
		$('#copia-origem').text('Copia de ' + dm(d) + ' a ' + dm(f) + '.');
	};
	var limparPrevia = function() { $('#copia-previa').empty(); $('#btnCopiarSemana').prop('disabled', true); textoOrigem(); };
	$('#copia-om, #copia-semana').on('change', limparPrevia);
	$('#modalCopiarSemana').on('show.bs.modal', function(e) { if (e.target === this) { limparPrevia(); } });

	var pedirCopia = function(acao) {
		return $.ajax({ url: 'controllers/copiar-semana.php', type: 'POST', dataType: 'json',
			data: { acao: acao, om: $('#copia-om').val(), semana: $('#copia-semana').val() } });
	};
	$('#btnPreviaCopia').on('click', function() {
		var $b = $(this).prop('disabled', true);
		pedirCopia('previa').done(function(d) {
			if (d.error) { $('#copia-previa').html('<div class="alert alert-warning">' + escHtml(d.error) + '</div>'); return; }
			var h = '<table class="table table-condensed table-bordered text-center" style="margin-bottom:8px;"><thead><tr>'
				+ '<th class="text-center">Dia</th><th class="text-center">Café</th><th class="text-center">Almoço</th><th class="text-center">Jantar</th></tr></thead><tbody>';
			$.each(d.linhas, function(_, l) {
				h += '<tr' + (l.passou ? ' class="text-muted"' : '') + '><td>' + escHtml(l.dia) + (l.passou ? ' <small>(já passou)</small>' : '') + '</td>'
					+ '<td>' + l.cafe + '</td><td>' + l.almoco + '</td><td>' + l.jantar + '</td></tr>';
			});
			h += '</tbody></table>';
			var notas = [];
			if (d.ja_existiam) { notas.push(d.ja_existiam + ' já estavam arranchados'); }
			if (d.dias_passados) { notas.push(d.dias_passados + ' são de dias que já passaram'); }
			if (d.bloqueados) { notas.push(d.bloqueados + ' caem em dias bloqueados'); }
			h += '<p><strong>' + d.total + ' refeição(ões) serão criadas</strong> (copiando ' + escHtml(d.origem) + ' para ' + escHtml(d.destino) + ').'
				+ (notas.length ? '<br><small class="text-muted">Ficam de fora: ' + notas.join('; ') + '.</small>' : '') + '</p>';
			$('#copia-previa').html(h);
			$('#btnCopiarSemana').prop('disabled', !d.total);
		}).fail(function() {
			$('#copia-previa').html('<div class="alert alert-danger">Não foi possível montar a prévia. Tente de novo.</div>');
		}).always(function() { $b.prop('disabled', false); });
	});
	$('#btnCopiarSemana').on('click', function() {
		if (!window.confirm('Confirmar a cópia da semana anterior?')) { return; }
		var $b = $(this).prop('disabled', true);
		pedirCopia('copiar').done(function(d) {
			if (d.error) { avisar(d.error, 'erro'); $b.prop('disabled', false); return; }
			avisar('Semana copiada: ' + d.criados + ' refeição(ões) arranchada(s).', 'sucesso');
			$('#modalCopiarSemana').modal('hide');
		}).fail(function() {
			avisar('Não foi possível copiar. Confira e tente de novo.', 'erro');
			$b.prop('disabled', false);
		});
	});

	// Lê o formulário do modal "Arranchar (Militares)": militares, datas e refeições marcadas.
	// Usado tanto para arranchar quanto para desarranchar (mesmo formulário, ações opostas).
	var lerFormularioArranchar = function() {
		var refeicao = [];
		$("input:checkbox[class=refeicao-arranchar]:checked").each(function(){
			refeicao.push(this.value);
		});
		return {
			militares: $('#militares').val(),
			dias: $('#dias-arranchar').val(),
			refeicao: refeicao
		};
	};

	var formularioArrancharValido = function(dados) {
		if (!dados.militares || dados.militares.length === 0) {
			avisar('Selecione pelo menos um militar!', 'aviso');
			return false;
		}
		if (!dados.dias) {
			avisar('Selecione pelo menos uma data!', 'aviso');
			return false;
		}
		if (dados.refeicao.length === 0) {
			avisar('Selecione pelo menos uma refeição!', 'aviso');
			return false;
		}
		return true;
	};

	$('#btnSave-arranchar').on('click', function(ev) {
		var dados = lerFormularioArranchar();
		if (!formularioArrancharValido(dados)) { return; }

		$('#modalArranchar').modal('hide');
		$("div#divLoading").addClass('show');

		$.ajax({
			url: "controllers/arranchar.php",
			type: "POST",
			dataType: "json",
			data: dados,
			success: function(data){
				if(data.error){
					$("div#divLoading").removeClass('show');
					avisar(data.error, 'erro');
				}
				if(data.success){
					$("div#divLoading").removeClass('show');
					avisar(data.success, 'sucesso')
					window.location.href  = "home.php";
				}
			},
			error: function(){
				$("div#divLoading").removeClass('show');
				avisar('Não foi possível concluir. Tente novamente.', 'erro');
			}
		});
	});

	$('#btnDesarranchar-arranchar').on('click', function(ev) {
		var dados = lerFormularioArranchar();
		if (!formularioArrancharValido(dados)) { return; }

		var qtdMilitares = dados.militares.length;
		var qtdDias = dados.dias.split(',').length;
		var qtdRefeicoes = dados.refeicao.length;
		var aviso = 'Tem certeza que deseja DESARRANCHAR ' + qtdMilitares + ' militar(es) em ' +
			qtdDias + ' dia(s) e ' + qtdRefeicoes + ' refeição(ões)? Essa ação não pede confirmação de novo.';
		if (!window.confirm(aviso)) { return; }

		$('#modalArranchar').modal('hide');
		$("div#divLoading").addClass('show');

		$.ajax({
			url: "controllers/desarranchar.php",
			type: "POST",
			dataType: "json",
			data: dados,
			success: function(data){
				if(data.error){
					$("div#divLoading").removeClass('show');
					avisar(data.error, 'erro');
				}
				if(data.success){
					$("div#divLoading").removeClass('show');
					avisar(data.success, 'sucesso')
					window.location.href  = "home.php";
				}
			},
			error: function(){
				$("div#divLoading").removeClass('show');
				avisar('Não foi possível concluir. Tente novamente.', 'erro');
			}
		});
	});

	$('#btnSave-OutraOm').on('click', function(ev) {
		var identidade = $('#identidade').val();
		var om = $('#om').val();
		var nome = $('#nome').val();
		var data = $('#data').val();
		var posto = $('#posto').val();
		var refeicao = [];
		$("input:checkbox[class=refeicao-arranchar-outraom]:checked").each(function(){
			refeicao.push(this.value);
		});

		$('#modalArrancharOutraOm').modal('hide');
		$("div#divLoading").addClass('show');

		if(identidade && om && nome){
			$.ajax({
				url: "controllers/arranchar-outra-om.php",
				type: "POST",
				dataType: "json",
				data: {identidade: identidade, om:om, posto: posto,  data: data, refeicao: refeicao, nome: nome},
				success: function(data){
					if(data.error){
						avisar(data.error, 'erro');
					}
					if(data.success){
						$("div#divLoading").removeClass('show');
						avisar(data.success, 'sucesso')
						window.location.href  = "home.php";
					}
				}
			});
		}else{
			avisar("Preencha todos campos!", 'aviso');
		}

	});

	$('#pg_arranchar').change(function(){
		$("#militares").find('option').remove();
		var posto = $(this).find('option:selected').val();
		var om = $("#om_arranchar").find('option:selected').val();
		$.ajax({
			url: "controllers/pesquisa-militar.php",
			type: "POST",
			data: {posto: posto, om: om },
			success: function(data){
				$('#militares').append(data);
				$('#militares').selectpicker('refresh');
				// Remarca os militares que estavam marcados da última vez neste P/G (se houver).
				var salvos = null;
				try { salvos = JSON.parse(localStorage.getItem('milData' + posto) || 'null'); } catch (e) { salvos = null; }
				if (salvos && salvos.length) {
					$('#militares').selectpicker('val', salvos);
				}
			}
		});
	});

	$('#btnRegra').on('click', function(ev) {
		ev.preventDefault();
		var om_bloq = [];
		$('.bloqOm option').each(function(i) {
			if (this.selected == true) {
				om_bloq.push(this.value);
			}
		});

		var pg_bloq = [];
		$('.bloqPg option').each(function(i) {
			if (this.selected == true) {
				pg_bloq.push(this.value);
			}
		});

		var ref_bloq = [];
		$("input:checkbox[class=refeicao]:checked").each(function(){
			ref_bloq.push(this.value);
		});
		var dias_bloq = $('#bloq_dias').val();
		var bloq_mot = $('#motivo_bloq').val();
		if(dias_bloq && bloq_mot && ref_bloq && om_bloq && pg_bloq){
			$.ajax({
				type: "POST",
				dataType: "json",
				url: "../rancho/controllers/registros.php?opt=add",
				data: {om_bloq:om_bloq, pg_bloq:pg_bloq, ref_bloq:ref_bloq, dias_bloq:dias_bloq, bloq_mot:bloq_mot},
				success: function(data){
					if(data.msg){
						$(".msg-status").hide().html(data.msg).fadeIn('slow').delay(5000).fadeOut('fast');
						window.location = "home.php" + "?status=success";
					}else{
						$(".msg-status").hide().html(data.error).fadeIn('slow').delay(5000).fadeOut('fast');
					}
				}
			});
		}else{
			avisar("Preencha todos campos!", 'aviso');
		}
	});

	$('#outrasOm').change(function() {
		if(this.checked) {
			$('.selectpicker').selectpicker('deselectAll');
			$('#om_ard').prop('disabled', true);
			$('#pg_ard').prop('disabled', true);
		}
		else{
			$('#om_ard').prop('disabled', false);
			$('#pg_ard').prop('disabled', false);
		}

	});


	$(function() {
		$('#militares').change(function() {
			var militares = $('#militares').val();
			var posto = $('#pg_arranchar').find('option:selected').val();
			localStorage.setItem('milData'+posto, JSON.stringify(militares));
		});
	});

	$.fn.modal.Constructor.prototype.enforceFocus = function() {
		var $modalElement = this.$element;
		$(document).on('focusin.modal',function(e) {
			var $parent = $(e.target.parentNode);
			if ($modalElement[0] !== e.target
				&& !$modalElement.has(e.target).length
				&& $(e.target).parentsUntil('*[role="dialog"]').length === 0) {
				$modalElement.focus();
		}
	});
	};

	$('.tbl thead tr#filtro th.pesquisa').each(function(){
		var title = $('.tbl thead th').eq($(this).index()).text();
		$(this).html('<input style="width:100%;" class="form-control input-sm text-center" onclick="stopPropagation(event);" type="text" placeholder="Filtrar" />');
	});

	$(".tbl thead input").on( 'keyup change', function () {
		table
		.column( $(this).parent().index()+':visible' )
		.search( this.value )
		.draw();
	});

	$('.tbl_us thead tr.filtro th.pesquisa').each(function(){
		var title = $('.tbl_us thead th').eq($(this).index()).text();
		$(this).html('<input style="width:100%;" class="form-control input-sm text-center" onclick="stopPropagation(event);" type="text" placeholder="Filtrar" />');
	});

	$(".tbl_us thead input").on( 'keyup change', function () {
		table_us
		.column( $(this).parent().index()+':visible' )
		.search( this.value )
		.draw();
	});

	$('.tbl_org thead tr.filtro th.pesquisa').each(function(){
		var title = $('.tbl_org thead th').eq($(this).index()).text();
		$(this).html('<input style="width:100%;" class="form-control input-sm text-center" onclick="stopPropagation(event);" type="text" placeholder="Filtrar" />');
	});

	$(".tbl_org thead input").on( 'keyup change', function () {
		table_org
		.column( $(this).parent().index()+':visible' )
		.search( this.value )
		.draw();
	});

	var table = $('.tbl').DataTable({
		pageLength:20,
		ordering: false,
		orderCellsTop: true,
		Processing: true,
		ServerSide: true,
		language: {
			lengthMenu: "Mostrar _MENU_ registros por página",
			zeroRecords: "Nenhum Registro Encontrado",
			info: "Mostrando _PAGE_ de _PAGES_ páginas",
			infoEmpty: "Nenhum Registro Encontrado",
			infoFiltered: "(Filtrado de _MAX_ registros totais)",
			loadingRecords: "Carregando ...",
			processing: "Processando ...",
			search: "Pesquisar",
			paginate:{
				first: "Primeira",
				last : "Última",
				next : "Próxima",
				previous: "Voltar"
			}
		},
		ajax: {
			url: 'controllers/militares.php',
			data: {opt: 'select'},
			type: 'POST',
			dataSrc: 'data'
		},
		columns: [
		{ data: 'posto_nome'},
		{ data: 'nome_guerra'},
		{ data: 'nome'},
		{ data: 'ident_militar'},
		{ data: 'om_nome'},
		{ data: 'ano_incorporacao', defaultContent: ''},
		{
			data: null,
			sortable: false,
			render:function(data) {
				var editar = "<button value=\"edit\" class=\"btn btn-sm btn-primary\" data-target=\"#militar\" title=\"Editar\" data-toggle=\"modal\" value=\""+data.id+"\"><i class=\"fa-regular fa-pen-to-square\"></i></button>";
				var excluir = "<button type=\"submit\" value=\"delete\" style='margin-left:10px;' class='btn btn-sm btn-danger' title=\"Excluir\" onclick=\"return (confirm('Estao operacao não poderá ser desfeita, deseja continuar?'))\"><i class=\"fa fa-trash\"></i></button>";
				return editar+excluir;
			}
		}
		],
		dom: 'Bfrtip',
		buttons: [
		{
			extend: 'pdfHtml5',
			filename: 'Listagem de Militares',
			exportOptions: {columns: [ 0, 1, 2, 3, 4, 5 ]},
			alignment: 'center',
			text:'<i class="fa-regular fa-file-pdf"></i>',
			titleAttr: 'PDF',
			customize: function(doc) {
				doc.content[1].table.widths = 
				Array(doc.content[1].table.body[0].length + 1).join('*').split('');
				doc.defaultStyle.fontSize = 11;
			} 
		},
		{
			extend: 'excelHtml5',
			filename: 'Listagem de Militares',
			exportOptions: {columns: [ 0, 1, 2, 3, 4, 5 ]},
			text:'<i class="fa-regular fa-file-excel"></i>',
			titleAttr: 'EXCEL'
		}
		]
	});


	var table_us = $('.tbl_us').DataTable({
		pageLength:20,
		ordering: false,
		orderCellsTop: true,
		Processing: true,
		ServerSide: true,
		language: {
			lengthMenu: "Mostrar _MENU_ registros por página",
			zeroRecords: "Nenhum Registro Encontrado",
			info: "Mostrando _PAGE_ de _PAGES_ páginas",
			infoEmpty: "Nenhum Registro Encontrado",
			infoFiltered: "(Filtrado de _MAX_ registros totais)",
			loadingRecords: "Carregando ...",
			processing: "Processando ...",
			search: "Pesquisar",
			paginate:{
				first: "Primeira",
				last : "Última",
				next : "Próxima",
				previous: "Voltar"
			}
		},
		ajax: {
			url: 'controllers/usuarios.php',
			data: {opt: 'select'},
			type: 'POST',
			dataSrc: 'data'
		},
		columns: [
		{ data: 'nome_us'},
		{ data: 'usuario_us'},
		{ data: 'om_nome'},
		{ data: 'tipo_us'},
		{
			data: null,
			sortable: false,
			render:function(data) {
				var editar = "<button value=\"edit\" class=\"btn btn-sm btn-primary\" data-target=\"#usuario\" title=\"Editar\" data-toggle=\"modal\" value=\""+data.id+"\"><i class=\"fa-regular fa-pen-to-square\"></i></button>";
				var excluir = "<button value=\"delete\" style='margin-left:10px;' class='btn btn-sm btn-danger' title=\"Excluir\" onclick=\"return (confirm('Estao operacao não poderá ser desfeita, deseja continuar?'))\"><i class=\"fa fa-trash\"></i></button>";
				return editar+excluir;
			}
		}
		]
	});

	var table_org = $('.tbl_org').DataTable({
		pageLength:20,
		ordering: false,
		orderCellsTop: true,
		Processing: true,
		ServerSide: true,
		language: {
			lengthMenu: "Mostrar _MENU_ registros por página",
			zeroRecords: "Nenhum Registro Encontrado",
			info: "Mostrando _PAGE_ de _PAGES_ páginas",
			infoEmpty: "Nenhum Registro Encontrado",
			infoFiltered: "(Filtrado de _MAX_ registros totais)",
			loadingRecords: "Carregando ...",
			processing: "Processando ...",
			search: "Pesquisar",
			paginate:{
				first: "Primeira",
				last : "Última",
				next : "Próxima",
				previous: "Voltar"
			}
		},
		ajax: {
			url: 'controllers/organizacao.php',
			data: {opt: 'select'},
			type: 'POST',
			dataSrc: 'data'
		},
		columns: [
		{ data: 'sigla_org'},
		{ data: 'desc_org'},
		{
			data: null,
			sortable: false,
			render:function(data) {
				var editar = "<button value=\"edit\" class=\"btn btn-sm btn-primary\" data-target=\"#organizacao\" title=\"Editar\" data-toggle=\"modal\" value=\""+data.id+"\"><i class=\"fa-regular fa-pen-to-square\"></i></button>";
				var excluir = "<button value=\"delete\" style='margin-left:10px;' class='btn btn-sm btn-danger' title=\"Excluir\" onclick=\"return (confirm('Estao operacao não poderá ser desfeita, deseja continuar?'))\"><i class=\"fa fa-trash\"></i></button>";
				return editar+excluir;
			}
		}
		]
	});


	$('.tbl tbody').on('click', 'button', function(){
		var opt = $(this).attr("value");
		var obj = $('.tbl').DataTable().row($(this).closest('tr')).data();

		if(opt == 'edit'){
			$("#militarForm #id_mil").val(obj.id);
			$("#militarForm #nome_mil").val(obj.nome);
			$("#militarForm #nome_guerra").val(obj.nome_guerra);
			$("#militarForm #identidade").val(obj.ident_militar);
			$("#militarForm #opt").val(opt);
			$('#militarForm select[name=pg_militar]').val(obj.posto_id);
			$('#militarForm select[name=om_militar]').val(obj.om_id);
			$('#militarForm #ano_incorporacao').val(obj.ano_incorporacao || '');
			$('#btn-zerar-senha-app').show();
			$('.selectpicker').selectpicker('refresh');
		}

		if(opt == 'delete'){
			$.ajax({
				type: "POST",
				datatype : 'html',
				url: "controllers/militares.php",
				data: {id: obj.id, ident_militar: obj.ident_militar, opt:opt},
				success: function(data){
					if(data.error){
						avisar(data.error, 'erro');
					}else{
						avisar(data.msg, 'sucesso');
						table.ajax.reload();
					}
				}
			});
		}
	});

	$('.tbl_us tbody').on('click', 'button', function(){
		var opt = $(this).attr("value");
		var obj = $('.tbl_us').DataTable().row($(this).closest('tr')).data();

		if(opt == 'edit'){
			$("#usuarioForm #id_us").val(obj.id);
			$("#usuarioForm #nome_us").val(obj.nome_us);
			$("#usuarioForm #usuario_us").val(obj.usuario_us);
			$("#usuarioForm #tipo_us").val(obj.tipo_id);
			$("#usuarioForm #opt_us").val(opt);
			$('#usuarioForm select[name=om_us]').val(obj.om_id);
			$('.selectpicker').selectpicker('refresh');
		}

		if(opt == 'delete'){
			$.ajax({
				type: "POST",
				datatype : 'json',
				url: "controllers/usuarios.php",
				data: {id: obj.id, usuario: obj.usuario_us, opt:opt},
				success: function(data){
					if(data.error){
						avisar(data.error, 'erro');
					}else{
						avisar(data.msg, 'sucesso');
						table_us.ajax.reload();
					}
				}
			});
		}
	});

	$('.tbl_org tbody').on('click', 'button', function(){
		var opt = $(this).attr("value");
		var obj = $('.tbl_org').DataTable().row($(this).closest('tr')).data();

		if(opt == 'edit'){
			$("#organizacaoForm #id_org").val(obj.id);
			$("#organizacaoForm #sigla_org").val(obj.sigla_org);
			$("#organizacaoForm #desc_org").val(obj.desc_org);
			$("#organizacaoForm #opt_org").val(opt);
		}

		if(opt == 'delete'){
			$.ajax({
				type: "POST",
				datatype : 'json',
				url: "controllers/organizacao.php",
				data: {id: obj.id, sigla: obj.sigla_org, opt:opt},
				success: function(data){
					if(data.error){
						avisar(data.error, 'erro');
					}else{
						avisar(data.msg, 'sucesso');
						table_org.ajax.reload();
					}
				}
			});
		}
	});


	// Ano de incorporação: só para SD (a antiguidade no relatório vale só entre os soldados).
	var mostrarIncorporacao = function() {
		var ehSd = $.trim($('#pg_militar option:selected').text()) === 'SD';
		$('#grupo-incorporacao').toggle(ehSd);
		if (!ehSd) { $('#ano_incorporacao').val(''); }
	};
	$('#pg_militar').on('change', mostrarIncorporacao);
	$('#militar').on('shown.bs.modal', mostrarIncorporacao);

	$('#btn-zerar-senha-app').on('click', function() {
		var id = $('#militarForm #id_mil').val();
		if (!id || !window.confirm('Zerar a senha do app deste militar? Ele vai precisar criar outra no site dentro da OM.')) { return; }
		$.ajax({ type: 'POST', dataType: 'json', url: 'controllers/militares.php', data: { opt: 'zerar_senha_app', id: id } })
			.done(function(d) { avisar(d.error || d.msg, d.error ? 'erro' : 'sucesso'); })
			.fail(function() { avisar('Não foi possível zerar a senha. Tente de novo.', 'erro'); });
	});

	$('#add_militar').on('click', function() {
		$('#btn-zerar-senha-app').hide();
		$('#militarForm').get(0).reset();
		$('.selectpicker').selectpicker('refresh');
		$("#militarForm #opt").val("add");
	});

	$('#add_usuario').on('click', function() {
		$('#usuarioForm').get(0).reset();
		$('.selectpicker').selectpicker('refresh');
		$("#usuarioForm #opt_us").val("add");
	});

	$('#add_organizacao').on('click', function() {
		$('#organizacaoForm').get(0).reset();
		$('.selectpicker').selectpicker('refresh');
		$("#organizacaoForm #opt_org").val("add");
	});

	function stopPropagation(evt) {
		if (evt.stopPropagation !== undefined) {
			evt.stopPropagation();
		} else {
			evt.cancelBubble = true;
		}
	}

	$(document).on('show.bs.modal', '.modal', function (event) {
		var zIndex = 1040 + (10 * $('.modal:visible').length);
		$(this).css('z-index', zIndex);
		setTimeout(function() {
			$('.modal-backdrop').not('.modal-stack').css('z-index', zIndex - 1).addClass('modal-stack');
		}, 0);
	});

	$('#militarForm').validate({ 
		rules: {
			nome_mil: {
				required: true,
			},
			nome_guerra: {
				required: true,
			},
			identidade: {
				required: true,
				minlength: 5
			},
			pg_militar: {
				required: true,
			},
			om_militar: {
				required: true,
			}
		},
		errorElement: 'span',
		errorClass: 'help-block',
		errorPlacement: function(error, element) {
			if(element.length) {
				error.insertAfter(element);
			} else {
				error.insertAfter(element);
			}
		},
		submitHandler: function (form) {
			var formData = btoa($("#militarForm").serialize());
			$.ajax({
				type: "POST",
				datatype : 'json',
				url: "controllers/militares.php",
				data: {formData: formData},
				success: function(data){
					if(data.error){
						avisar(data.error, 'erro');
					}else{
						avisar(data.msg, 'sucesso');
						$('#militarForm').get(0).reset();
						$('#militar').modal('hide');
						table.ajax.reload();
					}
				}
			});
			return false;
		}
	});

	$('#usuarioForm').validate({ 
		rules: {
			nome_us: {
				required: true,
			},
			usuario_us: {
				required: true,
			},
			identidade: {
				required: true,
			},
			om_us: {
				required: true,
			},
			tipo_us: {
				required: true,
			}
		},
		errorElement: 'span',
		errorClass: 'help-block',
		errorPlacement: function(error, element) {
			if(element.length) {
				error.insertAfter(element);
			} else {
				error.insertAfter(element);
			}
		},
		submitHandler: function (form) {
			var formData = btoa($("#usuarioForm").serialize());
			$.ajax({
				type: "POST",
				datatype : 'json',
				url: "controllers/usuarios.php",
				data: {formData: formData},
				success: function(data){
					if(data.error){
						avisar(data.error, 'erro');
					}else{
						avisar(data.msg, 'sucesso');
						$('#usuarioForm').get(0).reset();
						$('#usuario').modal('hide');
						table_us.ajax.reload();
					}
				}
			});
			return false;
		}
	});

	$('#senhaForm').validate({ 
		rules: {
			senha_atual: {
				required: true,
			},
			senha_nova: {
				required: true,
			}
		},
		errorElement: 'span',
		errorClass: 'help-block',
		errorPlacement: function(error, element) {
			if(element.length) {
				error.insertAfter(element);
			} else {
				error.insertAfter(element);
			}
		},
		submitHandler: function (form) {
			var form = btoa($("#senhaForm").serialize());
			$.ajax({
				type: "POST",
				datatype : 'json',
				url: "controllers/usuarios.php",
				data: {form: form},
				success: function(data){
					if(data.error){
						avisar(data.error, 'erro');
					}else{
						avisar(data.msg, 'sucesso');
						window.location.href  = "home.php";
					}
				}
			});
			return false;
		}
	});

	$('#organizacaoForm').validate({ 
		rules: {
			sigla_org: {
				required: true,
			},
			desc_org: {
				required: true,
			}
		},
		errorElement: 'span',
		errorClass: 'help-block',
		errorPlacement: function(error, element) {
			if(element.length) {
				error.insertAfter(element);
			} else {
				error.insertAfter(element);
			}
		},
		submitHandler: function (form) {
			var formData = btoa($("#organizacaoForm").serialize());
			$.ajax({
				type: "POST",
				datatype : 'json',
				url: "controllers/organizacao.php",
				data: {formData: formData},
				success: function(data){
					if(data.error){
						avisar(data.error, 'erro');
					}else{
						avisar(data.msg, 'sucesso');
						$('#organizacaoForm').get(0).reset();
						$('#organizacao').modal('hide');
						table_org.ajax.reload();
					}
				}
			});
			return false;
		}
	});
});
