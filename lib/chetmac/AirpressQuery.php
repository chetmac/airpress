<?php

class AirpressQuery {
	
	private $runtime_start;
	private $config;
	private $parameters;
	private $properties;

	private $relatedQueries;

	private $errors;

	public function toArray(){
		return [];
	}

	public function __construct($table=null,$config=null,$params=array()){
		global $airpress;

		$this->runtime_start = microtime(true);

		// Parameters will be encoded and sent to airtable as the query string
		$this->parameters = array();

		// properties will be used to construct the request itself
		$this->properties = array();

		if (isset($table)){
			$this->table($table);
		} else if (isset($params["table"])){
			$this->table($table);
		}

		if (isset($config)){
			$this->setConfig($config);
		} else {
			$this->config = array();
		}

		// set defaults from params
		if (isset($params["table"])){
			$this->table($params["table"]);
		}

		if (isset($params["view"])){
			$this->view($params["view"]);
		}

		if (isset($params["filterByFormula"])){
			$this->filterByFormula($params["filterByFormula"]);
		}

		if (isset($params["sort"])){
			$this->sort($params["sort"]);
		}

		if (isset($params["maxRecords"])){
			$this->maxRecords($params["maxRecords"]);
		}

		if (isset($params["pageSize"])){
			$this->pageSize($params["pageSize"]);
		}

		if (isset($params["fields"])){
			$this->fields($params["fields"]);
		}

		// Properties
		if (isset($params["preserveSort"])){
			$this->preserveSort($params["preserveSort"]);
		}

		if (isset($params["refresh"])){
			$this->setRefreshAfter($params["refresh"]);
		}

		if (isset($params["expire"])){
			$this->setExpireAfter($params["expire"]);
		}

		if (isset($params["cacheImageFields"])){
			$cif = $params["cacheImageFields"];

			if ( isset($cif["fields"]) ){
				$cif = array($cif);
			}

			foreach($cif as $c){

				if ( isset($c["fields"]) ){
					$sizes = ( isset($c["sizes"]) ) ? $c["sizes"] : null;
					$this->cacheImageFields($c["fields"],$sizes);
				}

			}

		}

	}

	public function hash(){
		return md5(serialize(array_merge($this->parameters,$this->properties,$this->config)));
	}

	public function getParameters(){
		return $this->parameters;
	}

	public function getProperties(){
		return $this->properties;
	}

	public function getAppId(){
		return $this->config["app_id"];
	}

	public function setAppId($value){
		$this->config["app_id"] = $value;
		return $this;
	}

	public function getApiKey(){
		return $this->config["api_key"];
	}

	public function setApiKey($value){
		$this->config["api_key"] = $value;
		return $this;
	}

	public function getApiUrl(){
		return $this->config["api_url"];
	}

	public function setCachedResults(&$records){
		
		$complete = $this->localizeImages($records);

		if ( !$complete || $this->getRefreshAfter() <= 0 || $this->getExpireAfter() <= 0){
			return false;
		}

		$transient = "aprq_".$this->hash();

		$storage = array(
			"created_at" => time(),
			"records" => $records
		);

		return set_transient( $transient, $storage, $this->getExpireAfter() );
	}

	public function getCachedResults(){
		global $airpress;
		$transient = "aprq_".$this->hash();

		$fresh_param = $this->config["fresh"];
		if (isset($_GET[$fresh_param])){
			//delete_transient($transient);
			return false;
		}

		$storage = get_transient($transient);

		if (isset($storage["records"]) && isset($storage["created_at"])){

			$age = ( time() - $storage["created_at"] );
			//airpress_debug("This query cache is $age seconds old and refresh limit is ".($this->config["refresh"] - $age) );
			if ( $age < $this->config["refresh"] ){
				airpress_debug(0,"Cached Query ".$this->toString()." needs refreshing in ".($this->config["refresh"] - $age) );
			} else {
				airpress_debug($this->config,"DELAYED QUERY : /".$this->getTable()." : ".$this->hash()."	".$this->toString());
				$airpress->defer_query($this);
			}

			return $storage["records"];

		}

		return false;
	
	}

