<?php

function airpress_vf_menu() {

	add_submenu_page(
		"airpress_settings", // parent slug
		"Virtual Fields", // page title
		"Virtual Fields", // menu title
		"administrator", // capability
		"airpress_vf", // menu_slug
		"airpress_vf_render" // function
	);

}
add_action( 'admin_menu', 'airpress_vf_menu' );

function airpress_vf_render( $active_tab = '' ) {
	global $airpress;
?>
	<!-- Create a header in the default WordPress 'wrap' container -->
	<div class="wrap">
	
		<div id="icon-themes" class="icon32"></div>
		<h2><?php _e( 'Airpress Virtual Fields', 'airpress' ); ?></h2>
		<p>Create as many configuration groups as you would like.</p>
		<?php settings_errors(); ?>
		
		<?php
		$configs = get_airpress_configs("airpress_vf",false);
		$active_tab = (isset($_GET['tab']))? $_GET['tab'] : 0;

		?>
		
		<h2 class="nav-tab-wrapper">
			<?php
			foreach($configs as $key => $config):
				$class = ($active_tab == $key)? 'nav-tab-active' : '';
			?>
			<a href="?page=airpress_vf&tab=<?php echo $key; ?>" class="nav-tab <?php echo $class; ?>"><?php echo $config["name"]; ?></a>
			<?php
			endforeach;
			?>
			<a href="?page=airpress_vf&tab=<?php echo count($configs);?>" class="nav-tab">+</a>
		</h2>
		
		<form method="post" action="options.php">
			<?php
				settings_fields( 'airpress_vf'.$active_tab );
				do_settings_sections( 'airpress_vf'.$active_tab );		
				submit_button();
			
			?>
		</form>
		
	</div><!-- /.wrap -->
<?php
}

function airpress_admin_vf_tab_controller(){

	if (isset($_GET["page"]) && $_GET["page"] != "airpress_vf"){
		return;
	}

	if (isset($_GET["delete"]) && $_GET["delete"] == "true"){
		delete_airpress_config("airpress_vf",$_GET['tab']);
		header("Location: ".admin_url("/admin.php?page=airpress_vf"));
		exit;
	} else {
		$configs = get_airpress_configs("airpress_vf",false);
		$requested_tab = (isset($_GET['tab']))? $_GET['tab'] : 0;
	}

	if (empty($configs) || !isset($configs[$requested_tab])){
		$config = array("name" => "New Configuration");
		$configs[] = $config;
		$active_tab = count($configs)-1;
		set_airpress_config("airpress_vf",$active_tab,$config);		
	} else {
		$active_tab = $requested_tab;
	}

	$_GET['tab'] = $active_tab;

	foreach($configs as $key => $config){
		airpress_admin_vf_tab($key,$config);
	}
}
add_action( 'admin_init', 'airpress_admin_vf_tab_controller');

/***********************************************/
# TAB: DEFAULT
/***********************************************/
function airpress_admin_vf_tab($key,$config) {

	$option_name = "airpress_vf".$key;
	//$options = get_option( $option_name );

	$defaults = array();

	$options = array_merge($defaults,$config);

	################################
	################################
	$section_title = "Virtual Fields";
	$section_name = "airpress_vf".$key;

	add_settings_section(
		$section_name,
		__( $section_title, 'airpress' ),
		"airpress_admin_vf_render_section",
		$option_name
	);
	
	################################
	$field_name = "name";
	$field_title = "Configuration Name";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vf_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "connection";
	$field_title = "Select Connection";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vf_render_element_select_connections', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	################################
	$section_title = "Auto Get Record by URL Regexp";
	$section_name = "airpress_vf".$key;
	$option_name = 'airpress_vf'.$key;

	add_settings_section(
		$section_name,
		__( $section_title, 'airpress' ),
		"airpress_admin_vf_render_section",
		$option_name
	);

	################################
	$field_name = "regexp_pattern";
	$field_title = "URL Pattern to Match";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "regexp_formula";
	$field_title = "Filter by formula";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "regexp_table";
	$field_title = "Airtable Table Name";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	###############################
	$field_name = "regexp_field";
	$field_title = "Airtable Field to be used as post_name";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "regexp_template";
	$field_title = "Map to this page";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_render_element_select__page', $option_name, $section_name, array($options,$option_name,$field_name) );

	###############################
	$field_name = "delete";
	$field_title = "Delete Configuration?";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vf_render_element_delete', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	// $field_name = "regexp_single";
	// $field_title = "Single Post Only";
	// add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_render_element_toggle', $option_name, $section_name, array($options,$option_name,$field_name) );

	register_setting($option_name,$option_name,"airpress_vf_validation");
}

function airpress_vf_validation($input){
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
	return $input;
}

function airpress_admin_vf_render_section__general() {
	echo '<p>' . __( 'Provides examples of the five basic element types.', 'sandbox' ) . '</p>';
}

function airpress_admin_vf_render_section() {
	echo '<p>' . __( 'Airpress settings for this particular post type.', 'airpress' ) . '</p>';
}

function airpress_admin_vf_render_element_text($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	echo '<input type="text" id="' . $field_name . '" name="' . $option_name . '[' . $field_name . ']" value="' . $options[$field_name] . '" />';
}

function airpress_admin_vf_render_element_toggle($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$checked = checked( 1, isset( $options[$field_name] ) ? $options[$field_name] : 0, false );
	echo '<input type="checkbox" id="' . $field_name . '" name="' . $option_name . '[' . $field_name . ']" value="1" '.$checked.'/>';
	echo '<label for="'.$field_name.'">&nbsp;'  . $field_name . '</label>'; 
}

function airpress_admin_vf_render_element_select__posttypes($args) {
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

function airpress_admin_vf_render_element_select_connections($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$connections = get_airpress_configs("airpress_cx");

	echo '<select id="' . $field_name . '" name="' . $option_name . '[' . $field_name . ']">';
	foreach ( $connections  as $connection ) {
		$selected = ( $connection["name"] == $options[$field_name] )? "selected" : "";
		echo '<option value="'.$connection["name"].'" '.$selected.'>'.$connection["name"].'</option>';
	}
	echo '</select>';
}

function airpress_admin_vf_render_element_select__page($args) {
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

function airpress_admin_vf_render_element_delete($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$tab = (int)$_GET["tab"];
	echo "<a href='?page=airpress_vf&tab=$tab&delete=true'>Yes, delete this configuration</a>";
}

?>