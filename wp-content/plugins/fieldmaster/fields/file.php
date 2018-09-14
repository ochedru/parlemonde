<?php

/*
*  FieldMaster File Field Class
*
*  All the logic for this field type
*
*  @class 		fieldmaster_field_file
*  @extends		fieldmaster_field
*  @package		FieldMaster
*  @subpackage	Fields
*/

if( ! class_exists('fieldmaster_field_file') ) :

class fieldmaster_field_file extends fieldmaster_field {
	
	
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
		$this->name = 'file';
		$this->label = __("File",'fieldmaster');
		$this->category = 'content';
		$this->defaults = array(
			'return_format'	=> 'array',
			'library' 		=> 'all',
			'min_size'		=> 0,
			'max_size'		=> 0,
			'mime_types'	=> ''
		);
		$this->l10n = array(
			'select'		=> __("Select File",'fieldmaster'),
			'edit'			=> __("Edit File",'fieldmaster'),
			'update'		=> __("Update File",'fieldmaster'),
			'uploadedTo'	=> __("Uploaded to this post",'fieldmaster'),
		);
		
		
		// filters
		add_filter('get_media_item_args',			array($this, 'get_media_item_args'));
		
		
		// do not delete!
    	parent::__construct();
    	
	}
	
	
	/*
	*  render_field()
	*
	*  Create the HTML interface for your field
	*
	*  @param	$field - an array holding all the field's data
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*/
	
	function render_field( $field ) {
		
		// vars
		$uploader = fieldmaster_get_setting('uploader');
		
		
		// enqueue
		if( $uploader == 'wp' ) {
			
			fieldmaster_enqueue_uploader();
			
		}
		
		
		// vars
		$o = array(
			'icon'		=> '',
			'title'		=> '',
			'url'		=> '',
			'filesize'	=> '',
			'filename'	=> '',
		);
		
		$div = array(
			'class'				=> 'fieldmaster-file-uploader fieldmaster-cf',
			'data-library' 		=> $field['library'],
			'data-mime_types'	=> $field['mime_types'],
			'data-uploader'		=> $uploader
		);
		
		
		// has value?
		if( $field['value'] ) {
			
			$file = get_post( $field['value'] );
			
			if( $file ) {
				
				$o['icon'] = wp_mime_type_icon( $file->ID );
				$o['title']	= $file->post_title;
				$o['filesize'] = @size_format(filesize( get_attached_file( $file->ID ) ));
				$o['url'] = wp_get_attachment_url( $file->ID );
				
				$explode = explode('/', $o['url']);
				$o['filename'] = end( $explode );	
							
			}
			
			
			// url exists
			if( $o['url'] ) {
				
				$div['class'] .= ' has-value';
			
			}
						
		}
				
?>
<div <?php fieldmaster_esc_attr_e($div); ?>>
	<?php fieldmaster_hidden_input(array( 'name' => $field['name'], 'value' => $field['value'], 'data-name' => 'id' )); ?>
	<div class="show-if-value file-wrap fieldmaster-soh">
		<div class="file-icon">
			<img data-name="icon" src="<?php echo $o['icon']; ?>" alt=""/>
		</div>
		<div class="file-info">
			<p>
				<strong data-name="title"><?php echo $o['title']; ?></strong>
			</p>
			<p>
				<strong><?php _e('File name', 'fieldmaster'); ?>:</strong>
				<a data-name="filename" href="<?php echo $o['url']; ?>" target="_blank"><?php echo $o['filename']; ?></a>
			</p>
			<p>
				<strong><?php _e('File size', 'fieldmaster'); ?>:</strong>
				<span data-name="filesize"><?php echo $o['filesize']; ?></span>
			</p>
			
			<ul class="fieldmaster-hl fieldmaster-soh-target">
				<?php if( $uploader != 'basic' ): ?>
					<li><a class="fieldmaster-icon -pencil dark" data-name="edit" href="#"></a></li>
				<?php endif; ?>
				<li><a class="fieldmaster-icon -cancel dark" data-name="remove" href="#"></a></li>
			</ul>
		</div>
	</div>
	<div class="hide-if-value">
		<?php if( $uploader == 'basic' ): ?>
			
			<?php if( $field['value'] && !is_numeric($field['value']) ): ?>
				<div class="fieldmaster-error-message"><p><?php echo $field['value']; ?></p></div>
			<?php endif; ?>
			
			<label class="fieldmaster-basic-uploader">
				<input type="file" name="<?php echo $field['name']; ?>" id="<?php echo $field['id']; ?>" />
			</label>
			
		<?php else: ?>
			
			<p style="margin:0;"><?php _e('No file selected','fieldmaster'); ?> <a data-name="add" class="fieldmaster-button button" href="#"><?php _e('Add File','fieldmaster'); ?></a></p>
			
		<?php endif; ?>
		
	</div>
</div>
<?php
		
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
		
		// clear numeric settings
		$clear = array(
			'min_size',
			'max_size'
		);
		
		foreach( $clear as $k ) {
			
			if( empty($field[$k]) ) {
				
				$field[$k] = '';
				
			}
			
		}
		
		
		// return_format
		fieldmaster_render_field_setting( $field, array(
			'label'			=> __('Return Value','fieldmaster'),
			'instructions'	=> __('Specify the returned value on front end','fieldmaster'),
			'type'			=> 'radio',
			'name'			=> 'return_format',
			'layout'		=> 'horizontal',
			'choices'		=> array(
				'array'			=> __("File Array",'fieldmaster'),
				'url'			=> __("File URL",'fieldmaster'),
				'id'			=> __("File ID",'fieldmaster')
			)
		));
		
		
		// library
		fieldmaster_render_field_setting( $field, array(
			'label'			=> __('Library','fieldmaster'),
			'instructions'	=> __('Limit the media library choice','fieldmaster'),
			'type'			=> 'radio',
			'name'			=> 'library',
			'layout'		=> 'horizontal',
			'choices' 		=> array(
				'all'			=> __('All', 'fieldmaster'),
				'uploadedTo'	=> __('Uploaded to post', 'fieldmaster')
			)
		));
		
		
		// min
		fieldmaster_render_field_setting( $field, array(
			'label'			=> __('Minimum','fieldmaster'),
			'instructions'	=> __('Restrict which files can be uploaded','fieldmaster'),
			'type'			=> 'text',
			'name'			=> 'min_size',
			'prepend'		=> __('File size', 'fieldmaster'),
			'append'		=> 'MB',
		));
		
		
		// max
		fieldmaster_render_field_setting( $field, array(
			'label'			=> __('Maximum','fieldmaster'),
			'instructions'	=> __('Restrict which files can be uploaded','fieldmaster'),
			'type'			=> 'text',
			'name'			=> 'max_size',
			'prepend'		=> __('File size', 'fieldmaster'),
			'append'		=> 'MB',
		));
		
		
		// allowed type
		fieldmaster_render_field_setting( $field, array(
			'label'			=> __('Allowed file types','fieldmaster'),
			'instructions'	=> __('Comma separated list. Leave blank for all types','fieldmaster'),
			'type'			=> 'text',
			'name'			=> 'mime_types',
		));
		
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
		
		
		// bail early if not numeric (error message)
		if( !is_numeric($value) ) return false;
		
		
		// convert to int
		$value = intval($value);
		
		
		// format
		if( $field['return_format'] == 'url' ) {
		
			return wp_get_attachment_url($value);
			
		} elseif( $field['return_format'] == 'array' ) {
			
			return fieldmaster_get_attachment( $value );
		}
		
		
		// return
		return $value;
	}
	
	
	/*
	*  get_media_item_args
	*
	*  description
	*
	*  @type	function
	*  @date	27/01/13
	*  @since	3.6.0
	*
	*  @param	$vars (array)
	*  @return	$vars
	*/
	
	function get_media_item_args( $vars ) {
	
	    $vars['send'] = true;
	    return($vars);
	    
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
	*  @param	$post_id - the $post_id of which the value will be saved
	*  @param	$field - the field array holding all the field options
	*
	*  @return	$value - the modified value
	*/
	
	function update_value( $value, $post_id, $field ) {
		
		// bail early if is empty
		if( empty($value) ) return false;
		
		
		// validate
		if( is_array($value) && isset($value['ID']) ) { 
			
			$value = $value['ID'];
			
		} elseif( is_object($value) && isset($value->ID) ) { 
			
			$value = $value->ID;
			
		}
		
		
		// bail early if not attachment ID
		if( !$value || !is_numeric($value) ) return false;
		
		
		// confirm type
		$value = (int) $value;
		
		
		// maybe connect attacment to post 
		fieldmaster_connect_attachment_to_post( $value, $post_id );
		
		
		// return
		return $value;
		
	}
		
	
	
	/*
	*  validate_value
	*
	*  This function will validate a basic file input
	*
	*  @type	function
	*  @date	11/02/2014
	*  @since	5.0.0
	*
	*  @param	$post_id (int)
	*  @return	$post_id (int)
	*/
	
	function validate_value( $valid, $value, $field, $input ){
		
		// bail early if empty		
		if( empty($value) ) return $valid;
		
		
		// bail ealry if is numeric
		if( is_numeric($value) ) return $valid;
		
		
		// bail ealry if not basic string
		if( !is_string($value) ) return $valid;
		
		
		// decode value
		$file = null;
		parse_str($value, $file);
		
		
		// bail early if no attachment
		if( empty($file) ) return $valid;
		
		
		// get errors
		$errors = fieldmaster_validate_attachment( $file, $field, 'basic_upload' );
		
		
		// append error
		if( !empty($errors) ) {
			
			$valid = implode("\n", $errors);
			
		}
		
		
		// return		
		return $valid;
		
	}
	
}


// initialize
fieldmaster_register_field_type( new fieldmaster_field_file() );

endif; // class_exists check

?>