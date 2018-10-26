<?php

function airpress_vp_menu() {

	add_submenu_page(
		"airpress_settings", // parent slug
		"Virtual Posts", // page title
		"Virtual Posts", // menu title
		"manage_options", // capability
		"airpress_vp", // menu_slug
		"airpress_vp_render" // function
	);

}
add_action( 'admin_menu', 'airpress_vp_menu' );

add_action( 'init', "airpress_vp_add_rules" );

// airpress_debug(0,"These are all teh hooks",$wp_filter);

//add_action('rewrite_rules_array','airpress_vp_update_permalinks');
//permalink_structure_changed
//generate_rewrite_rules

function airpress_vp_render( $active_tab = '' ) {
	global $airpress;



	if (isset($_GET['settings-updated'])){
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}
?>
	<!-- Create a header in the default WordPress 'wrap' container -->
	<div class="wrap">
	
		<div id="icon-themes" class="icon32"></div>
		<h2><?php _e( 'Airpress Virtual Posts', 'airpress' ); ?></h2>
		<p>Rather than creating hundreds of pages/posts in Wordpress, simply map a URL pattern to an Airpress table using Airpress Virtual Posts. Each requests that matches the pattern will attempt to retrieve the cooresponding Airtable record and serve it using the specified Wordpress page as a 'template'. If an Airtable record is not found, a 404 is served. If a Wordpress page/post exists for the requested URL, then it will be used instead of the 'template' specified.</p>
		<?php settings_errors(); ?>
		
		<?php
		$configs = get_airpress_configs("airpress_vp",false);
		$active_tab = (isset($_GET['tab']))? $_GET['tab'] : 0;

		?>
		
		<h2 class="nav-tab-wrapper">
			<?php
			foreach($configs as $key => $config):
				$class = ($active_tab == $key)? 'nav-tab-active' : '';
			?>
			<a href="?page=airpress_vp&tab=<?php echo $key; ?>" class="nav-tab <?php echo $class; ?>"><?php echo $config["name"]; ?></a>
			<?php
			endforeach;
			?>
			<a href="?page=airpress_vp&tab=<?php echo count($configs);?>" class="nav-tab">+</a>
		</h2>
		
		<form method="post" action="options.php">
			<?php
				settings_fields( 'airpress_vp'.$active_tab );
				do_settings_sections( 'airpress_vp'.$active_tab );		
				submit_button();
			
			?>
		</form>
		
	</div><!-- /.wrap -->
<?php
}

// function airpress_admin_vp_tab_controller(){
	
// 	if ( // Verify that we're dealing with Airpress
// 		( ! isset($_GET["page"]) && ! isset($_POST["option_page"]) ) ||
// 		( isset($_GET["page"]) && strpos($_GET["page"],"airpress_vp") === false ) || 
// 		( isset($_POST["option_page"]) && strpos($_POST["option_page"],"airpress_vp") === false )
// 	){
// 		// none of our business!		
// 		return;
// 	}

// 	if (isset($_GET["delete"]) && $_GET["delete"] == "true"){
// 		delete_airpress_config("airpress_vp",$_GET['tab']);
// 		header("Location: ".admin_url("/admin.php?page=airpress_vp"));
// 		exit;
// 	} else {
// 		$configs = get_airpress_configs("airpress_vp",false);
// 		$requested_tab = (isset($_GET['tab']))? $_GET['tab'] : 0;
// 	}

// 	if (empty($configs) || !isset($configs[$requested_tab])){
// 		$config = array("name" => "New Configuration");
// 		$configs[] = $config;
// 		$active_tab = count($configs)-1;
// 		set_airpress_config("airpress_vp",$active_tab,$config);		
// 	} else {
// 		$active_tab = $requested_tab;
// 	}

// 	$_GET['tab'] = $active_tab;

// 	foreach($configs as $key => $config){
// 		airpress_admin_vp_tab($key,$config);
// 	}
// }
// add_action( 'admin_init', 'airpress_admin_vp_tab_controller');

