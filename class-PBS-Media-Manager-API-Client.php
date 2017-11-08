<?php

/**
 * @file
 * PBS Media Manager API Client.
 *
 * Authors: William Tam (tamw@wnet.org), Augustus Mayo (amayo@tpt.org),
 * Aaron Crosman (aaron.crosman@cyberwoven.com)
 * version 2.0 2017-08-04
 */

/**
 * Class PBS_Media_Manager_API_Client.
 */
class PBS_Media_Manager_API_Client {
  private $client_id;
  private $client_secret;
  private $base_endpoint;
  private $auth_string;
  public  $valid_endpoints;
  public  $passport_windows;
  public  $asset_types;
  public  $episode_asset_types;
  public  $video_profiles;
  public  $file_types;

  /**
   * PBS_Media_Manager_API_Client constructor.
   *
   * @param string $client_id
   *   Client ID.
   * @param string $client_secret
   *   Client Secret.
   * @param string $base_endpoint
   *   Base Endpoint.
   */
  public function __construct($client_id = '', $client_secret = '', $base_endpoint = '') {
    $this->client_id = $client_id;
    $this->client_secret = $client_secret;
    $this->base_endpoint = $base_endpoint;
    $this->auth_string = $this->client_id . ":" . $this->client_secret;

    // Constants.
    $this->valid_endpoints = array(
      'assets',
      'episodes',
      'specials',
      'collections',
      'seasons',
      'remote-assets',
      'shows',
      'franchises',
      'stations',
      'changelog',
    );
    $this->passport_windows = array(
      'public',
      'all_members',
      'station_members',
      'unavailable',
    );
    $this->asset_types = array('preview', 'clip', 'extra');
    $this->episode_asset_types = array(
      'preview',
      'clip',
      'extra',
      'full_length',
    );
    $this->video_profiles = array(
      'hd-1080p-mezzanine-16x9',
      'hd-1080p-mezzanine-4x3',
      'hd-mezzanine-16x9',
      'hd-mezzanine-4x3',
    );
    $this->file_types = array('video', 'caption');
    // 'image' will be added when the api can handle it properly.
  }

