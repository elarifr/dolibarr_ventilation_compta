<?php
/*
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
 *   	\file       accountingex/journal/exportjournal.php
 *		\ingroup    Accounting Expert
 *		\brief      Page Report export with select journal & date from-to
 */

// Dolibarr environment
$res=@include("../main.inc.php");
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res) die("Include of main fails");

// Class
dol_include_once("/core/lib/admin.lib.php");
dol_include_once("/core/lib/bank.lib.php");
dol_include_once("/core/lib/date.lib.php");
dol_include_once("/core/lib/files.lib.php");
dol_include_once("/core/lib/report.lib.php");
dol_include_once("/core/class/html.formfile.class.php");
dol_include_once("/accountingex/core/lib/account.lib.php");
dol_include_once("/societe/class/societe.class.php");
dol_include_once("/adherents/class/adherent.class.php");
dol_include_once("/compta/sociales/class/chargesociales.class.php");
dol_include_once("/compta/paiement/class/paiement.class.php");
dol_include_once("/compta/tva/class/tva.class.php");
dol_include_once("/fourn/class/paiementfourn.class.php");
dol_include_once("/fourn/class/fournisseur.facture.class.php");
dol_include_once("/fourn/class/fournisseur.class.php");
dol_include_once("/accountingex/class/bookkeeping.class.php");
dol_include_once("/societe/class/client.class.php");
dol_include_once("/compta/facture/class/facture.class.php");
dol_include_once("/accountingex/class/bookkeeping.class.php");
dol_include_once("/accountingex/class/accountingaccount.class.php");

// Langs
$langs->load("companies");
$langs->load("compta");
$langs->load("bills");
$langs->load("other");
$langs->load("bank");
$langs->load("main");
$langs->load("accountingex@accountingex");

$date_startmonth=GETPOST('date_startmonth');
$date_startday=GETPOST('date_startday');
$date_startyear=GETPOST('date_startyear');
$date_endmonth=GETPOST('date_endmonth');
$date_endday=GETPOST('date_endday');
$date_endyear=GETPOST('date_endyear');

// Security check
if ($user->societe_id > 0) accessforbidden();
if (!$user->rights->accountingex->access) accessforbidden();

// select journal to export
$list=array('ACCOUNTINGEX_EXPORTJOURNAL_SELL',
            'ACCOUNTINGEX_EXPORTJOURNAL_PURCHASE',
            'ACCOUNTINGEX_EXPORTJOURNAL_SOCIAL',
            'ACCOUNTINGEX_EXPORTJOURNAL_CASH',
            'ACCOUNTINGEX_EXPORTJOURNAL_MISC',
            'ACCOUNTINGEX_EXPORTJOURNAL_BANK',
);

/*
 * Actions
 */
$action=GETPOST('action','alpha');


$year_current = strftime("%Y",dol_now());
$pastmonth = strftime("%m",dol_now()) - 1;
$pastmonthyear = $year_current;
if ($pastmonth == 0)
{
	$pastmonth = 12;
	$pastmonthyear--;
}


$date_start=dol_mktime(0, 0, 0, $date_startmonth, $date_startday, $date_startyear);
$date_end=dol_mktime(23, 59, 59, $date_endmonth, $date_endday, $date_endyear);

if (empty($date_start) || empty($date_end)) // We define date_start and date_end
{
	$date_start=dol_get_first_day($pastmonthyear,$pastmonth,false); $date_end=dol_get_last_day($pastmonthyear,$pastmonth,false);
}



$p = explode(":", $conf->global->MAIN_INFO_SOCIETE_COUNTRY);
$idpays = $p[0];


// Set journal to export yesno
foreach ($list as $key)
{
// print "action=".$action ." & key=". $key ."<br>" ;
 if ($action == $key) {
	$setjournal = GETPOST('value','int');
	$res = dolibarr_set_const($db, $key, $setjournal,'yesno',0,'',$conf->entity);
	if (! $res > 0) $error++;
	if (! $error)
	{
		$mesg = "<font class=\"ok\">".$langs->trans("SetupSaved")."</font>";
	}
	else
	{
		$mesg = "<font class=\"error\">".$langs->trans("Error")."</font>";
	}
 }
}



