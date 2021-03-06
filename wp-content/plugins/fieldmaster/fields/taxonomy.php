<?php

/*
*  FieldMaster Taxonomy Field Class
*
*  All the logic for this field type
*
*  @class 		fieldmaster_field_tab
*  @extends		fieldmaster_field
*  @package		FieldMaster
*  @subpackage	Fields
*/

if( ! class_exists('fieldmaster_field_taxonomy') ) :

class fieldmaster_field_taxonomy extends fieldmaster_field {
	
	
	/*
	*  __construct
	*
	*  This function will setup the field type data
	*
	*  @type	function
	*  @date	5/03/2014
	*  @since	5.0.0
	*
	*  @param	n/a
	*  @return	n/a
	*/
	
	function __construct() {
		
		// vars
		$this->name = 'taxonomy';
		$this->label = __("Taxonomy",'fieldmaster');
		$this->category = 'relational';
		$this->defaults = array(
			'taxonomy' 			=> 'category',
			'field_type' 		=> 'checkbox',
			'multiple'			=> 0,
			'allow_null' 		=> 0,
			//'load_save_terms' 	=> 0, // removed in 5.2.7
			'return_format'		=> 'id',
			'add_term'			=> 1, // 5.2.3
			'load_terms'		=> 0, // 5.2.7	
			'save_terms'		=> 0 // 5.2.7
		);
		
		
		// ajax
		add_action('wp_ajax_fieldmaster/fields/taxonomy/query',			array($this, 'ajax_query'));
		add_action('wp_ajax_nopriv_fieldmaster/fields/taxonomy/query',	array($this, 'ajax_query'));
		add_action('wp_ajax_fieldmaster/fields/taxonomy/add_term',		array($this, 'ajax_add_term'));
		
		
		// custom set_terms
		$this->save_post_terms = array();
		add_action('fieldmaster/save_post', array($this, 'save_post'), 15, 1);
		
		
		// do not delete!
    	parent::__construct();
    	
	}
	
	
	/*
	*  ajax_query
	*
	*  description
	*
	*  @type	function
	*  @date	24/10/13
	*  @since	5.0.0
	*
	*  @param	$post_id (int)
	*  @return	$post_id (int)
	*/
	
	function ajax_query() {
		
		// validate
		if( !fieldmaster_verify_ajax() ) die();
		
		
		// get choices
		$response = $this->get_ajax_query( $_POST );
		
		
		// return
		fieldmaster_send_ajax_results($response);
			
	}
	
	
	/*
	*  get_ajax_query
	*
	*  This function will return an array of data formatted for use in a select2 AJAX response
	*
	*  @type	function
	*  @date	15/10/2014
	*  @since	5.0.9
	*
	*  @param	$options (array)
	*  @return	(array)
	*/
	
