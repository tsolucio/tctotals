<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once 'data/CRMEntity.php';
require_once 'data/Tracker.php';

class TCTotals extends CRMEntity {
	public $table_name = 'vtiger_tctotals';
	public $table_index= 'tctotalsid';
	public $column_fields = array();

	/** Indicator if this is a custom module or standard module */
	public $IsCustomModule = true;
	public $HasDirectImageField = false;
	public $moduleIcon = array('library' => 'standard', 'containerClass' => 'slds-icon_container slds-icon-standard-account', 'class' => 'slds-icon', 'icon'=>'timeslot');

	/**
	 * Mandatory table for supporting custom fields.
	 */
	public $customFieldTable = array('vtiger_tctotalscf', 'tctotalsid');

	/**
	 * Mandatory for Saving, Include tables related to this module.
	 */
	public $tab_name = array('vtiger_crmentity', 'vtiger_tctotals', 'vtiger_tctotalscf');

	/**
	 * Mandatory for Saving, Include tablename and tablekey columnname here.
	 */
	public $tab_name_index = array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_tctotals'   => 'tctotalsid',
		'vtiger_tctotalscf' => 'tctotalsid',
	);

	/**
	 * Mandatory for Listing (Related listview)
	 */
	public $list_fields = array(
		/* Format: Field Label => array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'TCTotalNo'=> array('tctotals' => 'tctotalno'),
		'Work Date'=> array('tctotals' => 'workdate'),
		'TotalTime'=> array('tctotals' => 'totaltime'),
		'TotalHours'=> array('tctotals' => 'totalhours'),
		'Assigned To' => array('crmentity' => 'smownerid')
	);
	public $list_fields_name = array(
		/* Format: Field Label => fieldname */
		'TCTotalNo'=> 'tctotalno',
		'Work Date'=> 'workdate',
		'TotalTime'=> 'totaltime',
		'TotalHours'=> 'totalhours',
		'Assigned To' => 'assigned_user_id'
	);

	// Make the field link to detail view from list view (Fieldname)
	public $list_link_field = 'tctotalno';

	// For Popup listview and UI type support
	public $search_fields = array(
		/* Format: Field Label => array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'TCTotalNo'=> array('tctotals' => 'tctotalno'),
		'Work Date'=> array('tctotals' => 'workdate'),
		'TotalTime'=> array('tctotals' => 'totaltime'),
		'TotalHours'=> array('tctotals' => 'totalhours'),
		'Assigned To' => array('crmentity' => 'smownerid')
	);
	public $search_fields_name = array(
		/* Format: Field Label => fieldname */
		'TCTotalNo'=> 'tctotalno',
		'Work Date'=> 'workdate',
		'TotalTime'=> 'totaltime',
		'TotalHours'=> 'totalhours',
		'Assigned To' => 'smownerid'
	);

	// For Popup window record selection
	public $popup_fields = array('tctotalno');

	// Placeholder for sort fields - All the fields will be initialized for Sorting through initSortFields
	public $sortby_fields = array();

	// For Alphabetical search
	public $def_basicsearch_col = 'tctotalno';

	// Column value to use on detail view record text display
	public $def_detailview_recname = 'tctotalno';

	// Required Information for enabling Import feature
	public $required_fields = array('workdate'=>1,'totaltime'=>1,'smownerid'=>1);

	// Callback function list during Importing
	public $special_functions = array('set_import_assigned_user');

	public $default_order_by = 'workdate';
	public $default_sort_order='DESC';
	// Used when enabling/disabling the mandatory fields for the module.
	// Refers to vtiger_field.fieldname values.
	public $mandatory_fields = array('createdtime', 'modifiedtime');

	public function save_module($module) {
		if ($this->HasDirectImageField) {
			$this->insertIntoAttachment($this->id, $module);
		}
	}

	public function getRelationQuery($module, $secmodule, $table_name, $column_name, $queryPlanner) {
		return 'inner join vtiger_timecontrol on (vtiger_timecontrol.datestart=vtiger_tctotals.workdate)'; // and smownerid=smownerid
	}

	public function get_tctotal_tcs($id, $cur_tab_id, $rel_tab_id, $actions = false) {
		global $currentModule, $singlepane_view, $adb;

		$related_module = vtlib_getModuleNameById($rel_tab_id);
		$other = CRMEntity::getInstance($related_module);

		$singular_modname = 'SINGLE_'.$related_module;
		$crmEntityTable = CRMEntity::getcrmEntityTableAlias('TCTotals');
		$tcdata=$adb->pquery(
			'select vtiger_crmentity.smownerid,workdate from vtiger_tctotals inner join '.$crmEntityTable.' on vtiger_crmentity.crmid=tctotalsid where tctotalsid=?',
			array($id)
		);
		$workdate=$adb->query_result($tcdata, 0, 'workdate');
		$tcuser=$adb->query_result($tcdata, 0, 'smownerid');

		$button = '';

		// To make the edit or del link actions to return back to same view.
		if ($singlepane_view == 'true') {
			$returnset = "&return_module=$currentModule&return_action=DetailView&return_id=$id";
		} else {
			$returnset = "&return_module=$currentModule&return_action=CallRelatedList&return_id=$id";
		}

		$return_value = null;
		if ($actions) {
			if (is_string($actions)) {
				$actions = explode(',', strtoupper($actions));
			}
			if (in_array('ADD', $actions) && isPermitted($related_module, 1, '') == 'yes') {
				$button.="<input title='".getTranslatedString('LBL_ADD_NEW').' '.getTranslatedString($singular_modname, $related_module)."' class='crmbutton small create'"
					." onclick='this.form.action.value=\"EditView\";this.form.module.value=\"$related_module\"' type='submit' name='button'"
					." value='". getTranslatedString('LBL_ADD_NEW'). ' ' . getTranslatedString($singular_modname, $related_module) ."'>&nbsp;";
			}
		}

		$query = "SELECT vtiger_crmentity.*, $other->table_name.*";
		$query .= ", CASE WHEN (vtiger_users.user_name NOT LIKE '') THEN vtiger_users.ename ELSE vtiger_groups.groupname END AS user_name";

		$more_relation = '';
		if (!empty($other->related_tables)) {
			foreach ($other->related_tables as $tname => $relmap) {
				$query .= ", $tname.*";

				// Setup the default JOIN conditions if not specified
				if (empty($relmap[1])) {
					$relmap[1] = $other->table_name;
				}
				if (empty($relmap[2])) {
					$relmap[2] = $relmap[0];
				}
				$more_relation .= " LEFT JOIN $tname ON $tname.$relmap[0] = $relmap[1].$relmap[2]";
			}
		}

		$query .= " FROM $other->table_name";
		$query .= ' INNER JOIN '.$other->crmentityTableAlias." ON vtiger_crmentity.crmid = $other->table_name.$other->table_index";
		$query .= $more_relation;
		$query .= " LEFT  JOIN vtiger_users        ON vtiger_users.id = vtiger_crmentity.smownerid";
		$query .= " LEFT  JOIN vtiger_groups       ON vtiger_groups.groupid = vtiger_crmentity.smownerid";

		$query .= " WHERE vtiger_crmentity.deleted = 0 AND vtiger_timecontrol.date_start = '$workdate'";
		$query .= "   AND vtiger_crmentity.smownerid = $tcuser";

		$return_value = GetRelatedList($currentModule, $related_module, $other, $query, $button, $returnset);

		if ($return_value == null) {
			$return_value = array();
		}
		$return_value['CUSTOM_BUTTON'] = $button;

		return $return_value;
	}

	/**
	 * Invoked when special actions are performed on the module.
	 * @param String Module name
	 * @param String Event Type (module.postinstall, module.disabled, module.enabled, module.preuninstall)
	 */
	public function vtlib_handler($modulename, $event_type) {
		if ($event_type == 'module.postinstall') {
			// Handle post installation actions
			$this->setModuleSeqNumber('configure', $modulename, 'TIME-USER-TOTAL-', '00000001');
		} elseif ($event_type == 'module.disabled') {
			// Handle actions when this module is disabled.
		} elseif ($event_type == 'module.enabled') {
			// Handle actions when this module is enabled.
		} elseif ($event_type == 'module.preuninstall') {
			// Handle actions when this module is about to be deleted.
		} elseif ($event_type == 'module.preupdate') {
			// Handle actions before this module is updated.
		} elseif ($event_type == 'module.postupdate') {
			// Handle actions after this module is updated.
		}
	}

	/**
	 * Handle saving related module information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	// public function save_related_module($module, $crmid, $with_module, $with_crmid) { }

	/**
	 * Handle deleting related module information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function delete_related_module($module, $crmid, $with_module, $with_crmid) { }

	/**
	 * Handle getting related list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function get_related_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }

	/**
	 * Handle getting dependents list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function get_dependents_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }
}
?>
