<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2005 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2013 Olivier Geffroy  <jeff@jeffinfo.com>
 * Copyright (C) 2013 Florian Henry	  <florian.henry@open-concept.pro>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
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
 * $Id: fiche.php,v 1.14 2011/07/31 22:23:31 eldy Exp $
 */

/**
 * \file htdocs/custom/ventilation/param/comptes/fiche.php
 * \ingroup ventilation compta
 * \brief Page de la fiche des comptes comptables
 * \version $Revision: 1.14 $
 */

// Dolibarr environment
$res = @include ("../main.inc.php");
if (! $res && file_exists ( "../main.inc.php" ))
	$res = @include ("../main.inc.php");
if (! $res && file_exists ( "../../main.inc.php" ))
	$res = @include ("../../main.inc.php");
if (! $res && file_exists ( "../../../main.inc.php" ))
	$res = @include ("../../../main.inc.php");
if (! $res)
	die ( "Include of main fails" );

dol_include_once ( "/ventilation/class/bookkeeping.class.php" );

$langs->load ( "ventilation@ventilation" );

$mesg = '';
$action = GETPOST ( 'action' );
$piece_num = GETPOST ( "piece_num" );
$id = GETPOST ( "id" );

$numero_compte = GETPOST ( 'numero_compte' );
$code_tiers = GETPOST ( 'code_tiers' );
$label_compte = GETPOST ( 'label_compte' );
$debit = price2num ( GETPOST ( 'debit' ) );
$credit = price2num ( GETPOST ( 'credit' ) );

if ($action == "confirm_update") {
	
	$error = 0;
	
	if ((intval ( $debit ) != 0) && (intval ( $credit ) != 0)) {
		setEventMessage ( $langs->trans ( 'ErrorDebitCredit' ), 'errors' );
		$error ++;
	}
	
	if (empty ( $error )) {
		$book = new BookKeeping ( $db );
		
		$result = $book->fetch ( $id );
		if ($result < 0) {
			setEventMessage ( $book->errors, 'errors' );
		} else {
			$book->numero_compte = $numero_compte;
			$book->code_tiers = $code_tiers;
			$book->label_compte = $label_compte;
			$book->debit = $debit;
			$book->credit = $credit;
			
			if (! empty ( $debit )) {
				$book->montant = $debit;
				$book->sens = 'D';
			}
			if (! empty ( $credit )) {
				$book->montant = $credit;
				$book->sens = 'C';
			}
			
			$result = $book->update ();
			if ($result < 0) {
				setEventMessage ( $book->errors, 'errors' );
			} else {
				setEventMessage ( $langs->trans ( 'Saved' ), 'mesgs' );
				$action = '';
			}
		}
	}
} 

else if ($action == "add") {
	
	$error = 0;
	if ((intval ( $debit ) != 0) && (intval ( $credit ) != 0)) {
		setEventMessage ( $langs->trans ( 'ErrorDebitCredit' ), 'errors' );
		$error ++;
	}
	
	if (empty ( $error )) {
		$book = new BookKeeping ( $db );
		
		$book->numero_compte = $numero_compte;
		$book->code_tiers = $code_tiers;
		$book->label_compte = $label_compte;
		$book->debit = $debit;
		$book->credit = $credit;
		$book->doc_date = GETPOST ( 'doc_date' );
		$book->doc_type = GETPOST ( 'doc_type' );
		$book->piece_num = $piece_num;
		$book->doc_ref = GETPOST ( 'doc_ref' );
		$book->code_journal = GETPOST ( 'code_journal' );
		$book->fk_doc = GETPOST ( 'fk_doc' );
		$book->fk_docdet = GETPOST ( 'fk_docdet' );
		
		if (! empty ( $debit )) {
			$book->montant = $debit;
			$book->sens = 'D';
		}
		if (! empty ( $credit )) {
			$book->montant = $credit;
			$book->sens = 'C';
		}
		
		$result = $book->create_std ( $user );
		if ($result < 0) {
			setEventMessage ( $book->errors, 'errors' );
		} else {
			setEventMessage ( $langs->trans ( 'Saved' ), 'mesgs' );
			$action = '';
		}
	}
} 

else if ($action == "confirm_delete") {
	$book = new BookKeeping ( $db );
	
	$result = $book->fetch ( $id );
	
	$piece_num = $book->piece_num;
	
	if ($result < 0) {
		setEventMessage ( $book->errors, 'errors' );
	} else {
		$result = $book->delete ( $user );
		if ($result < 0) {
			setEventMessage ( $book->errors, 'errors' );
		}
	}
	$action = '';
} 

