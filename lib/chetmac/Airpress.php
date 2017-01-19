<?php

class Airpress {

	private $virtualPosts;
	private $virtualFields;
	public $debug_output;
	private $currentRecord;
	public $debug_stats;

	private $deferred_queries;

	function init(){

		airpress_debug(0,"\n\nAIRPRESS LOADED");

		$this->deferredQueries = array();

		$this->virtualFields = new AirpressVirtualFields();
		$this->virtualFields->init();

		$this->virtualPosts = new AirpressVirtualPosts();
		$this->virtualPosts->init();

		add_shortcode( 'airblock', array($this, 'shortcode_airblock'));
		add_shortcode( 'airfield', array($this,'shortcode_airfield') );
		add_shortcode( 'airfield_loop', array($this,'shortcode_loop') );
		for($i=1;$i<=10;$i++){
			add_shortcode( 'airfield_loop'.$i, array($this,'shortcode_loop') );
		}
		add_shortcode( 'airpress_populate', array($this,'shortcode_populate') );
		add_action( 'wp_footer', array($this,'renderDebugOutput') );
		add_action( 'admin_bar_menu', array($this,'renderDebugToggle'), 999 );
		add_action( 'shutdown', array($this,'stash_and_trigger_deferred_queries'));
	}


/* CONFIGURATION FUNCTIONS */

	
	public function shortcode_loop($atts,$content=null,$tag){
		global $post,$airpress;		

	    $a = shortcode_atts( array(
	        'field'				=> null,
	    ), $atts );


	    if ($a["field"] === null){
	    	$records_to_loop = (array)$post->AirpressCollection;
	    } else if (isset($this->currentRecord)){
	    	// This is a nested loop!
	    	$keys = explode("|",$a["field"]);
	    	$field = array_shift($keys);
	    	$records_to_loop = $this->currentRecord[$field]->getFieldValues($keys);
			$previousRecord = $this->currentRecord;
	    } else {
			$keys = explode("|", $a["field"]);
		    $records_to_loop = $post->AirpressCollection->getFieldValues($keys);
	    }

	    preg_match_all("/{{([^}]*)}}/", $content, $matches);

	    $replacementFields = array_unique($matches[1]);

	    $output = "";
	    foreach($records_to_loop as $record){

	    	$this->currentRecord = $record;

	    	// Reset the template for each record
	    	// By doing the shortcodes BEFORE any processing, we're ensuring that
	    	// any nested loops ... or innermost loops are processed prior to outter most loops.
	    	// if we don't do this all {{variables}} will be replaced by outter most loops.
	    	$template = do_shortcode($content);

	    	foreach($replacementFields as $replacementField){

	    		$keys = explode("|", $replacementField);
	    		$field = array_shift($keys);
				$replacementValue = "";
				
				if ( strtolower($field) == "record_id" ){
					$replacementValue = $record->record_id();
				} else if ( ! is_airpress_empty( $record[$field] ) ){ 
					// this means it IS an AirpressCollection with records

	    			if (empty($keys)){
	    				// this shouldn't really happen because we can't output a collection
	    				// we should be looking INSIDE the collection, but can't since keys is empty
	    			} else {
	    				$replacementValue = implode(", ", $record[$field]->getFieldValues($keys) );
	    			}

	    		// This field is an array
	    		} else if (is_array($record[$field]) ){

	    			if (empty($keys)){
	    				$replacementValue = implode(", ",$record[$field]);
	    			} else {
	    				$array = $record[$field];
	    				while (!empty($keys)){
	    					$key = array_shift($keys);
	    					$array = $array[$key];
	    				}
	    				if (is_array($array)){
	    					$replacementValue = implode(", ",$array);
	    				} else {
	    					$replacementValue = $array;
	    				}
	    			}

	    		} else if (isset($record[$field])){

	    			$replacementValue = $record[$field];

	    		} else {

	    			$replacementValue = "";

	    		}

    			$template = str_replace("{{".$replacementField."}}", $replacementValue, $template);

	    	}

	    	$output .= $template;
	    	unset($this->currentRecord);
	    }

	    if (isset($previousRecord)){
	    	$this->currentRecord = $previousRecord;
	    }

	    return $output;
	}

