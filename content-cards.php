<?php
/*
Plugin Name: Content Cards
Description: Embed any link from the web easily as a beautiful Content Card
Version: 0.9.5
Author: Arūnas Liuiza
Author URI: http://arunas.co
License: GPL2
*/

add_action( 'plugins_loaded', array( 'Content_Cards', 'init' ) );
register_uninstall_hook( __FILE__, array( 'Content_Cards', 'uninstall' ) );
register_deactivation_hook( __FILE__, array( 'Content_Cards', 'deactivate' ) );
/**
 * Main plugin class
 *
 * Class Content_Cards
 */
class Content_Cards {
	public static $options = array(
		'patterns' => "wptavern.com\r\nwordpress.org",
		'skin' => 'default',
		'target' => false,
		'update_interval' => DAY_IN_SECONDS,
		'cleanup_interval' => DAY_IN_SECONDS,
		'default_image' => '',
		'cache_images' => false,
		'word_limit' => 55,
		'enable_admin_page' => true
	);
	public static $settings = false;
	private static $stylesheet = '';
	public static $plugin_path;
	public static $temp_data = array();

	public static function init() {
		self::$plugin_path = plugin_dir_path( __FILE__ );

		// tinyOptions v 0.4.0
		self::$options = wp_parse_args( get_option( 'content-cards_options' ), self::$options );
		self::$options = apply_filters('content_cards_options', self::$options );
		add_action( 'plugins_loaded', array( 'Content_Cards', 'init_options' ), 9999 - 0040 );

		if ( isset( self::$options['theme'] ) ) {
			self::$options['skin'] = self::$options['theme'];
			unset( self::$options['theme'] );
		}
		self::$stylesheet = self::get_stylesheet();
		add_action( 'wp_enqueue_scripts', 		array( 'Content_Cards', 'styles' ) );
		add_action( 'amp_post_template_css',	array( 'Content_Cards', 'amp_styles') );
		add_action( 'admin_enqueue_scripts',array( 'Content_Cards', 'admin_scripts' ) );
		add_action( 'admin_init', 			array( 'Content_Cards', 'admin_init' ) );

		// if(self::$options['enable_admin_page']) {
		// 	add_action( 'admin_menu', 			array( 'Content_Cards', 'admin_menu' ) );
		// }

		add_action( 'content_cards_update', array( 'Content_Cards', 'update_data' ), 10, 3 );
		add_action( 'content_cards_retry',  array( 'Content_Cards', 'retry_data' ), 10, 4 );

		add_filter( 'ajax_query_attachments_args',  array( 'Content_Cards', 'filter_cached_images' ), 10, 1 );
		add_action( 'pre_get_posts',  				array( 'Content_Cards', 'filter_cached_images_query' ), 10, 1 );
		add_filter( 'wp_count_attachments',  		array( 'Content_Cards', 'filter_cached_images_count' ), 10, 2 );
		add_filter( 'query',				  		array( 'Content_Cards', 'filter_cached_images_orphans' ), 10, 1 );

		if ( false === wp_next_scheduled( 'content_cards_schedule_cleanups' ) ) {
			wp_schedule_event( time() , 'daily', 'content_cards_schedule_cleanups' );
		}
		add_action( 'content_cards_schedule_cleanups', array( 'Content_Cards', 'schedule_cleanups' ) );
		add_action( 'content_cards_link_cleanup', array( 'Content_Cards', 'link_cleanup' ), 10, 1 );
		add_action( 'content_cards_image_cleanup', array( 'Content_Cards', 'image_cleanup' ), 10, 1 );


		add_shortcode( 'contentcard', 		array( 'Content_Cards', 'shortcode' ) );
		add_shortcode( 'opengraph', 		array( 'Content_Cards', 'shortcode' ) );
		add_shortcode( 'contentcards', 		array( 'Content_Cards', 'shortcode' ) );
		$patterns = self::$options['patterns'];
		$patterns = explode( "\r\n", $patterns );
		if ( is_array( $patterns ) ) {
			foreach ( $patterns as $id => $domain) {
				$domain = str_replace( '.', '\.', $domain );
				$regex = "#https?://(www\.)?{$domain}/?(.*?)#i";
				wp_embed_register_handler( $id, $regex, array( 'Content_Cards', 'oembed' ) );
			}
		}
	    add_filter( "mce_external_plugins", array( 'Content_Cards', 'editor_button_js' ) );
	    add_filter( 'mce_buttons', 			array( 'Content_Cards', 'editor_button' ) );

	    // shortcode preview
		add_action( 'admin_init', 						array( 'Content_Cards', 'init_preview' ), 20 );
		add_action( 'wp_ajax_content_cards_shortcode', 	array( 'Content_Cards', 'ajax_shortcode' ), 20 );
	}

	public static function uninstall() {
		global $wpdb;
		self::_delete_cached();
		$q = "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` LIKE 'content_cards_%'";
		$wpdb->query( $q );
		delete_option( 'content-cards_options' );
		wp_cache_flush();
	}

