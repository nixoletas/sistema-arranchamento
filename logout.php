<?php
error_reporting(0);
session_start();
require_once "controllers/config/conexao.php";
app_esquecer($cnt);
session_destroy();		
session_unset();
header("Location: index.php?pasta=home&pagina=inicio");
?>