	public function shortcode_populate($atts, $content=null, $tag){
		global $post,$airpress;

		// Check for non-value "flag" attributes. Set to true
		$flags = array("single");
		foreach($atts as $k => $v){
			foreach($flags as $flag){
				if (strtolower($v) == $flag ){
					$atts[$flag] = true;
				}
			}
		}

	    $a = shortcode_atts( array(
	        'field'				=> null,
			'relatedto'			=> null,
			'filterbyformula'	=> null,
			'view'				=> null,
			'sort'				=> null,
			'maxrecords'		=> null,
	    ), $atts );

	    if (isset($post->AirpressCollection)){
	    	$collection = $post->AirpressCollection;
	    } else {
	    	airpress_debug(0,"NO AIRPRESS COLLECTION with which to populate ".$a["field"]);
	    	return null;
	    }

	    $keys = explode("|", $a["field"]);

		// Gather IDs
		$record_ids = $collection->getFieldValues($keys);

		$query = new AirpressQuery();
		$query->setConfig($collection->query->getConfig());
	    $query->table($a["relatedto"]);
	    $query->filterByRelated($record_ids);

	    if (isset($a["filterbyformula"]))
	    	$query->filterByFormula($a["filterbyformula"]);

	    if (isset($a["view"]))
	    	$query->view($a["view"]);

	    if (isset($a["sort"]))
	    	$query->view($a["sort"]);

	    if (isset($a["maxrecords"]))
	    	$query->maxRecords($a["maxrecords"]);

		$subCollection = new AirpressCollection($query);
		$collection->setFieldValues($keys,$subCollection,$query);

	}

	public function shortcode_airblock($atts, $content=null, $tag){
		global $post,$model;

	    $a = shortcode_atts( array(
	        'name' => null,
	        'value' => null,
			'title' => null
	    ), $atts );

	    $a["name"] = ltrim($a["name"], '/');

	    $filepath = apply_filters("airpress_airblock_path_pre",$a["name"]);
	    $filepath = get_stylesheet_directory()."/".$a["name"].".php";
	    $filepath = apply_filters("airpress_airblock_path",$filepath);

	    if (is_file($filepath)){
	    	ob_start();
	    	include($filepath);
	    	$html = ob_get_clean();
	    	return do_shortcode($html);
	    } else {
	    	return basename($filepath)." not found.";
	    }
	}

	public function shortcode_airfield($atts, $content=null, $tag){
		global $post;

		// Check for non-value "flag" attributes. Set to true
		$flags = array("single");
		foreach($atts as $k => $v){
			foreach($flags as $flag){
				if (strtolower($v) == $flag ){
					$atts[$flag] = true;
				}
			}
		}

	    $a = shortcode_atts( array(
	        'name'				=> null,
			'relatedto'			=> null,
			'recordtemplate'	=> null,
			'relatedtemplate'	=> null,
			'single'			=> null,
			'rollup'			=> null,
			'default'			=> null,
			'format'			=> null,
	    ), $atts );

	    $field_name = $a["name"];

	    $single = (!isset($a["single"]) || strtolower($a["single"]) == "false")? false : true;

		$recordTemplate = (isset($a["recordtemplate"]))? $a["recordtemplate"] : "%s\n";
		$relatedTemplate = (isset($a["relatedtemplate"]))? $a["relatedtemplate"] : "%s\n";


   		$keys = explode("|", $a["name"]);

		$collection = $post->AirpressCollection;

		$output = "";

		if ( ! is_airpress_empty($collection) ){

			$values = $collection->getFieldValues($keys);

			if (isset($a["format"])){

				// a typical filter would be: date|Y-m-d
				// resulting in a hookable filter of: airfield_filter_date

				$f = explode("|", $a["format"]);
				foreach($values as &$value):
					if ( has_filter("airfield_filter_".$f[0])){
						$value = apply_filters("airfield_filter_".$f[0],$value,$a["format"]);
					} else {
						switch ($f[0]){
							case "date":
								$value = date($f[1],strtotime($value));
							break;
						}
					}

				endforeach;
				unset($value);
			}

			$output = implode("\n", $values);			
		}

		// $output = "";
		// foreach($collection as $record){
		// 	foreach($record[$field_name] as $value){
		// 		if (isset($keys)){
		// 			$output .= sprintf($relatedTemplate,$this->array_traverse($value,$keys));
		// 		} else {
		// 			$output .= sprintf($relatedTemplate,$value);
		// 		}
		// 	}

		// 	if ($single)
		// 		break; // only do first record

		// }

		return apply_filters( 'airpress_airfield', $output, $atts, $content, $tag );

	}

