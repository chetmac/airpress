<?php

class AirpressQuery {
	
	private $config;
	private $parameters;
	private $properties;

	private $relatedQueries;

	private $errors;

	public function __construct($table=null,$config=null,$params=array()){
		global $airpress;

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
		return "https://api.airtable.com/v0/";
	}

	public function setCachedResults($records){

		if ($this->getRefreshAfter() <= 0 || $this->getExpireAfter() <= 0){
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
		return $this->config;
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

	####################################
	## PARAMETERS
	####################################

	function fields($value){
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

	####################################
	## filterByFormula HELPERS
	####################################

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
		if (is_string($query)){
			$table = $query; // table name was passed instead of query object
			$config = $this->getConfig(); // use the same config as parent query
			$query = new AirpressQuery($table,$this->getConfig());
		} else if (is_object($query) && $query->getConfig() === null){
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
		} else if (is_array($error)){
			$this->errors = array_merge($this->errors,$errors);
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
}
?>