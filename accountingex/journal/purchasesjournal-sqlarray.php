<?php
/* Copyright (C) 2007-2010	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2007-2010	Jean Heimburger		  <jean@tiaris.info>
 * Copyright (C) 2011		    Juanjo Menent		    <jmenent@2byte.es>
 * Copyright (C) 2012		    Regis Houssin		    <regis@dolibarr.fr>
 * Copyright (C) 2013-2014  Alexandre Spangaro  <alexandre.spangaro@gmail.com> 
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
 *   	\file       accountingex/journal/purchasesjournal-sqlarray.php
 *		\ingroup    Accounting Expert
 *		\brief      included part of exportjournal.php , query & create data array
 */


//print "include sqlarray ";
// PURCHASESJOURNAL-SQLARRAY



$sql = "SELECT f.rowid, f.ref, f.type, f.datef as df, f.libelle,";
$sql.= " fd.rowid as fdid, fd.description, fd.total_ttc, fd.tva_tx, fd.total_ht, fd.tva as total_tva, fd.product_type,";
$sql.= " s.rowid as socid, s.nom as name, s.code_compta_fournisseur, s.fournisseur,";
$sql.= " s.code_compta_fournisseur, p.accountancy_code_buy , ct.accountancy_code_buy as account_tva, aa.rowid as fk_compte, aa.account_number as compte, aa.label as label_compte";
$sql.= " FROM ".MAIN_DB_PREFIX."facture_fourn_det fd";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_tva ct ON fd.tva_tx = ct.taux AND ct.fk_pays = '".$idpays."'";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = fd.fk_product";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."accountingaccount aa ON aa.rowid = fd.fk_code_ventilation";
$sql.= " JOIN ".MAIN_DB_PREFIX."facture_fourn f ON f.rowid = fd.fk_facture_fourn";
$sql.= " JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc" ;
$sql.= " WHERE f.fk_statut > 0 AND f.entity = ".$conf->entity;
if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) $sql.= " AND f.type IN (0,1,2)";
else $sql.= " AND f.type IN (0,1,2,3)";
if ($date_start && $date_end) $sql .= " AND f.datef >= '".$db->idate($date_start)."' AND f.datef <= '".$db->idate($date_end)."'";
$sql.= " ORDER BY f.datef";

dol_syslog('accountingex/journal/purchasesjournal-sqlarray.php:: $sql='.$sql);
$result = $db->query($sql);
if ($result)
{
	$num = $db->num_rows($result);
	// les variables
	$cptfour = (! empty($conf->global->COMPTA_ACCOUNT_SUPPLIER))?$conf->global->COMPTA_ACCOUNT_SUPPLIER:$langs->trans("CodeNotDef");
	$cpttva = (! empty($conf->global->COMPTA_VAT_ACCOUNT))?$conf->global->COMPTA_VAT_ACCOUNT:$langs->trans("CodeNotDef");

//	$tabfac = array();
//	$tabht = array();
//	$tabtva = array();
//	$tabttc = array();
//	$tabcompany = array();
	
	$i=0;
	while ($i < $num)
	{
		$obj = $db->fetch_object($result);
		// contrôles
		$compta_soc = (! empty($obj->code_compta_fournisseur))?$obj->code_compta_fournisseur:$cptfour;
		$compta_prod = $obj->compte;
		if (empty($compta_prod))
		{
			if($obj->product_type == 0) $compta_prod = (! empty($conf->global->COMPTA_PRODUCT_BUY_ACCOUNT))?$conf->global->COMPTA_PRODUCT_BUY_ACCOUNT:$langs->trans("CodeNotDef");
			else $compta_prod = (! empty($conf->global->COMPTA_SERVICE_BUY_ACCOUNT))?$conf->global->COMPTA_SERVICE_BUY_ACCOUNT:$langs->trans("CodeNotDef");
		}
		$compta_tva = (! empty($obj->account_tva)?$obj->account_tva:$cpttva);

		$tabfac[$obj->rowid]["date"] = $obj->df;
		$tabfac[$obj->rowid]["ref"] = $obj->ref;
		$tabfac[$obj->rowid]["type"] = $obj->type;
		$tabfac[$obj->rowid]["description"] = $obj->description;
		$tabfac[$obj->rowid]["fk_facturefourndet"] = $obj->fdid;
		$tabfac[$obj->rowid]["code_journal"] = $conf->global->ACCOUNTINGEX_PURCHASE_JOURNAL;
		$tabttc[$obj->rowid][$compta_soc] += $obj->total_ttc;
		$tabht[$obj->rowid][$compta_prod] += $obj->total_ht;
		$tabtva[$obj->rowid][$compta_tva] += $obj->total_tva;
		$tabcompany[$obj->rowid]=array('id'=>$obj->socid,'name'=>$obj->name, 'code_fournisseur'=>$obj->code_compta_fournisseur);

		//elarifr test
		$tabdet2[$obj->rowid]=array(
		'fdet_rowid'       =>$obj->rowid,
		'fdet_facnumber'   =>$obj->ref,
		'fdet_compte'      =>$obj->compte,
		'fdet_total_ht'    =>$obj->total_ht,
		'fdet_product_type'=>$obj->product_type,
		'fdet_ref'         =>$obj->pref,
		'fdet_desc'        =>dol_trunc($obj->description,40),
		'code_journal'     =>$conf->global->ACCOUNTINGEX_PURCHASE_JOURNAL
		);

		$i++;
	}
}
else {
	dol_print_error($db);
}
// end part to move to purchasesjournal-sqlarray.php

