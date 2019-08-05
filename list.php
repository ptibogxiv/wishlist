<?php
/* Copyright (C) 2019-2019 Thibault FOUCART <support@ptibogxiv.net>
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
 *  \file       htdocs/societe/project.php
 *  \ingroup    societe
 *  \brief      Page of third party projects
 */

$res=@include("../main.inc.php");                                // For root directory
if (! $res) $res=@include("../../main.inc.php");                // For "custom" directory
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

$langs->loadLangs(array("companies", "products"));

// Security check
$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$rowid  = GETPOST('rowid', 'int');
$action = GETPOST('action', 'alpha');
$cancel = GETPOST('cancel', 'alpha');

$search_ref	= GETPOST('search_ref','alpha');
$search_label		= GETPOST('search_label','alpha');
$search_qty		= GETPOST('search_qty','int');
$type				= GETPOST('type','alpha');
$status				= GETPOST('status','alpha');

$limit = GETPOST('limit','int')?GETPOST('limit','int'):$conf->liste_limit;
$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page = GETPOST("page",'int');
if (empty($page) || $page == -1) { $page = 0; }     // If $page is not defined, or '' or -1
$offset = $limit * $page ;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortorder) {  $sortorder="DESC"; }
//if (! $sortfield) {  $sortfield="d.lastname"; }

$label=GETPOST("label","alpha");
$description=GETPOST("description","alpha");
$qty=GETPOST("qty","int");

if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'societe', $socid, '&societe');

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('wishlistthirdparty'));

$object = new Product($db);
$result = $object->fetch($id, $ref);
  
/*
 *	Actions
 */

$parameters=array('id'=>$socid);
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');



/*
 *	View
 */

$contactstatic = new Contact($db);

$form = new Form($db);

$title = $langs->trans('ProductServiceCard');
$helpurl = '';
$shortlabel = dol_trunc($object->label,16);
if (GETPOST("type") == '0' || ($object->type == Product::TYPE_PRODUCT))
{
	$title = $langs->trans('Product')." ". $shortlabel ." - ".$langs->trans('Card');
	$helpurl='EN:Module_Products|FR:Module_Produits|ES:M&oacute;dulo_Productos';
}
if (GETPOST("type") == '1' || ($object->type == Product::TYPE_SERVICE))
{
	$title = $langs->trans('Service')." ". $shortlabel ." - ".$langs->trans('Card');
	$helpurl='EN:Module_Services_En|FR:Module_Services|ES:M&oacute;dulo_Servicios';
}

llxHeader('', $title, $helpurl);

$head=product_prepare_head($object);
$titre=$langs->trans("CardProduct".$object->type);
$picto=($object->type== Product::TYPE_SERVICE?'service':'product');

dol_fiche_head($head, 'wishlist', $titre, -1, $picto);

$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php?restore_lastsearch_values=1&type='.$object->type.'">'.$langs->trans("BackToList").'</a>';
$object->next_prev_filter=" fk_product_type = ".$object->type;

$shownav = 1;
if ($user->societe_id && ! in_array('product', explode(',',$conf->global->MAIN_MODULES_FOR_EXTERNAL))) $shownav=0;

dol_banner_tab($object, 'ref', $linkback, $shownav, 'ref');

dol_fiche_end();

