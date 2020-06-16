<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 Releases plugin for GLPI
 Copyright (C) 2018 by the Releases Development Team.

 https://github.com/InfotelGLPI/releases
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Releases.

 Releases is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 releases is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with releases. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}


/**
 * Class PluginReleasesRelease
 */
class PluginReleasesRelease extends CommonITILObject {

   public    $dohistory  = true;
   static    $rightname  = 'plugin_releases_releases';
   protected $usenotepad = true;
   static    $types      = [];
   public $userlinkclass               = 'PluginReleasesRelease_User';
   public $grouplinkclass              = 'PluginReleasesGroup_Release';
   public $supplierlinkclass           = 'PluginReleasesSupplier_Release';

   // STATUS
   const TODO       = 1; // todo
   const DONE       = 2; // done
   const PROCESSING = 3; // processing
   const WAITING    = 4; // waiting
   const LATE       = 5; // late
   const DEF        = 6; // default

   const NEWRELEASE         = 7;
   const RELEASEDEFINITION  = 8; // default
   const DATEDEFINITION     = 9; // date definition
   const CHANGEDEFINITION   = 10; // changes defenition
   const RISKDEFINITION     = 11; // risks definition
   const ROLLBACKDEFINITION = 12; // rollbacks definition
   const TASKDEFINITION     = 13; // tasks definition
   const TESTDEFINITION     = 14; // tests definition
   const FINALIZE           = 15; // finalized
   const REVIEW             = 16; // reviewed
   const CLOSED             = 17; // closed


//   static $typeslinkable = ["Computer"  => "Computer",
//                            "Appliance" => "Appliance"];


   //TODO Add actors
   //TODO Add PluginReleaseFollowup
   /**
    * @param int $nb
    *
    * @return translated
    */
   static function getTypeName($nb = 0) {

      return _n('Release', 'Releases', $nb, 'releases');
   }