//QUERY DB & PREPARE DATA ARRAY TO DISPLAY
//SHOULD BE CALLED ONLY if we have ACTION=export_xxxxx
// TODO Move as a function and call function if needed according action
	// FOR SELL JOURNAL
	$tabfac 		= array();
	$tabdet2 		= array();
	$tabht 		= array();
	$tabtva 		= array();
	$tabttc 		= array();
	$tabcompany 	= array();
	if (ACCOUNTINGEX_EXPORTJOURNAL_SELL == 1 ){
	$ressqlarray=@include("sellsjournal-sqlarray.php");
	if (! $ressqlarray) print"Include of sellsjournal-sqlarray.php fails in accountingex/journal";
	}
	// FOR PURCHASESJOURNAL
	if (ACCOUNTINGEX_EXPORTJOURNAL_PURCHASE == 1 ){
	$ressqlarray=@include("purchasesjournal-sqlarray.php");
	if (! $ressqlarray) print "Include of puchasesjournal-sqlarray.php fails in accountingex/journal";
	}

	$tabpay = array ();
	$tabbq = array ();
	$tabtp = array ();
	$tabcompany = array ();
	$tabtype = array ();
	if (ACCOUNTINGEX_EXPORTJOURNAL_CASH == 1 ){
	$ressqlarray=@include("cashjournal-sqlarray.php");
	if (! $ressqlarray) print "Include of cashjournal-sqlarray.php fails in accountingex/journal";
	}
//	print "/////////////////////////////// TABPAY CASHJOURNAL ///////////////////////";
//	print_r ($tabpay);
	if (ACCOUNTINGEX_EXPORTJOURNAL_BANK == 1 ){
	$ressqlarray=@include("bankjournal-sqlarray.php");
	if (! $ressqlarray) print "Include of bankjournal-sqlarray.php fails in accountingex/journal";
	}
/*
	print "/////////////////////////////// TABFAC SELLJOURNAL ///////////////////////";
	print_r ($tabfac);
	print "/////////////////////////////// TABDET2 SELLJOURNAL ///////////////////////";
	print_r ($tabdet2);
	print "/////////////////////////////// TABPAY BANKJOURNAL ///////////////////////";
	print_r ($tabpay);
	print "/////////////////////////////// TABBQ BANKJOURNAL ///////////////////////";
	print_r ($tabbq);
	print "/////////////////////////////// TABCOMPANY BANKJOURNAL ///////////////////////";
	print_r ($tabcompany);
*/





// #####################################################################################
// # Check action to do from GETPOST                                                   #
// #####################################################################################
// export journal
// TODO add permission export by journal
if (GETPOST('action') == 'export_file')
{
	// Export file
	$res=@include("exportjournal-file.php");
	if (! $res) print "Include of exportjournal-file.php fails in accountingex/journal";

}

// Export file
if (GETPOST('action') == 'export_save')
{
	$res=@include("exportjournal-save.php");
	if (! $res) print "Include of exportjournal-save.php fails in accountingex/journal";

}

// reset exported date in table will allow re-export
// TODO : Add permission reset export date
if (GETPOST('action') == 'export_reset')
{
	// Reset exported line
	$res=@include("exportjournal-reset.php");
	if (! $res) print "Include of exportjournal-reset.php fails in accountingex/journal";
}



/*
 * View
 */
