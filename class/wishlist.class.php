<?php
/* Copyright (C) 2019 Thibault FOUCART           <support@ptibogxiv.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU  *General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

/**
 *	\class      Wishlist
 *	\brief      Class for Wishlist
 */
class Wish extends CommonObject
{
	public $fk_product;
  public $wish;
  public $ref;
  public $label;
  public $fk_type;
  public $socid;
  public $qty;
  public $target;
  
  public $date_creation;
  public $date_modification;
  
	public $priv;

  public $user_author_id;
  public $user_modification;
	
	/**
	 * 	Constructor
	 *
	 * 	@param	DoliDB		$db			Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}
	
	/**
	 * 
	 * @param 	Facture 	$facture	Invoice object
	 * @param 	double 		$points		Points to add/remove
	 * @param 	string 		$typemov	Type of movement (increase to add, decrease to remove)
	 * @return int			<0 if KO, >0 if OK
	 */
    public function create($user, $notrigger = 0)
	{
		global $conf,$user;
    $error = 0;
    
		$now=dol_now();
    
    if (! $this->datec) $this->datec=$now;
        
		$this->db->begin();
		
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."wishlist";
		$sql.= " (datec, fk_user_author, fk_user_mod, fk_product, fk_soc, qty, target, rang, priv, entity)";
		$sql.= " VALUES (";
    $sql.= " '".$this->db->idate($this->datec)."'";
		$sql.= ", ".($user->id>0?$user->id:"null");	// Can be null because member can be created by a guest or a script
		$sql.= ", null";    
		$sql.= ", '".$this->db->escape($this->fk_product)."'";
		$sql.= ", '".$this->db->escape($this->fk_soc)."'";
    $sql.= ", '".$this->db->escape($this->qty)."'";
    $sql.= ", '".(! empty($this->target) ? $this->db->escape($this->target) : "0")."'";
    $sql.= ", '".(! empty($this->rang) ? $this->db->escape($this->rang) : "0")."'";
    $sql.= ", '".$this->db->escape($this->priv)."'";
		$sql.= ", ".$conf->entity;
		$sql.= ")";
		
		dol_syslog(get_class($this)."::create::insert sql=".$sql, LOG_DEBUG);
		if (! $this->db->query($sql) )
		{
			dol_syslog(get_class($this)."::create::insert error", LOG_ERR);
			$error++;
		}
		
		if (! $error)
		{
				if (! $notrigger)
				{
					// Call trigger
					$result=$this->call_trigger('WISH_CREATE', $user);
					if ($result < 0) { $error++; }
					// End call triggers
				}
    
			dol_syslog(get_class($this)."::create by $user->id", LOG_DEBUG);
			$this->db->commit();
			return 1;
		}
		else
		{
			$this->error=$this->db->lasterror();
			dol_syslog(get_class($this)."::create ".$this->error, LOG_ERR);
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *    Load wish from database
	 *
	 *	@param	int		$rowid      			Id of object to load
	 * 	@param	string	$fk_product					To load wish from its products
	 * 	@param	int		$fk_soc					To load member from its link to third party
	 *  @return   int		            <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($rowid, $fk_product = '', $fk_soc = '')
	{
		$sql = 'SELECT t.rowid as id, t.datec, t.tms as datem, t.fk_user_author, t.fk_user_mod, t.fk_soc, t.fk_product as product, t.qty as qty, t.target as target, t.rang as rang, t.priv';
    $sql.= ', p.label, p.ref as ref, p.fk_product_type as type';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'wishlist as t LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON p.rowid = t.fk_product';
		$sql.= ' WHERE t.entity IN (' . getEntity('product').')';
    if ($ref && $fk_soc) {
		$sql.= " AND t.fk_product='".$fk_product."' AND t.fk_soc=".$fk_soc;
		} elseif ($rowid) {
    $sql.= " AND t.rowid=".$rowid;
    }  

		dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql)
		{
			if ($this->db->num_rows($resql))
			{
				$obj = $this->db->fetch_object($resql);
        $this->id             = $obj->id;
        $this->socid          = $obj->fk_soc;
        $this->fk_product     = $obj->product;
        $this->ref            = $obj->ref;
        $this->label          = $obj->label;
        $this->fk_type        = $obj->type;
        $this->qty            = $obj->qty;
        $this->target         = $obj->target;
        $this->rang           = $obj->rang;
        $this->priv           = $obj->priv;
        $this->date_creation  = $this->db->jdate($obj->datec);
        $this->date_modification = $this->db->jdate($obj->datem);
        $this->user_author_id    = $obj->fk_user_author;
        $this->user_modification = $obj->fk_user_mod;

				$this->db->free($resql);
				return 1;
			}
			else
			{
				$this->db->free($resql);
				return 0;
			}
		}
		else
		{
			dol_print_error($this->db);
			return -1;
		}
	}
  
	/**
	 *  Fonction qui supprime le souhait
	 *
	 *  @param	int		$rowid		Id of member to delete
	 *	@param	User		$user		User object
	 *	@param	int		$notrigger	1=Does not execute triggers, 0= execute triggers
	 *  @return	int					<0 if KO, 0=nothing to do, >0 if OK
	 */
	public function delete($rowid, $user, $notrigger = 0)
	{
		global $conf, $langs;

		$result = 0;
		$error=0;
		$errorflag=0;

		// Check parameters
		if (empty($rowid)) $rowid=$this->id;

		$this->db->begin();

		// Remove wish
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."wishlist WHERE rowid = ".$rowid;
			dol_syslog(get_class($this)."::delete", LOG_DEBUG);
			$resql=$this->db->query($sql);
			if (! $resql)
			{
				$error++;
				$this->error .= $this->db->lasterror();
				$errorflag=-5;
			}

		if (! $error)
		{
			$this->db->commit();
			return 1;
		}
		else
		{
			$this->db->rollback();
			return $errorflag;
		}
	}
  
    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *	Return translated label of Public or Private
	 *
	 * 	@param      int			$statut		Type (0 = public, 1 = private)
	 *  @return     string					Label translated
	 */
	public function LibPubPriv($statut)
	{
        // phpcs:enable
		global $langs;
		if ($statut=='1') return $langs->trans('ContactPrivate');
		else return $langs->trans('ContactPublic');
	}

  
}