	public static function deactivate() {
		self::_delete_cached();
	}

	/**
	 * Enqueues TinyMCE button JS
	 *
	 * @param $plugin_array
	 * @return mixed
	 */
	public static function editor_button_js( $plugin_array ) {
    	$plugin_array['contentcards'] = plugins_url( 'content-cards-button.js', __FILE__ );
	    return $plugin_array;
	}

	/**
	 * Adds the CC button to TinyMCE
	 *
	 * @param $buttons
	 * @return mixed
	 */
	public static function editor_button( $buttons ) {
	    array_push( $buttons, 'contentcards_shortcode' );
	    return $buttons;
	}

	/**
	 * Generic template helper that allows overriding via
	 * theme.
	 *
	 * @param $url
	 * @param string $extension
	 * @param string $type
	 * @param string $theme
	 * @param string $method
	 * @return mixed|string|void
	 */
	private static function _get_file( $url, $extension='php', $type = 'website', $theme ="default", $method='dir' ) {
		$plugin_path = plugin_dir_path( __FILE__ );
		$plugin_uri = 'dir' === $method ? $plugin_path : plugins_url( '/', __FILE__ );
		$theme_dir = get_template_directory() . '/';
		$theme_uri = 'dir' === $method ? $theme_dir : ( get_template_directory_uri() . '/' );
		$child_theme_dir = get_stylesheet_directory() . '/';
		$child_theme_uri = 'dir' === $method ? $child_theme_dir : ( get_stylesheet_directory_uri() . '/' );
		$template =  "{$plugin_uri}skins/{$theme}/content-cards.{$extension}";
		if ( file_exists( "{$theme_dir}content-cards.{$extension}" ) ) {
			$template = "{$theme_uri}content-cards.{$extension}";
		}
		if ( file_exists( "{$child_theme_dir}content-cards.{$extension}" ) ) {
			$template = "{$child_theme_uri}content-cards.{$extension}";
		}
		if ( file_exists( "{$plugin_path}skins/{$theme}/content-cards-{$type}.{$extension}" ) ) {
			$template = "{$plugin_uri}skins/{$theme}/content-cards-{$type}.{$extension}";
		}
		if ( file_exists( "{$theme_dir}content-cards-{$type}.{$extension}" ) ) {
			$template = "{$theme_uri}content-cards-{$type}.{$extension}";
		}
		if ( file_exists( "{$child_theme_dir}content-cards-{$type}.{$extension}" ) ) {
			$template = "{$child_theme_uri}content-cards-{$type}.{$extension}";
		}
		$template = apply_filters( 'content_cards_file', $template, $url, $extension );
		return $template;
	}

	/**
	 * Gets the template for the Content Card
	 *
	 * @param $url
	 * @param string $type
	 * @return mixed|string|void
	 */
	private static function get_template( $url, $type = 'website', $skin = false ) {
		if ( false === $skin ) {
			$skin = self::$options['skin'];
		}
		$template = self::_get_file( $url, 'php', $type, $skin );
		$template = apply_filters( 'content_cards_template', $template, $url );
		return $template;
	}

	/**
	 * Gets the stylesheet for the Content Card
	 *
	 * @return mixed|string|void
	 */
	private static function get_stylesheet( ) {
		$template = self::_get_file( false, 'css', '', self::$options['skin'], 'uri'  );
		$template = apply_filters( 'content_cards_stylesheet', $template );
		return $template;
	}


	/**
	 * Gets the placeholder image for the Content Card
	 *
	 * @return mixed|string|void
	 */
	private static function get_placeholder( ) {
		$template = self::_get_file( false, 'png', 'placeholder', self::$options['skin'], 'uri'  );
		$template = apply_filters( 'content_cards_placeholder', $template );
		return $template;
	}

	/**
	 * Gets the loading image for the Content Card
	 *
	 * @return mixed|string|void
	 */
	private static function get_loading_image( ) {
		$template = self::_get_file( false, 'gif', 'loading', self::$options['skin'], 'uri'  );
		$template = apply_filters( 'content_cards_loading_image', $template );
		return $template;
	}

	/**
	 * Gets the editor CSS image for the Content Card
	 *
	 * @return mixed|string|void
	 */
	private static function get_editor_stylesheet( ) {
		$template = self::_get_file( false, 'css', 'editor', self::$options['skin'], 'uri'  );
		$template = apply_filters( 'content_cards_editor_stylesheet', $template );
		return $template;
	}

	/**
	 * Adds admin stylesheet
	 */
	public static function admin_init() {
		if ( self::$stylesheet ) {
			add_editor_style( self::$stylesheet );
		}

		/* Stylesheet for loading indicator */
		add_editor_style( self::get_editor_stylesheet() );
	}

