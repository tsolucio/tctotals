<?php
/*************************************************************************************************
 * Copyright 2013 JPL TSolucio, S.L.  --  This file is a part of vtiger CRM TimeControl extension.
 * You can copy, adapt and distribute the work under the "Attribution-NonCommercial-ShareAlike"
 * Vizsage Public License (the "License"). You may not use this file except in compliance with the
 * License. Roughly speaking, non-commercial users may share and modify this code, but must give credit
 * and share improvements. However, for proper details please read the full License, available at
 * http://vizsage.com/license/Vizsage-License-BY-NC-SA.html and the handy reference for understanding
 * the full license at http://vizsage.com/license/Vizsage-Deed-BY-NC-SA.html. Unless required by
 * applicable law or agreed to in writing, any software distributed under the License is distributed
 * on an  "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and limitations under the
 * License terms of Creative Commons Attribution-NonCommercial-ShareAlike 3.0 (the License).
 *************************************************************************************************
 *  Module       : Timecontrol Totals
 *  Version      : 5.4.2
 *  Author       : JPL TSolucio, S. L.   Joe Bordes
 ********************************************************************************/

class TCTotalsHandler extends VTEventHandler {

	public function handleEvent($eventName, $entityData) {
		global $adb;

		if ($eventName == 'vtiger.entity.beforesave') {
			$moduleName = $entityData->getModuleName();
			if ($moduleName == 'Timecontrol' && vtlib_isModuleActive('TCTotals')) {
				$tcId = $entityData->getId();
				$oldUser = $workdate = $relto = $pdoid = 0;
				if (!empty($tcId)) {
					$tcdata=$adb->query("select smownerid,date_start,relatedto,product_id from vtiger_timecontrol inner join vtiger_crmentity on crmid=timecontrolid where timecontrolid=$tcId");
					$workdate=$adb->query_result($tcdata, 0, 'date_start');
					$oldUser=$adb->query_result($tcdata, 0, 'smownerid');
					$relto=$adb->query_result($tcdata, 0, 'relatedto');
					$pdoid=$adb->query_result($tcdata, 0, 'product_id');
				}
				$entityData->oldUser = $oldUser;
				$entityData->oldWorkDate = $workdate;
				$entityData->oldrelto = $relto;
				$entityData->oldpdoid = $pdoid;
			}
		}

		if ($eventName == 'vtiger.entity.aftersave') {
			$moduleName = $entityData->getModuleName();

			// Update total time record
			if ($moduleName == 'Timecontrol' && vtlib_isModuleActive('TCTotals')) {
				$tcId = $entityData->getId();
				$tcdata=$adb->query("select smownerid,date_start,relatedto,product_id from vtiger_timecontrol inner join vtiger_crmentity on crmid=timecontrolid where timecontrolid=$tcId");
				$workdate=$adb->query_result($tcdata, 0, 'date_start');
				$tcuser=$adb->query_result($tcdata, 0, 'smownerid');
				if (($entityData->oldUser!=0 && $entityData->oldWorkDate!=0)  // we are editing
					&&
					($tcuser != $entityData->oldUser || $workdate != $entityData->oldWorkDate)) {  // user or date have changed
					$this->updateTotalTimeForUserOnDate($entityData->oldUser, $entityData->oldWorkDate);
				}
				$relto=$adb->query_result($tcdata, 0, 'relatedto');
				$pdoid=$adb->query_result($tcdata, 0, 'product_id');
				$this->updateTotalTimeForUserOnDate($tcuser, $workdate);
			}
		}
	}

	public static function updateTotalTimeForUserOnDate($user, $workdate) {
		global $adb,$current_module;
		$recordExists=$adb->getOne("select count(*) from vtiger_tctotals inner join vtiger_crmentity on crmid=tctotalsid where smownerid=$user and workdate='$workdate' and deleted=0");
		if ($recordExists==0) { // no total record for this user on that date, we have to create one
			include_once 'modules/TCTotals/TCTotals.php';
			$current_module='TCTotals';
			$tc=new TCTotals();
			$tc->mode='';
			$tc->column_fields['assigned_user_id']=$user;
			$tc->save('TCTotals');
			$adb->query("update vtiger_crmentity set smownerid=$user where crmid = ".$tc->id); // not necessary, but in case $current_user gets in the way
			$adb->query("update vtiger_tctotals set workdate='$workdate' where tctotalsid = ".$tc->id);  // displaytype=2
			$tctotal2update=$tc->id;
			$current_module='Timecontrol';
		} else {
			$tctotal2update=$adb->getOne("select tctotalsid from vtiger_tctotals inner join vtiger_crmentity on crmid=tctotalsid where smownerid=$user and workdate='$workdate' and deleted=0");
		}
		$tctot=$adb->query("select coalesce(sum(time_to_sec(totaltime))/3600,0) as totnum, coalesce(sec_to_time(sum(time_to_sec(totaltime))),0) as tottime
			from vtiger_timecontrol
			inner join vtiger_crmentity on crmid=timecontrolid
			where date_start='$workdate' and smownerid=$user and deleted=0");
		$totnum=$adb->query_result($tctot, 0, 'totnum');
		$tottim=$adb->query_result($tctot, 0, 'tottime');
		$adb->query("update vtiger_tctotals set totalhours=$totnum,totaltime='$tottim' where tctotalsid = $tctotal2update");
	}
}
?>
