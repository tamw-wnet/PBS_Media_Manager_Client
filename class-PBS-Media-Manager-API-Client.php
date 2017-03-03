<?php 
/* PBS Media Manager API Client
 * Author: William Tam (tamw@wnet.org)
 * version 0.1 2017-02-17
*/
class PBS_Media_Manager_API_Client {
  private $client_id;
  private $client_secret;
  private $base_endpoint;
  private $auth_string;
  public  $container_types;
  public  $passport_windows;
  public  $asset_types;
  public  $episode_asset_types; 

  public function __construct($client_id = '', $client_secret = '', $base_endpoint =''){
    $this->client_id = $client_id;
    $this->client_secret = $client_secret;
    $this->base_endpoint = $base_endpoint;
    $this->auth_string = $this->client_id . ":" . $this->client_secret;

    // constants
    $this->valid_endpoints = array('assets', 'episodes', 'specials', 'collections', 'seasons', 'remote-assets', 'shows', 'franchises', 'stations', 'changelog');
    $this->passport_windows = array('public', 'all_members', 'station_members', 'unavailable');
    $this->asset_types = array('preview', 'clip', 'extra');
    $this->episode_asset_types = array('preview', 'clip', 'extra', 'full_length');
  }


  private function build_curl_handle($url) {
    if (!function_exists('curl_init')){
      die('the curl library is required for this client to work');
    }
    $ch = curl_init();
    if (!$ch) {
      die('could not initialize curl');
    }
    curl_setopt($ch, CURLOPT_URL,$url);
    // method and headers can be different, but these are always the same
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $this->auth_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    //curl_setopt($ch, CURLOPT_HEADER, TRUE);
    return $ch;
  }


