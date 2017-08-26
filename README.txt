=== Plugin Name ===
Contributors: chetmac
Donate link: https://www.paypal.me/chetmac
Tags: airtable, custom, custom field, data management, repeater, spreadsheet, remote data, api
Requires at least: 4.6
Tested up to: 4.8
Stable tag: 1.1.42
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Airpress allows you to use your Airtable data inside Wordpress as custom fields, virtual posts, and more!

== Description ==

Airpress is a Wordpress plugin that integrates Airtable with Wordpress, allowing you to use Airtable data the way you want.

= Features =
* Shortcodes for displaying, formating, and looping through fields
* Robust ORM-like PHP methods for advanced queries and coding
* Filters and actions for easily customizing field output
* Advanced caching with asynchronous background refresh
* Automaticly fetch Airtable records based on URL or Post Type
* Easily create completely "virtual" or runtime posts/pages
* Populate/fetch related records (and filter, sort, limit)
* Access records from multiple Airtable bases
* Use multiple Airtable API Keys

[youtube https://www.youtube.com/v/y0UkzQFk5Ok ]

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Find the "Airpress" admin menu item and select 'Airtable Connections'
1. Enter your Airtable API and APP ID (you can add as many as you want!)


== Frequently Asked Questions ==

= Do I have to pay for Airtable? = 

No. Airtable has a free service tier.

= Can I use Airpress to update records in Airtable? =

Airpress provides all the functionality you need to create, retrieve, update, and delete Airtable records.

= API's are slow. Won't this slow my site down? =

No. Airpress caches each API request, refreshing the cached results using the same non-blocking "background" technique as WP Cron. However, depending on the amount of data you're dealing with (and the webhost you're using) the transient caches may affect your page render time. In that case, a page caching solution will speed things right back up.

= Does Airpress require WP Cron? = 
No. Airpress uses the same technique as WP Cron to refresh cached data in the background, but it does not use WP Cron (or any timed jobs) at all.

== Screenshots ==

1. Using the Airtable template 'Restaurant Field Guide', this screenshot shows the 'Cuisines' Virtual Fields configuration. This shows that every request made for post_type 'post' will make a request to the Airtable table 'Cuisines' looking for records where the {Name} column contains the title of the post.
2. You can see that when actually editing a post (for which Virtual Fields are configured), related fields can be populated and displayed! Also note the two ways of accessing multiple records or arrays—using the "glue" attribute or by actually looping. When in a loop, double-squiggly brackets are used instead of shortcodes so that the 'top-level' data can always be accessed.
3. Here is the actual display of the 'burgers' post. You can see that the restaurants related to this cuising were retreived and displayed.
4. The Restaurant page displays several fields from the Restaurants table as well as fields from the related Districts table.
5. This single page is used by all restaurants because it is the selected Airpress Virtual Post template.
6. This is the configuration page for a Virtual Post. You can see that regular expressions are used to match incoming URLs to Airtable records.
7. Visit http://airtable.com/api to get your API Key and APP ID.

== Changelog ==

= 1.1.42 =
* Removed default value for VirualPost configuration "sort" field. It was causing Airtable API requests to respond with 422 "Unprocessable Entity" response (because it was not a valid field) with created 404's (as no records were returned). Thanks @mazoola

= 1.1.41 =
* Removed passing arguments by reference for is_airpress_empty, is_airpress_record, is_airpress_collection because it was creating a null item in the arrayobject of some empty AirpressCollections.

= 1.1.40 =
* Changed capabilities required to manage Airpress options from administrator to manage_options.