/***********************************************/
# TAB: DEFAULT
/***********************************************/
function airpress_admin_vp_tab($key,$config) {

	$option_name = "airpress_vp".$key;
	//$options = get_option( $option_name );

	$defaults = array(
		"name"			=> "New Configuration",
		"connection"	=> null,
		"pattern"		=> "^folder/([^/]+)/?$",
		"default"		=> "folder/my-unique-identifier/",
		"formula"		=> "{Your Airtable Field} = '$1'",
		"sort"			=> "",
		"table"			=> "Your Airtable Table",
		"view"			=> "",
		"field"			=> "Your Airtable Field",
		"field2"		=> "Your Airtable Field2",
		"template"		=> null,

	);

	$options = array_merge($defaults,$config);

	################################
	################################
	$section_title = "Virtual Posts";
	$section_name = "airpress_vp".$key;

	add_settings_section(
		$section_name,
		__( $section_title, 'airpress' ),
		"airpress_admin_vp_render_section",
		$option_name
	);
	
	################################
	$field_name = "name";
	$field_title = "Configuration Name";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vp_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "connection";
	$field_title = "Select Connection";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vp_render_element_select_connections', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	################################
	$section_title = "";
	$section_name = "airpress_vp".$key;
	$option_name = 'airpress_vp'.$key;

	add_settings_section(
		$section_name,
		__( $section_title, 'airpress' ),
		"airpress_admin_vp_render_section",
		$option_name
	);

	################################
	$field_name = "pattern";
	$field_title = "URL Pattern to Match";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vp_render_element_regex', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "default";
	$field_title = "Test url";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vp_render_element_test', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "formula";
	$field_title = "Filter by formula";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vp_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "sort";
	$field_title = "Sort results";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vp_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "sort_direction";
	$field_title = "Sort direction";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vp_render_element_select__direction', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "table";
	$field_title = "Airtable Table Name";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vp_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	###############################
	$field_name = "view";
	$field_title = "Airtable Table View Name";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vp_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	###############################
	$field_name = "field";
	$field_title = "Airtable Field to be used as post_name";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vp_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	###############################
	$field_name = "field2";
	$field_title = "Airtable Field to be used as post_title";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vp_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "template";
	$field_title = "Map to this page";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vp_render_element_select__page', $option_name, $section_name, array($options,$option_name,$field_name) );

	###############################
	$field_name = "delete";
	$field_title = "Delete Configuration?";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vp_render_element_delete', $option_name, $section_name, array($options,$option_name,$field_name) );

	register_setting($option_name,$option_name,"airpress_vp_validation");
}

function airpress_vp_validation($config){
	// global $wp_rewrite;
	// airpress_vp_add_rule($config);
	// $wp_rewrite->flush_rules();

	if ( isset($config["sort"]) && $config["sort"] == "Your Airtable Field"){
		$config["sort"] = "";
	}

	return $config;
}

function airpress_vp_add_rule($config){
	return add_rewrite_rule($config["pattern"], 'index.php?page_id='.$config["template"] , 'top');
}

function airpress_vp_add_rules(){
	global $wp_rewrite;

	$configs = get_airpress_configs("airpress_vp");

	foreach($configs as $config){
		if (isset($config["pattern"]) && !empty($config["pattern"])){
			airpress_vp_add_rule($config);	
		}

		if ( isset($config["default"]) && ! empty($config["default"]) ){
			$permalink = get_permalink($config["template"]);
			$protocol = (empty($_SERVER["HTTPS"]))? "http" : "https";
			$remove = $protocol."://".$_SERVER["HTTP_HOST"]."/";
			$permalink = trim(str_replace($remove,"",$permalink),"/");
			$pattern = "^".$permalink."/?";

			add_rewrite_rule($pattern, "index.php?page_id=".$config["template"]."&default_vp=".rawurlencode($config["default"]) , 'top');
			//die("Redirect: $pattern => ".$config["default"]);
		}

	}

}

function airpress_admin_vp_render_section__general() {
	echo '<p>' . __( 'Provides examples of the five basic element types.', 'sandbox' ) . '</p>';
}

function airpress_admin_vp_render_section() {
	echo '<p>' . __( '', 'airpress' ) . '</p>';
}

function airpress_admin_vp_render_element_text($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	echo '<input type="text" id="' . $field_name . '" name="' . $option_name . '[' . $field_name . ']" value="' . $options[$field_name] . '" />';

	if ( $field_name == "name" and $options[$field_name] == "New Configuration" ){
		echo "<p style='color:red'>You must change the configuration name from 'New Configuration' to something unique!</p>";
	}
}

