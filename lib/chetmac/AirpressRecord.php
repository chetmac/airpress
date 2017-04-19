<?php
class AirpressRecord extends ArrayObject {

	private $id;
	private $createdTime;
	private $collection;

	public function __construct($record=null,$collection=false){
		global $airpress;

		$this->collection = $collection;

		if ( isset($record) && is_array($record) ){

			if (isset($record["id"]))
				$this->id = $record["id"];

			if (isset($record["createdTime"]))
				$this->createdTime = $record["createdTime"];

			if (isset($record["fields"])){
				parent::__construct($record["fields"]);
			}

		}

	}

	public function array_keys(){
		$array = $this->toArray();
		$keys = array_keys($array["fields"]);
		return $keys;
	}

	public function dump(){
		var_dump($this->storage);
	}

	public function isEmpty($field){
		if (!isset($this[$field]) ){
			return true;
		} else if ( is_airpress_collection($this[$field]) ){
			return $this[$field]->isEmpty();
		} else {
			return empty($this[$field]);
		}
	}

	public function getCollection(){
		return $this->collection;
	}

	public function setCollection($collection){
		$this->collection = $collection;
	}

	public function isFieldRelated($field){
		global $airpress;

		if (!isset($this[$field]) || empty($this[$field])){
			// While this field MAY be related, if it's not set or empty we can't be sure.
			return null;
		} else if ( is_array($this[$field]) && preg_match("/rec[a-z0-9]{14}/i",$this[$field][0]) ) {
			return true;
		} else if (is_object($this[$field]) && get_class($this[$field]) == "AirpressCollection") {
			return true;
		} else {
			return false;
		}
	}

	public function isFieldPopulated($field){
		if (!isset($this[$field]) || empty($this[$field])){
			// While this field MAY be related, if it's not set or empty we can't be sure.
			return null;
		} else if (is_object($this[$field]) && get_class($this[$field]) == "AirpressCollection") {
			return true;
		} else {
			return false;
		}
	}

	public function populateField($field,$subCollection,$query){
		global $airpress;
		// Get array of record IDs
		if ( ! is_array($this[$field]) ){
			return;
		}

		$record_ids = $this[$field];

		// Create Query object that matches exactly this collection
		$queryClone = clone $query;
		$queryClone->filterByRelated($record_ids);

		// Create Collection with which to replace field. FALSE means DON'T execute query
		$collection = new AirpressCollection($queryClone,false);

		if ($query->hasSort()){
			// WHAT ABOUT ARRAY INTERSECT?
			// Loop through the query results, which will respect the query sort order
			foreach($subCollection as $record){

				if ( in_array($record->record_id(), $record_ids) ){
					$collection->addRecord($record);
				}

			}

		} else {

			// Loop through the IDs, which will respect the drag-and-drop order from Airtable
			foreach($record_ids as $record_id){
				$record = $subCollection->lookup("record_id",$record_id);
				if ( $record ){
					$collection->addRecord($record);
				} else {
					// When this happens, it's most likely because a filter was used during the 
					// creation of the subCollection that omitted this particular record_id.
				}
			}

		}
		
		$this[$field] = $collection;
		//airpress_debug(0,"Thanks for populating field $field of ".$this["Name"],$this[$field]->toArray());

		//$airpress->debug("Populate ".$collection->query->getTable()."->".$field." with ".count($collection)." records.",(array)$this[$field]);
	}

	public function record_id(){
		return $this->id;
	}

	public function created_time(){
		return $this->createdTime;
	}

	// DEPRECATED
	public function createdTime(){
		return $this->created_time();
	}

	public function toArray(){
		$this_array = array("id" => $this->record_id(), "createdTime" => $this->createdTime(), "fields" => array());
		foreach($this as $field => $value){
			if ( is_airpress_collection($value) ){
				$this_array["fields"][$field] = $value->toArray();
			} else {
				$this_array["fields"][$field] = $value;
			}
		}
		return $this_array;
	}

	public function cloneSlim($fields = array()){
		$slim = new self(array("id" => $this->record_id(),"fields" => array()));

		if (empty($fields)){
			$fields = array_keys($this);
		}

		foreach($fields as $field){
			if (is_airpress_collection($this[$field]) && is_airpress_record($this[$field][0])){
				$subSlim = array();
				foreach($this[$field] as $record){
					$subSlim[] = $record->cloneSlim();
				}
			} else {
				$slim[$field] = $this[$field];
			}
		}

		return $slim;
	}

	public function html($field){
		global $parsedown;
		if (isset($this[$field])){
			return $parsedown->text($this[$field]);
		}
	}

	public function update($fields){
		// for now, we're going to blindly update whatever we're sent

		$config = $this->collection->query->getConfig();
		$table = $this->collection->query->getTable();

		$result = AirpressConnect::update($config, $table, $this->record_id(),$fields);

		if ($result){
			foreach($result as $field => $value){
				$this[$field] = $value;
			}
		}

	}

}
?>