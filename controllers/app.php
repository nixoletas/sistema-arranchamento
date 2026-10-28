<?php

class App
{

	function __construct()
	{
		global $usuario_logado;
		$pasta = isset( $_GET['pasta'] ) ? $_GET['pasta'] : null;
		$pagina = isset( $_GET['pagina'] ) ? $_GET['pagina'] : null;

		// Só nomes simples (letras, números, "-" e "_"): impede usar "../" para incluir outros arquivos do servidor.
		$nome_valido = function ($s) { return is_string($s) && preg_match('/^[A-Za-z0-9_-]+$/', $s); };
		$logado = !empty($usuario_logado['id']) && is_numeric($usuario_logado['id']);

		include "inc/header.php";

		// Pelo app (endereço público / APK): barra no topo no lugar da coluna lateral e tela de entrada própria.
		$pelo_app = function_exists('acesso_pelo_app') && acesso_pelo_app();

		if($logado && $nome_valido($pasta) && $nome_valido($pagina) && is_file("views/".$pasta."/".$pagina.".php"))
		{
			include $pelo_app ? "inc/app-topo.php" : "inc/skeleton.php";
			include "views/".$pasta."/".$pagina.".php";
		}else{
			include $pelo_app ? "views/app-login.php" : "views/index.php";
		}
		include "inc/footer.php";
		
	}

}


?>