	/**
	 * Creates admin menu
	 */
	public static function init_options() {
		// require_once ( self::$plugin_path . 'includes/options.php' );
		self::$settings = array(
			'page' => array(
				'title' 			=> __( 'Content Cards Settings', 'content-cards' ),
				'menu_title'	=> __( 'Content Cards', 'content-cards' ),
				'slug' 				=> 'content-cards-settings',
				'option'			=> 'content-cards_options',
				// optional
				// 'description'	=> __( 'Some general information about the plugin', 'content-cards' ),
			),
			'sections' => array(
				"general" => array(
					'title'				=> '',
					'fields' => array(
						'patterns' => array(
							'title'				=> __('oEmbed White List','content-cards'),
							'callback' 		=> 'textarea',
							'attributes' 	=> array (
								'rows'	=> 10,
								'cols'	=> 60,
							),
							'description' => __( 'A list of domain names, i.e. <code>domain.com</code>, one per line.', 'content-cards' ),
						),
						'skin' => array(
							'title'				=> __('Snippet Skin','content-cards'),
							'callback' 		=> 'listfield',
							'list' 				=> self::get_skins(),
							'description' => __( 'Can be overwritten by theme.', 'content-cards' ),
						),
						'target' => array(
							'title'				=> __('Link Target','content-cards'),
							'label'				=> __('Open links in new tab?','content-cards'),
							'callback' 		=> 'checkbox',
						),
						'update_interval' => array(
							'title'				=> __('Update Interval','content-cards'),
							'callback' 		=> 'listfield',
							'list'	 			=> array(
								HOUR_IN_SECONDS     => __( 'Hourly', 'content-cards' ),
								2 * HOUR_IN_SECONDS => __( 'Every 2 Hours', 'content-cards' ),
								6 * HOUR_IN_SECONDS => __( 'Every 6 Hours', 'content-cards' ),
								DAY_IN_SECONDS / 2  => __( 'Twice Daily', 'content-cards' ),
								DAY_IN_SECONDS      => __( 'Daily', 'content-cards' ),
							),
							'description' => __( 'How often should Content Cards check for changes in OpenGraph data?', 'content-cards' ),
						),
						'default_image' => array(
							'title'							=> __( 'Placeholder Image', 'content-cards' ),
							'callback' 					=> 'upload',
							'description' 			=> __( 'Placeholder image for links that do not have OpenGraph data. (Leave empty to use default image set by skin.)', 'content-cards' ),
							'button_attributes' => array(
								'value' 											=> __( 'Upload Image', 'content-cards' ),
								'data-uploader_button_text' 	=> __( 'Upload Image', 'content-cards' ),
								'data-uploader_title' 				=> __( 'Select File', 'content-cards' ),
							),
						),
						'cache_images' => array(
							'title'			=> __( 'Cache Images', 'content-cards' ),
							'label'			=> __( 'Should Content Cards cache images to Media Library?', 'content-cards' ),
							'callback' 	=> 'checkbox',
						),
						'word_limit' => array(
							'title'				=> __( 'Word Limit', 'content-cards' ),
							'description'	=> __( 'Limit maximum number of words in description.', 'content-cards' ),
							'attributes' 	=> array (
								'type' 	=> 'number',
								'min' 	=> 0,
							),
						),
					),
				),
			),
			'l10n' => array(
				'no_access'			=> __( 'You do not have sufficient permissions to access this page.', 'content-cards' ),
				'save_changes'	=> esc_attr( 'Save Changes', 'content-cards' ),
				'upload'				=> __( 'Upload File', 'content-cards' ),
				'upload_button' => __( 'Upload', 'content-cards' ),
			),
		);
		require_once( self::$plugin_path . 'tiny/tiny.options.php' );
		self::$settings = new tinyOptions( self::$settings, __CLASS__ );
	}

	/**
	 * Builds a list of skins from the skins/ folder.
	 *
	 * @return array
	 */
	private static function get_skins() {
		$dir = plugin_dir_path( __FILE__ );
		$dir .= 'skins/*';
		$list = glob($dir);
		$skins = array();
		foreach ( $list as $skin ) {
			$key = basename($skin);
			$data = self::get_skin_data( $skin );
			if ( !$data['name'] ) {
				continue;
			}
			$name = $data['name'];
			// if ( $data['version'] ) {
			// 	$name .= " {$data['version']}";
			// }
			// if ( $data['author'] ) {
			// 	$name .= " by {$data['author']}";
			// }
			$skins[$key] = $name;
		}

		//Sort skins in alphabetical order
		asort($skins);

		return $skins;
	}

	/**
	 * Parses skin info data
	 *
	 * @param $dir
	 * @return array|bool
	 */
	private static function get_skin_data( $dir ) {
		$stylesheet = $dir."/content-cards.css";
		if ( !file_exists( $stylesheet ) ) {
			return false;
		}
		$default = array(
			'name' 		=> 'Skin Name',
			'version' 	=> 'Version',
			'author'  	=> 'Author',
		);
		$skin_data = get_file_data( $stylesheet, $default, 'cc_skin' );
		return $skin_data;
	}