else if ($action == "confirm_create") {
	$book = new BookKeeping ( $db );
	
	$book->label_compte = '';
	$book->debit = 0;
	$book->credit = 0;
	$book->doc_date = $date_start = dol_mktime ( 0, 0, 0, GETPOST ( 'doc_datemonth' ), GETPOST ( 'doc_dateday' ), GETPOST ( 'doc_dateyear' ) );
	$book->doc_type = GETPOST ( 'doc_type' );
	$book->piece_num = GETPOST ( 'next_num_mvt' );
	$book->doc_ref = GETPOST ( 'doc_ref' );
	$book->code_journal = GETPOST ( 'code_journal' );
	$book->fk_doc = 0;
	$book->fk_docdet = 0;
	
	$book->montant = 0;
	
	$result = $book->create_std ( $user );
	if ($result < 0) {
		setEventMessage ( $book->errors, 'errors' );
	} else {
		setEventMessage ( $langs->trans ( 'Saved' ), 'mesgs' );
		$action = '';
		$piece_num = $book->piece_num;
	}
}

llxHeader ( "", "Modification compte" );

$html = new Form ( $db );
$nbligne = 0;

/*
 * Confirmation de la suppression de la commande
*/
if ($action == 'delete') {
	$formconfirm = $html->formconfirm ( $_SERVER ["PHP_SELF"] . '?id=' . $id, $langs->trans ( 'DeleteMvt' ), $langs->trans ( 'ConfirmDeleteMvt' ), 'confirm_delete', '', 0, 1 );
	print $formconfirm;
}

