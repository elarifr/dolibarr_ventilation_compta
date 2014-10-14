<?php
/* Copyright (C) 2007-2010 Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2007-2010 Jean Heimburger			<jean@tiaris.info>
 * Copyright (C) 2011		   Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2012		   Regis Houssin		<regis@dolibarr.fr>
 * Copyright (C) 2013		   Christophe Battarel	<christophe.battarel@altairis.fr>
 * Copyright (C) 2013-2014 Alexandre Spangaro		<alexandre.spangaro@gmail.com>
 * Copyright (C) 2013-2014 Florian Henry			<florian.henry@open-concept.pro>
 * Copyright (C) 2013-2014 Olivier Geffroy			<jeff@jeffinfo.com>
 * Copyright (C) 2014      Ari Elbaz (elarifr)		<github@accedinfo.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file		accountingex/journal/sellsjournal.php
 * \ingroup		Accounting Expert
 * \brief		Page with sells journal
 */

// Dolibarr environment
$res = @include ("../main.inc.php");
if (! $res && file_exists("../main.inc.php"))
	$res = @include ("../main.inc.php");
if (! $res && file_exists("../../main.inc.php"))
	$res = @include ("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php"))
	$res = @include ("../../../main.inc.php");
if (! $res)
	die("Include of main fails");
	
	// Class
dol_include_once("/core/lib/report.lib.php");
dol_include_once("/core/lib/date.lib.php");
dol_include_once("/accountingex/core/lib/account.lib.php");
dol_include_once("/compta/facture/class/facture.class.php");
dol_include_once("/societe/class/client.class.php");
dol_include_once("/accountingex/class/bookkeeping.class.php");
dol_include_once("/accountingex/class/accountingaccount.class.php");

// Langs
$langs->load("compta");
$langs->load("bills");
$langs->load("other");
$langs->load("main");
$langs->load("accountingex@accountingex");

$date_startmonth = GETPOST('date_startmonth');
$date_startday = GETPOST('date_startday');
$date_startyear = GETPOST('date_startyear');
$date_endmonth = GETPOST('date_endmonth');
$date_endday = GETPOST('date_endday');
$date_endyear = GETPOST('date_endyear');

// Security check
if ($user->societe_id > 0)
	accessforbidden();
if (! $user->rights->accountingex->access)
	accessforbidden();

$action = GETPOST('action');

/*
 * View sql
 */

$year_current = strftime("%Y", dol_now());
$pastmonth = strftime("%m", dol_now()) - 1;
$pastmonthyear = $year_current;
if ($pastmonth == 0) {
	$pastmonth = 12;
	$pastmonthyear --;
}

$date_start = dol_mktime(0, 0, 0, $date_startmonth, $date_startday, $date_startyear);
$date_end = dol_mktime(23, 59, 59, $date_endmonth, $date_endday, $date_endyear);

if (empty($date_start) || empty($date_end)) // We define date_start and date_end
{
	$date_start = dol_get_first_day($pastmonthyear, $pastmonth, false);
	$date_end = dol_get_last_day($pastmonthyear, $pastmonth, false);
}

$p = explode(":", $conf->global->MAIN_INFO_SOCIETE_COUNTRY);
$idpays = $p[0];

//	$ressqlarray=@include("sellsjournal-sqlarray.php");
//	if (! $ressqlarray) die("Include of exportjournal-sqlarray.php fails in accountingex/journal");
// Should be Moved in sellsjournal-sqlarray.php
$sql  = "SELECT f.rowid, f.facnumber, f.type, f.datef as df, f.ref_client, f.date_lim_reglement,";
$sql .= " fd.rowid as fdid, fd.description, fd.product_type, fd.total_ht, fd.total_tva, fd.tva_tx, fd.total_ttc,";
$sql .= " s.rowid as socid, s.nom as name, s.code_compta, s.code_client,";
$sql .= " p.rowid as pid, p.ref as pref, p.accountancy_code_sell, aa.rowid as fk_compte, aa.account_number as compte, aa.label as label_compte, ";
$sql .= " ct.accountancy_code_sell as account_tva";
$sql .= " FROM " . MAIN_DB_PREFIX . "facturedet fd";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = fd.fk_product";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "accountingaccount aa ON aa.rowid = fd.fk_code_ventilation";
$sql .= " JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = fd.fk_facture";
$sql .= " JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "c_tva ct ON fd.tva_tx = ct.taux AND ct.fk_pays = '" . $idpays . "'";
$sql .= " WHERE fd.fk_code_ventilation > 0 ";
if (! empty($conf->multicompany->enabled)) {
	$sql .= " AND f.entity = " . $conf->entity;
}
$sql .= " AND f.fk_statut > 0";
if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS))
	$sql .= " AND f.type IN (0,1,2)";
else
	$sql .= " AND f.type IN (0,1,2,3)";
