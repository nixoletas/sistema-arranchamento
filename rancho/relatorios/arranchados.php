<?php
session_start();

// Só usuários logados no painel (Administrador ou Operador).
$tp_sessao = (int)($_SESSION['usuario']['tipo'] ?? 0);
if (empty($_SESSION['usuario']['id']) || ($tp_sessao !== 1 && $tp_sessao !== 2)) {
    http_response_code(401);
    exit('Acesso negado. Faça login novamente.');
}

require_once('../../assets/TCPDF/tcpdf.php');
require_once("../../controllers/config/conexao.php");

// Ordem da lista: "nome" (P/G e nome de guerra, como sempre foi) ou "antiguidade":
// também por P/G, mas entre os SD a ordem é o ano de incorporação (turma mais antiga primeiro;
// no mesmo ano, por nome de guerra; SD sem ano vão para o fim dos SD). Os demais P/G continuam por nome de guerra.
$por_antiguidade = (($_GET['ordem'] ?? '') === 'antiguidade');

// create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set document information
$pdf->setCreator(PDF_CREATOR);
$pdf->setAuthor('9º Batalhão de Comunicações e Guerra Eletrônica');
$pdf->setTitle('Arranchados');
$pdf->setSubject('Data de impressão: '.date('d/m/Y'));

$PDF_HEADER_LOGO = "../../../img/logo-9bcomge.jpg";
$PDF_HEADER_LOGO_WIDTH = "15";
$PDF_HEADER_TITLE = "Relatório de Arranchados";
$PDF_HEADER_STRING = "Sistema de Arranchamento\nData: ".htmlspecialchars($_GET['dt'] ?? '')."\nOrdem: ".($por_antiguidade ? "P/G e, entre os SD, antiguidade (ano de incorporação)" : "P/G e nome de guerra");

// set default header data
$pdf->setHeaderData($PDF_HEADER_LOGO, $PDF_HEADER_LOGO_WIDTH, $PDF_HEADER_TITLE, $PDF_HEADER_STRING, array(0,64,255), array(0,64,128));
$pdf->setFooterData(array(0,64,0), array(0,64,128));

// set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// set default monospaced font
$pdf->setDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
$pdf->setMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->setHeaderMargin(PDF_MARGIN_HEADER);
$pdf->setFooterMargin(PDF_MARGIN_FOOTER);

// set auto page breaks
$pdf->setAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// set some language-dependent strings (optional)
if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
    require_once(dirname(__FILE__).'/lang/eng.php');
    $pdf->setLanguageArray($l);
}

// ---------------------------------------------------------

// set default font subsetting mode
$pdf->setFontSubsetting(true);

// Set font
$pdf->setFont('dejavusans', '', 14, '', true);

// Add a page
$pdf->AddPage();

// set text shadow effect
$pdf->setTextShadow(array('enabled'=>true, 'depth_w'=>0.2, 'depth_h'=>0.2, 'color'=>array(196,196,196), 'opacity'=>1, 'blend_mode'=>'Normal'));

// Set some content to print
$html = <<<EOD
EOD;

// Print text using writeHTMLCell()
$pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);

// Fetch data from database
$usuario = $_SESSION['usuario']['id'];
$tp_usuario = $_SESSION['usuario']['tipo'];

$posto = isset($_GET['posto']) ? $_GET['posto'] : null;
$om = isset($_GET['om']) ? $_GET['om'] : null;
// O Operador (furriel) só tira relatório da própria Cia, como na tela.
if ($tp_usuario != 1) {
    $om = (string)(int)$_SESSION['usuario']['om'];
}
$data = str_replace('/', '-', $_GET['dt']);
$dt = date("Y-m-d", strtotime($data));

$cafeOF = null;
$cafeST_SGT = null;
$cafeCB_SD = null;
$almocoOF = null;
$almocoST_SGT = null;
$almocoCB_SD = null;
$jantarOF = null;
$jantarST_SGT = null;
$jantarCB_SD = null;
if ($tp_usuario == 1 || $tp_usuario == 2) {
    $con = $cnt->prepare("SELECT DISTINCT
        mil.nome_guerra AS nome_guerra,
        om.nome AS om,
        posto.nome AS posto,
        posto.id AS posto_id,
        (posto.nome = 'SD') AS eh_sd,
        CASE WHEN posto.nome = 'SD' THEN mil.ano_incorporacao END AS ano_incorporacao,
        CASE WHEN posto.nome = 'SD' THEN (mil.ano_incorporacao IS NULL) ELSE 0 END AS sem_data_inc,
        posto.ordem AS posto_ordem,
        GROUP_CONCAT(REPLACE(REPLACE(REPLACE(regs.tp,'3','Jantar'),'2','Almoço'),'1','Café')) AS refeicao,
        regs.data AS data
        FROM registros regs
        JOIN cad_militar mil ON regs.ident_militar = mil.ident_militar
        JOIN cad_cia om ON mil.om_id = om.id
        JOIN cad_posto posto ON mil.posto = posto.id
        WHERE data = :dt
        AND FIND_IN_SET(posto.id, :posto)
        AND FIND_IN_SET(om.id, :om)
        GROUP BY mil.ident_militar, posto.id
        ORDER BY posto_ordem, "
        . ($por_antiguidade ? "sem_data_inc, ano_incorporacao ASC, " : "")
        . "nome_guerra ASC");
    $con->bindParam(':dt', $dt, PDO::PARAM_STR);
    $con->bindParam(':posto', $posto);
    $con->bindParam(':om', $om);
    $con->execute();
}

