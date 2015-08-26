<?php
/*
Plugin Name: Content Cards
Description: Pull OpenGraph data from other websites and show them as "Cards"
Version: 0.1.0
Author: Arūnas Liuiza
Author URI: http://arunas.co
License: GPL2
*/

add_action( 'plugins_loaded', array( 'Content_Cards', 'init' ) );
class Content_Cards {
	public static $options = array(
		'patterns' => "wptavern.com\r\nwordpress.org",
		'skin' => 'default',
	);
	private static $stylesheet = '';
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
		$template = file_get_contents( $template );
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
							'values' => array('default','fancy'),
							'description' => __( 'Can be overwritten by theme.', 'content-cards' ),
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
		$result = self::build( $args['url'] );
		return $result;
	}
	public static function build( $url ) {
		$data = self::get_data( $url );
		$result = "";
		if ( !$data ) {
			return $result;
		}
		$data['description'] = wpautop($data['description']);
		$data['url'] = $url;
		if ( isset($data['image']) ) {
			$data['image'] = "<img src=\"{$data['image']}\" alt=\"\"/>";
		} else {
			$data['image'] = "";
		}
		$type = isset( $data['type'] ) ? $data['type'] : 'website';
		foreach( $data as $key => $value ) {
			unset( $data[$key] );
			$data["%%{$key}%%"] = $value;
		}
		$template = self::get_template( $url, $type );;

		$result = str_replace( 
			array_keys($data), 
			array_values($data), 
			$template 
		);
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