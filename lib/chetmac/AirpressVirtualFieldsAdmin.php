<?php

function airpress_vf_menu() {

	add_submenu_page(
		"airpress_settings", // parent slug
		"Virtual Fields", // page title
		"Virtual Fields", // menu title
		"manage_options", // capability
		"airpress_vf", // menu_slug
		"airpress_vf_render" // function
	);

}
add_action( 'admin_menu', 'airpress_vf_menu' );

//add_action('rewrite_rules_array','airpress_vf_update_permalinks');
//permalink_structure_changed
//generate_rewrite_rules

function airpress_vf_render( $active_tab = '' ) {
	global $airpress;



	if (isset($_GET['settings-updated'])){
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}
?>
	<!-- Create a header in the default WordPress 'wrap' container -->
	<div class="wrap">
	
		<div id="icon-themes" class="icon32"></div>
		<h2><?php _e( 'Airpress Virtual Fields', 'airpress' ); ?></h2>
		<p>Managing custom fields for hundreds or thousands of posts can be tedious and daunting! Airpress Virtual Fields allow you to automatically retrieve Airtable records for each post/page/etc by specifying a Wordpress field (such as ID or post_name) and an Airtable table and field.</p>
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

// function airpress_admin_vf_tab_controller(){
		
// 	if ( // Verify that we're dealing with Airpress
// 		( ! isset($_GET["page"]) && ! isset($_POST["option_page"]) ) ||
// 		( isset($_GET["page"]) && strpos($_GET["page"],"airpress_vf") === false ) || 
// 		( isset($_POST["option_page"]) && strpos($_POST["option_page"],"airpress_vf") === false )
// 	){
// 		// none of our business!		
// 		return;
// 	}

// 	if (isset($_GET["delete"]) && $_GET["delete"] == "true"){
// 		delete_airpress_config("airpress_vf",$_GET['tab']);
// 		header("Location: ".admin_url("/admin.php?page=airpress_vf"));
// 		exit;
// 	} else {
// 		$configs = get_airpress_configs("airpress_vf",false);
// 		$requested_tab = (isset($_GET['tab']))? $_GET['tab'] : 0;
// 	}

// 	if (empty($configs) || !isset($configs[$requested_tab])){
// 		$config = array("name" => "New Configuration");
// 		$configs[] = $config;
// 		$active_tab = count($configs)-1;
// 		set_airpress_config("airpress_vf",$active_tab,$config);		
// 	} else {
// 		$active_tab = $requested_tab;
// 	}

// 	$_GET['tab'] = $active_tab;

// 	foreach($configs as $key => $config){
// 		airpress_admin_vf_tab($key,$config);
// 	}
// }
// add_action( 'admin_init', 'airpress_admin_vf_tab_controller');

/***********************************************/
# TAB: DEFAULT
/***********************************************/
function airpress_admin_vf_tab($key,$config) {

	$option_name = "airpress_vf".$key;
	//$options = get_option( $option_name );

	$defaults = array(
		"name"			=> "New Configuration",
		"connection"	=> null,
		"post_type"		=> null,
		"table"			=> "Your Airtable Table",
		"column"		=> "Your Airtable Column",
		"field"			=> "Your Wordpress Field",
		"single"		=> 0

	);

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
	$section_title = "";
	$section_name = "airpress_vf".$key;
	$option_name = 'airpress_vf'.$key;

	add_settings_section(
		$section_name,
		__( $section_title, 'airpress' ),
		"airpress_admin_vf_render_section",
		$option_name
	);

	################################
	$field_name = "post_type";
	$field_title = "Select Post Type";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vf_render_element_select__posttypes', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "table";
	$field_title = "Airtable Table Name";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vf_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "column";
	$field_title = "Airtable Column";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vf_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "field";
	$field_title = "Wordpress Field (ID or post_name)";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vf_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "single";
	$field_title = "Enable only for single posts (not archive, search, etc)";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vf_render_element_toggle', $option_name, $section_name, array($options,$option_name,$field_name) );

	###############################
	$field_name = "delete";
	$field_title = "Delete Configuration?";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_vf_render_element_delete', $option_name, $section_name, array($options,$option_name,$field_name) );

	register_setting($option_name,$option_name,"airpress_vf_validation");
}

function airpress_vf_validation($config){
	return $config;
}

function airpress_admin_vf_render_section__general() {
	echo '<p>' . __( 'Provides examples of the five basic element types.', 'sandbox' ) . '</p>';
}

function airpress_admin_vf_render_section() {
	echo '<p>' . __( '', 'airpress' ) . '</p>';
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

	$post_types = get_post_types( array( 'public'   => true) );

	echo '<select id="' . $field_name . '" name="' . $option_name . '[' . $field_name . ']">';
	foreach ( $post_types  as $post_type ) {
		$selected = ( $post_type == $options[$field_name] )? "selected" : "";
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
		$selected = ($connection["name"] == $options[$field_name])? "selected" : "";
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