  /**
   * Build the curl handle.
   *
   * @param string $url
   *   The URL.
   *
   * @return resource
   *   The curl resource.
   */
  private function build_curl_handle($url) {
    if (!function_exists('curl_init')) {
      die('the curl library is required for this client to work');
    }
    $ch = curl_init();
    if (!$ch) {
      die('could not initialize curl');
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    // Method and headers can be different, but these are always the same.
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $this->auth_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    return $ch;
  }

  /**
   * Reformat API responses as an array.
   *
   * @param string $response
   *   The response from the API.
   *
   * @return array
   *   The response formatted as an array.
   */
  private function make_response_array($response) {
    $myarray = array();
    $data = explode("\n", $response);
    if (strpos($data[0], 'HTTP') === 0) {
      // the first line is a status code
      $myarray['status'] = $data[0];
      array_shift($data);
    }
    foreach ($data as $part) {
      if (json_decode($part)) {
        $myarray[] = json_decode($part);
        continue;
      }
      $middle = explode(": ", $part, 2);
      $myarray[trim($middle[0])] = trim($middle[1]);
    }
    return $myarray;
  }

  /**
   * Get request.
   *
   * @param string $query
   *   The querystring.
   *
   * @return array|mixed
   *   The result from the API.
   */
  public function get_request($query) {
    $request_url = $this->base_endpoint . $query;
    $ch = $this->build_curl_handle($request_url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    $result = curl_exec($ch);
    $info = curl_getinfo($ch);
    $errors = curl_error($ch);
    curl_close($ch);
    $json = json_decode($result, TRUE);
    if (empty($json)) {
      $result = $this->make_response_array($result);
      return array(
        'errors' => array(
          'info' => $info,
          'errors' => $errors,
          'response' => $result,
        ),
      );
    }
    if ($info['http_code'] != 200) {
      return array(
        'errors' => array(
          'info' => $info,
          'errors' => $errors,
          'response' => $json,
        ),
      );
    }
    return $json;
  }

  /**
   * Main constructor for creating elements.
   *
   * Asset, episode, special, collection, season.
   *
   * @param string $parent_id
   *    The parent id. Can also be a slug.
   * @param string $parent_type
   *    The parent type.
   * @param string $type
   *    The type.
   * @param array $attribs
   *    The attributes.
   *
   * @return array
   *    On success returns the url path of the editable asset.
   */
  public function create_child($parent_id, $parent_type, $type, $attribs = array()) {

    $endpoint = "/" . $parent_type . "s/" . $parent_id . "/" . $type . "s/";
    $data = array(
      "data" => array(
        "type" => $type,
        "attributes" => $attribs,
      ),
    );
    /* in the MM API, create is a POST */
    $payload_json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $request_url = $this->base_endpoint . $endpoint;
    $ch = $this->build_curl_handle($request_url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($payload_json)));
    $result = curl_exec($ch);
    $info = curl_getinfo($ch);
    $errors = curl_error($ch);
    curl_close($ch);
    if (!in_array($info['http_code'], array(200, 201, 202, 204))) {
      $result = $this->make_response_array($result);
      return array(
        'errors' => array(
          'info' => $info,
          'errors' => $errors,
          'result' => $result,
        ),
      );
    }
    /*
     * A successful request will return a 20x and the location of the created
     * object.
     * We'll follow that location and parse the resulting JSON to return the
     * cid.
     */
    // Get just the URI.
    preg_match("/(Location|URI): .*?\/([a-f0-9\-]+)\/(edit\/)?(\r|\n|\r\n)/", $result, $matches);

    // TODO: Unsafe indexing, how should errors be handled?
    return $matches[2];
  }

  /**
   * Get update endpoint.
   *
   * @param string $id
   *   The ID.
   * @param string $type
   *   The type.
   *
   * @return string
   *   The update endpoint.
   */
  private function _get_update_endpoint($id, $type) {
    $endpoint = "/" . $type . "s/" . $id . "/";
    if ($type == 'asset') {
      $endpoint .= "edit/";
    }
    return $endpoint;
  }

  /**
   * Get updatable object.
   *
   * @param string $id
   *   The ID.
   * @param string $type
   *   The type.
   *
   * @return array|mixed
   *   The updatable object.
   */
  public function get_updatable_object($id, $type) {
    return $this->get_request(
      $this->_get_update_endpoint($id, $type)
    );
  }

  /**
   * Modify query strings for submission to the API.
   *
   * @param array $args
   *   The arguments for the query.
   *
   * @return mixed|string
   *   The modified query.
   */
  public function build_pbs_querystring($args) {
    $querystring = !empty($args) ? "?" . http_build_query($args) : "";
    // PBS's endpoints don't like encoded entities.
    $querystring = str_replace("%3A", ":", $querystring);
    $querystring = str_replace("%3D", "=", $querystring);
    $querystring = str_replace("%26", "&", $querystring);
    return $querystring;
  }

  /**
   * Main constructor for updating objects.
   *
   * Asset, episode, special, collection, season.
   *
   * @param string $id
   *   The ID.
   * @param string $type
   *   The type.
   * @param array $attribs
   *   The attributes.
   *
   * @return array|bool
   *   A successful request will return a 20x and nothing else.
   */
  public function update_object($id, $type, $attribs = array()) {
    /* In the MM API, update is a PATCH. */
    $endpoint = $this->_get_update_endpoint($id, $type);
    $data = array(
      "data" => array(
        "type" => $type,
        "id" => $id,
        "attributes" => $attribs,
      ),
    );
    $payload_json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $request_url = $this->base_endpoint . $endpoint;
    $ch = $this->build_curl_handle($request_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($payload_json)));
    $result = curl_exec($ch);
    $info = curl_getinfo($ch);
    $errors = curl_error($ch);
    curl_close($ch);
    if (!in_array($info['http_code'], array(200, 201, 202, 204))) {
      $result = $this->make_response_array($result);
      return array(
        'errors' => array(
          'info' => $info,
          'errors' => $errors,
          'result' => $result,
        ),
      );
    }
    /* successful request will return a 20x and nothing else */
    return TRUE;
  }

