<?php
include_once('../../general_include/general_functions.php');
include_once('../../general_include/general_config.php');
include_once('../include/functions.php');
include_once('../include/config.php');
include_once('../include/HTML_TopBottom.php');
setlocale(LC_ALL, 'nl_NL');
$minUserLevel = 3;
$cfgProgDir = '../auth/';
include($cfgProgDir. "secure.php");
connect_db();

if(isset($_REQUEST['id'])) {
	$dataset = array($_REQUEST['id']);
} elseif(isset($_REQUEST['selectie'])) {
	$groep	= substr($_REQUEST['selectie'], 0, 1);
	$id			= substr($_REQUEST['selectie'], 1);	
	
	if($groep == 'Z') {		
		$opdrachtData	= getOpdrachtData($id);
		$Name					= $opdrachtData['naam'];
		$dataset			= getHuizen($id);
	} else {
		$LijstData		= getLijstData($id);
		$Name					= $LijstData['naam'];
		$dataset			= getLijstHuizen($id);
	}
}

foreach($dataset as $fundaID) {
	$data			= getFundaData($fundaID);	
	
	if($data['plaats'] == 'Deventer' AND $data['offline'] != 1 AND $data['verkocht'] != 1) {
		$urlNaam	= makeHuizenZoekerURL($data['adres']);
		$url			= "http://www.huizenzoeker.nl/koop/overijssel/deventer/$urlNaam/details.html";		
		$Links[] = $url;
		$contents	= file_get_contents_retry($url);
		$Prijshistorie = getString('<!-- Prijshistorie -->', '<!-- /Prijshistorie -->', $contents, 0);
		
		$regels		= explode('</tr>', $Prijshistorie[0]);
		array_pop($regels);
		array_pop($regels);
		
		foreach($regels as $regel) {
			$datum	= getString('<th>', '</th>', $regel, 0);	
			$prijs	= getString('&euro; ', '</td>', $regel, 0);
			
			$Elementen	= explode("-", $datum[0]);
			$Tijdstip = mktime(0, 0, 1, $Elementen[1], $Elementen[0], $Elementen[2]);			
			
			if(strstr($prijs[0], ' (')) {
				$price		= getString('', ' (', $prijs[0], 0);
			} else {
				$price[0]	= $prijs[0];
			}
			
			$price[0]	= str_replace(".", "", $price[0]);
			
			if(updatePrice($fundaID, $price[0], $Tijdstip)) {
				$Links[] = $price[0] .' voor '. $data['adres'] .' toegevoegd<br>';
			}
		}
	} elseif($data['offline'] == 1) {
		$Links[] = $data['adres'] .' is offline<br>';
	} elseif($data['verkocht'] == 1) {
		$Links[] = $data['adres'] .' is verkocht<br>';
	} else {
		$Links[] = $data['adres'] .' geen idee<br>';
	}
//} else {
//	$Links[] = '';
	sleep(3);
}

echo $HTMLHeader;
echo "<tr>\n";
echo "<td width='25%' valign='top' align='center'>&nbsp;</td>\n";
echo "<td width='50%' valign='top' align='center'>\n";
echo showBlock(implode("\n", $Links));
echo "</td>";
echo "<td width='25%' valign='top' align='center'>&nbsp;</td>\n";
echo "</tr>\n";
echo $HTMLFooter;

function makeHuizenZoekerURL($string) {
	$string = strtolower($string);
	$string = str_replace(".", "", $string);
	$string = str_replace(" -", "-", $string);
	$string = str_replace("- ", "-", $string);
	$string = str_replace(" ", "-", $string);
	
	return $string;
}

?>
