<?php
/* Copyright (C) 2013-2014 Olivier Geffroy		<jeff@jeffinfo.com>
 * Copyright (C) 2013-2014 Alexandre Spangaro	<alexandre.spangaro@gmail.com>
 * Copyright (C) 2014      Ari Elbaz (elarifr)	<github@accedinfo.com>
 * Copyright (C) 2013-2014 Florian Henry		<florian.henry@open-concept.pro>
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
 *
 */

/**
 * \file		accountingex/supplier/lignes.php
 * \ingroup	Accounting Expert
 * \brief		Page of details of suppliers purchase invoices lines breakdown already made
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
dol_include_once("/accountingex/class/html.formventilation.class.php");
dol_include_once("/fourn/class/fournisseur.facture.class.php");
dol_include_once("/product/class/product.class.php");
dol_include_once("/core/lib/date.lib.php");	// still needed ?

// Langs
$langs->load("compta");
$langs->load("bills");
$langs->load("other");					// still needed ? 
$langs->load("main");
$langs->load("accountingex@accountingex");

$account_parent = GETPOST('account_parent');

// Security check
if ($user->societe_id > 0)
	accessforbidden();
if (! $user->rights->accountingex->access)
	accessforbidden();

$formventilation = new FormVentilation($db);

// change account

$changeaccount = GETPOST('changeaccount');

$is_search = GETPOST('button_search_x');

if (is_array($changeaccount) && count($changeaccount) > 0 && empty($is_search)) {
	$error = 0;
	
	$db->begin();
	
	$sql1 = "UPDATE " . MAIN_DB_PREFIX . "facture_fourn_det as l";
	$sql1 .= " SET l.fk_code_ventilation=" . $account_parent;
	$sql1 .= ' WHERE l.rowid IN (' . implode(',', $changeaccount) . ')';
	
	dol_syslog('accountingex/supplier/lignes.php::changeaccount sql= ' . $sql1);
	$resql1 = $db->query($sql1);
	if (! $resql1) {
		$error ++;
		setEventMessage($db->lasterror(), 'errors');
	}
	if (! $error) {
		$db->commit();
		setEventMessage($langs->trans('Save'), 'mesgs');
	} else {
		$db->rollback();
		setEventMessage($db->lasterror(), 'errors');
	}
}

/*
 * View
 */

llxHeader('', $langs->trans("SuppliersVentilation") . ' - ' . $langs->trans("Dispatched"));

$page = GETPOST("page");
if ($page < 0)
	$page = 0;

if (! empty($conf->global->ACCOUNTINGEX_LIMIT_LIST_VENTILATION)) {
	$limit = $conf->global->ACCOUNTINGEX_LIMIT_LIST_VENTILATION;
} elseif ($conf->global->ACCOUNTINGEX_LIMIT_LIST_VENTILATION <= 0) {
	$limit = $conf->liste_limit;
} else {
	$limit = $conf->liste_limit;
}

$offset = $limit * $page;

$sql = "SELECT f.ref as facnumber, f.rowid as facid, l.fk_product, l.description, l.total_ht , l.qty, l.rowid, l.tva_tx, aa.label, aa.account_number, ";
$sql .= " p.rowid as product_id, p.ref as product_ref, p.label as product_label, p.fk_product_type as type";
$sql .= " FROM " . MAIN_DB_PREFIX . "facture_fourn as f";
$sql .= " , " . MAIN_DB_PREFIX . "accountingaccount as aa";
$sql .= " , " . MAIN_DB_PREFIX . "facture_fourn_det as l";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "product as p ON p.rowid = l.fk_product";
$sql .= " WHERE f.rowid = l.fk_facture_fourn AND f.fk_statut >= 1 AND l.fk_code_ventilation <> 0 ";
$sql .= " AND aa.rowid = l.fk_code_ventilation";
if (strlen(trim(GETPOST("search_facture")))) {
	$sql .= " AND f.facnumber like '%" . GETPOST("search_facture") . "%'";
}
if (strlen(trim(GETPOST("search_ref")))) {
	$sql .= " AND p.ref like '%" . GETPOST("search_ref") . "%'";
}
if (strlen(trim(GETPOST("search_label")))) {
	$sql .= " AND p.label like '%" . GETPOST("search_label") . "%'";
}
if (strlen(trim(GETPOST("search_desc")))) {
	$sql .= " AND l.description like '%" . GETPOST("search_desc") . "%'";
}
//elarifr
if (GETPOST("search_type") == 2 ) {
	$sql .= " AND p.fk_product_type = 0 ";
}
if (GETPOST("search_type") == 3 ) {
	$sql .= " AND p.fk_product_type = 1 ";
}
if ( (int)GETPOST("search_amount_min") <> 0 ) {
	$sql .= " AND l.total_ht >= " . (int)GETPOST("search_amount_min");
}
if ( (int)GETPOST("search_amount_max") <> 0 ) {
	$sql .= " AND l.total_ht <= " . (int)GETPOST("search_amount_max");
}
if (strlen(trim(GETPOST("search_account")))) {
	$sql .= " AND aa.account_number like '%" . GETPOST("search_account") . "%'";
}
// elarifr
if (! empty($conf->multicompany->enabled)) {
	$sql .= " AND f.entity = '" . $conf->entity . "'";
}