	public function toString(){
		$params = $this->parameters;

		/*
		This little beauty turns: AND(OR(RECORD_ID()='recXXX', RECORD_ID()='recXXX'),OR(RECORD_ID()='recXXX', RECORD_ID()='recXXX'))
		into this: AND(OR(...one of 2 ids...),OR(...one of 2 ids...))
		This is very helpful for debugging output as RECORD IDS aren't exactly human readable anyway
		but we need to know how many we're dealing with. This little two hour exercise taught me that
		you can have SUB-FREAKING-GROUPS in regular expressions... sigh... had I only known.
		*/

		$pattern = "/OR\((RECORD_ID\(\)='([^']+)',?\s?)+\)/";
		if ( isset($params["filterByFormula"]) && preg_match_all($pattern, $params["filterByFormula"],$matches) ){
			foreach($matches[0] as $matched_string){
				// count how many we replace
				$pattern = "/RECORD_ID\(\)='([^']+)',?\s?/";
				preg_match_all($pattern, $matched_string, $matches);
				$params["filterByFormula"] = str_replace($matched_string, "OR(...one of ".count($matches[0])." ids...)", $params["filterByFormula"]);
			}
		}

		return $this->properties["table"]."?".urldecode(http_build_query($params));
	}

	public function refreshCachedResults(){
		global $airpress;
		$results = AirpressConnect::_get($this);
	}

	public function getConfig(){
		if ( empty($this->config) ){
			return false;
		} else {
			return $this->config;
		}
	}

	public function setConfig($config){
		global $airpress;

		if ( ! is_array($config) ){
			$config = get_airpress_config("airpress_cx",$config);
		}

		$this->config = $config;
	}

	public function hasSort(){
		return isset($this->parameters["sort"]);
	}

	####################################
	## PROPERTIES 
	####################################

	function table($value){
		$this->properties["table"] = $value;
		return $this;
	}

	function cacheImageFields($fields,$sizes=null,$crop=false,$regenerate=false){

		if ( !isset($this->properties["cacheImageFields"]) ){
			$this->properties["cacheImageFields"] = array();
		}

		if ( is_string($fields) ){
			$fields = array($fields);
		}

		if ( is_null($sizes) ){
			$sizes = array("full");
		} else if ( is_string($sizes) ){
			$sizes = array($sizes);
		}

		$this->properties["cacheImageFields"][] = array(
													"fields" => $fields,
													"sizes" => $sizes,
													"crop" => $crop,
													"regenerate" => $regenerate
													);

		return $this;
	}

	function getCacheImageFields(){
		if ( !isset($this->properties["cacheImageFields"])){
			return false;
		} else {
			return $this->properties["cacheImageFields"];
		}
	}

	function getTable(){ return $this->properties["table"]; }

	function getRefreshAfter(){
		return $this->config["refresh"];
	}
	function getExpireAfter(){ return $this->config["expire"]; }

	function setRefreshAfter($value){ $this->config["refresh"] = $value; return $this; }
	function setExpireAfter($value){ $this->config["expire"] = $value; return $this; }

	function preserveSort($value){
		$this->properties["preserveSort"] = $value;
		return $this;
	}

	function prop($key, $val){
		$this->properties[$key]=$val;

		return $this;
	}

	####################################
	## PARAMETERS
	####################################

	function fields($value){
		if ( !is_array($value) ){
			$value = func_get_args();
		}
		$this->parameters["fields"] = $value;
		return $this;
	}

	function filterByFormula($value,$type="AND"){
		if (is_array($value)){
			$this->parameters["filterByFormula"] = $type."(".implode(",", $value).")";
		} else {
			$this->parameters["filterByFormula"] = $value;
		}
		return $this;
	}

	function maxRecords($value){
		$this->parameters["maxRecords"] = $value;
		return $this;
	}

	function pageSize($value){
		$this->parameters["pageSize"] = $value;
		return $this;
	}

