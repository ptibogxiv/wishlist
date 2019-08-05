<?php
/* Copyright (C) 2013	Juanjo Menent  <jmenent@2byte.es>
 * Copyright (C) 2014	Ferran Marcet  <fmarcet@2byte.es>
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
 * or see http://www.gnu.org/
 */

/**
 *	    \file       rewards/lib/rewards.lib.php
 *		\brief      Rewards functions
 * 		\ingroup	Rewards
 *
 */


 /**
 * Return array of tabs to used on page
 *
 * @param	Object	$object		Object for tabs
 * @return	array				Array of tabs
 */
function wishlist_prepare_head(Adherent $object)
{
	global $db, $langs, $conf, $user;

	$h = 0;
	$head = array();

	$head[$h][0] = DOL_URL_ROOT.'/adherents/card.php?rowid='.$object->id;
	$head[$h][1] = $langs->trans("Card");
	$head[$h][2] = 'general';
	$h++;

	if (! empty($conf->ldap->enabled) && ! empty($conf->global->LDAP_MEMBER_ACTIVE))
	{
		$langs->load("ldap");

		$head[$h][0] = DOL_URL_ROOT.'/adherents/ldap.php?id='.$object->id;
		$head[$h][1] = $langs->trans("LDAPCard");
		$head[$h][2] = 'ldap';
		$h++;
	}

	if (! empty($user->rights->adherent->cotisation->lire))
	{
		$nbSubscription = is_array($object->subscriptions)?count($object->subscriptions):0;
		$head[$h][0] = DOL_URL_ROOT.'/adherents/subscription.php?rowid='.$object->id;
		$head[$h][1] = $langs->trans("Subscriptions");
		$head[$h][2] = 'subscription';
		if ($nbSubscription > 0) $head[$h][1].= ' <span class="badge">'.$nbSubscription.'</span>';
		$h++;
	}

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to remove a tab
    complete_head_from_modules($conf,$langs,$object,$head,$h,'member');

    $nbNote = 0;
    if(!empty($object->note)) $nbNote++;
    if(!empty($object->note_private)) $nbNote++;
    if(!empty($object->note_public)) $nbNote++;
    $head[$h][0] = DOL_URL_ROOT.'/adherents/note.php?id='.$object->id;
	$head[$h][1] = $langs->trans("Note");
	$head[$h][2] = 'note';
    if ($nbNote > 0) $head[$h][1].= ' <span class="badge">'.$nbNote.'</span>';
	$h++;

    // Attachments
    require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
    require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
    $upload_dir = $conf->adherent->multidir_output[$object->entity].'/'.get_exdir(0,0,0,1,$object,'member');
    $nbFiles = count(dol_dir_list($upload_dir,'files',0,'','(\.meta|_preview\.png)$'));
    $nbLinks=Link::count($db, $object->element, $object->id);
    $head[$h][0] = DOL_URL_ROOT.'/adherents/document.php?id='.$object->id;
    $head[$h][1] = $langs->trans('Documents');
    if (($nbFiles+$nbLinks) > 0) $head[$h][1].= ' <span class="badge">'.($nbFiles+$nbLinks).'</span>';
    $head[$h][2] = 'document';
    $h++;

	// Show agenda tab
	if (! empty($conf->agenda->enabled))
	{
	    $head[$h][0] = DOL_URL_ROOT."/adherents/agenda.php?id=".$object->id;
	    $head[$h][1] = $langs->trans("Events");
	    if (! empty($conf->agenda->enabled) && (!empty($user->rights->agenda->myactions->read) || !empty($user->rights->agenda->allactions->read) ))
	    {
	        $head[$h][1].= '/';
	        $head[$h][1].= $langs->trans("Agenda");
	    }
	    $head[$h][2] = 'agenda';
	    $h++;
	}
	
	complete_head_from_modules($conf,$langs,$object,$head,$h,'member','remove');

	return $head;
}
