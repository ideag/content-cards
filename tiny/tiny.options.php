<?php
	// tinyOptions v0.4.0

	if ( ! class_exists( 'tinyOptions' ) ) {
		class tinyOptions {
			public $settings = false;
			public $parent = false;

			public function __construct( $settings, $parent ) {
				$this->settings = $settings;
				$this->parent = $parent;
				add_action( 'admin_enqueue_scripts',	array( $this, 'script' ) );
				add_action( 'admin_menu', 						array( $this, 'init_page' ) );
				add_action( 'admin_menu', 						array( $this, 'init_fields' ) );
			}

			public function script() {
				wp_register_script( 'tiny-options', plugins_url( 'tiny.options.js', __FILE__ ) , array( 'jquery', 'media-upload', 'thickbox' ) );
			}
			public function init_page() {
				$defaults = array(
					'title'			 => '',
					'menu_title' => '',
					'description'=> '',
					'role'			 => 'manage_options',
					'slug'			 => __FILE__,
					'callback'	 => array( $this, 'page' ),
				);
				$this->settings['page'] = wp_parse_args( $this->settings['page'], $defaults );
				if( isset( $this->settings['page']['parent'] ) ) {
					add_submenu_page(
						$this->settings['page']['parent'],
						$this->settings['page']['title'],
						$this->settings['page']['menu_title'],
						$this->settings['page']['role'],
						$this->settings['page']['slug'],
						$this->settings['page']['callback']
					);
				} else {
					add_options_page(
						$this->settings['page']['title'],
						$this->settings['page']['menu_title'],
						$this->settings['page']['role'],
						$this->settings['page']['slug'],
						$this->settings['page']['callback']
					);
				}
			}
			public function init_fields(){
				register_setting(
					$this->settings['page']['slug'],
					$this->settings['page']['option'],
					array( $this , 'sanitize' )
				);
				if ( !isset( $this->settings['sections'] ) ) {
					$this->settings['sections'] = array();
				}
				if ( isset( $this->settings['fields']) && is_array( $this->settings['fields'] ) ) {
					// echo 'fields';
					$group = array(
						'title'	=> false,//__( 'General Settings', 'tinytemplate' ),
						'description'	=> false,//__( 'General Settings description', 'tinytemplate' ),
						'fields'	=> $this->settings['fields'],
					);
					$this->settings['sections']['general'] = $group;
				}
				foreach ( $this->settings['sections'] as $section_id => $section ) {
					$this->section( $section_id, $section );
				}
			}

			public function page(){
				if ( !current_user_can( $this->settings['page']['role'] ) ) {
		        wp_die( $this->settings['l10n']['no_access'] );
		    }
			  ?>
			    <div class="wrap">
			      <div class="icon32" id="icon-page"><br></div>
			      <h2><?php echo $this->settings['page']['title']; ?></h2>
						<?php echo wpautop( $this->settings['page']['description'] ); ?>
			      <form action="options.php" method="post">
				      <?php settings_fields( $this->settings['page']['slug'] ); ?>
				      <?php do_settings_sections( $this->settings['page']['slug'] ); ?>
							<?php submit_button( $this->settings['l10n']['save_changes'] ); ?>
			      </form>
			    </div>
			  <?php
			}

			public function section( $group_id, $group ) {
				add_settings_section(
					$group_id,
					$group['title'],
					array( $this, 'section_description' ),
					$this->settings['page']['slug']
				);
				foreach ( $group['fields'] as $field_id => $field ) {
					$field['args']['option_id'] = $field_id;
					$field['args']['title'] = $field['title'];
					$field['args']['description'] = isset( $field['description'] ) ? $field['description'] : false;
					$field['args']['label'] = isset( $field['label'] ) ? $field['label'] : false;
					$field['args']['list'] = isset( $field['list'] ) ? $field['list'] : false;
					$field['args']['attributes'] = isset( $field['attributes'] ) ? $field['attributes'] : array();
					$field['args']['button_attributes'] = isset( $field['button_attributes'] ) ? $field['button_attributes'] : array();
					if ( !isset( $field['callback'] ) ) {
						$field['callback'] = 'text';
					}
					if ( !is_callable( $field['callback'] ) ) {
						$field['callback'] = array( $this, $field['callback'] );
					}
					if ( !is_callable( $field['callback'] ) ) {
						$field['callback'] = array( $this, 'input' );
					}
					add_settings_field(
						$field_id,
						$field['title'],
						$field['callback'],
						$this->settings['page']['slug'],
						$group_id,
						$field['args']
					);
				}
			}
			public function section_description( $args ){
				if ( isset( $this->settings['sections'][ $args['id'] ]['description'] ) ) {
					echo wpautop( $this->settings['sections'][ $args['id'] ]['description'] );
				}
			}

			public function sanitize( $new_values ) {
				// filter for unchecked checkboxes
				foreach ( $this->settings['sections'] as $section_id => $section ) {
					foreach( $section['fields'] as $field_id => $field ) {
						$new_values[ $field_id ] = apply_filters(  $this->settings['page']['option']."_sanitize_{$field_id}", $new_values[ $field_id ], $field );
						if ( !isset( $field['callback'] ) ) {
							continue;
						}
						if ( 'checkbox' != $field['callback'] ) {
							continue;
						}
						if ( isset( $new_values[ $field_id ] ) ) {
							continue;
						}
						$new_values[ $field_id ] = false;
					}
				}
				$new_values = apply_filters( $this->settings['page']['option']."_sanitize", $new_values );
				return $new_values;
			}

			// input fields
			public function input( $args ) {
				$defaults = array(
					'attributes'	=> array(),
				);
				$args = wp_parse_args( $args, $defaults );
				$tag_args = wp_parse_args(
					$args['attributes'],
					array(
						'id'	=> $args['option_id'],
						'name' => $this->settings['page']['option']."[{$args['option_id']}]",
						'type'	=> 'text',
						'value'	=> $this->_get_value( $args['option_id'] ),
						'class'	=> 'regular-text',
					)
				);
				$tag_args = $this->_print_attributes( $tag_args );
				echo "<input {$tag_args}/>";
				echo $args['description'] ? "<p class=\"description\">{$args['description']}</p>": '';
		  }
			// checkbox fields
			public function checkbox( $args ) {
				$defaults = array(
					'attributes'	=> array(),
				);
				$args = wp_parse_args( $args, $defaults );

				$tag_args = wp_parse_args(
					$args['attributes'],
					array(
						'id'	=> $args['option_id'],
						'name' => $this->settings['page']['option']."[{$args['option_id']}]",
						'type'	=> 'checkbox',
						'value'	=> $this->_get_value( $args['option_id'] ),
						// 'value'	=> isset( ($this->parent)::$options[ $args['option_id'] ] ) ? ($this->parent)::$options[ $args['option_id'] ] : false,
						// 'class'	=> 'regular-text',
					)
				);
				// $checked = checked( $tag_args['value'], true, false );
				if (  $tag_args['value'] ) {
					$tag_args['checked'] =  'checked';
				}
				$tag_args['value'] =  true;
				// var_dump(($this->parent)::$options);
				$tag_args_flat = $this->_print_attributes( $tag_args );
				echo "<input {$tag_args_flat}/>";
				echo $args['label'] ? "<label for=\"{$tag_args['id']}\">{$args['label']}</label> ": '';
				echo $args['description'] ? "<p class=\"description\">{$args['description']}</p>": '';
		  }
			// list fields
			public function listfield( $args ) {
				$defaults = array(
					'attributes'	=> array(),
				);
				$args = wp_parse_args( $args, $defaults );

				$tag_args = wp_parse_args(
					$args['attributes'],
					array(
						'id'	=> $args['option_id'],
						'name' => $this->settings['page']['option']."[{$args['option_id']}]",
						'type'	=> 'select',
						// 'class'	=> 'regular-text',
					)
				);
				$type = $tag_args['type'];
				unset( $tag_args['type'] );
				$value = $this->_get_value( $args['option_id'] );
				$list = $args['list'];
				switch( $type ) {
					case 'select' :
						if ( isset( $tag_args['multiple'] ) && $tag_args['multiple'] ) {
							$tag_args['name'] .= '[]';
						}

						$tag_args_flat = $this->_print_attributes( $tag_args );
						echo "<select {$tag_args_flat}>";
					  foreach($list as $key=>$label) {
							if ( isset( $tag_args['multiple'] ) && $tag_args['multiple'] ) {
								if ( !is_array( $value ) ) {
									$value = array();
								}
								$selected = selected( in_array( $key, $value), true, false );
							} else {
								$selected = selected( $key, $value, false );
							}
							$key = esc_attr($key);
			        echo "<option value='{$key}' $selected>$label</option>";
						}
						echo "</select>";
					break;
					case 'radio' :
						$tag_args['type'] = 'radio';
					  foreach($list as $key=>$label) {
			        $checked = checked( $key, $value, false );
							$tag_args['value'] = $key;
							$tag_args['id']	= $args['option_id'].'_'.$key;
							$tag_args_flat = $this->_print_attributes( $tag_args );
				      echo "<input {$checked} {$tag_args_flat}/><label for=\"{$tag_args['id']}\">{$label}</label><br />";
						}
					break;
					case 'checkbox' :
						$tag_args['type'] = 'checkbox';
						$tag_args['name'] .= '[]';
						if ( !$value ) {
							 $value = array();
						}
					  foreach($list as $key=>$label) {
			        $checked = checked( in_array( $key, $value), true, false );
							$tag_args['value'] = $key;
							$tag_args['id']	= $args['option_id'].'_'.$key;
							$tag_args_flat = $this->_print_attributes( $tag_args );
				      echo "<input {$checked} {$tag_args_flat}/><label for=\"{$tag_args['id']}\">{$label}</label><br />";
						}
					break;
				}
				echo $args['description'] ? "<p class=\"description\">{$args['description']}</p>": '';
		  }
			// textarea fields
			public function textarea( $args ) {
				$defaults = array(
					'attributes'	=> array(),
				);
				$args = wp_parse_args( $args, $defaults );
				$tag_args = wp_parse_args(
					$args['attributes'],
					array(
						'id'	=> $args['option_id'],
						'name' => $this->settings['page']['option']."[{$args['option_id']}]",
						'class'	=> 'regular-text',
					)
				);
				$value = $this->_get_value( $args['option_id'] );
				$value = esc_textarea( $value );
				$tag_args = $this->_print_attributes( $tag_args );
				echo "<textarea {$tag_args}>{$value}</textarea>";
				echo $args['description'] ? "<p class=\"description\">{$args['description']}</p>": '';
		  }
			// link fields
			public function url( $args ) {
				$defaults = array(
					'attributes'	=> array(),
				);
				$args = wp_parse_args( $args, $defaults );
				$tag_args = wp_parse_args(
					$args['attributes'],
					array(
						'id'	=> $args['option_id'],
					)
				);
				$tag_args = $this->_print_attributes( $tag_args );
				echo "<a {$tag_args}>{$args['label']}</a>";
				echo $args['description'] ? "<p class=\"description\">{$args['description']}</p>": '';
		  }
			// input fields
			public function upload( $args ) {
				$defaults = array(
					'attributes'				=> array(),
					'button_attributes' => array(),
				);
				$args = wp_parse_args( $args, $defaults );
				$tag_args = wp_parse_args(
					$args['attributes'],
					array(
						'id'	=> $args['option_id'],
						'name' => $this->settings['page']['option']."[{$args['option_id']}]",
						'type'	=> 'text',
						'value'	=> $this->_get_value( $args['option_id'] ),
						'class'	=> 'regular-text',
					)
				);
				$button_args = wp_parse_args(
					$args['button_attributes'],
					array(
						'id'	  										=> $args['option_id'] . '_button',
						'type'											=> 'button',
						'class'											=> 'button button_upload',
						'value' 										=> $this->settings['l10n']['upload_button'],
						'data-uploader_button_text' => $this->settings['l10n']['upload_button'],
						'data-uploader_title' 			=> $this->settings['l10n']['upload'],
						'data-target' 							=> $args['option_id'],
					)
				);

				$tag_args = $this->_print_attributes( $tag_args );
				$button_args = $this->_print_attributes( $button_args );

				echo "<input {$tag_args}/>";
				echo '<br/>';
				echo "<input {$button_args}/>";
				echo $args['description'] ? "<p class=\"description\">{$args['description']}</p>": '';

		    wp_enqueue_media();
        wp_enqueue_script( 'tiny-options' );
		  }



			private function _print_attributes( $attributes ) {
				foreach( $attributes as $key => $value ) {
					$attributes[ $key ] = $key. '="' . esc_attr( $value ). '"';
				}
				$attributes = implode( ' ', $attributes );
				return $attributes;
			}

			// HELPERS

			public static function _get_post_types( $args = array() ) {
				$defaults = array(
					'public'   => true,
					'except_media'	=> true,
				);
				$args = wp_parse_args( $args, $defaults );
				$except_media = false;
				if ( $args['except_media'] ) {
					$except_media = true;
					unset( $args['except_media'] );
				}
				$post_types = get_post_types( $args, 'object' );
				$result = array();
				if ( $except_media ) {
					unset( $post_types['attachment'] );
				}
				foreach( $post_types as $post_type ) {
					$result[ $post_type->name ] = $post_type->label;
				}
				return $result;
			}

			private function _get_value( $key ) {
				$value = $this->parent;
				$value = $value::$options;
				$value = isset( $value[ $key ] ) ? $value[ $key ] : false;
				return $value;
			}

		}

	}