function airpress_admin_vp_render_element_regex($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	echo '<input type="text" id="' . $field_name . '" name="' . $option_name . '[' . $field_name . ']" value="' . $options[$field_name] . '" />';

	echo "<br>";
	echo "<p>To experiment with more about creating patterns, visit https://regex101.com/ — Please note that Airpress does NOT need front-slashes escaped.</p>";
}

function airpress_admin_vp_render_element_test($args) {
	global $airpress;
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	echo '<input type="text" id="' . $field_name . '" name="' . $option_name . '[' . $field_name . ']" value="' . $options[$field_name] . '" />';

	if (
		isset($options["default"]) && 
		isset($options["pattern"]) && 
		isset($options["table"]) && 
		isset($options["field"]) && 
		isset($options["formula"])
	){
		$request = new StdClass();
		$request->request = trim($options["default"],"/")."/";
		$request->matched_rule = $options["pattern"];
		$query = new AirpressQuery();
		$collection = $airpress->simulateVirtualPost($request,$query);

		if ( is_airpress_collection($collection) ){
			echo "<br>This test URL matches ".count($collection)." records in table <em>".$options["table"]."</em>";
		} else {
			echo "<br>No results from test url.<br>";
			if ( $query->hasErrors() ){
				echo "ERRORS:<br>";
				foreach( $query->getErrors() as $error ){
					echo "<strong style='color:red'>{$error['code']}</strong>: {$error['message']}<br>";
				}
			}
		}

	}
}

function airpress_admin_vp_render_element_toggle($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$checked = checked( 1, isset( $options[$field_name] ) ? $options[$field_name] : 0, false );
	echo '<input type="checkbox" id="' . $field_name . '" name="' . $option_name . '[' . $field_name . ']" value="1" '.$checked.'/>';
	echo '<label for="'.$field_name.'">&nbsp;'  . $field_name . '</label>'; 
}

function airpress_admin_vp_render_element_select__posttypes($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$post_types = airpress_get_posttypes_available();

	echo '<select id="' . $field_name . '" name="' . $option_name . '[' . $field_name . '][]" multiple>';
	foreach ( $post_types  as $post_type ) {
		$selected = (in_array($post_type, $options[$field_name]))? "selected" : "";
		echo '<option value="'.$post_type.'" '.$selected.'>'.$post_type.'</option>';
	}
	echo '</select>';
}

function airpress_admin_vp_render_element_select_connections($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$connections = get_airpress_configs("airpress_cx");

	echo '<select id="' . $field_name . '" name="' . $option_name . '[' . $field_name . ']">';
	foreach ( $connections  as $connection ) {
		$selected = ($connection["name"] == $options[$field_name])? "selected" : "";
		echo '<option value="'.$connection["name"].'" '.$selected.'>'.$connection["name"].'</option>';
	}
	echo '</select>';
}

function airpress_admin_vp_render_element_select__page($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$pages = get_pages(); 
	
	echo '<select id="' . $field_name . '" name="' . $option_name . '[' . $field_name . ']">';

	foreach ( $pages as $page ) {
		$selected = ($options[$field_name] == $page->ID)? " selected" : "";
		$option = '<option value="' . $page->ID . '"'.$selected.'>';
		$option .= $page->post_title." (".$page->post_name.")";
		$option .= '</option>';
		echo $option;
	}
	echo '</select>';
}

function airpress_admin_vp_render_element_select__direction($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$directions = array(["value" => "asc","label" => "Ascending (A-Z)"],["value" => "desc","label" => "Descending (Z-A)"]);
	
	echo '<select id="' . $field_name . '" name="' . $option_name . '[' . $field_name . ']">';

	foreach ( $directions as $d ) {
		$selected = ($options[$field_name] == $d["value"])? " selected" : "";
		$option = '<option value="' . $d["value"] . '"'.$selected.'>';
		$option .= $d["label"];
		$option .= '</option>';
		echo $option;
	}
	echo '</select>';
}

function airpress_admin_vp_render_element_delete($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$tab = (int)$_GET["tab"];
	echo "<a href='?page=airpress_vp&tab=$tab&delete=true'>Yes, delete this configuration</a>";
}


?>