$sql .= " AND fd.product_type IN (0,1)";
if ($date_start && $date_end)
	$sql .= " AND f.datef >= '" . $db->idate($date_start) . "' AND f.datef <= '" . $db->idate($date_end) . "'";
$sql .= " ORDER BY f.datef";

dol_syslog('accountingex/journal/sellsjournal.php:: $sql=' . $sql);
$result = $db->query($sql);
if ($result) {
	$tabfac = array ();
	$tabht = array ();
	$tabtva = array ();
	$tabttc = array ();
	$tabcompany = array ();
	
	$num = $db->num_rows($result);
	$i = 0;
	$resligne = array ();
	while ( $i < $num ) {
		$obj = $db->fetch_object($result);
		// les variables
		$cptcli = (! empty($conf->global->COMPTA_ACCOUNT_CUSTOMER)) ? $conf->global->COMPTA_ACCOUNT_CUSTOMER : $langs->trans("CodeNotDef");
		$compta_soc = (! empty($obj->code_compta)) ? $obj->code_compta : $cptcli;
		
		$compta_prod = $obj->compte;
		if (empty($compta_prod)) {
			if ($obj->product_type == 0)
				$compta_prod = (! empty($conf->global->COMPTA_PRODUCT_SOLD_ACCOUNT)) ? $conf->global->COMPTA_PRODUCT_SOLD_ACCOUNT : $langs->trans("CodeNotDef");
			else
				$compta_prod = (! empty($conf->global->COMPTA_SERVICE_SOLD_ACCOUNT)) ? $conf->global->COMPTA_SERVICE_SOLD_ACCOUNT : $langs->trans("CodeNotDef");
		}
		$cpttva = (! empty($conf->global->COMPTA_VAT_ACCOUNT)) ? $conf->global->COMPTA_VAT_ACCOUNT : $langs->trans("CodeNotDef");
		$compta_tva = (! empty($obj->account_tva) ? $obj->account_tva : $cpttva);
		
		// la ligne facture
		$tabfac[$obj->rowid]["date"] = $obj->df;
		$tabfac[$obj->rowid]["ref"] = $obj->facnumber;
		$tabfac[$obj->rowid]["type"] = $obj->type;
		$tabfac[$obj->rowid]["date_lim_reglement"] = $obj->date_lim_reglement;
		$compte = new AccountingAccount($db);
		$resultcompte=$compte->fetch($obj->fk_compte);
		if ($resultcompte) {
			$sell_detail_label_compte = $obj->label_compte;
		} else {
			$sell_detail_label_compte = $langs->trans("CodeNotDef");
		}
		if ( $conf->global->ACCOUNTINGEX_SELL_DETAILED ==0 ) {
		/////////////////////////////////////////////////////////////////////
//		if (!empty($conf->global->ACCOUNTINGEX_GROUPBYACCOUNT)) {
			$tabfac[$obj->rowid]["description"] = $sell_detail_label_compte;
		} else {
			switch ($conf->global->ACCOUNTINGEX_SELL_DETAILED_DESC) {
				case 0:		// libelle compte comptable
					$sell_detail = $sell_detail_label_compte;
				break;
				case 1:		// libelle reference produit
					$sell_detail = $obj->pref;
				break;
				case 2:		// libelle description
					$sell_detail = $obj->description;
				break;
				case 3:		// libelle ref + description
					$sell_detail = $obj->pref . ' - ' . $obj->description;
				break;
				case 99:		// debug info
					if ($obj->product_type == 0){$sell_detail = $langs->trans("Products");} else {$sell_detail= $langs->trans("Services");}
					$sell_detail .= '['. $obj->product_type .'] - ('. $obj->pref . ') - '. $obj->description;
				break;
			}
			$tabfac[$obj->rowid]["description"][$obj->fdid]  = accountingex_clean_desc ($sell_detail);
		}
		$tabfac[$obj->rowid]["fk_facturedet"] = $obj->fdid;
		if (! isset($tabttc[$obj->rowid][$compta_soc]))
			$tabttc[$obj->rowid][$compta_soc] = 0;
		/////////////////////////////////////////////////////////////////////
		if ( $conf->global->ACCOUNTINGEX_SELL_DETAILED ==0 ) {
//		if (!empty($conf->global->ACCOUNTINGEX_GROUPBYACCOUNT)) {
			if (! isset($tabht[$obj->rowid][$compta_prod])) {
				$tabht[$obj->rowid][$compta_prod] = 0;
			}
		} else {
			if (! isset($tabht[$obj->rowid][$compta_prod])) {
				$tabht[$obj->rowid][$compta_prod][$obj->fdid] = 0;
			}
		}
		/*if (! isset($tabht[$obj->rowid][$compta_prod])) {
			$tabht[$obj->rowid][$compta_prod] = 0;
		}*/
		/////////////////////////////////////////////////////////////////////
		if (! isset($tabtva[$obj->rowid][$compta_tva]))
			$tabtva[$obj->rowid][$compta_tva] = 0;
		$tabttc[$obj->rowid][$compta_soc] += $obj->total_ttc;
		
		if ( $conf->global->ACCOUNTINGEX_SELL_DETAILED ==0 ) {
//		if (!empty($conf->global->ACCOUNTINGEX_GROUPBYACCOUNT)) {
			$tabht[$obj->rowid][$compta_prod] += $obj->total_ht;
		} else {
			$tabht[$obj->rowid][$compta_prod][$obj->fdid] = $obj->total_ht;
		}
		// $tabht[$obj->rowid][$compta_prod] += $obj->total_ht;
		/////////////////////////////////////////////////////////////////////
		$tabtva[$obj->rowid][$compta_tva] += $obj->total_tva;
		$tabcompany[$obj->rowid] = array (
				'id' => $obj->socid,
				'name' => $obj->name,
				'code_client' => $obj->code_compta 
		);
		
//		//elarifr test
//		$tabdet2[$i]=array(
//		'fdet_rowid'       =>$obj->rowid,
//		'fdet_facnumber'   =>$obj->facnumber,
//		'fdet_compte'      =>$obj->compte,
//		'fdet_total_ht'    =>$obj->total_ht,
//		'fdet_product_type'=>$obj->product_type,
//		'fdet_ref'         =>$obj->pref,
//		'fdet_desc'        =>dol_trunc($obj->description,40)
//		);
//		//print_r($tabht[9]).'****************';
//		//elarifr test
//		$i++;
		$i ++;
	}
} else {
	dol_print_error($db);
}

