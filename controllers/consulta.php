<?php
session_start();
require_once("config/conexao.php");
include "../rancho/controllers/auditoria.php";

$identidade = $_SESSION['auth']['usuario_logado']['ident_militar'];

//captura data atual
$newDate =  isset($_POST['data']) ? $_POST['data'] : null;
//captura tipo de arranchamento (cafe, almoco, janta)
$tipo =  isset($_POST['tipo']) ? $_POST['tipo'] : null;

function desarranchamentoPermitido($newDate)
{
	$diaSemana = (int) date('N', strtotime($newDate)); // 1=Segunda ... 6=Sábado, 7=Domingo

	// Sábado, Domingo e Segunda só podem ser desfeitos até a sexta-feira anterior
	$diasAteSextaAnterior = [6 => 1, 7 => 2, 1 => 3];

	if (isset($diasAteSextaAnterior[$diaSemana])) {
		$limite = date('Y-m-d', strtotime($newDate . ' -' . $diasAteSextaAnterior[$diaSemana] . ' days'));
		if (date('Y-m-d') > $limite) {
			return 'Refeições de sábado, domingo e segunda só podem ser desarranchadas até sexta-feira!';
		}
		return true;
	}

	$diasDeAntecedencia = (strtotime($newDate) - strtotime(date('Y-m-d'))) / 86400;
	if ($diasDeAntecedencia < 2) {
		return 'Só é possível desarranchar até 2 dias antes da refeição!';
	}

	return true;
}

if($newDate && $tipo){

	if(!$identidade){
		http_response_code(401);
		echo '{"error":"Sessão expirada, faça login novamente."}';
		die();
	}

	if($tipo != 1 && $tipo != 2 && $tipo != 3){
		echo '{"error":"Refeição não cadastrada!"}';
		die();
	}

	if($newDate <= date('Y-m-d')){
		echo '{"error":"Data não autorizada!"}';
		die();
	}

	$con = $cnt->prepare("SELECT id,data,tp FROM registros WHERE ident_militar = :identidade AND tp = :tipo AND data = :newDate");
	$con->bindValue(':identidade', $identidade, PDO::PARAM_STR);
	$con->bindValue(':tipo', $tipo, PDO::PARAM_INT);
	$con->bindValue(':newDate', $newDate, PDO::PARAM_STR);
	$con->execute();

	$res = $con->fetch(PDO::FETCH_ASSOC);
	$total = $con->rowCount();

	if($total){
		if($res['data'] == $newDate){

			$permitido = desarranchamentoPermitido($newDate);
			if($permitido !== true){
				echo json_encode(['error' => $permitido]);
				die();
			}

			$con = $cnt->prepare("DELETE FROM registros WHERE ident_militar = :identidade AND tp = :tipo AND data = :newDate");
			$con->bindValue(':identidade', $identidade, PDO::PARAM_STR);
			$con->bindValue(':tipo', $tipo, PDO::PARAM_INT);
			$con->bindValue(':newDate', $newDate, PDO::PARAM_STR);
			$con->execute();
			if($con){
				$registro = $res['id'];
				auditoria("DESARANCHAR", $registro, $cnt, $_SESSION['auth']['usuario_logado']['ident_militar']);
			}
			echo '{"msg":"Arranchamento Desfeito!"}';
			die();
		}
	}

	$con = $cnt->prepare("INSERT INTO registros (data, tp, ident_militar) VALUES (:newDate, :tipo, :identidade)");
	$con->bindValue(':identidade', $identidade, PDO::PARAM_STR);
	$con->bindValue(':tipo', $tipo, PDO::PARAM_INT);
	$con->bindValue(':newDate', $newDate, PDO::PARAM_STR);
	$con->execute();
	if($con){
		$registro = $cnt->lastInsertId();
		auditoria("ARRANCHAR", $registro, $cnt, $_SESSION['auth']['usuario_logado']['ident_militar']);
	}
	echo '{"msg":"Arranchamento Registrado!"}';
	die();
}

if($identidade){
	$con = $cnt->prepare("SELECT tp, GROUP_CONCAT(data ORDER BY data DESC) as data FROM registros WHERE ident_militar = :identidade GROUP BY tp ORDER BY MAX(id) DESC;");
	$con->bindValue(':identidade', $identidade, PDO::PARAM_STR);
	$con->execute();

	$reg = [];

	while($res = $con->fetch(PDO::FETCH_ASSOC)){
		$reg[] = [
			'date' => $res['data'],
			'tipo' => $res['tp']
		];
	}

	echo json_encode($reg);
}
?>