	function get_ajax_query( $options = array() ) {
		
   		// defaults
   		$options = fieldmaster_parse_args($options, array(
			'post_id'		=> 0,
			's'				=> '',
			'field_key'		=> '',
			'paged'			=> 0
		));
		
		
		// load field
		$field = fieldmaster_get_field( $options['field_key'] );
		if( !$field ) return false;
		
		
		// bail early if taxonomy does not exist
		if( !taxonomy_exists($field['taxonomy']) ) return false;
		
		
		// vars
   		$results = array();
		$is_hierarchical = is_taxonomy_hierarchical( $field['taxonomy'] );
		$is_pagination = ($options['paged'] > 0);
		$is_search = false;
		$limit = 20;
		$offset = 20 * ($options['paged'] - 1);
		
		
		// args
		$args = array(
			'taxonomy'		=> $field['taxonomy'],
			'hide_empty'	=> false
		);
		
		
		// pagination
		// - don't bother for hierarchial terms, we will need to load all terms anyway
		if( $is_pagination && !$is_hierarchical ) {
			
			$args['number'] = $limit;
			$args['offset'] = $offset;
		
		}
		
		
		// search
		if( $options['s'] !== '' ) {
			
			// strip slashes (search may be integer)
			$s = wp_unslash( strval($options['s']) );
			
			
			// update vars
			$args['search'] = $s;
			$is_search = true;
			
		}
		
		
		// filters
		$args = apply_filters('fieldmaster/fields/taxonomy/query', $args, $field, $options['post_id']);
		$args = apply_filters('fieldmaster/fields/taxonomy/query/name=' . $field['name'], $args, $field, $options['post_id'] );
		$args = apply_filters('fieldmaster/fields/taxonomy/query/key=' . $field['key'], $args, $field, $options['post_id'] );
		
		
		// get terms
		$terms = fieldmaster_get_terms( $args );
		
		
		// sort into hierachial order!
		if( $is_hierarchical ) {
			
			// update vars
			$limit = fieldmaster_maybe_get( $args, 'number', $limit );
			$offset = fieldmaster_maybe_get( $args, 'offset', $offset );
			
			
			// get parent
			$parent = fieldmaster_maybe_get( $args, 'parent', 0 );
			$parent = fieldmaster_maybe_get( $args, 'child_of', $parent );
			
			
			// this will fail if a search has taken place because parents wont exist
			if( !$is_search ) {
				
				// order terms
				$ordered_terms = _get_term_children( $parent, $terms, $field['taxonomy'] );
				
				
				// check for empty array (possible if parent did not exist within original data)
				if( !empty($ordered_terms) ) {
					
					$terms = $ordered_terms;
					
				}
			}
			
			
			// fake pagination
			if( $is_pagination ) {
				
				$terms = array_slice($terms, $offset, $limit);
				
			}
			
		}
		
		
		/// append to r
		foreach( $terms as $term ) {
		
			// add to json
			$results[] = array(
				'id'	=> $term->term_id,
				'text'	=> $this->get_term_title( $term, $field, $options['post_id'] )
			);
			
		}
		
		
		// vars
		$response = array(
			'results'	=> $results,
			'limit'		=> $limit
		);
		
		
		// return
		return $response;
			
	}
	
	
	/*
	*  get_term_title
	*
	*  This function returns the HTML for a result
	*
	*  @type	function
	*  @date	1/11/2013
	*  @since	5.0.0
	*
	*  @param	$post (object)
	*  @param	$field (array)
	*  @param	$post_id (int) the post_id to which this value is saved to
	*  @return	(string)
	*/
	
	function get_term_title( $term, $field, $post_id = 0 ) {
		
		// get post_id
		if( !$post_id ) $post_id = fieldmaster_get_form_data('post_id');
		
		
		// vars
		$title = '';
		
		
		// ancestors
		$ancestors = get_ancestors( $term->term_id, $field['taxonomy'] );
		
		if( !empty($ancestors) ) {
		
			$title .= str_repeat('- ', count($ancestors));
			
		}
		
		
		// title
		$title .= $term->name;
				
		
		// filters
		$title = apply_filters('fieldmaster/fields/taxonomy/result', $title, $term, $field, $post_id);
		$title = apply_filters('fieldmaster/fields/taxonomy/result/name=' . $field['_name'] , $title, $term, $field, $post_id);
		$title = apply_filters('fieldmaster/fields/taxonomy/result/key=' . $field['key'], $title, $term, $field, $post_id);
		
		
		// return
		return $title;
	}
	
	
	/*
	*  get_terms
	*
	*  This function will return an array of terms for a given field value
	*
	*  @type	function
	*  @date	13/06/2014
	*  @since	5.0.0
	*
	*  @param	$value (array)
	*  @return	$value
	*/
	
	function get_terms( $value, $taxonomy = 'category' ) {
		
		// load terms in 1 query to save multiple DB calls from following code
		if( count($value) > 1 ) {
			
			$terms = fieldmaster_get_terms(array(
				'taxonomy'		=> $taxonomy,
				'include'		=> $value,
				'hide_empty'	=> false
			));
			
		}
		
		
		// update value to include $post
		foreach( array_keys($value) as $i ) {
			
			$value[ $i ] = get_term( $value[ $i ], $taxonomy );
			
		}
		
		
		// filter out null values
		$value = array_filter($value);
		
		
		// return
		return $value;
	}
	
	
	/*
	*  load_value()
	*
	*  This filter is appied to the $value after it is loaded from the db
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value - the value found in the database
	*  @param	$post_id - the $post_id from which the value was loaded from
	*  @param	$field - the field array holding all the field options
	*
	*  @return	$value - the value to be saved in te database
	*/
	
