<?php


function airpress_cx_menu() {

	add_menu_page(
		'just a redirect',	// page title
		'Airpress',			// menu title
		'manage_options',		// capabilities
		'airpress_settings',	// menu ID
		'airpress_admin_render'	// function that renders
	);

	add_submenu_page(
		"airpress_settings", // parent slug
		"Airtable Connections", // page title
		"Airtable Connections", // menu title
		"manage_options", // capability
		"airpress_cx", // menu_slug
		"airpress_cx_render" // function
	);

	add_submenu_page(
		"airpress_settings", // parent slug
		"Debug Info", // page title
		"Debug Info", // menu title
		"manage_options", // capability
		"airpress_db", // menu_slug
		"airpress_db_render" // function
	);

	remove_submenu_page('airpress_settings', 'airpress_settings');
	
}
add_action( 'admin_menu', 'airpress_cx_menu' );

function airpress_db_render( $active_tab = '' ) {
	global $wpdb;

	if (isset($_GET["delete-expired-transients"]) && $_GET["delete-expired-transients"] == "true"){

		$all = isset($_GET["all"]) && $_GET["all"] === "true";
		airpress_flush_cache( $all );

	}

	$s = memory_get_usage();
	$results = $wpdb->get_results( 'SELECT * FROM wp_options WHERE option_name LIKE "_transient_aprq_%" ORDER BY option_id ASC', OBJECT );
	$e = memory_get_usage();
	$expirations = $wpdb->get_results( 'SELECT * FROM wp_options WHERE option_name LIKE "_transient_timeout_aprq_%" ORDER BY option_value ASC', OBJECT );

	$exp = array();
	foreach($expirations as $row){
		$hash = str_replace("_transient_timeout_aprq_","",$row->option_name);
		$exp[$hash] = $row->option_value;
	}

	?>
	<div class="wrap">
	<?php
		echo "There are ".count($results)." cached queries using ".round((($e-$s)/1024)/1024,2)." MB memory.<br><br>";
		$now = time();
		foreach($results as $row){
			$hash = str_replace("_transient_aprq_","",$row->option_name);
			$data = unserialize($row->option_value);
			$data_age = round( ($now - $data["created_at"])/60/60, 2 );
			$data_expire = round( ($exp[$hash]-$now)/60/60, 2 );
			echo "$data_age hours old. Transient expires in $data_expire hours.<br>";
		}
	?>
	<br><br>
	<a href="<?php echo admin_url("admin.php?page=airpress_db&delete-expired-transients=true"); ?>">Delete Expired Transients?</a><br>
	<a href="<?php echo admin_url("admin.php?page=airpress_db&delete-expired-transients=true&all=true"); ?>">Delete All Transients (completely clear cache)?</a>
	</div>
	<?php
}

function airpress_cx_render( $active_tab = '' ) {
	global $airpress;

?>
	<!-- Create a header in the default WordPress 'wrap' container -->
	<div class="wrap">
	
		<div id="icon-themes" class="icon32"></div>
		<h2><?php _e( 'Airtable API Settings', 'airpress' ); ?></h2>
		<p>You may find that multiple API Keys or APP IDs are required for your website. Create as many as you need!</p>
		<?php settings_errors(); ?>
		
		<?php
		$configs = get_airpress_configs("airpress_cx",false);
		$active_tab = (isset($_GET['tab']))? $_GET['tab'] : 0;

		?>
		
		<h2 class="nav-tab-wrapper">
			<?php
			foreach($configs as $key => $config):
				$class = ($active_tab == $key)? 'nav-tab-active' : '';
			?>
			<a href="?page=airpress_cx&tab=<?php echo $key; ?>" class="nav-tab <?php echo $class; ?>"><?php echo $config["name"]; ?></a>
			<?php
			endforeach;
			?>
			<a href="?page=airpress_cx&tab=<?php echo count($configs);?>" class="nav-tab">+</a>
		</h2>
		
		<form method="post" action="options.php">
			<?php
				settings_fields( 'airpress_cx'.$active_tab );
				do_settings_sections( 'airpress_cx'.$active_tab );		
				submit_button();
			
			?>
		</form>
		
	</div><!-- /.wrap -->
<?php
}

function airpress_admin_cx_tab_controller(){

	$airpress_config_initials = false;

	if ( isset($_GET["page"]) && preg_match("/^airpress_(..)$/",$_GET["page"],$matches) ){
		$airpress_config_initials = $matches[1];
	} else if ( isset($_POST["option_page"]) && preg_match("/^airpress_(..).*$/",$_POST["option_page"],$matches) ){
		$airpress_config_initials = $matches[1];
	}
	
	if ( ! $airpress_config_initials || $airpress_config_initials == "db"){
		// none of our business!		
		return;
	}

	if (isset($_GET["delete"]) && $_GET["delete"] == "true"){
		delete_airpress_config("airpress_".$airpress_config_initials,$_GET['tab']);
		header("Location: ".admin_url("/admin.php?page=airpress_".$airpress_config_initials));
		exit;
	} else {
		$configs = get_airpress_configs("airpress_".$airpress_config_initials,false);
		$requested_tab = (isset($_GET['tab']))? $_GET['tab'] : 0;
	}

	if (empty($configs) || !isset($configs[$requested_tab])){
		$config = array("name" => "New Configuration");
		$configs[] = $config;
		$active_tab = count($configs)-1;
		set_airpress_config("airpress_".$airpress_config_initials,$active_tab,$config);		
	} else {
		$active_tab = $requested_tab;
	}

	$_GET['tab'] = $active_tab;

	foreach($configs as $key => $config){
		$function = "airpress_admin_".$airpress_config_initials."_tab";
		call_user_func($function,$key,$config);
	}
}
add_action( 'admin_init', 'airpress_admin_cx_tab_controller');

