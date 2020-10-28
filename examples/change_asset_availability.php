<?php
/* this example script will update the availability of all full_length assets
 * in a show to end at a new date */


require('../class-PBS-Media-Manager-API-Client.php');
$baseurl = 'https://media.services.pbs.org/api/v1';

/* these you will get from PBS */
$api_id = 'your api id';
$api_secret = 'your secret';

/* the 'content id' for the show you're updating you'll get from the Media Manager console */
$show_id = 'your content id';


$requestor = new PBS_Media_Manager_API_Client($api_id, $api_secret, $baseurl);

/* the Media Manager API is picky about the format of dates.  Below is an example, 'Z' means UTC and is required */
$new_end_date = '2023-01-01T05:00:00Z';

$next = true;
$page = 1;
while ($next) {
  $args = array('sort' => 'premiered_on', 'page' => $page, 'page-size' => 3, 'show-id' => $show_id, 'platform-slug'=>'partnerplayer', 'type'=>'full_length' );
  $data = $requestor->get_list_data('/assets', $args, TRUE);
  if (empty($data['data'])) {
    echo "trying again for page $page \n";
    $data = $requestor->get_list_data('/assets', $args, TRUE);
    if (empty($data['data'])) {
      echo "failed, exiting";
      die(json_encode($data));
    }
  }

  $assets = $data['data'];
  foreach ($assets as $asset) {
    $asset_id = $asset['id'];
    $current_start = $asset['attributes']['availabilities']['public']['start'];
    echo '"' . $asset['attributes']['premiered_on'] . '","' . $asset['attributes']['title'] . ", $asset_id, $current_start\n"; // nice to echo the status and progress
    $newavail = array( 'start' => $current_start, 'end' => $new_end_date );  
    $updateary = array('availabilities' => array( 'public' => $newavail, 'all_members' => $newavail, 'station_members' => $newavail));
    //  echo json_encode($updateary) ."\n"; // for debugging the request
    $response = $requestor->update_object($asset_id, 'asset', $updateary); 
    if (!empty($response['errors'])) {
      $response = $requestor->update_object($asset_id, 'asset', $updateary);
      if (!empty($response['errors'])) {
        die(json_encode($response));
      }
    }
  }
  $page++;
  if (empty($data['links']['next'])) {
    $next = false;
  }
  sleep(1); // to keep from overloading the API we put in a one-second pause after each update
}
?>

