<?php
include_once(__DIR__.'/../include/config.php');
include_once('../include/HTML_TopBottom.php');
connect_db();

# Omdat deze via een cronjob door de server wordt gedraaid is deze niet beveiligd
# Iedereen kan deze pagina dus in principe openen.

# http://stackoverflow.com/questions/9049460/cron-jobs-and-random-times-within-given-hours/16289693
# Om te zorgen dat de pagina op wisselende tijden wordt geopend heb ik de volgende cronjob opgenomen :
#		sleep $[RANDOM\%3660] ; wget -q -O /dev/null http://example.com/funda/admin/getVerkochteHuizen.php

# Als er een OpdrachtID is meegegeven hoeft alleen die uitgevoerd te worden.
# In alle andere gevallen gewoon alle actieve zoekopdrachten
if(isset($_REQUEST[OpdrachtID])) {
	$Opdrachten = array($_REQUEST[OpdrachtID]);
	$enkeleOpdracht = true;
} else {
	$Opdrachten = getRandomOpdracht();
	$enkeleOpdracht = false;
}

$debug = 0;

# Doorloop alle zoekopdrachten
foreach($Opdrachten as $OpdrachtID) {	
	$OpdrachtData			= getOpdrachtData($OpdrachtID);
	
	$OpdrachtURL	= "http://partnerapi.funda.nl/feeds/Aanbod.svc/json/$fundaAPI/?type=koop&zo=/verkocht". str_replace ("http://www.funda.nl/koop", "", $OpdrachtData['url']) ."&pagesize=15";
	toLog('info', $OpdrachtID, '', 'Start controle verkochte huizen '. $OpdrachtData['naam']);
	
	$NrPaginas = 1;
	
	for($p=1 ; $p <= $NrPaginas ; $p++) {
		set_time_limit (30);
		
		$PageURL	= $OpdrachtURL.'&page='.$p;
	
		# Vraag de pagina op en herhaal dit het standaard aantal keer mocht het niet lukken
		$contents	= file_get_contents_retry($PageURL);
	
		$JSONArray = json_decode($contents, true);
	
		// = $JSONArray['AccountStatus'];
		// = $JSONArray['EmailNotConfirmed'];
		// = $JSONArray['ValidationFailed'];
		// = $JSONArray['ValidationReport'];
		// = $JSONArray['Website'];
		// = $JSONArray['Metadata'];
		$Huizen = $JSONArray['Objects'];
		$Paginas = $JSONArray['Paging'];
		$NrHuizen = $JSONArray['TotaalAantalObjecten'];
		
		$NrPageHuizen	= count($Huizen);
		$NrPaginas = $Paginas['AantalPaginas'];
				
		if($p == 1) {			
			$block[] = "<a href='$OpdrachtURL'>". $OpdrachtData['naam'] ."</a> -> <a href='". $OpdrachtData['url'] ."'>". $NrHuizen ." huizen</a><br>\n";
		}
	}
}