  /**
   * Delete an object.
   *
   * @param string $id
   *    The ID.
   * @param string $type
   *   The type.
   *
   * @return array|bool
   *   A successful request will return a 20x and nothing else.
   */
  public function delete_object($id, $type) {
    $endpoint = "/" . $type . "/" . $id . "/";
    $request_url = $this->base_endpoint . $endpoint;
    $ch = $this->build_curl_handle($request_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    $result = curl_exec($ch);
    $info = curl_getinfo($ch);
    $errors = curl_error($ch);
    curl_close($ch);
    if (!in_array($info['http_code'], array(200, 201, 202, 204))) {
      $result = $this->make_response_array($result);
      return array(
        'errors' => array(
          'info' => $info,
          'errors' => $errors,
          'result' => $result,
        ),
      );
    }
    /* successful request will return a 20x and nothing else */
    return TRUE;
  }

  /**
   * Main constructor for getting single items.
   *
   * Asset, episode, special, collection, season, show, franchise, station.
   *
   * @param string $id
   *    The ID. Can also be a slug.
   * @param string $type
   *    The type.
   * @param bool $private
   *    Whether the item is private.
   * @param array $queryargs
   *   The arguments for the query.
   *
   * @return array|mixed
   *    The items.
   */
  public function get_item_of_type($id, $type, $private = FALSE, $queryargs = array()) {
    $endpoint = "/" . $type . "s/" . $id . "/";
    // Unpublished, 'private' items have to do a GET on the update endpoint.
    if ($private === TRUE) {
      $endpoint = $this->_get_update_endpoint($id, $type);
    }
    if (empty($queryargs) && is_array($private)) {
      $queryargs = $private;
    }
    $querystring = $this->build_pbs_querystring($queryargs);
    return $this->get_request($endpoint . $querystring);
  }

  /**
   * Main constructor for lists.
   *
   * @param string $endpoint
   *    The endpoint.
   * @param array $args
   *    The arguments.  If a value is given for $args['page'], only the
   *    page number will be returned.
   * @param bool $include_metadata
   *    Option to include metadata in the results.
   *
   * @return array
   *   An array of list of items.
   */
  public function get_list_data($endpoint, $args = array(), $include_metadata
    = FALSE) {
    /* By default only return the actual data, stripping meta and pagination
     * data including all results. If a value is given for page
     * only return page number. If include_metadata is true, return fields from
     * the first page of results.
     */
    $result_data = array();
    $meta_data = array();
    $limit_pages = FALSE;
    $page = 1;
    /* NULL args are interpreted as strings, throwing errors. Force it to be
     * an array.
     */
    if (empty($args)) {
      $args = array();
    }
    if (empty($args['page'])) {
      /* If we get no specific page
       * start with page 1 and keep going.  */
      $args['page'] = $page;
    }
    else {
      $limit_pages = TRUE;
    }

    while ($page) {
      $querystring = $this->build_pbs_querystring($args);
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
      }
      else {
        $page = 0;
      }
    }

    if ($include_metadata) {
      $meta_data['data'] = $result_data;
      return $meta_data;
    }

    return $result_data;
  }

  /**
   * Main constructor for child items.
   *
   * @param string $parent_id
   *   Parent ID. Can also be a slug, but generally won't be.
   * @param string $parent_type
   *   Parent type.
   * @param string $type
   *   Type.
   * @param array $queryargs
   *   The arguments for the query.
   *
   * @return array
   *   An array of items.
   */
  public function get_child_items_of_type($parent_id, $parent_type, $type, $queryargs = array()) {
    $query = "/" . $parent_type . "s/" . $parent_id . "/" . $type . "s/";
    return $this->get_list_data($query, $queryargs);
  }

