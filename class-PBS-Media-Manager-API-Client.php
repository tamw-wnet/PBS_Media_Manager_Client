<?php
/* PBS Media Manager API Client
 * Authors: William Tam (tamw@wnet.org), Augustus Mayo (amayo@tpt.org), Aaron Crosman (aaron.crosman@cyberwoven.com)
 * version 1.0 2017-04-10
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
  public  $video_profiles;
  public  $file_types;

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
    $this->video_profiles = array('hd-1080p-mezzanine-16x9', 'hd-1080p-mezzanine-4x3', 'hd-mezzanine-16x9', 'hd-mezzanine-4x3');
    $this->file_types = array('video', 'caption'); // 'image' will be added when the api can handle it properly
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
    $payload_json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
    if (!in_array($info['http_code'], array(200, 201, 202, 204))) {
      return array('errors' => array('errors' => $errors, 'result' => $result));
    }
    /* successful request will return a 20x and the location of the created object
     * we'll follow that location and parse the resulting JSON to return the cid */
    // get just the URI
    preg_match("/(Location|URI): .*?\/([a-f0-9\-]+)\/(edit\/)?(\r|\n|\r\n)/", $result, $matches);

    // TODO: Unsafe indexing, how should errors be handled?
    return $matches[2];
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
    $payload_json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $request_url = $this->base_endpoint . $endpoint;
    $ch = $this->build_curl_handle($request_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json', 'Content-Length: ' . strlen($payload_json)));
    $result=curl_exec($ch);
    $info = curl_getinfo($ch);
    $errors = curl_error($ch);
    curl_close ($ch);
    if (!in_array($info['http_code'], array(200, 201, 202, 204))) {
      return array('errors' => array('info' => $info, 'errors' => $errors, 'result' => $result));
    }
    /* successful request will return a 20x and nothing else */
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
    if (!in_array($info['http_code'], array(200, 201, 202, 204))) {
      return array('errors' => array('info' => $info, 'errors' => $errors, 'result' => $result));
    }
    /* successful request will return a 20x and nothing else */
    return TRUE;
  }



  /* main constructor for getting single items
   * asset, episode, special, collection, season, show, franchise, station */
  public function get_item_of_type($id, $type, $private=false) {
    /* note that $id can also be a slug */
    $query = "/" . $type . "s/" . $id . "/";
    // unpublished, 'private' items have to do a GET on the update endpoint
    if ($private) {
       $query = $this->_get_update_endpoint($id, $type);
    }
    return $this->get_request($query);
  }


  /* main constructor for lists */
  public function get_list_data($endpoint, $args = array(), $include_metadata = false) {
    /* By default only return the actual data, stripping meta and pagination
     * data including all results. If a value is given for page
     * only return page number. If include_metadata is true, return fields from
     * the first page of results.
     */
    $result_data = array();
    $meta_data = array();
    $limit_pages = false;
    $page = 1;
    if (empty($args['page'])) {
      /* if we get no specific page
       * start with page 1 and keep going.  */
      $args['page'] = $page;
    } else {
      $limit_pages = true;
    }

    while ($page) {
      $querystring = !empty($args) ? "?" . http_build_query($args) : "";
      // PBS's endpoints don't like colons to be encoded
      $querystring = str_replace("%3A", ":", $querystring);
      $rawdata = $this->get_request($endpoint . $querystring);
      if (empty($rawdata['data'])) {
        return $rawdata;
      }
      $this_set = $rawdata['data'];
      foreach ($this_set as $entry) {
        $result_data[] = $entry;
      }

      if ($include_metadata && empty($meta_data)) {
        $meta_data = array(
          'links' => $rawdata['links'],
          'meta' => $rawdata['meta'],
          'jsonapi' => $rawdata['jsonapi'],
        );
      }

      if (!empty($rawdata['links']['next']) && !$limit_pages) {
        $page++;
        $args['page'] = $page;
      } else {
        $page = 0;
      }
    }

    if ($include_metadata) {
      $meta_data['data'] = $result_data;
      return $meta_data;
    }

    return $result_data;
  }


  /* main constructor for child items */
  public function get_child_items_of_type($parent_id, $parent_type, $type, $queryargs=array()) {
    /* note that $parent_id can also be a slug, but generally wont be */
    $query = "/" . $parent_type . "s/" . $parent_id . "/" . $type . "s/";
    return $this->get_list_data($query, $queryargs);
  }


  /* helper function for cleaning up arguments */
  public function validate_asset_type_list($asset_type_list, $container_type = 'episode') {
    $valid_asset_types = $this->asset_types;
    if ($container_type == 'episode' || $container_type == 'special') {
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
  public function get_child_assets($parent_id, $parent_type='episode', $asset_type='all', $window='all', $queryargs=array()) {
    $asset_types = $this->validate_asset_type_list($asset_type, $parent_type);
    if (!$asset_types) { return false; }
    $windows = $this->passport_windows;
    if ($window !== 'all') {
      // validate and construct the window arg
      $requested_windows = explode(',', $window);
      foreach ($requested_windows as $req_window) {
        if (!in_array($req_window, $windows)) {
          return array('errors' => 'invalid window');
        }
      }
      $windows = $requested_windows;
    }

    $result_data = array();
    $raw_result = $this->get_child_items_of_type($parent_id, $parent_type, 'asset', $queryargs);
    if (!empty($raw_result['errors'])) {
      return $raw_result;
    }
    foreach ($raw_result as $result) {
      // ignore non-list data
      if (empty($result['attributes'])) {
        continue;
      }
      // only include the right asset_types
      if (!in_array($result['attributes']['object_type'], $asset_types)) {
        continue;
      }
      // only include the right windows
      /* not yet implemented in API
      if (!in_array($result['attributes']['mvod_window'], $windows) ) {
        continue;
      }
      */
      $result_data[] = $result;
    }
    $result_data = empty($result_data) ? false : $result_data;
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
    $response = $this->get_request($query);
    if (!empty($response["errors"]["info"]["http_code"]) && $response["errors"]["info"]["http_code"] == 404) {
      // if this video is private/unpublished, retry the edit endpoint
      preg_match("/.*?(\/assets\/.*)\/$/", $response["errors"]["info"]["url"], $output_array);
      if (!empty($output_array[1])){
        $response = $this->get_request($output_array[1] . "/edit/");
      }
    }
    return $response;
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
      $datetime = new DateTime("-24 hour", $timezone );
      $since = $datetime->format('Y-m-d\TH:i:s.u\Z');
      $args['since'] = $since;
    }
    $query = "/changelog/";
    return $this->get_list_data($query, $args);
  }


  /* SHORTCUT FUNCTIONS */

  /* shortcut functions for single items */

  public function get_asset($id, $private=false) {
    return $this->get_item_of_type($id, 'asset', $private);
  }

  public function get_episode($id, $private=false) {
    return $this->get_item_of_type($id, 'episode', $private);
  }

  public function get_special($id, $private=false) {
    return $this->get_item_of_type($id, 'special', $private);
  }

  public function get_collection($id) {
    return $this->get_item_of_type($id, 'collection');
  }

  public function get_season($id) {
    return $this->get_item_of_type($id, 'season');
  }

  public function get_show($id) {
    return $this->get_item_of_type($id, 'show');
  }

  public function get_remote_asset($id) {
    return $this->get_item_of_type($id, 'remote-asset');
  }

  public function get_franchise($id) {
    return $this->get_item_of_type($id, 'franchise');
  }

  public function get_station($id) {
    return $this->get_item_of_type($id, 'station');
  }


  /* shortcut functions for lists */

  /* special cases -- get franchises and shows.
   * Franchises have no parent object, and shows do not
   * have to have a parent object  */

  public function get_franchises($queryargs=array()) {
    $query = "/franchises/";
    return $this->get_list_data($query, $queryargs);
  }

  public function get_shows($queryargs=array()) {
    $query = "/shows/";
    return $this->get_list_data($query, $queryargs);
  }

  /* shortcut functions for lists of child objects */

  public function get_franchise_shows($franchise_id, $queryargs=array()) {
    return $this->get_child_items_of_type($franchise_id, 'franchise', 'show', $queryargs);
  }

  public function get_show_seasons($show_id, $queryargs=array()) {
    return $this->get_child_items_of_type($show_id, 'show', 'season', $queryargs);
  }

  public function get_show_specials($show_id, $queryargs=array()) {
    return $this->get_child_items_of_type($show_id, 'show', 'special', $queryargs);
  }

  public function get_season_episodes($season_id, $queryargs=array()) {
    return $this->get_child_items_of_type($season_id, 'season', 'episode', $queryargs);
  }


  /* shortcuts for asset lists:  Note that assets can be children of a franchise, show, season,
   * collection, special, or episode BUT can only be the child of one of them --
   * if an asset is a child of an episode it is not a child of a show.
   * These methods also allow filtering by asset_type and window */

  public function get_episode_assets($episode_id, $asset_type='all', $window='all', $queryargs=array()) {
    return $this->get_child_assets($episode_id, 'episode', $asset_type, $window, $queryargs);
  }

  public function get_special_assets($special_id, $asset_type='all', $window='all', $queryargs=array()) {
    return $this->get_child_assets($special_id, 'special', $asset_type, $window, $queryargs);
  }

  public function get_season_assets($season_id, $asset_type='all', $window='all', $queryargs=array()) {
    return $this->get_child_assets($season_id, 'season', $asset_type, $window, $queryargs);
  }

  public function get_show_assets($show_id, $asset_type='all', $window='all', $queryargs=array()) {
    return $this->get_child_assets($show_id, 'show', $asset_type, $window, $queryargs);
  }

  public function get_franchise_assets($franchise_id, $asset_type='all', $window='all', $queryargs=array()) {
    return $this->get_child_assets($franchise_id, 'franchise', $asset_type, $window, $queryargs);
  }


  /* shortcut functions for images */

  public function get_franchise_images($franchise_id) {
    return $this->get_images($franchise_id, 'franchise');
  }

  public function get_show_images($show_id) {
    return $this->get_images($show_id, 'show');
  }

  public function get_season_images($season_id) {
    return $this->get_images($season_id, 'season');
  }

  public function get_collection_images($collection_id) {
    return $this->get_images($collection_id, 'collection');
  }

  public function get_episode_images($episode_id) {
    return $this->get_images($episode_id, 'episode');
  }

  public function get_special_images($special_id) {
    return $this->get_images($special_id, 'special');
  }

  public function get_asset_images($asset_id) {
    return $this->get_images($asset_id, 'asset');
  }

  /* file ingest helpers */

  public function delete_file_from_asset($asset_id, $type='video') {
    /* deleting a file from an asset is just submitting a null value for it */
    if (empty($asset_id)) {
      return array('errors' => 'no asset id');
    }
    if (! in_array($type, $this->file_types)) {
      return array('errors' => 'invalid file type');
    }
    $attribs = array(
      $type => null
    );
    return $this->update_object($asset_id, 'asset', $attribs);
  }


}
