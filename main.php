<?PHP
/*
Plugin Name: On The Fly YouTube Embeds
Plugin URI: http://JoeAnzalone.com/plugins/on-the-fly-youtube-embeds/
Description: Creates a page on your site that will play any YouTube video based on the requested URL without having to create a new page for each individual video.
Version: 1.1.3
Author: Joe Anzalone
Author URI: http://JoeAnzalone.com
License: GPL2
*/

/*
Thanks to Scott for helping me figure out how to create this fake page:
http://scott.sherrillmix.com/blog/blogger/creating-a-better-fake-post-with-a-wordpress-plugin/

Thanks to PressCoders for "Settings API Explained"
http://www.presscoders.com/2010/05/wordpress-settings-api-explained/
The code for the plugin options is based off of http://wordpress.org/extend/plugins/plugin-options-starter-kit/
*/

class on_the_fly_youtube_embeds {

	var $slug;
	var $width;
	var $height;
	var $vid_info;
    
    /**
     * The title for your fake post.
     * @var string
     */
    var $page_title = '';

    /**
     * Allow pings?
     * @var string
     */
    var $ping_status = 'closed';

	function body_class_filter($classes) {
		if ( !empty($this->vid) ) {
			// add 'class-name' to the $classes array
			$classes[] = 'youtube-embed';
		}		
		// return the $classes array
		return $classes;
	}

	function admin_enqueue_scripts_action( $hook ) {
	    if ( 'settings_page_on-the-fly-youtube-embeds/main' != $hook ) {
			return;
	    } else {
	    	wp_enqueue_script( 'otfye-admin', plugins_url('/admin.js', __FILE__) );
	    	wp_enqueue_style( 'otfye-admin', plugins_url('/admin.css', __FILE__) );
	    }
	}

	// Called by the_posts_filter() to load up $this->vid_info with video metadata from the YouTube API
	function get_video_info($vid) {
		$transient_name = "otfye-youtube-api-call_$vid";

		// Check to see if we've queried YouTube for this VID recently
		$transient = get_transient($transient_name);

		if ( $transient ) {
			$json = $transient;
		} else {
			$vid_request_url = "https://gdata.youtube.com/feeds/api/videos/$vid?v=2&alt=jsonc";
			$remote_response = wp_remote_get($vid_request_url);
			$json = $remote_response['body'];
			set_transient( $transient_name, $json, $this->options['api_cache_time'] ); // Cache API response
		}

		$vid_info = json_decode($json);

		if(empty($vid_info->data->id)){
			return FALSE;
		}
		return $vid_info;
	}

	// Replace each variable name in brackets with the value taken from the settings page and YouTube API
	function replace_placeholders($placeholders, $subject, $prefix=NULL) {

		$replaced = $subject;
		
		foreach ($placeholders as $key => $value) {

			if (is_scalar($value)) {
				if (!empty($prefix)) {
					$replaced = str_ireplace("[$prefix/$key]", $value, $replaced);
				} else {
					$replaced = str_ireplace("[$key]", $value, $replaced);
				}

			}

			if (is_array($value)) {
				$replaced = $this->replace_placeholders($value, $replaced, $key);
			}
		}
		return $replaced;
	}

	// Called by create_fake_post() to generate the embed code
	function get_embed_code($vid, $width, $height) {
		$html = NULL;
		$iframe = NULL;
		$vid_info = $this->vid_info->data;
		$placeholders = $this->options + (array) $vid_info;
		
		$html .= '<div class="otfye">';

		$html .= '<div class="embedded-youtube">';
		$html .= $this->replace_placeholders($placeholders, $this->options['embed_code']);
		$html .= '</div>';
		
		// If the user has chosen to, show the video description below the embedded video
		if ( !empty($this->options['show_description']) && !empty($this->vid_info->data) ) {
			$video_description = make_clickable( $this->vid_description );
			$html .= '<div class="video-description">' . $video_description . '</div>';
		}

		$html .= '</div>';
		return $html;
	}

   	public static $default_options = array(
			"show_description" => 1,
			"slug" => "video",
			"player" => array('width' => 850, 'height' => 480, 'autoplay' => 0),
			"slug" => "video",
			"page_template" => "default",
			"youtube_uploader_whitelist" => "",
			"embed_code" => '<iframe src="http://www.youtube.com/embed/[id]?rel=0&autoplay=[player/autoplay]" frameborder="0" width="[player/width]" height="[player/height]"></iframe>',
			"api_cache_time" => 43200, // 12 hours
			"chk_default_options_db" => 0,
		);
       
    // Class constructor
    function __construct() {
    	$this->plugin_meta['name'] = 'On The Fly YouTube Embeds';
		// ------------------------------------------------------------------------
		// REGISTER HOOKS & CALLBACK FUNCTIONS:
		// ------------------------------------------------------------------------
		// HOOKS TO SETUP DEFAULT PLUGIN OPTIONS, HANDLE CLEAN-UP OF OPTIONS WHEN
		// PLUGIN IS DEACTIVATED AND DELETED, INITIALISE PLUGIN, ADD OPTIONS PAGE.
		// ------------------------------------------------------------------------
		add_action( 'admin_init', array(&$this, 'requires_wordpress_version' ) );
		register_activation_hook(dirname(__FILE__) . '/main.php', 'otfye_add_defaults');
		register_uninstall_hook(dirname(__FILE__) . '/main.php', 'otfye_delete_plugin_options');

		add_action('admin_init', array(&$this, 'admin_init_action') );
		add_action('admin_menu', array(&$this, 'admin_menu_action') );
		add_action('wp_before_admin_bar_render', array(&$this, 'wp_before_admin_bar_render_action') );
		add_action( 'admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts_action'), 10 );

		add_filter( 'plugin_action_links', array(&$this, 'plugin_action_links_filter'), 10, 2) ;
		add_filter( 'edit_post_link', array(&$this, 'edit_post_link_filter')) ;
		add_filter( 'the_comments', array(&$this, 'the_comments_filter')) ;
		add_filter('body_class', array($this, 'body_class_filter'));

    	$this->options = get_option('otfye_options');
		$this->options = $this->set_missing_default_options($this->options);

    	if ( is_array($this->options) ) {
			$this->slug = $this->options['slug'];
		    $this->width = $this->options['player']['width'];
		    $this->height = $this->options['player']['height'];
    	   
		}	    
        
        /**
         * We'll wait until WordPress has looked for posts, and then
         * check to see if the requested URL matches our target.
         */
        add_filter('the_posts', array(&$this,'the_posts_filter'));
        add_action('template_redirect',array(&$this,'template_redirect_action'));

		// I wonder if there's some bug with get_post_metadata since I just keep getting "NULL"
  		// add_filter('get_post_metadata',array(&$this,'template_filter'), -100);
        
    } // END Class constructor

    // Remove the comments from the page since it's fake and uneditable
    function the_comments_filter($html) {
    	if (!empty($this->vid)) {
    		return NULL;
    	} else {
    		return $html;
    	}
    }

    // Remove the "Edit" link from the page since it's fake and uneditable
    function edit_post_link_filter($html) {
    	if (!empty($this->vid)) {
    		return NULL;
    	} else {
    		return $html;
    	}
    }

    // Remove the "Edit Page" link from the admin bar since it's fake and uneditable
    function wp_before_admin_bar_render_action() {
    	if (!empty($this->vid)) {
	    	global $wp_admin_bar;
			$wp_admin_bar->remove_menu('edit');
    	}
    }


	/**
	* Hook into the template_redirect action to specify which page template should be used for the fake page
	* http://stackoverflow.com/a/4975004/1027770
	*/
	function template_redirect_action() {
		if ( !empty($this->vid)) {
		    global $wp;

		    // $page_template = 'foo.php';

		    if (!empty( $this->options['page_template'] )) {
		    	$page_template = $this->options['page_template'];
			}

		    if (file_exists(TEMPLATEPATH . '/' . $page_template)) {
		        $return_template = TEMPLATEPATH . '/' . $page_template;
		        $this->do_theme_redirect($return_template);
		    }
	    }
	}

	// Called by template_redirect_action() to include the chosen page template based on the user's choice
	function do_theme_redirect($page_template) {
	    global $post, $wp_query;
	    if (have_posts()) {
	        include($page_template);
	        die();
	    } else {
	        $wp_query->is_404 = true;
	    }
	}

    /**
     * Called by the 'the_posts_filter' action
     */
    function create_fake_post() {
        /**
         * What we are going to do here, is create a fake post.  A post
         * that doesn't actually exist. We're gonna fill it up with
         * whatever values you want.  The content of the post will be
         * the output from your plugin.
         */  
       
        /**
         * Create a fake post.
         */
        $post = new stdClass;
       
        /**
         * The author ID for the post.  Usually 1 is the sys admin.  Your
         * plugin can find out the real author ID without any trouble.
         */
        $post->post_author = 1;
       
        /**
         * The safe name for the post.  This is the post slug.
         */
        $post->post_name = $this->slug . '/' . $this->vid;
       
        /**
         * Not sure if this is even important.  But gonna fill it up anyway.
         */
        $post->guid = get_bloginfo('wpurl') . '/' . $this->slug . '/' . $this->vid;
       
       
        /**
         * The title of the page.
         */
        $post->post_title = $this->page_title;
       
        /**
         * This is the content of the post.  This is where the output of
         * your plugin should go.  Just store the output from all your
         * plugin function calls, and put the output into this var.
         */

		$post->post_content = $this->get_embed_code($this->vid, $this->width, $this->height);

        /**
         * Fake post ID to prevent WP from trying to show comments for
         * a post that doesn't really exist.
         */
        $post->ID = 0;
       
        /**
         * Static means a page, not a post.
         */
        $post->post_status = 'static';
       
        /**
         * Turning off comments for the post.
         */
        $post->comment_status = 'closed';
       
        /**
         * Let people ping the post?  Probably doesn't matter since
         * comments are turned off, so not sure if WP would even
         * show the pings.
         */
        $post->ping_status = $this->ping_status;
       
        $post->comment_count = 0;
       
        $post->post_date = current_time('mysql');
        $post->post_date_gmt = current_time('mysql', 1);
 
        $post->post_type = 'page';
        $post->post_parent = 0;
        $post->post_mime_type = '';

        return($post);
    }
 
	function the_posts_filter($posts) {
    	
        /**
         * Check if the requested page matches our target
         */
        global $wp;
        global $wp_query;
		$request = $wp->request; // video/9bZkp7q19f0

		preg_match("#{$this->slug}/([a-zA-Z0-9_-]+)#", $request, $preg_matches);
		if (!empty($preg_matches[1])) {
			$this->vid = $preg_matches[1];
		}

		if (!empty($_GET[$this->slug])) {
			$this->vid = $_GET[$this->slug];
        }

		if ( !empty($this->vid) ) {

			$this->vid_info = $this->get_video_info( $this->vid );
			if($this->vid_info) {
				$this->vid_description = esc_html( $this->vid_info->data->description );

				if (!empty($this->vid_info->data->title)) {
					$this->page_title = $this->vid_info->data->title;
				}

				$wp->page ='';
				$wp->pagename = 'video';
				$wp->query_string = 'pagename=video';
				$wp->matched_query = 'pagename=video&page=';

	            //Add the fake post
	            $posts = NULL;
	            $posts[] = $this->create_fake_post();
	       
	            /**
	             * Trick wp_query into thinking this is a page (necessary for wp_title() at least)
	             * Not sure if it's cheating or not to modify global variables in a filter
	             * but it appears to work and the codex doesn't directly say not to.
	             */
	            $wp_query->is_page = true;
	            //Not sure if this one is necessary but might as well set it like a true page
	            
	            // $wp_query->is_singular = true;
	            $wp_query->is_single = false;

	            $wp_query->is_home = false;
	            $wp_query->is_archive = false;
	            $wp_query->is_category = false;
	            //Longer permalink structures may not match the fake post slug and cause a 404 error so we catch the error here
	            unset($wp_query->query["error"]);
	            $wp_query->query_vars["error"] = '';
	            $wp_query->is_404 = false;
	            
	            $wp_query->is_attachment = false;

		        $youtube_uploader_whitelist_arr = array_filter( explode(',', $this->options['youtube_uploader_whitelist']) );

		        foreach ($youtube_uploader_whitelist_arr as $k => $user) {
		        	$youtube_uploader_whitelist_arr[$k] = trim($user);
		        }

		        if (!in_array($this->vid_info->data->uploader, $youtube_uploader_whitelist_arr) && !empty($youtube_uploader_whitelist_arr)) {
					unset($this->vid);
		        	$posts = array();
		        }
			} else {
				unset($this->vid);
				$posts = array();
			}
        }
        return $posts;
	}


	/* Settings Page Stuff */
	/**
	* Why can't we just include a settings.php file or extend this class in a separate file?
	* http://stackoverflow.com/questions/1957732/can-i-include-code-into-a-php-class
	*/

	// ------------------------------------------------------------------------
	// REQUIRE MINIMUM VERSION OF WORDPRESS:                                               
	// ------------------------------------------------------------------------
	// THIS IS USEFUL IF YOU REQUIRE A MINIMUM VERSION OF WORDPRESS TO RUN YOUR
	// PLUGIN. IN THIS PLUGIN THE WP_EDITOR() FUNCTION REQUIRES WORDPRESS 3.3 
	// OR ABOVE. ANYTHING LESS SHOWS A WARNING AND THE PLUGIN IS DEACTIVATED.                    
	// ------------------------------------------------------------------------
	function requires_wordpress_version() {
		global $wp_version;
		$plugin = plugin_basename( __FILE__ );
		$plugin_data = get_plugin_data( __FILE__, false );

		if ( version_compare($wp_version, "3.3", "<" ) ) {
			if ( is_plugin_active($plugin) ) {
				deactivate_plugins( $plugin );
				wp_die( "<p>'".$plugin_data['Name']."' requires WordPress 3.3 or higher, and has been deactivated! Please upgrade WordPress and try again.</p><p>Back to <a href='".admin_url()."'>WordPress admin</a>.</p>" );
			}
		}
	}

	// Init plugin options to white list our options
	function admin_init_action() {
		if (isset($_GET['delete_options'])) {
			delete_option('otfye_options');
		}
		register_setting( 'otfye_plugin_options', 'otfye_options', array(&$this, 'validate_options') );
	}

	// Add menu page
	function admin_menu_action() {
		add_options_page($this->plugin_meta['name'], 'OTF YouTube Embeds', 'manage_options', __FILE__, array(&$this, 'render_form'));
	}

	function set_missing_default_options($options, $default_value=NULL) {
		foreach (self::$default_options as $key => $value) {
			if ( !isset($options[$key]) ) {

				if ( isset($default_value) ) {
					$options[$key] = $default_value;
				} else {
					$options[$key] = $value;
				}

			}
		}
		return $options;
	}

// ------------------------------------------------------------------------------
// CALLBACK FUNCTION SPECIFIED IN: add_options_page()
// ------------------------------------------------------------------------------
// THIS FUNCTION IS SPECIFIED IN add_options_page() AS THE CALLBACK FUNCTION THAT
// ACTUALLY RENDER THE PLUGIN OPTIONS FORM AS A SUB-MENU UNDER THE EXISTING
// SETTINGS ADMIN MENU.
// ------------------------------------------------------------------------------

	// Render the Plugin options form
	function render_form() {
		$options = $this->options;
		?>
		<div class="wrap">
			
			<!-- Display Plugin Icon, Header, and Description -->
			<div class="icon32" id="icon-options-general"><br></div>
			<h2><?PHP echo $this->plugin_meta['name']; ?></h2>
			<!-- <p>Change settings below</p> -->

			<!-- Beginning of the Plugin Options Form -->
			<form method="post" action="options.php">
			<?PHP settings_fields('otfye_plugin_options'); ?>
				<!-- Table Structure Containing Form Controls -->
				<!-- Each Plugin Option Defined on a New Table Row -->
				<table class="form-table">

					<!-- Video page slug (Textbox Control) -->
					<tr class="page-slug">
						<th scope="row">Video page slug</th>
						<td>
							<?PHP
													// An array of example video IDs
							$example_vids = array(  // Yes, this is absolutley *crucial* to the operation of this plugin!
								'jOyFDvWf83w',
								'XvutrQoIAEM',
								'4PTsEjSEnhA',
								'_6-KspZegsE',
								'twqM56f_cVo',
								'bjWPyDMk8k8',
								'9bZkp7q19f0',
								'pHCdS7O248g',
								'AcusYrqBvYQ',
								'gqmo9-jVOcM',
								'SCsKRbChILA',
								'KQ6zr6kCPj8',
								'UtQbENQ4Zk0',
								'Yhx0vEecE5o',
								'5FQ08S6roF8',
							);

							// Randomly choose an example video from the array
							$example_vid = $example_vids[array_rand($example_vids)];
							if ( get_option('permalink_structure') ) {
								// Example URL for sites with pretty permalinks ENabled
								$example_url = home_url() . '/<strong class="slug">' . $options['slug'] . "</strong>/$example_vid/";
								$video_page_url = home_url() . '/' . $options['slug'] . '/';
							} else {
								// Example URL for sites with pretty permalinks DISabled
								$example_url = home_url() . '/?<strong class="slug">' . $options['slug'] . "</strong>=$example_vid";
								$video_page_url = home_url() . '/?' . $options['slug'] . '=';
							}

							?>
							<input placeholder="<?PHP echo self::$default_options['slug']; ?>" type="text" size="57" name="otfye_options[slug]" value="<?php echo $options['slug']; ?>" />
							<p class="description">The URL slug for the page where all YouTube videos will be displayed</p>
							<p class="description example-url">Example: <code><?PHP echo $example_url; ?></code></p>
						</td>
					</tr>

					<!-- Video Player Dimensions (Two Textbox Controls) -->
					<tr>
						<th scope="row">Video Player Dimensions</th>
						<td>
							<label>Width<input type="number" size="5" name="otfye_options[player][width]" value="<?php echo $options['player']['width']; ?>" /></label>
							<label>Height<input type="number" size="5" name="otfye_options[player][height]" value="<?php echo $options['player']['height']; ?>" /></label>
						</td>
					</tr>

					<!-- Show video description (Checkbox Button) -->
					<tr valign="top">
						<th scope="row">Show video description</th>
						<td>
							<label><input name="otfye_options[show_description]" type="checkbox" value="1" <?php if (isset($options['show_description'])) { checked('1', $options['show_description']); } ?> /> Show video description</label>
							<p class="description">If checked, the video description will be taken from YouTube and inserted below the embedded player on your site</p>
						</td>
					</tr>

					<!-- Autoplay video (Checkbox Button) -->
					<tr valign="top">
						<th scope="row">Autoplay video</th>
						<td>
							<!-- First checkbox button -->
							<label><input name="otfye_options[player][autoplay]" type="checkbox" value="1" <?php if (isset($options['player']['autoplay'])) { checked('1', $options['player']['autoplay']); } ?> /> Autoplay video</label>
							<p class="description">If checked, videos will automatically play as soon as the page is loaded</p>
						</td>
					</tr>

					<!-- Page Template (Select Drop-Down Control) -->
					<tr>
						<th scope="row">Page Template</th>
						<td>
							<select name='otfye_options[page_template]'>
								<?PHP
								// Get current theme's page templates, plus add "default" option
								$page_templates = array('Default Template' => 'default' ) + get_page_templates();
								 foreach ( $page_templates as $template_name => $template_file) {
								 	// Echo out an <option> element for each template option with the filename as the value, and name as the label
									echo '<option value="' . $template_file. '" '. selected($template_file, $options['page_template']) .'>' . $template_name . '</option>';

								} ?>
							</select>
							<p class="description">Choose a page template from the current theme to use on the video page</p>
						</td>
					</tr>

					<!-- YouTube uploader whitelist (Textbox Control) -->
					<tr>
						<th scope="row">YouTube uploader whitelist</th>
						<td>
							<input placeholder="WordPressNYC, StevenAnzalone, nathanjbarnatt, realcsstricks" type="text" size="57" name="otfye_options[youtube_uploader_whitelist]" value="<?php echo $options['youtube_uploader_whitelist']; ?>" />
							<p class="description">Only allow embedding of videos from these YouTube users</p>
							<p class="description">Separate multiple user names with commas or leave blank to allow videos from any user</p>
						</td>
					</tr>

					<!-- Customize Embed Code (Textarea Control) -->
					<tr class="embed-code">
						<th scope="row">Customize Embed Code</th>
						<td>
							<textarea rows="7" cols="50" size="57" name="otfye_options[embed_code]"><?php echo $options['embed_code']; ?></textarea>
							<p class="description">Customize the embed code from YouTube (use <code>[id]</code> as a placeholder for the video's ID)</p>
						</td>
					</tr>

					<tr>
						<th scope="row">YouTube API Cache Timeout</th>
						<td>
						<?PHP
						$default_api_cache_time_seconds = self::$default_options['api_cache_time'];
						$default_api_cache_time_hours = $default_api_cache_time_seconds / 60 / 60;
						?>
							<label>Timeout (in seconds)<input placeholder="<?PHP echo self::$default_options['api_cache_time']; ?>" type="number" size="5" name="otfye_options[api_cache_time]" value="<?php echo $options['api_cache_time']; ?>" /></label>
							<p class="description">Video info from YouTube is temporarily saved to increase performance. How long should it be saved for?</p>
							<p class="description">Default is <?PHP echo $default_api_cache_time_seconds; ?> seconds (<?PHP echo $default_api_cache_time_hours; ?> hours)</p>
						</td>
					</tr>

					<!-- Database Options (Checkbox Button) -->
					<tr valign="top">
						<th scope="row">Database Options</th>
						<td>
							<label><input name="otfye_options[chk_default_options_db]" type="checkbox" value="1" <?php if (isset($options['chk_default_options_db'])) { checked('1', $options['chk_default_options_db']); } ?> /> Restore defaults upon plugin deactivation/reactivation</label>
							<p class="description">Only check this if you want to reset plugin settings upon plugin reactivation</p>
							
						</td>
					</tr>

				</table>
				<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
				</p>
			</form>
			<h3>Bookmarklet</h3>
			<p>Drag this handy bookmarklet onto your browser's bookmarks bar and you'll be able to instantly view any YouTube video on your own site with just a single click!</p>
			<p>Simply click the bookmarklet while on any YouTube video page and you'll be immdiately redirected to your site's video page for that video.</p>
			<p class="bookmarklet"><a onclick="return false;" href="javascript:window.location = '<?PHP echo $video_page_url; ?>' + yt.config_.VIDEO_ID;"><span>View on <?PHP bloginfo( 'name' ); ?></span></a></p>
		</div>
		<?php	
	}

	// Sanitize and validate input. Accepts an array, return a sanitized array.
	function validate_options($input) {
	
		$input = $this->set_missing_default_options( $input, 0 );

		/*var_dump( get_defined_vars() );
		die();*/

		$input['slug'] = sanitize_title($input['slug'], self::$default_options['slug']);
		
		if (empty($input['embed_code'])) {
			$input['embed_code'] = self::$default_options['embed_code'];
		}

		if ( empty($input['api_cache_time']) ) {
					$input['api_cache_time'] = self::$default_options['api_cache_time'];
		}

		return $input;
	}

	// Display a Settings link on the main Plugins page
	function plugin_action_links_filter( $links, $file ) {

		if ( plugin_basename( __FILE__ ) == $file ) {
			$otfye_links = '<a href="'.get_admin_url().'options-general.php?page=on-the-fly-youtube-embeds/main.php">'.__('Settings').'</a>';
			// make the 'Settings' link appear first
			array_unshift( $links, $otfye_links );
		}

		return $links;
	}
	/* END Settings Page Stuff */


} // End on_the_fly_youtube_embeds class definition
 
// Create an instance of our class and run its __construct() method
$on_the_fly_youtube_embeds = new on_the_fly_youtube_embeds;

// Delete options table entries ONLY when plugin deactivated AND deleted
function otfye_delete_plugin_options() {
	delete_option('otfye_options');
}

// ------------------------------------------------------------------------------
// CALLBACK FUNCTION FOR: register_activation_hook()
// ------------------------------------------------------------------------------
// THIS FUNCTION RUNS WHEN THE PLUGIN IS ACTIVATED. IF THERE ARE NO THEME OPTIONS
// CURRENTLY SET, OR THE USER HAS SELECTED THE CHECKBOX TO RESET OPTIONS TO THEIR
// DEFAULTS THEN THE OPTIONS ARE SET/RESET.
//
// OTHERWISE, THE PLUGIN OPTIONS REMAIN UNCHANGED.
// ------------------------------------------------------------------------------

// Define default option settings
 function otfye_add_defaults() {
 	$options = get_option('otfye_options');
 	if(isset( $options['chk_default_options_db'] )){
	    if ( '1' == $options['chk_default_options_db'] ) {
			delete_option('otfye_options');
		}
	}
}