   static function countForItem($ID, $class, $state = 0) {
      $dbu   = new DbUtils();
      $table = CommonDBTM::getTable($class);
      if ($state) {
         return $dbu->countElementsInTable($table,
                                           ["plugin_releases_releases_id" => $ID, "state" => 2]);
      }
      return $dbu->countElementsInTable($table,
                                        ["plugin_releases_releases_id" => $ID]);
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if (static::canView()) {
         switch ($item->getType()) {
            case __CLASS__ :
               $timeline    = $item->getTimelineItems();
               $nb_elements = count($timeline);

               $ong = [
                  1 => __("Processing release", 'releases') . " <sup class='tab_nb'>$nb_elements</sup>",
               ];
               //TODO create own class - for own tab position
               if ($item->canUpdate()) {
                  $ong[2] = __("Finalization", 'releases');
               }

               return $ong;
            case "Change" :
               return self::createTabEntry(self::getTypeName(2), self::countItemForAChange($item));
         }
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      switch ($item->getType()) {
         case __CLASS__ :
            switch ($tabnum) {
               case 1 :
                  echo "<div class='timeline_box'>";
                  $rand = mt_rand();
                  $item->showTimelineForm($rand);
                  $item->showTimeline($rand);
                  echo "</div>";
                  break;
               case 2 :
                  //TODO Drop to own class / tab
                  $item->showFinalisationTabs($item->getID());
                  break;
            }
            break;
         case "Change" :
            PluginReleasesChange_Release::showReleaseFromChange($item);
            break;
      }
      return true;
   }

   static function countItemForAChange($item) {
      $dbu   = new DbUtils();
      $table = CommonDBTM::getTable(PluginReleasesChange_Release::class);
      return $dbu->countElementsInTable($table,
                                        ["changes_id" => $item->getID()]);
   }

   function defineTabs($options = []) {

      $ong = [];
      $this->defineDefaultObjectTabs($ong, $options);
      $this->addStandardTab('PluginReleasesChange_Release', $ong, $options);
      $this->addStandardTab('Document_Item', $ong, $options);
      $this->addStandardTab('KnowbaseItem_Item', $ong, $options);
      $this->addStandardTab('PluginReleasesRelease_Item', $ong, $options);
      if ($this->hasImpactTab()) {
         $this->addStandardTab('Impact', $ong, $options);
      }
      $this->addStandardTab('PluginReleasesReview', $ong, $options);
      $this->addStandardTab('Notepad', $ong, $options);
      $this->addStandardTab('Log', $ong, $options);
      return $ong;
   }

   /**
    * @return array
    */
   function rawSearchOptions() {

      $tab = [];

      $tab[] = [
         'id'   => 'common',
         'name' => self::getTypeName(2)
      ];

      $tab[] = [
         'id'            => '1',
         'table'         => $this->getTable(),
         'field'         => 'name',
         'name'          => __('name'),
         'datatype'      => 'itemlink',
         'itemlink_type' => $this->getType()
      ];
      $tab[] = [
         'id'            => '2',
         'table'         => $this->getTable(),
         'field'         => 'content',
         'name'          => __('Description'),
         'massiveaction' => false,
         'datatype'      => 'text',
         'htmltext'      => true
      ];
      $tab[] = [
         'id'            => '3',
         'table'         => $this->getTable(),
         'field'         => 'date_preproduction',
         'name'          => __('Pre-production run date', 'releases'),
         'massiveaction' => false,
         'datatype'      => 'date'
      ];
      $tab[] = [
         'id'            => '4',
         'table'         => $this->getTable(),
         'field'         => 'is_recursive',
         'name'          => __('Number of risks', 'releases'),
         'massiveaction' => false,
         'datatype'      => 'specific'
      ];
      $tab[] = [
         'id'            => '5',
         'table'         => $this->getTable(),
         'field'         => 'name',
         'name'          => __('Number of tests', 'releases'),
         'massiveaction' => false,
         'datatype'      => 'specific'
      ];
      $tab[] = [
         'id'            => '6',
         'table'         => $this->getTable(),
         'field'         => 'service_shutdown',
         'name'          => __('Number of tasks', 'releases'),
         'massiveaction' => false,
         'datatype'      => 'specific'
      ];
      $tab[] = [
         'id'            => '7',
         'table'         => $this->getTable(),
         'field'         => 'status',
         'name'          => __('Status'),
         'massiveaction' => false,
         'datatype'      => 'specific'
      ];
      $tab[] = [
         'id'            => '8',
         'table'         => $this->getTable(),
         'field'         => 'date_production',
         'name'          => __('Production run date', 'releases'),
         'massiveaction' => false,
         'datatype'      => 'date'
      ];
      return $tab;

   }

   /**
    * display a value according to a field
    *
    * @param $field     String         name of the field
    * @param $values    String / Array with the value to display
    * @param $options   Array          of option
    *
    * @return a string
    **@since version 0.83
    *
    */
   static function getSpecificValueToDisplay($field, $values, array $options = []) {

      if (!is_array($values)) {
         $values = [$field => $values];
      }
      switch ($field) {
         case 'status':
            $var = "<span class='status'>";
            $var .= self::getStatusIcon($values["status"]);
            $var .= self::getStatus($values["status"]);
            $var .= "</span>";
            return $var;
            break;
         case 'service_shutdown':
            return self::countForItem($options["raw_data"]["id"], PluginReleasesDeploytask::class, 1) . ' / ' . self::countForItem($options["raw_data"]["id"], PluginReleasesDeploytask::class);
            break;
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }

   /**
    * @param datas $input
    *
    * @return datas
    */
   function prepareInputForAdd($input) {


      $input = parent::prepareInputForAdd($input);

      if ((isset($input['target']) && empty($input['target'])) || !isset($input['target'])) {
         $input['target'] = [];
      }
      $input['target'] = json_encode($input['target']);
      if (!empty($input["date_preproduction"])
          && $input["date_preproduction"] != NULL
          && !empty($input["date_production"])
          && $input["date_production"] != NULL
          && $input["status"] < self::DATEDEFINITION) {

         $input['status'] = self::DATEDEFINITION;

      } else if (!empty($input["content"]) && $input["status"] < self::RELEASEDEFINITION) {

         $input['status'] = self::RELEASEDEFINITION;

      }


      return $input;
   }


   /**
    * Actions done after the ADD of the item in the database
    *
    * @return void
    **/
   function post_addItem() {
      global $DB;
      if (isset($this->input["releasetemplates_id"])) {
         $template = new PluginReleasesReleasetemplate();
         $template->getFromDB($this->input["releasetemplates_id"]);
         $tests            = json_decode($template->getField("tests"));
         $rollbacks        = json_decode($template->getField("rollbacks"));
         $tasks            = json_decode($template->getField("tasks"));
         $risks            = [];
         $releaseTest      = new PluginReleasesTest();
         $testTemplate     = new PluginReleasesTesttemplate();
         $releaseTask      = new PluginReleasesDeploytask();
         $taskTemplate     = new PluginReleasesDeploytasktemplate();
         $releaseRollback  = new PluginReleasesRollback();
         $rollbackTemplate = new PluginReleasesRollbacktemplate();
         $releaseRisk      = new PluginReleasesRisk();
         $riskTemplate     = new PluginReleasesRisktemplate();

         foreach ($tests as $test) {
            if ($testTemplate->getFromDB($test)) {
               $input                                = $testTemplate->fields;
               $input["plugin_releases_releases_id"] = $this->getID();
               if ($riskTemplate->getFromDB($input["plugin_releases_risks_id"])) {
                  if (array_key_exists($input["plugin_releases_risks_id"], $risks)) {
                     $input["plugin_releases_risks_id"] = $risks[$input["plugin_releases_risks_id"]];
                  } else {
                     $inputRisk                                = $riskTemplate->fields;
                     $inputRisk["plugin_releases_releases_id"] = $this->getID();
                     unset($inputRisk["id"]);
                     $idRisk                                    = $releaseRisk->add($inputRisk);
                     $risks[$input["plugin_releases_risks_id"]] = $idRisk;
                     $input["plugin_releases_risks_id"]         = $idRisk;
                  }
               } else {
                  $input["plugin_releases_risks_id"] = 0;
               }
               unset($input["id"]);
               $releaseTest->add($input);
            }
         }
         foreach ($tasks as $task) {
            if ($taskTemplate->getFromDB($task)) {
               $input                                = $taskTemplate->fields;
               $input["plugin_releases_releases_id"] = $this->getID();
               if ($riskTemplate->getFromDB($input["plugin_releases_risks_id"])) {
                  if (array_key_exists($input["plugin_releases_risks_id"], $risks)) {
                     $input["plugin_releases_risks_id"] = $risks[$input["plugin_releases_risks_id"]];
                  } else {
                     $inputRisk                                = $riskTemplate->fields;
                     $inputRisk["plugin_releases_releases_id"] = $this->getID();
                     unset($inputRisk["id"]);
                     $idRisk                                    = $releaseRisk->add($inputRisk);
                     $risks[$input["plugin_releases_risks_id"]] = $idRisk;
                     $input["plugin_releases_risks_id"]         = $idRisk;
                  }
               } else {
                  $input["plugin_releases_risks_id"] = 0;
               }
               unset($input["id"]);
               $releaseTask->add($input);
            }
         }
         foreach ($rollbacks as $rollback) {
            if ($rollbackTemplate->getFromDB($rollback)) {
               $input                                = $rollbackTemplate->fields;
               $input["plugin_releases_releases_id"] = $this->getID();
               unset($input["id"]);
               $releaseRollback->add($input);
            }
         }

      }
      if (isset($this->input["changes"])) {


         foreach ($this->input["changes"] as $change) {
            $release_change                      = new PluginReleasesChange_Release();
            $vals                                = [];
            $vals["changes_id"]                  = $change;
            $vals["plugin_releases_releases_id"] = $this->getID();
            $release_change->add($vals);
         }
      }
      //      $query = "INSERT INTO `glpi_plugin_release_globalstatues`
      //                             ( `plugin_release_releases_id`,`itemtype`, `status`)
      //                      VALUES (".$this->fields['id'].",'". PluginReleasesRisk::getType()."', 0),
      //                      (".$this->fields['id'].",'". PluginReleasesTest::getType()."', 0),
      //                      (".$this->fields['id'].",'". PluginReleasesRelease::getType()."', 0),
      //                      (".$this->fields['id'].",'". PluginReleasesDeploytask::getType()."', 0),
      //                      (".$this->fields['id'].",'PluginReleaseDate', 0),
      //                      (".$this->fields['id'].",'". PluginReleasesRollback::getType()."', 0)
      //                      ;";
      //      $DB->queryOrDie($query, "statues creation");

   }

   /**
    * get the Ticket status list
    *
    * @param $withmetaforsearch boolean (false by default)
    *
    * @return array
    **/
   static function getAllStatusArray($releasestatus = false) {

      // To be overridden by class
      if ($releasestatus) {
         $tab = [
            //TODO Used ?
            self::TODO       => __('To do'),
            self::DONE       => __('Done'),
            self::PROCESSING => __('In progress', 'releases'),
            self::WAITING    => __('waiting', 'releases'),
            self::LATE       => __('Late', 'releases'),
            self::DEF        => __('Default', 'releases'),

            self::NEWRELEASE         => _x('status', 'New'),
            self::RELEASEDEFINITION  => __('Release area defined', 'releases'),
            self::DATEDEFINITION     => __('Dates defined', 'releases'),
            self::CHANGEDEFINITION   => __('Changes defined', 'releases'),
            self::RISKDEFINITION     => __('Risks defined', 'releases'),
            self::ROLLBACKDEFINITION => __('Rollbacks defined', 'releases'),
            self::TASKDEFINITION     => __('Deployment tasks in progress', 'releases'),
            self::TESTDEFINITION     => __('Tests in progress', 'releases'),
            self::FINALIZE           => __('Finalized', 'releases'),
            self::REVIEW             => __('Reviewed', 'releases'),
            self::CLOSED             => _x('status', 'Closed')];
      } else {
         $tab = [
            self::NEWRELEASE         => _x('status', 'New'),
            self::RELEASEDEFINITION  => __('Release area defined', 'releases'),
            self::DATEDEFINITION     => __('Dates defined', 'releases'),
            self::CHANGEDEFINITION   => __('Changes defined', 'releases'),
            self::RISKDEFINITION     => __('Risks defined', 'releases'),
            self::ROLLBACKDEFINITION => __('Rollbacks defined', 'releases'),
            self::TASKDEFINITION     => __('Deployment tasks in progress', 'releases'),
            self::TESTDEFINITION     => __('Tests in progress', 'releases'),
            self::FINALIZE           => __('Finalized', 'releases'),
            self::REVIEW             => __('Reviewed', 'releases'),
            self::CLOSED             => _x('status', 'Closed')];
      }


      return $tab;
   }

   /**
    * Get status icon
    *
    * @return string
    * @since 9.3
    *
    */
   public static function getStatusIcon($status) {
      $class = static::getStatusClass($status);
      $label = static::getStatus($status);
      return "<i class='$class' title='$label'></i>";
   }

   /**
    * Get ITIL object status Name
    *
    * @param integer $value status ID
    **@since 0.84
    *
    */
   static function getStatus($value) {

      $tab = static::getAllStatusArray(true);
      // Return $value if not defined
      return (isset($tab[$value]) ? $tab[$value] : $value);
   }

   /**
    * Get status class
    *
    * @return string
    * @since 9.3
    *
    */
   public static function getStatusClass($status) {
      $class = null;
      $solid = true;

      switch ($status) {
         case self::TODO :
            $class = 'circle';
            break;
         case self::DONE :
            $class = 'circle';
            //            $solid = false;
            break;
         case self::PROCESSING :
            $class = 'circle';
            break;
         case self::WAITING :
            $class = 'circle';
            break;
         case self::LATE :
            $class = 'circle';
            //            $solid = false;
            break;
         case self::DEF :
            $class = 'circle';
            break;
         case self::NEWRELEASE :
            $class = 'circle';
            break;
         case self::RELEASEDEFINITION :
            $class = 'circle';
            $solid = false;
            break;
         case self::DATEDEFINITION :
            $class = 'circle';
            $solid = false;
            break;
         case self::CHANGEDEFINITION :
            $class = 'circle';
            $solid = false;
            break;
         case self::RISKDEFINITION :
            $class = 'circle';
            $solid = false;
            break;
         case self::TESTDEFINITION :
            $class = 'circle';
            $solid = false;
            break;
         case self::TASKDEFINITION :
            $class = 'circle';
            $solid = false;
            break;
         case self::ROLLBACKDEFINITION :
            $class = 'circle';
            $solid = false;
            break;
         case self::FINALIZE :
            $class = 'circle';
            $solid = false;
            break;
         case self::REVIEW :
            $class = 'circle';
            $solid = false;
            break;
         case self::CLOSED :
            $class = 'circle';
            break;


         default:
            $class = 'circle';
            break;

      }

      return $class == null
         ? ''
         : 'releasestatus ' . ($solid ? 'fas fa-' : 'far fa-') . $class .
           " " . self::getStatusKey($status);
   }

   /**
    * Get status key
    *
    * @return string
    * @since 9.3
    *
    */
   public static function getStatusKey($status) {
      $key = '';
      switch ($status) {
         case self::DONE :
            $key = 'done';
            break;
         case self::TODO :
            $key = 'todo';
            break;
         case self::WAITING :
            $key = 'waiting';
            break;
         case self::PROCESSING :
            $key = 'inprogress';
            break;
         case self::LATE :
            $key = 'late';
            break;
         case self::DEF :
            $key = 'default';
            break;
         case self::NEWRELEASE :
            $key = 'newrelease';
            break;
         case self::RELEASEDEFINITION :
            $key = 'releasedef';
            break;
         case self::DATEDEFINITION :
            $key = 'datedef';
            break;
         case self::CHANGEDEFINITION :
            $key = 'changedef';
            break;
         case self::RISKDEFINITION :
            $key = 'riskdef';
            break;
         case self::TESTDEFINITION :
            $key = 'testdef';
            break;
         case self::TASKDEFINITION :
            $key = 'taskdef';
            break;
         case self::ROLLBACKDEFINITION :
            $key = 'rollbackdef';
            break;
         case self::FINALIZE :
            $key = 'finalize';
            break;
         case self::REVIEW :
            $key = 'review';
            break;
         case self::CLOSED :
            $key = 'closerelease';
            break;

      }
      return $key;
   }

   /**
    *
    * @param datas $input
    *
    * @return datas
    */
   function prepareInputForUpdate($input) {

//      $input = parent::prepareInputForUpdate($input);
      if ((isset($input['target']) && empty($input['target'])) || !isset($input['target'])) {
         $input['target'] = [];
      }
      $input['target'] = json_encode($input['target']);
      if (!empty($input["date_preproduction"])
          && !empty($input["date_production"])
          && $input["status"] < self::DATEDEFINITION) {

         $input['status'] = self::DATEDEFINITION;

      } else if (!empty($input["content"])
                 && $input["status"] < self::RELEASEDEFINITION) {

         $input['status'] = self::RELEASEDEFINITION;

      }
      $do_not_compute_takeintoaccount = $this->isTakeIntoAccountComputationBlocked($input);
      if (isset($input['_itil_requester'])) {
         if (isset($input['_itil_requester']['_type'])) {
            $input['_itil_requester'] = [
                  'type'                            => CommonITILActor::REQUESTER,
                  $this->getForeignKeyField()       => $input['id'],
                  '_do_not_compute_takeintoaccount' => $do_not_compute_takeintoaccount,
                  '_from_object'                    => true,
               ] + $input['_itil_requester'];

            switch ($input['_itil_requester']['_type']) {
               case "user" :
                  if (isset($input['_itil_requester']['use_notification'])
                     && is_array($input['_itil_requester']['use_notification'])) {
                     $input['_itil_requester']['use_notification'] = $input['_itil_requester']['use_notification'][0];
                  }
                  if (isset($input['_itil_requester']['alternative_email'])
                     && is_array($input['_itil_requester']['alternative_email'])) {
                     $input['_itil_requester']['alternative_email'] = $input['_itil_requester']['alternative_email'][0];
                  }

                  if (!empty($this->userlinkclass)) {
                     if (isset($input['_itil_requester']['alternative_email'])
                        && $input['_itil_requester']['alternative_email']
                        && !NotificationMailing::isUserAddressValid($input['_itil_requester']['alternative_email'])) {

                        $input['_itil_requester']['alternative_email'] = '';
                        Session::addMessageAfterRedirect(__('Invalid email address'), false, ERROR);
                     }

                     if ((isset($input['_itil_requester']['alternative_email'])
                           && $input['_itil_requester']['alternative_email'])
                        || ($input['_itil_requester']['users_id'] > 0)) {

                        $useractors = new $this->userlinkclass();
                        if (isset($input['_auto_update'])
                           || $useractors->can(-1, CREATE, $input['_itil_requester'])) {
                           $useractors->add($input['_itil_requester']);
                           $input['_forcenotif']                     = true;
                        }
                     }
                  }
                  break;

               case "group" :
                  if (!empty($this->grouplinkclass)
                     && ($input['_itil_requester']['groups_id'] > 0)) {
                     $groupactors = new $this->grouplinkclass();
                     if (isset($input['_auto_update'])
                        || $groupactors->can(-1, CREATE, $input['_itil_requester'])) {
                        $groupactors->add($input['_itil_requester']);
                        $input['_forcenotif']                     = true;
                     }
                  }
                  break;
            }
         }
      }

      if (isset($input['_itil_observer'])) {
         if (isset($input['_itil_observer']['_type'])) {
            $input['_itil_observer'] = [
                  'type'                            => CommonITILActor::OBSERVER,
                  $this->getForeignKeyField()       => $input['id'],
                  '_do_not_compute_takeintoaccount' => $do_not_compute_takeintoaccount,
                  '_from_object'                    => true,
               ] + $input['_itil_observer'];

            switch ($input['_itil_observer']['_type']) {
               case "user" :
                  if (isset($input['_itil_observer']['use_notification'])
                     && is_array($input['_itil_observer']['use_notification'])) {
                     $input['_itil_observer']['use_notification'] = $input['_itil_observer']['use_notification'][0];
                  }
                  if (isset($input['_itil_observer']['alternative_email'])
                     && is_array($input['_itil_observer']['alternative_email'])) {
                     $input['_itil_observer']['alternative_email'] = $input['_itil_observer']['alternative_email'][0];
                  }

                  if (!empty($this->userlinkclass)) {
                     if (isset($input['_itil_observer']['alternative_email'])
                        && $input['_itil_observer']['alternative_email']
                        && !NotificationMailing::isUserAddressValid($input['_itil_observer']['alternative_email'])) {

                        $input['_itil_observer']['alternative_email'] = '';
                        Session::addMessageAfterRedirect(__('Invalid email address'), false, ERROR);
                     }
                     if ((isset($input['_itil_observer']['alternative_email'])
                           && $input['_itil_observer']['alternative_email'])
                        || ($input['_itil_observer']['users_id'] > 0)) {
                        $useractors = new $this->userlinkclass();
                        if (isset($input['_auto_update'])
                           || $useractors->can(-1, CREATE, $input['_itil_observer'])) {
                           $useractors->add($input['_itil_observer']);
                           $input['_forcenotif']                    = true;
                        }
                     }
                  }
                  break;

               case "group" :
                  if (!empty($this->grouplinkclass)
                     && ($input['_itil_observer']['groups_id'] > 0)) {
                     $groupactors = new $this->grouplinkclass();
                     if (isset($input['_auto_update'])
                        || $groupactors->can(-1, CREATE, $input['_itil_observer'])) {
                        $groupactors->add($input['_itil_observer']);
                        $input['_forcenotif']                    = true;
                     }
                  }
                  break;
            }
         }
      }

      if (isset($input['_itil_assign'])) {
         if (isset($input['_itil_assign']['_type'])) {
            $input['_itil_assign'] = [
                  'type'                            => CommonITILActor::ASSIGN,
                  $this->getForeignKeyField()       => $input['id'],
                  '_do_not_compute_takeintoaccount' => $do_not_compute_takeintoaccount,
                  '_from_object'                    => true,
               ] + $input['_itil_assign'];

            if (isset($input['_itil_assign']['use_notification'])
               && is_array($input['_itil_assign']['use_notification'])) {
               $input['_itil_assign']['use_notification'] = $input['_itil_assign']['use_notification'][0];
            }
            if (isset($input['_itil_assign']['alternative_email'])
               && is_array($input['_itil_assign']['alternative_email'])) {
               $input['_itil_assign']['alternative_email'] = $input['_itil_assign']['alternative_email'][0];
            }

            switch ($input['_itil_assign']['_type']) {
               case "user" :
                  if (!empty($this->userlinkclass)
                     && ((isset($input['_itil_assign']['alternative_email'])
                           && $input['_itil_assign']['alternative_email'])
                        || $input['_itil_assign']['users_id'] > 0)) {
                     $useractors = new $this->userlinkclass();
                     if (isset($input['_auto_update'])
                        || $useractors->can(-1, CREATE, $input['_itil_assign'])) {
                        $useractors->add($input['_itil_assign']);
                        $input['_forcenotif']                  = true;
                        if (((!isset($input['status'])
                                 && in_array($this->fields['status'], $this->getNewStatusArray()))
                              || (isset($input['status'])
                                 && in_array($input['status'], $this->getNewStatusArray())))
                           && !$this->isStatusComputationBlocked($input)) {
                           if (in_array(self::ASSIGNED, array_keys($this->getAllStatusArray()))) {
                              $input['status'] = self::ASSIGNED;
                           }
                        }
                     }
                  }
                  break;

               case "group" :
                  if (!empty($this->grouplinkclass)
                     && ($input['_itil_assign']['groups_id'] > 0)) {
                     $groupactors = new $this->grouplinkclass();

                     if (isset($input['_auto_update'])
                        || $groupactors->can(-1, CREATE, $input['_itil_assign'])) {
                        $groupactors->add($input['_itil_assign']);
                        $input['_forcenotif']                  = true;
                        if (((!isset($input['status'])
                                 && (in_array($this->fields['status'], $this->getNewStatusArray())))
                              || (isset($input['status'])
                                 && (in_array($input['status'], $this->getNewStatusArray()))))
                           && !$this->isStatusComputationBlocked($input)) {
                           if (in_array(self::ASSIGNED, array_keys($this->getAllStatusArray()))) {
                              $input['status'] = self::ASSIGNED;
                           }
                        }
                     }
                  }
                  break;

               case "supplier" :
                  if (!empty($this->supplierlinkclass)
                     && ((isset($input['_itil_assign']['alternative_email'])
                           && $input['_itil_assign']['alternative_email'])
                        || $input['_itil_assign']['suppliers_id'] > 0)) {
                     $supplieractors = new $this->supplierlinkclass();
                     if (isset($input['_auto_update'])
                        || $supplieractors->can(-1, CREATE, $input['_itil_assign'])) {
                        $supplieractors->add($input['_itil_assign']);
                        $input['_forcenotif']                  = true;
                        if (((!isset($input['status'])
                                 && (in_array($this->fields['status'], $this->getNewStatusArray())))
                              || (isset($input['status'])
                                 && (in_array($input['status'], $this->getNewStatusArray()))))
                           && !$this->isStatusComputationBlocked($input)) {
                           if (in_array(self::ASSIGNED, array_keys($this->getAllStatusArray()))) {
                              $input['status'] = self::ASSIGNED;
                           }

                        }
                     }
                  }
                  break;
            }
         }
      }

//      $this->addAdditionalActors($input);

      return $input;
   }

   /**
    * Type than could be linked to a Rack
    *
    * @param $all boolean, all type, or only allowed ones
    *
    * @return array of types
    * */
   static function getTypes($all = false) {

      if ($all) {
         return self::$types;
      }

      // Only allowed types
      $types = self::$types;

      foreach ($types as $key => $type) {
         if (!class_exists($type)) {
            continue;
         }

         $item = new $type();
         if (!$item->canView()) {
            unset($types[$key]);
         }
      }
      return $types;
   }

   function prepareField($template_id) {
      $template = new PluginReleasesReleasetemplate();
      $template->getFromDB($template_id);

      foreach ($this->fields as $key => $field) {
         if ($key != "id") {
            $this->fields[$key] = $template->getField($key);
         }
      }
   }

   function showForm($ID, $options = []) {
      global $CFG_GLPI, $DB;

      if (isset($options["template_id"]) && $options["template_id"] > 0) {
         $this->prepareField($options["template_id"]);
         echo Html::hidden("releasetemplates_id", ["value" => $options["template_id"]]);
      }
      $select_changes = [];
      if (isset($options["changes_id"])) {
         $select_changes = [$options["changes_id"]];
         if ((isset($options["template_id"]) && $options["template_id"] = 0) || !isset($options["template_id"])) {
            $c = new Change();
            if ($c->getFromDB($options["changes_id"])) {
               $this->fields["name"]        = $c->getField("name");
               $this->fields["content"]     = $c->getField("content");
               $this->fields["entities_id"] = $c->getField("entities_id");
            }

         }
      }

      if ($ID > 0) {
         $this->check($ID, READ);
      } else {
         // Create item
         $this->check(-1, CREATE, $options);
      }

      if (!$this->isNewItem()) {
         $options['formtitle'] = sprintf(
            __('%1$s - ID %2$d'),
            $this->getTypeName(1),
            $ID
         );
         //set ID as already defined
         $options['noid'] = true;
      }

      if (!isset($options['template_preview'])) {
         $options['template_preview'] = 0;
      }

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Name') . "</td>";
      echo "<td>";
      echo Html::input("name", ["value" => $this->getField('name')]);
      echo "</td>";
      echo "<td>" . __('Status') . "</td>";
      echo "<td>";
      Dropdown::showFromArray('status', self::getAllStatusArray(false), ['value' => $this->getField('status')]);
      //      echo self::getStatus($this->getField('status'));
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Release area', 'releases') . "</td>";
      echo "<td colspan='3'>";
      Html::textarea(["name"            => "content",
                      "enable_richtext" => true,
                      "value"           => $this->getField('content')]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Pre-production planned run date', 'releases') . "</td>";
      echo "<td >";
      Html::showDateField("date_preproduction", ["value" => $this->getField('date_preproduction')]);
      echo "</td>";
      echo "<td>" . __('Production planned run date', 'releases') . "</td>";
      echo "<td >";
      Html::showDateField("date_production", ["value" => $this->getField('date_production')]);
      echo "</td>";

      echo "</tr>";
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Location') . "</td>";
      echo "<td >";
      Dropdown::show(Location::getType(), ["name"  => "locations_id",
                                           "value" => $this->getField('locations_id')]);
      echo "</td>";
      echo "<td>" . __('Service shutdown', 'releases') . "</td>";
      echo "<td >";
      Dropdown::showYesNo("service_shutdown", $this->getField('service_shutdown'));
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Service shutdown details', 'releases') . "</td>";
      echo "<td colspan='3'>";
      Html::textarea(["name"            => "service_shutdown_details",
                      "enable_richtext" => true,
                      "value"           => $this->getField('service_shutdown_details')]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Non-working hour', 'releases') . "</td>";
      echo "<td >";
      Dropdown::showYesNo("hour_type", $this->getField('hour_type'));
      echo "</td>";
      echo "<td>" . __('Communication', 'releases') . "</td>";
      echo "<td >";
      Dropdown::showYesNo("communication", $this->getField('communication'));
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Communication type', 'releases') . "</td>";
      echo "<td >";
      $types   = ['Entity'   => 'Entity',
                  'Group'    => 'Group',
                  'Profile'  => 'Profile',
                  'User'     => 'User',
                  'Location' => 'Location'];
      $addrand = Dropdown::showItemTypes('communication_type', $types, ["id" => "communication_type", "value" => $this->getField('communication_type')]);
      echo "</td>";
      $targets = [];
      $targets = json_decode($this->getField('target'));
      //      $targets = $this->getField('target');
      echo "<td>" . _n('Target', 'Targets',
                       Session::getPluralNumber()) . "</td>";


      echo "<td id='targets'>";


      echo "</td>";
      Ajax::updateItem("targets",
                       $CFG_GLPI["root_doc"] . "/plugins/releases/ajax/changeTarget.php",
                       ['type' => $this->getField('communication_type'), 'current_type' => $this->getField('communication_type'), 'values' => $targets], true);
      Ajax::updateItemOnSelectEvent("dropdown_communication_type" . $addrand, "targets",
                                    $CFG_GLPI["root_doc"] . "/plugins/releases/ajax/changeTarget.php",
                                    ['type' => '__VALUE__', 'current_type' => $this->getField('communication_type'), 'values' => $targets], true);
      echo "</tr>";
      if ($ID == "") {
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Associate changes');
         echo "</td>";
         echo "<td>";
         $change  = new Change();
         $changes = $change->find(['entities_id' => $_SESSION['glpiactive_entity'], 'status' => Change::getNotSolvedStatusArray()]);
         $list    = [];
         foreach ($changes as $ch) {
            $list[$ch["id"]] = $ch["name"];
         }
         Dropdown::showFromArray("changes", $list, ["multiple" => true, "values" => $select_changes]);
         //      Change::dropdown([
         ////            'used' => $used,
         //         'entity' => $_SESSION['glpiactive_entity'],'condition'=>['status'=>Change::getNotSolvedStatusArray()]]);
         echo "</td>";
         echo "<td colspan='2'>";
         echo "</td>";
         echo "</tr>";
      }

      if ($ID) {
         echo "<tr  class='tab_bg_1'>";
         echo "<td colspan='4'>";
         $this->showActorsPartForm($ID, $options);
         echo "</td>";
         echo "</tr>";
      }

      if ($ID != "") {
         echo "<tr  class='tab_bg_1'>";
         echo "<td colspan='4'>";
         echo " <div class=\"container-fluid\">
                              <ul class=\"list-unstyled multi-steps\">";

         for ($i = 7; $i <= 17; $i++) {
            $class = "";
            //
            //            if ($value["ranking"] < $ranking) {
            ////                     $class = "class = active2";
            //
            //            } else
            if ($this->getField("status") == $i - 1) {
               //               $class = "class='current'";
               $class = "class='is-active'";
            }
            $name = self::getStatus($i);
            echo "<li $class>" . $name . "</li>";
         }
         echo " </ul></div>";
         echo "</td>";
         echo "</tr>";
      }

      $this->showFormButtons($options);

      return true;
   }

   //TODO create own class for own tab
   function showFinalisationTabs($ID) {
      global $CFG_GLPI;
      $this->getFromDB($ID);

      echo "<table class='tab_cadre_fixe' id='mainformtable'>";
      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      echo _n('Risk', 'Risks', 2, 'releases');
      echo "</td>";
      echo "<td>";
      echo self::getStateItem($this->getField("risk_state"));
      echo "</td>";
      echo "<td>";


      echo "<td>";
      echo _n('Rollback', 'Rollbacks', 2, 'releases');
      echo "</td>";
      echo "<td>";
      echo self::getStateItem($this->getField("rollback_state"));
      echo "</td>";

      echo "<td>";
      echo _n('Deploy Task', 'Deploy Tasks', 2, 'releases');
      echo "</td>";
      echo "<td class='left'>";
      $dtF = self::countForItem($ID, PluginReleasesDeploytask::class, 1);
      $dtT = self::countForItem($ID, PluginReleasesDeploytask::class);
      if ($dtT != 0) {
         $pourcentage = $dtF / $dtT * 100;
      } else {
         $pourcentage = 0;
      }

      echo "<div class=\"progress-circle\" data-value=\"" . round($pourcentage) . "\">
             <div class=\"progress-masque\">
                 <div class=\"progress-barre\"></div>
                 <div class=\"progress-sup50\"></div>
             </div>
            </div>";

      //      echo $dtF;
      //      echo "/";
      //      echo $dtT;
      echo "</td>";

      echo "<td>";
      echo _n('Test', 'Tests', 2, 'releases');
      echo "</td>";
      echo "<td>";
      echo self::getStateItem($this->getField("test_state"));
      echo "</td>";

      echo "</tr>";


      echo "</table>";
      $allfinish = $this->getField("risk_state")
                   && ($dtT == $dtF)
                   && $this->getField("test_state")
                   && $this->getField("rollback_state");
      $text      = "";
      if (!$allfinish) {

         $text .= '<span class="center"><i class=\'fas fa-exclamation-triangle fa-1x\' style=\'color: orange\'></i> ' . __("Care all steps are not finish !") . '</span>';
         $text .= "<br>";
         $text .= "<br>";
      }
      if ($this->getField('status') < self::FINALIZE) {
         echo '<a id="finalize" class="vsubmit"> ' . __("Finalize", 'releases') . '</a>';

         echo Html::scriptBlock(
            "$('#finalize').click(function(){
         $( '#alert-message' ).dialog( 'open' );

         });");
         //TODO
         echo "<div id='alert-message' class='tab_cadre_navigation_center' style='display:none;'>" . $text . __("production run date", "releases") . Html::showDateField("date_production", ["id" => "date_production", "maybeempty" => false, "display" => false]) . "</div>";
         $srcImg     = "fas fa-info-circle";
         $color      = "forestgreen";
         $alertTitle = _n("Information", "Informations", 1);

         echo Html::scriptBlock("var mTitle =  \"<i class='" . $srcImg . " fa-1x' style='color:" . $color . "'></i>&nbsp;" . "finalize" . " \";");
         echo Html::scriptBlock("$( '#alert-message' ).dialog({
        autoOpen: false,
        height: " . 200 . ",
        width: " . 300 . ",
        modal: true,
        open: function (){
         $(this)
            .parent()
            .children('.ui-dialog-titlebar')
            .html(mTitle);
      },
        buttons: {
         'ok': function() {
            if($(\"[name = 'date_production']\").val() == '' || $(\"[name = 'date_production']\").val() === undefined){
        
              $(\"[name = 'date_production']\").siblings(':first').css('border-color','red')
            }else{  
               var date = $(\"[name = 'date_production']\").val();
               console.log(date);
               $.ajax({
                  url:  '" . $CFG_GLPI['root_doc'] . "/plugins/releases/ajax/finalize.php',
                  data: {'id' : " . $this->getID() . ",'date' : date},
                  success: function() {
                     document.location.reload();
                  }
               });
               
            }
         
         },
         'cancel': function() {
               $( this ).dialog( 'close' );
          }
      },
      
    })");


      }

   }

   /**
    * Return a field Value if exists
    *
    * @param string $field field name
    *
    * @return mixed value of the field / false if not exists
    **/
   function getField($field) {

      if ($field == "content") {
         return $this->fields["service_shutdown_details"];
      } else {
         return parent::getField($field);
      }
      if (array_key_exists($field, $this->fields)) {
         return $this->fields[$field];
      }
      return NOT_AVAILABLE;
   }

   /**
    * @return mixed
    */
   function getNameAlert() {
      return $this->fields["name"];
   }

   /**
    * @return mixed
    */
   function getContentAlert() {
      return $this->fields["service_shutdown_details"];
   }


   /**
    * @param $state
    *
    * @return string
    */
   public static function getStateItem($state) {
      switch ($state) {
         case 0:
            //            return __("Waiting","releases");
            return "<span><i class=\"fas fa-4x fa-hourglass-half\"></i></span>";
            break;
         case 1:
            //            return __("Done");
            return "<span><i class=\"fas fa-4x fa-check\"></i></span>";
            break;
      }
   }

   /**
    * Displays the form at the top of the timeline.
    * Includes buttons to add items to the timeline, new item form, and approbation form.
    *
    * @param integer $rand random value used by JavaScript function names
    *
    * @return void
    * @since 9.4.0
    *
    */
   function showTimelineForm($rand) {
      global $CFG_GLPI;

      $objType    = static::getType();
      $foreignKey = static::getForeignKeyField();

      //check sub-items rights
      $tmp       = [$foreignKey => $this->getID()];
      $riskClass = "PluginReleasesRisk";
      $risk      = new $riskClass;
      $risk->getEmpty();
      $risk->fields['itemtype'] = $objType;
      $risk->fields['items_id'] = $this->getID();


      $rollbackClass = "PluginReleasesRollback";
      $rollback      = new $rollbackClass;
      $rollback->getEmpty();
      $rollback->fields['itemtype'] = $objType;
      $rollback->fields['items_id'] = $this->getID();

      $taskClass = "PluginReleasesDeploytask";
      $task      = new $taskClass;
      $task->getEmpty();
      $task->fields['itemtype'] = $objType;
      $task->fields['items_id'] = $this->getID();

      $testClass = "PluginReleasesTest";
      $test      = new $testClass;
      $test->getEmpty();
      $test->fields['itemtype'] = $objType;
      $test->fields['items_id'] = $this->getID();

      $canadd_risk = $risk->can(-1, CREATE, $tmp) && !in_array($this->fields["status"],
                                                               array_merge($this->getSolvedStatusArray(), $this->getClosedStatusArray()));

      $canadd_rollback = $rollback->can(-1, CREATE, $tmp) && !in_array($this->fields["status"],
                                                                       array_merge($this->getSolvedStatusArray(), $this->getClosedStatusArray()));

      $canadd_task = $task->can(-1, CREATE, $tmp) && !in_array($this->fields["status"],
                                                               array_merge($this->getSolvedStatusArray(), $this->getClosedStatusArray()));

      $canadd_test = $test->can(-1, CREATE, $tmp) && !in_array($this->fields["status"], $this->getSolvedStatusArray());

      // javascript function for add and edit items
      $objType    = self::getType();
      $foreignKey = self::getForeignKeyField();

      echo "<script type='text/javascript' >
      function change_task_state(items_id, target, itemtype) {
         $.post('" . $CFG_GLPI["root_doc"] . "/plugins/releases/ajax/timeline.php',
                {'action':     'change_task_state',
                  'items_id':   items_id,
                  'itemtype':   itemtype,
                  'parenttype': '$objType',
                  '$foreignKey': " . $this->fields['id'] . "
                })
                .done(function(response) {
                  $(target).removeClass('state_1 state_2')
                           .addClass('state_'+response.state)
                           .attr('title', response.label);
                });
      }

      function viewEditSubitem" . $this->fields['id'] . "$rand(e, itemtype, items_id, o, domid) {
               domid = (typeof domid === 'undefined')
                         ? 'viewitem" . $this->fields['id'] . $rand . "'
                         : domid;
               var target = e.target || window.event.srcElement;
               if (target.nodeName == 'a') return;
               if (target.className == 'read_more_button') return;

               var _eltsel = '[data-uid='+domid+']';
               var _elt = $(_eltsel);
               _elt.addClass('edited');
               $(_eltsel + ' .displayed_content').hide();
               $(_eltsel + ' .cancel_edit_item_content').show()
                                                        .click(function() {
                                                            $(this).hide();
                                                            _elt.removeClass('edited');
                                                            $(_eltsel + ' .edit_item_content').empty().hide();
                                                            $(_eltsel + ' .displayed_content').show();
                                                        });
               $(_eltsel + ' .edit_item_content').show()
                                                 .load('" . $CFG_GLPI["root_doc"] . "/plugins/releases/ajax/timeline.php',
                                                       {'action'    : 'viewsubitem',
                                                        'type'      : itemtype,
                                                        'parenttype': '$objType',
                                                        '$foreignKey': " . $this->fields['id'] . ",
                                                        'id'        : items_id
                                                       });
      };
      </script>";

      if (!$canadd_risk && !$canadd_rollback && !$canadd_task && !$canadd_test && !$this->canReopen()) {
         return false;
      }

      echo "<script type='text/javascript' >\n
//      $(document).ready(function() {
//                $('.ajax_box').show();
//      });
      function viewAddSubitem" . $this->fields['id'] . "$rand(itemtype) {\n";

      $params = ['action'     => 'viewsubitem',
                 'type'       => 'itemtype',
                 'parenttype' => $objType,
                 $foreignKey  => $this->fields['id'],
                 'id'         => -1];
      $out    = Ajax::updateItemJsCode("viewitem" . $this->fields['id'] . "$rand",
                                       $CFG_GLPI["root_doc"] . "/plugins/releases/ajax/timeline.php",
                                       $params, "", false);
      echo str_replace("\"itemtype\"", "itemtype", $out);
      echo "};
      ";

      echo "</script>\n";
//TODO on launch - display only risks and hide new form on click object
      //show choices
      echo "<div class='timeline_form'>";
      echo "<div class='filter_timeline_release'>";
      echo "<ul class='timeline_choices'>";

      $release = new $objType();
      $release->getFromDB($this->getID());

      echo "<li class='risk'>";
      echo "<a href='#' data-type='risk' title='" . $riskClass::getTypeName(2) .
           "'><i class='fas fa-bug'></i>" . $riskClass::getTypeName(2) . " (" . $riskClass::countForItem($release) . ")</a></li>";
      if ($canadd_risk) {
         echo "<i class='fas fa-plus-circle pointer' onclick='" . "javascript:viewAddSubitem" . $this->fields['id'] . "$rand(\"$riskClass\");' style='margin-right: 10px;margin-left: -5px;'></i>";
      }

      $style = "color:firebrick;";
      $fa = "fa-times-circle";
      if ($riskClass::countForItem($release) == $riskClass::countDoneForItem($release)) {
         $style = "color:forestgreen;";
         $fa = "fa-check-circle";
      }
      echo "<i class='fas $fa' style='margin-right: 10px;$style'></i>";

      echo "<li class='rollback'>";
      echo "<a href='#' data-type='rollback' title='" . $rollbackClass::getTypeName(2) .
           "'><i class='fas fa-undo-alt'></i>" . $rollbackClass::getTypeName(2) . " (" . $rollbackClass::countForItem($release) . ")</a></li>";
      if ($canadd_rollback) {
         echo "<i class='fas fa-plus-circle pointer' onclick='" . "javascript:viewAddSubitem" . $this->fields['id'] . "$rand(\"$rollbackClass\");' style='margin-right: 10px;margin-left: -5px;'></i>";
      }

      $style = "color:firebrick;";
      $fa = "fa-times-circle";
      if ($rollbackClass::countForItem($release) == $rollbackClass::countDoneForItem($release)) {
         $style = "color:forestgreen;";
         $fa = "fa-check-circle";
      }
      echo "<i class='fas $fa' style='margin-right: 10px;$style'></i>";

      echo "<li class='task'>";
      echo "<a href='#' data-type='task' title='" . _n('Deploy task', 'Deploy tasks', 2, 'releases') .
           "'><i class='fas fa-check-square'></i>" . _n('Deploy task', 'Deploy tasks', 2, 'releases') . " (" . $taskClass::countForItem($release) . ")</a></li>";
      if ($canadd_task) {
         echo "<i class='fas fa-plus-circle pointer'  onclick='" . "javascript:viewAddSubitem" . $this->fields['id'] . "$rand(\"$taskClass\");' style='margin-right: 10px;margin-left: -5px;'></i>";
      }

      $style = "color:firebrick;";
      $fa = "fa-times-circle";
      if ($taskClass::countForItem($release) == $taskClass::countDoneForItem($release)) {
         $style = "color:forestgreen;";
         $fa = "fa-check-circle";
      }
      echo "<i class='fas $fa' style='margin-right: 10px;$style'></i>";

      echo "<li class='test'>";
      echo "<a href='#' data-type='test' title='" . $testClass::getTypeName(2) .
           "'><i class='fas fa-check'></i>" . $testClass::getTypeName(2) . " (" . $testClass::countForItem($release) . ")</a></li>";
      if ($canadd_test) {
         echo "<i class='fas fa-plus-circle pointer' onclick='" . "javascript:viewAddSubitem" . $this->fields['id'] . "$rand(\"$testClass\");' style='margin-right: 10px;margin-left: -5px;'></i>";
      }
      $style = "color:firebrick;";
      $fa = "fa-times-circle";
      if ($testClass::countForItem($release) == $testClass::countDoneForItem($release)) {
         $style = "color:forestgreen;";
         $fa = "fa-check-circle";
      }
      echo "<i class='fas $fa' style='margin-right: 10px;$style'></i>";
      echo "</ul>"; // timeline_choices
      echo "</div>";

      echo "<div class='clear'>&nbsp;</div>";

      echo "</div>"; //end timeline_form

      echo "<div class='ajax_box' id='viewitem" . $this->fields['id'] . "$rand'></div>\n";
   }


   /**
    * Displays the timeline filter buttons
    *
    * @return void
    * @since 9.4.0
    *
    */
   function filterTimeline() {

      echo "<div class='filter_timeline'>";
      echo "<h3>" . __("Timeline filter") . " : </h3>";
      echo "<ul>";

      $riskClass = "PluginReleasesRisk";
      echo "<li><a href='#' class='fas fa-bug pointer' data-type='risk' title='" . $riskClass::getTypeName(2) .
           "'><span class='sr-only'>" . $riskClass::getTypeName(2) . "</span></a></li>";
      $rollbackClass = "PluginReleasesRollback";
      echo "<li><a href='#' class='fas fa-undo-alt pointer' data-type='rollback' title='" . $rollbackClass::getTypeName(2) .
           "'><span class='sr-only'>" . $rollbackClass::getTypeName(2) . "</span></a></li>";
      $taskClass = "PluginReleasesDeploytask";
      echo "<li><a href='#' class='fas fa-check-square pointer' data-type='task' title='" . _n('Deploy task', 'Deploy tasks', 2, 'releases') .
           "'><span class='sr-only'>" . _n('Deploy task', 'Deploy tasks', 2, 'releases') . "</span></a></li>";
      $testClass = "PluginReleasesTest";
      echo "<li><a href='#' class='fas fa-check pointer' data-type='test' title='" . $testClass::getTypeName(2) .
           "'><span class='sr-only'>" . $testClass::getTypeName(2) . "</span></a></li>";
      echo "<li><a href='#' class='fa fa-ban pointer' data-type='reset' title=\"" . __s("Reset display options") .
           "\"><span class='sr-only'>" . __('Reset display options') . "</span></a></li>";
      echo "</ul>";
      echo "</div>";

      echo "<script type='text/javascript'>$(function() {filter_timeline();});</script>";
      echo "<script type='text/javascript'>$(function() {filter_timeline_release();});</script>";
   }

   /**
    * Displays the timeline of items for this ITILObject
    *
    * @param integer $rand random value used by div
    *
    * @return void
    * @since 9.4.0
    *
    */
   function showTimeLine($rand) {
      global $CFG_GLPI, $autolink_options;

      $user     = new User();
      $pics_url = $CFG_GLPI['root_doc'] . "/pics/timeline";
      $timeline = $this->getTimelineItems();

      $autolink_options['strip_protocols'] = false;

      $objType    = static::getType();
      $foreignKey = static::getForeignKeyField();

      //display timeline
      echo "<div class='timeline_history'>";

      static::showTimelineHeader();

      $timeline_index = 0;

      foreach ($timeline as $item) {

         if ($obj = getItemForItemtype($item['type'])) {
            $obj->fields = $item['item'];
         } else {
            $obj = $item;
         }

         if (is_array($obj)) {
            $item_i = $obj['item'];
         } else {
            $item_i = $obj->fields;
         }

         $date = "";
         if (isset($item_i['date'])) {
            $date = $item_i['date'];
         } else if (isset($item_i['date_mod'])) {
            $date = $item_i['date_mod'];
         }

         // set item position depending on field timeline_position
         $user_position = 'left'; // default position
         //         if (isset($item_i['timeline_position'])) {
         //            switch ($item_i['timeline_position']) {
         //               case self::TIMELINE_LEFT:
         //                  $user_position = 'left';
         //                  break;
         //               case self::TIMELINE_MIDLEFT:
         //                  $user_position = 'left middle';
         //                  break;
         //               case self::TIMELINE_MIDRIGHT:
         //                  $user_position = 'right middle';
         //                  break;
         //               case self::TIMELINE_RIGHT:
         //                  $user_position = 'right';
         //                  break;
         //            }
         //         }


         echo "<div class='h_item $user_position'>";

         echo "<div class='h_info'>";

         echo "<div class='h_date'><i class='far fa-clock'></i>" . Html::convDateTime($date) . "</div>";
         if ($item_i['users_id'] !== false) {
            echo "<div class='h_user'>";
            if (isset($item_i['users_id']) && ($item_i['users_id'] != 0)) {
               $user->getFromDB($item_i['users_id']);

               echo "<div class='tooltip_picture_border'>";
               echo "<img class='user_picture' alt=\"" . __s('Picture') . "\" src='" .
                    User::getThumbnailURLForPicture($user->fields['picture']) . "'>";
               echo "</div>";

               echo "<span class='h_user_name'>";
               $userdata = getUserName($item_i['users_id'], 2);
               echo $user->getLink() . "&nbsp;";
               echo Html::showToolTip(
                  $userdata["comment"],
                  ['link' => $userdata['link']]
               );
               echo "</span>";
            } else {
               echo __("Requester");
            }
            echo "</div>"; // h_user
         }

         echo "</div>"; //h_info

         $domid     = "viewitem{$item['type']}{$item_i['id']}";
         $randdomid = $domid . $rand;
         $domid     = Toolbox::slugify($domid);

         $fa    = null;
         $class = "h_content";
         $class .= " {$item['type']::getCssClass()}";


         //         $class .= " {$item_i['state']}";


         echo "<div class='$class' id='$domid' data-uid='$randdomid'>";
         if ($fa !== null) {
            echo "<i class='solimg fa fa-$fa fa-5x'></i>";
         }
         if (isset($item_i['can_edit']) && $item_i['can_edit']) {
            echo "<div class='edit_item_content'></div>";
            echo "<span class='cancel_edit_item_content'></span>";
         }
         echo "<div class='displayed_content'>";
         echo "<div class='h_controls'>";
         if ($item_i['can_edit']
             && !in_array($this->fields['status'], $this->getClosedStatusArray())
         ) {
            // merge/split icon

            // edit item
            echo "<span class='far fa-edit control_item' title='" . __('Edit') . "'";
            echo "onclick='javascript:viewEditSubitem" . $this->fields['id'] . "$rand(event, \"" . $item['type'] . "\", " . $item_i['id'] . ", this, \"$randdomid\")'";
            echo "></span>";
         }

         echo "</div>";
         if (isset($item_i['content'])) {
            $content = "<h2>" . $item_i['name'] . "  </h2>" . $item_i['content'];
            $content = Toolbox::getHtmlToDisplay($content);
            $content = autolink($content, false);

            $long_text = "";
            if ((substr_count($content, "<br") > 30) || (strlen($content) > 2000)) {
               $long_text = "long_text";
            }

            echo "<div class='item_content $long_text'>";
            echo "<p>";
            if (isset($item_i['state'])) {
               $onClick = "onclick='change_task_state(" . $item_i['id'] . ", this,\"" . $item['type'] . "\")'";
               if (!$item_i['can_edit']) {
                  $onClick = "style='cursor: not-allowed;'";
               }
               echo "<span class='state state_" . $item_i['state'] . "'
                           $onClick
                           title='" . Planning::getState($item_i['state']) . "'>";
               echo "</span>";
            }
            echo "</p>";

            echo "<div class='rich_text_container'>";
            $richtext = Html::setRichTextContent('', $content, '', true);
            $richtext = Html::replaceImagesByGallery($richtext);
            echo $richtext;
            echo "</div>";

            if (!empty($long_text)) {
               echo "<p class='read_more'>";
               echo "<a class='read_more_button'>.....</a>";
               echo "</p>";
            }
            echo "</div>";
         }

         echo "<div class='b_right'>";

         if (isset($item_i['plugin_releases_typedeploytasks_id'])
             && !empty($item_i['plugin_releases_typedeploytasks_id'])) {
            echo Dropdown::getDropdownName("glpi_plugin_releases_typedeploytasks", $item_i['plugin_releases_typedeploytasks_id']) . "<br>";
         }
         if (isset($item_i['plugin_releases_typerisks_id'])
             && !empty($item_i['plugin_releases_typerisks_id'])) {
            echo Dropdown::getDropdownName("glpi_plugin_releases_typerisks", $item_i['plugin_releases_typerisks_id']) . "<br>";
         }
         if (isset($item_i['plugin_releases_typetests_id'])
             && !empty($item_i['plugin_releases_typetests_id'])) {
            echo Dropdown::getDropdownName("glpi_plugin_releases_typetests", $item_i['plugin_releases_typetests_id']) . "<br>";
         }
         if (isset($item_i['plugin_releases_risks_id'])
             && !empty($item_i['plugin_releases_risks_id'])) {
            echo __("Associated with") . " ";
            echo Dropdown::getDropdownName("glpi_plugin_releases_risks", $item_i['plugin_releases_risks_id']) . "<br>";
         }

         if (isset($item_i['actiontime'])
             && !empty($item_i['actiontime'])) {
            echo "<span class='actiontime'>";
            echo Html::timestampToString($item_i['actiontime'], false);
            echo "</span>";
         }
         if (isset($item_i['begin'])) {
            echo "<span class='planification'>";
            echo Html::convDateTime($item_i["begin"]);
            echo " &rArr; ";
            echo Html::convDateTime($item_i["end"]);
            echo "</span>";
         }

         if (isset($item_i['users_id_editor'])
             && $item_i['users_id_editor'] > 0) {
            echo "<div class='users_id_editor' id='users_id_editor_" . $item_i['users_id_editor'] . "'>";
            $user->getFromDB($item_i['users_id_editor']);
            $userdata = getUserName($item_i['users_id_editor'], 2);
            if (isset($item_i['date_mod']))
               echo sprintf(
                  __('Last edited on %1$s by %2$s'),
                  Html::convDateTime($item_i['date_mod']),
                  $user->getLink()
               );
            echo Html::showToolTip($userdata["comment"],
                                   ['link' => $userdata['link']]);
            echo "</div>";
         }

         echo "</div>"; // b_right

         echo "</div>"; // displayed_content
         echo "</div>"; //end h_content

         echo "</div>"; //end  h_info

         $timeline_index++;
      }
      // end timeline
      echo "</div>"; // h_item $user_position
   }


   function getTimelineItems() {

      $objType    = self::getType();
      $foreignKey = self::getForeignKeyField();

      $timeline = [];

      $riskClass     = 'PluginReleasesRisk';
      $risk_obj      = new $riskClass;
      $rollbackClass = 'PluginReleasesRollback';
      $rollback_obj  = new $rollbackClass;
      $taskClass     = 'PluginReleasesDeploytask';
      $task_obj      = new $taskClass;
      $testClass     = 'PluginReleasesTest';
      $test_obj      = new $testClass;

      //checks rights
      $restrict_risk = $restrict_rollback = $restrict_task = $restrict_test = [];
      //      $restrict_risk['itemtype'] = static::getType();
      //      $restrict_risk['items_id'] = $this->getID();

      //add risks to timeline
      if ($risk_obj->canview()) {
         $risks = $risk_obj->find([$foreignKey => $this->getID()] + $restrict_risk, ['date_mod DESC', 'id DESC']);
         foreach ($risks as $risks_id => $risk) {
            $risk_obj->getFromDB($risks_id);
            $risk['can_edit']                                   = $risk_obj->canUpdateItem();
            $timeline[$risk['date_mod'] . "_risk_" . $risks_id] = ['type'     => $riskClass,
                                                                   'item'     => $risk,
                                                                   'itiltype' => 'Risk'];
         }
      }

      if ($rollback_obj->canview()) {
         $rollbacks = $rollback_obj->find([$foreignKey => $this->getID()] + $restrict_rollback, ['date_mod DESC', 'id DESC']);
         foreach ($rollbacks as $rollbacks_id => $rollback) {
            $rollback_obj->getFromDB($rollbacks_id);
            $rollback['can_edit']                                       = $rollback_obj->canUpdateItem();
            $timeline[$risk['date_mod'] . "_rollback_" . $rollbacks_id] = ['type'     => $rollbackClass,
                                                                           'item'     => $rollback,
                                                                           'itiltype' => 'Rollback'];
         }
      }

      if ($task_obj->canview()) {
         //         $tasks = $task_obj->find([$foreignKey => $this->getID()] + $restrict_task);
         $tasks = $task_obj->find([$foreignKey => $this->getID()] + $restrict_task, ['level DESC']);
         foreach ($tasks as $tasks_id => $task) {
            $task_obj->getFromDB($tasks_id);
            $task['can_edit']                                                      = $task_obj->canUpdateItem();
            $rand                                                                  = mt_rand();
            $timeline["task" . $task_obj->getField('level') . "$tasks_id" . $rand] = ['type'     => $taskClass,
                                                                                      'item'     => $task,
                                                                                      'itiltype' => 'Task'];
         }
      }

      if ($test_obj->canview()) {
         $tests = $test_obj->find([$foreignKey => $this->getID()] + $restrict_test, ['date_mod DESC', 'id DESC']);
         foreach ($tests as $tests_id => $test) {
            $test_obj->getFromDB($tests_id);
            $test['can_edit']                                   = $test_obj->canUpdateItem();
            $timeline[$risk['date_mod'] . "_test_" . $tests_id] = ['type'     => $testClass,
                                                                   'item'     => $test,
                                                                   'itiltype' => 'test'];
         }
      }

      //reverse sort timeline items by key (date)
      ksort($timeline);

      return $timeline;
   }

   /**
    * Dropdown of releases items state
    *
    * @param $name   select name
    * @param $value  default value (default '')
    * @param $display  display of send string ? (true by default)
    * @param $options  options
    **/
   static function dropdownStateItem($name, $value = '', $display = true, $options = []) {

      $values = [static::TODO => __('To do'),
                 static::DONE => __('Done')];

      return Dropdown::showFromArray($name, $values, array_merge(['value'   => $value,
                                                                  'display' => $display], $options));
   }

   /**
    * Dropdown of releases state
    *
    * @param $name   select name
    * @param $value  default value (default '')
    * @param $display  display of send string ? (true by default)
    * @param $options  options
    **/
   static function dropdownState($name, $value = '', $display = true, $options = []) {

      $values = [static::TODO       => __('To do'),
                 static::DONE       => __('Done'),
                 static::PROCESSING => __('Processing'),
                 static::WAITING    => __("Waiting"),
                 static::LATE       => __("Late"),
                 static::DEF        => __("Default"),
      ];

      return Dropdown::showFromArray($name, $values, array_merge(['value'   => $value,
                                                                  'display' => $display], $options));
   }

   //TODO replace by update objects - tests...
   function showStateItem($field = "", $text = "", $state) {
      global $CFG_GLPI;

      echo "<div colspan='4' class='center'>" . $text . "</div>";
      echo "<div id='fakeupdate'></div>";

      echo "<div class='center'>";
      $rand = mt_rand();
      Dropdown::showYesNo($field, $this->getField($field), -1, ["rand" => $rand]);
      $params = ['value'                       => "__VALUE__",
                 "field"                       => $field,
                 "plugin_releases_releases_id" => $this->getID(),
                 'state'                       => $state];
      Ajax::updateItemOnSelectEvent("dropdown_$field$rand", "fakeupdate", $CFG_GLPI["root_doc"] . "/plugins/releases/ajax/changeitemstate.php", $params);

      echo "</div>";

   }

   static function showCreateRelease($item) {

      $item_t    = new PluginReleasesReleasetemplate();
      $dbu       = new DbUtils();
      $condition = $dbu->getEntitiesRestrictCriteria($item_t->getTable());
      PluginReleasesReleasetemplate::dropdown(["comments"   => false,
                                               "addicon"    => false,
                                               "emptylabel" => __("From this change", "releases"),
                                               "name"       => "releasetemplates_id"] + $condition);
      $url = PluginReleasesRelease::getFormURL();
      echo "<a  id='link' href='$url?changes_id=" . $item->getID() . "'>";
      $url    = $url . "?changes_id=" . $item->getID() . "&template_id=";
      $script = "
      var link = function (id,linkurl) {
         var link = linkurl+id;
         $(\"a#link\").attr(\"href\", link);
      };
      $(\"select[name='releasetemplates_id']\").change(function() {
         link($(\"select[name='releasetemplates_id']\").val(),'$url');
         });";


      echo Html::scriptBlock('$(document).ready(function() {' . $script . '});');
      echo "<br/><br/>";
      echo __("Create a release", 'releases');
      echo "</a>";
      //      echo "<form name='form' method='post' action='".$this->getFormURL()."'  enctype=\"multipart/form-data\">";
      //      echo Html::hidden("changes_id",["value"=>$item->getID()]);
      ////      echo '<a class="vsubmit"> '.__("Create a releases from this change",'release').'</a>';
      //      echo Html::submit(__("Create a release from this change",'releases'), ['name' => 'createRelease']);
      //      Html::closeForm();
   }

   function getLinkedItems(bool $addNames = true): array {
      global $DB;

      $assets = $DB->request([
                                'SELECT' => ['itemtype', 'items_id'],
                                'FROM'   => 'glpi_plugin_releases_releases_items',
                                'WHERE'  => ['plugin_releases_releases_id' => $this->getID()]
                             ]);

      $assets = iterator_to_array($assets);

      if ($addNames) {
         foreach ($assets as $key => $asset) {
            if (!class_exists($asset['itemtype'])) {
               //ignore if class does not exists (maybe a plugin)
               continue;
            }
            /** @var CommonDBTM $item */
            $item = new $asset['itemtype'];
            $item->getFromDB($asset['items_id']);

            // Add name
            $assets[$key]['name'] = $item->fields['name'];
         }
      }

      return $assets;
   }

   /**
    * Should impact tab be displayed? Check if there is a valid linked item
    *
    * @return boolean
    */
   protected function hasImpactTab() {
      foreach ($this->getLinkedItems() as $linkedItem) {
         $class = $linkedItem['itemtype'];
         if (Impact::isEnabled($class) && Session::getCurrentInterface() === "central") {
            return true;
         }
      }
      return false;
   }

   /**
    * @return array
    */
   static function getMenuContent() {

      $menu['title']           = self::getMenuName(2);
      $menu['page']            = self::getSearchURL(false);
      $menu['links']['search'] = self::getSearchURL(false);

      $menu['links']['template'] = "/plugins/releases/front/releasetemplate.php";
      $menu['icon']              = static::getIcon();
      if (self::canCreate()) {
         $dbu       = new DbUtils();
         $template  = new PluginReleasesReleasetemplate();
         $condition = $dbu->getEntitiesRestrictCriteria($template->getTable());
         $templates = $template->find($condition);
         if (empty($templates)) {
            $menu['links']['add'] = self::getFormURL(false);
         } else {
            $menu['links']['add'] = PluginReleasesReleasetemplate::getSearchURL(false);
         }
      }


      return $menu;
   }


   static function getIcon() {
      return "fas fa-tags";
   }

   static function getDefaultValues($entity = 0) {
      // TODO: Implement getDefaultValues() method.
   }

   static function isAllowedStatus($old,$new){
      if($old != self::CLOSED && $old != self::REVIEW){
         return true;
      }
      return false;
   }
}