	/**
	 * Enqueues frontend styles
	 */
	public static function styles() {
		if ( self::$stylesheet ) {
			wp_register_style( 'content-cards', self::$stylesheet );
			wp_enqueue_style( 'content-cards' );
		}
	}

	/**
	 * Loads styles for AMP
	 */
	public static function amp_styles() {
		$stylesheet = self::_get_file( false, 'css', false, self::$options['skin'] );
		$amp_stylesheet = self::_get_file( false, 'css', 'amp', self::$options['skin'] );
		// var_dump( $stylesheet );
		if ( $stylesheet ) {
			echo file_get_contents( $stylesheet );
		}
		if ( $stylesheet !== $amp_stylesheet ) {
			echo file_get_contents( $amp_stylesheet );
		}
	}

	/**
	 * oEmbed handler for domains in
	 * "oEmbed White List"
	 *
	 * @param $matches
	 * @param $attr
	 * @param $url
	 * @param $rawattr
	 * @return mixed|void
	 */
	public static function oembed( $matches, $attr, $url, $rawattr ) {
		$embed = self::build( $url );
		return apply_filters( 'embed_content_cards', $embed, $matches, $attr, $url, $rawattr );
	}

	/**
	 * Registers shortcode
	 *
	 * @param $args
	 * @param string $content
	 * @return string
	 */
	public static function shortcode( $args, $content = '') {
		$result = '';
		if ( !isset( $args['url'] ) ) {
			return $result;
		}
		$result = self::build( $args['url'], $args, !is_admin() );
		return $result;
	}

	/**
	 * Builds the data and output
	 * for displaying the Content Card
	 *
	 * @param $url
	 * @param null $target
	 * @return string
	 */
	public static function build( $url, $args = array(), $fallback = false ) {
		$default = array(
			'url' 				=> $url,
			'target'			=> self::$options['target'],
			'word_limit'	=> self::$options['word_limit'],
			'class'				=> '',
			'skin'				=> self::$options['skin'],
		);
		$args = wp_parse_args( $args, $default );
		$args = apply_filters( 'content_cards_args', $args, $url );
		$data = self::get_data( $url );
		if ( !$data ) {
			$result = '';
			if ( $fallback ) {
				$target = $args['target'] ? ' target="_blank"' : "";
				$domain = wp_parse_url( $url, PHP_URL_HOST );
				$result = wpautop( "<a href=\"{$url}\"{$target}>{$domain}</a>" );
			}
			return $result;
		}
		$data['description'] = wpautop(isset($data['description'])?$data['description']:'');
		$data['url'] = $url;
		$data['target'] = $args['target'];
		$data['css_class'] = $args['class'];
		$type = isset( $data['type'] ) ? $data['type'] : 'website';
		$data = apply_filters( 'content_cards_data', $data, $url );
		self::$temp_data = $data;
		$template = self::get_template( $url, $type, $args['skin'] );
		ob_start();
		require( $template );
		$result = ob_get_contents();
		ob_end_clean();
		return $result;
	}

