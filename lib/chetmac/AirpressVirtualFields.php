<?php

/*

creates $post->airpress_data for posts matched by an Airpress config
airpress_data may be a record or a collection

what if multiple records in Airtable match the given Airtable field value or "slug"?
Do I force "getRecord" or "getCollection"
I lean toward allowing collection

*/

class AirpressVirtualFields{

	private $configs;

	function __construct(){

		$this->configs = array();

    	add_filter('template_redirect', array($this,"run"), 1);

    	// TODO: add an option for updating actual post meta/custom fields on post save/update
    	// then look here to impliment: https://codex.wordpress.org/Plugin_API/Action_Reference/save_post

	}

	function register($config){
		if ( !isset($config["api_key"]) && !isset($config["app_id"]) )
			return false;

		if (
			isset($config["posttype_types"]) &&
			isset($config["posttype_wpfield"]) &&
			isset($config["posttype_table"]) &&
			isset($config["posttype_field"])
		){
			$this->configs[] = $config;
		}
	}

	function run(){
		global $wp_query, $airpress;

		foreach($this->configs as $config):

			if (count($wp_query->posts) > 1 && $config["posttype_single"] == true){
				// Config specified we only wanted data on singles, not multiple posts
				return;
			}

			$airtable_field = $config["posttype_field"];
			$airtable_table = $config["posttype_table"];
			$wordpress_field = $config["posttype_wpfield"];

			$filterByFormula = array();

			$airpress->debug("VirtualFields - Using \$post->$wordpress_field from ".count($wp_query->posts)." posts to find matching Airtable records from table ".$airtable_table."->".$airtable_field);

			// Loop through posts to build filter for Airtable request
			foreach($wp_query->posts as $post){
								
				if (in_array($post->post_type,$config["posttype_types"])){
					$wordpress_value = $post->$wordpress_field;

					// wrap string in quotes, otherwise leave it.
					// this could also be where Post Date is transformed so that 
					// airtable records could be queried by matching dates
					// this is also where ACF fields could be used/interpreted.
					$wordpress_value = (is_string($wordpress_value))? '"'.$wordpress_value.'"' : $wordpress_value;

					$filterByFormula[] = sprintf("{%s} = %s", $airtable_field, $wordpress_value);

				}

			}

			$query = new AirpressQuery($config);
			$query->table($airtable_table)->filterByFormula($filterByFormula,"OR");

			$records = AirpressConnect::get($query);

			$index = array();
			foreach($records as $record){

				if (!isset($record["fields"][$airtable_field]))
					continue;

				$airtable_value = $record["fields"][$airtable_field];

				if (empty($airtable_value))
					continue;

				if (!isset($index[$airtable_value]))
					$index[$airtable_value] = array();

				// We're allowing for more than one matching record in the index.
				$index[$airtable_value][] = $record;
			}

			// Loop through posts to inject airtable data into each post
			foreach($wp_query->posts as $post){
				$wordpress_value = $post->$wordpress_field;
				if (isset($index[$wordpress_value])){
					$airpress->debug("Populating wordpress post because ".$wordpress_field." (".$wordpress_value.") matches.");
					// This creates a collection, but false means it WON'T attempt to populate
					$post->AirpressCollection = new AirpressCollection($query,false);
					
					// Since the actual query (used above) used OR to target ALL posts, let's retroactively revise the query so we could
					// theoretically run it again and get the expected results. This will become important later in caching scenarios
					// where the initial request was for many posts, but the next request is for an individual post
					$post->AirpressCollection->query->filterByFormula(sprintf("{%s} = %s", $airtable_field, $wordpress_value));
					
					// Now inject the records into this collection.
					$post->AirpressCollection->setRecords($index[$wordpress_value]);

				}
			}

		endforeach;
	}

}
?>