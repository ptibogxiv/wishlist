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
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

$langs->loadLangs(array("companies", "products", "orders"));

// Security check
$socid = GETPOST("socid", "int");
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'societe', '', '');

$rowid  = GETPOST('rowid','int');
$action = GETPOST('action','alpha');
$cancel = GETPOST('cancel','alpha');

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
$lineid=GETPOST("lineid","int");
$target=GETPOST("target","int");

if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'societe', $socid, '&societe');

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('wishlistthirdparty'));

$object = new Societe($db);
$result = $object->fetch($socid);
  
/*
 *	Actions
 */

if ($cancel)
{
	$action='';
}

$parameters=array('id'=>$socid, 'objcanvas'=>$objcanvas);
$reshook=$hookmanager->executeHooks('doActions', $parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
	if ($cancel)
	{
		$action='';
		if (! empty($backtopage))
		{
			header("Location: ".$backtopage);
			exit;
		}
	}
  
	if ($action == 'add')
	{
		$error=0;

		if (! GETPOST('productid', 'alpha') || ! GETPOST('qty', 'int'))
		{
			if (! GETPOST('productid', 'alpha')) setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("ProductOrService")), null, 'errors');
			if (! GETPOST('qty', 'int')) setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Qty")), null, 'errors');
			$action='create';
			$error++;
		}

		if (! $error)
		{
		$db->begin();

		// Insert member
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."wishlist";
		$sql.= " (fk_product, fk_soc, qty, target, entity)";
		$sql.= " VALUES (";
		$sql.= "'".$db->escape(GETPOST('productid', 'alpha'))."'";
		$sql.= ", '".$db->escape($socid)."'";
    $sql.= ", '".$db->escape(GETPOST('qty', 'int'))."'";
    $sql.= ", '".$db->escape(GETPOST('target', 'int'))."'";
		$sql.= ", ".$conf->entity;
		$sql.= ")";

		//dol_syslog(get_class($this)."::create", LOG_DEBUG);
		$result = $db->query($sql);

			if (! $error)
			{
				//$result = $companypaymentmode->create($user);
				if ($result < 0)
				{
					$error++;
					//setEventMessages($companypaymentmode->error, $companypaymentmode->errors, 'errors');
					$action='create';     // Force chargement page création
				}
			}

			if (! $error)
			{
				$db->commit();

				$url=$_SERVER["PHP_SELF"].'?socid='.$object->id;
				header('Location: '.$url);
				exit;
			}
			else
			{
				$db->rollback();
			}
		}
	}
  
	if ($action == 'confirm_delete' && GETPOST('confirm', 'alpha') == 'yes')
	{
$db->begin();
$sql  = "DELETE FROM ".MAIN_DB_PREFIX."wishlist WHERE rowid = ".$lineid." AND fk_soc = ".$socid." ";
$resql = $db->query($sql);
$db->commit();
	}
}

/*
 *	View
 */

$form = new Form($db);

if ($socid > 0 && empty($object->id))
{
    $result=$object->fetch($socid);
	if ($result <= 0) dol_print_error('',$object->error);
}


$title=$langs->trans("Wishlist");
if (! empty($conf->global->MAIN_HTML_TITLE) && preg_match('/thirdpartynameonly/',$conf->global->MAIN_HTML_TITLE) && $object->name) $title=$object->name." - ".$langs->trans('Card');
$help_url='EN:Module_Third_Parties|FR:Module_Tiers|ES:Empresas';
llxHeader('',$title,$help_url);

$head = societe_prepare_head($object);

if ($socid && $action == 'create' && $user->rights->societe->creer)
{
	print '<form action="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'" method="post">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	$actionforadd='add';
	print '<input type="hidden" name="action" value="'.$actionforadd.'">';
}

