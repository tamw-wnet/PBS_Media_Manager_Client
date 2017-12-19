<?php
/* example content retrieval functions
 *
 * The client will need to be invoked with an api_id and api_secret that has read access to the show in question.  
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */


require_once('../class-PBS-Media-Manager-API-Client.php');



function dump_all_assets($client, $show_slug, $page=1) {
  /* this function uses the client object to page through all assets and dump the output 
   * to a file with the name of the show slug */

  $filename = $show_slug . ".json";
  echo "\nStarting export to $filename\n";
  $count = $page;

  while ($raw_assets = $client->get_assets( array('show-slug' => $show_slug, 'platform-slug' => 'partnerplayer', 'sort' => 'premiered_on', 'page' => $count) ) ) {
    if (!empty($raw_assets['errors'])) {
      // retry once
      $raw_assets = $client->get_assets( array('show-slug' => $show_slug, 'platform-slug' => 'partnerplayer', 'sort' => 'premiered_on', 'page' => $count) );
      if (!empty($raw_assets['errors'])) {
        echo "ABORTED: error at page $count -- restart this script with page as the 3rd arg\n";
      }
    }
    if (empty($raw_assets[0])) {
      break;
    }
    foreach ($raw_assets as $asset) {
      $tp_media_id = $asset['attributes']['legacy_tp_media_id'];
      $output = array('tp_media_id' => $tp_media_id, 'asset' => $asset);
      $json = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
      file_put_contents($filename, $json . "\n", FILE_APPEND);
    }
    $count++;
    echo $count . " ";
  }
  echo "\nExport complete to $filename\n";
}

