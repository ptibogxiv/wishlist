<?php
/* Copyright (C) 2019-2019	Thibauylt FOUCART	<support@ptibogxiv.net>
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 */

/**
 * \defgroup agefodd Module Wishlist
 * \brief agefodd module descriptor.
 * \file /core/modules/modAgefodd.class.php
 * \ingroup agefodd
 * \brief Description and activation file for module agefodd
 */
include_once DOL_DOCUMENT_ROOT . "/core/modules/DolibarrModules.class.php";

/**
 * \class modAgefodd
 * \brief Description and activation class for module agefodd
 */
class modWishlist extends DolibarrModules {
	var $error;
	/**
	 * Constructor.
	 *
	 * @param DoliDB		Database handler
	 */
	function __construct($db) {
		global $conf;
		
		$this->db = $db;
		
		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 431335;
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'wishlist';
		// Module description used if translation string 'ModuleXXXDesc' not found (XXX is id value)
    $this->editor_name = 'ptibogxiv.net';
    $this->editor_url = 'https://www.ptibogxiv.net';
		// Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
		// It is used to group modules in module setup page
		$this->family = "products";
    // Can be enabled / disabled only in the main company with superadmin account
		//$this->core_enabled = 1;
		// Module label, used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		// Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
		$this->description = "Module Wishlist";
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = '12.0.3';
		
		// Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
		// Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
		$this->special = 1;
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/images directory, use this->picto=DOL_URL_ROOT.'/module/images/file.png'
		$this->picto = 'wishlist@wishlist';
		
    
    // Dependencies
    $this->depends = array('modProduct','modService');		// List of modules id that must be enabled if this module is enabled
    $this->requiredby = array();	// List of modules id to disable if this one is disabled
    $this->conflictwith = array();
    $this->phpmin = array(7,0);					// Minimum version of PHP required by module
    $this->need_dolibarr_version = array(9,0);	// Minimum version of Dolibarr required by module
    $this->langfiles = array("wishlist@wishlist");

  
    // Config pages. Put here list of php page, stored into oblyon/admin directory, to use to setup module.
    //$this->config_page_url = array("stripeconnect.php@stripeconnect");
    
		// Defined all module parts (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = array();

        // Array to add new pages in new tabs
        $this->tabs = array(
            'thirdparty:+wishlist:Wishlist:wishlist@wishlist:$user->rights->wishlist->read:/wishlist/card.php?socid=__ID__',
            'product:+wishlist:Wishlist:wishlist@wishlist:$user->rights->wishlist->read:/wishlist/list.php?id=__ID__'
        );

        // Boxes
        //------
        $this->boxes = array();
        
                // Permissions
        $this->rights = array();        // Permission array used by this module
        $r = 0;
        $this->rights_class = 'wishlist';
        $r++;
        $this->rights[$r][0] = 431335;
        $this->rights[$r][1] = 'Lire les souhaits';
        $this->rights[$r][2] = 'a';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'read';

        $r++;
        $this->rights[$r][0] = 431336;
        $this->rights[$r][1] = 'Creer les souhaits';
        $this->rights[$r][2] = 'a';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'create';
        
        $r++;
        $this->rights[$r][0] = 431337;
        $this->rights[$r][1] = 'Supprimer les souhaits';
        $this->rights[$r][2] = 'a';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'delete';

		// Main menu entries
		$this->menus = array();			// List of menus to add
		$r=0;
  }

	/**
	 *		Function called when module is enabled.
	 *		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *		It also creates data directories
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
    function init($options='')
    {
        global $conf;

        // Permissions
        $this->remove($options);

        $sql = array();
        $result=$this->load_tables();
        if ($result != 1)
            var_dump($this);

        return $this->_init($sql, $options);
    }

	/**
	 * Function called when module is disabled.
	 * Remove from database constants, boxes and permissions from Dolibarr database.
	 * Data directories are not deleted
	 *
	 * @param      string	$options    Options when enabling module ('', 'noboxes')
	 * @return     int             	1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}

    /**
     *		Create tables, keys and data required by module
     * 		Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys
     * 		and create data commands must be stored in directory /mymodule/sql/
     *		This function is called by this->init.
     *
     * 		@return		int		<=0 if KO, >0 if OK
     */
    public function load_tables()
    {
        return $this->_load_tables('/wishlist/sql/');
    }

}
?>