	function load_value( $value, $post_id, $field ) {
		
		// get valid terms
		$value = fieldmaster_get_valid_terms($value, $field['taxonomy']);
		
		
		// load_terms
		if( $field['load_terms'] ) {
			
			// get terms
			$info = fieldmaster_get_post_id_info($post_id);
			$term_ids = wp_get_object_terms($info['id'], $field['taxonomy'], array('fields' => 'ids', 'orderby' => 'none'));
			
			
			// bail early if no terms
			if( empty($term_ids) || is_wp_error($term_ids) ) return false;
			
			
			// sort
			if( !empty($value) ) {
				
				$order = array();
				
				foreach( $term_ids as $i => $v ) {
					
					$order[ $i ] = array_search($v, $value);
					
				}
				
				array_multisort($order, $term_ids);
				
			}
			
			
			// update value
			$value = $term_ids;
						
		}
		
		
		// convert back from array if neccessary
		if( $field['field_type'] == 'select' || $field['field_type'] == 'radio' ) {
			
			$value = array_shift($value);
			
		}
		
		
		// return
		return $value;
		
	}
	
	
	/*
	*  update_value()
	*
	*  This filter is appied to the $value before it is updated in the db
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value - the value which will be saved in the database
	*  @param	$field - the field array holding all the field options
	*  @param	$post_id - the $post_id of which the value will be saved
	*
	*  @return	$value - the modified value
	*/
	
	function update_value( $value, $post_id, $field ) {
		
		// vars
		if( is_array($value) ) {
		
			$value = array_filter($value);
			
		}
		
		
		// save_terms
		if( $field['save_terms'] ) {
			
			// vars
			$taxonomy = $field['taxonomy'];
			
			
			// force value to array
			$term_ids = fieldmaster_get_array( $value );
			
			
			// convert to int
			$term_ids = array_map('intval', $term_ids);
			
			
			// get existing term id's (from a previously saved field)
			$old_term_ids = isset($this->save_post_terms[ $taxonomy ]) ? $this->save_post_terms[ $taxonomy ] : array();
			
			
			// append
			$this->save_post_terms[ $taxonomy ] = array_merge($old_term_ids, $term_ids);
			
			
			// if called directly from frontend update_field()
			if( !did_action('fieldmaster/save_post') ) {
				
				$this->save_post( $post_id );
				
				return $value;
				
			}
			
		}
		
		
		// return
		return $value;
		
	}
	
	
	/*
	*  save_post
	*
	*  This function will save any terms in the save_post_terms array
	*
	*  @type	function
	*  @date	26/11/2014
	*  @since	5.0.9
	*
	*  @param	$post_id (int)
	*  @return	n/a
	*/
	
	function save_post( $post_id ) {
		
		// bail ealry if no terms
		if( empty($this->save_post_terms) ) return;
		
		
		// vars
		$info = fieldmaster_get_post_id_info($post_id);
		
		
		// loop
		foreach( $this->save_post_terms as $taxonomy => $term_ids ){
			
			// save
			wp_set_object_terms( $info['id'], $term_ids, $taxonomy, false );
			
		}
		
		
		// reset array ( WP saves twice )
		$this->save_post_terms = array();
		
	}
	
	
	/*
	*  format_value()
	*
	*  This filter is appied to the $value after it is loaded from the db and before it is returned to the template
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value (mixed) the value which was loaded from the database
	*  @param	$post_id (mixed) the $post_id from which the value was loaded
	*  @param	$field (array) the field array holding all the field options
	*
	*  @return	$value (mixed) the modified value
	*/
	
	function format_value( $value, $post_id, $field ) {
		
		// bail early if no value
		if( empty($value) ) return false;
		
		
		// force value to array
		$value = fieldmaster_get_array( $value );
		
		
		// load posts if needed
		if( $field['return_format'] == 'object' ) {
			
			// get posts
			$value = $this->get_terms( $value, $field["taxonomy"] );
		
		}
		
		
		// convert back from array if neccessary
		if( $field['field_type'] == 'select' || $field['field_type'] == 'radio' ) {
			
			$value = array_shift($value);
			
		}
		

		// return
		return $value;
		
	}
	
	
	/*
	*  render_field()
	*
	*  Create the HTML interface for your field
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field - an array holding all the field's data
	*/
	
