<?php
header('Content-Type: application/json');
session_start();
error_reporting(0);

include "../../controllers/config/conexao.php";

if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
	echo "Exception: #".strtoupper( substr( md5(rand()), 0, 20));;
	die();
}

// Gerenciar militares: só usuários logados no painel. O Administrador mexe em todas as Cias;
// o Operador (furriel) só na própria Cia — a mesma regra que a tela já aplica.
if (empty($_SESSION['usuario']['id'])) {
	http_response_code(401);
	echo '{"error":"Acesso negado. Faça login novamente."}';
	die();
}

$tp_usuario = $_SESSION['usuario']['tipo'];
$om_usuario = $_SESSION['usuario']['om'];
$so_propria_cia = (int)$tp_usuario !== 1;


if (isset($_POST['opt']) && !empty($_POST['opt'])) {
	if($_POST['opt'] == 'delete'){

		$id = isset($_POST['id']) ? $_POST['id'] : null;
		$ident_militar = isset($_POST['ident_militar']) ? $_POST['ident_militar'] : null;

		if($id && $ident_militar){

			$con = $cnt->prepare("DELETE FROM cad_militar WHERE ident_militar = :ident_militar AND id = :id"
				. ($so_propria_cia ? " AND om_id = :om_usuario" : ""));
			$con->bindValue(':ident_militar', $ident_militar, PDO::PARAM_STR);
			$con->bindValue(':id', $id, PDO::PARAM_INT);
			if ($so_propria_cia) {
				$con->bindValue(':om_usuario', $om_usuario, PDO::PARAM_INT);
			}
			$con->execute();
			$total = $con->rowCount();
			if($total){
				echo '{"msg":"Registro excluído com sucesso!"}';
				die();
			}	
		}
	}
}


// Zerar a senha do app (o militar esqueceu): ele cria outra no site dentro da OM.
if (($_POST['opt'] ?? '') === 'zerar_senha_app') {
	$con = $cnt->prepare("SELECT ident_militar FROM cad_militar WHERE id = :id" . ($so_propria_cia ? " AND om_id = :om_usuario" : ""));
	$con->bindValue(':id', (int)($_POST['id'] ?? 0), PDO::PARAM_INT);
	if ($so_propria_cia) {
		$con->bindValue(':om_usuario', $om_usuario, PDO::PARAM_INT);
	}
	$con->execute();
	$ident = $con->fetchColumn();
	if (!$ident) {
		echo '{"error":"Militar não encontrado na sua Cia."}';
		die();
	}
	require_once "../../controllers/acesso-app.php";
	app_zerar_senha($cnt, $ident);
	include_once "auditoria.php";
	auditoria('ZERAR_SENHA_APP', (int)$_POST['id'], $cnt, $_SESSION['usuario']['id']);
	echo '{"msg":"Senha do app zerada. O militar cria outra no site dentro da OM."}';
	die();
}

//atualizado -------

// ... (seu código anterior) ...

