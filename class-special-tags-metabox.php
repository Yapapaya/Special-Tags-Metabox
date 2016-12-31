<?php

/**
 * Creates a metabox to store and display some tags in post meta
 *
 * @author saurabhshukla
 */
class Special_Tags_Metabox {

	/**
	 * The metabox ID for html attributes
	 * 
	 * @var string 
	 */
	public $ID = '';

	/**
	 * The metabox title
	 * 
	 * @var string 
	 */
	public $title = '';

	/**
	 * The textarea label
	 * 
	 * @var string
	 */
	public $label = '';

	/**
	 * 
	 * @param type $args
	 * @return type
	 */
	function __construct( $args ) {
		if ( empty( $args[ 'ID' ] ) )
			return;
		$this->ID = $args[ 'ID' ];
		$this->title = $args[ 'title' ] ? $args[ 'title' ] : $args[ 'ID' ];
		$this->label = $this->ID . "-textarea";
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_post' ) );
	}

	/**
	 * 
	 */
	function add_meta_box() {

		add_meta_box( 'tagsdiv-' . $this->ID, $this->title, array( $this, 'meta_box' ), null, 'side', 'core' );
	}

	/**
	 * Saves labels as tags & labels on post save
	 * 
	 * @param int $post_id
	 * @return
	 */
	function save_post( $post_id ) {

		// don't do autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		// get the lable tags from textarea
		$label_string = isset( $_REQUEST[ $this->label ] ) ? $_REQUEST[ $this->label ] : '';

		$taxonomy = "post_tag";

		$tags = $label_string;

		// convert label string into array of tag names
		$tag_array = explode( ',', trim( $tags, " \n\t\r\0\x0B," ) );

		// get tax object
		$taxonomy_obj = get_taxonomy( $taxonomy );

		// if the user has permissions
		if ( current_user_can( $taxonomy_obj->cap->assign_terms ) ) {

			// first set all the lables as tags
			wp_set_post_tags( $post_id, $tags, true );

			// get all the tags for the post, including labels
			$terms = wp_get_post_terms( $post_id, $taxonomy );

			// initialise empty arry for labels
			$labels = array();

			// get the ids of labels that are tags
			foreach ( $terms as $term ) {
				// check if term name is in our label array above
				if ( in_array( $term->name, $tag_array ) ) {
					// if it is, then add it to label array
					$labels[ $term->term_id ] = $term->name;
				}
			}

			// update label information in post meta
			update_post_meta( $post_id, $this->ID, $labels );
		}
	}

	/**
	 * Get labels for the post
	 * 
	 * @param int $post_id
	 * @return boolean
	 */
	function get_labels( $post_id ) {
		$post_id = ( int ) $post_id;

		// no post id, get out!
		if ( ! $post_id )
			return false;

		// get the labels from post meta
		$labels = get_post_meta( $post_id, $this->ID, true );

		// no labels, nothing to do now
		if ( empty( $labels ) ) {
			return;
		}

		// get all the terms for the post
		$terms = wp_get_post_terms( $post_id, 'post_tag' );

		// no terms for post, get out
		if ( ! $terms ) {
			return false;
		}

		// error, get out
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		// initialise empty array to store term names
		$term_names = array();

		// loop through post's terms
		foreach ( $terms as $term ) {
			// if the term id is present in labels
			if ( array_key_exists( $term->term_id, $labels ) ) {
				// we use it
				$term_names[] = $term->name;
			}
		}

		// join them as a string for UI
		$terms_to_edit = esc_attr( join( ',', $term_names ) );

		// done!
		return $terms_to_edit;
	}

	/**
	 * A close replica of the default tags metabox function
	 * 
	 * @see https://wpseek.com/function/post_tags_meta_box/
	 * @param object $post
	 */
	function meta_box( $post ) {


		// force taxonomy to be post tag
		$tax = 'post_tag';
		$tax_name = esc_attr( $tax );
		$taxonomy = get_taxonomy( $tax );

		// user has permissions
		$user_can_assign_terms = current_user_can( $taxonomy->cap->assign_terms );

		// the tag delimiter (that can be translated via WP
		$comma = _x( ',', 'tag delimiter' );

		// get the existing labels for post
		$terms_to_edit = $this->get_labels( $post->ID );

		// not there, make it empty
		if ( ! is_string( $terms_to_edit ) ) {
			$terms_to_edit = '';
		}

		// the UI
		?>
		<div class="tagsdiv" id="<?php echo $tax_name; ?>">
			<div class="jaxtag">
				<div class="nojs-tags hide-if-js">
					<label for="tax-input-<?php echo $this->ID; ?>"><?php echo $taxonomy->labels->add_or_remove_items; ?></label>
					<p><textarea name="<?php echo $this->label; ?>" rows="3" cols="20" class="the-tags" id="tax-input-<?php echo $this->ID; ?>" <?php disabled(  ! $user_can_assign_terms ); ?> aria-describedby="new-tag-<?php echo $this->ID; ?>-desc"><?php echo str_replace( ',', $comma . ' ', $terms_to_edit ); // textarea_escaped by esc_attr()     ?></textarea></p>
				</div>
		<?php if ( $user_can_assign_terms ) : ?>
					<div class="ajaxtag hide-if-no-js">
						<label class="screen-reader-text" for="new-tag-<?php echo $this->ID; ?>"><?php echo $taxonomy->labels->add_new_item; ?></label>
						<p><input data-wp-taxonomy="<?php echo $tax_name; ?>" type="text" id="new-tag-<?php echo $this->ID; ?>" name="newtag[<?php echo $this->ID; ?>]" class="newtag form-input-tip" size="16" autocomplete="off" aria-describedby="new-tag-<?php echo $this->ID; ?>-desc" value="" />
							<input type="button" class="button tagadd" value="<?php esc_attr_e( 'Add' ); ?>" /></p>
					</div>
					<p class="howto" id="new-tag-<?php echo $this->ID; ?>-desc"><?php _e( 'Separate labels with commas', 'techpp' ); ?></p>
		<?php elseif ( empty( $terms_to_edit ) ): ?>
					<p><?php _e( 'No tags/labels', 'techpp' ) ?></p>
				<?php endif; ?>
			</div>
			<div class="tagchecklist"></div>
		</div>
		<?php
	}

}