	function render_field( $field ) {
		
		// force value to array
		$field['value'] = fieldmaster_get_array( $field['value'] );
		
		
		// vars
		$div = array(
			'class'				=> 'fieldmaster-taxonomy-field fieldmaster-soh',
			'data-save'			=> $field['save_terms'],
			'data-type'			=> $field['field_type'],
			'data-taxonomy'		=> $field['taxonomy']
		);
		
		
		// get taxonomy
		$taxonomy = get_taxonomy( $field['taxonomy'] );
		
		?>
<div <?php fieldmaster_esc_attr_e($div); ?>>
	<?php if( $field['add_term'] && current_user_can( $taxonomy->cap->manage_terms) ): ?>
	<a href="#" class="fieldmaster-icon -plus fieldmaster-js-tooltip small fieldmaster-soh-target" data-name="add" title="<?php echo esc_attr($taxonomy->labels->add_new_item); ?>"></a>
	<?php endif;

	if( $field['field_type'] == 'select' ) {
	
		$field['multiple'] = 0;
		
		$this->render_field_select( $field );
	
	} elseif( $field['field_type'] == 'multi_select' ) {
		
		$field['multiple'] = 1;
		
		$this->render_field_select( $field );
	
	} elseif( $field['field_type'] == 'radio' ) {
		
		$this->render_field_checkbox( $field );
		
	} elseif( $field['field_type'] == 'checkbox' ) {
	
		$this->render_field_checkbox( $field );
		
	}

	?>
</div><?php
		
	}
	
	
	/*
	*  render_field_select()
	*
	*  Create the HTML interface for your field
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field - an array holding all the field's data
	*/
	
	function render_field_select( $field ) {
		
		// Change Field into a select
		$field['type'] = 'select';
		$field['ui'] = 1;
		$field['ajax'] = 1;
		$field['choices'] = array();
		
		
		// value
		if( !empty($field['value']) ) {
			
			// get terms
			$terms = $this->get_terms( $field['value'], $field['taxonomy'] );
			
			
			// set choices
			if( !empty($terms) ) {
				
				foreach( array_keys($terms) as $i ) {
					
					// vars
					$term = fieldmaster_extract_var( $terms, $i );
					
					
					// append to choices
					$field['choices'][ $term->term_id ] = $this->get_term_title( $term, $field );
				
				}
				
			}
			
		}
		
		
		// render select		
		fieldmaster_render_field( $field );
		
	}
	
	
	/*
	*  render_field_checkbox()
	*
	*  Create the HTML interface for your field
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field - an array holding all the field's data
	*/
	
	function render_field_checkbox( $field ) {
		
		// hidden input
		fieldmaster_hidden_input(array(
			'type'	=> 'hidden',
			'name'	=> $field['name'],
		));
		
		
		// checkbox saves an array
		if( $field['field_type'] == 'checkbox' ) {
		
			$field['name'] .= '[]';
			
		}
		
		
		// taxonomy
		$taxonomy_obj = get_taxonomy($field['taxonomy']);
		
		
		// vars
		$args = array(
			'taxonomy'     		=> $field['taxonomy'],
			'show_option_none'	=> __('No', 'fieldmaster') . ' ' . $taxonomy_obj->labels->name,
			'hide_empty'   		=> false,
			'style'        		=> 'none',
			'walker'       		=> new fieldmaster_taxonomy_field_walker( $field ),
		);
		
		
		// filter for 3rd party customization
		$args = apply_filters('fieldmaster/fields/taxonomy/wp_list_categories', $args, $field);
		$args = apply_filters('fieldmaster/fields/taxonomy/wp_list_categories/name=' . $field['_name'], $args, $field);
		$args = apply_filters('fieldmaster/fields/taxonomy/wp_list_categories/key=' . $field['key'], $args, $field);
		
		?><div class="categorychecklist-holder">
		
			<ul class="fieldmaster-checkbox-list fieldmaster-bl">
			
				<?php if( $field['field_type'] == 'radio' && $field['allow_null'] ): ?>
					<li>
						<label class="selectit">
							<input type="radio" name="<?php echo $field['name']; ?>" value="" /> <?php _e("None", 'fieldmaster'); ?>
						</label>
					</li>
				<?php endif; ?>
				
				<?php wp_list_categories( $args ); ?>
		
			</ul>
			
		</div><?php
		
	}
	
	
	/*
	*  render_field_settings()
	*
	*  Create extra options for your field. This is rendered when editing a field.
	*  The value of $field['name'] can be used (like bellow) to save extra data to the $field
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field	- an array holding all the field's data
	*/
	