  public function get_request($query) {
    $return = array();
    $request_url = $this->base_endpoint . $query;
    $ch = $this->build_curl_handle($request_url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    $result=curl_exec($ch);
    $info = curl_getinfo($ch);
    $errors = curl_error($ch);
    curl_close ($ch);
    $json = json_decode($result, true);
    if (empty($json)) {
      return array('errors' => array('info' => $info, 'response' => $result));
    }
    if ($info['http_code'] != 200) {
      return array('errors' => array('info' => $info, 'response' => $json)); 
    } 
    return $json;
  }


  /* main constructor for creating elements 
   * asset, episode, special, collection, season */
  public function create_child($parent_id, $parent_type, $type, $attribs = array()) {
    /* on success returns the url path of the editable asset 
     * note that $parent_id can also be a slug */
    $endpoint = "/" . $parent_type . "s/" . $parent_id . "/" . $type . "s/";
    $data = array(
      "data" => array(
        "type" => $type,
        "attributes" => $attribs
      )
    );
    /* in the MM API, create is a POST */
    $return = array();
    $payload_json = json_encode($data);
    $request_url = $this->base_endpoint . $endpoint;
    $ch = $this->build_curl_handle($request_url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json', 'Content-Length: ' . strlen($payload_json)));
    $result=curl_exec($ch);
    $info = curl_getinfo($ch);
    $errors = curl_error($ch);
    curl_close ($ch);
    if ($info['http_code'] != 201) {
      return array('errors' => array('errors' => $errors, 'result' => $result));
    }
    /* successful request will return a 201 and the location of the created object 
     * we'll follow that location and parse the resulting JSON to return the cid */
    // get just the URI 
    preg_match("!\r\n(?:Location|URI): *(.*?) *\r\n!", $result, $matches);
    // parse out the last segment before /edit/
    preg_match("/.*(?:\/)(.*)(?:\/edit\/)/", $matches[1], $segments);
    return $segments[1];
  }

  private function _get_update_endpoint($id, $type) {
    return $endpoint = "/" . $type . "s/" . $id . "/edit/";
  }

  public function get_updatable_object($id, $type) {
    return $this->get_request(
      $this->_get_update_endpoint($id, $type)
    );
  }

  /* main constructor for updating objects
   * asset, episode, special, collection, season */
  public function update_object($id, $type, $attribs = array()) {
    /* in the MM API, update is a PATCH */
    $endpoint = $this->_get_update_endpoint($id, $type);
    $data = array(
      "data" => array(
        "type" => $type,
        "id" => $id,
        "attributes" => $attribs
      )
    );
    $payload_json = json_encode($data);
    $request_url = $this->base_endpoint . $endpoint; 
    $ch = $this->build_curl_handle($request_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json', 'Content-Length: ' . strlen($payload_json)));
    $result=curl_exec($ch);
    $info = curl_getinfo($ch);
    $errors = curl_error($ch);
    curl_close ($ch);
    if ($info['http_code'] != 200) {
      return array('errors' => array('info' => $info, 'errors' => $errors, 'result' => $result));
    }
    /* successful request will return a 200 and nothing else */
    return TRUE;
  }

  public function delete_object($id, $type) {
    $endpoint = "/" . $type . "/" . $id . "/";
    $request_url = $this->base_endpoint . $endpoint;
    $ch = $this->build_curl_handle($request_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    $result=curl_exec($ch);
    $info = curl_getinfo($ch);
    $errors = curl_error($ch);
    curl_close ($ch);
    if ($info['http_code'] != 200) {
      return array('errors' => array('info' => $info, 'errors' => $errors, 'result' => $result));
    }
    /* successful request will return a 200 and nothing else */
    return TRUE;
  }



  /* main constructor for getting single items 
   * asset, episode, special, collection, season, show, franchise, station */
  public function get_item_of_type($id, $type) {
    /* note that $id can also be a slug */
    $query = "/" . $type . "/" . $id . "/";
    return $this->get_request($query);
  }  


  /* main constructor for lists */
  public function get_list_data($endpoint, $args = array()) {
    /* Only return the actual data, stripping meta and pagination data
     * by default, return all results, but if a value is given for page
     * only that page number will be returned */
    $result_data = array();
    $page = 1;
    if (empty($args['page'])) {
      /* if we get no specific page 
       * start with page 1 and keep going.  */
      $args['page'] = $page; 
    }
    while ($page) {
      $querystring = !empty($args) ? "?" . http_build_query($args) : "";
      $rawdata = $this->get_request($endpoint . $querystring);
      if (empty($rawdata['data'])) {
        return $rawdata;
      }
      $this_set = $rawdata['data'];
      foreach ($this_set as $entry) {
        $result_data[] = $entry;
      }
      if (!empty($rawdata['links']['next'])) {
        $page++;
        $args['page'] = $page;
      } else {
        $page = 0;
      }
    }
    return $result_data;
  }


  /* main constructor for child items */
  public function get_child_items_of_type($parent_id, $parent_type, $type, $page=0) {
    /* note that $parent_id can also be a slug, but generally wont be */
    $query = "/" . $parent_type . "/" . $parent_id . "/" . $type . "/";
    $queryargs = $page ? array("page" => $page) : null;
    return $this->get_list_data($query, $queryargs);
  }


  /* helper function for cleaning up arguments */
  public function validate_asset_type_list($asset_type_list, $container_type = 'episodes') {
    $valid_asset_types = $this->asset_types;
    if ($container_type == 'episodes' || $container_type == 'specials') {
      $valid_asset_types = $this->episode_asset_types;
    }
    if ($asset_type_list == 'all') {
      return $valid_asset_types;
    }
    $typelist = explode(',', $asset_type_list);
    foreach ($typelist as $type) {
      if (!in_array($type, $valid_asset_types)) {
        return false;
      }
    }
    return $typelist;
  }


  /* main constructor for getting assets */
  public function get_child_assets($parent_id, $parent_type='episodes', $asset_type='all', $window='all', $page=0) {
    $asset_types = $this->validate_asset_type_list($asset_type, $parent_type);
    if (!$asset_types) { return false; }
    $windows = $this->passport_windows;
    if ($window !== 'all') {
      // validate and construct the window arg
      $requested_windows = explode(',', $window);
      foreach ($requested_windows as $req_window) {
        if (!in_array($req_window, $windows)) {
          return false;
        }
      }
      $windows = $requested_windows;
    }

    $result_data = array();
    $raw_result = $this->get_child_items_of_type($parent_id, $parent_type, 'assets', $page);
    foreach ($raw_result as $result) {
      // only include the right asset_types
      if (!in_array($result['attributes']['object_type'], $asset_types)) {
        continue;
      }
      // only include the right windows
      if (!in_array($result['attributes']['mvod_window'], $windows) ) {
        continue;
      }
      $result_data[] = $result;
    }
    return $result_data;
  }

  /* images are handled very differently from 'assets' */
  public function get_images($parent_id, $parent_type) {
    $returnary = array();
    $parent = $this->get_item_of_type($parent_id, $parent_type);
    foreach ($parent['data']['attributes']['images'] as $image) {
      $returnary[] = $image;
    }
    return $returnary;
  }

  /* Special functions */

  public function get_asset_by_tp_media_id($tp_media_id) {
    /* Returns the corresponding asset if it exists.  Note that they're
     * calling it tp_media_id, NOT tp_media_object_id */
    $query = "/assets/legacy/?tp_media_id=" . $tp_media_id;
    return $this->get_request($query);
  }

  public function get_show_by_program_id($program_id) {
    /* Returns the corresponding show if it exists.  Note that they're
     * calling it content_channel_id, NOT program_id */
    $query = "/shows/legacy/?content_channel_id=" . $program_id;
    return $this->get_request($query);
  }

  public function get_changelog($args = array()) {
    /* args should be an array, possible elements are
     * type (episode|asset|etc), action(updated|deleted), id,
     * since (timestamp in %Y-%m-%dT%H:%M:%S format)
     * all can be combined and multiple except 'since' */
    if (empty($args['since'])) {
      // default 'since' to be in the last 8hrs
      $timezone = new DateTimeZone('UTC');
      $datetime = new DateTime("-10.5 hour", $timezone );
      $since = $datetime->format('Y-m-d\TH:i:s');
      $args['since'] = $since; 
    }
    $query = "/changelog/";
    return $this->get_list_data($query, $args);
  }


  /* SHORTCUT FUNCTIONS */

  /* shortcut functions for single items */

  public function get_asset($id) {
    return $this->get_item_of_type($id, 'assets');
  }

  public function get_episode($id) {
    return $this->get_item_of_type($id, 'episodes');
  }

  public function get_special($id) {
    return $this->get_item_of_type($id, 'specials');
  }

  public function get_collection($id) {
    return $this->get_item_of_type($id, 'collections');
  }

  public function get_season($id) {
    return $this->get_item_of_type($id, 'seasons');
  }

  public function get_show($id) {
    return $this->get_item_of_type($id, 'shows');
  }

  public function get_remote_asset($id) {
    return $this->get_item_of_type($id, 'remote-assets');
  }

  public function get_franchise($id) {
    return $this->get_item_of_type($id, 'franchises');
  }

  public function get_station($id) {
    return $this->get_item_of_type($id, 'stations');
  }


  /* shortcut functions for lists */

  /* special cases -- get franchises and shows.  
   * Franchises have no parent object, and shows do not 
   * have to have a parent object  */

  public function get_franchises($page=0) {
    $query = "/franchises/";
    $queryargs = $page ? array("page" => $page) : null;
    return $this->get_list_data($query, $queryargs);
  }

  public function get_shows($page=0) {
    $query = "/shows/";
    $queryargs = $page ? array("page" => $page) : null;
    return $this->get_list_data($query, $queryargs);
  }

  /* shortcut functions for lists of child objects */

  public function get_franchise_shows($franchise_id, $page=0) {
    return $this->get_child_items_of_type($franchise_id, 'franchises', 'shows', $page);
  } 

  public function get_show_seasons($show_id, $page=0) {
    return $this->get_child_items_of_type($show_id, 'shows', 'seasons', $page);
  }

  public function get_show_specials($show_id, $page=0) {
    return $this->get_child_items_of_type($show_id, 'shows', 'specials', $page);
  }

  public function get_season_episodes($season_id, $page=0) {
    return $this->get_child_items_of_type($season_id, 'seasons', 'episodes', $page);
  }


  /* shortcuts for asset lists:  Note that assets can be children of a franchise, show, season, 
   * collection, special, or episode BUT can only be the child of one of them -- 
   * if an asset is a child of an episode it is not a child of a show.  
   * These methods also allow filtering by asset_type and window */

  public function get_episode_assets($episode_id, $asset_type='all', $window='all', $page=0) {
    return $this->get_child_assets($episode_id, 'episodes', $asset_type, $window, $page);
  }

  public function get_special_assets($special_id, $asset_type='all', $window='all', $page=0) {
    return $this->get_child_assets($special_id, 'specials', $asset_type, $window, $page);
  }

  public function get_season_assets($season_id, $asset_type='all', $window='all', $page=0) {
    return $this->get_child_assets($season_id, 'seasons', $asset_type, $window, $page);
  }

  public function get_show_assets($show_id, $asset_type='all', $window='all', $page=0) {
    return $this->get_child_assets($show_id, 'shows', $asset_type, $window, $page);
  }

  public function get_franchise_assets($franchise_id, $asset_type='all', $window='all', $page=0) {
    return $this->get_child_assets($franchise_id, 'franchises', $asset_type, $window, $page);
  }


  /* shortcut functions for images */

  public function get_franchise_images($franchise_id) {
    return $this->get_images($franchise_id, 'franchises');
  }

  public function get_show_images($show_id) {
    return $this->get_images($show_id, 'shows');
  }

  public function get_season_images($season_id) {
    return $this->get_images($season_id, 'seasons');
  }

  public function get_collection_images($collection_id) {
    return $this->get_images($collection_id, 'collections');
  }

  public function get_episode_images($episode_id) {
    return $this->get_images($episode_id, 'episodes');
  }

  public function get_special_images($special_id) {
    return $this->get_images($special_id, 'specials');
  }

  public function get_asset_images($asset_id) {
    return $this->get_images($asset_id, 'assets');
  }

}
?>