//$sql .= " ORDER BY l.rowid";
//elari check if still usefull to reorder by f.rowid
$sql.= " ORDER BY f.rowid ";
if ($conf->global->ACCOUNTINGEX_LIST_SORT_VENTILATION_DONE == 1) {
	$sql .= " DESC ";
}
$sql.= ", l.rowid ";
if ($conf->global->ACCOUNTINGEX_LIST_SORT_VENTILATION_DONE == 1) {
	$sql .= " DESC ";
}
$sql .= $db->plimit($limit + 1, $offset);

dol_syslog("accountingex/supplier/lignes.php::list sql= " . $sql1, LOG_DEBUG);
$result = $db->query($sql);
//print "+++" . GETPOST("search_type") . "+++" . $sql;
if ($result) {
	$num_lignes = $db->num_rows($result);
	$i = 0;
	
	// TODO : print_barre_liste always use $conf->liste_limit and do not care about custom limit in list...
	print_barre_liste($langs->trans("InvoiceLinesDone"), $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, '', $num_lignes);
	
	print '<td align="left"><b>' . $langs->trans("DescVentilDoneSupplier") . '</b></td>';
	
	print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
	print '<table class="noborder" width="100%">';
	
	print '<br><br><div class="inline-block divButAction">' . $langs->trans("ChangeAccount");
	print $formventilation->select_account($account_parent, 'account_parent', 1);
	print '<input type="submit" class="butAction" value="' . $langs->trans("Validate") . '" /></div>';
	
	print '<tr class="liste_titre"><td>' . $langs->trans("Invoice") . '</td>';
	print '<td>' . $langs->trans("Ref") . '</td>';
	print '<td>' . $langs->trans("Label") . '</td>';
	print '<td>' . $langs->trans("Description") . '</td>';
	print '<td align="center">' . $langs->trans("Amount") . '</td>';
	print '<td colspan="2" align="center">' . $langs->trans("Account") . '</td>';
	print '<td align="center">&nbsp;</td>';
	print '<td align="center">&nbsp;</td>';
	print "</tr>\n";
	
	print '<tr class="liste_titre"><td><input name="search_facture" size="8" value="' . GETPOST("search_facture") . '"></td>';
	print '<td class="liste_titre"><input type="text" class="flat" size="15" name="search_ref" value="' . GETPOST("search_ref") . '"></td>';
	print '<td class="liste_titre"><input type="text" class="flat" size="15" name="search_label" value="' . GETPOST("search_label") . '"></td>';
//	print '<td class="liste_titre"><input type="text" class="flat" size="15" name="search_desc" value="' . GETPOST("search_desc") . '"></td>';
	// elarifr add select prod / service filter a amount filter
	print '<td class="liste_titre"><input type="text" class="flat" size="15" name="search_desc" value="' . GETPOST("search_desc") . '">';
	print '<select name="search_type">';
	foreach(	array(	1 => $langs->trans('All'),
					2 => $langs->trans('Products'),
					3 => $langs->trans('Services'))
	as $key => $val) {
		print '<option value="'. $key .'"';
		if($key == GETPOST("search_type")) print ' selected="selected"';
		print '>' . $val . '</option>';
	}
	print '</select>';
	print '</td>';
	print '<td align="right" class="liste_titre">><input type="text" class="flat" size="4" name="search_amount_min" value="' . GETPOST("search_amount_min") . '">&nbsp;<<input type="text" class="flat" size="4" name="search_amount_max" value="' . GETPOST("search_amount_max") . '"></td>';
	print '<td align="center" class="liste_titre"><input type="text" class="flat" size="15" name="search_account" value="' . GETPOST("search_account") . '"></td>';
	print '<td align="center">&nbsp;</td>';
	print '<td align="right">';
	print '<input type="image" class="liste_titre" name="button_search" src="' . DOL_URL_ROOT . '/theme/' . $conf->theme . '/img/search.png" alt="' . $langs->trans("Search") . '">';
	print '</td>';
	print '<td align="center">&nbsp;</td>';
	print "</tr>\n";
	
	$facturefournisseur_static = new FactureFournisseur($db);
	$product_static = new Product($db);
	
	$var = True;
//	while ( $i < min($num_lignes, $limit) ) {
//		$objp = $db->fetch_object($result);
	while ( $objp = $db->fetch_object($result) ) {
		$var = ! $var;
		$codeCompta = $objp->account_number . ' ' . $objp->label;
		
		print "<tr $bc[$var]>";
		
		// Ref Invoice
		$facturefournisseur_static->ref = $objp->facnumber;
		$facturefournisseur_static->id = $objp->facid;
		print '<td>' . $facturefournisseur_static->getNomUrl(1) . '</td>';
		
		// Ref Product
		$product_static->ref = $objp->product_ref;
		$product_static->id = $objp->product_id;
		$product_static->type = $objp->type;
		print '<td>';
		if ($product_static->id)
			print $product_static->getNomUrl(1);
		else
			print '&nbsp;';
		print '</td>';
		
		print '<td>' . dol_trunc($objp->product_label, 24) . '</td>';
		print '<td>' . dol_trunc($objp->description, ACCOUNTINGEX_LENGTH_DESCRIPTION) . '</td>'; // adjust user size screen
		print '<td align="right">' . price($objp->total_ht) . '</td>';
		print '<td align="center">' . $codeCompta . '</td>';
		print '<td>' . $objp->rowid . '</td>';
		print '<td><a href="./card.php?id=' . $objp->rowid . '">';
		print img_edit();
		print '</a></td>';
		
		print '<td align="center"><input type="checkbox" name="changeaccount[]" value="' . $objp->rowid . '"/></td>';
		
		print "</tr>";
//		$i ++;
	}
} else {
	print $db->error();
}

print "</table></form>";

$db->close();
llxFooter();