	/**
	 * Retrieves the OpenGraph info
	 * from postmeta storage
	 *
	 * @param $url
	 * @param $post_id
	 * @return array|mixed
	 */
	private static function get_data( $url, $post_id = false ) {
		if ( !$post_id ) {
			$post_id = get_the_id();
		}
		$url_md5 = md5( $url );
		$result = get_post_meta( $post_id, 'content_cards_'.$url_md5, true );
		if ( !$result ) {
			$args = array( $post_id, $url, $url_md5, MINUTE_IN_SECONDS );
			if ( false === wp_next_scheduled( 'content_cards_retry', $args ) ) {
				$result = self::get_remote_data( $url, $post_id );
				if ( $result ) {
					$result['url'] = $url;
					$meta_id = update_post_meta( $post_id, 'content_cards_'.$url_md5, $result );
				} else {
					wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'content_cards_retry', $args );
				}
			}
		}
		if ( $result && time() - $result['cc_last_updated'] > self::$options['update_interval'] ) {
			$args = array(
				$post_id,
				$url,
				$url_md5,
			);
			if ( false === wp_next_scheduled( 'content_cards_update', $args ) ) {
				wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'content_cards_update', $args );
			}
		}
		if ( $result && !isset( $result['image'] ) ) {
			$result['image'] = self::$options['default_image'] ? self::$options['default_image'] : self::get_placeholder();
		}

		$args = array(
			$post_id,
		);
		if ( false === wp_next_scheduled( 'content_cards_link_cleanup', $args ) ) {
			wp_schedule_single_event( time() + self::$options['cleanup_interval'] - HOUR_IN_SECONDS + mt_rand(0, HOUR_IN_SECONDS), 'content_cards_link_cleanup', $args );
		}
		return $result;
	}

	/**
	 * Updates OpenGraph info
	 * from remote site
	 * via wp_cron task
	 *
	 * @param $post_id
	 * @param $url
	 * @param $url_md5
	 * @return null
	 */
	public static function update_data( $post_id, $url, $url_md5 ) {
		// remove link metadata if link is not in post_content anymore
		$content = get_post_field('post_content', $post_id);
		if ( false === strpos( $content, $url ) ) {
			delete_post_meta( $post_id, 'content_cards_'.$url_md5 );
			return false;
		}
		// update link metadata from remote source
		$result = get_post_meta( $post_id, 'content_cards_'.$url_md5, true );
		if ( $result && time() - $result['cc_last_updated'] > self::$options['update_interval'] ) {
			$new_result = self::get_remote_data( $url, $post_id );
			if ( $new_result ) {
				$new_result['url'] = $url;
				$result = $new_result;
			} else {
				$result['cc_last_updated'] = time();
			}
			$meta_id = update_post_meta( $post_id, 'content_cards_'.$url_md5, $result );
		}
	}

	/**
	 * Retries to get OpenGraph info
	 * from remote site
	 * via wp_cron task
	 *
	 * @param $post_id
	 * @param $url
	 * @param $url_md5
	 * @return null
	 */
	public static function retry_data( $post_id, $url, $url_md5, $interval ) {
		// remove link metadata if link is not in post_content anymore
		$content = get_post_field('post_content', $post_id);
		if ( false === strpos( $content, $url ) ) {
			delete_post_meta( $post_id, 'content_cards_'.$url_md5 );
			return false;
		}
		// update link metadata from remote source
		$result = get_post_meta( $post_id, 'content_cards_'.$url_md5, true );
		if ( $result ) {
			$new_result = self::get_remote_data( $url, $post_id );
			if ( $new_result ) {
				$new_result['url'] = $url;
				$result = $new_result;
			}
			$result['cc_last_updated'] = time();
			$meta_id = update_post_meta( $post_id, 'content_cards_'.$url_md5, $result );
		} else {
			$args = array( $post_id, $url, $url_md5, $interval * 2 );
			wp_schedule_single_event( time() + $interval, 'content_cards_retry', $args );
		}
	}

	/**
	 * Retrieves the OpenGraph info
	 * from remote site
	 *
	 * @param $url
	 * @return array|mixed
	 */
	private static function get_remote_data( $url, $post_id ) {
		if ( !class_exists( 'tiny_OpenGraph' ) ) {
			require_once( self::$plugin_path . 'includes/opengraph.php' );
		}

		$data = wp_remote_retrieve_body( wp_remote_get( $url ) );
		$data = mb_convert_encoding($data, 'HTML-ENTITIES', 'auto,ISO-8859-1');
		$result = array();
		if ( $data ) {
			$graph = tiny_OpenGraph::parse( $data );
			if ( $graph ) {
				foreach ($graph as $key => $value) {
				    $result[$key] = $value;
				}
			}
		}
		if ( $data && !$result ) {
			$result = self::get_remote_data_fallback( $data );
		}
		if ( $result ) {
			if ( !isset( $result['site_name'] ) ) {
				$result['site_name'] = wp_parse_url( $url, PHP_URL_HOST );
			}
			$result['favicon'] = self::get_remote_favicon( $data, $url );
			if ( isset($result['image']) && $result['image'] ) {
				$result['image'] = self::force_absolute_url( $result['image'], $url );
				if ( isset( $result['image_id'] ) && $result['image_id'] ) {
					$image_data = get_post_meta( $result['image_id'], 'content_cards_cached', true );
					if ( $image_data['original_url'] !== $result['image'] ) {
						wp_delete_attachment( $result['image_id'], true );
						unset( $result['image_id'] );
					}
				}
			    if ( self::$options['cache_images'] && ( !isset( $result['image_id'] ) || !$result['image_id'] ) ) {
					$image_id = self::cache_image( $result['image'], $post_id );
					if ( $image_id ) {
						$result['image_id'] = $image_id;
					}
				}
			}
			$result['cc_last_updated'] = time();

			if ( isset( $result['description'] ) && self::$options['word_limit'] ) {
				$result['description'] = wp_trim_words( $result['description'], self::$options['word_limit'] );
			}
		}
		return $result;
	}
	public static function schedule_cleanups() {
		global $wpdb;
		$q = "SELECT DISTINCT `post_id` FROM {$wpdb->postmeta} WHERE meta_key LIKE 'content_cards_%' AND meta_key != 'content_cards_cached'";
		$posts = $wpdb->get_col( $q, 0 );
		foreach ($posts as $post_id) {
			$args = array(
				$post_id,
			);
			if ( false === wp_next_scheduled( 'content_cards_link_cleanup', $args ) ) {
				wp_schedule_single_event( time() + self::$options['cleanup_interval'] - HOUR_IN_SECONDS + mt_rand(0, HOUR_IN_SECONDS), 'content_cards_link_cleanup', $args );
			}
		}
		$q = "SELECT DISTINCT `post_id` FROM {$wpdb->postmeta} WHERE meta_key = 'content_cards_cached'";
		$images = $wpdb->get_col( $q, 0 );
		foreach ($images as $post_id) {
			$args = array(
				$post_id,
			);
			if ( false === wp_next_scheduled( 'content_cards_image_cleanup', $args ) ) {
				wp_schedule_single_event( time() + self::$options['cleanup_interval'] - HOUR_IN_SECONDS + mt_rand(0, HOUR_IN_SECONDS), 'content_cards_image_cleanup', $args );
			}
		}
	}
	public static function link_cleanup( $post_id ) {
		$meta = get_post_meta( $post_id );
		$content = get_post_field('post_content', $post_id);
		foreach ( $meta as $key => $value ) {
			if ( 0 === strpos( $key, 'content_cards_') ) {
				$value = unserialize($value[0]);
				if ( false === strpos( $content,  $value['url'] ) ) {
					delete_post_meta( $post_id, $key );
					if ( $value['image_id'] ) {
						wp_delete_attachment( $value['image_id'], true );
					}
				}
			}
		}
	}
	public static function image_cleanup( $image_id ) {
		$image_meta = get_post_meta( $image_id, 'content_cards_cached', true );
		$post_id = $image_meta['post_id'];
		$meta = get_post_meta( $post_id );
		$found = false;
		foreach ( $meta as $key => $value ) {
			if ( 0 === strpos( $key, 'content_cards_') ) {
				$value = unserialize($value[0]);
				if ( isset( $value['image_id'] ) && $image_id == $value['image_id'] )  {
					$found = $key;
					break;
				}
			}
		}
		if ( !$found ) {
			wp_delete_attachment( $image_id, true );
		}
	}

	public static function filter_cached_images( $args ) {
		if ( !isset($args['meta_query'])) {
			$args['meta_query'] = array();
		}
		$args['meta_query'][] = array(
			'key' 		=> 'content_cards_cached',
			'compare'	=> 'NOT EXISTS',
		);
		return $args;
	}
	public static function filter_cached_images_query( $query ) {
		if ( !is_admin() ) {
			return $query;
		}
		if (defined('DOING_AJAX') && DOING_AJAX) {
			return $query;
		}
		if ( !function_exists('get_current_screen') || 'upload' !== get_current_screen()->base ) {
			return $query;
		}
		$q = $query->get( 'meta_query' );
		$q[] = array(
			'key' 		=> 'content_cards_cached',
			'compare'	=> 'NOT EXISTS',
		);
		$query->set( 'meta_query', $q );
		return $query;
	}
	public static function filter_cached_images_count( $count ) {
		$cached = 'image/cached';
		$count->$cached = self::_count_cached() * -1;
		return $count;
	}
	public static function filter_cached_images_orphans( $query ) {
		global $wpdb;
		if ( "SELECT COUNT( * ) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status != 'trash' AND post_parent < 1" == $query ) {
			$count_cached = self::_count_cached();
			$query = "SELECT COUNT( * ) - {$count_cached} FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status != 'trash' AND post_parent < 1";
		}
		return $query;
	}
	private static function _count_cached() {
		global $wpdb;
		$q = "SELECT COUNT( * ) FROM {$wpdb->postmeta} WHERE meta_key='content_cards_cached'";
		$q = $wpdb->get_var($q);
		return $q;
	}
	private static function _delete_cached() {
		global $wpdb;
		$q = "SELECT `post_id` FROM {$wpdb->postmeta} WHERE meta_key='content_cards_cached'";
		$q = $wpdb->get_col($q);
		foreach ($q as $attachment_id) {
			wp_delete_attachment( $attachment_id, true );
		}
	}

	private static function cache_image( $image_url, $post_id ) {
		require_once(ABSPATH . "wp-admin" . '/includes/image.php');
		require_once(ABSPATH . "wp-admin" . '/includes/file.php');
		require_once(ABSPATH . "wp-admin" . '/includes/media.php');
		$temp_file = download_url( $image_url );
		if ( !is_wp_error( $temp_file ) ) {
			$allowed_mime_types = array(
				'image/jpeg',
				'image/gif',
				'image/png',
				'image/bmp',
				'image/tiff',
				'image/x-icon',
			);
			$mime = self::_get_mime_type( $temp_file);
			preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png|ico)/i', $image_url, $matches);
			if ( in_array( $mime, $allowed_mime_types ) ) {
				$filename = basename( $matches[0] );
				$filename = urldecode( $filename );
				$filename = explode( '.', $filename );
				foreach ($filename as $key => $value) {
					$filename[ $key ] = sanitize_title( $value );
				}
				$filename = implode( '.', $filename );
				$file = array(
					'name' => $filename,
					'type' => $mime,
					'tmp_name' => $temp_file,
					'error' => 0,
					'size' => filesize($temp_file),
				);
				$overrides = array(
					'test_form' => false,
					'test_size' => true,
					'test_upload' => true,
				);
				$a = wp_check_filetype_and_ext($file['tmp_name'],$file['name'],false);
				$movefile = wp_handle_sideload( $file, $overrides );
				if ( $movefile && !isset( $movefile['error'] ) ) {
					$wp_upload_dir = wp_upload_dir();
					$attachment = array(
						'guid'           => $wp_upload_dir['url'] . '/' . basename( $movefile['file'] ),
						'post_mime_type' => $movefile['type'],
						'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $movefile['file'] ) ),
						'post_content'   => '',
						'post_status'    => 'inherit'
					);
					$attach_id = wp_insert_attachment( $attachment, $movefile['file'] );
					require_once( ABSPATH . 'wp-admin/includes/image.php' );
					$attach_data = wp_generate_attachment_metadata( $attach_id, $movefile['file'] );
					wp_update_attachment_metadata( $attach_id, $attach_data );
					$cached = array(
						'post_id' => $post_id,
						'original_url' => $image_url,
					);
					add_post_meta( $attach_id, 'content_cards_cached', $cached );
					return $attach_id;
				}
			}
		}
		return false;
	}
	private static function _get_mime_type($file) {
		$mtype = false;
		if (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mtype = finfo_file($finfo, $file);
			finfo_close($finfo);
		} elseif (function_exists('mime_content_type')) {
			$mtype = mime_content_type($file);
		}
		return $mtype;
	}

	private static function get_remote_favicon( $html, $url ) {
		$old_libxml_error = libxml_use_internal_errors(true);
		$dom = new DOMDocument();
        $dom->loadHTML( $html );
		libxml_use_internal_errors($old_libxml_error);
        $links = $dom->getElementsByTagName('link');
        $favicon = false;
        for( $i=0; $i < $links->length; $i++ ) {
            $link = $links->item( $i );
            if ( in_array( $link->getAttribute('rel'), array( 'icon', "Shortcut Icon", "shortcut icon") ) ) {
                $favicon = $link->getAttribute('href');
                break;
            }
        }
        if ( !$favicon ) {
	        $url_parts = wp_parse_url( $url );
	        $temp = "{$url_parts['scheme']}://{$url_parts['host']}/favicon.ico";
	        $response = wp_remote_head($temp);
	        if ( isset($response['headers']['content-type']) && 0 === strpos( $response['headers']['content-type'], 'image/' ) ) {
	        	$favicon = $temp;
	        }
        }
        $favicon = self::force_absolute_url( $favicon, $url );
        return $favicon;
	}

	private static function force_absolute_url( $url, $site_url ) {
		if ( $url && !filter_var( $url, FILTER_VALIDATE_URL ) && !filter_var( 'http:'.$url, FILTER_VALIDATE_URL ) ) {
			$url_parts = wp_parse_url($site_url);
    		$site_url = $url_parts['scheme'] . "://" . $url_parts['host'] . "/";
    		if ( 0 !== strpos( $url, '/' ) ) {
    			$url = '/'.$url;
    		}
    		$url = untrailingslashit( $site_url ) . $url;
		}
		return $url;
	}

	private static function get_remote_data_fallback( $data ) {
		$result = array();

		$title = false;
		$description = false;

		$old_libxml_error = libxml_use_internal_errors(true);
		$doc = new DOMDocument;
		$doc->loadHTML( $data );
		libxml_use_internal_errors($old_libxml_error);



		$title_dom = $doc->getElementsByTagName( 'title' );
		if( $title_dom->item(0) ) {
			$title = $title_dom->item(0)->textContent;
		};

		$xpath = new DOMXPath($doc);
		$description_dom = $xpath->query('//meta[@name="description"]/@content');

		if($description_dom->item(0)) {
			$description = $description_dom->item(0)->value;
		}

		if ( $title && $description ) {
			$result = array(
				'title' => $title,
				'description' => $description
			);
		}
		return $result;
	}

    public static  function init_preview() {
        add_action( 'print_media_templates', array( 'Content_Cards', 'print_media_templates' ) );
        add_action( 'admin_enqueue_scripts', array( 'Content_Cards', 'scripts_preview' ), 100 );
    }
	public static function ajax_shortcode() {
		// Don't sanitize shortcodes — can contain HTML kses doesn't allow (e.g. sourcecode shortcode)
		if ( ! empty( $_POST['shortcode'] ) ) {
			$shortcode = stripslashes( $_POST['shortcode'] );
		} else {
			$shortcode = null;
		}
		if ( isset( $_POST['post_id'] ) ) {
			$post_id = intval( $_POST['post_id'] );
		} else {
			$post_id = null;
		}
		if ( ! empty( $post_id ) ) {
			global $post;
			$post = get_post( $post_id );
			setup_postdata( $post );
		}

		wp_send_json_success( do_shortcode( $shortcode ) );
	}

    /**
     * Outputs the view inside the wordpress editor.
     */
    public static function print_media_templates() {
        if ( ! isset( get_current_screen()->id ) || get_current_screen()->base != 'post' )
            return;
        ?>
        <script type="text/html" id="tmpl-editor-contentcards">
			<div class="content_cards_preview">{{ data.content }}</div>
		</script>
        <?php
    }


    public static function scripts_preview() {
        if ( ! isset( get_current_screen()->id ) || get_current_screen()->base != 'post' ) {
            return;
        }
    	wp_enqueue_script( 'content-cards', plugins_url( 'content-cards.js', __FILE__ ), array('shortcode'), false, true );
    	$data = array(
    		'loading_image' => self::get_loading_image(),
    		'icon' => plugins_url( 'content-cards-button.png', __FILE__ ),
    		'texts' => array(
    			'main_label' 		=> __( 'Main', 'content-cards' ),
    			'advanced_label'	=> __( 'Advanced', 'content-cards' ),
    			'link_label' 		=> __( 'Content Card URI', 'content-cards' ),
    			'target_label' 		=> __( 'Target', 'content-cards' ),
    			'target_text' 		=> __( 'Open Link in New Tab', 'content-cards' ),
    			'class_label' 		=> __( 'CSS classes', 'content-cards' ),
    			'wordlimit_label'	=> __( 'Word Limit', 'content-cards' ),
    			'link_dialog_title' => __( 'Edit Content Card', 'content-cards' ),
    			'add_dialog_title'  => __( 'Add Content Card', 'content-cards' ),
				'loading_image_heading' => __( 'This Content Card is still processing', 'content-cards' ),
				'loading_image_text' => __( 'If this message persists, make sure the link you are trying to embed is reachable. While your content card hasn\'t been processed, visitors will see a normal link.' , 'content-cards' ),
    		)
    	);
    	wp_localize_script( 'content-cards', 'contentcards', $data );
    }

	public static function admin_scripts() {
    	wp_register_script( 'content-cards-upload', plugins_url( 'content-cards-upload.js', __FILE__ ) , array('jquery','media-upload','thickbox') );
	    if ( 'settings_page_content-cards-settings' == get_current_screen() -> id ) {
	        wp_enqueue_media();
	        wp_enqueue_script( 'content-cards-upload' );
	    }
	}
}