= 1.1.39 =
* Added error message when attempting to use apr_populate inside an apr_loop
* Fixed error message on Airpress->Debug Info admin page (thanks @@magisterravn)
* Added option to completely empty cache to Airpress->Debug Info admin page
* Added airpress_flush_cache for those who want a nuclear option. Remember that if you only want to flush the cache for requests specific to a certain page/URL, you can simply append ?fresh=true (or whatever you've configured) to the URL.

= 1.1.38 =
* Fixed fatal error when using Airpress [apr] shortcode on a page without VirtualFields or VirtualPosts (no AirpressCollection). Thanks @mazoola

= 1.1.37 =
* Added loopScope attribute to apr shortcode to reference parent loops
* Added ability to loop and reference attachment fields with apr and apr_loop

= 1.1.36 =
* Improve availability of sort options (thanks @frederickjansen)
* Fixed 'View Details' conflict/bug on plugin list page (thanks anvi7924)
* Fixed inability to delete Virtual Post and Virtual Field configs. (how long was THAT there... sheesh!)

= 1.1.35 =
* Removed extra debug statement

= 1.1.34 =
* Enabled the use of [apr field=''] inside [apr_loop].

= 1.1.33 =
* Fixed apr_loop for attachements fields

= 1.1.32 =
* Fixed bad debugging call (calling method on non object)

= 1.1.31 =
* cacheImageFields will only process images for 25 seconds at a time and resume processing on the next load regardless of if ?fresh=true. The URL to Airtable's small thumbnail will be used in place of all unprocessed images... the idea is to mimick a progressive JPG, showing a low resolution version until the correct resolution is achieved.
* Note: 25 seconds is extremely conservative as fopen, file_get_contents, etc don't count "againts" max_execution_time (typically 30 seconds), however the rotation of images, and the loop logic itself does count. 

= 1.1.30 =
* up to 10 nested loops now supported [apr_loop][apr_loop1][/apr_loop1][apr_loop] 

= 1.1.29 =
* fixed cacheImageFields path and cleanup debug output for rotated images

= 1.1.28 =
* changed cacheImageFields from file_get_contents to "chunked" fopen for better reliability (in hindsight this probably isn't any more reliable... not better, not worse, just different. See 1.1.31 for what is the real solution to reliably downloading images )

= 1.1.27 =
* bugs

= 1.1.26 =
* cacheImageFields no longer duplicates file extensions on cached full images(.jpg.jpg). Thanks @mcloone
* VirtualPosts admin will not apply the airpress_virtualpost_query filter on save. This will keep related queries and cached images from slowing down what should be a simple test to see if a given URL will match any records.

= 1.1.25 =
* Fixed using shortcode apr_loop when it triggered fatal error because of array_unique being used on an array of objects

= 1.1.24 =
* Added Virtual Post configuration object to airpress_virtualpost_query filter
* Bug fixes

= 1.1.23 =
* cacheImageFields will no longer download the full image unless absolutely neccessary!
* cacheImageFields will reorient images using read_exif_data when available. (I'm looking at you Pressable...)

= 1.1.22 =
* airpress_virtualpost_query wasn't actually applied to $query
* cacheImageFields now works with custom sizes! Thanks @mcloone for pointing out the bug 
* cacheImageFields now politely attempts to create airpress-image-cache directory instead of simply whining about it
* fixed issue with VirtualPost post_title when dollar sign was in the Airtable field data

= 1.1.21 =
* Fixed bug with cacheImageFields when not saving full sized image

= 1.1.20 =
* Changed batch size from 500 to 250 because of curl timeout when Airtable was slow
* Removed error condition where WP Error was accessed as array

= 1.1.19 =
* Fixed strange error when using wp cli to update plugins

= 1.1.18 =
* Found and fixed another id => record_id instance

= 1.1.17 =
* AirpressConnect::update() now accepts a config object or int or string just like AirpressQuery()

= 1.1.16 =
* AirpressConnect::create() now accepts a config object or int or string just like AirpressQuery()

= 1.1.15 =
* created cacheImageFields() to locally cache Airtable images and allow for customized thumbnail sizes.

= 1.1.14 =
* deprecated $record->createdTime() and added $record->created_time() for consistency
* Fixed typo in documentation

= 1.1.13 =
* Ensured VirtualPosts still look for page-{post_name}.php template

= 1.1.12 =
* Added action to override deferred_queries function
* Started using connection config's api_url
* Added AirpressQuery->param() and ->prop for new/generic key/values

= 1.1.11 =
* Fixed blank permalink bug
* disambiguated record_id from id for getFieldValues and other functions
* Fixed error when batch request was empty
* Admin Toggle Debug link jumps to top of page now
* Improved path handling for apr_include shortcode

= 1.1.10 =
* Ensured populateRelatedField submits queries in batches to stay within GET request limits
* Fixed get_permalink() for virtual pages

= 1.1.9 =
* Added compatibility with Cornerstone page builder
* Added 'test url' field for Virtual Posts configurations

= 1.1.8 =
* Fixed Virtual Post 'post_name' and 'post_title'!

= 1.1.7 =
* Changed expandable debug message to blue

= 1.1.6 =
* Fixed Debug option so logfile can actually be created by plugin
* Added debug option to enable on-screen debug log as well as logfile

= 1.1.5 =
* Added video tutorial to readme
* fixed undefined index: filterByFormula. Thanks @gobot

= 1.1.4 =
* multiple [apr_populate] or populateRelatedField calls are gracefully handled
* add wrapper parameter for [apr] shortcode to compliment existing glue parameter
* updated README with correct shortcode names

= 1.1.3 =
* VirtualPost post_name setting field can reference fields and matches now
* bigfix where virtualposts caught all urls

= 1.1.2 =
* Ensured Compatibility with php 5.3

= 1.1.1 =
* Removed getConfig()[0] notation

= 1.1 =
* Added AirpressCollection->forget($keys)
* Added is_airpress_force_fresh() 

= 1.0 =
* Hello world!

== Basic Usage ==

= Automatic Airtable Requests =
Airpress comes with two built-in extensions—Virtual Fields and Virtual Posts—both of which are used to map certain Wordpress objects or URLs to Airtable records (one to one or one to many or many to many). Records that are automatically requested are stored in the variable $post->AirpressCollection

`
<?php
$e = $post->AirpressCollection[0];
echo $e["Name"].": ".$e["Start Date"]."<br>";
?>
`
or you can use the shortcode wherever shortcodes are allowed:
`
[apr field="Name"]: [apr field="Start Date"]
`

= Manual Airtable Requests =
Airpress can be used to manually request records from Airtable by specifying the desired table, view, filter, sort, etc.

`
<?php
$query = new AirpressQuery();
$query->setConfig("default");
$query->table("Events")->view("Upcoming");
$query->addFilter("{Status}='Enabled'");

$events = new AirpressCollection($query);

foreach($events as $e){
  echo $e["Name"].": ".$e["Start Date"]."<br>";
}
?>
`

Both manual and automatic requests can be configured and used entirely within the Wordpress admin dashboard or entirely via PHP code.

= Related Records =
Related records may easily be retrieved both in PHP code and via shortcodes. When a related/linked field is "populated", the linked records actually replace the corresponding RECORD_ID().

Consider a base with two related tables: _Events_ and _Locations_, if you populate the "Events" field of the Locations collection, it goes from being an array of RECORD_ID()s to an AirpressCollection with AirpressRecords.

`
[apr_populate field="Location" relatedTo="Locations"]
[apr_populate field="Location|Owner" relatedTo="Contacts"]
[apr name="Name"] at [airfield name="Location|Name"] owned by [airfield name="Location|Owner|Name"]
`

`
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
`

You may also specify a complete query with which to retrieve the linked records. For example, if you want to find all upcoming Events for a particular Location:

`
// default is the name of the Airtable Connection configuration
$query = new AirpressQuery("Locations", "default");
$query->filterByFormula("{Name}='My Local Pub'");
$locations = new AirpressCollection($query);

$query = new AirpressQuery("Events", "default");
$query->filterByFormula("IS_BEFORE( TODAY(), {Start Date} )");
$locations->populateRelatedField("Events", $query);
`

This will update each record in the $locations collection with associated events that are after TODAY(). Any other linked events will be removed—NOT from the Airtable record, just from the $locations collection.

== Airpress Collections and Records ==
There are two reasons why AirpressCollections should be used even when dealing with just a single AirpressRecord.

1. All Airtable linked fields are arrays, regardless of it you uncheck 'Allow Linking to Multiple Records'. And until the Airtable Metadata API is available there's no way to know if your linked record *might* contain more than one record. 
2. Airpress allows you to automatically (or manually) retrieve one or more Airtable records. And when you're dealing with populating related fields for *multiple* records, Airpress intelligently aggregates ALL the RECORD_ID()s for the same field in all the records, making a single API request, then "collates" the resulting records back into the appropriate "parent" record. Essentially, Airpress does **_everything_** it can do to minimize the number of API requests. Dealing with just a single record would mean many many more API requests.

Both AirpressCollection() and AirpressRecord() are PHP [ArrayObjects](http://php.net/manual/en/class.arrayobject.php). This means that they behave like arrays even though they can store custom properties and methods as well. So for an AirpressRecord $r["Field Name"] and $r->record_id() both work! This allows easy foreach iteration through records and fields while allowing custom methods as well.

Airpress needs better documentation regarding how it "implodes" fields from multiple records when using the shortcodes and AirpressCollection methods.

== Airtable Connections ==
Airtable Connections store your API credentials and allow Airpress to "talk" to the Airtable API. You'll need to enter your [API KEY](https://support.airtable.com/hc/en-us/articles/219046777-How-do-I-get-my-API-key-) and [APP ID](https://airtable.com/api).

Multiple connections to the same base can be used if you want different multiple caching or logging settings. For example, you may have a single base but you want all requests made to the Events table to be refreshed every 5 minutes, however any requests made to the Locations table only need to be refreshed daily.

The name you give the connection is how you'll refer to this Connection from other settings pages and in any PHP code you write. *(You can also refer to the first connection configuration numerically using a zero-based index)*.

`
<?php
$query = new AirpressQuery();
$query->setConfig("default");
$query->table("My Airtable table name");
?>
`

== Caching ==

The Airpress cache saves the results of each Airtable API request using a hash of the request parameters. Each subsequent identical request will be served from the local database.

When a cached request is no longer considered "fresh", the cached result will be served one last time while the cache is updated asynchronously in the background so as not to slow the page load.

If a cached request has expired completely, then the page load will wait for "fresh" data to be requested.

Airpress gives you control over what is considered "fresh" and "expired" data via these two variables:

**Refresh**: The number of seconds after which Airpress will perform a background refresh on a cached Airtable API request.

**Expire**: The number of seconds after which Airpress will no longer serve the cached request.

**Query var to force refresh**: During development (and even while in production) it can be extremely helpful to force Airpress to load *all* requests directly from Airpress. Hosts like GoDaddy and MediaTemple already provide a query var to flush the cache, so if you set Airpress' force refresh to the same var, you can flush everything at once.

Assuming "Refresh" is set for five minutes and "Expire" is set for an hour:

1. Visitor loads a page triggering a request at 8:00am. No cache exists, so data is fetched in real-time, significantly slowing the loading of the page.
1. Visitor reloads the page at 8:04am, so data is fetched from the cache.
1. Visitor reloads the page at 8:06am (1 minute past the refresh time), so data is loaded from the cache while an asynchronous background request is made to refresh the cache. Page load is NOT affected.
1. Visitor reloads the page at 9:07am (1 minute past the exire time—remember the data was last refreshed at 8:06am), so the data is fetched from Airtable in real-time, significantly slowing the loading of the page.

Airpress plays nicely with object caches and page caches. Please note that some hosts aggressively purge the transient cache (which is where cached requests are stored) resulting in more requests than might be expected. Also, if you have a page cache that bypasses PHP and directly serves cached HTML, then obviously Airpress won't be able to check the "freshness" of the data until the cached page is regenerated.


== Shortcodes ==
* apr_populate
* apr
* apr_include
* apr_loop
* apr_loop_0..10

== filters ==
* airpress_configs ($configs array, option group "airpress_cx, airpress_vf, airpress_vp//
* airpress_include_path_pre ($include)
* airpress_include_path
* airpress_shortcode_filter_{date}
* airpress_shortcode_filter
* airpress_virtualpost_query
* airpress_virtualpost_last_chance

== Actions ==
* airpress_virtualpost_setup

== Functions ==
* airpress_debug
* is_airpress_force_fresh
* get_airpress_configs
* is_airpress_record
* is_airpress_empty
* is_airpress_collection