<?php
/* example content retrieval functions
 *
 * The client will need to be invoked with an api_id and api_secret that have read/write access to the show in question.  
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



function get_all_show_episode_assets($show_id, $client, $order = 'ASC', $window = 'all', $type = 'all') {
  /* this function uses the client object to retrieve the list of seasons, then
   * go through each season to retrieve the episodes, and output each asset for each
   * episode.  */
  $seasons = $client->get_show_seasons($show_id);
  if (empty($seasons[0])) {
    return;
  }
  $output = array();
  foreach ($seasons as $season) {
    $season_content = $season;
    echo "\n starting season " . $season["attributes"]["ordinal"] ."\n";
    $season_id = $season['id'];
    $raw_season = $client->get_season_episodes($season_id);
    foreach ($raw_season as $episode) {
      $episode_content = $episode;
      $this_ep_id = $episode['id'];
      $assets = $client->get_episode_assets($this_ep_id, "all", "all", array("platform-slug" => "partnerplayer"));
      $episode_content['assets'] = $assets;
      $season_content['episodes'][] = $episode_content;
    }
    $output['seasons'][] = $season_content;
  }
  return $output;
}

