# PBS Media Manager Client
PHP class that provides a client for the PBS Media Manager API.  Full documentation for that API is at <https://docs.pbs.org/display/CDA/Media+Manager+API>.

## Requirements
The client requires at least PHP version 5.6, and the cURL library <http://php.net/manual/en/book.curl.php>.

## Usage
Invoke the client as so:

`$client = new PBS_Media_Manager_API_Client($client_id = '', $client_secret = '', $base_endpoint ='');`

The staging base_endpoint is 
https://media-staging.services.pbs.org/api/v1 
for instance.
client_id and client_secret will come from PBS

### Retrieving data

#### Get an individual asset, episode, special, season, show, franchise by it's CID or slug:

```php
$client->get_asset($cid);
$client->get_episode($cid);
$client->get_special($cid);
$client->get_season($cid);
$client->get_show($cid);
$client->get_franchise($cid);
```

##### Query args for filtering by platform and/or audience 

Any request either to the individual assets above or to lists below can include as it's final arg an array containing querystring arguments.  For instance

```php
$client->get_show($cid, array('platform-slug' => 'partnerplayer'));
```
This 'platform-slug' argument is particularly important, because if a show, asset, or franchise is set in the Media Manager environment to be available in anything other than 'all platforms', the object will only be retrieved if you include the platform-slug for one of the platforms that the object is explicitly set to be available for.

Multiple platform-slugs -- or any filter argument with multiple values -- should be passed in an array, like so
```php
$client->get_asset($cid, array('platform-slug' => array('partnerplayer', 'bento')));
```

This array will auto-construct the appropriate query string, including escaping characters as needed.  Check the PBS Assets documentation <https://docs.pbs.org/display/CDA/Assets> for available query args -- the best documented examples are for platform-slug, which has possible values of 'allplatforms', 'partnerplayer', 'bento', 'pbsorg', 'videoportal'.

##### Shows and audiences

'shows' have 'audience-scopes'.  This is unique to 'shows'.  get_shows() will return all shows with the 'all_platforms' platform slug, but those shows may be otherwise unavailable.  <https://docs.pbs.org/display/CDA/Shows#Shows-Methods> includes details about the 'national', 'local', and 'kids' audience-scopes.  If a show has the 'local' audience-scope, an 'audience' argument (call letters) must also be included.  These args should be included in the same array as other filtering args, eg
```php
$client->get_shows( array('platform-slug' => 'partnerplayer', 'audience-scope' => 'local', 'audience' => 'weta') );
```

To get a list of shows that are the UNION of multiple audiences-scopes and/or audiences, those arguments should be an array if they have multiple values, like so
```php
$client->get_shows( array('platform-slug' => 'partnerplayer', 'audience-scope' => array('local','national'), 'audience' => array('wnet','wliw') ) );
```


##### Unpublished assets

There is an additional flag required to get unpublished assets, as they're only available with a GET from the edit endpoint -- 

```php
$client->get_asset($cid, true);
```
The client id will need to have edit permission on the object to get that unpublished object.  The 'platform-slug' is NOT required to get the edit endpoint, but it will not fail --

```php
$client->get_asset($cid, true, array('platform-slug' => 'partnerplayer'));
```
will not fail.


#### Get a list of available shows or franchises 

```php
$client->get_shows();
$client->get_franchises();
$client->get_shows(array('platform-slug' => 'partnerplayer', 'slug' => 'newshour'));
$client->get_franchises(array('platform-slug' => 'bento'));
```

As noted above, a query argument array is an optional argument.

#### Get a list of available assets 

```php
$client->get_assets();
$client->get_assets(array('platform-slug' => 'partnerplayer', 'show-id' => 
{show_id}));
$client->get_assets(array('platform-slug' => 'partnerplayer', 'show-id' => {show_id}, 'type' => 'full_length));
```

As noted above, a query argument array is an optional argument. Adding the show-id will return all assets belonging to that show, regardless of the hierarchy (i.e., it returns assets for all seasons, episodes, or specials connected to the show).