/*
 * Action
 */

// Bookkeeping Write
if ($action == 'writebookkeeping') {
	$now = dol_now();
	foreach ( $tabfac as $key => $val ) {
		foreach ( $tabttc[$key] as $k => $mt ) {
			$bookkeeping = new BookKeeping($db);
			$bookkeeping->doc_date = $val["date"];
			$bookkeeping->doc_ref = $val["ref"];
			$bookkeeping->date_create = $now;
			$bookkeeping->doc_type = 'customer_invoice';
			$bookkeeping->fk_doc = $key;
			$bookkeeping->fk_docdet = $val["fk_facturedet"];
			$bookkeeping->code_tiers = $tabcompany[$key]['code_client'];
			$bookkeeping->numero_compte = $conf->global->COMPTA_ACCOUNT_CUSTOMER;
			$bookkeeping->label_compte = $tabcompany[$key]['name'];
			$bookkeeping->montant = $mt;
			$bookkeeping->sens = ($mt >= 0) ? 'D' : 'C';
			$bookkeeping->debit = ($mt >= 0) ? $mt : 0;
			$bookkeeping->credit = ($mt < 0) ? $mt : 0;
			$bookkeeping->code_journal = $conf->global->ACCOUNTINGEX_SELL_JOURNAL;
			
			$bookkeeping->create();
		}
		
		// Product / Service
		foreach ( $tabht[$key] as $k => $mt ) {
			if ($mt) {
				// get compte id and label
				$compte = new AccountingAccount($db);
				if ($compte->fetch(null, $k)) {
					$bookkeeping = new BookKeeping($db);
					$bookkeeping->doc_date = $val["date"];
					$bookkeeping->doc_ref = $val["ref"];
					$bookkeeping->date_create = $now;
					$bookkeeping->doc_type = 'customer_invoice';
					$bookkeeping->fk_doc = $key;
					$bookkeeping->fk_docdet = $val["fk_facturedet"];
					$bookkeeping->code_tiers = '';
					$bookkeeping->numero_compte = $k;
					$bookkeeping->label_compte = dol_trunc($val["description"], 128);
					$bookkeeping->montant = $mt;
					$bookkeeping->sens = ($mt < 0) ? 'D' : 'C';
					$bookkeeping->debit = ($mt < 0) ? $mt : 0;
					$bookkeeping->credit = ($mt >= 0) ? $mt : 0;
					$bookkeeping->code_journal = $conf->global->ACCOUNTINGEX_SELL_JOURNAL;
					
					$bookkeeping->create();
				}
			}
		}
		
		// VAT
		// var_dump($tabtva);
		foreach ( $tabtva[$key] as $k => $mt ) {
			if ($mt) {
				$bookkeeping = new BookKeeping($db);
				$bookkeeping->doc_date = $val["date"];
				$bookkeeping->doc_ref = $val["ref"];
				$bookkeeping->date_create = $now;
				$bookkeeping->doc_type = 'customer_invoice';
				$bookkeeping->fk_doc = $key;
				$bookkeeping->fk_docdet = $val["fk_facturedet"];
				$bookkeeping->fk_compte = $compte->id;
				$bookkeeping->code_tiers = '';
				$bookkeeping->numero_compte = $k;
				$bookkeeping->label_compte = $langs->trans("VAT");
				$bookkeeping->montant = $mt;
				$bookkeeping->sens = ($mt < 0) ? 'D' : 'C';
				$bookkeeping->debit = ($mt < 0) ? $mt : 0;
				$bookkeeping->credit = ($mt >= 0) ? $mt : 0;
				$bookkeeping->code_journal = $conf->global->ACCOUNTINGEX_SELL_JOURNAL;
				
				$bookkeeping->create();
			}
		}
	}
}
// export csv
//################################################################################################################
if ($action == 'export_csv') {
	$sep = $conf->global->ACCOUNTINGEX_SEPARATORCSV;
	//elarifr export filename set in admin
	$filename=accountingex_export_filename_set($filename="",$conf->global->ACCOUNTINGEX_SELL_JOURNAL);
	
	header('Content-Type: text/csv');
//	header( 'Content-Disposition: attachment;filename=journal_ventes.csv');
//	header( 'Content-Disposition: attachment;filename ='.$conf->global->ACCOUNTINGEX_EXPORT_FILENAME);
	header( 'Content-Disposition: attachment;filename ='.$filename);
	
	$companystatic = new Client($db);
//################################################################################################################
	if ($conf->global->ACCOUNTINGEX_MODELCSV == 1) // Modèle Cegid Expert
	{
//################################################################################################################
	foreach ( $tabfac as $key => $val )
	{
		$companystatic->id = $tabcompany[$key]['id'];
		$companystatic->name = $tabcompany[$key]['name'];
		$companystatic->client = $tabcompany[$key]['code_client'];

		$date = dol_print_date($db->jdate($val["date"]), $conf->global->ACCOUNTINGEX_EXP_DATE);
	
		print $date . $sep;
		print $conf->global->ACCOUNTINGEX_SELL_JOURNAL . $sep;
		
		if ($conf->global->ACCOUNTINGEX_EXP_GLOBAL_ACCOUNT == 1) 
		{			
			print length_accountg($conf->global->COMPTA_ACCOUNT_CUSTOMER) . $sep;
		}
		
		// Thirdparty
		foreach ( $tabttc[$key] as $k => $mt ) {
			print length_accounta(html_entity_decode($k)) . $sep;

			if ($conf->global->ACCOUNTINGEX_EXP_AMOUNT == 1)
			{
				print ($mt < 0 ? 'C' : 'D') . $sep;
				print ($mt <= 0 ? price(- $mt) : $mt) . $sep;
			}
			else
			{
				print '"' . ($mt >= 0 ? price($mt) : '') . '"' . $sep;
				print '"' . ($mt < 0 ? price(- $mt) : '') . '"';
			}

			print utf8_decode($companystatic->name) . $sep;
		}

		print $val["ref"];
		print "\n";

		// Product / Service
		/////////////////////////////////////////////////////////////////////////// darkjeff
		foreach ( $tabht[$key] as $k => $mt ) {
			//print_r($mt);
			$date = dol_print_date($db->jdate($val["date"]), $conf->global->ACCOUNTINGEX_EXP_DATE);

//			if (is_array($mt) && count($mt)>0 && empty($conf->global->ACCOUNTINGEX_GROUPBYACCOUNT)) {
			if (is_array($mt) && count($mt)>0 &&  ( $conf->global->ACCOUNTINGEX_SELL_DETAILED ==1)) {

				foreach($mt as $lineid=>$amountline) {
					if ($amountline) {
						print $date . $sep;
						print $conf->global->ACCOUNTINGEX_SELL_JOURNAL . $sep;
						if ($conf->global->ACCOUNTINGEX_EXP_GLOBAL_ACCOUNT == 1) 
						{			
							print $sep;
						}
						
						print length_accountg(html_entity_decode($k)) . $sep;
						print $sep;
								
						if ($conf->global->ACCOUNTINGEX_EXP_AMOUNT == 1) 
						{
							print ($amountline < 0 ? 'D' : 'C') . $sep;
							print ($amountline <= 0 ? price(- $amountline) : $amountline) . $sep;
						}
						else
						{	
							print '"' . ($amountline < 0 ? price(- $amountline) : '') . '"' . $sep;
							print '"' . ($amountline >= 0 ? price($amountline) : '') . '"';
						}
						print dol_trunc($val["description"][$lineid], 32) . $sep;
						print $val["ref"];
						print "\n";
					}
				}
			}
			else
			{
				if ($mt) {
					print $date . $sep;
					print $conf->global->ACCOUNTINGEX_SELL_JOURNAL . $sep;

					if ($conf->global->ACCOUNTINGEX_EXP_GLOBAL_ACCOUNT == 1)
					{
						print $sep;
					}
					
					print length_accountg(html_entity_decode($k)) . $sep;
					print $sep;
							
					if ($conf->global->ACCOUNTINGEX_EXP_AMOUNT == 1) 
					{
						print ($mt < 0 ? 'D' : 'C') . $sep;
						print ($mt <= 0 ? price(- $mt) : $mt) . $sep;
					}
					else
					{
						print '"' . ($mt < 0 ? price(- $mt) : '') . '"' . $sep;
						print '"' . ($mt >= 0 ? price($mt) : '') . '"';
					}
							
					print dol_trunc($val["description"], 32) . $sep;
					print $val["ref"];
					print "\n";
				}
			}
		}	
			
		// TVA
		foreach ( $tabtva[$key] as $k => $mt ) {
			$date = dol_print_date($db->jdate($val["date"]), $conf->global->ACCOUNTINGEX_EXP_DATE);
			if ($mt) {
				print $date . $sep;
				print $conf->global->ACCOUNTINGEX_SELL_JOURNAL . $sep;
				if ($conf->global->ACCOUNTINGEX_EXP_GLOBAL_ACCOUNT == 1) 
				{			
					print $sep;
				}
					
				print length_accountg(html_entity_decode($k)) . $sep;
				print $sep;
					
				if ($conf->global->ACCOUNTINGEX_EXP_AMOUNT == 1) 
				{		
					print ($mt < 0 ? 'D' : 'C') . $sep;
					print ($mt <= 0 ? price(- $mt) : $mt) . $sep;
				}
				else
				{
					print '"' . ($mt < 0 ? price(- $mt) : '') . '"' . $sep;
					print '"' . ($mt >= 0 ? price($mt) : '') . '"';
				}
					
				print $langs->trans("VAT") . $sep;
				print $val["ref"];
				print "\n";
			}
		}
	/////////////////////////////////////////////////////////////////////////// darkjeff
	}
	}
	elseif ($conf->global->ACCOUNTINGEX_MODELCSV == 20) // ModÃ¨le Ciel test Ximport
	{
	//print "sample ximport ciel http://forum.gestan.fr/viewtopic.php?f=55&t=399&start=10". "\n";
	//print "   53HA20090106200801062152        401000     Facture EDF 2152               1196.00C2152              Fournisseurs divers               O2003". "\n";
	foreach ( $tabfac as $key => $val )
	{
		$companystatic->id = $tabcompany[$key]['id'];
		$companystatic->name = $tabcompany[$key]['name'];
		$companystatic->client = $tabcompany[$key]['code_client'];
		$date = dol_print_date($db->jdate($val["date"]),'%d%m%Y');

		// N° de Mouvement 5 caractères Numérique
		$exportlinestart  = strftime("%y%j");
		// Journal 2 caractères Alphanumérique
		$exportlinestart .= str_pad(substr($conf->global->ACCOUNTINGEX_SELL_JOURNAL,0,2),2);
		// Date d’écriture 8 caractères Date (AAAAMMJJ)
		$exportlinestart .= dol_print_date($db->jdate($val["date"]),'%Y%m%d');
		// Date d’échéance 8 caractères Date (AAAAMMJJ)                                // ok A RECUPERER DATE ECHEANCE  date_lim_reglement
		$exportlinestart .= dol_print_date($db->jdate($val["date_lim_reglement"]),'%Y%m%d');
		// N° de pièce 12 caractères Alphanumérique
		$exportlinestart .= str_pad(substr($val["ref"],0,12),12);
		// Compte 11 caractères Alphanumérique
		// Libellé 25 caractères Alphanumérique
		// Montant 13 caractères (2 déc.) Numérique
		// Crédit-Débit 1 caractère (D ou C)
		// N° de pointage 12 caractères Alphanumérique
		//                123456789012
		$exportanalyt  = "            ";
		// Code analyt./budgét 6 caractères Alphanumérique
		//                123456
		$exportanalyt .= "      " ;
		// Libellé du compte 34 caractères Alphanumérique

		// Euro 1 caractère Alphanumérique
		// Avec en plus pour le format Ciel 2003 : Version 4 caractères Alphanumérique
		$exportlineclose  = "02003";                                                  // c'est surement plus ok mais j'attend modele export de gestcom

		// SELL INVOICE
		// begin export line
		print $exportlinestart;
		foreach ($tabttc[$key] as $k => $mt)
		{
		// Compte 11 caractères Alphanumérique
		//print str_pad(substr($conf->global->COMPTA_ACCOUNT_CUSTOMER,0,11),11,"0");
		print str_pad(substr($k,0,11),11,"0");
		// Libellé 25 caractères Alphanumérique
		print str_pad(substr(ucfirst(strtolower(utf8_decode($companystatic->name))),0,25),25);
		// Montant 13 caractères (2 déc.) Numérique
		print str_pad(number_format(($mt < 0?-$mt:$mt),2,'.',''),13,' ', STR_PAD_LEFT);
		// Crédit-Débit 1 caractère (D ou C)
		print ($mt < 0?'C':'D');
		}
		// middle pointage & analytique not managed yet
		print $exportanalyt;
		// Libellé du compte 34 caractères Alphanumérique
		print str_pad(substr(ucfirst(strtolower(utf8_decode($companystatic->name))),0,34),34);
		//print str_pad(substr(ucfirst(strtolower(utf8_decode("libelle compte client"))),0,34),34);
		// end of line depend of export version & euro to check
		print $exportlineclose. "\n";
		///////////////////////////////////////////////////////////////////////////////////
		// Product / Service
		foreach ( $tabht[$key] as $k => $mt ) {
			//print_r($mt);
			$date = dol_print_date($db->jdate($val["date"]), $conf->global->ACCOUNTINGEX_EXP_DATE);
			//
//			if (is_array($mt) && count($mt)>0 && empty($conf->global->ACCOUNTINGEX_GROUPBYACCOUNT)) {
			if (is_array($mt) && count($mt)>0 &&  ( $conf->global->ACCOUNTINGEX_SELL_DETAILED ==1)) {
				foreach($mt as $lineid=>$amountline) {
					if ($amountline!=0 || $conf->global->ACCOUNTINGEX_SELL_EXPORTZERO==1) {
						// begin export line
						print  $exportlinestart;
						// Compte 11 caractères Alphanumérique
						print str_pad(substr(html_entity_decode($k),0,11),11,"0");
						// Libellé 25 caractères Alphanumérique
						print str_pad(substr(ucfirst(strtolower($val["description"][$lineid])),0,25),25);
						// Montant 13 caractères (2 déc.) Numérique
						print str_pad(number_format(($amountline < 0?-$amountline:$amountline),2,'.',''),13,' ', STR_PAD_LEFT);
						// Crédit-Débit 1 caractère (D ou C)
						print ($mt < 0?'D':'C');
						// middle pointage & analytique not managed yet
						print $exportanalyt;
						// Libellé du compte 34 caractères Alphanumérique
						print str_pad(substr(ucfirst(strtolower(utf8_decode("libelle compte vente"))),0,34),34);
						// end of line depend of export version & euro to check
						print $exportlineclose. "\n";
					}
				}
			} else {
				if ($mt!=0 || $conf->global->ACCOUNTINGEX_SELL_EXPORTZERO==1) {
					// begin export line
					print $exportlinestart;
					// Compte 11 caractères Alphanumérique
					print str_pad(substr(html_entity_decode($k),0,11),11,"0");
					// Libellé 25 caractères Alphanumérique
					print  str_pad(substr(ucfirst(strtolower($val["description"][$lineid])),0,25),25);
					// Montant 13 caractères (2 déc.) Numérique
					print str_pad(number_format(($mt < 0?-$mt:$mt),2,'.',''),13,' ', STR_PAD_LEFT);
					// Crédit-Débit 1 caractère (D ou C)
					print ($mt < 0?'D':'C');
					// middle pointage & analytique not managed yet
					print $exportanalyt;
					// Libellé du compte 34 caractères Alphanumérique
					print str_pad(substr(ucfirst(strtolower(utf8_decode("libelle compte vente"))),0,34),34);
					// end of line depend of export version & euro to check
					print $exportlineclose. "\n";
				}
			}
		}
		// TVA
		foreach ($tabtva[$key] as $k => $mt)
		{
			if ($mt) {
				// begin export line
				print $exportlinestart;
				// Compte 11 caractères Alphanumérique
				print str_pad(substr(html_entity_decode($k),0,11),11,"0");
				// Libellé 25 caractères Alphanumérique // ok test avec EGroult
				print str_pad(substr(ucfirst(strtolower($langs->trans("VAT"))),0,25),25);
				// Montant 13 caractères (2 déc.) Numérique
				print str_pad(number_format(($mt < 0?-$mt:$mt),2,'.',''),13,' ', STR_PAD_LEFT);
				// Crédit-Débit 1 caractère (D ou C)
				print ($mt < 0?'D':'C');
				// middle pointage & analytique not managed yet
				print $exportanalyt;
				// Libellé du compte 34 caractères Alphanumérique
				print str_pad(substr(ucfirst(strtolower(utf8_decode("libelle compte tva"))),0,34),34);
				// end of line depend of export version & euro to check
				print $exportlineclose. "\n";
			}
		}
	}
	}
//end export
//################################################################################################################
}
else
{

	$form = new Form($db);

	llxHeader('', $langs->trans("SellsJournal"));

	$nom = $langs->trans("SellsJournal");
	$nomlink = '';
	$periodlink = '';
	$exportlink = '';
	$builddate = time();
	//elarifr export filename set in admin
	$filename=accountingex_export_filename_set($filename="",$conf->global->ACCOUNTINGEX_SELL_JOURNAL);
	$description = $langs->trans("DescSellsJournal").' '.$filename.'<br>';
	if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS))
		$description .= $langs->trans("DepositsAreNotIncluded");
	else
		$description .= $langs->trans("DepositsAreIncluded");
	$period = $form->select_date($date_start, 'date_start', 0, 0, 0, '', 1, 0, 1) . ' - ' . $form->select_date($date_end, 'date_end', 0, 0, 0, '', 1, 0, 1);
	report_header($nom, $nomlink, $period, $periodlink, $description, $builddate, $exportlink, array ('action'=>''));
	
	print '<input type="button" class="button" style="float: right;" value="Export CSV" onclick="launch_export();" />';
	
	print '<input type="button" class="button" value="' . $langs->trans("WriteBookKeeping") . '" onclick="writebookkeeping();" />';
	
	print '
	<script type="text/javascript">
		function launch_export() {
		    $("div.fiche div.tabBar form input[name=\"action\"]").val("export_csv");
			$("div.fiche div.tabBar form input[type=\"submit\"]").click();
		    $("div.fiche div.tabBar form input[name=\"action\"]").val("");
		}
		function writebookkeeping() {
		    $("div.fiche div.tabBar form input[name=\"action\"]").val("writebookkeeping");
			$("div.fiche div.tabBar form input[type=\"submit\"]").click();
		    $("div.fiche div.tabBar form input[name=\"action\"]").val("");
		}
	</script>';
	
	/*
	 * Show result array
	 */
	print '<br><br>';
	
	$i = 0;
	print "<table class=\"noborder\" width=\"100%\">";
	print "<tr class=\"liste_titre\">";
	print "<td>" . $langs->trans("Date") . "</td>";
	print "<td>" . $langs->trans("Piece") . ' (' . $langs->trans("InvoiceRef") . ")</td>";
	print "<td>" . $langs->trans("Account") . "</td>";
	print "<td>" . $langs->trans("Type") . "</td>";
	print "<td align='right'>" . $langs->trans("Debit") . "</td>";
	print "<td align='right'>" . $langs->trans("Credit") . "</td>";
	print "</tr>\n";
	
	$var = true;
	$r = '';
	
	$invoicestatic = new Facture($db);
	$companystatic = new Client($db);
	
	foreach ( $tabfac as $key => $val ) {
		$invoicestatic->id = $key;
		$invoicestatic->ref = $val["ref"];
		$invoicestatic->type = $val["type"];
		
		$invoicestatic->description = $val["description"];
		
		$date = dol_print_date($db->jdate($val["date"]), 'day');
		
		print "<tr " . $bc[$var] . ">";
		
		// Third party
		// print "<td>".$conf->global->COMPTA_JOURNAL_SELL."</td>";
		print "<td>" . $date . "</td>";
		print "<td>" . $invoicestatic->getNomUrl(1) . "</td>";
		foreach ( $tabttc[$key] as $k => $mt ) {
			$companystatic->id = $tabcompany[$key]['id'];
			$companystatic->name = $tabcompany[$key]['name'];
			$companystatic->client = $tabcompany[$key]['code_client'];
			print "<td>" . length_accounta($k);
			print "</td><td>" . $langs->trans("ThirdParty");
			print ' (' . $companystatic->getNomUrl(0, 'customer', 16) . ')';
			print "</td><td align='right'>" . ($mt >= 0 ? price($mt) : '') . "</td>";
			print "<td align='right'>" . ($mt < 0 ? price(- $mt) : '') . "</td>";
		}
		print "</tr>";
		
		// Product / Service
		// #####################################################
		foreach ( $tabht[$key] as $k => $mt ) {
			
//			if (is_array($mt) && count($mt) > 0 && empty($conf->global->ACCOUNTINGEX_GROUPBYACCOUNT)) {
//			supposed same purpose as ACCOUNTINGEX_GROUPBYACCOUNT but should prefered separate option for sell / buy detailed group by account
			if (is_array($mt) && count($mt) > 0 && $conf->global->ACCOUNTINGEX_SELL_DETAILED ==1 ) {
				foreach ( $mt as $ligneid => $line_mt ) {
					//elarifr add option to export zero value lines. Can not be imported in all accounting software
					if ($conf->global->ACCOUNTINGEX_SELL_EXPORTZERO==1 || $mt != 0) {
					if ($mt) {
						print "<tr " . $bc[$var] . ">";
						// print "<td>".$conf->global->COMPTA_JOURNAL_SELL."</td>";
						print "<td>" . $date . "</td>";
						print "<td>" . $invoicestatic->getNomUrl(1) . "</td>";
						print "<td>" . length_accountg($k) . "</td>";
						print "<td>" .  html_entity_decode(dol_trunc($invoicestatic->description[$ligneid], ACCOUNTINGEX_LENGTH_DESCRIPTION )) . "</td>";
						print "<td align='right'>" . ($line_mt < 0 ? price(- $line_mt) : '') . "</td>";
						print "<td align='right'>" . ($line_mt >= 0 ? price($line_mt) : '') . "</td>";
						print "</tr>";
					}
					}
				}
			} else {
				//elarifr add option to export zero value lines. Can not be imported in all accounting software
				if ($conf->global->ACCOUNTINGEX_SELL_EXPORTZERO==1 || $mt != 0) {
				if ($mt) {
				$compte = new AccountingAccount($db);
				$compte->fetch(null, $k) ;
					print "<tr " . $bc[$var] . ">";
					// print "<td>".$conf->global->COMPTA_JOURNAL_SELL."</td>";
					print "<td>" . $date . "</td>";
					print "<td>" . $invoicestatic->getNomUrl(1) . "</td>";
					print "<td>" . length_accountg($k) . "</td>";
					print "<td>" . html_entity_decode(dol_trunc($compte->label), ACCOUNTINGEX_LENGTH_DESCRIPTION ) . "</td>";
					//print "<td>" . html_entity_decode(dol_trunc($invoicestatic->description)) . "</td>";
					print "<td align='right'>" . ($mt < 0 ? price(- $mt) : '') . "</td>";
					print "<td align='right'>" . ($mt >= 0 ? price($mt) : '') . "</td>";
					print "</tr>";
				}
				}
			}
		}
		// End Product / Service 
/*		this is to be removed 
//////////////////////////////////////////////////////////////////////////////////////////////////
		$tabdet2_filter = array_filter($tabdet2 , function ($element) use ($key)      { if ($element[fdet_rowid] == $key) return $element; });
                //print_r($tabdet2_filter);
                foreach ($tabdet2_filter as $k2=>$val2 ) {
                  //foreach ($detail as $k2=>$val2)
                  //print 'tabfac key='.$key . '-tabdet2 k='.$val2[fdet_rowid].$val2[fdet_facnumber].$val2[fdet_product_type].$val2[fdet_ref].$val2[fdet_desc].$val2[fdet_total_ht].'<br />';
			if ($conf->global->ACCOUNTINGEX_SELL_EXPORTZERO==1 || $val2[fdet_total_ht] != 0) {
				print "<tr ".$bc[$var].">";
				//print "<td>".$conf->global->COMPTA_JOURNAL_SELL."</td>";
				print "<td>".$date."</td>";
				print "<td>".$invoicestatic->getNomUrl(1)."</td>";
//elarifr
//				print "<td>".length_accountg($k)."</td><td>".$langs->trans("Products")."</td><td align='right'>".($mt<0?price(-$mt):'')."</td><td align='right'>".($mt>=0?price($mt):'')."</td></tr>";
				$mt=  $val2[fdet_total_ht];
				if ($val2[fdet_product_type]==0){$sell_detail= $langs->trans("Products");} else {$sell_detail= $langs->trans("Services");}
				$sell_detail .= " (".$val2[fdet_product_type].") (".$val2[fdet_ref].") - ".$val2[fdet_desc];
				print "<td>".length_accountg($val2[fdet_compte])."</td><td>".$sell_detail."</td><td align='right'>".($mt<0?price(-$mt):'')."</td><td align='right'>".($mt>=0?price($mt):'')."</td></tr>";
//elarifr
			}
		}
	} else {
		foreach ($tabht[$key] as $k => $mt) {
			if ($mt) {
					print "<tr " . $bc[$var] . ">";
					// print "<td>".$conf->global->COMPTA_JOURNAL_SELL."</td>";
					print "<td>" . $date . "</td>";
					print "<td>" . $invoicestatic->getNomUrl(1) . "</td>";
					print "<td>" . length_accountg($k) . "</td>";
					print "<td>".$invoicestatic->description."</td>";
					print "<td align='right'>" . ($mt < 0 ? price(- $mt) : '') . "</td>";
					print "<td align='right'>" . ($mt >= 0 ? price($mt) : '') . "</td>";
					print "</tr>";
//				if ($invoicestatic->ptype==1){$sell_detail= $langs->trans("Products");} else {$sell_detail= $langs->trans("Services");}
//				$sell_detail .= " (".$invoicestatic->ptype.") (".$invoicestatic->pref.") - ".$invoicestatic->pdesc;
//				print "<td>".length_accountg($k)."</td><td>".$sell_detail."</td><td align='right'>".($mt<0?price(-$mt):'')."</td><td align='right'>".($mt>=0?price($mt):'')."</td></tr>";
//elarifr
				}
			}
		}
//
//////////////////////////////////////////////////////////////////////////////////////////////////
*/
		
		// VAT
		// var_dump($tabtva);
		foreach ( $tabtva[$key] as $k => $mt ) {
			if ($mt) {
				print "<tr " . $bc[$var] . ">";
				// print "<td>".$conf->global->COMPTA_JOURNAL_SELL."</td>";
				print "<td>" . $date . "</td>";
				print "<td>" . $invoicestatic->getNomUrl(1) . "</td>";
				print "<td>" . length_accountg($k) . "</td>";
				print "<td>" . $langs->trans("VAT") . "</td>";
				print "<td align='right'>" . ($mt < 0 ? price(- $mt) : '') . "</td>";
				print "<td align='right'>" . ($mt >= 0 ? price($mt) : '') . "</td>";
				print "</tr>";
			}
		}
		
		$var = ! $var;
	}
	
	print "</table>";
	
	// End of page
	llxFooter();
}
$db->close();

