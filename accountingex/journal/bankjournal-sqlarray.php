<?php
/* Copyright (C) 2007-2010	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2007-2010	Jean Heimburger		  <jean@tiaris.info>
 * Copyright (C) 2011		    Juanjo Menent		    <jmenent@2byte.es>
 * Copyright (C) 2012		    Regis Houssin		    <regis@dolibarr.fr>
 * Copyright (C) 2013		    Christophe Battarel	<christophe.battarel@altairis.fr>
 * Copyright (C) 2013-2014  Alexandre Spangaro	<alexandre.spangaro@gmail.com>
 * Copyright (C) 2013       Florian Henry	      <florian.henry@open-concept.pro>
 * Copyright (C) 2013-2014  Olivier Geffroy     <jeff@jeffinfo.com>
 * Copyright (C) 2014      Ari Elbaz (elarifr)  <github@accedinfo.com>
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
 *   	\file       accountingex/journal/bankjournal-sqlarray.php
 *		\ingroup    Accounting Expert
 *		\brief      included part of exportjournal.php , query & create data array
 */


// print $conf->entity . "include bankjournal-sqlarray ";
// journal bank par defaut $conf->global->ACCOUNTINGEX_BANK_JOURNAL;
// BANKJOURNAL-SQLARRAY

$sql = "SELECT b.rowid , b.dateo as do, b.datev as dv, b.amount, b.label, b.rappro, b.num_releve, b.num_chq, b.fk_type, soc.code_compta, ba.courant,";
$sql .= " soc.code_compta_fournisseur, soc.rowid as socid, soc.nom as name, ba.account_number, bu1.type as typeop, ba.code_journal";
$sql .= " FROM " . MAIN_DB_PREFIX . "bank b";
$sql .= " JOIN " . MAIN_DB_PREFIX . "bank_account ba on b.fk_account=ba.rowid";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "bank_url bu1 ON bu1.fk_bank = b.rowid AND bu1.type='company'";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe soc on bu1.url_id=soc.rowid";
$sql .= " WHERE ba.entity = " . $conf->entity;
$sql .= " AND ba.courant <> 2"; // Pour isoler la caisse des autres comptes
if ($date_start && $date_end) $sql .= " AND b.dateo >= '" . $db->idate ( $date_start ) . "' AND b.dateo <= '" . $db->idate ( $date_end ) . "'";
$sql .= " ORDER BY b.datev";

$object = new Account ( $db );
$paymentstatic = new Paiement ( $db );
$paymentsupplierstatic = new PaiementFourn ( $db );
$societestatic = new Societe ( $db );
$chargestatic = new ChargeSociales ( $db );
$paymentvatstatic = new TVA ( $db );