	function render_field_settings( $field ) {
		
		// default_value
		fieldmaster_render_field_setting( $field, array(
			'label'			=> __('Taxonomy','fieldmaster'),
			'instructions'	=> __('Select the taxonomy to be displayed','fieldmaster'),
			'type'			=> 'select',
			'name'			=> 'taxonomy',
			'choices'		=> fieldmaster_get_taxonomies(),
		));
		
		
		// field_type
		fieldmaster_render_field_setting( $field, array(
			'label'			=> __('Appearance','fieldmaster'),
			'instructions'	=> __('Select the appearance of this field','fieldmaster'),
			'type'			=> 'select',
			'name'			=> 'field_type',
			'optgroup'		=> true,
			'choices'		=> array(
				__("Multiple Values",'fieldmaster') => array(
					'checkbox' => __('Checkbox', 'fieldmaster'),
					'multi_select' => __('Multi Select', 'fieldmaster')
				),
				__("Single Value",'fieldmaster') => array(
					'radio' => __('Radio Buttons', 'fieldmaster'),
					'select' => _x('Select', 'noun', 'fieldmaster')
				)
			)
		));
		
		
		// allow_null
		fieldmaster_render_field_setting( $field, array(
			'label'			=> __('Allow Null?','fieldmaster'),
			'instructions'	=> '',
			'name'			=> 'allow_null',
			'type'			=> 'true_false',
			'ui'			=> 1,
		));
		
		
		// add_term
		fieldmaster_render_field_setting( $field, array(
			'label'			=> __('Create Terms','fieldmaster'),
			'instructions'	=> __('Allow new terms to be created whilst editing','fieldmaster'),
			'name'			=> 'add_term',
			'type'			=> 'true_false',
			'ui'			=> 1,
		));
		
		
		// save_terms
		fieldmaster_render_field_setting( $field, array(
			'label'			=> __('Save Terms','fieldmaster'),
			'instructions'	=> __('Connect selected terms to the post','fieldmaster'),
			'name'			=> 'save_terms',
			'type'			=> 'true_false',
			'ui'			=> 1,
		));
		
		
		// load_terms
		fieldmaster_render_field_setting( $field, array(
			'label'			=> __('Load Terms','fieldmaster'),
			'instructions'	=> __('Load value from posts terms','fieldmaster'),
			'name'			=> 'load_terms',
			'type'			=> 'true_false',
			'ui'			=> 1,
		));
		
		
		// return_format
		fieldmaster_render_field_setting( $field, array(
			'label'			=> __('Return Value','fieldmaster'),
			'instructions'	=> '',
			'type'			=> 'radio',
			'name'			=> 'return_format',
			'choices'		=> array(
				'object'		=>	__("Term Object",'fieldmaster'),
				'id'			=>	__("Term ID",'fieldmaster')
			),
			'layout'	=>	'horizontal',
		));
		
	}
	
	
	/*
	*  ajax_add_term
	*
	*  description
	*
	*  @type	function
	*  @date	17/04/2015
	*  @since	5.2.3
	*
	*  @param	$post_id (int)
	*  @return	$post_id (int)
	*/
	
