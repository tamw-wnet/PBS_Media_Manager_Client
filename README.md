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
TK

### Updating items
TK

### Deleting items
TK

### Special functions
changelog, looking up by TP Media Id
TK