// Create table header
$thead = "
<h3 style=\"text-align:center;\">Arranchados</h3>
<table style=\"width:100%;text-align:center;font-size:7pt;margin-top:10px;\" border=\"0.5px\">

<tr style=\"font-weight:bold;font-size:9pt;\">
<th style=\"width:10%;\">Nº Ord</th>
<th style=\"width:10%;\">P/G</th>"
. ($por_antiguidade
    ? "<th style=\"width:27%;\">Nome Guerra</th><th style=\"width:13%;\">Ano incorp.</th><th style=\"width:20%;\">Cia</th>"
    : "<th style=\"width:35%;\">Nome Guerra</th><th style=\"width:25%;\">Cia</th>")
. "<th style=\"width:20%;\">Refeição</th>
</tr>";

$tbody = "";

$i = 0;
$sem_data = 0;
while ($res = $con->fetch(PDO::FETCH_ASSOC)) {
    $count = str_pad(($i + 1), 2, '0', STR_PAD_LEFT);
    $tbody .= "
	<tr>
	<td>".$count."</td>
	<td>".$res['posto']."</td>
	<td>".htmlspecialchars($res['nome_guerra'])."</td>"
	. ($por_antiguidade ? "<td>".($res['ano_incorporacao'] ? (int)$res['ano_incorporacao'] : ($res['eh_sd'] ? "—" : ""))."</td>" : "")
	. "<td>".htmlspecialchars($res['om'])."</td>
	<td>".$res['refeicao']."</td>
	</tr>";
    $i++;
    if ($por_antiguidade && $res['eh_sd'] && !$res['ano_incorporacao']) {
        $sem_data++;
    }
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

if($tp_usuario == 1 || $tp_usuario == 2){
	$qnt = $cnt->prepare("SELECT
		regs.tp,
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
		FROM registros regs
		JOIN cad_militar mil
		ON regs.ident_militar = mil.ident_militar
		JOIN cad_cia om
		ON mil.om_id = om.id
		JOIN cad_posto posto
		ON mil.posto = posto.id
		WHERE data = :dt
		AND FIND_IN_SET (posto.id, :posto)
		AND FIND_IN_SET (om.id, :om)
		GROUP BY regs.tp,
		posto.id;");
	$qnt->bindParam(':dt', $dt, PDO::PARAM_STR);
	$qnt->bindParam(':posto', $posto);
	$qnt->bindParam(':om', $om);
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

<!--
<tr style=\"font-weight:bold;\">
	<td style=\"background-color:#CCC;\">TOTAL</td>
	<td>".($oficiais ? $oficiais : "0")."</td>
	<td>".($st_sgt ? $st_sgt : "0")."</td>
	<td>".($cb_sd ? $cb_sd : "0")."</td>
	<td style=\"background-color:#CCC;\"></td>
</tr>
-->

</table>
<p></p>
<table style=\"text-align:center;font-size:8pt;\">
<tr>
<td style=\"font-weight:bold;border-top:0.5px solid black;\">FURRIEL</td>
<td></td>
<td style=\"font-weight:bold;border-top:0.5px solid black;\">FISC ADM</td>
</tr>
</table>";

// Aviso logo abaixo da lista: SD que ainda não têm o ano de incorporação cadastrado.
$aviso_sem_data = "";
if ($por_antiguidade && $sem_data) {
    $aviso_sem_data = "<p style=\"font-size:7pt;color:#8a6d3b;\">".$sem_data." SD sem ano de incorporação cadastrado (marcados com —) aparecem no fim dos SD. Cadastre o ano em Gerenciar Militares.</p>";
}

$page = $thead.$tbody.$tbhead.$tbBody;
if ($aviso_sem_data) {
    // Fecha a lista, mostra o aviso e segue com o quadro de quantidades.
    $page = $thead.$tbody."</table>".$aviso_sem_data.preg_replace('#^\s*</table>#', '', $tbhead).$tbBody;
}

// Print table using writeHTMLCell()
$pdf->writeHTMLCell($w = 0, $h = 0, $x = '', $y = '', $page, $border = 0, $ln = 1, $fill = 0, $reseth = true, $align = 'top', $autopadding = true);

// ---------------------------------------------------------

// Close and output PDF document
$pdf->Output('arranchados.pdf', 'I');

//============================================================+
// END OF FILE
//============================================================+
?>