	function sort($sort,$direction="asc"){
		if (!is_array($sort)){
			$sort = array(array("field" => $sort, "direction" => $direction));
		} else {

			if (isset($sort["field"])){

				if (!isset($sort["direction"])){
					$sort["direction"] = $direction;
				}

				$sort = array($sort);
			}

		}

		$this->parameters["sort"] = $sort;
		return $this;
	}

	function view($value){
		$this->parameters["view"] = $value;
		return $this;
	}

	function param($key, $val){
		$this->parameters[$key]=$val;

		return $this;
	}

	####################################
	## filterByFormula HELPERS
	####################################

	/*
	$object may be an array, a record, or a collection
	*/
	public function filterByRelated($object,$field="record_id"){
		$related_ids = $this->compileRecordIDs($object,$field);
		$this->addFilter("OR(RECORD_ID()='".implode("', RECORD_ID()='",$related_ids)."')");
		return $this;
	}

	public function resetFilter(){
		unset($this->parameters["filterByFormula"]);
	}

	public function addFilter($filterByFormula,$type="AND"){
		if (isset($this->parameters["filterByFormula"])){
			
			// Is this an AND we can just add to?
			if ( strtoupper($type) == "AND" && preg_match("`^AND\((.*)\)$`i",$this->parameters["filterByFormula"], $matches) ){
				$this->parameters["filterByFormula"] = $matches[1];
			}

			$this->parameters["filterByFormula"] = $type."(".$this->parameters["filterByFormula"].",".$filterByFormula.")";

		} else {
			$this->parameters["filterByFormula"] = $filterByFormula;
		}
		return $this;
	}

	public function addRelatedQuery($field,$query){
		// Query is simply name of table
		if ( is_string($query) ){
			$table = $query; // table name was passed instead of query object
			$config = $this->getConfig(); // use the same config as parent query
			$query = new AirpressQuery($table,$this->getConfig());
		} else if ( is_object($query) && ! $query->getConfig() ){
			$config = $this->getConfig();
			$query->setConfig($config); // use the same config as parent query
		}

		if (!isset($relatedQueries)){
			$relatedQueries = array();
		}

		$this->relatedQueries[] = array($field,$query);
	}

	public function getRelatedQueries(){
		return (isset($this->relatedQueries))? $this->relatedQueries : array();
	}


	public function logError($error){
		if (!isset($this->errors)){
			$this->errors = array();
		}
		if (isset($error["message"])){
			$this->errors[] = $error;
		// } else if (is_array($error)){
		// 	$this->errors = array_merge($this->errors,$errors);
		} else {
			$this->errors[] = $error;
		}
	}
	public function hasErrors(){
		return (isset($this->errors))? true : false;
	}
	public function getErrors(){
		return (isset($this->errors))? $this->errors : false;
	}

  	public function compileRecordIDs($object,$field_name="record_id"){
  		global $airpress;
		$record_ids = array();

		$class = (is_object($object))? get_class($object) : false;

		if ($class == "AirpressCollection"){
			foreach($object as $record){
				if ($field_name == "record_id"){
					 if ($record_id = $record->record_id() ){
						$record_ids[] = $record_id;
					 }
				} else {
					$record_ids = array_merge($record_ids,$this->compileRecordIDs($record,$field_name));
				}
			}
		} else if ($class == "AirpressRecord"){
			
			$record = &$object;

			if ($field_name == "record_id"){
				if ($id = $record->record_id()){
					$record_ids[] = $id;
				}
			} else {
				if (isset($record[$field_name])){
					if (is_array($record[$field_name])){
						// this is (most likely?!?) an array of record ids.
						// which is to say, it's an AirpressCollection waiting to be populated
						$record_ids = array_merge($record_ids,$record[$field_name]);
					} else if (get_class($record[$field_name]) == "AirpressCollection"){
						foreach($record[$field_name] as $r){
							if ($id = $r->record_id()){
								$record_ids[] = $id;
							}
						}
					} 
				}
			}
		} else if (is_array($object)){
			$record_ids = $object;
		}

		$record_ids = array_unique($record_ids);

		return $record_ids;
  	}

