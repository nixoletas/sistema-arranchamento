<?php
header('Content-Type: application/json');
session_start();
error_reporting(0);


include "../../controllers/config/conexao.php";
include "../../controllers/senha.php";

if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
	echo "Exception: #".strtoupper( substr( md5(rand()), 0, 20));;
	die();
}

// Este arquivo atende dois casos: trocar a própria senha (qualquer usuário logado, tipo 1 ou 2)
// e gerenciar usuários (listar/criar/editar/excluir), restrito ao Administrador — mesma regra
// dos cards "Alterar Senha" e "Gerenciar (Usuários do Sistema)" em rancho/home.php.
if (empty($_SESSION['usuario']['id'])) {
	http_response_code(401);
	echo '{"error":"Acesso negado. Faça login novamente."}';
	die();
}
$souAdmin = (int)($_SESSION['usuario']['tipo'] ?? 0) === 1;
if (!$souAdmin && (isset($_POST['opt']) || isset($_POST['formData']))) {
	http_response_code(401);
	echo '{"error":"Acesso negado. Só o Administrador pode gerenciar usuários."}';
	die();
}

$usuario = $_SESSION['usuario']['id'];

if (isset($_POST['form']) && !empty($_POST['form'])) {
	parse_str(base64_decode($_POST['form']),$dados);

	$senhaNova = isset($dados['senha_nova']) ? $dados['senha_nova'] : null;

	$senhaAtual = isset($dados['senha_atual']) ? $dados['senha_atual'] : null;

	if(!$senhaAtual){
		echo '{"error":"Digite a senha atual!"}';
		die();
	}

	if(!$senhaNova){
		echo '{"error":"Digite uma nova senha!"}';
		die();
	}

	$con = $cnt->prepare('SELECT * FROM usuarios WHERE id = :id');
	$con->bindValue(':id', $usuario, PDO::PARAM_INT);
	$con->execute();
	$res = $con->fetch(PDO::FETCH_ASSOC);

	if(!$res || !senha_confere($senhaAtual, $res['senha'])){
		echo '{"error":"Senha atual não confere!"}';
		die();
	}

	if($res){
		$con = $cnt->prepare('UPDATE usuarios SET senha = :senha WHERE id = :id');
		$con->bindValue(':id', $usuario, PDO::PARAM_INT);
		$con->bindValue(':senha', senha_hash($senhaNova), PDO::PARAM_STR);
		$con->execute();
		echo '{"msg":"Senha alterada com sucesso!"}';
		die();

	}
}

if (isset($_POST['opt']) && !empty($_POST['opt'])) {
	if($_POST['opt'] == 'delete'){

		$id = isset($_POST['id']) ? $_POST['id'] : null;
		$usuario = isset($_POST['usuario']) ? $_POST['usuario'] : null;

		if($id && $usuario){
			
			$con = $cnt->prepare("DELETE FROM usuarios WHERE usuario = :usuario AND id = :id");
			$con->bindValue(':usuario', $usuario, PDO::PARAM_STR);
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
			us.id,
			us.nome,
			us.usuario,
			us.tipo,
			om.nome as om,
			om.id as om_id
			FROM usuarios us
			JOIN cad_cia om
			ON us.om = om.id
			ORDER BY us.id ASC;");
		$con->execute();
		$total = $con->rowCount();

		$tbl = "";

		while ($result = $con->fetch(PDO::FETCH_ASSOC)){ 

			if($result['tipo'] == 1){$result['tipo_nome'] = 'Administrador'; }
			if($result['tipo'] == 2){$result['tipo_nome'] = 'Operador'; }

			$tbl .='{"id":"'.$result['id'].'","nome_us":"'.$result['nome'].'","usuario_us":"'.$result['usuario'].'","tipo_id":"'.$result['tipo'].'","tipo_us":"'.$result['tipo_nome'].'","om_nome":"'.$result['om'].'","om_id":"'.$result['om_id'].'"},';
		}

		$tbl = substr($tbl,0,strlen($tbl) - 1);
		echo '{"draw":"0","recordsTotal":"'.$total.'","recordsFiltered":"'.$total.'","data":['.$tbl.']}';
	}
}

if (isset($_POST['formData']) && !empty($_POST['formData'])) {
	parse_str(base64_decode($_POST['formData']),$dados);

	if(!$dados['nome_us']){
		echo '{"error":"Digite o Nome Completo!"}';
		die();
	}
	if(!$dados['usuario_us']){
		echo '{"error":"Digite o Nome de Usuário!"}';
		die();
	}
	if(!$dados['tipo_us']){
		echo '{"error":"Selecione o tipo de Perfil!"}';
		die();
	}
	if(!$dados['om_us']){
		echo '{"error":"Selecione alguma OM!"}';
		die();
	}

	switch ($dados['opt_us']) {
		case 'add':
		$con = $cnt->prepare("SELECT id FROM usuarios WHERE usuario = :usuario_us");
		$con->bindValue(':usuario_us', $dados['usuario_us'], PDO::PARAM_STR);
		$con->execute();
		$total = $con->rowCount();
		
		if($total){
			echo '{"error":"Usuário já cadastrado!"}';
			die();
		}

		if(!$total){
			$con = $cnt->prepare('INSERT INTO usuarios (nome, usuario, senha, tipo, om) VALUES (:nome_us, :usuario_us, :senha_us, :tipo_us, :om_us)');
			$con->bindValue(':nome_us', $dados['nome_us'] , PDO::PARAM_STR);
			$con->bindValue(':usuario_us', $dados['usuario_us'], PDO::PARAM_STR);
			$con->bindValue(':senha_us',  senha_hash($dados['senha_us']), PDO::PARAM_STR);
			$con->bindValue(':tipo_us', $dados['tipo_us'], PDO::PARAM_INT);
			$con->bindValue(':om_us', $dados['om_us'], PDO::PARAM_INT);
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
		if(trim($dados["senha_us"]) !== ""){
			$pass = !empty($dados['senha_us']) ? senha_hash($dados['senha_us']) : '';
			$upd = "senha = :senha_us,";
		}else{
			$upd = '';
		}

		$con = $cnt->prepare("UPDATE usuarios SET nome = :nome_us, usuario = :usuario_us, ".$upd." tipo = :tipo_us, om = :om_us WHERE id = :id_us");
		$con->bindValue(':id_us', $dados['id_us'] , PDO::PARAM_STR);
		$con->bindValue(':nome_us', $dados['nome_us'] , PDO::PARAM_STR);
		$con->bindValue(':usuario_us', $dados['usuario_us'], PDO::PARAM_STR);
		$con->bindValue(':tipo_us', $dados['tipo_us'], PDO::PARAM_INT);
		$con->bindValue(':om_us', $dados['om_us'], PDO::PARAM_INT);
		if($upd){
			$con->bindValue(':senha_us', $pass, PDO::PARAM_STR);
		}

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