	####################################
	## DEFERRED
	####################################

	function stash_and_trigger_deferred_queries(){

		if (!empty($this->deferredQueries)){

			$stash_key = "airpress_stash_".microtime(true);
			set_transient($stash_key,$this->deferredQueries,60);
			//trigger async request to process stashed queries
			$start = microtime(true);
			airpress_debug(0,__FUNCTION__."|"."Sending ASYNC request to process ".count($this->deferredQueries)." deferred queries.");
			wp_remote_post(
				admin_url( 'admin-ajax.php' )."?action=airpress_deferred&stash_key=".urlencode($stash_key),
				array(
						"blocking"  => false,
						"timeout"   => 0.01 ,
						'sslverify' => apply_filters( 'https_local_ssl_verify', false )
					)
			);
			airpress_debug(0,__FUNCTION__."|"."DONE in ".(microtime(true) - $start)." seconds");
		}
	}

	function defer_query($query){
		$hash = $query->hash();
		$this->deferredQueries[$hash] = clone $query;
	}

	function run_deferred_queries($stash_key){
		$deferred_queries = get_transient($stash_key);
		delete_transient($stash_key);
		airpress_debug(0,__FUNCTION__."|"."Processing ".count($deferred_queries)." queries");
		foreach($deferred_queries as $hash => $query){
			$results = AirpressConnect::_get($query);
			airpress_debug(0,__FUNCTION__."|"."Query ".$query->toString()." had ".count($results)." records returned. Que Bella!");
		}

	}

	function debug(){

		$args = func_get_args();

		$message = array_shift($args);
		if (!is_string($message)){
			ob_start();
			var_dump($message);
			$message = ob_get_clean();
		}

		if (!empty($args)){
			$expanded = "";
			foreach($args as $input){
				if (!is_string($input)){
					ob_start();
					var_dump($input);
					$expanded .= ob_get_clean()."<Br><Br>";
				}
			}

			$this->debug_output .= "<a class='expander' href='#'>$message</a>";
			$this->debug_output .= "<div class='expandable'>$expanded</div>";
		} else {
			$this->debug_output .= $message;
		}

		$this->debug_output .= "<br><br>";
	
	}

	public function renderDebugOutput(){
		?>
		<style type="text/css">
			#airpress_debugger {
				width:100%;
				font-family: monospace;
				padding:50px;
				font-size: 12px;
				padding-top:100px;
				display:none;
				position: absolute;
				top: 0px;
				left: 0px;
				background-color: #f7f7f7;
				z-index:99998;
			}
			#airpress_debugger .expandable {
				padding: 20px;
				border: 1px dashed #cccccc;
				display: none;
				white-space: pre-wrap;
			}
		</style>
		<script type="text/javascript">
		jQuery(document).ready(function(){
			jQuery("#wp-admin-bar-airpress_debugger_toggle").click(function(e){
				jQuery("#airpress_debugger").toggle();
			});

			jQuery(".expander").click(function(e){
				e.preventDefault();
				jQuery(this).next().fadeToggle();
			});
		});
		</script>
		<div id="airpress_debugger">
		<?php
		foreach($this->debug_stats as $name => $value){
			echo $name.": ".$value."<br>";
		}
		?>
		<hr>
		<?php echo $this->debug_output; ?>
		</div>
		<?php
	}

	function renderDebugToggle( $wp_admin_bar ) {
		$args = array(
			'id'    => 'airpress_debugger_toggle',
			'title' => 'Toggle Airpress Debugger',
			'href'  => '#',
			'meta'  => array( 'class' => 'my-toolbar-page' )
		);
		$wp_admin_bar->add_node( $args );
	}


}
?>