/**
 * Returns Content Card data, template function.
 *
 * @param $key
 * @param $sanitize
 * @return mixed
 */
function get_cc_data( $key, $sanitize = false ) {
	$result = isset(Content_Cards::$temp_data[$key]) ? Content_Cards::$temp_data[$key] : '';
	if (is_callable($sanitize)) {
		$result = call_user_func( $sanitize, $result );
	}
	return $result;
}

/**
 * Prints Content Card data, template function.
 *
 * @param $key
 */
function the_cc_data( $key, $sanitize = false ) {
	echo get_cc_data( $key, $sanitize );
}

/**
 * Generates the target="" portion of the Content Card link based
 * on user settings.
 */
function the_cc_target() {
	echo Content_Cards::$temp_data['target'] ? ' target="_blank"' : '';
}

/**
 * Add in filterable CSS classes
 *
 * @param array $classes classes to print.
 */
function the_cc_css_classes( $classes = array( 'content_cards_card' ) ) {
	$temp_class = Content_Cards::$temp_data['css_class'];
	$temp_class = explode( ' ', $temp_class );
	if ( ! is_array( $classes ) ) {
		$classes = explode( ' ', $classes );
	}
	$domain = wp_parse_url( Content_Cards::$temp_data['url'], PHP_URL_HOST );
	$domain = sanitize_title( $domain );
	$classes[] = "content_cards_domain_{$domain}";
	$classes = array_merge( $temp_class, $classes );
	$classes = apply_filters( 'content_cards_css_classes', $classes );
	$classes = implode( ' ', $classes );
	echo esc_attr( $classes );
}