// #################################################################
// # no action we set variables to display form / setting / output #
// #################################################################
$form=new Form($db);
$formfile = new FormFile($db);
$var=True;
//TODO ADD TRANSLATION JOURNAL NAME LIST EXPORTED
$filename_journal="";
/*
//TODO Should need to rename value ACCOUNTINGEX_SELL_JOURNAL -> ACCOUNTINGEX_JOURNAL_SELL
foreach ($list as $key)
{
if ($conf->global->$key            ==1 ) $filename_journal .= '+'.$conf->global->ACCOUNTINGEX_SELL_JOURNAL;
*/
if ($conf->global->ACCOUNTINGEX_EXPORTJOURNAL_SELL      ==1 ) $filename_journal .= '+'.$conf->global->ACCOUNTINGEX_SELL_JOURNAL;
if ($conf->global->ACCOUNTINGEX_EXPORTJOURNAL_PURCHASE  ==1 ) $filename_journal .= '+'.$conf->global->ACCOUNTINGEX_PURCHASE_JOURNAL;
if ($conf->global->ACCOUNTINGEX_EXPORTJOURNAL_SOCIAL    ==1 ) $filename_journal .= '+'.$conf->global->ACCOUNTINGEX_SOCIAL_JOURNAL;
if ($conf->global->ACCOUNTINGEX_EXPORTJOURNAL_CASH      ==1 ) $filename_journal .= '+'.$conf->global->ACCOUNTINGEX_CASH_JOURNAL;
if ($conf->global->ACCOUNTINGEX_EXPORTJOURNAL_MISC      ==1 ) $filename_journal .= '+'.$conf->global->ACCOUNTINGEX_MISCELLANEOUS_JOURNAL;
if ($conf->global->ACCOUNTINGEX_EXPORTJOURNAL_BANK      ==1 ) $filename_journal .= '+'.$conf->global->ACCOUNTINGEX_BANK_JOURNAL;

$filename=accountingex_export_filename_set($filename="",$filename_journal);
$nom=$langs->trans("ExportJournal"). ' - ' . $filename ;
$nomlink='';
$period=$form->select_date($date_start,'date_start',0,0,0,'',1,0,1).' - '.$form->select_date($date_end,'date_end',0,0,0,'',1,0,1);
$periodlink='';
$description=$langs->trans("DescSellsJournal").' '.$filename.'<br>';
$builddate=time();
$exportlink='';

if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) $description.= $langs->trans("DepositsAreNotIncluded");
else  $description.= $langs->trans("DepositsAreIncluded");

// Display form / setting / output
llxHeader('',$nom);
print '<div class="fichecenter">';
//show header report to select date export range
	print '<div class="fichehalfleft">';
		report_header($nom,$nomlink,$period,$periodlink,$description,$builddate,$exportlink, array('action'=>'') );
		print '<input type="button" class="button" style="float: right;" value="Preview" onclick="launch_preview();" />';  // preview line to export
		print '<input type="button" class="button" style="float: right;" value="Export" onclick="launch_export();" />';    // download export
		print '<input type="button" class="button" style="float: right;" value="Save" onclick="launch_export_save();" />'; // save export in local server documents/accountingexpert folder
		print '<input type="button" class="button" style="float: right;" value="Folder2" onclick="launch_folder1();" />';  // open local user folder1
		print '<input type="button" class="button" style="float: right;" value="Folder1" onclick="launch_folder2();" />';  // open local user folder2
		//Export reset will be moved to setting / tab / export with OWN permission
		print '<input type="button" class="button" style="float: right;" value="ExportedReset" onclick="launch_export_reset();" />'; // planned, May reset export date value in db to allow re-exporting
		//
	print "</div>";
	//show option to select journal to export
	//TODO add perms to allow change journal export option
	print '<div class="fichehalfright">';
		print '<div class="tabBar">';
		print '<table class="border" width="100%">';
		//
		$num=count($list);
		print '<table class="noborder" width="100%">';
			print '<tr class="liste_titre">';
				 print '<td colspan="3">'.$langs->trans('Journaux').'</td>';
			print "</tr>\n";
			// todo manage bank journal separatly
			// add permission to allow change settings
			foreach ($list as $key)
			{
				$var=!$var;
				$label = $langs->trans($key);
				print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
				print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
				print '<input type="hidden" name="action" value="updateoptionsdone">';
				print "<tr ".$bc[$var].">";
				print '<td width="80%">'.$label.'</td>';
				if (! empty($conf->global->$key))
				{
				print '<td align="center" colspan="2"><a href="'.$_SERVER['PHP_SELF'].'?action='.$key.'&value=0">';
				print img_picto($langs->trans("Activated"),'switch_on');
				print '</a></td>';
			}
			else
			{
				print '<td align="center" colspan="2"><a href="'.$_SERVER['PHP_SELF'].'?action='.$key.'&value=1">';
				print img_picto($langs->trans("Disabled"),'switch_off');
				print '</a></td>';
				}
				print '</tr>';
				print '</form>';
				$i++;
			}
		print '</table>';
		print '</table>';
	  print "</div>";
	print "</div>";	// end fichehalfright