print '<br>';


	// Wishlist
  
		$sql = "SELECT t.rowid, t.fk_product as product, t.qty as qty, t.fk_soc as socid";
    $sql.= " , p.label, p.ref as ref";
		$sql.= " FROM ".MAIN_DB_PREFIX."wishlist as t";
    $sql.= " JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = t.fk_product";
		$sql.= " WHERE t.entity IN (".getEntity('product').")";
    $sql.= " AND t.fk_product = ".$object->id;

		if ($sall)
		{
			//$sql.=natural_search(array("f.firstname","d.lastname","d.societe","d.email","d.login","d.address","d.town","d.note_public","d.note_private"), $sall);
		}
		if ($status != '')
		{
		    $sql.= " AND t.statut IN (".$db->escape($status).")";     // Peut valoir un nombre ou liste de nombre separes par virgules
		}
		if ($action == 'search')
		{
			if (GETPOST('search'))
			{
		  		//$sql.= natural_search(array("d.firstname","d.lastname"), GETPOST('search','alpha'));
		  	}
		}
		if (! empty($search_socid))
		{
			$sql.= natural_search("p.socid", $search_socid);
		}
		if (! empty($search_label))
		{
			$sql.= natural_search("p.label", $search_label);
		}
		if (! empty($search_qty))
		{
			$sql.= natural_search("t.qty", $search_qty);
		}
		if ($filter == 'uptodate')
		{
		    //$sql.=" AND datefin >= '".$db->idate($now)."'";
		}
		if ($filter == 'outofdate')
		{
		    //$sql.=" AND datefin < '".$db->idate($now)."'";
		}
		// Count total nb of records
		$nbtotalofrecords = '';
		if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST))
		{
			$resql = $db->query($sql);
		    if ($resql) $nbtotalofrecords = $db->num_rows($result);
		    else dol_print_error($db);
		}
		// Add order and limit
		$sql.= " ".$db->order($sortfield,$sortorder);
		$sql.= " ".$db->plimit($conf->liste_limit+1, $offset);

		$resql = $db->query($sql);
		if ($resql)
		{
		    $num = $db->num_rows($resql);
		    $i = 0;

		    $titre=$langs->trans("ProductsList");
		    if ($status != '')
		    {
		        if ($status == '-1,1')								{ $titre=$langs->trans("MembersListQualified"); }
		        else if ($status == '-1')							{ $titre=$langs->trans("MembersListToValid"); }
		        else if ($status == '1' && ! $filter)				{ $titre=$langs->trans("MembersListValid"); }
		        else if ($status == '1' && $filter=='uptodate')		{ $titre=$langs->trans("MembersListUpToDate"); }
		        else if ($status == '1' && $filter=='outofdate')	{ $titre=$langs->trans("MembersListNotUpToDate"); }
		        else if ($status == '0')							{ $titre=$langs->trans("MembersListResiliated"); }
		    }
		    elseif ($action == 'search')
		    {
		        $titre=$langs->trans("MembersListQualified");
		    }

		    $param="&socid=".$socid;
		    if (! empty($status))			$param.="&status=".$status;
		    if (! empty($search_socid))	$param.="&search_ref=".$search_socid;
		    if (! empty($search_label))		$param.="&search_label=".$search_label;
		    if (! empty($search_email))		$param.="&search_email=".$search_email;
		    if (! empty($filter))			$param.="&filter=".$filter;

		    if ($sall)
		    {
		        print $langs->trans("Filter")." (".$langs->trans("Lastname").", ".$langs->trans("Firstname").", ".$langs->trans("EMail").", ".$langs->trans("Address")." ".$langs->trans("or")." ".$langs->trans("Town")."): ".$sall;
		    }

			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?socid='.$socid.'">';
			print '<input class="flat" type="hidden" name="id" value="'.$id.'" size="12"></td>';

			print '<br>';
      
      print_barre_liste('',$page,$_SERVER["PHP_SELF"],$param,$sortfield,$sortorder,'',$num,$nbtotalofrecords);

      $moreforfilter = '';

      print '<div class="div-table-responsive">';
      print '<table class="tagtable liste'.($moreforfilter?" listwithfilterbefore":"").'">'."\n";

			// Lignes des champs de filtre
			print '<tr class="liste_titre_filter">';

			print '<td class="liste_titre" align="left">';
			print '<input class="flat" type="text" name="search_socid" value="'.dol_escape_htmltag($search_socid).'" size="7"></td>';

			print '<td class="liste_titre" align="left">';
			print '<input class="flat" type="text" name="search_qty" value="'.dol_escape_htmltag($search_qty).'" size="5"></td>';

			print '<td class="liste_titre">&nbsp;</td>';

			print '<td align="right" colspan="2" class="liste_titre">';
			print '<input type="image" class="liste_titre" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/search.png" name="button_search" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
		    print '&nbsp; ';
		    print '<input type="image" class="liste_titre" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/searchclear.png" name="button_removefilter" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
			print '</td>';

			print "</tr>";

			print '<tr class="liste_titre">';
		    print_liste_field_titre("ThirdPartyName",$_SERVER["PHP_SELF"],"p.socid",$param,"","",$sortfield,$sortorder);
		    print_liste_field_titre("Qty",$_SERVER["PHP_SELF"],"t.qty",$param,"","",$sortfield,$sortorder);
		    print_liste_field_titre("DateStart",$_SERVER["PHP_SELF"],"d.statut,d.datefin",$param,"","",$sortfield,$sortorder);
		    print_liste_field_titre("DateEnd",$_SERVER["PHP_SELF"],"d.datefin",$param,"",'align="center"',$sortfield,$sortorder);
		    print_liste_field_titre("Action",$_SERVER["PHP_SELF"],"",$param,"",'width="60" align="center"',$sortfield,$sortorder);
		    print "</tr>\n";

		    while ($i < $num && $i < $conf->liste_limit)
		    {
		        $objp = $db->fetch_object($resql);

		        $datefin=$db->jdate($objp->datefin);

$company_static = new Societe($db);
$company_static->fetch($objp->socid);

		        // Lastname
          print '<tr class="oddeven">';
          print '<td class="tdoverflowmax200">';
          print $company_static->getNomUrl(1);
          print "</td>";

		        // Qty
            if (!empty($objp->qty)) {
 		        print "<td>".$objp->qty."</td>";           
            } else {
		        print "<td>".$langs->trans("unlimited")."</td>";
            }

		        // Statut
		        print '<td class="nowrap">';
		        //print $adh->LibStatut($objp->statut,$objp->subscription,$datefin,2);
		        print "</td>";

		        // Date end subscription
		        if ($datefin)
		        {
			        print '<td align="center" class="nowrap">';
		            if ($datefin < dol_now() && $objp->statut > 0)
		            {
		                print dol_print_date($datefin,'day')." ".img_warning($langs->trans("SubscriptionLate"));
		            }
		            else
		            {
		                print dol_print_date($datefin,'day');
		            }
		            print '</td>';
		        }
		        else
		        {
			        print '<td align="left" class="nowrap">';
			        if ($objp->subscription == 'yes')
			        {
		                print $langs->trans("SubscriptionNotReceived");
		                if ($objp->statut > 0) print " ".img_warning();
			        }
			        else
			        {
			            print '&nbsp;';
			        }
		            print '</td>';
		        }

		        // Actions
		        print '<td align="center">';
				if ($user->rights->societe->creer)
        {
				print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&socid='.$objp->socid.'&action=edit">';
				print img_picto($langs->trans("Modify"), 'edit');
				print '</a>';

		   	print '&nbsp;';

		   	print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&socid='.$objp->socid.'&action=delete">';
		   	print img_picto($langs->trans("Delete"), 'delete');
		   	print '</a>';
		    }
				print "</td>";

		        print "</tr>\n";
		        $i++;
		    }

		    print "</table>\n";
            print '</div>';
            print '</form>';

			if ($num > $conf->liste_limit)
			{
			    print_barre_liste('',$page,$_SERVER["PHP_SELF"],$param,$sortfield,$sortorder,'',$num,$nbtotalofrecords,'');
			}
		}
		else
		{
		    dol_print_error($db);
		}  

// End of page
llxFooter();
$db->close();
