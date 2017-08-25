<?php
/* example bulk migration functions
 *
 * Functions here for converting 'specials' to 'episodes' and assigning 'extras' to 'episodes'.
 * Invoke them by passing a show_id and a client object, like so
 * $client = new PBS_Media_Manager_API_Client($api_id, $api_secret, $baseurl);
 * $show_id = 'some-show-id-or-slug';
 * convert_specials_to_episodes($show_id, $client);
 * assign_extras_to_episodes($show_id, $client);
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


require('../class-PBS-Media-Manager-API-Client.php');


function assign_extras_to_episodes($show_id, $client) {
  /* this function uses the client object to retrieve the list of 'extras' that are 
   * assigned to the show_id  -- assets that are assigned to that show
   * but NOT assigned to any specific season, special, or episode -- 
   * and attempts to assign it to an episode with the same premiered_on value.
   * 
   * The function assumes year-based seasons.  
   *
   * If no matching episode or year is found, the function echos a notice.  
   *
   * Because the function only looks at 'unassigned' assets, it can be 
   * run multiple times on the same 'show' as episodes are created to re-attempt the match.  */
  $curr_year = false;
  $curr_year_id = '';
  $curr_episodes = false;
  $count = 1;
  $extras = array();
  while ($raw_extras = $client->get_show_assets($show_id, 'all', 'all', array('sort' => 'premiered_on', 'page' => $count))) {
    // load up the entire list into a single array of ids and titles, because paging will change as they're processed
    if (empty($raw_extras[0])) {
      break;
    }
    foreach ($raw_extras as $asset) {
      $extras[] = array('id' => $asset['id'], 'title' => $asset['attributes']['title'], 'premiered_on' => $asset['attributes']['premiered_on']);
    }
    echo count($extras) . " ";
    $count++;
  }

  if (empty($extras)) {
    return;
  }
  echo " extras found.\n"; 
  foreach ($extras as $asset) {
    $thisdate = $asset['premiered_on'];
    $date = explode('-', $thisdate); // date is formatted yyyy-mm-dd
    $year  = $date[0];
    if ($year != $curr_year) {
      echo 'getting season ' . $year . "\n";
      // get a new seasons worth of episodes to select from
      $year_id = get_season_by_ordinal($year, $show_id, $client);
      if (!$year_id || !empty($year_id['errors'])) {
        echo $year_id['errors'];
        echo('no season for ' . $year . " so skipping\n");
        continue;
      }
      $curr_year = $year;
      $curr_year_id = $year_id;
      // repopulate the current season episode array since we're in a different season
      unset($curr_episodes);
      unset($raw_season); 
      $raw_season = $client->get_season_episodes($curr_year_id);
      $curr_episodes = array();
      foreach ($raw_season as $episode) {
        $this_ep_date = $episode['attributes']['premiered_on']; 
        $this_ep_id = $episode['id'];
        if (!empty($curr_episodes[$this_ep_date])) {
          echo 'duplicate episode for ' . $this_ep_date . ': ' . $curr_episodes[$this_ep_date]['title'] . ' :  ' . $episode['attributes']['title'] . "\n";
        } else {
          $curr_episodes[$this_ep_date] = array('id' => $this_ep_id, 'title' => $episode['attributes']['title']);
        }
      }
    } 
    if (empty($curr_episodes[$thisdate])) {
      echo 'no episode found for ' . $thisdate . " " . $asset['title'] . "\n";
    } else {
      echo "assigning " . $asset['title'] . " to " .$thisdate. "\n";
      $response = $client->update_object($asset['id'], 'asset', array("episode" => $curr_episodes[$thisdate]['id']) );
       
      if (!empty($response['errors'])) {
        print_r($response['errors']);
        // try again
        echo "\n Retrying " . $asset['title'] . "\n";
        $response = $client->update_object($asset['id'], 'asset', array("episode" => $curr_episodes[$thisdate]['id']) );
        if (!empty($response['errors'])) {
          die();
        }
      }
      unset($response);
      // give the PBS server a second to catch its breath
      sleep(1);
      
    }
  }

}