You can look up an asset by TP Media ID using this method also:
```php
$client->get_assets(array('tp-media-id' => $tp_media_id, 'platform-slug' => 'partnerplayer'));
```


#### Get a list of child elements of the given CID 

```php
$client->get_child_items_of_type($parent_id, $parent_type, $type, $queryargs = array(), $include_metadata = FALSE);
```

To get a list of the assets for an episode that are available in the partner player,

```php
$client->get_child_items_of_type($episode_id, 'episode', 'asset', array('platform-slug' => 'partnerplayer'));
```


##### Get shows, seasons, episodes 

```php
$client->get_show_seasons($cid);
$client->get_show_specials($cid);
$client->get_season_episodes($cid);
```

NOTE that for any of our 'list' functions, the client returns an unpaged list of all available results, even though the Media Manager API from PBS automatically pages results. 
For very large returns it may be useful to use paging -- the list of all available shows could be long, or a daily news show might have 300+ episodes in a season. 

To implement paging, add an array arg with a 'page' element that is the corresponding page from the PBS API -- eg 

```php
$client->get_shows(array('page' => 2));
$client->get_season_episodes($cid, array('page' => 3));
```

Additionally you can combine the 'page' element with a 'page-size' element to control how many elements the returned page contains. 'page-size' generally supports sizes of 1-50.

```php
$client->get_shows(array('page-size' => 10, 'page' => 2)); // Returns shows 11 through 20
```

This array can also contain the platform-slug values etc.

##### Assets need more filtering, so getting assets allows for more args

These next four asset-specific functions should probably be DEPRECATED in favor of the more generic get_child_items_of_type().

```php
$client->get_episode_assets($episode_id, $asset_type='all', $window='all');
$client->get_special_assets($special_id, $asset_type='all', $window='all');
$client->get_season_assets($season_id, $asset_type='all', $window='all');
$client->get_franchise_assets($franchise_id, $asset_type='all', $window='all');
```

get_episode_assets() etc returns the list of assets on success, or false if none are returned.  
If there's an error with the request (such as a bad episode_id, some server problem, or a bad parameter), an 'errors' array is returned.

In the above examples, the asset_type arg doesn't work well and the window arg doesn't work at all.  PBS changed their API model to allow those filters in the query string, so something like so is a better format example :

```php
$client->get_child_items_of_type($episode_id, 'episode', 'asset', array('platform-slug' => 'partnerplayer', 'type' => 'full_length', 'window' => 'all_members'));
```



##### Getting images is a little different

```php
$client->get_episode_images($episode_id);
```
etc

```php
$client->get_images($parent_id, $parent_type, $image_profile = '')
```

### Creating items

There's a base creation method

```php
$client->create_child($cid, $parent_type, $child_type, $attributes);
```
args are
* $cid (of the parent)
* $parent_type can be 'episode', 'special', 'season', 'show'
* $child_type can be 'asset', 'episode', 'special', or 'season'
* $attributes is an array, matching the required/optional attributes for the child.  For instance an asset might have

```php
$attributes = array(
  'title_short' => 'My title',
  'availabilities' => array(
     'public' => array( 
       'start' => '1970-01-01 00:00:00',
       'end' => '2020-01-01 00:00:00'
     )
  )
);
```
while a 'season' has much less metadata and would be 
```php
$attributes = array(
  'ordinal' => 2019
);
```

The method returns either the CID of the created object, or an 'errors' array.

For more details on arguments for create arrays see the PBS documentation at <https://docs.pbs.org/display/CDA/Create>

There will be shortcut methods TK like 'create_episode_asset' etc.



### Updating items

There's a base update method

```php
$client->update_object($cid, $object_type, $attributes);
```

