<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 databases plugin for GLPI
 Copyright (C) 2009-2016 by the databases Development Team.

 https://github.com/InfotelGLPI/databases
 -------------------------------------------------------------------------

 LICENSE

 This file is part of databases.

 databases is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 databases is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with databases. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

if (strpos($_SERVER['PHP_SELF'], "finalize.php")) {
   $AJAX_INCLUDE = 1;
   include('../../../inc/includes.php');
   header("Content-Type: text/html; charset=UTF-8");
   Html::header_nocache();
}

Session::checkCentralAccess();


if (isset($_REQUEST["id"]) && isset($_REQUEST["date"]) ) {
   $release = new PluginReleasesRelease();
   $val = [];
   $val['id'] = $_REQUEST["id"];
   $val['status'] = PluginReleasesRelease::FINALIZE;
   $release->update($val);

   $review = new PluginReleasesReview();

   if($review->getFromDBByCrit(["plugin_releases_releases_id"=> $_REQUEST["id"]])){
      $val=[];
      $val['id'] = $review->getID();
      $val['real_date_release'] = $_REQUEST["date"];
      $val['date_lock'] = 1;

      $review->update($val);
   }else{
      $val=[];
      $val['plugin_releases_releases_id'] = $_REQUEST["id"];
      $val['real_date_release'] = $_REQUEST["date"];
      $val['date_lock'] = 1;

      $review->add($val);
   }

   echo "ok";
}