/**
 * Returns cached image url or original image url, if cache is not available
 *
 * @param $size - WordPress image size
 * @param $sanitize
 */
function get_cc_image( $size = 'thumbnail', $sanitize = false ) {
	if ( isset(Content_Cards::$temp_data['image_id']) && Content_Cards::$temp_data['image_id'] ) {
		$result = wp_get_attachment_image_src( Content_Cards::$temp_data['image_id'], $size );
		$result = $result[0];
		if (is_callable($sanitize)) {
			$result = call_user_func( $sanitize, $result );
		}
		return $result;
	} else if ( isset(Content_Cards::$temp_data['image']) && Content_Cards::$temp_data['image'] ) {
		return get_cc_data( 'image', $sanitize );
	} else {
		return false;
	}
}

/**
 * Prints cached image original image, if cache is not available
 *
 * @param $size - WordPress image size
 * @param $args
 */
function the_cc_image( $size = 'thumbnail', $args = array() ) {
	$defaults = array(
		'alt' => get_cc_data( 'title' ),
	);
	$args = wp_parse_args( $args, $defaults );
	$attributes = array();
	foreach ($args as $key => $value) {
		$value = esc_attr( $value );
		$attributes[] = "{$key}=\"{$value}\"";
	}
	$attributes = implode( ' ', $attributes );
	$img = get_cc_image( $size, 'esc_url' );
	$result = "<img src=\"{$img}\" {$attributes}>";
	echo $result;
}