// View
if ($socid && $action !='create' && $action !='edit')
{

dol_fiche_head($head, 'wishlist', $langs->trans("ThirdParty"), 0, 'company');

$linkback = '<a href="'.DOL_URL_ROOT.'/societe/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';

dol_banner_tab($object, 'socid', $linkback, ($user->societe_id?0:1), 'rowid', 'nom', '', '', 0, '', '', 'arearefnobottom');

dol_fiche_end();

	// Confirm delete ban
	if ($action == 'delete')
	{
		print $form->formconfirm($_SERVER["PHP_SELF"]."?socid=".$object->id."&lineid=".($lineid), $langs->trans("DeleteAProduct"), $langs->trans("ConfirmDeleteProduct", ''), "confirm_delete", '', 0, 1);
	}

$year_current = strftime("%Y", dol_now());
$month_current = strftime("%m", dol_now());
$year_start = $year_current;

// We define date_start and date_end
$year_end=$year_start + 1;
$month_start=$conf->global->SOCIETE_FISCAL_MONTH_START?($conf->global->SOCIETE_FISCAL_MONTH_START):1;
if ($month_start > $month_current)
{
$year_start--;
$year_end--;
}
$month_end=$month_start-1;
if ($month_end < 1) $month_end=12;
$date_start = dol_print_date(dol_get_first_day($year_start, $month_start, false), '%Y-%m-%d'); 
//$date_end=dol_get_last_day($year_end, $month_end, false);
  
		$sql = "SELECT t.rowid, t.fk_product as product, t.qty as qty, t.target as target";
    $sql.= ", p.label, p.ref as ref";
    $sql.= ", (SELECT c.rowid FROM ".MAIN_DB_PREFIX."commandedet AS d LEFT JOIN ".MAIN_DB_PREFIX."commande AS c ON c.rowid=d.fk_commande WHERE d.fk_product = t.fk_product AND c.fk_soc = ".$socid." ORDER BY c.date_commande DESC LIMIT 1) as orderid";
    $sql.= ", (SELECT c.date_commande FROM ".MAIN_DB_PREFIX."commandedet AS d LEFT JOIN ".MAIN_DB_PREFIX."commande AS c ON c.rowid=d.fk_commande WHERE d.fk_product = t.fk_product AND c.fk_soc = ".$socid." ORDER BY c.date_commande DESC LIMIT 1) as date_commande";    
    $sql.= ", (SELECT d.qty FROM ".MAIN_DB_PREFIX."commandedet AS d LEFT JOIN ".MAIN_DB_PREFIX."commande AS c ON c.rowid=d.fk_commande WHERE d.fk_product = t.fk_product AND c.fk_soc = ".$socid." ORDER BY c.date_commande DESC LIMIT 1) as lastqty";
    $sql.= ", (SELECT sum(d.qty) FROM ".MAIN_DB_PREFIX."commandedet AS d LEFT JOIN ".MAIN_DB_PREFIX."commande AS c ON c.rowid=d.fk_commande WHERE d.fk_product = t.fk_product AND c.fk_soc = ".$socid." AND date_commande >= '".$date_start."' ORDER BY c.date_commande DESC) as totalqty";
    $sql.= " FROM ".MAIN_DB_PREFIX."wishlist as t";
    $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = t.fk_product";
		$sql.= " WHERE t.entity IN (".getEntity('societe').") ";
		$sql.= " AND t.fk_soc = ".$socid;
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
		if (! empty($search_ref))
		{
			$sql.= natural_search("p.ref", $search_ref);
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

		    $param="&socid=".$socid;
		    if (! empty($status))			$param.="&status=".$status;
		    if (! empty($search_ref))	$param.="&search_ref=".$search_ref;
		    if (! empty($search_label))		$param.="&search_label=".$search_label;
		    if (! empty($filter))			$param.="&filter=".$filter;

			print '<input class="flat" type="hidden" name="socid" value="'.$socid.'" size="12">';
      
      print_barre_liste('',$page,$_SERVER["PHP_SELF"],$param,$sortfield,$sortorder,'',$num,$nbtotalofrecords);

      if ((float) DOL_VERSION < 10) {
	$morehtmlright='<a class="butActionNew" href="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&action=create">'.$langs->trans("Add").' <span class="fa fa-plus-circle valignmiddle"></span></a>';
      } else {
  $morehtmlright= dolGetButtonTitle($langs->trans('Add'), '', 'fa fa-plus-circle', $_SERVER["PHP_SELF"].'?socid='.$object->id.'&action=create');
      }

      print load_fiche_titre($langs->trans("ListOfProductsServices"), $morehtmlright, '');

      print '<div class="div-table-responsive">';
      print '<table class="tagtable liste'.($moreforfilter?" listwithfilterbefore":"").'">'."\n";

			// Lignes des champs de filtre
			print '<tr class="liste_titre_filter">';

			print '<td class="liste_titre" align="left">';
			print '<input class="flat" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" size="7"></td>';

			print '<td class="liste_titre" align="left">';
			print '<input class="flat" type="text" name="search_label" value="'.dol_escape_htmltag($search_label).'" size="12"></td>';

			print '<td class="liste_titre" align="left">';
			print '<input class="flat" type="text" name="search_qty" value="'.dol_escape_htmltag($search_qty).'" size="5"></td>';
      
      print '<td align="center" class="liste_titre"></td>';
			print '<td align="center" class="liste_titre" colspan="3">'.$langs->trans("LastOrder").'</td>';

			print '<td align="right"  class="liste_titre">';
			print '<input type="image" class="liste_titre" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/search.png" name="button_search" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
		  print '&nbsp; ';
		  print '<input type="image" class="liste_titre" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/searchclear.png" name="button_removefilter" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
			print '</td>';

			print "</tr>";

			print '<tr class="liste_titre">';
		    print_liste_field_titre( $langs->trans("Ref"),$_SERVER["PHP_SELF"],"p.ref",$param,"","",$sortfield,$sortorder);
		    print_liste_field_titre("Label",$_SERVER["PHP_SELF"],"p.label",$param,"","",$sortfield,$sortorder);
		    print_liste_field_titre("Wish",$_SERVER["PHP_SELF"],"t.qty",$param,"","",$sortfield,$sortorder);
        print_liste_field_titre("Target",$_SERVER["PHP_SELF"],"t.target",$param,"","",$sortfield,$sortorder);
		    print_liste_field_titre("Ref",$_SERVER["PHP_SELF"],"orderid",$param,"","",$sortfield,$sortorder);
        print_liste_field_titre("Qty",$_SERVER["PHP_SELF"],"lastqty",$param,"","",$sortfield,$sortorder);
        print_liste_field_titre("OrderDateShort",$_SERVER["PHP_SELF"],"date_commande",$param,"","",$sortfield,$sortorder);
		    print_liste_field_titre("Action",$_SERVER["PHP_SELF"],"",$param,"",'width="90" align="center"',$sortfield,$sortorder);
		    print "</tr>\n";

		    while ($i < $num && $i < $conf->liste_limit)
		    {
		    $objp = $db->fetch_object($resql);
        	        
        $datefin=$db->jdate($objp->datefin);

	      $product_static = new Product($db);
		    $product_static->id = $objp->product;
		    $product_static->ref = $objp->ref;
		        // Product/service
		  print '<tr class="oddeven">';
			print '<td class="tdoverflowmax200">';
			print $product_static->getNomUrl(1);
			print "</td>";

		        // Description
		        print '<td class="tdoverflowmax200">'.dol_trunc($objp->label, 80).'</td>';

		        // Qty
            $quantity= GETPOSTISSET('qty')?GETPOST('qty'):$objp->qty;
 		        print "<td><input type='text' name='qty' value='".$quantity."' size='5'></td>";
             
		        // Target
            $quantity= GETPOSTISSET('target')?GETPOST('target'):$objp->target;
 		        print "<td>".$objp->totalqty;
            print "/".$objp->target;
            print "</td>";                            

		        // Last order
            if (! empty($objp->orderid)) {
            $commandestatic = new Commande($db);
            $commandestatic->fetch($objp->orderid);            
            $commandestatic->id = $objp->orderid;
            $commandestatic->ref = $commandestatic->ref;
 		        print "<td>".$commandestatic->getNomUrl(1,'',200,0,'',0,1)."</td>";
            } else {
 		        print "<td></td>";            
            }
            
		        // Last order
            if (! empty($objp->orderid)) {
 		        print "<td>".$objp->lastqty."</td>";
            } else {
 		        print "<td></td>";            
            }            
            
            // Date order
 		        print "<td>".dol_print_date($db->jdate($objp->date_commande), 'day')."</td>";

		        // Actions
		        print '<td align="center">';
				if ($user->rights->societe->creer)
        {
				print '<a href="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&lineid='.$objp->rowid.'&action=edit">';
				print img_picto($langs->trans("Modify"), 'edit');
				print '</a>';

		   	print '&nbsp;';

		   	print '<a href="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&lineid='.$objp->rowid.'&action=delete">';
		   	print img_picto($langs->trans("Delete"), 'delete');
		   	print '</a>';
		    }
				print "</td>";

		        print "</tr>\n";
		        $i++;
		    }

		    print "</table>\n";
        print '</div>';

			if ($num > $conf->liste_limit)
			{
			    print_barre_liste('',$page,$_SERVER["PHP_SELF"],$param,$sortfield,$sortorder,'',$num,$nbtotalofrecords,'');
			}
		}
		else
		{
		    dol_print_error($db);
		}  
}