  	private function localizeImages(&$records){
  		global $_wp_additional_image_sizes;

  		$complete = true;
  		$stats = array("cached" => 0, "created" => 0, "rotated" => 0, "deferred" => 0);

		$cacheImageFields = $this->getCacheImageFields();

		if ( $cacheImageFields ){

			$local_image_base = WP_CONTENT_DIR."/airpress-image-cache/".$this->getTable()."/";

			if ( ! is_dir($local_image_base)){
				if ( ! mkdir($local_image_base,0777,true) ){
					airpress_debug(0,"Cannot create $local_image_base");
					return false;					
				}
			}

			if ( ! is_writable($local_image_base) ){
				airpress_debug(0,"Cannot write to $local_image_base");
				return false;
			}

			$mime_types = wp_get_mime_types();
			$new_types = array();
			foreach($mime_types as $ext_string => $type){
				$exts = explode("|", $ext_string);
				$new_types[$type] = $exts[0];
			}
			$mime_types = $new_types;

			// Multiple calls to cacheImageFields creates and array
			// of field/size/crop combinations, so loop through all
			foreach($cacheImageFields as $cif):

				$fields = $cif["fields"];
				$sizes = $cif["sizes"];
				$crop = $cif["crop"];
				$regenerate = $cif["regenerate"];

				$size_defs = array();
				foreach($sizes as $key => $size){
					if ( is_array($size) ){
						$name = $key;
						$def = $size;
					} else {
						$name = ( $size == "thumb" ) ? "thumbnail" : $size;

						if ( $name == "full" ){
							$def = array();
						} else if ( isset($_wp_additional_image_sizes[$name]) ){
							$def = $_wp_additional_image_sizes[$name];
						} else {
							// Use the media size defined (possibly) in WP Admin
							$w = get_option($name."_size_w");
							$h = get_option($name."_size_h");

							if ( ! $w || ! $h){
								airpress_debug($this->getConfig(),"The provided size of '$name' has no width or height params. Skipping.");
								continue; // skip this size
							}

							$def = array("width" => $w, "height" => $h);
						}

					}
					
					if ( !isset($def["crop"]) ){
						$def["crop"] = $crop;
					}

					if ( !isset($def["regenerate"]) ){
						$def["regenerate"] = $regenerate;
					}

					$size_defs[$name] = $def;
				}

				$sizes = $size_defs;

				if ( empty($sizes) ){
					continue;
				}

				// this CIF may only be targeting a single field
				// however it may also be targting more, so loop!
				foreach($fields as $field):
					
					// Now that we're looking for a single field, let's
					// look for it inside each record of our result set
					foreach($records as &$record):$r = &$record["fields"];

						if ( ! is_airpress_attachment($r[$field]) ){
							// is either an empty image field or not an image field
							continue;
						}

						if ( !isset($r[$field][0]["type"]) || substr($r[$field][0]["type"],0,5) != "image" ){
							// must be an image type
							continue;
						}

						foreach($r[$field] as &$airtable_image):

							// this will merge with this fields thumbnails array from Airtable
							$new_thumbnails = array();
							
							$filename = $airtable_image["id"];
							$ext = $mime_types[$airtable_image["type"]];
							$base_image_path = $local_image_base.$filename.".$ext";

							$cleanup_needed = false;

						    foreach($sizes as $size_name => $size_def):

					    		$clone_filename = $filename;
					    		$clone_filename .= "-" . sanitize_title($size_name);
								$clone_filename .= "." . $ext;

								$clone_image_path = $local_image_base.$clone_filename;

								if ( file_exists($clone_image_path) && $size_def["regenerate"] === false ){

						    		$wordpress_clone = wp_get_image_editor( $clone_image_path );

									if ( is_wp_error( $wordpress_clone ) ){
										airpress_debug($this->getConfig(),"wp_get_image_editor error: bad image file $clone_image_path",$wordpress_clone);
										continue;
									}

								} else {

									$runtime = (microtime(true)-$this->runtime_start);
									if ( $runtime > 25) {
						    			$new_thumbnails[$size_name] = array(
					    					"url" => $airtable_image["thumbnails"]["small"]["url"],
					    								"width" => $airtable_image["thumbnails"]["small"]["width"],
					    								"height" => $airtable_image["thumbnails"]["small"]["height"],
						    						);
										airpress_debug(0,"SOFT TIMEOUT after ".round($runtime,2).". Will attempt to reprocess ".$size_name." of ".$airtable_image["filename"]);
										$complete = false;
										continue;
									}

						    		//$wordpress_clone = wp_get_image_editor( $base_image_path );
						    		$wordpress_clone = $this->airtable_url_to_wp_image($airtable_image,$base_image_path);

									if ( is_wp_error( $wordpress_clone ) ){
										airpress_debug($this->getConfig(),"wp_get_image_editor error: $base_image_path",$wordpress_clone);
										continue;
									}

									$cleanup_needed = true;

									$max_w = ( isset($size_def["width"]) )? $size_def["width"] : null;
									$max_h = ( isset($size_def["height"]) )? $size_def["height"] : null;
									$crop =  ( isset($size_def["crop"]) )? $size_def["crop"] : false;

									if ( is_null($max_w) && is_null($max_h) ){
										// something needs to be resized. Otherwise what's the point??!?
										continue;
									}

						    		$wordpress_clone->resize( $max_w, $max_h, $crop );

							    	$wordpress_clone->save( $clone_image_path );

								}

								$size = $wordpress_clone->get_size();
								
						    	$new_thumbnails[$size_name] = array(
					    									"url" => content_url("airpress-image-cache")."/".$this->getTable()."/".$clone_filename,
					    									"width" => $size["width"],
					    									"height" => $size["height"],
						    								);

						    endforeach; // new thumbnails

						    // If full was specified, then we won't delete the original image from
						    // airtable, rather we'll rewrite the URL attribute to point local
						    if ( array_key_exists("full", $sizes) ){
								$airtable_image["url"] = content_url("airpress-image-cache/$filename.$ext");
							} else if ( $cleanup_needed ) {
								unlink( $base_image_path );
							}

						    // This is the magic line that updates the result records with the local paths
						    // essentially injecting new thumbnails sizes, overwriting existing ones and 
						    // changing image paths.
							$airtable_image["thumbnails"] = array_merge($airtable_image["thumbnails"],$new_thumbnails);

						endforeach; // airtable images

					endforeach; // airtable records

				endforeach; // CIF target fields

			endforeach; // CIF groups

		}

		return $complete;

  	}