if ($action == 'create') {
	
	print_fiche_titre ( $langs->trans ( "CreateMvts" ) );
	
	$code_journal_array = array (
		$conf->global->VENTILATION_SELL_JOURNAL=>$conf->global->VENTILATION_SELL_JOURNAL,
		$conf->global->VENTILATION_PURCHASE_JOURNAL=>$conf->global->VENTILATION_PURCHASE_JOURNAL,
		$conf->global->VENTILATION_BANK_JOURNAL=>$conf->global->VENTILATION_BANK_JOURNAL,
		$conf->global->VENTILATION_SOCIAL_JOURNAL=>$conf->global->VENTILATION_SOCIAL_JOURNAL 
	);
	
	$book = new BookKeeping ( $db );
	$next_num_mvt = $book->next_num_mvt ();
	
	print '<form action="' . $_SERVER ["PHP_SELF"] . '" name="create_mvt" method="post">';
	print '<input type="hidden" name="action" value="confirm_create">' . "\n";
	print '<input type="hidden" name="next_num_mvt" value="' . $next_num_mvt . '">' . "\n";
	
	print '<table class="border" width="100%">';
	print '<tr class="pair">';
	print '<td>' . $langs->trans ( "NumMvts" ) . '</td>';
	print '<td>' . $next_num_mvt . '</td>';
	print '</tr>';
	print '<tr class="impair">';
	print '<td>' . $langs->trans ( "Docdate" ) . '</td>';
	print '<td>';
	print $html->select_date ( '', 'doc_date', '', '', '', "create_mvt", 1, 1 );
	print '</td>';
	
	print '</tr>';
	print '<tr class="pair">';
	print '<td>' . $langs->trans ( "Codejournal" ) . '</td>';
	
	print '<td>' . $html->selectarray ( 'code_journal', $code_journal_array ) . '</td>';
	print '</tr>';
	print '<tr class="impair">';
	print '<td>' . $langs->trans ( "Docref" ) . '</td>';
	print '<td><input type="text" size="20" name="doc_ref" value=""/></td>';
	print '</tr>';
	print '<tr class="pair">';
	print '<td>' . $langs->trans ( "Doctype" ) . '</td>';
	print '<td><input type="text" size="20" name="doc_type" value=""/></td>';
	print '</tr>';
	print '</table>';
	print '<BR>';
	print '<input type="submit" class="butAction" value="' . $langs->trans ( "Record" ) . '">';
	
	print '</form>';
} else {
	$book = new BookKeeping ( $db );
	$result = $book->fetch_per_mvt ( $piece_num );
	if ($result < 0) {
		setEventMessage ( $book->errors, 'errors' );
	}
	if (! empty ( $book->piece_num )) {
		
		print_fiche_titre ( $langs->trans ( "UpdateMvts" ) );
		
		print '<table class="border" width="100%">';
		print '<tr class="pair">';
		print '<td>' . $langs->trans ( "NumMvts" ) . '</td>';
		print '<td>' . $book->piece_num . '</td>';
		print '</tr>';
		print '<tr class="impair">';
		print '<td>' . $langs->trans ( "Docdate" ) . '</td>';
		print '<td>' . dol_print_date ( $book->doc_date, 'daytextshort' ) . '</td>';
		print '</tr>';
		print '<tr class="pair">';
		print '<td>' . $langs->trans ( "Codejournal" ) . '</td>';
		print '<td>' . $book->code_journal . '</td>';
		print '</tr>';
		print '<tr class="impair">';
		print '<td>' . $langs->trans ( "Docref" ) . '</td>';
		print '<td>' . $book->doc_ref . '</td>';
		print '</tr>';
		print '<tr class="pair">';
		print '<td>' . $langs->trans ( "Doctype" ) . '</td>';
		print '<td>' . $book->doc_type . '</td>';
		print '</tr>';
		print '</table>';
		
		$result = $book->fetch_all_per_mvt ( $piece_num );
		if ($result < 0) {
			setEventMessage ( $book->errors, 'errors' );
		} else {
			
			print_fiche_titre ( $langs->trans ( "ListeMvts" ) );
			print "<table class=\"noborder\" width=\"100%\">";
			if (count ( $book->linesmvt ) > 0) {
				
				print '<tr class="liste_titre">';
				
				print_liste_field_titre ( $langs->trans ( "Numerocompte" ) );
				print_liste_field_titre ( $langs->trans ( "Code_tiers" ) );
				print_liste_field_titre ( $langs->trans ( "Labelcompte" ) );
				print_liste_field_titre ( $langs->trans ( "Debit" ) );
				print_liste_field_titre ( $langs->trans ( "Credit" ) );
				print_liste_field_titre ( $langs->trans ( "Amount" ) );
				print_liste_field_titre ( $langs->trans ( "Sens" ) );
				
				print '<td></td>';
				print "</tr>\n";
				
				foreach ( $book->linesmvt as $line ) {
					$var = ! $var;
					print "<tr $bc[$var]>";
					
					if ($action == 'update' && $line->id == $id) {
						
						print '<form action="' . $_SERVER ["PHP_SELF"] . '?piece_num=' . $book->piece_num . '" method="post">';
						print '<input type="hidden" name="id" value="' . $line->id . '">' . "\n";
						print '<input type="hidden" name="action" value="confirm_update">' . "\n";
						print '<td><input type="text" size="6" name="numero_compte" value="' . $line->numero_compte . '"/></td>';
						print '<td><input type="text" size="15" name="code_tiers" value="' . $line->code_tiers . '"/></td>';
						print '<td><input type="text" size="15" name="label_compte" value="' . $line->label_compte . '"/></td>';
						print '<td><input type="text" size="6" name="debit" value="' . price ( $line->debit ) . '"/></td>';
						print '<td><input type="text" size="6" name="credit" value="' . price ( $line->credit ) . '"/></td>';
						print '<td>' . $line->montant . '</td>';
						print '<td>' . $line->sens . '</td>';
						
						print '<td>';
						if ($user->rights->compta->ventilation->parametrer) {
							print '<input type="submit" class="button" value="' . $langs->trans ( "Update" ) . '">';
						}
						print '</form>';
						print '</td>';
					} else {
						print '<td>' . $line->numero_compte . '</td>';
						print '<td>' . $line->code_tiers . '</td>';
						print '<td>' . $line->label_compte . '</td>';
						print '<td>' . $line->debit . '</td>';
						print '<td>' . $line->credit . '</td>';
						print '<td>' . $line->montant . '</td>';
						print '<td>' . $line->sens . '</td>';
						
						print '<td>';
						if ($user->rights->compta->ventilation->parametrer) {
							print '<a href="./fiche.php?action=update&id=' . $line->id . '&piece_num=' . $line->piece_num . '">';
							print img_edit ();
							print '</a>&nbsp;';
							print '<a href="./fiche.php?action=delete&id=' . $line->id . '&piece_num=' . $line->piece_num . '">';
							print img_delete ();
							print '</a>';
						}
						print '</td>';
					}
					print "</tr>\n";
				}
				
				if ($action == "" || $action == 'add') {
					$var = ! $var;
					print "<tr $bc[$var]>";
					
					print '<form action="' . $_SERVER ["PHP_SELF"] . '?piece_num=' . $book->piece_num . '" method="post">';
					print '<input type="hidden" name="action" value="add">' . "\n";
					print '<input type="hidden" name="doc_date" value="' . $book->doc_date . '">' . "\n";
					print '<input type="hidden" name="doc_type" value="' . $book->doc_type . '">' . "\n";
					print '<input type="hidden" name="doc_ref" value="' . $book->doc_ref . '">' . "\n";
					print '<input type="hidden" name="code_journal" value="' . $book->code_journal . '">' . "\n";
					print '<input type="hidden" name="fk_doc" value="' . $book->fk_doc . '">' . "\n";
					print '<input type="hidden" name="fk_docdet" value="' . $book->fk_docdet . '">' . "\n";
					print '<td><input type="text" size="6" name="numero_compte" value="' . $numero_compte . '"/></td>';
					print '<td><input type="text" size="15" name="code_tiers" value="' . $code_tiers . '"/></td>';
					print '<td><input type="text" size="15" name="label_compte" value="' . $label_compte . '"/></td>';
					print '<td><input type="text" size="6" name="debit" value="' . price ( $debit ) . '"/></td>';
					print '<td><input type="text" size="6" name="credit" value="' . price ( $credit ) . '"/></td>';
					print '<td></td>';
					print '<td></td>';
					print '<td><input type="submit" class="button" value="' . $langs->trans ( "Save" ) . '"></td>';
					
					print '</tr>';
				}
				
				print "</table>";
			}
		}
	} else {
		print_fiche_titre ( $langs->trans ( "NoRecords" ) );
	}
}

$db->close ();
llxFooter ( '' );