args are
* $cid of the object 
* $object_type can be 'asset', 'episode', or 'special'.   For the moment, PBS doesn't support 'season' (NOTE: possible that 'season' can be updated but not sure how that would work -- changing the ordinal for a season has a minimal use case), 'show' (NOTE: possible that 'show' can be updated via API but most metadata is read-only by the producer), etc.
* $attributes is an array, matching the required/optional attributes for the object.  For instance an asset might have
```php
$attributes = array(
  'title_short' => 'My title',
  'availabilities' => array(
     'public' => array(
       'start' => '1970-01-01 00:00:00',
       'end' => '2020-01-01 00:00:00'
     )
  )
);
```

The update method either returns TRUE (aka '1') on success or an 'errors' array.

For more examples formatting update arrays, see the PBS API documentation at <https://docs.pbs.org/display/CDA/Update#Update-update>

In order to update an asset's video, caption, or image, any existing video/caption/image has to be deleted.  Here's a helper method:

```php
$client->delete_file_from_asset($asset_id, $type='video');
```

This is a wrapper for the update_object method.  It submits a NULL payload for the video/caption/image, which is how files are deleted in the API.  It returns true or an errors array.

There will be helper methods TK specifically for handling video, image, and caption file additions.   These file additions have additional logic required.


### Deleting items

Very simple -- 

```php
$client->delete_object($cid);
```


### Special functions

#### Get the changelog of what's changed in the Media Manager database

```php
$client->get_changelog($args);
```
where $args can either be empty or an array with one or more of the following:

* action can be 'update', 'delete', 'create'
* type can be 'asset', 'episode', 'special', 'season', 'show', 'franchise' 
* since is a timestamp in UTC, and must be formatted like so: 2017-03-06T06:35:36.001Z
* id is an object id

More details on options in the PBS documentation from <https://docs.pbs.org/display/CDA/Changelog+Endpoint>

No 'since' given will dump all changes in the last 24 hours.

Here's an example of combining args -- all assets updated since March 6 at 6:35pm UTC, sorted oldest to newest

```php
$client->get_changelog( array('since' => '2017-03-06T06:35:36.001Z', 'sort' => 'timestamp', 'type' => 'asset', 'action' => 'update'));
```

#### Look up an asset by TP Media ID
The TP Media ID that was used throughout the "old" COVE API, Merlin, and is still surfaced in the URLs for various videos, is an 10-digit number, like 3009849339, and would appear (for instance) in the URL for an iframe, like https://player.pbs.org/portalplayer/3009849339/ or https://player.pbs.org/widget/partnerplayer/3009849339/ .  

```php
$client->get_asset_by_tp_media_id($tp_media_id)
```

returns the asset object.

Note that this can also be accomplished with 

```php
$client->get_assets(array('tp-media-id' => $tp_media_id));
```

NOTE: TP Media IDs are NOT, by default, returned in the data from the Media Manager API.  This is set on a per-keypair basis.  If TP Media ID's aren't appearing in your asset results, or if you want to do the lookup by TP Media ID, you'll need to submit a ticket to PBS via the PBS Digital Support portal; "Please enable TP Media IDs for my Media Manager API Keys" should do it.   PBS may at some point phase out the TP Media ID.


## Changelog
* Version 2.0.4 -- documentation updates and bugfixes

* Version 2.0.3 -- new get_images behavior

* Version 2.0.2 -- proper handling and documentation for filters with multiple values

* Version 2.0 -- changes to accomodate new PBS restriction on returning objects that are available on only specific platforms, and significant formatting fixes

* Version 1.1 -- changed update object to take account for new PBS functionality of updatable episodes and specials, bugfix for certain url entities needing to be escaped

* Version 1.0 STABLE -- refined results, documentation in place, can perform all core functions that the API provides.

* Version .01 ALPHA -- connects to API, provides basic read, list, update, create, and delete functionality.

## Authors
* William Tam, WNET/IEG
* Augustus Mayo, TPT
* Aaron Crosman, Cyberwoven
* Jess Snyder, WETA

## Licence
The PBS Media Manager Client is licensed under the GPL v2 or later.

> This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

> This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

> You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA



