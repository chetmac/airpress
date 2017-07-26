<?php

class AirpressVirtualPosts {

	private $config = false;
	private $matches = array();
	public $AirpressCollection = false;

	function init(){
		global $wp_rewrite;

		add_action( 'parse_request',		array($this,'check_for_actual_page') );
		add_action( 'template_redirect',	array($this,'check_for_virtual_page') );//, -10);

		add_filter( 'query_vars',			array($this,'add_query_vars' ));
		add_action( 'the_post',				array($this,'last_chance_for_data') );//, -10);

		add_filter( 'page_link',			array($this,'get_virtual_page_permalink'), 10, 3);
		// hmmm... posts and pages are more different than previously assumed.
		// add_filter( 'post_link',			array($this,'get_permalink'), 10, 3); 

		// If Yoast SEO is NOT installed, we need to modify the
		// build in wordpress canonical URL. Unfortunately there's
		// no hook or filter for this yet so I'm replicating it
		// in this class and running it through my own "filter"
		$plugins = get_option("active_plugins");
		if (!in_array("wordpress-seo/wp-seo.php", $plugins)){
  			remove_action( 'wp_head', 'rel_canonical' );
  			add_action( 'wp_head', array($this,'rel_canonical') );
		} else {
			add_filter( 'wpseo_canonical',		array($this,'wpseo_canonical') );
		}	
	}

	public function add_query_vars( $qvars ) {
		$qvars[] = 'default_vp';
		return $qvars;
	}
   
	public function get_virtual_page_permalink( $link, $id, $sample ){
		global $wp;

		$configs = get_airpress_configs("airpress_vp");

		foreach( $configs as $config ){
			if ( $id == $config["template"] ){
				if ( ! empty($wp->request) ) {
					return home_url($wp->request);
				}
				break;
			}
		}

		return $link;
	}

	/*
	If one of our redirect rules matched, we want to check to see if it WOULD HAVE matched
	an actual wordpress post/page
	*/
	public function check_for_actual_page( $request, $simulation=false ) {

		if (isset($request->matched_rule)){

			$configs = get_airpress_configs("airpress_vp");

			if ( count($configs) == 0){
				return $request;
			}

			// figure out which config to use
			foreach($configs as $config){
				if ($request->matched_rule == $config["pattern"]){
					airpress_debug(0,$config["name"]." VirtualPost	".$config["pattern"]." matched");
					$this->config = $config;
					break; // we got what we came for, let's jet
				} else if (
					isset($config["default"]) && 
					isset($request->query_vars["default_vp"]) && 
					$request->query_vars["default_vp"] == $config["default"]
				){
					airpress_debug(0,$config["name"]." DEFAULT VirtualPost	".$config["pattern"]);
					$this->config = $config;
					$request->matched_rule = $config["pattern"]; 
					$request->request = $config["default"];
					break; // we got what we came for, let's jet
				}
			}
		}

		// I just realized/discovered that EVERY request will have a request->matched_rule
		// as long as pretty permalinks are enabled. sooo... must check that a config was
		// matched, or else get outta here
		if ( ! $this->config ){
			return;
		}

		// populate $matches var to save parts of the request string
		preg_match("`" . $request->matched_rule . "`", $request->request, $this->matches);

		// If request is empty, then the home page was requested
		// and we're not going to allow home page to be virtual at this time...
		// mostly because, why?
		if ( ! empty($request->request) ){

			$requested_post = get_page_by_path( $request->request ); // See if the original request WOULD have returned a page or 404

        	if ( $this->config ){
        		$this->config["requested_post"] = $requested_post;
        	}

			// if the requested post was REAL
	        if( $requested_post ){

	        	// This undoes the redirect because it turns out there IS an ACTUAL page
	        	// that WOULD have been served. And since we want REAL pages to override
	        	// Virtual posts, let's undo what we've done.
	            $request->query_vars['page_id'] = $requested_post->ID;
	        }

	    }

	    // an active config means that we should ask Airtable for some data
		if ($this->config){
			
			// This gets the API connection config
			$connection = get_airpress_config("airpress_cx",$this->config["connection"]);

			// We've matched a config. Let's check and see if a cooresponding record exists
			$query = new AirpressQuery();

			$query->setConfig($connection);

			$query->table($this->config["table"]);
			
			// Process formula to inject any vars
			$formula = $this->config["formula"];
			$i = 1;
			while (isset($this->matches[$i])){
				// use matches to replace variables in string like this.
				// AND({Field Name} = '$1',{Field 2} = '$2')
				$formula = str_replace("$".$i,$this->matches[$i],$formula);
				$i++;
			}

			$query->filterByFormula($formula);

			// Handle sort parameter
			if (isset($this->config["sort"]) && !empty($this->config["sort"])){
				$query->sort($this->config["sort"]);
			}

			if ( $simulation ){
				$query->fields(array()); // specify no fields so this is as quick as possible
			} else {
				$query = apply_filters("airpress_virtualpost_query",$query,$request,$this->config);
			}
			
			
			$result = new AirpressCollection($query);

			if (count($result) > 0){
				$this->AirpressCollection = $result;
			}

		}

	    return $request;
	}

