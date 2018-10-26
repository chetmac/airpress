<?php

class AirpressConnect{

	private function __construct(){
		
	}

	public static function _get($query,$blocking=true){
		global $airpress;

		$start = microtime(true);

		$http_params = $query->getParameters();

		$http_headers = array(	'Authorization' => 'Bearer ' . $query->getApiKey(),
							'Content-Type' => 'application/json'
						);
		
		// Initialize the offset.
		$offset = '';
		
		$records = array();

		// Make calls to Airtable, until all of the data has been retrieved...
		while (!is_null($offset)):
			
			$http_params["offset"] = $offset;

			// Specify the URL to call.
			$url =	  $query->getApiUrl()
					. $query->getAppId()
					. '/' . rawurlencode($query->getTable())
					. "?" . http_build_query($http_params);

			$args = array(
			    'timeout'     => 60,
			    'redirection' => 5,
			    'blocking'    => $blocking,
			    'headers'     => $http_headers,
			    'cookies'     => array(),
			    'body'        => null,
			    'compress'    => false,
			    'decompress'  => true,
			    'sslverify'   => true
			);

			$response  		= wp_remote_get( $url, $args );
			$response_code 	= wp_remote_retrieve_response_code( $response );

			if ( is_wp_error( $response ) ){

				$e = round(microtime(true) - $start,2);
				
				airpress_debug($query->getConfig(),"WP Error | ".$query->toString()." ($e)",array($http_params,$response));

				foreach( $response->errors as $code => $error ){
					$query->logError(["code" => $code, "message" => implode(" | ", $error) ]);
				}

				return false;

			} else if ( $response_code != 200 ) {

				$e = round(microtime(true) - $start,2);
				airpress_debug($query->getConfig(),$response_code." | ".$query->toString()." ($e)",array($http_params,$response));

				$body = isset($response['body']) ? json_decode($response['body'],true) : [];

				if ( isset($body["error"]["message"]) ){
					$message = $body["error"]["message"];
				} else {
					$message = "Not specified";
				}

				$query->logError(["code" => $response_code, "message" => $message]);
				// Log error. wp_error?
				return false;
				
			} else {

				$header = $response['headers']; // array of http header lines
	  			$body = isset($response['body']) ? json_decode($response['body'],true) : [];

				// When getting a table, we'll build an array of records,
				// when getting a record, we'll just return the record.
				if (isset($body["records"])){

					$records = array_merge($records,$body["records"]);	

					// Adjust the offset.
					// Airtable returns NULL when the final batch of records has been returned.
					$offset = (isset($body["offset"]))? $body["offset"] : null;

				} else {

					$records = $body;
					$offset = null;

				}

			}

		endwhile;

		$e = round(microtime(true) - $start,2);
		airpress_debug($query->getConfig(),$response_code."	".count($records)."	".$e."	".$query->toString());

		$query->setCachedResults($records);

		return $records;
	}

	public static function get($query){
		global $airpress;

		$http_params = $query->getParameters();

		$cachedResults = $query->getCachedResults();
		if ($cachedResults !== false){
			return $cachedResults;
		} else {
			return AirpressConnect::_get($query);
		}


	}

	public static function create($config,$table,$fields){
		global $airpress;

		if ( ! is_array($config) ){
			$config = get_airpress_config("airpress_cx",$config);
		}

		$http_headers = array(	'Authorization' => 'Bearer ' . $config["api_key"],
							'Content-Type' => 'application/json'
						);
		
		$data = array("fields" => $fields, "typecast" => true);

		// Specify the URL to call.
		$curlopt_url =   $config["api_url"]
			           . $config["app_id"]
			           . '/' . rawurlencode($table);

		$args = array(  "method" => "POST",
					"timeout" => 15,
					"redirection" => 5,
					"blocking" => true,
					"headers" => $http_headers,
					"body" => json_encode($data),
				);

		$response = wp_remote_post( $curlopt_url, $args );
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) || $response_code != 200) {
			airpress_debug($config,"Error while attempting to create record in $table",array($curlopt_url,$data,$response) );
			return false;
		} else {

			$header = $response['headers']; // array of http header lines
			$record = json_decode($response['body'],true); // use the content

			return $record;

		}

	}

	public static function update($config,$table,$record_id,$fields){
		global $airpress;

		if ( ! is_array($config) ){
			$config = get_airpress_config("airpress_cx",$config);
		}

		$http_headers = array(	'Authorization' => 'Bearer ' . $config["api_key"],
							'Content-Type' => 'application/json'
						);
		
		$data = array("fields" => $fields, "typecast" => true);

		// Specify the URL to call.
		$curlopt_url =   $config["api_url"]
			           . $config["app_id"]
			           . '/' . rawurlencode($table) . '/' . $record_id;

		$args = array(  "method" => "PATCH",
					"timeout" => 15,
					"redirection" => 5,
					"blocking" => true,
					"headers" => $http_headers,
					"body" => json_encode($data),
				);

		$response = wp_remote_post( $curlopt_url, $args );
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) || $response_code != 200) {
			airpress_debug($config,"Error while attempting to update record in $table",array($curlopt_url,$data,$response) );
			return false;
		} else {

			$header = $response['headers']; // array of http header lines
			$record = json_decode($response['body'],true); // use the content

			return $record;

		}

	}


}

?>