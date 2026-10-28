<?php
error_reporting(0);
date_default_timezone_set("America/Santarem");
session_start();

require_once "controllers/config/conexao.php";

if(!empty($_SESSION['auth']['usuario_logado']['id']) && is_numeric($_SESSION['auth']['usuario_logado']['id']) && $_SESSION['auth']['usuario_logado']['id'] != 0){

	$con = $cnt->prepare('SELECT u.*,
		c.id AS companhia_id,
		c.nome AS companhia_nome,
		c.descricao AS companhia_desc,
		p.nome as posto_desc,
		p.id as posto_id
		FROM cad_militar u
		JOIN cad_cia c ON c.id = u.om_id
		JOIN cad_posto p ON p.id = u.posto
		WHERE u.id = :usuario_logado
		ORDER BY u.id DESC
		LIMIT 1;');
	$con->bindValue(':usuario_logado', $_SESSION['auth']['usuario_logado']['id'], PDO::PARAM_INT);
	$con->execute();
	$total = $con->rowCount();

	if($total){ 
		$usuario_logado = $con->fetch(PDO::FETCH_ASSOC);
	}
	
	if(!$total){
		unset($usuario_logado);
	}

	if(($_SERVER['QUERY_STRING'] ?? '') == ""){
		header("Location: index.php?pasta=home&pagina=inicio");
	}
}

require_once "controllers/app.php";
$App = new App();


?>
