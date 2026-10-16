$(function() {
    const pg = $("p.pg").attr('id');
    const om = $("p.om").attr('id');
    const ident = $("p.ident").attr('id');

    $(".maskIdentidade").mask("000.000.000-00");

    const mostrarCarregando = function() {
        $('#loading-overlay').css('display', 'flex');
    };
    const esconderCarregando = function() {
        $('#loading-overlay').css('display', 'none');
    };

    // Espelha a regra do servidor (controllers/registros.php) só para dar feedback visual.
    // A regra de verdade continua sendo validada no servidor.
    const desarranchamentoPermitido = function(dataStr) {
        const data = new Date(dataStr + 'T00:00:00');
        const diaSemana = data.getDay(); // 0=Dom ... 6=Sab

        const hoje = new Date();
        hoje.setHours(0, 0, 0, 0);

        // Sábado(6), Domingo(0) e Segunda(1) só podem ser desfeitos até a sexta-feira anterior
        const diasAteSextaAnterior = { 6: 1, 0: 2, 1: 3 };

        if (diasAteSextaAnterior.hasOwnProperty(diaSemana)) {
            const limite = new Date(data);
            limite.setDate(limite.getDate() - diasAteSextaAnterior[diaSemana]);
            return hoje <= limite;
        }

        const diffDias = Math.round((data - hoje) / 86400000);
        return diffDias >= 2;
    };

    const verificaDias = function() {
        return new Promise((resolve, reject) => {
            $.ajax({
                type: "POST",
                url: "controllers/consulta.php",
                dataType: 'json',
                success: function(data) {
                    if (data.error) {
                        reject(data.error);
                    } else {
                        resolve(data);
                    }
                },
                error: function(xhr, status, error) {
                    reject(error);
                }
            });
        });
    };

    const VerificaBloqueios = function() {
        let datesForbbiden, refForbbiden, motForbbidden;
        $.ajax({
            async: false,
            type: "POST",
            url: "controllers/bloqueios.php",
            dataType: 'json',
            data: { pg: pg, om: om },
            success: function(data) {
                datesForbbiden = data.dias;
                refForbbiden = data.refeicao;
                motForbbidden = data.motivo;
            }
        });
        return [datesForbbiden, refForbbiden, motForbbidden];
    };

    const bloqueios = VerificaBloqueios();
    let cafe_bloq_dias = "", almoco_bloq_dias = "", janta_bloq_dias = "";
    const bloq_ref = bloqueios[1];

    if ($.inArray("1", bloq_ref) > -1) {
        cafe_bloq_dias = bloqueios[0];
        $('.box-motivo-bloqueio-cafe').html(bloqueios[2]);
    }
    if ($.inArray("2", bloq_ref) > -1) {
        almoco_bloq_dias = bloqueios[0];
        $('.box-motivo-bloqueio-almoco').html(bloqueios[2]);
    }
    if ($.inArray("3", bloq_ref) > -1) {
        janta_bloq_dias = bloqueios[0];
        $('.box-motivo-bloqueio-janta').html(bloqueios[2]);
    }

    $('#login-modal').on('shown.bs.modal', function() {
        $('.maskIdentidade').focus();
    });

    $('#identidade').keypress(function(event) {
        if (event.which == 13) {
            $("#form-login").submit();
        }
    });

    $('#form-login').validate({
        rules: {
            identidade: {
                required: true,
                minlength: 5
            }
        },
        errorElement: 'span',
        errorClass: 'help-block',
        errorPlacement: function(error, element) {
            error.insertAfter(element);
        },
        submitHandler: function(form) {
            const $i = $('#identidade').val();
            $.ajax({
                type: "POST",
                dataType: "json",
                url: "controllers/autentica.php",
                data: { identidade: $i },
                success: function(data) {
                    if (data.erro) {
                        $('#box-login').html(data.erro);
                    } else {
                        window.location = data.url;
                    }
                }
            });
            return false;
        }
    });

    async function init() {
        try {
            const diasArranchado = await verificaDias();
            processDiasArranchado(diasArranchado);
			console.log(diasArranchado);
        } catch (error) {
            console.error("Erro ao verificar dias:", error);
        }
    }

    function calcularResumoSemana(diasArranchado) {
        const hoje = new Date();
        hoje.setHours(0, 0, 0, 0);

        const diaSemanaHoje = hoje.getDay(); // 0=Dom ... 6=Sab
        const offsetSegunda = diaSemanaHoje === 0 ? 6 : diaSemanaHoje - 1;
        const segunda = new Date(hoje);
        segunda.setDate(segunda.getDate() - offsetSegunda);
        const domingo = new Date(segunda);
        domingo.setDate(domingo.getDate() + 6);

        let total = 0;
        $.each(diasArranchado, function(_, item) {
            const dias = item.date.split(',');
            $.each(dias, function(_, d) {
                if (!d) return;
                const data = new Date(d + 'T00:00:00');
                if (data >= segunda && data <= domingo) {
                    total++;
                }
            });
        });

        $('#resumo-semana-total').text(total);
    }

    function processDiasArranchado(diasArranchado) {
        let DiasCafe = [], DiasAlmoco = [], DiasJanta = [];

        $.each(diasArranchado, function() {
            if (this.tipo == "1") {
                DiasCafe = this.date.split(',');
            }
            if (this.tipo == "2") {
                DiasAlmoco = this.date.split(',');
            }
            if (this.tipo == "3") {
                DiasJanta = this.date.split(',');
            }
        });

        calcularResumoSemana(diasArranchado);

        const setupDatepicker = function(selector, datesDisabled, Dias) {
            let requestEmAndamento = false;

            $(selector).datepicker({
                datesDisabled: datesDisabled,
                format: "dd/mm/yyyy",
                language: "pt-BR",
                daysOfWeekHighlighted: [0, 6],
                orientation: "top auto",
                startDate: '+2d',
                todayHighlight: true,
                endDate: '+14d',
                beforeShowDay: function(date) {
                    const m = ("0" + (date.getMonth() + 1)).slice(-2),
                        d = ("0" + date.getDate()).slice(-2),
                        y = date.getFullYear();
                    const dateFormat = y + "-" + m + "-" + d;
                    if (Array.isArray(Dias) && Dias.includes(dateFormat)) {
                        if (desarranchamentoPermitido(dateFormat)) {
                            return {
                                tooltip: 'Arranchado — clique para desarranchar',
                                classes: 'active'
                            };
                        }
                        return {
                            tooltip: 'Arranchado — prazo para cancelar já encerrou',
                            classes: 'arranchado-bloqueado'
                        };
                    }
                    return {};
                }
            }).on('changeDate', function(e) {
                if (requestEmAndamento) {
                    return;
                }

                const dataClicada = e.format(0, "yyyy-mm-dd");
                const vaiDesarranchar = Array.isArray(Dias) && Dias.includes(dataClicada);

                if (vaiDesarranchar) {
                    if (!desarranchamentoPermitido(dataClicada)) {
                        window.alert('Não é mais possível desarranchar esse dia — o prazo já passou.');
                        return;
                    }
                    const dataFormatada = e.format(0, "dd/mm/yyyy");
                    if (!window.confirm('Tem certeza que deseja desarranchar o dia ' + dataFormatada + '?')) {
                        return;
                    }
                }

                requestEmAndamento = true;
                mostrarCarregando();

                const refeicaoNome = selector === '#cafe-container' ? 'cafe' : selector === '#almoco-container' ? 'almoco' : 'jantar';
                const tipoRefeicao = selector === '#cafe-container' ? '1' : selector === '#almoco-container' ? '2' : '3';
                const dadosEnvio = { data: dataClicada, tipo: tipoRefeicao };

                $.ajax({
                    type: "POST",
                    dataType: "json",
                    url: "controllers/registros.php",
                    data: dadosEnvio,
                    success: function(data) {
                        if (data.error) {
                            esconderCarregando();
                            window.alert(data.error);
                        }
                        // Recarrega para refletir o estado real do servidor
                        window.location.href = `index.php?pasta=home&pagina=inicio&refeicao=${refeicaoNome}`;
                    },
                    error: function() {
                        // Não tenta de novo sozinho: como a ação alterna (arranchar/desarranchar),
                        // repetir automaticamente poderia desfazer uma gravação que na verdade deu certo.
                        // Recarrega para mostrar o estado real e o usuário decide se precisa clicar de novo.
                        esconderCarregando();
                        window.alert('Não foi possível confirmar se salvou. Verificando o estado atual...');
                        window.location.href = `index.php?pasta=home&pagina=inicio&refeicao=${refeicaoNome}`;
                    }
                });
            });
        };

        setupDatepicker('#cafe-container', cafe_bloq_dias, DiasCafe);
        setupDatepicker('#almoco-container', almoco_bloq_dias, DiasAlmoco);
        setupDatepicker('#janta-container', janta_bloq_dias, DiasJanta);

        const atualizarListaDiasArranchados = function() {
            const lista = $('#dias-arranchado-list');
            lista.empty();

            let diasFuturos = [];
            let diasPassados = [];
            const hoje = new Date();

            $.each(diasArranchado, function(index, item) {
                let refeicao = '';
                if (item.tipo == "1") refeicao = "café da manhã";
                if (item.tipo == "2") refeicao = "almoço";
                if (item.tipo == "3") refeicao = "jantar";

                const dias = item.date.split(',');
                $.each(dias, function(i, dia) {
                    // Fim do dia no horário local do navegador (o dia de hoje conta como futuro).
                    // Não usar new Date(dia): "AAAA-MM-DD" é lido como meia-noite UTC e, no fuso de
                    // Itaituba (UTC-3), o ajuste antigo de +27h59 jogava a data para o dia seguinte.
                    const data = new Date(dia + 'T23:59:59');

                    if (data >= hoje) {
                        diasFuturos.push({
                            data: data,
                            refeicao: refeicao
                        });
                    } else {
                        diasPassados.push({
                            data: data,
                            refeicao: refeicao
                        });
                    }
                });
            });

            diasFuturos = ordenarPorDataERefeicao(diasFuturos);
            diasPassados = ordenarDaFrenteParaTras(diasPassados);

            const diasOrdenados = diasFuturos.concat(diasPassados);

            $.each(diasOrdenados, function(i, item) {
                const diaTexto = item.data.toLocaleDateString('pt-BR');
                const classe = item.data >= hoje ? '' : 'disabled';
                const iconeHoje = item.data.toDateString() === hoje.toDateString() ? '<i class="fa-solid fa-calendar-week"></i> ' : '';
                lista.append('<li class="list-group-item ' + classe + '"><strong>' + diaTexto + '</strong>' + ' <i class="fa-solid fa-arrow-right"></i> ' + iconeHoje + item.refeicao.toUpperCase() + '</li>');
            });
        };

        atualizarListaDiasArranchados();
    }

    // Chame a função init para iniciar o processo
    init();

    function toggleChevron(e) {
        $(e.target)
            .prev('.panel-heading')
            .find("i.indicator")
            .toggleClass('fa-arrow-circle-up fa-arrow-circle-down');
    }
    $('.accordion').on('hidden.bs.collapse', toggleChevron);
    $('.accordion').on('shown.bs.collapse', toggleChevron);

    const remoto_avisos = function(url) {
        const spinner = "<div class='text-center'><i class='fa fa-spinner fa-spin fa-3x fa-fw'></i></div>";
        $("#avisos .modal-body").html(spinner);
        $("#avisos .modal-body").load(url);
        $("#avisos").modal("show");
    };

    $('.avisos').click(function() {
        remoto_avisos('controllers/avisos.php');
    });

    const ordenarPorDataERefeicao = function(dias) {
        return dias.sort(function(a, b) {
            if (a.data < b.data) return -1;
            if (a.data > b.data) return 1;
            const ordemRefeicao = ["café da manhã", "almoço", "jantar"];
            return ordemRefeicao.indexOf(a.refeicao) - ordemRefeicao.indexOf(b.refeicao);
        });
    };

    const ordenarDaFrenteParaTras = function(dias) {
        return dias.sort(function(a, b) {
            if (a.data < b.data) return 1;
            if (a.data > b.data) return -1;
            const ordemRefeicao = ["café da manhã", "almoço", "jantar"];
            return ordemRefeicao.indexOf(a.refeicao) - ordemRefeicao.indexOf(b.refeicao);
        });
    };
});
