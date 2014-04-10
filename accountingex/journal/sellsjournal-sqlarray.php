<?php
/* Copyright (C) 2007-2010 Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2007-2010 Jean Heimburger		  <jean@tiaris.info>
 * Copyright (C) 2011		   Juanjo Menent		    <jmenent@2byte.es>
 * Copyright (C) 2012		   Regis Houssin		    <regis@dolibarr.fr>
 * Copyright (C) 2013		   Christophe Battarel	<christophe.battarel@altairis.fr>
 * Copyright (C) 2013-2014 Alexandre Spangaro	  <alexandre.spangaro@gmail.com>
 * Copyright (C) 2013      Florian Henry	      <florian.henry@open-concept.pro>
 * Copyright (C) 2013-2014 Olivier Geffroy      <jeff@jeffinfo.com>
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
 *   	\file       accountingex/journal/sellsjournal-sqlarray.php
 *		\ingroup    Accounting Expert
 *		\brief      included part of exportjournal.php , query & create data array
 */


//print "include sqlarray ";
// SELLSJOURNAL-SQLARRAY



$sql = "SELECT f.rowid, f.facnumber, f.type, f.datef as df, f.ref_client, f.date_lim_reglement,";
//elari we need fd.description to export in accounting
$sql.= " fd.rowid as fdid, fd.description, fd.product_type, fd.total_ht, fd.total_tva, fd.tva_tx, fd.total_ttc,";
$sql.= " s.rowid as socid, s.nom as name, s.code_compta, s.code_client,";
$sql.= " p.rowid as pid, p.ref as pref, p.accountancy_code_sell, aa.rowid as fk_compte, aa.account_number as compte, aa.label as label_compte, ";
$sql.= " ct.accountancy_code_sell as account_tva";
$sql.= " FROM ".MAIN_DB_PREFIX."facturedet fd";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = fd.fk_product";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."accountingaccount aa ON aa.rowid = fd.fk_code_ventilation";
$sql.= " JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = fd.fk_facture";
$sql.= " JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_tva ct ON fd.tva_tx = ct.taux AND ct.fk_pays = '".$idpays."'";
$sql.= " WHERE f.entity = ".$conf->entity;
$sql.= " AND fd.fk_code_ventilation > 0";
$sql.= " AND f.fk_statut > 0";
if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) $sql.= " AND f.type IN (0,1,2)";
else $sql.= " AND f.type IN (0,1,2,3)";
$sql.= " AND fd.product_type IN (0,1)";
if ($date_start && $date_end) $sql .= " AND f.datef >= '".$db->idate($date_start)."' AND f.datef <= '".$db->idate($date_end)."'";
$sql.= " ORDER BY f.datef";

dol_syslog('accountingex/journal/sellsjournal.php:: $sql='.$sql);
$result = $db->query($sql);
if ($result)
{
	$num = $db->num_rows($result);
   	$i=0;
   	$resligne=array();
   	while ($i < $num)
   	{
   	    $obj = $db->fetch_object($result);
   	    //print_r($obj);
   	    // les variables
   	    $cptcli = (! empty($conf->global->COMPTA_ACCOUNT_CUSTOMER))?$conf->global->COMPTA_ACCOUNT_CUSTOMER:$langs->trans("CodeNotDef");
   	    $compta_soc = (! empty($obj->code_compta))?$obj->code_compta:$cptcli;
		
		
		$compta_prod = $obj->compte;
		if (empty($compta_prod))
		{
			if($obj->product_type == 0) $compta_prod = (! empty($conf->global->COMPTA_PRODUCT_SOLD_ACCOUNT))?$conf->global->COMPTA_PRODUCT_SOLD_ACCOUNT:$langs->trans("CodeNotDef");
			else $compta_prod = (! empty($conf->global->COMPTA_SERVICE_SOLD_ACCOUNT))?$conf->global->COMPTA_SERVICE_SOLD_ACCOUNT:$langs->trans("CodeNotDef");
		}
		$cpttva = (! empty($conf->global->COMPTA_VAT_ACCOUNT))?$conf->global->COMPTA_VAT_ACCOUNT:$langs->trans("CodeNotDef");
		$compta_tva = (! empty($obj->account_tva)?$obj->account_tva:$cpttva);

    	//la ligne facture
   		$tabfac[$obj->rowid]["date"] = $obj->df;
   		$tabfac[$obj->rowid]["ref"] = $obj->facnumber;
   		$tabfac[$obj->rowid]["type"] = $obj->type;
   		$tabfac[$obj->rowid]["date_lim_reglement"] = $obj->date_lim_reglement;
		$tabfac[$obj->rowid]["description"] = $obj->description;
   		$tabfac[$obj->rowid]["fk_facturedet"] = $obj->fdid;
		$tabfac[$obj->rowid]["code_journal"] = $conf->global->ACCOUNTINGEX_SELL_JOURNAL;
   		if (! isset($tabttc[$obj->rowid][$compta_soc])) $tabttc[$obj->rowid][$compta_soc]=0;
   		if (! isset($tabht[$obj->rowid][$compta_prod])) $tabht[$obj->rowid][$compta_prod]=0;
   		if (! isset($tabtva[$obj->rowid][$compta_tva])) $tabtva[$obj->rowid][$compta_tva]=0;
   		$tabttc[$obj->rowid][$compta_soc] += $obj->total_ttc;
   		$tabht[$obj->rowid][$compta_prod] += $obj->total_ht;
   		$tabtva[$obj->rowid][$compta_tva] += $obj->total_tva;
   		$tabcompany[$obj->rowid]=array('id'=>$obj->socid, 'name'=>$obj->name, 'code_client'=>$obj->code_compta);

		//elarifr test
		$tabdet2[$obj->rowid]=array(
		'fdet_rowid'       =>$obj->rowid,
		'fdet_facnumber'   =>$obj->facnumber,
		'fdet_compte'      =>$obj->compte,
		'fdet_total_ht'    =>$obj->total_ht,
		'fdet_product_type'=>$obj->product_type,
		'fdet_ref'         =>$obj->pref,
		'fdet_desc'        =>dol_trunc($obj->description,40),
		'code_journal'     =>$conf->global->ACCOUNTINGEX_SELL_JOURNAL
		);

//print_r($tabht[9]).'****************';
   		$i++;
   	}
}
else {
    dol_print_error($db);
}
