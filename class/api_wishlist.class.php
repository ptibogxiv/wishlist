<?php
/* Copyright (C) 2015   Jean-François Ferry     <jfefe@aternatik.fr>
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

use Luracast\Restler\RestException;


require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
dol_include_once('/wishlist/class/wishlist.class.php', 'Wishlist');

/**
 * API class for wishlist
 *
 * 
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class Wishlist extends DolibarrApi
{
    /**
     * @var array   $FIELDS     Mandatory fields, checked when create and update object 
     */
    static $FIELDS = array(
//         exemple

//         'objecttypes' => array(
//             'mandatoryFields' => array('id', 'entity')
//             ,'fieldTypes' => array(
//                 'id' => 'int'
//                 ,'entity' => 'int'
//                 ,'label' => 'string'
//                 ,'price' => 'float'
//             )
//          )

        // validate sessions
        'session' => array(
            'mandatoryFields' => array('fk_formation_catalogue', 'fk_session_place')
            ,'fieldTypes' => array()
        )

    );


    /**
     * @var Agsession $session {@type Session}
     */
	  public $company;
    public $product;
    public $wishlist;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $db, $conf;
        $this->db = $db;
    }
    
    /**
     * Get properties of an wish
     *
     * Return an array with wish informations
     *
     * @param  int    $id               ID of wish
     * @param  int    $includestockdata Load also information about stock (slower)
     * @return array|mixed                 Data without useless information
     *
     * @throws 401
     * @throws 403
     * @throws 404
     */
    public function get($id, $includestockdata = 0)
    {
        if(! DolibarrApiAccess::$user->rights->wishlist->read) {
            throw new RestException(401);
        }

        $wish = new Wish($this->db);
        $result = $wish->fetch($id);
        if( ! $result ) {
            throw new RestException(404, 'wish not found');
        }
        
        if( ! DolibarrApi::_checkAccessToResource('wishlist', $wish->id)) {
            throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
        }
        
                if ($includestockdata) {
               $this->product->load_stock();
        }

        return $this->_cleanObjectDatas($wish);
    }   
    
    /**
     * List wishlists
     *
     * Get a list of wishlists
     *

	 * @param string	$sortfield	        Sort field
	 * @param string	$sortorder	        Sort order
	 * @param int		$limit		        Limit for list
	 * @param int		$page		        Page number
	 * @param string   	$thirdparty_ids	Thirdparty ids to filter wishlists of.
	 * @param string    $sqlfilters         Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.datec:<:'20160101')" 
	 * @return array                Array of session objects
	 * 
	 *@throws RestException
	 */
    public function index($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $thirdparty_ids = '', $sqlfilters = '') {
        global $db, $conf;

        $obj_ret = array();

		// case of external user, $thirdparty_ids param is ignored and replaced by user's socid
		$socids = DolibarrApiAccess::$user->societe_id ? DolibarrApiAccess::$user->societe_id : $thirdparty_ids;

        $sql = "SELECT t.rowid,";
		if ((!DolibarrApiAccess::$user->rights->wishlist->read && !$socids) || $search_sale > 0) $sql .= ", sc.fk_soc, sc.fk_user"; // We need these fields in order to filter by sale (including the case where the user can only see his prospects)
        $sql.= " t.fk_product, t.qty, t.target";
        $sql.= " FROM ".MAIN_DB_PREFIX."wishlist as t";
        //if ($category > 0) {
            //$sql.= ", ".MAIN_DB_PREFIX."categorie_product as c";
        //}
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = t.fk_product";
        $sql.= ' WHERE p.entity IN ('.getEntity('product').')';
        if ($socids) $sql.= " AND t.fk_soc IN (".$socids.")";
        // Select products of given category
        //if ($category > 0) {
            //$sql.= " AND c.fk_categorie = ".$db->escape($category);
            //$sql.= " AND c.fk_product = t.rowid ";
        //}
        //if ($mode == 1) {
            // Show only products
            //$sql.= " AND t.fk_product_type = 0";
        //} elseif ($mode == 2) {
            // Show only services
            //$sql.= " AND t.fk_product_type = 1";
        //}
		// Add sql filters
		if ($sqlfilters)
		{
			if (! DolibarrApi::_checkFilters($sqlfilters))
			{
				throw new RestException(503, 'Error when validating parameter sqlfilters '.$sqlfilters);
			}
			$regexstring='\(([^:\'\(\)]+:[^:\'\(\)]+:[^:\(\)]+)\)';
			$sql.=" AND (".preg_replace_callback('/'.$regexstring.'/', 'DolibarrApi::_forge_criteria_callback', $sqlfilters).")";
		}

		$sql.= $db->order($sortfield, $sortorder);
		if ($limit)	{
			if ($page < 0)
			{
				$page = 0;
			}
			$offset = $limit * $page;

			$sql.= $db->plimit($limit + 1, $offset);
		}

        dol_syslog("API Rest request");
		$result = $db->query($sql);

		if ($result)
		{
			$num = $db->num_rows($result);
			$min = min($num, ($limit <= 0 ? $num : $limit));
			$i = 0;
			while ($i < $min)
			{
                $obj = $db->fetch_object($result);
                $wish_static = new Wish($db);
                if($wish_static->fetch($obj->rowid)) {
                    $obj_ret[] = $this->_cleanObjectDatas($wish_static);
                }
                $i++;
            }
        }
        else {
            throw new RestException(503, 'Error when retrieve wish list : '.$db->lasterror());
        }
        if(! count($obj_ret)) {
            throw new RestException(404, 'No wish found');
        }
        return $obj_ret;
    }
 
    /**
     * Create wish object
     *
     * @param array $request_data   Request data
     * @return int  ID of wish
     */
    public function post($request_data = null)
    {
        if(! DolibarrApiAccess::$user->rights->wishlist->create) {
            throw new RestException(401);
        }
        // Check mandatory fields
        $result = $this->_validate($request_data);

        $wish = new Wish($this->db);
        foreach($request_data as $field => $value) {
            $wish->$field = $value;
        }
        if ($wish->create(DolibarrApiAccess::$user) < 0) {
            throw new RestException(500, 'Error creating wish', array_merge(array($wish->error), $wish->errors));
        }
        return $wish->id;
    }

    /**
     * Update wish
     *
     * @param int   $id             ID of wish to update
     * @param array $request_data   Datas
     * @return int
     */
    public function put($id, $request_data = null)
    {
        if(! DolibarrApiAccess::$user->rights->wishlist->create) {
            throw new RestException(401);
        }

        $wish = new Wish($this->db);
        $result = $wish->fetch($id);
        if( ! $result ) {
            throw new RestException(404, 'wish not found');
        }

        if( ! DolibarrApi::_checkAccessToResource('wishlist', $wish->id)) {
            throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
        }

        foreach($request_data as $field => $value) {
            if ($field == 'id') continue;
                $member->$field = $value;
        }

        // If there is no error, update() returns the number of affected rows
        // so if the update is a no op, the return value is zero.
        if ($wish->update(DolibarrApiAccess::$user) >= 0)
        {
            return $this->get($id);
        }
        else
        {
        	throw new RestException(500, $wish->error);
        }
    }
    
    /**
     * Delete wish
     *
     * @param int $id   Wish ID
     * @return array
     */
    public function delete($id)
    {
        if(! DolibarrApiAccess::$user->rights->wishlist->delete) {
            throw new RestException(401);
        }

        $wish = new Wish($this->db);
        $result = $wish->fetch($id);
        if( ! $result ) {
            throw new RestException(404, 'wish not found');
            }
        
        if( ! DolibarrApi::_checkAccessToResource('wishlist', $wish->id)) {
            throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
        }

        if (! $wish->delete($wish->id, DolibarrApiAccess::$user)) {
            throw new RestException(401,'error when deleting wish');
        }

        return array(
            'success' => array(
                'code' => 200,
                'message' => 'wish deleted'
            )
        );
    }
     
    /**
     * Clean sensible object datas
     *
     * @param   Categorie  $object    Object to clean
     * @return    array    Array of cleaned object properties
     */
    function _cleanObjectDatas($object) {
    
        $object = parent::_cleanObjectDatas($object);
    
        // Remove fields not relevent to categories
        unset($object->country);
        unset($object->country_id);
        unset($object->country_code);
        unset($object->total_ht);
        unset($object->total_ht);
        unset($object->total_localtax1);
        unset($object->total_localtax2);
        unset($object->total_ttc);
        unset($object->total_tva);
        unset($object->lines);
        unset($object->fk_incoterms);
        unset($object->libelle_incoterms);
        unset($object->location_incoterms);
        unset($object->civility_id);
        //unset($object->name);
        //unset($object->lastname);
        //unset($object->firstname);
        unset($object->shipping_method_id);
        unset($object->fk_delivery_address);
        unset($object->cond_reglement);
        unset($object->cond_reglement_id);
        unset($object->mode_reglement_id);
        unset($object->barcode_type_coder);
        unset($object->barcode_type_label);
        unset($object->barcode_type_code);
        unset($object->barcode_type);
        unset($object->canvas);
        unset($object->cats);
        unset($object->motherof);
        unset($object->context);
        unset($object->socid);
        unset($object->thirdparty);
        unset($object->contact);
        unset($object->contact_id);
        unset($object->user);
        unset($object->fk_account);
        unset($object->fk_project);
        unset($object->note);
        unset($object->statut);
        unset($object->labelstatut);
        unset($object->labelstatut_short);
        
        return $object;
    }
    
    /**
     * Validate fields before create or update object
     *
     * @param  array $data Datas to validate
     * @return array
     * @throws RestException
     */
    private function _validate($data)
    {
        $product = array();
        foreach (Products::$FIELDS as $field) {
            if (!isset($data[$field])) {
                throw new RestException(400, "$field field missing");
            }
            $product[$field] = $data[$field];
        }
        return $product;
    }
}
