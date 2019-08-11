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
	public $id;
  public $product;
  public $ref;
  public $label;
  public $product_type;
  public $socid;
  public $qty;
  public $target;
	
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
    public function create($facture, $points,$typemov='increase')
	{
		global $conf,$user;
        $error = 0;
		
		if($typemov==='decrease') $points=$points*-1;
		
		$this->db->begin();
		
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."rewards (";
		$sql.= "fk_soc, fk_invoice, fk_actioncomm, points, entity, fk_user_author, date";
		$sql.= ")";
		$sql.= " VALUES (".$facture->socid;
		$sql.= ", ".($facture->id?$facture->id:'NULL');
    $sql.= ", ".($facture->fk_facture_source?$facture->fk_facture_source:'NULL');
		$sql.= ", ".floor($points*100)/100;
		$sql.= ", ".$conf->entity;
		$sql.= ", ".$user->id;
		$sql.= ", '".$this->db->idate(dol_now());
		$sql.= "')";
		
		dol_syslog(get_class($this)."::create::insert sql=".$sql, LOG_DEBUG);
		if (! $this->db->query($sql) )
		{
			dol_syslog(get_class($this)."::create::insert error", LOG_ERR);
			$error++;
		}
		
		if (! $error)
		{
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
	 *    @param	int		$id			Id of wish to get
	 *    @return   int		            <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id)
	{
		$sql = 'SELECT t.rowid, t.fk_soc, t.fk_product as product, t.qty as qty, t.target as target';
    $sql.= ', p.label, p.ref as ref, p.fk_product_type as type';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'wishlist as t LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON p.rowid = t.fk_product';
		$sql.= ' WHERE t.entity IN (' . getEntity('product').')';
		$sql.= ' AND t.rowid = '.$id;    

		$resql = $this->db->query($sql);
		if ($resql)
		{
			if ($this->db->num_rows($resql))
			{
				$obj = $this->db->fetch_object($resql);
        $this->id             = $obj->rowid;
        $this->socid          = $obj->fk_soc;
        $this->product        = $obj->product;
        $this->ref            = $obj->ref;
        $this->label          = $obj->label;
        $this->product_type   = $obj->type;
        $this->qty            = $obj->qty;
        $this->target         = $obj->target;

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
  
}