  	private function airtable_url_to_wp_image($airtable_image,$base_image_path){
		if ( file_exists($base_image_path) ){

			$wordpress_image = wp_get_image_editor( $base_image_path );

		} else {

			//file_put_contents($base_image_path,file_get_contents($airtable_image["url"]));

			$s = microtime(true);
			$this->downloadFile($airtable_image["url"],$base_image_path);
			airpress_debug($this->getConfig(),"Cached remote image in ".round((microtime(true)-$s)/60,4)." seconds to $base_image_path");

			$wordpress_image = wp_get_image_editor( $base_image_path );

			if ( function_exists("exif_read_data") ){
				$exif = @exif_read_data($base_image_path);
				$degrees = 0;
	
				if ( isset($exif["Orientation"]) ){
					switch($exif["Orientation"]){
						case 8:
							$degrees = 90;
						break;
						case 3:
							$degrees = 180;
						break;
						case 6:
							$degrees = -90;
						break;
					}
				}

				if ( $degrees ){
					airpress_debug($this->getConfig(),"Applying rotation to $base_image_path");
					$wordpress_image->rotate($degrees);
					$wordpress_image->save( $base_image_path );
				}
			}

		}

		return $wordpress_image;
  	}

  	private function downloadFile($url, $path)
	{
	    $newfname = $path;
	    $file = fopen ($url, 'rb');
	    if ($file) {
	        $newf = fopen ($newfname, 'wb');
	        if ($newf) {
	            while(!feof($file)) {
	                fwrite($newf, fread($file, 1024 * 8), 1024 * 8);
	            }
	        }
	    }
	    if ($file) {
	        fclose($file);
	    }
	    if ($newf) {
	        fclose($newf);
	    }
	}

}
?>