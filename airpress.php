<?php
/*
Plugin Name: Airpress
Plugin URI: http://chetmac.com/airpress
Description: Extend Wordpress Posts, Pages, and Custom Fields with data from remote Airtable records.
Version: 1.1.49
Author: Chester McLaughlin
Author URI: http://chetmac.com
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

$airpress_version = "1.1.49";

if ( ! defined( 'WPINC' ) ) {
	 die;
}

// Create a helper function for easy SDK access.
function air_fs() {
    global $air_fs;

    if ( ! isset( $air_fs ) ) {
        // Include Freemius SDK.
        require_once dirname(__FILE__) . '/freemius/start.php';

        $air_fs = fs_dynamic_init( array(
            'id'                  => '2758',
            'slug'                => 'airpress',
            'type'                => 'plugin',
            'public_key'          => 'pk_67f31e3d8769bc7d4e1cacda132d4',
            'is_premium'          => false,
            'has_addons'          => false,
            'has_paid_plans'      => false,
            'menu'                => array(
                'slug'           => 'airpress_settings',
                'first-path'     => 'admin.php?page=airpress_cx',
            ),
        ) );
    }

    return $air_fs;
}

// Init Freemius.
air_fs();
// Signal that SDK was initiated.
do_action( 'air_fs_loaded' );

function air_fs_custom_connect_message_on_update(
    $message,
    $user_first_name,
    $plugin_title,
    $user_login,
    $site_link,
    $freemius_link
) {
    return sprintf(
        __( 'Hey %1$s' ) . ',<br>' .
        __( 'I\'m so pleased you\'re using %2$s! Please help me improve it! If you opt-in, some data about your usage of %2$s will be sent to %5$s. If you skip this, that\'s okay! %2$s will still work just fine.', 'airpress' ),
        $user_first_name,
        '<b>' . $plugin_title . '</b>',
        '<b>' . $user_login . '</b>',
        $site_link,
        $freemius_link
    );
}

air_fs()->add_filter('connect_message_on_update', 'air_fs_custom_connect_message_on_update', 10, 6);

require_once("lib/chetmac/Airpress.php");
require_once("lib/chetmac/AirpressConnect.php");
require_once("lib/chetmac/AirpressQuery.php");
require_once("lib/chetmac/AirpressRecord.php");
require_once("lib/chetmac/AirpressCollection.php");
require_once("lib/chetmac/AirpressVirtualFields.php");
require_once("lib/chetmac/AirpressVirtualPosts.php");

if (is_admin()){
	require_once("lib/chetmac/AirpressAdmin.php");	
	require_once("lib/chetmac/AirpressVirtualPostsAdmin.php");
	require_once("lib/chetmac/AirpressVirtualFieldsAdmin.php");
}

$airpress = new Airpress();
add_action( 'plugins_loaded', array($airpress,'init') );

if ( is_admin()){
	add_action( 'plugins_loaded', array($airpress,"check_for_update") );
}

if ( ! class_exists("Parsedown")){
	require_once("lib/erusev/Parsedown.php");
}

if (!isset($parsedown)){
	$parsedown = new Parsedown();
	$parsedown->setBreaksEnabled(true);
}

// add ajax handle which will act as trigger
add_action( 'wp_ajax_nopriv_airpress_deferred', 'airpress_execute_deferred_queries' );
add_action( 'wp_ajax_airpress_deferred', 'airpress_execute_deferred_queries' );

function airpress_get_current_record(){
	global $airpress, $post;

	if ( is_airpress_collection($post->AirpressCollection) ){

		if ( ! empty($airpress->loopscope) ){
			return $airpress->loopscope[0];
		} else {
			return $post->AirpressCollection[0];
		}

	}

}

function airpress_execute_deferred_queries() {
	global $airpress;

	$airpress->run_deferred_queries($_GET["stash_key"]);

	wp_send_json_success(array());
}


function is_airpress_attachment($input=null){
	return is_array($input) && isset($input[0]["url"]);	
}

function is_airpress_collection($input=null){
	if (isset($input) && is_object($input) && get_class($input) == "AirpressCollection"){
		return true;
	}

	return false;
}

function is_airpress_empty($input=null){
	if (!is_airpress_collection($input) || !is_airpress_record($input[0])){
		return true;
	}

	return false;
}

function is_airpress_record($input=null){
	if (isset($input) && is_object($input) && get_class($input) == "AirpressRecord"){
		return true;
	}

	return false;
}

function get_airpress_configs($option_group,$run_filter=true){
	$configs = array();
	$id = 0;
	while ($config = get_airpress_config($option_group,$id) ){

		$config["id"] = $id; // never assumed the ID will be the same
		$configs[] = $config;
		$id++;
	}

	if (count($configs) == 1 && $configs[0]["name"] == "New Configuration"){
		$configs = array();
	}

	if ($run_filter){
		$configs = apply_filters( 'airpress_configs', $configs, $option_group );
	}

	return $configs;
}

function get_airpress_config($option_group, $id){
	if ( !is_int($id) ){
		$configs = get_airpress_configs($option_group);
		foreach($configs as $config){
			if ($id == $config["name"]){
				break;
			}
		}
	} else {
		$config = get_option($option_group.$id);
	}

	if (!$config)
		return false;

	return $config;
}

function delete_airpress_config($option_group, $id){
	$options = get_airpress_configs($option_group,false);

	foreach($options as $key => $option){
		delete_option($option_group.$key);
	}

	unset($options[$id]);
	$options = array_values($options);

	foreach($options as $key => $option){
		$option["id"] = $key;
		add_option($option_group.$key,$option);
	}

}

function set_airpress_config($option_group, $id, $config){
	update_option($option_group.$id,$config);
}

function is_airpress_force_fresh($config=0){
	
	if ( ! is_array($config) ){
		$config = get_airpress_config("airpress_cx",$config);
	}

	$fresh_param = $config["fresh"];

	if (isset($_GET[$fresh_param]) && $_GET[$fresh_param] == "true"){
		return true;
	} else {
		return false;
	}
}

function airpress_getArrayValue($array,$keys){
	// for backwards compatibility
	return airpress_getArrayValues($array,$keys);
}

function airpress_getArrayValues($array,$keys){
	while(!empty($keys)){
		$key = array_shift($keys);

		if (isset($array[$key])){
			$array = $array[$key];
		} else {
			// Maybe it's an array of arrays
			$return_array = array();
			foreach($array as $item){
				if ( isset($item[$key]) ){
					$return_array[] = $item[$key];
				}
			}
			if ( ! empty($return_array) ){
				$array = $return_array;
			}
		}
	}
	return $array;
}

function airpress_parse_template($template, $record, $replacementFields=null){

	if ( is_null($replacementFields) ){
	    preg_match_all("/{{([^}]*)}}/", $template, $matches);

	    $replacementFields = array_unique($matches[1]);
	}

	foreach($replacementFields as $replacementField){

		$keys = explode("|", $replacementField);
		$field = array_shift($keys);
		$replacementValue = "";
		
		if ( strtolower($field) == "record_id" ){
			if ( is_airpress_record($record)){
				$replacementValue = $record->record_id();
			} else {
				airpress_debug(0,"Attempting to populate field $field on a non-populated record",$keys);
			}
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

	return $template;

}

function airpress_flush_cache($all=false){

	global $wpdb;

	$to_delete = array();
	$expirations = $wpdb->get_results( 'SELECT * FROM wp_options WHERE option_name LIKE "_transient_timeout_aprq_%" ORDER BY option_value ASC', OBJECT );

	$exp = array();
	foreach($expirations as $row){
		$hash = str_replace("_transient_timeout_aprq_","",$row->option_name);
		if (time() >= $row->option_value || $all === true ){
			$to_delete[] = "_transient_timeout_aprq_".$hash;
			$to_delete[] = "_transient_aprq_".$hash;
		}
	}

	if (!empty($to_delete)){
		$in = '"'.implode('","', $to_delete).'"';
		$wpdb->get_results( 'DELETE FROM wp_options WHERE option_name IN ('.$in.')', OBJECT );
	}

}

function airpress_debug($cx=0,$message=null,$object=null){
	global $airpress;

	if ( !isset($airpress)){
		// somehow this happens with wp cli
		return;		
	}

	if ( is_null($message) ){
		$message = $cx;
		$cx = 0;
	}

	if ( ! is_array($cx) ){
		$config = get_airpress_config("airpress_cx",$cx);
	} else {
		$config = $cx;
	}
	
	if ( isset($config["debug"]) ){

		if ( $config["debug"] == 1 || $config["debug"] == 2 ){

			$object_string = print_r($object,true);
			$object_string = str_replace(substr($config["api_key"],3,-3),"*******",$object_string);
			$object_string = str_replace(substr($config["app_id"],3,-3),"*******",$object_string);

			if ( $h = @fopen($config["log"], "a") ){
				fwrite($h, $message."\n");
				if (isset($object)){
					fwrite($h, "#########################################\n".$object_string."\n#########################################\n\n");
				}
				fclose($h);
			}

		}

		if ( $config["debug"] == 1 || $config["debug"] == 3 ){

			if ( ! is_null($object) ){
				// ob_start();
				// var_dump($object);
				// $expanded = ob_get_clean();;

				$airpress->debug_output .= "+ <a class='expander' href='#'>$message</a>";
				$airpress->debug_output .= "<div class='expandable'>".print_r($object_string,true)."</div>";
				//$airpress->debug_output .= "<div class='expandable'>$expanded</div>";
			} else {
				$airpress->debug_output .= $message;
			}

			$airpress->debug_output .= "<br><br>";

		}

	}
	
}

// Look at me being backwards compatible while 
// working towards using namespaced functions.
if ( ! function_exists("is_cornerstone") ){
	function is_cornerstone(){
		return is_airpress_cornerstone();
	}
}

function is_airpress_cornerstone(){
	if (isset($_GET["action"]) && $_GET["action"] == "cs_render_element"){
		// Cornerstone Element Render
		return "render";
	} else if (isset($_GET["cornerstone_preview"])){
		// Cornerstone Admin Preview iFrame
		return "preview";
	} else if (isset($_GET["cornerstone"])){
		// Cornerstone Admin
		return "admin";
	}

	return false;
}

function is_airpress_elementor(){
	if (isset($_POST["action"]) && $_POST["action"] == "elementor_ajax"){
		// elementor Element Render
		return "render";
	}

	return false;
}

function is_airpress_compatible_page_builder(){

	if ( $mode = is_airpress_cornerstone() ){
		return $mode;
	}

	if ( $mode = is_airpress_elementor() ){
		return $mode;
	}
	
	return false;

}

/*
to do: Allow cacheImageFields via shortcodes
*/

?>