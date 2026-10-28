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

// Busca por nome de guerra, nome ou CPF (campo "Buscar militar" da janela Arranchar). Devolve JSON.
if (isset($_POST['busca'])) {
	header('Content-Type: application/json; charset=utf-8');
	$termo = trim((string)$_POST['busca']);
	if (mb_strlen($termo, 'UTF-8') < 2) {
		echo '[]';
		exit;
	}
	$restrito = (int)($_SESSION['usuario']['tipo'] ?? 0) !== 1;
	$like = '%' . addcslashes($termo, '%_\\') . '%';
	$digitos = preg_replace('/\D/', '', $termo);
	$sql = "SELECT mil.ident_militar, mil.nome_guerra, posto.nome AS posto, om.nome AS cia
		FROM cad_militar mil
		JOIN cad_posto posto ON posto.id = mil.posto
		JOIN cad_cia om ON om.id = mil.om_id
		WHERE (mil.nome_guerra LIKE :t1 OR mil.nome LIKE :t2"
		. (strlen($digitos) >= 3 ? " OR REPLACE(REPLACE(mil.ident_militar, '.', ''), '-', '') LIKE :dig" : "") . ")"
		. ($restrito ? " AND mil.om_id = :om" : "")
		. " ORDER BY posto.ordem, mil.nome_guerra LIMIT 15";
	$con = $cnt->prepare($sql);
	$con->bindValue(':t1', $like);
	$con->bindValue(':t2', $like);
	if (strlen($digitos) >= 3) {
		$con->bindValue(':dig', '%' . $digitos . '%');
	}
	if ($restrito) {
		$con->bindValue(':om', $om, PDO::PARAM_INT);
	}
	$con->execute();
	echo json_encode($con->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
	exit;
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
