<?php
error_reporting(0);
require_once("../../controllers/config/conexao.php");
session_start();

if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
	echo "Exception: #".strtoupper( substr( md5(rand()), 0, 20));;
	die();
}

// Criar e apagar regras de "Bloquear dias" é restrito ao Administrador (mesma regra do card em rancho/home.php).
if (empty($_SESSION['usuario']['id']) || (int)($_SESSION['usuario']['tipo'] ?? 0) !== 1) {
	http_response_code(401);
	echo '{"error":"Acesso negado. Faça login novamente."}';
	die();
}

$om = "";
$pg = "";
$ref = "";
$dias = "";
$motivo = "";

switch ($_GET['opt']) {
	case 'add':

	if($_POST['om_bloq']){

		$tmp = $_POST['om_bloq'];
		foreach ($tmp as $key){ 
			$om.= $key.",";  
		}

		$om = substr($om,0,strlen($om) - 1);

	}

	if($_POST['pg_bloq']){

		$tmp = $_POST['pg_bloq'];
		foreach ($tmp as $key){ $pg.= $key.",";}
		$pg = substr($pg,0,strlen($pg) - 1);

	}

	if($_POST['ref_bloq']){

		$tmp = $_POST['ref_bloq'];
		foreach ($tmp as $key){ $ref.= $key.",";}
		$ref = substr($ref,0,strlen($ref) - 1);
	}

	if($_POST['dias_bloq']){

		$dias = $_POST['dias_bloq'];

	}

	if($_POST['bloq_mot']){

		$motivo = $_POST['bloq_mot'];

	}

	$stmt = $cnt->prepare("INSERT INTO config (bloq_cia, bloq_dias, bloq_ref, bloq_mot, bloq_pg) VALUES (:om, :dias, :ref, :mot, :pg);");
	$stmt->bindValue(':om', $om, PDO::PARAM_STR);
	$stmt->bindValue(':dias', $dias, PDO::PARAM_STR);
	$stmt->bindValue(':ref', $ref, PDO::PARAM_STR);
	$stmt->bindValue(':mot', $motivo, PDO::PARAM_STR);
	$stmt->bindValue(':pg', $pg, PDO::PARAM_STR);
	$stmt->execute();

	if ($stmt) {
		echo '{"msg":"<span class=\"text-success\">Regra Criada com sucesso <i class=\"fa fa-exclamation\"></i></span>"}';
	} 

	if (!$stmt) {
		echo '{"error":"<span class=\"text-danger\">Não foi possivel criar a Regra <i class=\"fa fa-times\"></i></span>"}';
	} 
	break;
	
	case 'del':
	if(isset($_POST['idRegra']) && is_numeric($_POST['idRegra'])){

		$idRegra = $_POST['idRegra'];
		$stmt = $cnt->prepare("DELETE FROM config WHERE id = :idRegra");
		$stmt->bindValue(':idRegra', $idRegra, PDO::PARAM_INT);
		$stmt->execute();

		if ($stmt) {
			echo '{"msg":"<span class=\"text-success\">Regra Excluida com sucesso <i class=\"fa fa-exclamation\"></i></span>"}';
		} 

		if (!$stmt) {
			echo '{"msg":"<span class=\"text-danger\">Não foi possivel excluir a Regra <i class=\"fa fa-times\"></i></span>"}';
		}
	}
	break;

	default:
	echo '{"msg":"<span class=\"text-danger\">Não foi possível processar a Regra <i class=\"fa fa-times\"></i></span>"}';
	break;
}
?>