if (isset($_POST['opt']) && !empty($_POST['opt'])) {
	if ($_POST['opt'] == 'select') {

		if ($tp_usuario == 1) {
			$con = $cnt->prepare("
				SELECT
				mil.id,
				mil.nome,
				mil.nome_guerra,
				mil.ident_militar,
				mil.ano_incorporacao,
				posto.nome as posto,
				posto.id as posto_id,
				om.nome as om,
				om.id as om_id
				FROM cad_militar mil
				JOIN cad_posto posto
				ON mil.posto = posto.id
				JOIN cad_cia om
				ON mil.om_id = om.id
				ORDER BY posto.ordem, mil.nome_guerra ASC;");
		}
		if ($tp_usuario != 1) {
			$con = $cnt->prepare("
				SELECT
				mil.id,
				mil.nome,
				mil.nome_guerra,
				mil.ident_militar,
				mil.ano_incorporacao,
				posto.nome as posto,
				posto.id as posto_id,
				om.nome as om,
				om.id as om_id
				FROM cad_militar mil
				JOIN cad_posto posto
				ON mil.posto = posto.id
				JOIN cad_cia om
				ON mil.om_id = om.id
				WHERE mil.om_id = :om_usuario
				ORDER BY posto.ordem, mil.nome_guerra ASC;");
			$con->bindValue(':om_usuario', $om_usuario, PDO::PARAM_STR);
		}
		$con->execute();
		$total = $con->rowCount();

		$data = array();  // Array para armazenar os dados

		while ($result = $con->fetch(PDO::FETCH_ASSOC)) {
			$data[] = array(
				"id" => $result['id'],
				"nome_guerra" => $result['nome_guerra'],
				"nome" => $result['nome'],
				"ident_militar" => $result['ident_militar'],
				"ano_incorporacao" => $result['ano_incorporacao'] ?? '',
				"posto_nome" => $result['posto'],
				"posto_id" => $result['posto_id'],
				"om_nome" => $result['om'],
				"om_id" => $result['om_id']
			);
		}

		$response = array(
			"draw" => "0",
			"recordsTotal" => $total,
			"recordsFiltered" => $total,
			"data" => $data
		);

		echo json_encode($response);  // Use json_encode para gerar a resposta JSON
	}
}

// ... (seu código posterior) ...


//atualizado -------

if (isset($_POST['formData']) && !empty($_POST['formData'])) {

	parse_str(base64_decode($_POST['formData']),$dados);

	if(!$dados['nome_mil']){
		echo '{"error":"Digite o Nome Completo!"}';
		die();
	}
	if(!$dados['nome_guerra']){
		echo '{"error":"Digite o Nome de Guerra!"}';
		die();
	}
	if(!$dados['identidade']){
		echo '{"error":"Digite o número do CPF!"}';
		die();
	}
	if(!$dados['pg_militar']){
		echo '{"error":"Selecione algum P/G!"}';
		die();
	}
	if(!$dados['om_militar']){
		echo '{"error":"Selecione alguma OM!"}';
		die();
	}
	if($so_propria_cia && (int)$dados['om_militar'] !== (int)$om_usuario){
		echo '{"error":"Você só pode cadastrar militares da sua Cia!"}';
		die();
	}

	// Ano de incorporação: só para SD (a turma entra toda no mesmo dia), opcional. Usado para ordenar
	// os soldados por antiguidade no relatório. Para os demais P/G não é guardado.
	$ano_incorporacao = null;
	$ano_txt = trim($dados['ano_incorporacao'] ?? '');
	if ($ano_txt !== '') {
		if (!preg_match('/^\d{4}$/', $ano_txt) || (int)$ano_txt < 1950 || (int)$ano_txt > (int)date('Y')) {
			echo '{"error":"Ano de incorporação inválido!"}';
			die();
		}
		$ano_incorporacao = (int)$ano_txt;
	}
	$con_sd = $cnt->prepare("SELECT id FROM cad_posto WHERE nome = 'SD' LIMIT 1");
	$con_sd->execute();
	if ((int)$dados['pg_militar'] !== (int)$con_sd->fetchColumn()) {
		$ano_incorporacao = null;
	}

	switch ($dados['opt']) {
		case 'add':
		$con = $cnt->prepare("SELECT id FROM cad_militar WHERE ident_militar = :identidade");
		$con->bindValue(':identidade', $dados['identidade'], PDO::PARAM_STR);
		$con->execute();
		$total = $con->rowCount();

		if($total){
			echo '{"error":"CPF já cadastrado!"}';
			die();
		}

		if(!$total){
			$con = $cnt->prepare('INSERT INTO cad_militar (nome, nome_guerra, ident_militar, posto, om_id, ano_incorporacao) VALUES (:nome_mil, :nome_guerra, :identidade, :pg_militar, :om_id, :ano_incorporacao)');
			$con->bindValue(':nome_mil', $dados['nome_mil'] , PDO::PARAM_STR);
			$con->bindValue(':nome_guerra', $dados['nome_guerra'], PDO::PARAM_STR);
			$con->bindValue(':identidade',  $dados['identidade'], PDO::PARAM_STR);
			$con->bindValue(':pg_militar', $dados['pg_militar'], PDO::PARAM_INT);
			$con->bindValue(':om_id', $dados['om_militar'], PDO::PARAM_INT);
			$con->bindValue(':ano_incorporacao', $ano_incorporacao, $ano_incorporacao === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
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
		$con = $cnt->prepare("UPDATE cad_militar SET nome = :nome_mil, nome_guerra = :nome_guerra, ident_militar = :identidade, posto = :pg_militar, om_id = :om_id, ano_incorporacao = :ano_incorporacao WHERE id = :id_mil"
			. ($so_propria_cia ? " AND om_id = :om_usuario" : ""));
		$con->bindValue(':id_mil', $dados['id_mil'] , PDO::PARAM_INT);
		if ($so_propria_cia) {
			$con->bindValue(':om_usuario', $om_usuario, PDO::PARAM_INT);
		}
		$con->bindValue(':nome_mil', $dados['nome_mil'] , PDO::PARAM_STR);
		$con->bindValue(':nome_guerra', $dados['nome_guerra'], PDO::PARAM_STR);
		$con->bindValue(':identidade',  $dados['identidade'], PDO::PARAM_STR);
		$con->bindValue(':pg_militar', $dados['pg_militar'], PDO::PARAM_INT);
		$con->bindValue(':om_id', $dados['om_militar'], PDO::PARAM_INT);
		$con->bindValue(':ano_incorporacao', $ano_incorporacao, $ano_incorporacao === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
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