	function template_reset( $template ) {
		global $post;
		
		// If this is virtual
		if ( isset($post->real_post_name) ){

			$new_template = locate_template( array("page-{$post->real_post_name}.php") );

		}

		if ( ! empty($new_template) ){
			$template = $new_template;
		}

		return $template;
	}

	public function last_chance_for_data($post){
		global $wp,$wp_query;

		if ( ! $this->config && function_exists("is_cornerstone") && is_cornerstone() == "render"){

			$configs = get_airpress_configs("airpress_vp");

			if ( count($configs) == 0){
				return;
			}

			$request = new StdClass();

			// figure out which config to use
			foreach($configs as $config){

				if ( $post->ID == $config["template"] ){
					$request->request = $config["default"];
					$request->matched_rule = $config["pattern"];
					break;
				}

			}

			$request = apply_filters("airpress_virtualpost_last_chance",$request);

			$this->check_for_actual_page( $request );
			$this->setupPostObject();
		}
	}


	public function check_for_virtual_page(){
		global $wp, $wp_query;
		
		// if request matched one of our VirtualPost redirect rules
		if ($this->config){

			// if request doesn't match a real post
			if ($this->config["requested_post"] == false){

				// if this virtual post returned data from Airtable, set it up
				if ($this->AirpressCollection){

					// A virtual page requires an actual page to use as a template
					// Consider if this template is named "My Template", it will have
					// a post_name of "my-template" as automatically created by wordpress.
					// When loading this page, the "locate_template" function would include a 
					// check for the file "page-my-template.php" in the stylesheet directory.
					// However, Virtual posts will be assigned a new post_name based on the airtable
					// data with which they're populated, so we need to save the "real_post_name" and
					// then add a filter to ensure that wordpress still checks for a file called
					// page-my-template.php and not page-my-virtual-post-name.php ? Clear as mud? good.
					$wp_query->post->real_post_name = $wp_query->post->post_name;
					$wp_query->post->real_post_title = $wp_query->post->post_title;
					add_filter( 'page_template', 		array($this,'template_reset'));

					$slug_field = $this->config["field"];
					if (isset( $this->AirpressCollection[0][$slug_field] )){
						airpress_debug("$slug_field exists so post_name is ".$this->AirpressCollection[0][$slug_field].".");
						$wp_query->post->guid = $wp_query->post->post_name = $this->AirpressCollection[0][$slug_field];
					} else if ( ! empty($slug_field) ) {

						$post_name = $slug_field;

						// replace squiggly brackets with Airtable data
						if ( preg_match_all("`{([^}]+)}`",$post_name,$field_matches) ){
							foreach($field_matches[1] as $field_name){
								$field_value = ( isset($this->AirpressCollection[0][$field_name]) )? $this->AirpressCollection[0][$field_name] : "";
								$post_name = str_replace("{".$field_name."}",$field_value,$post_name);
							}
						}

						// replace $1 vars with matches
						$i = 1;
						while (isset($this->matches[$i])){
							// use matches to replace variables in string like this.
							// $1-{Field Name}
							$post_name = str_replace("$".$i,$this->matches[$i],$post_name);
							$i++;
						}

						$wp_query->post->post_name = $post_name;
						$wp_query->post->guid = $post_name;
						airpress_debug("fancy post_name provided: ".$post_name);

					}

					$title_field = (isset($this->config["field2"]))? $this->config["field2"] : false;
					if ( $title_field ){
						if ( isset( $this->AirpressCollection[0][$title_field] ) ){
							airpress_debug("$title_field exists so post_title is ".$this->AirpressCollection[0][$title_field].".");
							$wp_query->post->post_title = $this->AirpressCollection[0][$title_field];
						} else {

							$post_title = $title_field;

							// replace $1 vars with matches
							$i = 1;
							while (isset($this->matches[$i])){
								// use matches to replace variables in string like this.
								// $1-{Field Name}
								$post_title = str_replace("$".$i,$this->matches[$i],$post_title);
								$i++;
							}

							// replace squiggly brackets with Airtable data
							if ( preg_match_all("`{([^}]+)}`",$post_title,$field_matches) ){
								foreach($field_matches[1] as $field_name){
									$field_value = ( isset($this->AirpressCollection[0][$field_name]) )? $this->AirpressCollection[0][$field_name] : "";
									$post_title = str_replace("{".$field_name."}",$field_value,$post_title);
								}
							}

							$wp_query->post->post_title = $post_title;
							airpress_debug("fancy post_title provided: ".$post_title);
						}

					}
				} else {
					airpress_debug("no data");
					$this->force_404();
				}

			} else {

				// Yes, the request was for a real post, however it was for the template post so 404
				if ( $this->config["requested_post"]->ID == $this->config["template"] && !is_user_logged_in()){
					$this->force_404();
				}

			}

			$this->setupPostObject();
		}
	}

