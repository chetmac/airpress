<?php

/*

creates $post->airpress_data for posts matched by an Airpress config
airpress_data may be a record or a collection

what if multiple records in Airtable match the given Airtable field value or "slug"?
Do I force "getRecord" or "getCollection"
I lean toward allowing collection

*/

class AirpressVirtualFields{

	function init(){

		$configs = get_airpress_configs("airpress_vf");

		if ( ! empty($configs) ){
    		add_filter('template_redirect', array($this,"airtable_lookup"), 1);

    		if ( function_exists("is_cornerstone") && is_cornerstone() == "render" ){
    			add_filter('the_post', array($this,"last_chance_for_data"));
    		}
		}

	}

	function last_chance_for_data($post){
		global $wp_query;
		if ( is_null($wp_query->post) ){
			$wp_query->posts = array($post);
			$this->airtable_lookup();
		}
	}

	function airtable_lookup(){
		
		global $wp_query, $airpress;

		$configs = get_airpress_configs("airpress_vf");

		foreach($configs as $config):

			if (count($wp_query->posts) > 1 && isset($config["single"]) && $config["single"] == 1 ){
				// Config specified we only wanted data on singles, not multiple posts
				return;
			}

			$airtable_field = $config["column"];
			$airtable_table = $config["table"];
			$wordpress_field = $config["field"];

			$filterByFormula = array();

			//airpress_debug(0,"Looking through ".count($wp_query->posts)." posts in wp_query");

			// Loop through posts to build filter for Airtable request
			foreach($wp_query->posts as $post){

				if ( $post->post_type != $config["post_type"] ){
					//airpress_debug(0,"Skipping ".$post->post_type ." because it is not a ".$config["post_type"]);
					continue;
				}

				$wordpress_value = $post->$wordpress_field;

				// wrap string in quotes, otherwise leave it.
				// this could also be where Post Date is transformed so that 
				// airtable records could be queried by matching dates
				// this is also where ACF fields could be used/interpreted.
				$wordpress_value = (is_string($wordpress_value))? '"'.$wordpress_value.'"' : $wordpress_value;
				//airpress_debug(0,"Wordpress VALUE: $wordpress_value");

				$formula = sprintf("{%s} = %s", $airtable_field, $wordpress_value);
				$filterByFormula[] = $formula;

				//airpress_debug(0,$post->post_type."	\$post->".$wordpress_field." is ".$wordpress_value.". Looking up matching records in table {$airtable_table} using formula: $formula");

			}

			if ( empty($filterByFormula) ){
				continue;
			}

			$connection = get_airpress_config("airpress_cx",$config["connection"]);

			$query = new AirpressQuery();

			$query->setConfig($connection);

			$query->table($airtable_table)->filterByFormula($filterByFormula,"OR");

			$records = AirpressConnect::get($query);

			$index = array();
			if ($records):
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
			endif;

			// Loop through posts to inject airtable data into each post
			foreach($wp_query->posts as $post){
				$wordpress_value = $post->$wordpress_field;
				if (isset($index[$wordpress_value])){

					// This creates a collection, but false means it WON'T attempt to populate
					$post->AirpressCollection = new AirpressCollection(clone $query,false);
									
					// Now inject the records into this collection.
					$post->AirpressCollection->setRecords($index[$wordpress_value]);

					// Since the actual query (used above) used OR to target ALL posts, let's retroactively revise the query so we could
					// theoretically run it again and get the expected results. This will become important later in caching scenarios
					// where the initial request was for many posts, but the next request is for an individual post

					$wordpress_value = (is_string($wordpress_value))? '"'.$wordpress_value.'"' : $wordpress_value;
					$post->AirpressCollection->query->filterByFormula(sprintf("{%s} = %s", $airtable_field, $wordpress_value));

				}
			}

		endforeach;
	}

}
?>