dol_syslog ( "bankjournal::create sql=" . $sql, LOG_DEBUG );
$result = $db->query ( $sql );
if ($result) {
	
	$num = $db->num_rows ( $result );
	// les variables
	$cptfour = (! empty ( $conf->global->COMPTA_ACCOUNT_SUPPLIER ) ? $conf->global->COMPTA_ACCOUNT_SUPPLIER : $langs->trans ( "CodeNotDef" ));
	$cptcli = (! empty ( $conf->global->COMPTA_ACCOUNT_CUSTOMER ) ? $conf->global->COMPTA_ACCOUNT_CUSTOMER : $langs->trans ( "CodeNotDef" ));
	$cpttva = (! empty ( $conf->global->ACCOUNTINGEX_ACCOUNT_SUSPENSE ) ? $conf->global->ACCOUNTINGEX_ACCOUNT_SUSPENSE : $langs->trans ( "CodeNotDef" ));
	$cptsociale = (! empty ( $conf->global->ACCOUNTINGEX_ACCOUNT_SUSPENSE ) ? $conf->global->ACCOUNTINGEX_ACCOUNT_SUSPENSE : $langs->trans ( "CodeNotDef" ));
	
	
//	$tabpay = array ();
//	$tabbq = array ();
//	$tabtp = array ();
	$tabcompany[$obj->rowid]=array('id'=>$obj->socid, 'name'=>$obj->name, 'code_client'=>$obj->code_compta);
//	$tabtype = array ();
	
	$i = 0;
	while ( $i < $num ) {
		$obj = $db->fetch_object ( $result );
		
    // contrôles
		$compta_bank = $obj->account_number;
		if ($obj->label == '(SupplierInvoicePayment)') $compta_soc = (! empty ( $obj->code_compta_fournisseur ) ? $obj->code_compta_fournisseur : $cptfour);
		if ($obj->label == '(CustomerInvoicePayment)') $compta_soc = (! empty ( $obj->code_compta ) ? $obj->code_compta : $cptcli);
		if ($obj->typeop == '(BankTransfert)') $compta_soc = $conf->global->ACCOUNTINGEX_ACCOUNT_TRANSFER_CASH;
		//elari get $bank_journal value from bank_account
		$bank_code_journal = (! empty ( $obj->code_journal) ? $obj->code_journal : $conf->global->ACCOUNTINGEX_BANK_JOURNAL);
		// variable bookkeeping
		
		$tabpay [$obj->rowid] ["date"] = $obj->do;
		$tabpay [$obj->rowid] ["ref"] = $obj->label;
		$tabpay [$obj->rowid] ["fk_bank"] = $obj->rowid;
		$tabpay [$obj->rowid] ["code_journal"] = $bank_code_journal;
		if (preg_match ( '/^\((.*)\)$/i', $obj->label, $reg )) {
			$tabpay [$obj->rowid] ["lib"] = $langs->trans ( $reg [1] );
		} else {
			$tabpay [$obj->rowid] ["lib"] = dol_trunc ( $obj->label, 60 );
		}
		$links = $object->get_url ( $obj->rowid );
		
		foreach ( $links as $key => $val ) {
			
			$tabtype [$obj->rowid] = $links [$key] ['type'];
			
			if ($links [$key] ['type'] == 'payment') {
				$paymentstatic->id = $links [$key] ['url_id'];
				$tabpay [$obj->rowid] ["lib"] .= ' ' . $paymentstatic->getNomUrl ( 2 );
			} else if ($links [$key] ['type'] == 'payment_supplier') {
				$paymentsupplierstatic->id = $links [$key] ['url_id'];
				$paymentsupplierstatic->ref = $links [$key] ['url_id'];
				$tabpay [$obj->rowid] ["lib"] .= ' ' . $paymentsupplierstatic->getNomUrl ( 2 );
			} else if ($links [$key] ['type'] == 'company') {
				
				$societestatic->id = $links [$key] ['url_id'];
				$societestatic->nom = $links [$key] ['label'];
				$tabpay [$obj->rowid] ["soclib"] = $societestatic->getNomUrl ( 1, '', 30 );
				$tabtp [$obj->rowid] [$compta_soc] += $obj->amount;
			} else if ($links [$key] ['type'] == 'sc') {
				
				$chargestatic->id = $links [$key] ['url_id'];
				$chargestatic->ref = $links [$key] ['url_id'];
				
				$tabpay [$obj->rowid] ["lib"] .= ' ' . $chargestatic->getNomUrl ( 2 );
				if (preg_match ( '/^\((.*)\)$/i', $links [$key] ['label'], $reg )) {
					if ($reg [1] == 'socialcontribution') $reg [1] = 'SocialContribution';
					$chargestatic->lib = $langs->trans ( $reg [1] );
				} else {
					$chargestatic->lib = $links [$key] ['label'];
				}
				$chargestatic->ref = $chargestatic->lib;
				$tabpay [$obj->rowid] ["soclib"] = $chargestatic->getNomUrl ( 1, 30 );
				
				$sqlmid= 'SELECT cchgsoc.accountancy_code';
				$sqlmid.= " FROM " . MAIN_DB_PREFIX . "c_chargesociales cchgsoc ";
				$sqlmid.= " INNER JOIN " . MAIN_DB_PREFIX . "chargesociales as chgsoc ON  chgsoc.fk_type=cchgsoc.id";
				$sqlmid.= " INNER JOIN " . MAIN_DB_PREFIX . "paiementcharge as paycharg ON  paycharg.fk_charge=chgsoc.rowid";
				$sqlmid.= " INNER JOIN " . MAIN_DB_PREFIX . "bank_url as bkurl ON  bkurl.url_id=paycharg.rowid";
				$sqlmid.= " WHERE bkurl.fk_bank=".$obj->rowid;
				dol_syslog ( "bankjournal::  sqlmid=" . $sqlmid, LOG_DEBUG );
				$resultmid = $db->query ( $sqlmid );
				if ($resultmid) {
					$objmid = $db->fetch_object ( $resultmid );
					$tabtp [$obj->rowid] [$objmid->accountancy_code] += $obj->amount;	
				}
				
			} else if ($links [$key] ['type'] == 'payment_vat') {
				
				$paymentvatstatic->id = $links [$key] ['url_id'];
				$paymentvatstatic->ref = $links [$key] ['url_id'];
				$tabpay [$obj->rowid] ["lib"] .= ' ' . $paymentvatstatic->getNomUrl ( 2 );
				$tabtp [$obj->rowid] [$cpttva] += $obj->amount;
			
      } else if ($links [$key] ['type'] == 'banktransfert') {
				
        $tabpay [$obj->rowid] ["lib"] .= ' ' . $paymentvatstatic->getNomUrl ( 2 );
				$tabtp [$obj->rowid] [$cpttva] += $obj->amount;
			} 
		}
		$tabbq [$obj->rowid] [$compta_bank] += $obj->amount;
		
		// if($obj->socid)$tabtp[$obj->rowid][$compta_soc] += $obj->amount;
		
		$i ++;
	}
} else {
	dol_print_error ( $db );
}


