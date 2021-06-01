<?php
/**
 ***********************************************************************************************
 * Neuberechnung der Mitgliedsbeitraege
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * 
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * mode       : preview - preview of the new mandate ids
 *              write   - save the new mandate ids
 *              print   - preview for printing  
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');

// only authorized user are allowed to start this module
if (!isUserAuthorized($_SESSION['pMembershipFee']['script_name']))
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Initialize and check the parameters
$getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'preview', 'validValues' => array('preview', 'write', 'print')));

$pPreferences = new ConfigTablePMB();
$pPreferences->read();

// set headline of the script
$headline = $gL10n->get('PLG_MITGLIEDSBEITRAG_RECALCULATION');

$gNavigation->addUrl(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/mitgliedsbeitrag.php', array('show_option' => 'recalculation')));
$gNavigation->addUrl(CURRENT_URL);

if ($getMode == 'preview')     //Default
{
    $page = new HtmlPage('plg-mitgliedsbeitrag-recalculation-preview', $headline);

	$members = array();
	$message = '';
	
	// anstelle eines Leerzeichens ist ein # in der $pPreferences->config gespeichert; # wird hier wieder ersetzt
	$text_token = ($pPreferences->config['Beitrag']['beitrag_text_token'] == '#') ? ' ' : $pPreferences->config['Beitrag']['beitrag_text_token'];
	
	//alle Beitragsrollen einlesen
	$contributingRolls = beitragsrollen_einlesen('', array('FIRST_NAME', 'LAST_NAME', 'IBAN', 'DEBTOR'));
	
	//pruefen, ob Eintraege in der Rollenauswahl bestehen
	if (isset($_POST['recalculation_roleselection']) )
	{
		$_SESSION['pMembershipFee']['recalculation_rol_sel'] = $_POST['recalculation_roleselection'];
	
		// nicht gewaehlte Beitragsrollen im Array $contributingRolls loeschen
		$message .= '<strong>'.$gL10n->get('SYS_NOTE').':</strong> '.$gL10n->get('PLG_MITGLIEDSBEITRAG_RECALCULATION_ROLLQUERY_INFO').'</strong><br/><br/>';
		foreach ($contributingRolls as $rol => $roldata)
		{
			if (!in_array($rol, $_POST['recalculation_roleselection']))
			{
				unset($contributingRolls[$rol]);
			}
			else
			{
				$message .= '- '.$roldata['rolle'].'<br/>';
			}
		}
		
		// Rollendaten aufbereiten fuer list_members()
		$selectionRolls = array();
		$role = new TableRoles($gDb);
		foreach ($_POST['recalculation_roleselection'] as $rol)
		{
			$role->readDataById($rol);
			$selectionRolls[$role->getValue('rol_name')] = 0;
		}
	}
	else
	{
		$selectionRolls = 0;
		unset($_SESSION['pMembershipFee']['recalculation_rol_sel']);
	}
	
	// diese Rollen durchlaufen und bei den Familienrollen eine Zahlungspflichtigen bestimmen
	foreach ($contributingRolls as $rol => $roldata)
	{
		// nur Familien
		if ($roldata['rollentyp'] == 'fam')
		{
			// alle Mitglieder dieser Rolle durchlaufen und einen Zahlungspflichtigen bestimmen
			// 1. Durchlauf: hierbei das erste Mitglied bei dem (Kontonummer UND BLZ) oder IBAN belegt sind bestimmen
			foreach ($roldata['members'] as $key => $data)
			{
				$contributingRolls[$rol]['has_to_pay'] = $key;
	
				if(strlen($data['IBAN']) !== 0)
				{
					$contributingRolls[$rol]['has_to_pay'] = $key;
					break;
				}
			}
			// alle Mitglieder dieser Rolle durchlaufen und einen Zahlungspflichtigen bestimmen
			// 2. Durchlauf: gibt es einen Rollenleiter, dann den Zahlungspflichtigen ueberschreiben, da hoeherwertiger
			foreach ($roldata['members'] as $key => $data)
			{
				if (isGroupLeader($key, $rol))
				{
					$contributingRolls[$rol]['has_to_pay'] = $key;
					break;
				}
			}
		}
	}
	
	// alle aktiven Mitglieder einlesen
	$members = list_members(array('FIRST_NAME', 'LAST_NAME', 'FEE'.ORG_ID, 'CONTRIBUTORY_TEXT'.ORG_ID, 'PAID'.ORG_ID, 'ACCESSION'.ORG_ID, 'DEBTOR'), $selectionRolls);
	
	//alle Mitglieder durchlaufen und aufgrund von Rollenzugehoerigkeiten die Beitraege bestimmen
	foreach ($members as $member => $memberdata)
	{
		$members[$member]['FEE_NEW'] = 0;
		$members[$member]['CONTRIBUTORY_TEXT_NEW'] = '';
	
		foreach ($contributingRolls as $rol => $roldata)
		{
			// alle Rollen, außer Familienrollen
			if (($roldata['rollentyp'] != 'fam') && (array_key_exists($member, $roldata['members'])))
			{
				if($pPreferences->config['Beitrag']['beitrag_anteilig'] == true)
				{
					$members[$member]['ACCESSION'.ORG_ID] = $roldata['members'][$member]['mem_begin'];
				}
	
				if($pPreferences->config['Beitrag']['beitrag_anteilig'] == true)
				{
					$time_begin = strtotime($roldata['members'][$member]['mem_begin']);
				}
				else
				{
					$time_begin = strtotime($members[$member]['ACCESSION'.ORG_ID]);
				}
	
				// das Standarddatum '9999-12-31' kann auf best. Systemen nicht verarbeitet werden
				if($roldata['members'][$member]['mem_end'] == '9999-12-31')
				{
					$time_end = strtotime('2038-01-19');
				}
				else
				{
					$time_end = strtotime($roldata['members'][$member]['mem_end']);
				}
	
				// anteiligen Beitrag berechnen, falls das Mitglied im aktuellen Jahr ein- oder ausgetreten ist
				// && Beitragszeitraum (cost_period) darf nicht "Einmalig" (-1) sein
				// && Beitragszeitraum (cost_period) darf nicht "Jaehrlich" (1) sein
				if ((strtotime(date('Y').'-01-01') < $time_begin || $time_end < strtotime(date('Y').'-12-31'))
						&& ($roldata['rol_cost_period'] != -1)
						&& ($roldata['rol_cost_period'] != 1))
				{
	
					if (strtotime(date('Y').'-01-01') <  $time_begin)
					{
						$month_begin = date('n', $time_begin);
					}
					else
					{
						$month_begin = 1;
					}
					if (strtotime(date('Y').'-12-31') >  $time_end)
					{
						$month_end   = date('n', $time_end);
					}
					else
					{
						$month_end = 12;
					}
	
					$segment_begin = ceil($month_begin * $roldata['rol_cost_period']/12);
					$segment_end = ceil($month_end * $roldata['rol_cost_period']/12);
	
					$members[$member]['FEE_NEW'] +=  ($segment_end - $segment_begin +1) * $roldata['rol_cost'] / $roldata['rol_cost_period'];
					if ($roldata['rol_description'] != '')
					{
						$members[$member]['CONTRIBUTORY_TEXT_NEW'] .= ' '.$roldata['rol_description'].' ';
					}
					if ($pPreferences->config['Beitrag']['beitrag_suffix'] != '')
					{
						$members[$member]['CONTRIBUTORY_TEXT_NEW'] .= ' '.$pPreferences->config['Beitrag']['beitrag_suffix'].' ';
					}
					// nur einmal soll beitrag_suffix angezeigt werden, wenn aber rol_description leer ist,
					// wird es mehrfach hintereinander mit vielen Leerzeichen dazwischen angefuegt, deshalb ersetzen
					//zuerst zwei aufeinanderfolgende Leerzeichen durch ein Leerzeichen ersetzen
					$members[$member]['CONTRIBUTORY_TEXT_NEW'] = str_replace('  ', ' ', $members[$member]['CONTRIBUTORY_TEXT_NEW']);
					//jetzt mehrfache beitrag_suffix loeschen
					$members[$member]['CONTRIBUTORY_TEXT_NEW'] = str_replace($pPreferences->config['Beitrag']['beitrag_suffix'].' '.$pPreferences->config['Beitrag']['beitrag_suffix'], $pPreferences->config['Beitrag']['beitrag_suffix'], $members[$member]['CONTRIBUTORY_TEXT_NEW']);
				}
				else                             //keine anteilige Berechnung
				{
					$members[$member]['FEE_NEW'] += $roldata['rol_cost'];
					if ($roldata['rol_description'] != '')
					{
						$members[$member]['CONTRIBUTORY_TEXT_NEW'] .= ' '.$roldata['rol_description'].' ';
					}
				}
			}
		}
	
		// wenn definiert: Beitragstext mit dem Namen des Benutzers
		if(($pPreferences->config['Beitrag']['beitrag_textmitnam'] == true)
				&&  ($members[$member]['FEE_NEW'] != 0)
				&&  !(($members[$member]['LAST_NAME'].' '.$members[$member]['FIRST_NAME'] == $members[$member]['DEBTOR'])
						|| ($members[$member]['FIRST_NAME'].' '.$members[$member]['LAST_NAME'] == $members[$member]['DEBTOR'])
						|| (empty($members[$member]['DEBTOR']))))
		{
			$members[$member]['CONTRIBUTORY_TEXT_NEW'] .= $text_token.$members[$member]['LAST_NAME'].' '.$members[$member]['FIRST_NAME'].$text_token;
		}
	}
	
	// alle Rollen und deren Mitglieder durchlaufen  und die Beitraege eines Mitglieds,
	// das zudem ein Familienmitglied ist, dem Zahlungspflichtigen der Familie zugeschlagen
	foreach ($contributingRolls as $rol => $roldata)
	{
		// nur Rollen mit dem Praefix einer Familie && die Familienrolle muß Mitglieder aufweisen
		if (($roldata['rollentyp'] == 'fam') && (count($roldata['members']) > 0))
		{
			// wenn definiert: Beitragstext mit allen Familienmitgliedern
			if($pPreferences->config['Beitrag']['beitrag_textmitfam'] == true)
			{
				$members[$roldata['has_to_pay']]['CONTRIBUTORY_TEXT_NEW'] .= ' ';
				foreach ($roldata['members'] as $member => $memberdata)
				{
					$members[$roldata['has_to_pay']]['CONTRIBUTORY_TEXT_NEW'] .= $text_token.$members[$member]['LAST_NAME'].' '.$members[$member]['FIRST_NAME'];
				}
				$members[$roldata['has_to_pay']]['CONTRIBUTORY_TEXT_NEW'] .= $text_token.' ';
			}
	
			//alle Mitglieder dieser Rolle durchlaufen und die Beitraege der Mitglieder dem Zahlungspflichtigen zuordnen
			foreach ($roldata['members'] as $member => $memberdata)
			{
				// nicht beim Zahlungspflichtigen selber und auch nur, wenn ein Zusatzbeitrag beim Mitglied errechnet wurde
				if  (($roldata['has_to_pay'] != $member) && ($members[$member]['FEE_NEW'] > 0))
				{
					$members[$roldata['has_to_pay']]['FEE_NEW'] += $members[$member]['FEE_NEW'];
					$members[$member]['FEE_NEW'] = 0;
					$members[$roldata['has_to_pay']]['CONTRIBUTORY_TEXT_NEW'] .= $members[$member]['CONTRIBUTORY_TEXT_NEW'].' ';
	
					// wenn nicht definiert: Beitragstext mit allen Familienmitgliedern, trotzdem Name und Vorname anfuegen
					if(!$pPreferences->config['Beitrag']['beitrag_textmitnam'])
					{
						$members[$roldata['has_to_pay']]['CONTRIBUTORY_TEXT_NEW'] .= $text_token.$memberdata['LAST_NAME'].' '.$memberdata['FIRST_NAME'].$text_token.' ';
					}
					$members[$member]['CONTRIBUTORY_TEXT_NEW'] = '';
				}
			}
	
			// anteiligen Beitrag berechnen, falls die Familie erst im aktuellen Jahr angelegt wurde
			// && Beitragszeitraum (cost_period) darf nicht "Einmalig" (-1) sein
			// && Beitragszeitraum (cost_period) darf nicht "Jaehrlich" (1) sein
			if ((date('Y') == date('Y', strtotime($roldata['rol_timestamp_create']))) && ($roldata['rol_cost_period'] != -1) && ($roldata['rol_cost_period'] != 1))
			{
				$beitrittsmonat = date('n', strtotime($roldata['rol_timestamp_create']));
				$members[$roldata['has_to_pay']]['FEE_NEW'] +=  (($roldata['rol_cost_period']+1)-ceil($beitrittsmonat/(12/$roldata['rol_cost_period'])))*($roldata['rol_cost']/$roldata['rol_cost_period']);
				$members[$roldata['has_to_pay']]['CONTRIBUTORY_TEXT_NEW'] = ' '.$roldata['rol_description'].' '.$pPreferences->config['Beitrag']['beitrag_suffix'].' '.$members[$roldata['has_to_pay']]['CONTRIBUTORY_TEXT_NEW'].' ';
			}
			else
			{
				$members[$roldata['has_to_pay']]['FEE_NEW'] += $roldata['rol_cost'];
				$members[$roldata['has_to_pay']]['CONTRIBUTORY_TEXT_NEW'] = ' '.$roldata['rol_description'].$members[$roldata['has_to_pay']]['CONTRIBUTORY_TEXT_NEW'].' ';
			}
		}
	}
	
	foreach ($members as $member => $memberdata)
	{
		// letzte Datenaufbereitung (aussummieren, ueberschreiben, runden...)
		if ((is_null($members[$member]['FEE'.ORG_ID])
				||  (!(is_null($members[$member]['FEE'.ORG_ID]))
				    && (isset($_POST['recalculation_modus']) && (($_POST['recalculation_modus'] == 'overwrite') || ($_POST['recalculation_modus'] == 'summation')))
				    )
		    )
				&& ($members[$member]['FEE_NEW'] > $pPreferences->config['Beitrag']['beitrag_mindestbetrag']))
		{
			$members[$member]['CONTRIBUTORY_TEXT_NEW'] =  $pPreferences->config['Beitrag']['beitrag_prefix'].' '.$members[$member]['CONTRIBUTORY_TEXT_NEW'].' ';
	
			// alle Beitraege auf 2 Nachkommastellen runden
			$members[$member]['FEE_NEW'] = round($members[$member]['FEE_NEW'], 2);
	
			//ggf. abrunden
			if ($pPreferences->config['Beitrag']['beitrag_abrunden'] == true)
			{
				$members[$member]['FEE_NEW'] = floor($members[$member]['FEE_NEW']);
			}
	
			if (isset($_POST['recalculation_modus']) && $_POST['recalculation_modus'] == 'summation')
			{
				$members[$member]['FEE_NEW'] += $members[$member]['FEE'.ORG_ID];
				$members[$member]['CONTRIBUTORY_TEXT_NEW'] .= ' '.$members[$member]['CONTRIBUTORY_TEXT'.ORG_ID].' ';
			}
	
			//fuehrende und nachfolgene Leerstellen im Beitragstext loeschen
			$members[$member]['CONTRIBUTORY_TEXT_NEW'] = trim($members[$member]['CONTRIBUTORY_TEXT_NEW']);
			//zwei aufeinanderfolgende Leerzeichen durch ein Leerzeichen ersetzen
			$members[$member]['CONTRIBUTORY_TEXT_NEW'] = str_replace('  ', ' ', $members[$member]['CONTRIBUTORY_TEXT_NEW']);
		}
		else 
		{
			unset($members[$member]);        //wenn kein neuer Beitrag errechnet wurde, dann dieses Mitglied in der Liste loeschen
		}
	}
	
	if (sizeof($members) > 0)
	{
		// save members in session (for mode write and mode print)
		$_SESSION['pMembershipFee']['recalculation_user'] = $members;
	
		$datatable = true;
		$hoverRows = true;
		$classTable  = 'table table-condensed';
		$table = new HtmlTable('table_new_recalculation', $page, $hoverRows, $datatable, $classTable);
		$table->setColumnAlignByArray(array('left', 'left', 'center', 'center', 'center','center'));
		$columnValues = array($gL10n->get('SYS_LASTNAME'), 
							  $gL10n->get('SYS_FIRSTNAME'), 
							  $gL10n->get('PLG_MITGLIEDSBEITRAG_FEE_NEW'),
							  $gL10n->get('PLG_MITGLIEDSBEITRAG_CONTRIBUTORY_TEXT_NEW'),
							  $gL10n->get('PLG_MITGLIEDSBEITRAG_FEE_PREVIOUS'),
							  $gL10n->get('PLG_MITGLIEDSBEITRAG_CONTRIBUTORY_TEXT_PREVIOUS'));
		$table->addRowHeadingByArray($columnValues);

		foreach ($members as $member => $data)
		{
			$columnValues = array();
			$columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_id' => $member)).'">'.$data['LAST_NAME'].'</a>';
			$columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_id' => $member)).'">'.$data['FIRST_NAME'].'</a>';
			$columnValues[] = $data['FEE_NEW'];
			$columnValues[] = $data['CONTRIBUTORY_TEXT_NEW'];
			$columnValues[] = $data['FEE'.ORG_ID];
			$columnValues[] = $data['CONTRIBUTORY_TEXT'.ORG_ID];
			$table->addRowByArray($columnValues);
		}

		$page->addHtml($table->show(false));

        $form = new HtmlForm('recalculation_preview_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/recalculation.php', array('mode' => 'write')), $page);
		$form->addSubmitButton('btn_next_page', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => 'btn btn-primary'));
		$form->addDescription($gL10n->get('PLG_MITGLIEDSBEITRAG_RECALCULATION_PREVIEW'));
        
        $page->addHtml($form->show(false));
	}
	else 
	{
        $page->addHtml($gL10n->get('PLG_MITGLIEDSBEITRAG_RECALCULATION_NO_DATA').'<br/>');
	}
	
	if (!empty($message))
	{
        $page->addHtml($message);
	}
}
elseif ($getMode == 'write')
{
    $page = new HtmlPage('plg-mitgliedsbeitrag-recalculation-write', $headline);

 	$page->addPageFunctionsMenuItem('menu_item_print_view', $gL10n->get('SYS_PRINT_PREVIEW'), 'javascript:void(0);', 'fa-print');
        
	$page->addJavascript('
    	$("#menu_item_print_view").click(function() {
            window.open("'. SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/recalculation.php', array('mode' => 'print')). '", "_blank");
        });',
		true
	);
	
	$datatable = false;
	$hoverRows = true;
	$classTable  = 'table table-condensed';
    
	$table = new HtmlTable('table_saved_recalculation', $page, $hoverRows, $datatable, $classTable);
	$table->setColumnAlignByArray(array('left', 'left', 'center', 'center'));
	$columnValues = array($gL10n->get('SYS_LASTNAME'),
						  $gL10n->get('SYS_FIRSTNAME'),
						  $gL10n->get('PLG_MITGLIEDSBEITRAG_FEE_NEW'),
						  $gL10n->get('PLG_MITGLIEDSBEITRAG_CONTRIBUTORY_TEXT_NEW'));
	$table->addRowHeadingByArray($columnValues);
	
	$user = new User($gDb, $gProfileFields);
	
	foreach ($_SESSION['pMembershipFee']['recalculation_user'] as $member => $data)
	{
		$columnValues = array();
		$columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_id' => $member)).'">'.$data['LAST_NAME'].'</a>';
		$columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_id' => $member)).'">'.$data['FIRST_NAME'].'</a>';
		$columnValues[] = $data['FEE_NEW'];
		$columnValues[] = $data['CONTRIBUTORY_TEXT_NEW'];
		$table->addRowByArray($columnValues);
		
		$user->readDataById($member);
		$user->setValue('FEE'.ORG_ID, $data['FEE_NEW']);
		$user->setValue('CONTRIBUTORY_TEXT'.ORG_ID, $data['CONTRIBUTORY_TEXT_NEW']);
		$user->save();         
	}
	
	$page->addHtml('<div style="width:100%; height: 500px; overflow:auto; border:20px;">');
	$page->addHtml($table->show(false));
	$page->addHtml('</div><br/>');
	$page->addHtml('<strong>'.$gL10n->get('PLG_MITGLIEDSBEITRAG_RECALCULATION_SAVED').'</strong><br/><br/>');
}
elseif ($getMode == 'print')
{
	// create html page object without the custom theme files
	$hoverRows = false;
	$datatable = false;
	$classTable  = 'table table-condensed table-striped';

	$page = new HtmlPage('plg-mitgliedsbeitrag-recalculation-print', $gL10n->get('PLG_MITGLIEDSBEITRAG_RECALCULATION_NEW'));
	$page->setPrintMode();
	
	$table = new HtmlTable('table_print_recalculation', $page, $hoverRows, $datatable, $classTable);
	$table->setColumnAlignByArray(array('left', 'left', 'center', 'center'));
	$columnValues = array($gL10n->get('SYS_LASTNAME'),
						  $gL10n->get('SYS_FIRSTNAME'),
						  $gL10n->get('PLG_MITGLIEDSBEITRAG_FEE_NEW'),
						  $gL10n->get('PLG_MITGLIEDSBEITRAG_CONTRIBUTORY_TEXT_NEW'));
	$table->addRowHeadingByArray($columnValues);
	
	foreach ($_SESSION['pMembershipFee']['recalculation_user'] as $data)
	{
		$columnValues = array();
		$columnValues[] = $data['LAST_NAME'];
		$columnValues[] = $data['FIRST_NAME'];
		$columnValues[] = $data['FEE_NEW'];
		$columnValues[] = $data['CONTRIBUTORY_TEXT_NEW'];
		$table->addRowByArray($columnValues);
	}
	$page->addHtml($table->show(false));
}

$page->show();


