<select id="<?php echo BU_FILTER_PAGES_ID; ?>" name="post_parent">
	<option value=""><?php _e('Show all sections'); ?></option>
	<?php 
	$post_parent = isset( $_GET['post_parent'] ) ? $_GET['post_parent'] : 0;
	bu_filter_pages_parent_dropdown($pages_by_parent, $post_parent); ?>
</select>