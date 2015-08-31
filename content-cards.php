<?php
/*
Plugin Name: Content Cards
Description: Pull OpenGraph data from other websites and show them as "Cards"
Version: 0.2.0
Author: Arūnas Liuiza, khromov
Author URI: http://arunas.co
License: GPL2
*/

add_action( 'plugins_loaded', array( 'Content_Cards', 'init' ) );
register_uninstall_hook( __FILE__, array( 'Content_Cards', 'uninstall' ) );
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
	);
	private static $stylesheet = '';
	public static $temp_data = array();

	public static function init() {
		$options = get_option( 'content-cards_options' );
		self::$options = wp_parse_args( $options, self::$options );
		if ( isset( self::$options['theme'] ) ) {
			self::$options['skin'] = self::$options['theme'];
			unset( self::$options['theme'] );
		}
		self::$stylesheet = self::get_stylesheet();
		add_action( 'wp_enqueue_scripts', 	array( 'Content_Cards', 'styles' ) );
		add_action( 'admin_init', 			array( 'Content_Cards', 'admin_init' ) );
		add_action( 'admin_menu', 			array( 'Content_Cards', 'admin_menu' ) );
		add_action( 'content_cards_update', array( 'Content_Cards', 'update_data' ), 10, 3 );

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
	}

	public static function uninstall() {
		global $wpdb;
		$q = "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` LIKE 'content_cards_%'";
		$wpdb->query( $q );
		delete_option( 'content-cards_options' );
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
		$plugin_dir = plugin_dir_path( __FILE__ );
		$plugin_uri = 'dir' === $method ? $plugin_dir : plugins_url( '/', __FILE__ );
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
		if ( file_exists( "{$plugin_dir}skins/{$theme}/content-cards-{$type}.{$extension}" ) ) {
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
	private static function get_template( $url, $type = 'website' ) {
		$template = self::_get_file( $url, 'php', $type, self::$options['skin'] );
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
	 * Adds admin stylesheet
	 */
	public static function admin_init() {
		if ( self::$stylesheet ) {
			add_editor_style( self::$stylesheet );		
		}
	}

	/**
	 * Creates admin menu
	 */
	public static function admin_menu() {
		require_once ( 'includes/options.php' );
		$fields =   array(
			"general" => array(
				'title' => '',
				'callback' => '',
				'options' => array(
					'patterns' => array(
						'title'=>__('oEmbed White List','content-cards'),
						'args' => array (
							'rows' 		  => 10,
							'cols'		  => 60,
							'description' => __( 'A list of domain names, i.e. <code>domain.com</code>, one per line.', 'content-cards' ),
						),
						'callback' => 'textarea',
					),
					'skin' => array(
						'title'=>__('Snippet Skin','content-cards'),
						'args' => array (
							'values' => self::get_skins(),
							'description' => __( 'Can be overwritten by theme.', 'content-cards' ),
						),
						'callback' => 'select',
					),
					'target' => array(
						'title'=> __('Link Target','content-cards'),
						'args' => array (
							'label'			=> __('Open links in new tab?','content-cards'),
						),
						'callback' => 'checkbox',
					),
					'update_interval' => array(
						'title'=>__('Update Interval','content-cards'),
						'args' => array (
							'values' => array(
								HOUR_IN_SECONDS     => __( 'Hourly', 'content-cards' ),
								2 * HOUR_IN_SECONDS => __( 'Every 2 Hours', 'content-cards' ),
								6 * HOUR_IN_SECONDS => __( 'Every 6 Hours', 'content-cards' ),
								DAY_IN_SECONDS / 2  => __( 'Twice Daily', 'content-cards' ),
								DAY_IN_SECONDS      => __( 'Daily', 'content-cards' ),
							),
							'description' => __( 'How often should Content Cards check for changes in OpenGraph data?', 'content-cards' ),
						),
						'callback' => 'select',
					),
				),
			),
		);
		$tabs = array();
		Content_Cards_Options::init(
			'content-cards',
			__( 'Content Cards',          'content-cards' ),
			__( 'Content Cards Settings', 'content-cards' ),
			$fields,
			$tabs,
			'Content_Cards',
			'content-cards-settings'
		);		
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
		if ( isset( $args['target'] ) &&  in_array( $args['target'], array( true, 'true', 'blank', '_blank' ) ) ) {
			$target = true;
		} else {
			$target = null;
		}
		$result = self::build( $args['url'], $target );
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
	public static function build( $url, $target = null ) {
		if ( null === $target ) {
			$target = self::$options['target'];
		}
		$data = self::get_data( $url );
		$result = "";
		if ( !$data ) {
			return $result;
		}
		$data['description'] = wpautop($data['description']);
		$data['url'] = $url;
		$data['target'] = $target;
		$type = isset( $data['type'] ) ? $data['type'] : 'website';
		$data = apply_filters( 'content_cards_data', $data, $url );
		self::$temp_data = $data;
		$template = self::get_template( $url, $type );
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
			$result = self::get_remote_data( $url );
			if ( $result ) {
				$result['url'] = $url;
				$meta_id = update_post_meta( $post_id, 'content_cards_'.$url_md5, $result );
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
			$new_result = self::get_remote_data( $url );
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
	 * Retrieves the OpenGraph info
	 * from remote site
	 *
	 * @param $url
	 * @return array|mixed
	 */
	private static function get_remote_data( $url ) {
		require_once( 'includes/opengraph.php' );
		$data = wp_remote_retrieve_body( wp_remote_get( $url ) );
		$result = array();
		if ( $data ) {
			$graph = OpenGraph::parse( $data );
			if ( sizeof( $graph ) > 0 ) {
				foreach ($graph as $key => $value) {
				    $result[$key] = $value;
				}				
			}
		}
		if ( $result ) {
			$result['cc_last_updated'] = time();
		}
		return $result;
	} 
}

/**
 * Returns Content Card data, template function.
 *
 * @param $key
 * @return mixed
 */
function get_cc_data( $key ) {
	return isset(Content_Cards::$temp_data[$key]) ? Content_Cards::$temp_data[$key] : '';
}

/**
 * Prints Content Card data, template function.
 *
 * @param $key
 */
function the_cc_data( $key ) {
	echo get_cc_data( $key );
}

/**
 * Generates the target="" portion of the Content Card link based
 * on user settings.
 */
function the_cc_target() {
	echo Content_Cards::$temp_data['target'] ? ' target="_blank"' : '';
}
