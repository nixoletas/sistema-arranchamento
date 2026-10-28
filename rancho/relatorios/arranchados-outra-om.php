<?php
session_start();

// Relatório de militares de outras OM: só o Administrador (é ele quem vê essa opção na tela).
if (empty($_SESSION['usuario']['id']) || (int)($_SESSION['usuario']['tipo'] ?? 0) !== 1) {
	http_response_code(401);
	exit('Acesso negado. Faça login novamente.');
}
require_once('../../assets/TCPDF/tcpdf.php');
require_once("../../controllers/config/conexao.php");


$file = "export";

header("Cache-Control: public");
header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=".$file."");
header("Content-type:application/pdf");

$PDF_HEADER_LOGO = "../../../img/1bda.png";
$PDF_HEADER_LOGO_WIDTH = "15";
$PDF_HEADER_TITLE = "Relatório de Arranchados";
$PDF_HEADER_STRING = "Sistema de Arrachamento\nData: ".$_GET['dt']."\n";


$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetAuthor('Sistema de Arranchamento');
$pdf->SetTitle('Relatório de Arranchados');

$pdf->SetHeaderData($PDF_HEADER_LOGO, $PDF_HEADER_LOGO_WIDTH, $PDF_HEADER_TITLE, $PDF_HEADER_STRING);
$pdf->SetFooterData(array(0,64,0), array(0,64,128));

$pdf->SetHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->SetFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));


$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

$pdf->AddPage('P', 'A4');

$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM); 

$usuario = $_SESSION['usuario']['id'];
$tp_usuario = $_SESSION['usuario']['tipo'];

$data = str_replace('/', '-', $_GET['dt']);
$dt = date("Y-m-d", strtotime($data));


