<?php

error_reporting(0);
session_start();

include "../../controllers/config/conexao.php";

if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
	echo "Exception: #".strtoupper( substr( md5(rand()), 0, 20));;
	die();
}

// Lista de militares (CPF e nome de guerra) do modal "Arranchar (Militares)": só para usuários
// logados no painel. O Operador (furriel) só vê a própria Cia, como na tela.
if (empty($_SESSION['usuario']['id'])) {
	http_response_code(401);
	die('');
}

$om    = isset($_POST['om']) ? (int)$_POST['om'] : 0;
$posto = isset($_POST['posto']) ? (int)$_POST['posto'] : 0;

if ((int)($_SESSION['usuario']['tipo'] ?? 0) !== 1) {
	$om = (int)$_SESSION['usuario']['om'];
}

$con = $cnt->prepare("SELECT nome_guerra, ident_militar FROM cad_militar WHERE om_id = :om AND posto = :posto ORDER BY nome_guerra ASC");
$con->bindValue(':om', $om, PDO::PARAM_INT);
$con->bindValue(':posto', $posto, PDO::PARAM_INT);
$con->execute();

$retorno = "";

while ($result = $con->fetch(PDO::FETCH_ASSOC)){
	$cpf  = htmlspecialchars($result['ident_militar'], ENT_QUOTES, 'UTF-8');
	$nome = htmlspecialchars($result['nome_guerra'], ENT_QUOTES, 'UTF-8');
	$retorno .= "<option value=\"{$cpf}\">[{$cpf}] {$nome}</option>";
}

echo $retorno;

?>
