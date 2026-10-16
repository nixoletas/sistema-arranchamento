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
    endDate: '+6d',
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
				window.alert(data.msg)
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

	$('#btnGerar-ard').on('click', function() {	
		var dt = $('#data_ard').val();
		var om = $('#om_ard').val();
		var posto = $('#pg_ard').val();
		var req = new XMLHttpRequest();
		var refeicao = [];
		$("input:radio[class=refeicao-rel]:checked").each(function(){
			refeicao.push(this.value);
		});

		$('#gerar-relatorio').modal('hide');
		$("div#divLoading").addClass('show');

		if(dt && om && posto){
			var url = "../rancho/relatorios/arranchados.php?om="+encodeURIComponent(om)+"&ref="+encodeURIComponent(refeicao)+"&dt="+encodeURIComponent(dt)+"&posto="+encodeURIComponent(posto);
		}
		if ($('#outrasOm').is(":checked")){
			var url = "../rancho/relatorios/arranchados-outra-om.php?dt="+encodeURIComponent(dt);
		}

		if(url){
			req.open("GET", url , true);
			req.responseType = "blob";
			req.onreadystatechange = function () {
				if (req.readyState === 4 && req.status === 200) {
					$("div#divLoading").removeClass('show');
					$('#gerar-relatorio').modal('show');
					var filename = "relatorio-arranchados-" + new Date().getTime() + ".pdf";
					if (typeof window.chrome !== 'undefined') {
							//CHORME
							var link = document.createElement('a');
							link.href = window.URL.createObjectURL(req.response);
							link.download = "relatorio-arranchados-" + new Date().getTime() + ".pdf";
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
			window.alert('Selecione pelo menos um militar!');
			return false;
		}
		if (!dados.dias) {
			window.alert('Selecione pelo menos uma data!');
			return false;
		}
		if (dados.refeicao.length === 0) {
			window.alert('Selecione pelo menos uma refeição!');
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
					window.alert(data.error);
				}
				if(data.success){
					$("div#divLoading").removeClass('show');
					window.alert(data.success)
					window.location.href  = "home.php";
				}
			},
			error: function(){
				$("div#divLoading").removeClass('show');
				window.alert('Não foi possível concluir. Tente novamente.');
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
					window.alert(data.error);
				}
				if(data.success){
					$("div#divLoading").removeClass('show');
					window.alert(data.success)
					window.location.href  = "home.php";
				}
			},
			error: function(){
				$("div#divLoading").removeClass('show');
				window.alert('Não foi possível concluir. Tente novamente.');
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
						window.alert(data.error);
					}
					if(data.success){
						$("div#divLoading").removeClass('show');
						window.alert(data.success)
						window.location.href  = "home.php";
					}
				}
			});
		}else{
			alert("Preencha todos campos!");
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
			alert("Preencha todos campos!");
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
		{
			data: null,
			sortable: false,
			render:function(data) {
				var editar = "<button value=\"edit\" class=\"btn btn-sm btn-primary\" data-target=\"#militar\" title=\"Editar\" data-toggle=\"modal\" value=\""+data.id+"\"><i class=\"fa fa-pencil-square-o\"></i></button>";
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
			text:'<i class="fa fa-file-pdf-o"></i>',
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
			text:'<i class="fa fa-file-excel-o"></i>',
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
				var editar = "<button value=\"edit\" class=\"btn btn-sm btn-primary\" data-target=\"#usuario\" title=\"Editar\" data-toggle=\"modal\" value=\""+data.id+"\"><i class=\"fa fa-pencil-square-o\"></i></button>";
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
				var editar = "<button value=\"edit\" class=\"btn btn-sm btn-primary\" data-target=\"#organizacao\" title=\"Editar\" data-toggle=\"modal\" value=\""+data.id+"\"><i class=\"fa fa-pencil-square-o\"></i></button>";
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
						alert(data.error);
					}else{
						alert(data.msg);
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
						alert(data.error);
					}else{
						alert(data.msg);
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
						alert(data.error);
					}else{
						alert(data.msg);
						table_org.ajax.reload();
					}
				}
			});
		}
	});


	$('#add_militar').on('click', function() {
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
						alert(data.error);
					}else{
						alert(data.msg);
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
						alert(data.error);
					}else{
						alert(data.msg);
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
						alert(data.error);
					}else{
						alert(data.msg);
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
						alert(data.error);
					}else{
						alert(data.msg);
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
