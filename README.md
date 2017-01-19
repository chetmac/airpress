# Airpress - BETA
[Wordpress](http://wordpress.org) is a *Content Management System*.

[Airtable](http://airtable.com) is a *Data Managment System*.

Airpress is a Wordpress plugin that integrates the two systems, allowing you to display Airtable data the way you want.

# Features
* Connect to multiple Airtable bases
* Use multiple Airtable API Keys
* Advanced caching with asynchronous background refresh
* Methods/Shortcodes for fetching related records
* Methods/Shortcodes for displaying record fields
* Actions and Filters for customization
* Virtual Posts created on-the-fly from Airtable records
* Virtual Fields associate Airtable records with Wordpress posts replacing the need for extensive Wordpress Custom Fields.

# Basic Usage

**Automatic Airtable Requests**  
Airpress comes with two extensions—Virtual Fields and Virtual Posts—both of which are used to map certain Wordpress objects or URLs to Airtable records (one to one or one to many or many to many). Records that are automatically requested are stored in the variable $post->AirtableCollection

```
<?php
$e = $post->AirtableCollection[0];
echo $e["Name"].": ".$e["Start Date"]."<br>";
?>
```
or you can use the shortcode wherever shortcodes are allowed:
```
[airfield name="Name"]: [airfields name="Start Date"]
```

**Manual Airtable Requests**  
Airpress can be used to manually request records from Airtable by specifying the desired table, view, filter, sort, etc.

```
<?php
$query = new AirpressQuery();
$query->setConfig("default");
$query->table("Events")->view("Upcoming");
$query->addFilter({Status}='Enabled');

$events = new AirpressCollection($query);

foreach($events as $e){
  echo $e["Name"].": ".$e["Start Date"]."<br>";
}
?>
```

Both manual and automatic requests can be configured and used entirely within the Wordpress admin dashboard or entirely via PHP code.

# Related Records
Related records may easily be retrieved both in PHP code and via shortcodes. When a related/linked field is "populated", the linked records actually replace the corresponding RECORD_ID().

Consider a base with two related tables: _Events_ and _Locations_, if you populate the "Events" field of the Locations collection, it goes from being an array of RECORD_ID()s to an AirpressCollection with AirpressRecords.

```
<?php
$events = $post->AirpressCollection();
$linked_field = "Location";
$linked_table = "Locations";
$events->populateRelatedField($linked_field, $linked_table);

// You can even populate linked fields of linked fields!
$events->populateRelatedField("Location|Owner", "Contacts");

echo $events[0]["Name"]." at ";
echo $events[0]["Location"][0]["Name"]." owned by";
echo $events[0]["Location"][0]["Owner"][0]["Name"]."<br>";
?>
```

```
[airpress_populate field="Location" relatedTo="Locations"]
[airpress_populate field="Location|Owner" relatedTo="Contacts"]
[airfield name="Name"] at [airfield name="Location|Name"] owned by [airfield name="Location|Owner|Name"]
```

You may also specify a complete query with which to retrieve the linked records. For example, if you want to find all upcoming Events for a particular Location:

```
// default is the name of the Airtable Connection configuration
$query = new AirpressQuery("Locations", "default");
$query->filterByFormula("{Name}='My Local Pub'");
$locations = new AirpressCollection($query);

$query = new AirpressQuery("Events", "default");
$query->filterByFormula("IS_BEFORE( TODAY(), {Start Date} )");
$locations->populateRelatedField("Events", $query);
```

This will update each record in the $locations collection with associated events that are after TODAY(). Any other linked events will be removed—NOT from the Airtable record, just from the $locations collection.

# Airpress Collections and Records
There are two reasons why AirpressCollections should be used even when dealing with just a single AirpressRecord.
1. All Airtable linked fields are arrays, regardless of it you uncheck 'Allow Linking to Multiple Records'. And until the Airtable Metadata API is available there's no way to know if your linked record *might* contain more than one record. 
2. Airpress allows you to automatically (or manually) retrieve one or more Airtable records. And when you're dealing with populating related fields for *multiple* records, Airpress intelligently aggregates ALL the RECORD_ID()s for the same field in all the records, making a single API request, then "collates" the resulting records back into the appropriate "parent" record. Essentially, Airpress does **_everything_** it can do to minimize the number of API requests.

Both AirpressCollection() and AirpressRecord() are PHP [ArrayObjects](http://php.net/manual/en/class.arrayobject.php). This means that they behave like arrays even though they can store custom properties and methods as well.

Airpress needs better documentation regarding how it "implodes" fields from multiple records when using the shortcodes and AirpressCollection methods.

# Airtable Connections
Airtable Connections store your API credentials and allow Airpress to "talk" to the Airtable API. You'll need to enter your [API KEY](https://support.airtable.com/hc/en-us/articles/219046777-How-do-I-get-my-API-key-) and [APP ID](https://airtable.com/api).

Multiple connections to the same base can be used if you want different multiple caching or logging settings. For example, you may have a single base but you want all requests made to the Events table to be refreshed every 5 minutes, however any requests made to the Locations table only need to be refreshed daily.

The name you give the connection is how you'll refer to this Connection from other settings pages and in any PHP code you write. *(You can also refer to the first connection configuration numerically using a zero-based index)*.

```
<?php
$query = new AirpressQuery();
$query->setConfig("default");
$query->table("My Airtable table name");
?>
```

# Caching

The Airpress cache saves the results of each Airtable API request using a hash of the request parameters. Each subsequent identical request will be served from the local database.

When a cached request is no longer considered "fresh", the cached result will be served one last time while the cache is updated asynchronously in the background so as not to slow the page load.

If a cached request has expired completely, then the page load will wait for "fresh" data to be requested.

Airpress gives you control over what is considered "fresh" and "expired" data via these two variables:

**Refresh**: The number of seconds after which Airpress will perform a background refresh on a cached Airtable API request.

**Expire**: The number of seconds after which Airpress will no longer serve the cached request.

**Query var to force refresh**: During development (and even while in production) it can be extremely helpful to force Airpress to load *all* requests directly from Airpress. Hosts like GoDaddy and MediaTemple already provide a query var to flush the cache, so if you set Airpress' force refresh to the same var, you can flush everything at once.

Assuming "Refresh" is set for five minutes and "Expire" is set for an hour:

1. Visitor loads a page triggering a request at 8:00am. No cache exists, so data is fetched in real-time, significantly slowing the loading of the page.
2. Visitor reloads the page at 8:04am, so data is fetched from the cache.
3. Visitor reloads the page at 8:06am (1 minute past the refresh time), so data is loaded from the cache while an asynchronous background request is made to refresh the cache. Page load is NOT affected.
4. Visitor reloads the page at 9:07am (1 minute past the exire time—remember the data was last refreshed at 8:06am), so the data is fetched from Airtable in real-time, significantly slowing the loading of the page.

Airpress plays nicely with object caches and page caches. Please note that some hosts aggressively purge the transient cache (which is where cached requests are stored) resulting in more requests than might be expected. Also, if you have a page cache that bypasses PHP and directly serves cached HTML, then obviously Airpress won't be able to check the "freshness" of the data until the cached page is regenerated.

# To Do
1. Do cleanup on deactivation and uninstallation
2. Keep improving readme/documentation
3. Check logfile permissions before attempting to write
4. Find other developers to help improve code!
5. Create Extensions for maps
6. Create Extensions for slackbots
  