/***********************************************/
# TAB: DEFAULT
/***********************************************/
function airpress_admin_cx_tab($key,$config) {

	$option_name = "airpress_cx".$key;
	//$options = get_option( $option_name );

	$defaults = array(
			"api_key" => "",
			"app_id" => "",
			"refresh" => MINUTE_IN_SECONDS * 5,
			"expire" => DAY_IN_SECONDS,
			"api_url" => "https://api.airtable.com/v0/",
			"fresh"  => "fresh",
			"debug"	=> 0,
			"log"	=> dirname(dirname(dirname(__FILE__)))."/airpress.log"
		);

	$options = array_merge($defaults,$config);

	################################
	################################
	$section_title = "Airtable API Connections";
	$section_name = "airpress_cx".$key;

	add_settings_section(
		$section_name,
		__( $section_title, 'airpress' ),
		"airpress_admin_cx_render_section",
		$option_name
	);
	
	################################
	$field_name = "name";
	$field_title = "Configuration Name";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "api_key";
	$field_title = "Airtable API Key";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "app_id";
	$field_title = "Airtable APP ID";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "api_url";
	$field_title = "Airtable API URL";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	################################
	$section_title = "Request Caching";
	$section_name = "airpress_cx".$key;

	add_settings_section(
		$section_name,
		__( $section_title, 'airpress' ),
		"airpress_admin_cx_render_section",
		$option_name
	);

	################################
	$field_name = "refresh";
	$field_title = "Refresh";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "expire";
	$field_title = "Expire";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "fresh";
	$field_title = "Query var to force refresh cache for this request";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "debug";
	$field_title = "Enable Debugging";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_select__debug', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "log";
	$field_title = "Debug Logfile";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	###############################
	$field_name = "delete";
	$field_title = "Delete Configuration?";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_delete', $option_name, $section_name, array($options,$option_name,$field_name) );

	register_setting($option_name,$option_name,"airpress_cx_validation");
}

function airpress_cx_validation($input){
	global $wp_rewrite;

	if ($input["debug"] == 1 || $input["debug"] == 2){

		if ( $h = @fopen($input["log"], "a") ){
			$message = "log file created at ".$input["log"];
			fwrite($h, $message."\n");
			fclose($h);
		} else {
			$input["debug"] = 0;
			add_settings_error('airpress_cx_log', esc_attr( 'settings_updated' ), esc_attr($input["log"])." is not writable.","error");
		}

	}

	$wp_rewrite->flush_rules();
	return $input;
}

function airpress_admin_cx_render_section__general() {
	echo '<p>' . __( 'Provides examples of the five basic element types.', 'sandbox' ) . '</p>';
}

function airpress_admin_cx_render_section() {
	echo '<p>' . __( '', 'airpress' ) . '</p>';
}

function airpress_admin_cx_render_element_text($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	echo '<input type="text" id="' . $field_name . '" name="' . $option_name . '[' . $field_name . ']" value="' . $options[$field_name] . '" />';
}

function airpress_admin_cx_render_element_toggle($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$checked = checked( 1, isset( $options[$field_name] ) ? $options[$field_name] : 0, false );
	echo '<input type="checkbox" id="' . $field_name . '" name="' . $option_name . '[' . $field_name . ']" value="1" '.$checked.'/>';
	echo '<label for="'.$field_name.'">&nbsp;'  . $field_name . '</label>'; 
}

function airpress_admin_cx_render_element_select__posttypes($args) {
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

function airpress_admin_cx_render_element_select_connections($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$connections = get_airpress_configs("airpress_cx");

	echo '<select id="' . $field_name . '" name="' . $option_name . '[' . $field_name . '][]" multiple>';
	foreach ( $connections  as $connection ) {
		$selected = (in_array($connection["name"], $options[$field_name]))? "selected" : "";
		echo '<option value="'.$connection["name"].'" '.$selected.'>'.$connection["name"].'</option>';
	}
	echo '</select>';
}

function airpress_admin_cx_render_element_select__page($args) {
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

function airpress_admin_cx_render_element_select__debug($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	echo '<select id="' . $field_name . '" name="' . $option_name . '[' . $field_name . ']">';
	$select_options = array(0 => "Disabled", 1 => "Admin Bar & Logfile", 2 => "Logfile only", 3 => "Admin Bar only");

	foreach ( $select_options as $value => $label ) {
		$selected = ($options[$field_name] == $value)? " selected" : "";
		$option = '<option value="' . $value . '"'.$selected.'>';
		$option .= $label;
		$option .= '</option>';
		echo $option;
	}
	echo '</select>';
}

function airpress_admin_cx_render_element_delete($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$tab = (int)$_GET["tab"];
	echo "<a href='?page=airpress_cx&tab=$tab&delete=true'>Yes, delete this configuration</a>";
}

?>