/*

	
	$HTML = array();
	$HTML[] = "Zoekopdracht <a href='$OpdrachtURL'>". $OpdrachtData['naam'] ."</a>\n<p>\n";
	
	while($nextPage) {
		set_time_limit (60);
		$p++;
		
		$PageURL	= $OpdrachtURL.'p'.$p.'/';
		$contents	= file_get_contents_retry($PageURL, 5);
		
		$HTML[] = "<a href='$PageURL'>pagina $p</a><br>\n";
		
		if(is_numeric(strpos($contents, "paging next")) AND $debug == 0) {
			$nextPage = true;
		} else {
			$nextPage = false;
		}
		
		# Op funda.nl staan huizen van verschillende makkelaars-organisaties (NVM, VBO, etc.)
		# Voor elke organisatie wordt een andere class uit de style-sheet gebruikt
		# Deze class geeft precies het begin van een nieuw huis op de overzichtspagina aan
		# Om zeker te zijn dat ik alle huizen vind doe ik eerst alsof �lle huizen van NVM zijn,
		# dan of �lle huizen van VBO zijn, etc.
		$HuizenNVM		= explode(' nvm sold" >', $contents);			array_shift($HuizenNVM);
		$HuizenNVMlst	= explode(' nvm sold lst" >', $contents);	array_shift($HuizenNVMlst);
		$HuizenVBO		= explode(' vbo sold" >', $contents);			array_shift($HuizenVBO);
		$HuizenVBOlst	= explode(' vbo sold lst" >', $contents);	array_shift($HuizenVBOlst);
		$HuizenLMV		= explode(' lmv sold" >', $contents);			array_shift($HuizenLMV);
		$HuizenLMVlst	= explode(' lmv sold lst" >', $contents);	array_shift($HuizenLMVlst);
		$HuizenExt		= explode(' ext sold" >', $contents);			array_shift($HuizenExt);
		$HuizenExtlst	= explode(' ext sold lst" >', $contents);	array_shift($HuizenExtlst);
		$Huizen				= array_merge($HuizenNVM, $HuizenNVMlst, $HuizenVBO, $HuizenVBOlst, $HuizenLMV, $HuizenLMVlst, $HuizenExt, $HuizenExtlst);
		
		$Huizen			= array_slice($Huizen, 1);
								
		# Doorloop nu alle gevonden huizen op de overzichtspagina
		foreach($Huizen as $HuisText) {
			# Extraheer hier adres, plaats, prijs, id etc. uit
			$data			= extractFundaData_old($HuisText, true);
			$fundaID	= $data['id'];
			$url			= "http://www.funda.nl". urldecode($data['url']);
			$new			= false;
						
			
			# Mochten huizen verkocht zijn die nog niet bekend zijn in de dB.
			# Voeg deze dan toe als addSoldHouses = true
			if(!knownHouse($fundaID) AND $addSoldHouses) {
				$allData = extractDetailedFundaData($url, false);
				$extraData = $allData[1];
				saveHouse($data, $extraData);
				addHouse($data, $OpdrachtID);				
				addCoordinates($data['adres'], $data['PC_c'], $data['plaats'], $fundaID);
				
				$HTML[] = '<b>'. urldecode($data['adres']) ."</b> (<a href='". $data['url'] ."'>url</a>, ". urldecode($data['plaats']) .")<br>";
				$HTML[] = '-> toegevoegd<br>';
				$new = true;
			} elseif(newHouse($fundaID, $OpdrachtID) AND $addSoldHouses) {				
				if(!addHouse($data, $OpdrachtID)) {
					$HTML[] = "Toevoegen van ". $data['adres'] ." aan opdracht $OpdrachtID ging niet goed";
					toLog('error', $OpdrachtID, $data['id'], 'Huis toekennen aan opdracht mislukt');
				}
			}
			
			# Als huis niet als verkocht in de dB staat, maar wel online, dan moet de dB worden bijgewerkt
			if(knownHouse($fundaID) AND !soldHouse($fundaID)) {
				$FundaData	= getFundaData($data['id']);			
				if(!$new) {
					$HTML[] = '<b>'. urldecode($data['adres']) ."</b> (<a href='$url'>url</a>, ". urldecode($FundaData['plaats']) .")<br>";
					$HTML[] = "[van ". date("d-m-Y", $FundaData['start']) ." tot ". date("d-m-Y", $FundaData['eind']) ."]<br>";
				}
				$HTML_temp = extractAndUpdateVerkochtData($data['id'], $OpdrachtID);
				$HTML = array_merge($HTML, $HTML_temp);
				$HTML[] = "<br>\n";
			}
		}
		
	}
	$block[] = implode("\n", $HTML);
}
*/

# Laat de resultaten van de check netjes op het scherm zien.
$tweeKolom = false;
echo $HTMLHeader;
echo "<tr>\n";
echo "<td width='50%' valign='top' align='center'>\n";

foreach($block as $key => $value) {
	echo showBlock($value);
	echo '<p>';	
	if($key >= (count($block)/2 - 1) AND !$tweeKolom) {
		echo "</td><td width='50%' valign='top' align='center'>\n";
		$tweeKolom = true;
	}
}
echo "</td>\n";
echo "</tr>\n";
echo $HTMLFooter;
