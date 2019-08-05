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
class Wishlist extends CommonObject
{
	public $rowid;
    public $fk_soc;
    public $fk_invoice;
    public $points;
    public $fk_user_author;
	
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
     * Set Customer Rewards
     *
     * @param int $status Customer status to set (0=Exclude, 1=Include)
     * @param int $socid Customer id
     * @return int
     */
    public function setCustomerReward($status,$socid)
	{
		global $conf;
		
		$error=0;
		$alreadyexists=false;
		
		$sql = "SELECT fk_soc";
		$sql.=" FROM ".MAIN_DB_PREFIX."rewards_soc";	
		$sql.=" WHERE fk_soc=".$socid;
		$sql.=" AND entity=".$conf->entity;
			
		$result = $this->db->query($sql);
		if ($result)
		{
			$num = $this->db->num_rows($result);
		}
		
		if ($num)
		{
			$alreadyexists=true;
		}
				
		if($status=='no')
		{
			if (! $alreadyexists) return 1;
			else
			{
				$this->db->begin();
				
				$sql = 'DELETE FROM '.MAIN_DB_PREFIX."rewards_soc WHERE fk_soc = ".$socid;
				$sql.= ' AND entity='.$conf->entity;
				
				dol_syslog(get_class($this)."::setCustomerReward::delete sql=".$sql, LOG_DEBUG);
				if (! $this->db->query($sql) )
				{
					dol_syslog(get_class($this)."::setCustomerReward::delete error", LOG_ERR);
					$error++;
				}
				
			}
		}
		elseif($status==='yes')
		{
			if ($alreadyexists) return 1;
			else
			{
				$this->db->begin();
				
				$sql = "INSERT INTO ".MAIN_DB_PREFIX."rewards_soc (";
				$sql.= "fk_soc, entity";
				$sql.= ")";
				$sql.= " VALUES (".$socid; 				
				$sql.= ", ".$conf->entity;
				$sql.= ")";
				
				dol_syslog(get_class($this)."::setCustomerReward::insert sql=".$sql, LOG_DEBUG);
				if (! $this->db->query($sql) )
				{
					dol_syslog(get_class($this)."::setCustomerReward::insert error", LOG_ERR);
					$error++;
				}
			
			}
		}
		
		if (! $error)
		{
			dol_syslog(get_class($this)."::setCustomerReward $socid by $user->id", LOG_DEBUG);
			$this->db->commit();
			return 1;
		}
		else
		{
			$this->error=$this->db->lasterror();
			dol_syslog(get_class($this)."::setCustomerReward ".$this->error, LOG_ERR);
			$this->db->rollback();
			return -1;
		}
	}

    public function getCustomerPoints($socid = null)
	{
		global $conf;
		$sql = "SELECT sum(r.points) as points";
    
    if (! empty($conf->global->REWARDS_VALIDITY)) {
    $sql.= " , (SELECT sum(c.points) as controle FROM ".MAIN_DB_PREFIX."rewards as c ";
    if (empty($socid)) {
    $sql.= " JOIN ".MAIN_DB_PREFIX."rewards_soc as s on c.fk_soc=s.fk_soc";
    }
    $sql.= " WHERE c.entity=".$conf->entity;
    if (!empty($socid)) {		
    $sql.= " AND c.fk_soc=".$socid;
    }
    $sql.= " AND c.date > '".strftime("%Y-%m-%d",dol_time_plus_duree(dol_now(), -$conf->global->REWARDS_VALIDITY, 'm'))."')";   
    }
    
		$sql.= " FROM ".MAIN_DB_PREFIX."rewards as r";
    if (empty($socid)) {
    $sql.= " JOIN ".MAIN_DB_PREFIX."rewards_soc as s on r.fk_soc=s.fk_soc";
    }
		$sql.= " WHERE r.entity=".$conf->entity;
    if (!empty($socid)) {		
    $sql.= " AND r.fk_soc=".$socid;
    }

		$result = $this->db->query($sql);
		if ($result)
		{
			$num = $this->db->num_rows($result);
			if ($num)
			{
				$objp = $this->db->fetch_object($result);
				$total = $objp->controle.' '.price2num($objp->points,'MT');
				return $total;
			}
		}
		return 0;
	}
  
      public function convertCustomerPoints($points = null)
	{
		global $conf;

			if ( ! empty($points))
			{
				$total = price2num($points,'MT')*$conf->global->REWARDS_DISCOUNT;
				return $total;
			}
      
		return 0;
	}

    public function getInvoicePoints($facid,$iscredit=0)
	{
		global $conf;
		$sql = "SELECT sum(points) as points";
		$sql.=" FROM ".MAIN_DB_PREFIX."rewards";
		$sql.=" WHERE fk_invoice=".$facid;
		if($iscredit)
			$sql.=" AND points<0";
		else 
			$sql.=" AND points>0";
		$sql.=" AND entity=".$conf->entity;
	
		$result = $this->db->query($sql);
		if ($result)
		{
			$num = $this->db->num_rows($result);
			if ($num)
			{
				$objp = $this->db->fetch_object($result);
				$total = price2num($objp->points,'MT');
				return $total;
			}
		}
		return 0;
	}
	
	/**
	 * Get Customer rewards status
	 * 
	 * @param 	int 	$socid	Customer to get reward status
	 * @return int				>0 If Ok <=0 if KO
	 */
    public function getCustomerReward($socid)
	{
		global $conf;
		
		$sql = "SELECT fk_soc";
		$sql.=" FROM ".MAIN_DB_PREFIX."rewards_soc";	
		$sql.=" WHERE fk_soc=".$socid;
		$sql.=" AND entity=".$conf->entity;
			
		$result = $this->db->query($sql);
		if ($result)
		{
			$num = $this->db->num_rows($result);
		
			if ($num)
			{
				return 1;
			}
			else
			{
				return 0;
			}
		}
		else
		{
            dol_syslog(get_class($this).'::getCustomerReward Error socid='.$socid, LOG_ERR);
            $this->error=$this->db->error();
            return -1;
        }
		
	}

    public function usePoints($facture,$points)
	{
		global $conf, $langs, $user;
		$langs->load("rewards@rewards");
		$amounts=array();
		
		$money= $points*$conf->global->REWARDS_DISCOUNT;
		
		$desc=$langs->trans("RewardsDiscountDesc",$points);
		
		//$result = $facture->addline($facture->id,$desc,$money,-1,$vat);
		
		$amounts[$facture->id] = price2num($money);
		
		$datepaye=dol_now();
		
		$paiement = new Paiement($this->db);
		$paiement->datepaye     = $datepaye;
		$paiement->amounts      = $amounts;   // Array with all payments dispatching
		$paiement->paiementid   = dol_getIdFromCode($this->db,'PNT','c_paiement','code','id',1);
		//$paiement->num_paiement = $_POST['num_paiement'];
		$paiement->note_public         = $desc;
		
		$result=$paiement->create($user, 1);
			
		if ($result > 0)
		{
			$result = $this->create($facture, $points, 'decrease');
			if ($result > 0)
			{
				return 1;
			}
			else 
			{
				setEventMessage($this->error, 'errors');
				return -1;
			}
		}
		else 
		{

			setEventMessage($paiement->error, 'errors');
			return -1;
		}
		
		
	}
}