if($tp_usuario == 1){
	$con = $cnt->prepare("SELECT DISTINCT
		regs.nome AS nome_guerra,
		posto.nome AS posto,
		regs.om as om,
		GROUP_CONCAT(REPLACE(REPLACE(REPLACE(regs.tpRef,'3','Jantar'),'2','Almoço'),'1','Café')) AS refeicao,
		regs.data AS data
		FROM registros_outros regs
		JOIN cad_posto posto
		ON regs.posto = posto.id
		WHERE data = :dt
		GROUP BY regs.ident_militar
		ORDER BY posto.ordem, nome_guerra ASC");
	$con->bindParam(':dt', $dt, PDO::PARAM_STR);
	$con->execute();
}


$thead = "
<h3 style=\"text-align:center;\">Arranchados - Outras OM</h3>
<table style=\"width:100%;text-align:center;font-size:7pt;margin-top:10px;\" border=\"0.5px\">

<tr style=\"font-weight:bold;font-size:9pt;\">
<th style=\"width:10%;\">Nº Ord</th>
<th style=\"width:10%;\">P/G</th>
<th style=\"width:35%;\">Nome Guerra</th>
<th style=\"width:25%;\">OM</th>
<th style=\"width:20%;\">Refeição</th>
</tr>";

$tbody = "";
$i = 0;

while($res = $con->fetch(PDO::FETCH_ASSOC)){

	$count = str_pad(($i+1),2,'0',STR_PAD_LEFT);

	$tbody .= "
	<tr>
	<td>".$count."</td>
	<td>".$res['posto']."</td>
	<td>".$res['nome_guerra']."</td>
	<td>".$res['om']."</td>
	<td>".$res['refeicao']."</td>
	</tr>";

	$i++;
}


$tbhead = "
</table>
<div style=\"padding-top:10px;\"></div>
<table style=\"text-align:center;font-size:7pt;\" border=\"0.5px\">
<tr style=\"font-weight:bold;\">
<th>REFEIÇÃO</th>
<th>OFICIAIS</th>
<th>ST/SGT</th>
<th>CB/SD</th>
<th style=\"background-color:#CCC;\">TOTAL</th>
</tr>";

if($tp_usuario == 1){
	$qnt = $cnt->prepare("SELECT
		regs.tpRef as tp,
		posto.ordem AS posto,
		SUM(CASE
		WHEN posto.ordem <= 11 THEN 1
		ELSE 0
		END) OFICIAIS,
		SUM(CASE
		WHEN posto.ordem >= 12 AND
		posto.ordem <= 15 THEN 1
		ELSE 0
		END) ST_SGT,
		SUM(CASE
		WHEN posto.ordem >= 16 AND
		posto.ordem <= 17 THEN 1
		ELSE 0
		END) CB_SD
		FROM registros_outros regs
		JOIN cad_posto posto
		ON regs.posto = posto.id
		WHERE data = :dt
		GROUP BY regs.tpRef, posto.id;");
	$qnt->bindParam(':dt', $dt, PDO::PARAM_STR);
	$qnt->execute();
}

while($ln = $qnt->fetch(PDO::FETCH_ASSOC)){

	if($ln['tp'] == 1 && $ln['posto'] <= 11){
		$cafeOF+= $ln['OFICIAIS'];
	}

	if($ln['tp'] == 2 && $ln['posto'] <= 11){
		$almocoOF+= $ln['OFICIAIS'];
	}

	if($ln['tp'] == 3 && $ln['posto'] <= 11){
		$jantarOF+= $ln['OFICIAIS'];
	}

	if($ln['tp'] == 1 && ($ln['posto'] >= 12 && $ln['posto'] <= 15)){
		$cafeST_SGT+= $ln['ST_SGT'];
	}

	if($ln['tp'] == 2 && ($ln['posto'] >= 12 && $ln['posto'] <= 15)){
		$almocoST_SGT+= $ln['ST_SGT'];
	}

	if($ln['tp'] == 3 && ($ln['posto'] >= 12 && $ln['posto'] <= 15)){
		$jantarST_SGT+= $ln['ST_SGT'];
	}

	if($ln['tp'] == 1 && ($ln['posto'] >= 16 && $ln['posto'] <= 17)){
		$cafeCB_SD+= $ln['CB_SD'];
	}

	if($ln['tp'] == 2 && ($ln['posto'] >= 16 && $ln['posto'] <= 17)){
		$almocoCB_SD+= $ln['CB_SD'];
	}

	if($ln['tp'] == 3 && ($ln['posto'] >= 16 && $ln['posto'] <= 17)){
		$jantarCB_SD+= $ln['CB_SD'];
	}

}

$cafe = $cafeOF + $cafeST_SGT + $cafeCB_SD;
$almoco = $almocoOF + $almocoST_SGT + $almocoCB_SD;
$jantar = $jantarOF + $jantarST_SGT + $jantarCB_SD;

$oficiais = $cafeOF + $almocoOF + $jantarOF;
$st_sgt = $cafeST_SGT + $almocoST_SGT + $jantarST_SGT;
$cb_sd = $cafeCB_SD + $almocoCB_SD + $jantarCB_SD;

$tbBody ="
<tr>
<td style=\"background-color:#DEB887;\">CAFÉ</td>
<td>".($cafeOF ? $cafeOF : "0")."</td>
<td>".($cafeST_SGT ? $cafeST_SGT : "0")."</td>
<td>".($cafeCB_SD ? $cafeCB_SD : "0")."</td>
<td>".($cafe ? $cafe : "0")."</td>
</tr>
<tr>
<td style=\"background-color:#DAA520;\">ALMOÇO</td>
<td>".($almocoOF ? $almocoOF : "0")."</td>
<td>".($almocoST_SGT ? $almocoST_SGT : "0")."</td>
<td>".($almocoCB_SD ? $almocoCB_SD : "0")."</td>
<td>".($almoco ? $almoco : "0")."</td>
</tr>
<tr>
<td style=\"background-color:#CD853F;\">JANTAR</td>
<td>".($jantarOF ? $jantarOF : "0")."</td>
<td>".($jantarST_SGT ? $jantarST_SGT : "0")."</td>
<td>".($jantarCB_SD ? $jantarCB_SD : "0")."</td>
<td>".($jantar ? $jantar : "0")."</td>
</tr>
<tr style=\"font-weight:bold;\">
<td style=\"background-color:#CCC;\">TOTAL</td>
<td>".($oficiais ? $oficiais : "0")."</td>
<td>".($st_sgt ? $st_sgt : "0")."</td>
<td>".($cb_sd ? $cb_sd : "0")."</td>
<td style=\"background-color:#CCC;\"></td>
</tr>
</table>";

$page = $thead.$tbody.$tbhead.$tbBody;

$pdf->writeHTMLCell($w = 0, $h = 0, $x = '', $y = '', $page, $border = 0, $ln = 1, $fill = 0, $reseth = true, $align = 'top', $autopadding = true);
$pdf->Output($file, 'I');

?>