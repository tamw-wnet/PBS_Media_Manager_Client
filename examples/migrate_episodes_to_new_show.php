<?php
require('../class-PBS-Media-Manager-API-Client.php');

// creds with read/write for both shows
$api_id = '';
$api_secret = '';
$baseurl = 'https://media.services.pbs.org/api/v1';

$client = new PBS_Media_Manager_API_Client($api_id, $api_secret, $baseurl);

$old_show_id = '';

$new_show_id = '';



function migrate_episodes_to_new_show($old_show_id, $new_show_id, $client) {
  $old_seasons = $client->get_show_seasons($old_show_id, array("sort" => "ordinal"));
  $new_seasons = $client->get_show_seasons($new_show_id, array("sort" => "ordinal"));
  foreach ($old_seasons as $season) {
    $ordinal = $season['attributes']['ordinal'];
    $old_season_id = $season['id'];
    $new_season_id = '';
    echo "\n\n old $ordinal is $old_season_id\n";
    foreach ($new_seasons as $new_season) {
      if ($ordinal == $new_season['attributes']['ordinal']) {
        $new_season_id = $new_season['id'];
        echo "matched by $new_season_id\n";
      }
    }
    $episodes = $client->get_child_items_of_type($old_season_id, 'season', 'episode', array("sort" => "ordinal"));
    foreach ($episodes as $episode) {
      $episode_ordinal = $episode['attributes']['ordinal'];
      $episode_id = $episode['id'];
      echo $new_season_id . '"' . $episode_ordinal . '","' . $episode['attributes']['title']  . '","' . $episode_id . "\"\n";
      echo "Moved to new show and season: " . $client->update_object($episode_id, 'episode', array("season" => $new_season_id) ) . "\"\n";
      echo "Ordinal updated: " . $client->update_object($episode_id, 'episode', array("ordinal" => $episode_ordinal) ) . "\"\n";
    }
  }

}

migrate_episodes_to_new_show($old_show_id, $new_show_id, $client);