print '</div>';	// end fichecenter

/*
 * Show result array
 */

print '<br />';
print '<div class="fichecenter">';
if (GETPOST('action') == 'preview')
{
	//preview data
	$res=@include("exportjournal-preview.php");
	if (! $res) print "Include of exportjournal-preview.php fails in accountingex/journal";
	print "<br />/////////////////////////////// TABFAC SELLJOURNAL ///////////////////////<br />";
	print_r ($tabfac);
	print "<br />/////////////////////////////// TABDET2 SELLJOURNAL ///////////////////////<br />";
	print_r ($tabdet2);
	print "<br />/////////////////////////////// TABPAY BANKJOURNAL ///////////////////////<br />";
	print_r ($tabpay);
	print "<br />/////////////////////////////// TABBQ BANKJOURNAL ///////////////////////<br />";
	print_r ($tabbq);
	print "<br />/////////////////////////////// TABCOMPANY BANKJOURNAL ///////////////////////<br />";
	print_r ($tabcompany);

}
else
{
	// TODO maybe we can move exported files as a tab
	$filearray=dol_dir_list($conf->accountingexpert->dir_output.'/exportjournal','files',0,'','',$sortfield,(strtolower($sortorder)=='asc'?SORT_ASC:SORT_DESC),1);
	// should be in folder accountingex but $conf->accountingexpert->dir_output is accountingexpert
	print_r ( $filearray );
	// print '################$conf->accountingexpert->dir_output='.$conf->accountingexpert->dir_output.'-#####################<br />';
	//print_r ( $conf );
	//$result=$formfile->list_of_documents($filearray,null,'accountingexpert/exportjournal','',1,'',1,0,$langs->trans("NoBackupFileAvailable"),0,$langs->trans("PreviousDumpFiles"));
	$result=$formfile->list_of_documents($filearray,null,'accountingexpert','',1,'',1,0,$langs->trans("NoBackupFileAvailable"),0,$langs->trans("PreviousDumpFiles"));
}
print '<br />';
print '<br />';
print '</div>';	// end fichecenter

// End of page
llxFooter();

$db->close();

//TODO ADD BUTTON FUNCTION
print  '<script type="text/javascript">
		function launch_preview() {
			$("div.fiche div.tabBar form input[name=\"action\"]").val("preview");
			$("div.fiche div.tabBar form input[type=\"submit\"]").click();
			$("div.fiche div.tabBar form input[name=\"action\"]").val("");
		}
		function launch_export() {
		    $("div.fiche div.tabBar form input[name=\"action\"]").val("export_file");
			$("div.fiche div.tabBar form input[type=\"submit\"]").click();
		    $("div.fiche div.tabBar form input[name=\"action\"]").val("");
		}
		function launch_export_save() {
			$("div.fiche div.tabBar form input[name=\"action\"]").val("export_save");
			$("div.fiche div.tabBar form input[type=\"submit\"]").click();
			$("div.fiche div.tabBar form input[name=\"action\"]").val("");
		}
		function launch_export_reset() {
		    $("div.fiche div.tabBar form input[name=\"action\"]").val("export_reset");
			$("div.fiche div.tabBar form input[type=\"submit\"]").click();
		    $("div.fiche div.tabBar form input[name=\"action\"]").val("");
		}
	</script>';
