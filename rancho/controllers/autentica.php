<?php

error_reporting(0);

session_start();

if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
	echo "Exception: #".strtoupper( substr( md5(rand()), 0, 20));;
	die();
}

if (isset($_POST['formData']) && !empty($_POST['formData'])) {

	parse_str(base64_decode($_POST['formData']),$dados);

	$usuario =  isset($dados['usuario']) ? $dados['usuario'] : null;
	$senha =  isset($dados['senha']) ? $dados['senha'] : null;

	if($usuario || $senha){

		require_once("../../controllers/config/conexao.php");
		require_once("../../controllers/senha.php");

		$con = $cnt->prepare('SELECT * FROM usuarios WHERE usuario = :usuario');
		$con->bindParam(':usuario', $usuario, PDO::PARAM_STR);
		$con->execute();
		$res = $con->fetch(PDO::FETCH_ASSOC);

		if($res && senha_confere($senha, $res['senha'])){
			senha_migrar_se_preciso($cnt, $res['id'], $senha, $res['senha']);

			$_SESSION['usuario']['id'] = $res['id'];
			$_SESSION['usuario']['login'] = $res['usuario'];
			$_SESSION['usuario']['om'] = $res['om'];
			$_SESSION['usuario']['tipo'] = $res['tipo'];
			echo '{"url":"home.php"}';
		} else {
			echo '{"erro":"<p style=\"font-size:13px;\" class=\"alert alert-danger\">Usuário ou Senha Incorretos <i class=\"fa fa-exclamation\"></i></p>"}';
		}
	}

	if(!$usuario && !$senha){
		echo '{"url":"index.php"}';
	}

}
?>