	function ajax_add_term() {
		
		// vars
		$args = fieldmaster_parse_args($_POST, array(
			'nonce'				=> '',
			'field_key'			=> '',
			'term_name'			=> '',
			'term_parent'		=> ''
		));
		
		
		// verify nonce
		if( ! wp_verify_nonce($args['nonce'], 'fieldmaster_nonce') ) {
		
			die();
			
		}
		
		
		// load field
		$field = fieldmaster_get_field( $args['field_key'] );
		
		if( !$field ) {
		
			die();
			
		}
		
		
		// vars
		$taxonomy_obj = get_taxonomy($field['taxonomy']);
		$taxonomy_label = $taxonomy_obj->labels->singular_name;
			
			
		// validate cap
		// note: this situation should never occur due to condition of the add new button
		if( !current_user_can( $taxonomy_obj->cap->manage_terms) ) {
			
			echo '<p><strong>' . __("Error.", 'fieldmaster') . '</strong> ' . sprintf( __('User unable to add new %s', 'fieldmaster'), $taxonomy_label ) . '</p>';
			die;
			
		}
	
		
		// save?
		if( $args['term_name'] ) {
			
			// exists
			if( term_exists($args['term_name'], $field['taxonomy']) ) {
				
				wp_send_json_error(array(
					'error'	=> sprintf( __('%s already exists', 'fieldmaster'), $taxonomy_label )
				));
			
			}
			
			
			// insert
			$extra = array();
			
			if( $args['term_parent'] ) {
				
				$extra['parent'] = $args['term_parent'];
				
			}
			
			$data = wp_insert_term( $args['term_name'], $field['taxonomy'], $extra );
			
			
			// error?
			if( is_wp_error($data) ) {
				
				wp_send_json_error(array(
					'error'	=> $data->get_error_message()
				));
			
			}
			
			
			// ancestors
			$prefix = '';
			$ancestors = get_ancestors( $data['term_id'], $field['taxonomy'] );
			
			if( !empty($ancestors) ) {
			
				$prefix = str_repeat('- ', count($ancestors));
				
			}
		
		
			// success
			wp_send_json_success(array(
				'message'		=> sprintf( __('%s added', 'fieldmaster'), $taxonomy_label ),
				'term_id'		=> $data['term_id'],
				'term_name'		=> $args['term_name'],
				'term_label'	=> $prefix . $args['term_name'],
				'term_parent'	=> $args['term_parent']
			));
				
		}
		
		?><form method="post"><?php
		
		fieldmaster_render_field_wrap(array(
			'label'			=> __('Name', 'fieldmaster'),
			'name'			=> 'term_name',
			'type'			=> 'text'
		));
		
		
		if( is_taxonomy_hierarchical( $field['taxonomy'] ) ) {
			
			$choices = array();
			$response = $this->get_ajax_query($args);
			
			if( $response ) {
				
				foreach( $response['results'] as $v ) { 
					
					$choices[ $v['id'] ] = $v['text'];
					
				}
				
			}
			
			fieldmaster_render_field_wrap(array(
				'label'			=> __('Parent', 'fieldmaster'),
				'name'			=> 'term_parent',
				'type'			=> 'select',
				'allow_null'	=> 1,
				'ui'			=> 0,
				'choices'		=> $choices
			));
			
		}
		
		
		?><p class="fieldmaster-submit"><button class="fieldmaster-button button button-primary" type="submit"><?php _e("Add", 'fieldmaster'); ?></button><i class="fieldmaster-spinner"></i><span></span></p></form><?php
		
		
		// die
		die;	
		
	}
	
		
}


// initialize
fieldmaster_register_field_type( new fieldmaster_field_taxonomy() );

endif; // class_exists check



if( ! class_exists('fieldmaster_taxonomy_field_walker') ) :

class fieldmaster_taxonomy_field_walker extends Walker {
	
	var $field = null,
		$tree_type = 'category',
		$db_fields = array ( 'parent' => 'parent', 'id' => 'term_id' );
	
	function __construct( $field ) {
	
		$this->field = $field;
		
	}

	function start_el( &$output, $term, $depth = 0, $args = array(), $current_object_id = 0) {
		
		// vars
		$selected = in_array( $term->term_id, $this->field['value'] );
		
		
		// append
		$output .= '<li data-id="' . $term->term_id . '"><label><input type="' . $this->field['field_type'] . '" name="' . $this->field['name'] . '" value="' . $term->term_id . '" ' . ($selected ? 'checked="checked"' : '') . ' /> <span>' . $term->name . '</span></label>';
				
	}
	
	function end_el( &$output, $term, $depth = 0, $args = array() ) {
	
		// append
		$output .= '</li>' .  "\n";
		
	}
	
	function start_lvl( &$output, $depth = 0, $args = array() ) {
		
		// append
		$output .= '<ul class="children fieldmaster-bl">' . "\n";
		
	}

	function end_lvl( &$output, $depth = 0, $args = array() ) {
	
		// append
		$output .= '</ul>' . "\n";
		
	}
	
}

endif;

?>