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
				//airpress_debug($this->query->getConfig(),"New AirpressCollection from table {".$query->getTable()."} with ".count($records)." records.");
			}

			if ($records != false && !empty($records)){
				$this->setRecords($records);

				foreach ($this->query->getRelatedQueries() as $relatedQuery){
					$field = $relatedQuery[0];
					$query = $relatedQuery[1];

					$this->populateRelatedField($field,$query);

				}
			} else {
				//airpress_debug($this->query->getConfig(),"Empty Collection created from table {".$query->getTable()."}");
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

		if ($key == "record_id"){
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
			if ( isset($record[$field]) && $record->isFieldRelated($field) ){

				if ( empty($fields) ){

					$record->populateField($field,$subCollection,$query);

				} else {
					
					if ( is_airpress_collection($record[$field]) ){
						$record[$field]->setFieldValues($fields,$subCollection,$query);
					} else {
						airpress_debug($this->query->getConfig(),"asking for something that doesn't exist");
					}

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
				if ( is_airpress_collection($record[$field]) ){

					if (empty($keys)){
						// no more keys to recurse, add the records of this collection to the array
						// array_unique doesn't work on objects
						$values = array_merge($values,(array)$record[$field]);
					} else {
						// ask the collection for the values for the next set of keys
						$result = $record[$field]->getFieldValues($keys);
						$values = array_merge($values, $record[$field]->getFieldValues($keys) );
					}

				// Is this an array of images/attachments?
				} else if ( is_array($record[$field]) && isset($record[$field][0]["url"]) ){

					$attachment_values = array();
					foreach( $record[$field] as $attachment ){
						$attachment_values[] = airpress_getArrayValues($attachment,$keys);
					}

					$values = array_merge($values, $attachment_values );

				// Is this field an Array
				} else if (is_array($record[$field])){

					if (empty($keys)){

						// is this an array of record IDs?
						if (
							isset($record[$field][0]) && 
							is_string($record[$field][0]) && 
							substr($record[$field][0],0,3) == "rec"
						){
							$values = array_merge($values,$record[$field]);
						} else {
							$values = array_merge($values,$record[$field]);							
						}

					} else {
						$values = array_merge($values, airpress_getArrayValues($record[$field],$keys) );
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
		
		// array_unique is pointless and even dangerous when run on multi-dimensional
		// arrays and objects. The only real point to using array_unique to begin with
		// is to respect the intent of getFieldValues—which is to follow a key path
		// such as District|Cuisine|Name for multiple Restaurant records and return an
		// array of those values. In this example, we don't want duplicate cuisine names!

		// This is especially true when the key path (District|Cuisines) would only return
		// arrays of RECORD_IDs for a variety of Cuisine records. Since this function is used
		// to build a HIGHLY optimized API request to Airtable, I don't want to ask for the same
		// record more than one by sending duplicate RECORD_IDs in the filterByFormula.

		// However, if I've already populated the districts and cuisines for a given set of 
		// restaurants then the key path (District|Cuisines) would return the records, not
		// the record IDs—so running array unique on THOSE results are bad/unpredictable.

		if ( isset($values[0]) && ! is_object($values[0]) && ! is_array($values[0]) ){
			$values = array_unique($values);
		}

		return $values;
	}

	// function getArrayValue($array,$keys){
	// 	while(!empty($keys)){
	// 		$key = array_shift($keys);

	// 		if (isset($array[$key])){
	// 			$array = $array[$key];
	// 		} else {
	// 			// Maybe it's an array of arrays
	// 			$return_array = array();
	// 			foreach($array as $item){
	// 				if ( isset($item[$key]) ){
	// 					$return_array[] = $item[$key];
	// 				}
	// 			}
	// 			if ( ! empty($return_array) ){
	// 				$array = $return_array;
	// 			}
	// 		}
	// 	}
	// 	return $array;
	// }

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
		
		return $this->lookup("record_id",$record["id"]);
	}


    /**
     * Remove an item from the collection by key.
     *
     * @param  string|array  $keys
     * @return $this
     */
    public function forget($keys)
    {
        foreach ((array) $keys as $key) {
            $this->offsetUnset($key);
        }
    }

	public function populateRelatedField($field,$query=null,$params=null){
		global $airpress;

		if ($this->isEmpty()){
			airpress_debug($this->query->getConfig(),"Can't populate an empty collection.");
			return false;
		}

		if ( is_string($field) ){
			$keys = explode("|", $field);
		} else {
			$keys = $field;
		}

		if ( is_null($query) ){
			$query = end($keys);
		}

		// $query is a string, so create the query object using parent collection query config
		if ( is_string($query) ){
			$table = $query;
			$query = new AirpressQuery($table,$this->query->getConfig(),$params);
		}

		$s = microtime(true);
		// Gather IDs
		$record_ids = $this->getFieldValues($keys);

		if ( isset($record_ids[0]) && is_airpress_record($record_ids[0]) ){
			// This has already been populated.
			airpress_debug($this->query->getConfig(),"Attempting to re-populate a collection.",$keys);
			return false;
		}

		$batch_results = array();
		$batch_ids = $record_ids;
		$i = 0;
		while ( ! empty($batch_ids) ){
			$i++;
			$batch = array_splice($batch_ids, 0, 250);
			$batch_query = clone $query;
			$batch_query->filterByRelated($batch);

			$results = AirpressConnect::get($batch_query);

			if ( is_array($results) && ! empty($results) ){
				$batch_results = array_merge($batch_results,$results);
			}

		    //airpress_debug($this->query->getConfig(),"BATCH $i (".count($batch).")");
		}

		$query->filterByRelated($record_ids);
		$relatedCollection = new AirpressCollection($query,false); // not actually running query
		$relatedCollection->setRecords($batch_results);

		$this->setFieldValues($keys,$relatedCollection,$query);

	}

}
?>