<div class="wrap">
	<div id="icon-edit-pages" class="icon32"><br></div>
	<h2><?php echo $pt_labels['singular']; ?> Order</h2>
	<div id="navman-notices"><?php echo $notices; ?></div>

	<div id="navman-container">
		<form method="post" id="navman_form" action="">
			<input type="hidden" id="navman-moves" name="navman-moves" value="" />
			<input type="hidden" id="navman-inserts" name="navman-inserts" value="" />
			<input type="hidden" id="navman-updates" name="navman-updates" value="" />
			<input type="hidden" id="navman-deletions" name="navman-deletions" value="" />

			<ul class="navman-toolbar">
				<li><a href="#nav-tree-container" id="navman_expand_all">Expand All</a></li>
				<li>| <a href="#nav-tree-container" id="navman_collapse_all">Collapse All</a></li>
				<?php if ( $include_links ): ?>
				<li <?php if( $disable_add_link ) echo 'class="disabled"'; ?>>| <a href="#" id="navman_add_link">Add a Link</a></li>
				<?php endif; ?>
			</ul>
			<div class="navman-body">
					<div id="nav-tree-container" class="jstree-bu"></div>
			</div>
			<div class="navman-actions">
				<div class="postbox metabox-holder">
					<h3>Publish</h3>
					<div class="inside">
						<p><strong>Note:</strong> You must publish changes to navigation, or all changes will be lost.</p>
						<div class="actions">
							<img src="<?php echo $ajax_spinner; ?>" class="ajax-loading" alt="" />
							<input class="button button-primary" id="bu_navman_save" name="bu_navman_save" type="submit" value="Publish Changes" />
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>
	<?php if ( $include_links ): ?>
	<div id="navman-link-editor">
		<div class="submitbox navform navformwide">
			<form id="navman_editlink_form">
				<input type="hidden" name="editlink_id" id="editlink_id" value="" />
				<div>
					<label for="editlink_address"><sup class="req">*</sup>Link URL</label>
					<input size="40" type="text" name="editlink_address" id="editlink_address" class="required url" value="" />
				</div>
				<div>
					<label for="editlink_label"><sup class="req">*</sup>Link Label</label>
					<input size="30" type="text" name="editlink_label" id="editlink_label" class="required" value="" />
				</div>
				<div class="radios">
					<input type="radio" name="editlink_target" id="editlink_target_same" value="" checked="checked" />
					<label class="inline" for="editlink_target_same">Open Link in Same Window</label>
				</div>
				<div class="radios">
					<input type="radio" name="editlink_target" id="editlink_target_new" value="new" />
					<label class="inline" for="editlink_target_new">Open Link in New Window</label>
				</div>
			</form>
		</div>
	</div>
	<?php endif; ?>
</div>
