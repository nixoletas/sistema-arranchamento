<?php
header('Content-Type: application/json');
session_start();
error_reporting(0);


include "../../controllers/config/conexao.php";

if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
	echo "Exception: #".strtoupper( substr( md5(rand()), 0, 20));;
	die();
}

// Gerenciar organização é restrito ao Administrador (mesma regra do card em rancho/home.php).
if (empty($_SESSION['usuario']['id']) || (int)($_SESSION['usuario']['tipo'] ?? 0) !== 1) {
	http_response_code(401);
	echo '{"error":"Acesso negado. Faça login novamente."}';
	die();
}

if (isset($_POST['opt']) && !empty($_POST['opt'])) {
	if($_POST['opt'] == 'delete'){

		$id = isset($_POST['id']) ? $_POST['id'] : null;
		$sigla = isset($_POST['sigla']) ? $_POST['sigla'] : null;

		if($id && $sigla){
			
			$con = $cnt->prepare("DELETE FROM cad_cia WHERE nome = :sigla_org AND id = :id");
			$con->bindValue(':sigla_org', $sigla, PDO::PARAM_STR);
			$con->bindValue(':id', $id, PDO::PARAM_INT);
			$con->execute();
			$total = $con->rowCount();
			if($total){
				echo '{"msg":"Registro excluído com sucesso!"}';
				die();
			}	
		}
	}

	if($_POST['opt'] == 'select'){

		$con = $cnt->prepare("
			SELECT
			om.id,
			om.nome,
			om.descricao
			FROM cad_cia om
			ORDER BY om.id ASC;");
		$con->execute();
		$total = $con->rowCount();

		$tbl = "";

		while ($result = $con->fetch(PDO::FETCH_ASSOC)){ 

			$tbl .='{"id":"'.$result['id'].'","sigla_org":"'.$result['nome'].'","desc_org":"'.$result['descricao'].'"},';
		}

		$tbl = substr($tbl,0,strlen($tbl) - 1);
		echo '{"draw":"0","recordsTotal":"'.$total.'","recordsFiltered":"'.$total.'","data":['.$tbl.']}';
	}
}

if (isset($_POST['formData']) && !empty($_POST['formData'])) {
	parse_str(base64_decode($_POST['formData']),$dados);

	if(!$dados['sigla_org']){
		echo '{"error":"Digite a Sigla da Organização!"}';
		die();
	}

	if(!$dados['sigla_org']){
		echo '{"error":"Digite a Descrição da Organização!"}';
		die();
	}

	switch ($dados['opt_org']) {
		case 'add':
		$con = $cnt->prepare("SELECT id FROM cad_cia WHERE nome = :sigla_org");
		$con->bindValue(':sigla_org', $dados['sigla_org'], PDO::PARAM_STR);
		$con->execute();
		$total = $con->rowCount();
		
		if($total){
			echo '{"error":"Organização já cadastrado!"}';
			die();
		}

		if(!$total){
			$con = $cnt->prepare('INSERT INTO cad_cia (nome, descricao) VALUES (:sigla_org, :desc_org)');
			$con->bindValue(':sigla_org', $dados['sigla_org'] , PDO::PARAM_STR);
			$con->bindValue(':desc_org', $dados['desc_org'], PDO::PARAM_STR);
			$con->execute();
			if($con){
				echo '{"msg":"Cadastro realizado com sucesso!"}';
			}else{
				echo '{"error":"Erro no Cadastro!"}';
				die();
			}
		}
		break;

		case 'edit':

		$con = $cnt->prepare("UPDATE cad_cia SET nome = :sigla_org, descricao = :desc_org  WHERE id = :id_org");
		$con->bindValue(':id_org', $dados['id_org'] , PDO::PARAM_STR);
		$con->bindValue(':sigla_org', $dados['sigla_org'] , PDO::PARAM_STR);
		$con->bindValue(':desc_org', $dados['desc_org'], PDO::PARAM_STR);
		$con->execute();

		if($con){
			echo '{"msg":"Atualização realizada com sucesso!"}';
		}else{
			echo '{"error":"Erro na Atualização!"}';
			die();
		}
		break;

		default:
		echo '{"error":"Erro na Solicitação!"}';
		die();
		break;
	}
}

?>