// Create Card
if ($socid && $action == 'create' && $user->rights->societe->creer)
{
	dol_fiche_head($head, 'wishlist', $langs->trans("ThirdParty"), 0, 'company');

	$linkback = '<a href="'.DOL_URL_ROOT.'/societe/list.php">'.$langs->trans("BackToList").'</a>';

	dol_banner_tab($object, 'socid', $linkback, ($user->societe_id?0:1), 'rowid', 'nom');

	print '<div class="nofichecenter">';

	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent">';

	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans("PredefinedProductsAndServicesToSell").'</td>';
	print '<td>';
  			if (! empty($conf->global->ENTREPOT_EXTRA_STATUS))
			{
				// hide products in closed warehouse, but show products for internal transfer
				$form->select_produits(GETPOST('productid', 'int'), 'productidd', $filtertype, $conf->product->limit_size, $object->price_level, 1, 2, '', 1, array(), $object->id, '1', 0, 'maxwidth300', 0, 'warehouseopen,warehouseinternal', GETPOST('combinations', 'array'));
			}
			else
			{
				$form->select_produits(GETPOST('productid', 'int'), 'productid', $filtertype, $conf->product->limit_size, $object->price_level, 1, 2, '', 1, array(), $object->id, '1', 0, 'maxwidth300', 0, '', GETPOST('combinations', 'array'));
			}
  print '</td></tr>';

	print '<tr><td class="fieldrequired">'.$langs->trans("Qty").'</td>';
	print '<td><input class="minwidth200" type="text" name="qty" value="'.GETPOST('qty', 'int').'"></td></tr>';

	print '<tr><td>'.$langs->trans("AnnualTarget").'</td>';
	print '<td><input class="minwidth200" type="text" name="target" value="'.GETPOST('target', 'int').'"></td></tr>';

	print '</table>';

	print '</div>';

	dol_fiche_end();

	dol_set_focus('#label');

	print '<div class="center">';
	print '<input class="button" value="'.$langs->trans("Add").'" type="submit">';
	print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	print '<input name="cancel" class="button" value="'.$langs->trans("Cancel").'" type="submit">';
	print '</div>';
}

