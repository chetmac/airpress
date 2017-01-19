<?php


// consider adding implode (return same field for all records as a string)
// and collapse which would be like getFieldValues— it returns the 


class AirpressCollection extends ArrayObject {

	public $query;
	private $index;

	public function __construct($query,$execute_query=true){
		global $airpress;
		$this->index = array();
		$this->query = $query;

		if ($execute_query){

			$records = AirpressConnect::get($this->query);

			if ($records != false){
				//airpress_debug($this->query->getConfig()["id"],"New AirpressCollection from table {".$query->getTable()."} with ".count($records)." records.");
			}

			if ($records != false && !empty($records)){
				$this->setRecords($records);

				foreach ($this->query->getRelatedQueries() as $relatedQuery){
					$field = $relatedQuery[0];
					$query = $relatedQuery[1];

					$this->populateRelatedField($field,$query);

				}
			} else {
				//airpress_debug($this->query->getConfig()["id"],"Empty Collection created from table {".$query->getTable()."}");
			}

		}

	}

	public function toArray(){
		$this_array = array();
		foreach($this as $record){
			$this_array[] = $record->toArray();
		}		
		return $this_array;
	}

	public function toJson(){
		return json_encode($this->toArray());
	}

	public function implode($separator,$field){
		$values = $this->getFieldValues($field);
		return implode(",", $values);
	}

	public function isEmpty(){
		return (count($this) > 0)? false : true;
	}

	public function isFieldRelated($field){


		/*

		THIS IS BROKEN AND INCOMPLETE
		need to provide KEYS and make recursive like the other methods

		*/

		foreach($this as $record){
			$isFieldRelated = $record->isFieldRelated($field);
			if ($isFieldRelated !== null){
				// We're going to loop through the records in this collection
				// testing until we get a positive or negative response.
				// We assumed that if we get a true or false from one record, all
				// other records will conform.
				return $isFieldRelated;
			}
		}
		return null;
	}

	public function isFieldPopulated($field){

		/*

		THIS IS BROKEN AND INCOMPLETE
		need to provide KEYS and make recursive like the other methods

		*/

		foreach($this as $record){
			$isFieldPopulated = $record->isFieldPopulated($field);
			if ($isFieldPopulated !== null){
				// We're going to loop through the records in this collection
				// testing until we get a positive or negative response.
				// We assumed that if we get a true or false from one record, all
				// other records will conform.
				return $isFieldPopulated;
			}
		}
		return null;
	}

	private function indexRecord($key, $record=null){
		$key_value = false;

		if (!is_array($this->index[$key])){
			$this->index[$key] = array();
		}

		if ($key == "id"){
			$key_value = $record->record_id();
		} else if (isset($record[$key])){
			$key_value = $record[$key];
		}

		if ($key_value){
			if (!isset($this->index[$key][$key_value])){
				$this->index[$key][$key_value] = array();
			}

			$this->index[$key][$key_value][] = $record;

		}

	}



	/*
	return any record(s) in the collection by any field/value pair
	creates an index for faster repeat lookup
	*/
	public function lookup($key,$value,$multiple=false){
		global $airpress;

		if ( !isset($this->index[$key]) ){
			$this->index[$key] = array();
			foreach($this as $record){
				$this->indexRecord($key,$record);
			}
		}

		if (isset($this->index[$key][$value])){
			if ($multiple){
				return $this->index[$key][$value];
			} else {
				return $this->index[$key][$value][0];
			}
		} else {
			return false;
		}

	}

	public function setFieldValues($fields, $subCollection, $query){
		global $airpress;

		if (is_array($fields)){
			$field = array_shift($fields);
		} else {
			$field = $fields;
			$fields = array();
		}

		foreach($this as $record){
			if (isset($record[$field]) && $record->isFieldRelated($field) ){

				if (!empty($fields) && is_object($record[$field]) && get_class($record[$field]) == "AirpressCollection"){
					$record[$field]->setFieldValues($fields,$subCollection,$query);
				} else if (empty($fields)){
					$record->populateField($field,$subCollection,$query);
				} else {
					// asking for something that doesn't exist
				}

			}
		}

	}

	public function val($keys){
		$values = $this->getFieldValues(explode("|", $keys) );
		if (empty($values)){
			return false;
		} else if (count($values) == 1){
			return $values[0];
		} else {
			return $values;
		}
	}

	public function getFieldValues($keys){
		global $airpress;
		$values = array();

		if (is_array($keys)){
			$field = array_shift($keys);
		} else {
			$field = $keys;
			$keys = array();
		}

		foreach($this as $record){
			//airpress_debug(0,"field: $field");
			if (strtolower($field) == "record_id"){
				$values[] = $record->record_id();
			} else if (isset($record[$field])){

				// Is this field a collection? i
				if (is_object($record[$field]) && get_class($record[$field]) == "AirpressCollection"){

					if (empty($keys)){
						// no more keys to recurse, add the records of this collection to the array
						$values = array_merge($values,(array)$record[$field]);
					} else {
						// ask the collection for the values for the next set of keys
						$values = array_merge($values, $record[$field]->getFieldValues($keys) );
					}

				// Is this field an Array
				} else if (is_array($record[$field])){

					if (empty($keys)){
						$values = array_merge($values,$record[$field]);						
					} else {
						$values = array_merge($values, $this->getArrayValue($record[$field],$keys) );
					}

				// Is this a string or somthing?
				} else {

					if (empty($keys)){
						$values[] = $record[$field];
					} else {
						// I can't keep digging into a string.
					}

				}

			}
		}
		
		return $values;
	}

	function getArrayValue($array,$keys){
		while(!empty($keys)){
			$key = array_shift($keys);

			if (isset($array[$key])){
				$array = $array[$key];
			}
		}
		return $array;
	}

	public function setRecords($records=array()){
		// empty collection first
		foreach($this as $key => $record){
			unset($this[$key]);
		}

		// now add records
		foreach($records as $record){
			$this->addRecord($record);
		}
	}

	public function addRecord($record=array()){
		global $airpress;
		if (is_array($record)){
			$this[] = new AirpressRecord($record,$this);
		} else if( is_airpress_record($record) ) {
			$this[] = $record;
			$record->setCollection($this);
		}

		// update index // if theres no index than
		// lookup hasn't run yet so no worries
		foreach($this->index as $key => $values){
			$this->indexRecord($key,$record);
		}

	}

	public function createRecord($fields){
		$config = $this->query->getConfig();
		$table = $this->query->getTable();

		$record = AirpressConnect::create($config,$table,$fields);
		$this->addRecord($record);
		
		return $this->lookup("id",$record["id"]);
	}

	public function populateRelatedField($field,$query=null,$params=null){
		global $airpress;

		if ($this->isEmpty()){
			//airpress_debug($this->query->getConfig()["id"],"Can't populate an empty collection.");
			return false;
		}

		$keys = explode("|", $field);

		// $query is a string, so create the query object using parent collection query config
		if (is_string($query)){
			$table = $query;
			$query = new AirpressQuery($table,$this->query->getConfig(),$params);
		}

		// Gather IDs
		$record_ids = $this->getFieldValues($keys);

	    $query->filterByRelated($record_ids);
	    //airpress_debug($this->query->getConfig()["id"],"Get records from {".$query->getTable()."} to populate ".$this->query->getTable()."|".$field);

		$relatedCollection = new AirpressCollection($query);
		$this->setFieldValues($keys,$relatedCollection,$query);
	}

}
?>