	private function setupPostObject(){
		global $post;

		if ( ! is_airpress_empty($this->AirpressCollection) ){

			$post->AirpressCollection = $this->AirpressCollection;

			do_action("airpress_virtualpost_setup",$this->config);

		}

	}

	function force_404(){
		// 1. Ensure `is_*` functions work
		global $wp_query;
		$wp_query->set_404();
		 
		// 2. Fix HTML title
		add_action( 'wp_title', function () {
				return '404: Not Found';
			}, 9999 );
	 
	    // 3. Throw 404
	    status_header( 404 );
	    nocache_headers();
	 
	    // 4. Show 404 template
	    require get_404_template();
	 
	    // 5. Stop execution
	    exit;
	}

	function wpseo_canonical($canonical_url){
		global $wp;

		if ($this->config){
			$slug_field = $this->config["field"];
			if (isset($this->AirpressCollection[0][$slug_field])){
				$canonical_url = $_SERVER["HTTP_HOST"].dirname($_SERVER["REQUEST_URI"])."/".$this->AirpressCollection[0][$slug_field]."/";
			}
		}
		
		return $canonical_url;
	}

	function rel_canonical() {
		if ( ! is_singular() ) {
			return;
		}

		if ( ! $id = get_queried_object_id() ) {
			return;
		}

		$url = get_permalink( $id );

		$page = get_query_var( 'page' );
		if ( $page >= 2 ) {
			if ( '' == get_option( 'permalink_structure' ) ) {
				$url = add_query_arg( 'page', $page, $url );
			} else {
				$url = trailingslashit( $url ) . user_trailingslashit( $page, 'single_paged' );
			}
		}

		$cpage = get_query_var( 'cpage' );
		if ( $cpage ) {
			$url = get_comments_pagenum_link( $cpage );
		}

		$url = $this->wpseo_canonical($url);

		echo '<link rel="canonical" href="' . esc_url( $url ) . "\" />\n";
	}


}

?>