// Create Card
if ($socid && $action == 'edit' && $user->rights->societe->creer)
{
	dol_fiche_head($head, 'wishlist', $langs->trans("ThirdParty"), 0, 'company');

	$linkback = '<a href="'.DOL_URL_ROOT.'/societe/list.php">'.$langs->trans("BackToList").'</a>';

	dol_banner_tab($object, 'socid', $linkback, ($user->societe_id?0:1), 'rowid', 'nom');

	print '<div class="nofichecenter">';

	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent">';

	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans("PredefinedProductsAndServicesToSell").'</td>';
	      $product_static = new Product($db);
		    $product_static->id = '226';
		    $product_static->ref = 'SV1902-0002';
			print '<td>';
			print $product_static->getNomUrl(1);
			print "</td>";
  print '</td></tr>';

	print '<tr><td class="fieldrequired">'.$langs->trans("Qty").'</td>';
	print '<td><input class="minwidth200" type="text" name="qty" value="'.GETPOST('qty', 'int').'"></td></tr>';

	print '<tr><td>'.$langs->trans("AnnualTarget").'</td>';
	print '<td><input class="minwidth200" type="text" name="target" value="'.GETPOST('target', 'int').'"></td></tr>';

	print '</table>';

	print '</div>';

	dol_fiche_end();

	dol_set_focus('#label');

	print '<div class="center">';
	print '<input class="button" value="'.$langs->trans("Add").'" type="submit">';
	print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	print '<input name="cancel" class="button" value="'.$langs->trans("Cancel").'" type="submit">';
	print '</div>';
}

print '</form>';

// End of page
llxFooter();
$db->close();
