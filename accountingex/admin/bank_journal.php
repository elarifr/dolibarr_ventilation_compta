<?php
/*

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
 *   \file       htdocs/accountingex/bank_journal.php
 *   \brief      Tab for bank journal accountingex
 *   \ingroup    accountingex
 */

// Dolibarr environment
$res=@include("../main.inc.php");
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/bank.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
//require_once DOL_DOCUMENT_ROOT.'/core/class/html.formbank.class.php';
//require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

$langs->load("banks");
//$langs->load("categories");
//$langs->load("companies");

$action = GETPOST('action');

// Security check Societe restrictedArea ?
if ($user->societe_id > 0) accessforbidden();
if (!$user->rights->accountingex->admin) accessforbidden();


$sql  = "SELECT ba.rowid, ba.ref , ba.label, ba.bank , ba.account_number, ba.code_journal ";
$sql .= " FROM ".MAIN_DB_PREFIX."lx_bank_account as ba";
$sql .= " WHERE ba.clos = 0" ;
$sql .= " ORDER BY label";

dol_syslog('accountingex/admin/bank_journal.php:: $sql='.$sql);

$resql = $db->query($sql);
if ($resql)
{
	$num = $db->num_rows($resql);
	$i = 0;

}

/*
 * Actions
 */

if ($action == 'setnote_public' && $user->rights->societe->creer)
{
	$object->fetch($id);
	$result=$object->update_note(dol_html_entity_decode(GETPOST('note_public'), ENT_QUOTES),'_public');
	if ($result < 0) setEventMessage($object->error,'errors');
}

else if ($action == 'setnote_private' && $user->rights->societe->creer)
{
	$object->fetch($id);
	$result=$object->update_note(dol_html_entity_decode(GETPOST('note_private'), ENT_QUOTES),'_private');
	if ($result < 0) setEventMessage($object->error,'errors');
}

/*
 *	View
 */

$form = new Form($db);
//$formbank = new FormBank($db);
//$formcompany = new FormCompany($db);


llxHeader();

//if ($id > 0)
//{
		$account = new Account($db);
		if ($_GET["id"])
		{
			$account->fetch($_GET["id"]);
		}
		if ($_GET["ref"])
		{
			$account->fetch(0,$_GET["ref"]);
			$_GET["id"]=$account->id;
		}

		/*
		* Affichage onglets
		*/

		// Onglets
		$head=bank_prepare_head($account);
		dol_fiche_head($head, 'accountingex', $langs->trans("FinancialAccount"),0,'account');


		print '<table class="border" width="100%">';

		$linkback = '<a href="'.DOL_URL_ROOT.'/compta/bank/index.php">'.$langs->trans("BackToList").'</a>';

		// Ref
		print '<tr><td valign="top" width="25%">'.$langs->trans("Ref").'</td>';
		print '<td colspan="3">';
		print $form->showrefnav($account, 'ref', $linkback, 1, 'ref');
		print '</td></tr>';

		// Label
		print '<tr><td valign="top">'.$langs->trans("Label").'</td>';
		print '<td colspan="3">'.$account->label.'</td></tr>';

		// Journal
		print '<tr><td valign="top">'.$langs->trans("Journal").'</td>';
		print '<td colspan="3">'.$account->code_journal.'</td></tr>';


		print '</table>';
		print '</div>';
		print_r ($account);

    print '<br>';

//    $colwidth='25';
//    include DOL_DOCUMENT_ROOT.'/core/tpl/notes.tpl.php';


    dol_fiche_end();
//}

llxFooter();
$db->close();

?>