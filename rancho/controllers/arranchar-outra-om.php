<?php

error_reporting(0);
session_start();

include "../../controllers/config/conexao.php";

if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
	echo "Exception: #".strtoupper( substr( md5(rand()), 0, 20));;
	die();
}

// Arranchar (Outra OM) é restrito ao Administrador (mesma regra do card, hoje desativado, em rancho/home.php).
if (empty($_SESSION['usuario']['id']) || (int)($_SESSION['usuario']['tipo'] ?? 0) !== 1) {
	http_response_code(401);
	echo '{"error":"Acesso negado. Faça login novamente."}';
	die();
}

$refeicao = isset($_POST['refeicao']) ? $_POST['refeicao'] : null;
$identidade = isset($_POST['identidade']) ? $_POST['identidade'] : null;
$data = isset($_POST['data']) ? $_POST['data'] : null;
$om = isset($_POST['om']) ? $_POST['om'] : null;
$nome = isset($_POST['nome']) ? $_POST['nome'] : null;
$posto = isset($_POST['posto']) ? $_POST['posto'] : null;

if(!$identidade){
	echo '{"error":"Insira alguma identidade!"}';
	die();
}

if(!$posto){
	echo '{"error":"Insira algum P/G!"}';
	die();
}

if(!$data){
	echo '{"error":"Selecione alguma data!"}';
	die();
}

if(!$om){
	echo '{"error":"Digite alguma OM!"}';
	die();
}

if(!$refeicao){
	echo '{"error":"Selecione alguma refeicao!"}';
	die();
}

$dt = explode(',', $_POST['data']);

if(in_array(1, $refeicao)){

	foreach ($dt as $data) {

		if($data > date('Y-m-d')){
			$con = $cnt->prepare("SELECT data,tpRef FROM registros_outros WHERE ident_militar = :identidade AND tpRef = :refeicao AND data = :data");
			$con->bindValue(':refeicao', 1, PDO::PARAM_INT);
			$con->bindValue(':data', $data, PDO::PARAM_STR);
			$con->bindValue(':identidade', $identidade, PDO::PARAM_STR);
			$con->execute();
			$total = $con->rowCount();

			if(!$total){
				$con = $cnt->prepare('INSERT INTO registros_outros (nome, ident_militar, om, posto, tpRef, data) VALUES (:nome, :identidade, :om, :posto, :refeicao, :data)');
				$con->bindValue(':nome', $nome, PDO::PARAM_STR);
				$con->bindValue(':identidade', $identidade, PDO::PARAM_STR);
				$con->bindValue(':om', $om, PDO::PARAM_STR);
				$con->bindValue(':posto', $posto, PDO::PARAM_STR);
				$con->bindValue(':refeicao', 1, PDO::PARAM_INT);
				$con->bindValue(':data', $data, PDO::PARAM_STR);
				$con->execute();
			}
		}
	}
}

if(in_array(2, $refeicao)){

	foreach ($dt as $data) {
		if($data > date('Y-m-d')){
			$con = $cnt->prepare("SELECT data,tpRef FROM registros_outros WHERE ident_militar = :identidade AND tpRef = :refeicao AND data = :data");
			$con->bindValue(':refeicao', 2, PDO::PARAM_INT);
			$con->bindValue(':data', $data, PDO::PARAM_STR);
			$con->bindValue(':identidade', $identidade, PDO::PARAM_STR);
			$con->execute();
			$total = $con->rowCount();

			if(!$total){
				$con = $cnt->prepare('INSERT INTO registros_outros (nome, ident_militar, om, posto, tpRef, data) VALUES (:nome, :identidade, :om, :posto, :refeicao, :data)');
				$con->bindValue(':nome', $nome, PDO::PARAM_STR);
				$con->bindValue(':identidade', $identidade, PDO::PARAM_STR);
				$con->bindValue(':om', $om, PDO::PARAM_STR);
				$con->bindValue(':posto', $posto, PDO::PARAM_STR);
				$con->bindValue(':refeicao', 2, PDO::PARAM_INT);
				$con->bindValue(':data', $data, PDO::PARAM_STR);
				$con->execute();
			}
		}
	}
}

if(in_array(3, $refeicao)){

	foreach ($dt as $data) {
		if($data > date('Y-m-d')){
			$con = $cnt->prepare("SELECT data,tpRef FROM registros_outros WHERE ident_militar = :identidade AND tpRef = :refeicao AND data = :data");
			$con->bindValue(':refeicao', 3, PDO::PARAM_INT);
			$con->bindValue(':data', $data, PDO::PARAM_STR);
			$con->bindValue(':identidade', $identidade, PDO::PARAM_STR);
			$con->execute();
			$total = $con->rowCount();

			if(!$total){
				$con = $cnt->prepare('INSERT INTO registros_outros (nome, ident_militar, om, posto, tpRef, data) VALUES (:nome, :identidade, :om, :posto, :refeicao, :data)');
				$con->bindValue(':nome', $nome, PDO::PARAM_STR);
				$con->bindValue(':identidade', $identidade, PDO::PARAM_STR);
				$con->bindValue(':om', $om, PDO::PARAM_STR);
				$con->bindValue(':posto', $posto, PDO::PARAM_STR);
				$con->bindValue(':refeicao', 3, PDO::PARAM_INT);
				$con->bindValue(':data', $data, PDO::PARAM_STR);
				$con->execute();
			}
		}
	}
}
echo '{"success":"Arranchamento realizado com sucesso!"}';
?>