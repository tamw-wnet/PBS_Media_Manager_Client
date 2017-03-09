# PBS Media Manager Client
PHP class that provides a client for the PBS Media Manager API


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

There is an additional flag required to get unpublished objects, as they're only available with a GET from the edit endpoint -- 

```php
$client->get_asset($cid, true);
```
The client id will need to have edit permission on the object to get that unpublished object


#### Get a list of available shows or franchises 

```php
$client->get_shows();
$client->get_franchises();
```

#### Get a list of child elements of the given CID 

##### Get shows, seasons, episodes 

```php
$client->get_show_seasons($cid);
$client->get_show_specials($cid);
$client->get_season_episodes($cid);
```

NOTE that for any of our 'list' functions, the client returns an unpaged list of all available results, even though the Media Manager API from PBS automatically pages results. 
For very large returns it may be useful to use paging -- the list of all available shows could be long, or a daily news show might have 300+ episodes in a season. 
To implement paging, add an array arg with a 'page' element that is the corresponding paged from the PBS API -- eg 

```php
$client->get_shows(array('page' => 2));
$client->get_season_episodes($cid, array('page' => 3));
```

##### Assets need more filtering, so getting assets allows for more args

```php
$client->get_episode_assets($episode_id, $asset_type='all', $window='all');
$client->get_special_assets($special_id, $asset_type='all', $window='all');
$client->get_season_assets($season_id, $asset_type='all', $window='all');
$client->get_franchise_assets($franchise_id, $asset_type='all', $window='all');
```

##### Getting images is a little different

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
```

The update method either returns TRUE (aka '1') on success or an 'errors' array.

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

Here's an example of combining args -- all assets updated since March 6 at 6:35pm UTC:

```php
$client->get_changelog( array('since' => '2017-03-06T06:35:36.001Z', 'type' => 'asset', 'action' => 'update'));
```

#### Look up an asset by TP Media Id

```php
$client->get_asset_by_tp_media_id($tp_media_id)
```

returns the asset object.


## Changelog

* Version .01 ALPHA -- connects to API, provides basic read, list, update, create, and delete functionality.

## Authors
* William Tam, WNET/IEG
* Augustus Mayo, TPT
* Aaron Crosman, Cyberwoven

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



