<?php
/*
Plugin Name: Content Cards
Description: Pull OpenGraph data from other websites and show them as "Cards"
Version: 0.2.0
Author: Arūnas Liuiza
Author URI: http://arunas.co
License: GPL2
*/

add_action( 'plugins_loaded', array( 'Content_Cards', 'init' ) );
class Content_Cards {
	public static $options = array(
		'patterns' => "wptavern.com\r\nwordpress.org",
		'skin' => 'default',
		'target' => false,
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
	public static function editor_button_js( $plugin_array ) {
    	$plugin_array['contentcards'] = plugins_url( 'content-cards-button.js', __FILE__ );
	    return $plugin_array;
	}
	public static function editor_button( $buttons ) {
	    array_push( $buttons, 'contentcards_shortcode' );
	    return $buttons;
	}
	private static function _get_file( $url, $extension='php', $type = 'website', $theme ="default", $method='dir' ) {
		$plugin_dir = plugin_dir_path( __FILE__ );
		$plugin_uri = 'dir' === $method ? $plugin_dir : plugins_url( '/', __FILE__ );
		$theme_dir = get_template_directory() . '/';
		$theme_uri = 'dir' === $method ? $theme_dir : ( get_template_directory_uri() . '/' );
		$child_theme_dir = get_stylesheet_directory() . '/';
		$child_theme_uri = 'dir' === $method ? $child_theme_dir : ( get_stylesheet_directory_uri() . '/' );
		$template =  "{$plugin_uri}templates/{$theme}/content-cards.{$extension}";
		if ( file_exists( "{$theme_dir}content-cards.{$extension}" ) ) {
			$template = "{$theme_uri}content-cards.{$extension}";
		}
		if ( file_exists( "{$child_theme_dir}content-cards.{$extension}" ) ) {
			$template = "{$child_theme_uri}content-cards.{$extension}";
		}
		if ( file_exists( "{$plugin_dir}templates/{$theme}/content-cards-{$type}.{$extension}" ) ) {
			$template = "{$plugin_uri}templates/{$theme}/content-cards-{$type}.{$extension}";
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
	private static function get_template( $url, $type = 'website' ) {
		$template = self::_get_file( $url, 'php', $type, self::$options['skin'] );
		$template = apply_filters( 'content_cards_template', $template, $url );
		return $template;
	}
	private static function get_stylesheet( ) {
		$template = self::_get_file( false, 'css', '', self::$options['skin'], 'uri'  );
		$template = apply_filters( 'content_cards_stylesheet', $template );
		return $template;
	}
	public static function admin_init() {
		if ( self::$stylesheet ) {
			add_editor_style( self::$stylesheet );		
		}
	}
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
		return $skins;
	}
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
	public static function styles() {
		if ( self::$stylesheet ) {
			wp_register_style( 'content-cards', self::$stylesheet );
			wp_enqueue_style( 'content-cards' );
		}
	}
	public static function oembed( $matches, $attr, $url, $rawattr ) {
		$embed = self::build( $url );
		return apply_filters( 'embed_content_cards', $embed, $matches, $attr, $url, $rawattr );
	}
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
	private static function get_data( $url ) {
		$result = get_transient( 'og_oembed_'.md5( $url ) );
		if ( !$result ) {
			require_once( 'includes/opengraph.php' );
			$data = wp_remote_retrieve_body( wp_remote_get( $url ) );
			if ( $data ) {
				$graph = OpenGraph::parse( $data );
				$result = array();
				if ( sizeof( $graph ) > 0 ) {
					foreach ($graph as $key => $value) {
					    $result[$key] = $value;
					}				
				}
				if ( $result ) {
					set_transient( 'og_oembed_'.md5( $url ), $result, DAY_IN_SECONDS );
				}				
			}
		}
		return $result;
	}
} 

function get_cc_data( $key ) {
	return Content_Cards::$temp_data[$key];
}
function the_cc_data( $key ) {
	echo Content_Cards::$temp_data[$key];
}
function the_cc_target() {
	echo Content_Cards::$temp_data['target'] ? ' target="_blank"' : '';
}
