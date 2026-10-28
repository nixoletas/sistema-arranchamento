<?php
// Regras do arranchamento usadas pelo militar (tela "Arranchar por semana", calendários) e pelo painel
// (copiar semana). Um só lugar para a regra de prazo e para os bloqueios ("Bloquear dias").

// Janela em que o próprio militar pode se arranchar: de 2 a 14 dias à frente (a mesma dos calendários).
const ARRANCHAR_DIAS_MIN = 2;
const ARRANCHAR_DIAS_MAX = 14;

// Último dia para desarranchar uma refeição da data informada (Y-m-d):
// sábado, domingo e segunda só até a sexta-feira anterior; os demais dias até 2 dias antes.
function regra_prazo_cancelar($data)
{
	$diaSemana = (int)date('N', strtotime($data)); // 1=Segunda ... 6=Sábado, 7=Domingo
	$diasAteSextaAnterior = [6 => 1, 7 => 2, 1 => 3];
	$recuo = $diasAteSextaAnterior[$diaSemana] ?? 2;
	return date('Y-m-d', strtotime($data . ' -' . $recuo . ' days'));
}

// true se ainda dá para desarranchar; senão, a mensagem explicando o motivo.
function regra_pode_cancelar($data)
{
	if (date('Y-m-d') <= regra_prazo_cancelar($data)) {
		return true;
	}
	$diaSemana = (int)date('N', strtotime($data));
	if (in_array($diaSemana, [6, 7, 1], true)) {
		return 'Refeições de sábado, domingo e segunda só podem ser desarranchadas até sexta-feira!';
	}
	return 'Só é possível desarranchar até 2 dias antes da refeição!';
}

// Converte uma data "dd/mm/aaaa" ou "aaaa-mm-dd" para "aaaa-mm-dd" (ou null se não for data válida).
function regra_data_iso($texto)
{
	$texto = trim((string)$texto);
	foreach (['!d/m/Y', '!Y-m-d'] as $formato) {
		$d = DateTime::createFromFormat($formato, $texto);
		if ($d && $d->format(substr($formato, 1)) === $texto) {
			return $d->format('Y-m-d');
		}
	}
	return null;
}

// Bloqueios ("Bloquear dias") que valem para um militar da Cia $om e P/G $posto.
// Considera todas as regras cadastradas (não só a primeira). Retorna [aaaa-mm-dd][refeição 1..3] => motivo.
function regra_bloqueios($cnt, $om, $posto)
{
	$bloqueios = [];
	$con = $cnt->query("SELECT bloq_cia, bloq_pg, bloq_ref, bloq_dias, bloq_mot FROM config");
	while ($regra = $con->fetch(PDO::FETCH_ASSOC)) {
		$cias = array_map('trim', explode(',', $regra['bloq_cia']));
		$pgs  = array_map('trim', explode(',', $regra['bloq_pg']));
		$valeCia = in_array((string)$om, $cias, true) || in_array('100', $cias, true);
		$valePg  = in_array((string)$posto, $pgs, true) || in_array('100', $pgs, true);
		if (!$valeCia || !$valePg) {
			continue;
		}
		$refeicoes = array_intersect(['1', '2', '3'], array_map('trim', explode(',', $regra['bloq_ref'])));
		foreach (explode(',', $regra['bloq_dias']) as $dia) {
			$iso = regra_data_iso($dia);
			if (!$iso) {
				continue;
			}
			foreach ($refeicoes as $ref) {
				$bloqueios[$iso][(int)$ref] = $regra['bloq_mot'];
			}
		}
	}
	return $bloqueios;
}