function assign_extras_to_episodes_by_season($show_id, $client) {
  /* this function uses the client object to retrieve the list of 'extras' that are
   * assigned to the seasons of a show_id  -- assets that are assigned to individual 
   * seasons for that show --
   * and attempts to assign it to an episode with the same premiered_on value.
   *
   * The function assumes year-based seasons.
   *
   * If no matching episode or year is found, the function echos a notice.
   *
   * Because the function only looks at 'unassigned' assets, it can be
   * run multiple times on the same 'show' as episodes are created to re-attempt the match.  */
  $seasons = $client->get_show_seasons($show_id);
  if (empty($seasons[0])) {
    return;
  }
  foreach ($seasons as $season) {
    echo "\n starting season " . $season["attributes"]["ordinal"] ."\n";
    $season_id = $season['id'];
    $extras = array();
    $raw_extras = $client->get_season_assets($season_id, 'all', 'all', array('sort' => 'premiered_on')); 
    if (empty($raw_extras[0])) {
      continue;
    }
    foreach ($raw_extras as $asset) {
      $extras[] = array('id' => $asset['id'], 'title' => $asset['attributes']['title'], 'premiered_on' => $asset['attributes']['premiered_on']);
    }
    $raw_season = $client->get_season_episodes($season_id);
    $curr_episodes = array();
    foreach ($raw_season as $episode) {
      $this_ep_date = $episode['attributes']['premiered_on'];
      $this_ep_id = $episode['id'];
      if (!empty($curr_episodes[$this_ep_date])) {
        echo 'duplicate episode for ' . $this_ep_date . ': ' . $curr_episodes[$this_ep_date]['title'] . ' :  ' . $episode['attributes']['title'] . "\n";
      } else {
        $curr_episodes[$this_ep_date] = array('id' => $this_ep_id, 'title' => $episode['attributes']['title']);
      }
    }
    foreach ($extras as $asset) {
      $thisdate = $asset['premiered_on'];
      if (empty($curr_episodes[$thisdate])) {
        echo 'no episode found for ' . $thisdate . " " . $asset['title'] . "\n";
        continue;
      }
      echo "assigning " . $asset['title'] . " to " .$thisdate. "\n";
      $response = $client->update_object($asset['id'], 'asset', array("episode" => $curr_episodes[$thisdate]['id']) );

      if (!empty($response['errors'])) {
        print_r($response['errors']);
        // try again
        echo "\n Retrying " . $asset['title'] . "\n";
        $response = $client->update_object($asset['id'], 'asset', array("episode" => $curr_episodes[$thisdate]['id']) );
        if (!empty($response['errors'])) {
          die();
        }
      }
      unset($response);
      // give the PBS server a second to catch its breath
      sleep(1);
    }
  }
}




function convert_specials_to_episodes($show_id, $client) {
  /* this function uses the client object to retrieve the list of 'specials' that are
   * assigned to the show_id and assigns them to the season with the matching year.
   *
   * This function is only really useful in the case where a show has had the bulk (or all) 
   * of it's episodes imported as 'specials'.
   *
   * The function assumes year-based seasons.
   * If no matching season is found, the season is created.
   *
   * The function processes the specials from oldest premiered_on date to newest.
   * It assigns the episode an ordinal of which number the special was found in that year's list * 10.
   * This 'times 10' results in ordinals of 10, 20, 30 etc and allows for easier later re-arrangement 
   * of episodes as needed.
   *
   * The function will 'die' immediately if an error occurs such as 
   * attempting to assign an already-used ordinal to an episode.   
   *
   * This function should only be run ONCE on any show. */

  // note that if the script gets interrupted you should set count/curr_year/curr_year_id to the 'current' values to start up again
  $count = 0;
  $curr_year = '';
  $curr_year_id = '';
  $specials = $client->get_show_specials($show_id, array('sort' => 'premiered_on'));
  foreach ($specials as $special) {
    echo $special['attributes']['premiered_on'] . " " .  $special['attributes']['title'] . " " . $special['id'] . "\n";
    $thisdate = $special['attributes']['premiered_on']; 
    $date = explode('-', $thisdate); // date is formatted yyyy-mm-dd
    $year  = $date[0]; 
    // create or assign a season
    if ($year != $curr_year) {
      $count = 1;
      $year_id = get_season_by_ordinal($year, $show_id, $client);
      if (!$year_id) {
        echo ('creating season ' . $year . "\n");
        $year_id = $client->create_child($show_id, 'show', 'season', array('ordinal' => $year));
      }
      if (!$year_id || !empty($year_id['errors'])) {
        echo $year_id['errors'];
        die('no season for ' . $year . " so dying\n");
      }
      $curr_year = $year;
      $curr_year_id = $year_id;
    }
    // convert the special to a season
    $episode = $client->update_object($special['id'], 'special', array("season" => $curr_year_id) );
    if (!empty($episode['errors'])) {
      print_r($episode['errors']);
      die();
    }
    $count++;
    // update the episode to have a multiple of their current ordinal
    // give the PBS server a second to catch its breath 
    sleep(1);
    $episode = $client->update_object($special['id'], 'episode', array("ordinal" => ($count * 10)) );
    if (!empty($episode['errors'])) {
      print_r($episode['errors']);
      //die();
      // this will sometimes fail if the encore date is significantly after the premiere date
      // it'll get confused about where the ordinal is
    }
  }
}

function get_season_by_ordinal($ordinal, $show_id, $client) {
  /* helper function to find a season by its year or other ordinal */
  $season = $client->get_show_seasons($show_id, array('ordinal' => $ordinal));
  if (empty($season[0]['id'])) {
    return false;
  }
  return $season[0]['id'];
}

