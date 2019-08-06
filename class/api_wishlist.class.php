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
     * 
     */
    function __construct()
    {
		global $db, $conf;
		$this->db = $db;

		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
    require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
    //dol_include_once('/wishlist/class/wishlist.class.php');

		$this->company = new Societe($this->db);
    $this->product = new Product($this->db);
    //$this->wishlist = new Wishlist($this->db);
    }
    
    /**
     * List wishlist
     *
     * Get a list of wishlists
     *
     * @param int	$thirdparty	Thirdparty
     * @param string	$sortfield	Sort field
     * @param string	$sortorder	Sort order
     * @param int		$limit		Limit for list
     * @param int		$page		Page number
     * @return array                Array of session objects
     *
     * @throws RestException
     */
    function get($thirdparty = null, $sortfield = "", $sortorder = 'DESC', $limit = 100, $page = 0) {
        global $db, $conf;

        $obj_ret = array();

        $socid = DolibarrApiAccess::$user->societe_id ? DolibarrApiAccess::$user->societe_id : '';

        $sql = "SELECT t.rowid, t.fk_product, t.qty, t.target";
        $sql.= " FROM ".MAIN_DB_PREFIX."wishlist as t";
        if ($category > 0) {
            $sql.= ", ".MAIN_DB_PREFIX."categorie_product as c";
        }
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = t.fk_product";
        $sql.= ' WHERE t.entity IN ('.getEntity('societe').')';
        // Select products of given category
        if ($category > 0) {
            $sql.= " AND c.fk_categorie = ".$db->escape($category);
            $sql.= " AND c.fk_product = t.rowid ";
        }
        if ($mode == 1) {
            // Show only products
            $sql.= " AND t.fk_product_type = 0";
        } elseif ($mode == 2) {
            // Show only services
            $sql.= " AND t.fk_product_type = 1";
        }
        // Add sql filters
        if ($sqlfilters) {
            if (! DolibarrApi::_checkFilters($sqlfilters)) {
                throw new RestException(503, 'Error when validating parameter sqlfilters '.$sqlfilters);
            }
            $regexstring='\(([^:\'\(\)]+:[^:\'\(\)]+:[^:\(\)]+)\)';
            $sql.=" AND (".preg_replace_callback('/'.$regexstring.'/', 'DolibarrApi::_forge_criteria_callback', $sqlfilters).")";
        }

        $sql.= $db->order($sortfield, $sortorder);
        if ($limit) {
            if ($page < 0) {
                $page = 0;
            }
            $offset = $limit * $page;

            $sql.= $db->plimit($limit + 1, $offset);
        }

        $result = $db->query($sql);
        if ($result) {
            $num = $db->num_rows($result);
            $min = min($num, ($limit <= 0 ? $num : $limit));
            $i = 0;
            while ($i < $min)
            {
                $obj = $db->fetch_object($result);
                $product_static = new Product($db);
                if($product_static->fetch($obj->fk_product)) {
                    $obj_ret[] = $this->_cleanObjectDatas($product_static);
                }
                $i++;
            }
        }
        else {
            throw new RestException(503, 'Error when retrieve wish list : '.$db->lasterror());
        }
        if(! count($obj_ret)) {
            throw new RestException(404, 'No product found');
        }
        return $obj_ret;
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