  /**
   * Helper function for cleaning up arguments.
   *
   * @param string $asset_type_list
   *   A comma-delimited list of asset types.
   * @param string $container_type
   *   The type of container. Defaults to episode.
   *
   * @return array|bool
   *   An array of asset types.
   */
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
        return FALSE;
      }
    }
    return $typelist;
  }

  /**
   * Main constructor for getting child assets.
   *
   * @param string $parent_id
   *   Parent ID.
   * @param string $parent_type
   *   Parent type.
   * @param string $asset_type
   *   Asset type.
   * @param string $window
   *   The availability window.
   * @param array $queryargs
   *   The arguments for the query.
   *
   * @return array|bool
   *   Returns an array of child assets.
   */
  public function get_child_assets($parent_id, $parent_type = 'episode', $asset_type = 'all', $window = 'all', $queryargs = array()) {

    $asset_types = $this->validate_asset_type_list($asset_type, $parent_type);
    if (!$asset_types) {
      return FALSE;
    }
    $windows = $this->passport_windows;
    if ($window !== 'all') {
      // Validate and construct the window arg.
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
      // Ignore non-list data.
      if (empty($result['attributes'])) {
        continue;
      }
      // Only include the right asset_types.
      if (!in_array($result['attributes']['object_type'], $asset_types)) {
        continue;
      }
      // Only include the right windows.
      /* Not yet implemented in API.
       * if (!in_array($result['attributes']['mvod_window'], $windows) ) {
       *   continue;
       * }
       */
      $result_data[] = $result;
    }
    $result_data = empty($result_data) ? FALSE : $result_data;
    return $result_data;
  }

  /**
   * Images are handled very differently from 'assets'.
   *
   * @param string $parent_id
   *   Parent ID.
   * @param string $parent_type
   *   Parent type.
   *
   * @return array
   *   Returns an array of images.
   */
  public function get_images($parent_id, $parent_type) {
    $returnary = array();
    $parent = $this->get_item_of_type($parent_id, $parent_type);
    foreach ($parent['data']['attributes']['images'] as $image) {
      $returnary[] = $image;
    }
    return $returnary;
  }

  /* Special functions */

  /**
   * Legacy function to get assets by TP Media Object ID.
   *
   * @param string $tp_media_id
   *   TP Media Object ID.
   *
   * @return array|mixed
   *   Returns an asset.
   */
  public function get_asset_by_tp_media_id($tp_media_id) {
    /* Returns the corresponding asset if it exists.  Note that they're
     * calling it tp_media_id, NOT tp_media_object_id */
    $query = "/assets/legacy/?tp_media_id=" . $tp_media_id;
    $response = $this->get_request($query);
    if (!empty($response["errors"]["info"]["http_code"]) && $response["errors"]["info"]["http_code"] == 404) {
      // If this video is private/unpublished, retry the edit endpoint.
      preg_match("/.*?(\/assets\/.*)\/$/", $response["errors"]["info"]["url"], $output_array);
      if (!empty($output_array[1])) {
        $response = $this->get_request($output_array[1] . "/edit/");
      }
    }
    return $response;
  }

  /**
   * Legacy function to get a show by program ID.
   *
   * @param string $program_id
   *   Program ID.
   *
   * @return array|mixed
   *   Returns the corresponding show if it exists.
   */
  public function get_show_by_program_id($program_id) {
    /* Note that they're calling it content_channel_id, NOT program_id */
    $query = "/shows/legacy/?content_channel_id=" . $program_id;
    return $this->get_request($query);
  }

  /**
   * Get the changelog.
   *
   * @param array $args
   *   Possible elements are type (episode|asset|etc), action
   *   (updated|deleted), id, since (timestamp in %Y-%m-%dT%H:%M:%S format).
   *   All can be combined and multiple except 'since'.
   *
   * @return array
   *   Returns the changelog.
   */
  public function get_changelog($args = array()) {
    if (empty($args['since'])) {
      // Default 'since' to be in the last 8hrs.
      $timezone = new DateTimeZone('UTC');
      $datetime = new DateTime("-24 hour", $timezone);
      $since = $datetime->format('Y-m-d\TH:i:s.u\Z');
      $args['since'] = $since;
    }
    $query = "/changelog/";
    return $this->get_list_data($query, $args);
  }

  /* SHORTCUT FUNCTIONS */

  /* Shortcut functions for single items. */

  /**
   * Get a single asset.
   *
   * @param string $id
   *   The ID.
   * @param bool $private
   *   Whether the asset is private.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array|mixed
   *   Returns the asset.
   */
  public function get_asset($id, $private = FALSE, $queryargs = array()) {
    return $this->get_item_of_type($id, 'asset', $private, $queryargs);
  }

  /**
   * Get a single episode.
   *
   * @param string $id
   *   The ID.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array|mixed
   *   Returns the episode.
   */
  public function get_episode($id, $queryargs = array()) {
    return $this->get_item_of_type($id, 'episode', $queryargs);
  }

  /**
   * Get a single special.
   *
   * @param string $id
   *   The ID.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array|mixed
   *   Returns the special.
   */
  public function get_special($id, $queryargs = array()) {
    return $this->get_item_of_type($id, 'special', $queryargs);
  }

  /**
   * Get a single collection.
   *
   * @param string $id
   *   The ID.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array|mixed
   *   Returns the collection.
   */
  public function get_collection($id, $queryargs = array()) {
    return $this->get_item_of_type($id, 'collection', $queryargs);
  }

  /**
   * Get a single season.
   *
   * @param string $id
   *   The ID.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array|mixed
   *   Returns the season.
   */
  public function get_season($id, $queryargs = array()) {
    return $this->get_item_of_type($id, 'season', $queryargs);
  }

  /**
   * Get a single show.
   *
   * @param string $id
   *   The ID.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array|mixed
   *   Returns the show.
   */
  public function get_show($id, $queryargs = array()) {
    return $this->get_item_of_type($id, 'show', $queryargs);
  }

  /**
   * Get a single remote asset.
   *
   * @param string $id
   *   The ID.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array|mixed
   *   Returns the asset.
   */
  public function get_remote_asset($id, $queryargs = array()) {
    return $this->get_item_of_type($id, 'remote-asset', $queryargs);
  }

  /**
   * Get a single franchise.
   *
   * @param string $id
   *   The ID.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array|mixed
   *   Returns the asset.
   */
  public function get_franchise($id, $queryargs = array()) {
    return $this->get_item_of_type($id, 'franchise', $queryargs);
  }

  /**
   * Get a single station.
   *
   * @param string $id
   *   The ID.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array|mixed
   *   Returns the station.
   */
  public function get_station($id, $queryargs = array()) {
    return $this->get_item_of_type($id, 'station', $queryargs);
  }

  /* Shortcut functions for lists. */

  /* Special cases -- franchises and shows. */

  /**
   * Get a list of franchises.
   *
   * NOTE: Franchises have no parent object.
   *
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array
   *   Returns a list of franchises.
   */
  public function get_franchises($queryargs = array()) {
    $query = "/franchises/";
    return $this->get_list_data($query, $queryargs);
  }

  /**
   * Get a list of shows.
   *
   * NOTE: Shows do not have to have a parent object.
   *
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array
   *   Returns a list of shows.
   */
  public function get_shows($queryargs = array()) {
    $query = "/shows/";
    return $this->get_list_data($query, $queryargs);
  }

  /**
   * Get a list of assets.
   *
   * @param array $queryargs
   *   The query arguments. For example, show-id or type.
   *
   * @return array
   *   Returns a list of assets.
   */
  public function get_assets($queryargs = array()) {
    $query = "/assets/";
    return $this->get_list_data($query, $queryargs);
  }

  /* Shortcut functions for lists of child objects. */

  /**
   * Get the shows that belong to a specific franchise.
   *
   * @param string $franchise_id
   *   The franchise ID.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array
   *   Returns a list of shows.
   */
  public function get_franchise_shows($franchise_id, $queryargs = array()) {
    return $this->get_child_items_of_type($franchise_id, 'franchise', 'show', $queryargs);
  }

  /**
   * Get the seasons for a specific show.
   *
   * @param string $show_id
   *   The show ID.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array
   *   Returns a list of seasons.
   */
  public function get_show_seasons($show_id, $queryargs = array()) {
    return $this->get_child_items_of_type($show_id, 'show', 'season', $queryargs);
  }

  /**
   * Get the specials for a specific show.
   *
   * @param string $show_id
   *   The show ID.
   * @param array $queryargs
   *    The query arguments.
   *
   * @return array
   *   Returns a list of specials.
   */
  public function get_show_specials($show_id, $queryargs = array()) {
    return $this->get_child_items_of_type($show_id, 'show', 'special', $queryargs);
  }

  /**
   * Get the episodes of a specific season.
   *
   * @param string $season_id
   *   The season ID.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array
   *   Returns a list of episodes.
   */
  public function get_season_episodes($season_id, $queryargs = array()) {
    return $this->get_child_items_of_type($season_id, 'season', 'episode', $queryargs);
  }

  /* Shortcuts for asset lists:  Note that assets can be children of a
   * franchise, show, season, collection, special, or episode BUT can only be
   * the child of one of them.
   * If an asset is a child of an episode it is not a child of a show.
   * These methods also allow filtering by asset_type and window.
   */

  /**
   * Get episode assets.
   *
   * @param string $episode_id
   *   The episode ID.
   * @param string $asset_type
   *   The asset type.
   * @param string $window
   *   The availability window.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array|bool
   *   Returns a list of assets.
   */
  public function get_episode_assets($episode_id, $asset_type = 'all', $window = 'all', $queryargs = array()) {
    return $this->get_child_assets($episode_id, 'episode', $asset_type, $window, $queryargs);
  }

  /**
   * Get special assets.
   *
   * @param string $special_id
   *   The special ID.
   * @param string $asset_type
   *   The asset type.
   * @param string $window
   *   The availability window.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array|bool
   *   Returns a list of special assets.
   */
  public function get_special_assets($special_id, $asset_type = 'all', $window = 'all', $queryargs = array()) {
    return $this->get_child_assets($special_id, 'special', $asset_type, $window, $queryargs);
  }

  /**
   * Get season assets.
   *
   * @param string $season_id
   *   The season ID.
   * @param string $asset_type
   *   The asset type.
   * @param string $window
   *   The availability window.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array|bool
   *   Returns a list of season assets.
   */
  public function get_season_assets($season_id, $asset_type = 'all', $window = 'all', $queryargs = array()) {
    return $this->get_child_assets($season_id, 'season', $asset_type, $window, $queryargs);
  }

  /**
   * Get show assets.
   *
   * @param string $show_id
   *   The show ID.
   * @param string $asset_type
   *   The asset type.
   * @param string $window
   *   The availability window.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array|bool
   *   Returns a list of show assets.
   */
  public function get_show_assets($show_id, $asset_type = 'all', $window = 'all', $queryargs = array()) {
    return $this->get_child_assets($show_id, 'show', $asset_type, $window, $queryargs);
  }

  /**
   * Get franchise assets.
   *
   * @param string $franchise_id
   *   The franchise ID.
   * @param string $asset_type
   *   The asset type.
   * @param string $window
   *   The availability window.
   * @param array $queryargs
   *   The query arguments.
   *
   * @return array|bool
   *   Returns a list of franchise assets.
   */
  public function get_franchise_assets($franchise_id, $asset_type = 'all', $window = 'all', $queryargs = array()) {
    return $this->get_child_assets($franchise_id, 'franchise', $asset_type, $window, $queryargs);
  }

  /* Shortcut functions for images. */

  /**
   * Get franchise images.
   *
   * @param string $franchise_id
   *   The franchise ID.
   *
   * @return array
   *   Returns a list of images for the franchise.
   */
  public function get_franchise_images($franchise_id) {
    return $this->get_images($franchise_id, 'franchise');
  }

  /**
   * Get show images.
   *
   * @param string $show_id
   *   The show ID.
   *
   * @return array
   *   Returns a list of images for the show.
   */
  public function get_show_images($show_id) {
    return $this->get_images($show_id, 'show');
  }

  /**
   * Get season images.
   *
   * @param string $season_id
   *   The season ID.
   *
   * @return array
   *   Returns a list of images for the season.
   */
  public function get_season_images($season_id) {
    return $this->get_images($season_id, 'season');
  }

  /**
   * Get collection images.
   *
   * @param string $collection_id
   *   The collection ID.
   *
   * @return array
   *   Returns a list of images for the collection.
   */
  public function get_collection_images($collection_id) {
    return $this->get_images($collection_id, 'collection');
  }

  /**
   * Get episode images.
   *
   * @param string $episode_id
   *   The episode ID.
   *
   * @return array
   *   Returns a list of images for the episode.
   */
  public function get_episode_images($episode_id) {
    return $this->get_images($episode_id, 'episode');
  }

  /**
   * Get special images.
   *
   * @param string $special_id
   *   The special ID.
   *
   * @return array
   *   Returns a list of images for the special.
   */
  public function get_special_images($special_id) {
    return $this->get_images($special_id, 'special');
  }

  /**
   * Get asset images.
   *
   * @param string $asset_id
   *   The asset ID.
   *
   * @return array
   *   Returns a list of images for the asset.
   */
  public function get_asset_images($asset_id) {
    return $this->get_images($asset_id, 'asset');
  }

  /* File ingest helpers. */

  /**
   * Delete a file from an asset.
   *
   * @param string $asset_id
   *   The asset ID.
   * @param string $type
   *   The type of asset.
   *
   * @return array|bool
   *   Returns the updated object.
   */
  public function delete_file_from_asset($asset_id, $type = 'video') {
    /* deleting a file from an asset is just submitting a null value for it */
    if (empty($asset_id)) {
      return array('errors' => 'no asset id');
    }
    if (!in_array($type, $this->file_types)) {
      return array('errors' => 'invalid file type');
    }
    $attribs = array(
      $type => NULL,
    );
    return $this->update_object($asset_id, 'asset', $attribs);
  }

}
