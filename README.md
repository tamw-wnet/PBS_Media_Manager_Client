# PBS_Media_Manager_Client
PHP class that provides a client for the PBS Media Manager API

## Usage
Invoke the client as so:

`$client = new PBS_Media_Manager_API_Client($client_id = '', $client_secret = '', $base_endpoint ='');`

The staging base_endpoint is 
https://media-staging.services.pbs.org/api/v1 
for instance.
client_id and client_secret will come from PBS

### Retrieving data
Individual retrievals for asset, episode, special, season, show, franchise by their CID or slug:

```php
$client->get_asset($cid);
$client->get_episode($cid);
$client->get_special($cid);
$client->get_season($cid);
$client->get_show($cid);
$client->get_franchise($cid);
```

There is an additional flag required to get unpublished objects, as they're only available with a GET from the edit endpoint -- 

```php
$client->get_asset($cid, true);
```
The client id will need to have edit permission on the object to get that unpublished object


Return a list of available shows or franchises (paging is optional, by default it will dump the full list

```php
$client->get_shows();
$client->get_franchises();
```

Return a list of child elements of the given CID for shows or seasons 

```php
$client->get_show_seasons($cid);
$client->get_show_specials($cid);
$client->get_season_episodes($cid);
```

Assets need more filtering, so getting assets allows for more args

```php
$client->get_episode_assets($episode_id, $asset_type='all', $window='all');
$client->get_special_assets($special_id, $asset_type='all', $window='all');
$client->get_season_assets($season_id, $asset_type='all', $window='all');
$client->get_franchise_assets($franchise_id, $asset_type='all', $window='all');
```

Images are a little different

```php
$client->get_episode_images($episode_id);
```
etc

### Creating items

There's a base creation method

```php
$client->create_child($cid, $parent_type, $child_type, $attributes);
```
args are
* $cid (of the parent)
* $parent_type can be 'episode', 'special', 'season', 'show'
* $child_type can be 'asset', 'episode', 'special', 'season'
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

The method returns either the CID of the created object, or an 'errors' array.

There will be shortcut methods TK like 'create_episode_asset' etc.



### Updating items

There's a base update method

```php
$client->update_object($cid, $object_type, $attributes);
```

args are
* $cid of the object 
* $object_type can be 'asset', 'episode', 'special', 'season'
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


The update method either returns TRUE (aka '1') on success or an 'errors' array.

There will be helper methods TK specifically for handling video, image, and caption file additions.   These file additions have additional logic required.


### Deleting items

Very simple -- 

```php
$client->delete_object($cid);
```


### Special functions
changelog, looking up by TP Media Id
TK





