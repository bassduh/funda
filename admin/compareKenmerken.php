<?php
include_once('../../general_include/general_functions.php');
include_once('../../general_include/general_config.php');
include_once('../include/functions.php');
include_once('../include/config.php');
include_once('../include/HTML_TopBottom.php');
$minUserLevel = 1;
$cfgProgDir = '../auth/';
include($cfgProgDir. "secure.php");
connect_db();

if(isset($_REQUEST['selectie'])) {
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
	
	# Doorloop alle huizen
	foreach($dataset as $huisID) {
		$kenmerken = getFundaKenmerken($huisID);
		
		# Sommige huizen hebben maar weinig (lees : geen) kenmerken in de database.
		# Die toevoegen in het overzicht is zinloos
		if(count($kenmerken) > 2) {
			$huizen[] = $huisID;
			
			# Doorloop alle kenmerken van het huis
			# Een aantal kenmerken worden uitgesloten omdat die slechts een moment-opname zijn
			foreach($kenmerken as $key => $value) {
				if(	$key != 'descr' AND
						$key != 'foto' AND
						$key != 'Oorspronkelijke vraagprijs' AND
						$key != 'Vraagprijs' AND
						$key != 'Laatste vraagprijs' AND
						$key != 'Verkoopdatum' AND
						$key != 'Aangeboden sinds'
						) {
					$kenmerkenArray[$key][$huisID] = $value;
					$kolom[$key] = 1;
				}
			}
		}
	}
	
	# Sorteer de kenmerken op alfabetische volgorde
	ksort($kolom);
	
	# Maak de de eerste regel aan
	$CSV_kop = array('', 'url', 'Huidige Prijs', 'Orginele Prijs');
	foreach($kolom as $kenmerk => $dummy) {
		if($kenmerk == 'Achtertuin') {
			$CSV_kop[] = $kenmerk;
			$CSV_kop[] = $kenmerk .' (diep)';
			$CSV_kop[] = $kenmerk .' (breed)';
		} else {
			$CSV_kop[] = $kenmerk;
		}
	}	
	$CSV[] = implode(';', $CSV_kop);
	
	# Doorloop alle huizen en geef de waarde van het kenmerk weer
	foreach($huizen as $huisID) {
		$data				= getFundaData($huisID);
		$CSV_regel	= array($data['adres'], 'http://www.funda.nl'.$data['url'], getHuidigePrijs($huisID), getOrginelePrijs($huisID));
		
		foreach($kolom as $kenmerk => $dummy) {
			$string = html_entity_decode($kenmerkenArray[$kenmerk][$huisID]);
			
			if($kenmerk == 'Achtertuin') {			
				$string = str_replace(' m²', '', $string);
				$temp = getString('', '(', $string, 0);						$CSV_regel[] = trim($temp[0]);
				$temp = getString('(', 'm diep', $string, 0);			$CSV_regel[] = trim($temp[0]);
				$temp = getString('en ', 'm breed', $string, 0);	$CSV_regel[] = trim($temp[0]);
			} else {					
				$string = str_replace('m�', '', $string);
				$string = str_replace('m�', '', $string);
				$CSV_regel[] = trim($string);
			}
		}
		$CSV[] = implode(';', $CSV_regel);
	}
	
	header("Expires: Mon, 26 Jul 2001 05:00:00 GMT");
	header("Cache-Control: no-store, no-cache, must-revalidate");
	header("Cache-Control: post-check=0, pre-check=0", false); 
	header("Pragma: no-cache");
	header("Cache-control: private");
	header('Content-type: application/csv');
	header('Content-Disposition: attachment; filename="'.  str_replace(' ', '_', $Name .'-'. strftime ('%d%b %H%M')) .'.txt"');
	echo implode("\n", $CSV);
	
} else {
	$HTML[] = "<form method='post' action='$_SERVER[PHP_SELF]'>";
	$HTML[] = "<table>";
	$HTML[] = "<tr>";
	$HTML[] = "	<td>Selectie</td>";	
	$HTML[] = "	<td>&nbsp;</td>";
	$HTML[] = "	<td>". makeSelectionSelection(isset($_REQUEST['addHouses']), false) ."</td>";
	$HTML[] = "</tr>";
	$HTML[] = "<tr>";
	$HTML[] = "	<td colspan='3' align='center'><input type='submit' name='submit' value='Weergeven'></td>";
	$HTML[] = "</tr>";
	$HTML[] = "</table>";
	$HTML[] = "</form>";
	
	echo $HTMLHeader;
	echo "<tr>\n";
	echo "<td width='25%' valign='top' align='center'>&nbsp;</td>\n";
	echo "<td width='50%' valign='top' align='center'>\n";
	echo showBlock(implode("\n", $HTML));
	echo "</td>\n";
	echo "<td width='25%' valign='top' align='center'>&nbsp;</td>\n";
	echo "</tr>\n";
	echo $HTMLFooter;
}
?>