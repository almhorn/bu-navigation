// Check prerequisites
if((typeof bu === 'undefined') ||
	(typeof bu.plugins.navigation === 'undefined') ||
	(typeof bu.plugins.navigation.tree === 'undefined'))
		throw new TypeError('BU Navigation Manager script dependencies have not been met!');

(function($){

	// If we are the first view object, set up our namespace
	bu.plugins.navigation.views = bu.plugins.navigation.views || {};

	var Navman, Linkman, Navtree;

	Navman = bu.plugins.navigation.views.Navman = {

		el: '#nav-tree-container',

		ui: {
			form: '#navman_form',
			dataField: '#navman_data',
			deletionsField: '#navman_delete',
			editsField: '#navman_edits',
			expandAllBtn: '#navman_expand_all',
			collapseAllBtn: '#navman_collapse_all',
			container: '#navman-body'
		},

		data: {
			dirty: false,
			deletions: []
		},

		initialize: function( config ) {
			// Create post navigation tree from server-provided instance settings object
			var settings = bu_navman_settings;
			settings.el = this.el;

			Navtree = bu.plugins.navigation.tree('navman', settings );

			// Initialize link manager
			Linkman.initialize();

			// Subscribe to relevan tree signals
			Navtree.listenFor('editPost', $.proxy( this.editPost, this ));
			Navtree.listenFor('removePost', $.proxy( this.removePost, this ));

			// Form submission
			$(this.ui.form).bind('submit', $.proxy( this.save, this ));
			$(this.ui.expandAllBtn).bind('click', this.expandAll );
			$(this.ui.collapseAllBtn).bind('click', this.collapseAll );
		},

		expandAll: function(e) {
			e.preventDefault();
			e.stopImmediatePropagation();
			Navtree.showAll();
		},

		collapseAll: function(e) {
			e.preventDefault();
			e.stopImmediatePropagation();
			Navtree.hideAll();
		},

		editPost: function( post ) {
			if( post.type == 'link' ) {
				Linkman.edit( post );
			} else {
				var url = "post.php?action=edit&post=" + post.ID;
				window.location = url;
			}
		},

		removePost: function( post ) {
			var id = post.ID;

			if (id) {
				this.data.deletions.push(id);
				this.data.dirty = true;
			}
		},

		save: function(e) {
			var posts = Navtree.getPosts();

			$(this.ui.dataField).attr("value", JSON.stringify(posts));
			$(this.ui.deletionsField).attr("value", JSON.stringify(this.data.deletions));
			$(this.ui.editsField).attr("value", JSON.stringify(Linkman.data.edits));

			this.data.dirty = false;
		}

	};

	Linkman = bu.plugins.navigation.views.Linkman = {

		el: '#navman-link-editor',

		ui: {
			form: '#navman_editlink_form',
			urlField: '#editlink_address',
			labelField: '#editlink_label',
			targetNewField: '#editlink_target_new',
			targetSameField: '#editlink_target_same',
			addBtn: '#navman_add_link'
		},

		data: {
			currentLink: null,
			edits: {}
		},

		initialize: function() {

			this.$el = $(this.el);
			this.$form = $(this.ui.form);

			// Edit link dialog
			this.$el.dialog({
				autoOpen: false,
				buttons: {
					"Ok": $.proxy( this.save, this ),
					"Cancel": $.proxy( this.cancel, this )
				},
				minWidth: 400,
				width: 500,
				modal: true,
				resizable: false
			});

			// Prevent clicks in dialog/overlay from removing tree selections
			$(document.body).delegate('.ui-widget-overlay, .ui-widget', 'click', this.stopPropagation );

			// Add link event
			$(this.ui.addBtn).bind('click', $.proxy(this.add, this ));

			return this;

		},

		add: function(e) {
			e.preventDefault();
			e.stopPropagation();
			this.$el.dialog('option', 'title', 'Add a Link').dialog('open');
		},

		edit: function( link ) {

			$(this.ui.urlField).attr("value", link.content);
			$(this.ui.labelField).attr("value", link.title);

			if (link.meta.bu_link_target == "new") {
				$(this.ui.targetNewField).attr("checked", "checked");
			} else {
				$(this.ui.targetSameField).attr("checked", "checked");
			}

			this.data.currentLink = link;

			this.$el.dialog('option', 'title', 'Edit a Link').dialog('open');
		},

		save: function(e) {
			e.preventDefault();
			e.stopPropagation();

			if (this.$form.valid()) {

				// Global link being edited
				var link = this.data.currentLink || { "status": "new", "type": "link", "meta": {} };

				// Extract updates from form
				link.content = $(this.ui.urlField).attr("value");
				link.title = $(this.ui.labelField).attr("value");
				link.meta.bu_link_target = $("input[name='editlink_target']:checked").attr("value");

				// Insert or update link
				if (link.status === 'new' && !link.ID) {

					Navtree.insertPost( link );

				} else {

					Navtree.updatePost( link );
					this.data.edits[link.ID] = link;

				}

				Navman.data.dirty = true;

				this.clear();

				this.$el.dialog('close');

			}

		},

		cancel: function (e) {
			e.preventDefault();
			e.stopPropagation();

			this.$el.dialog('close');

			this.clear();
		},

		clear: function () {

			// Clear dialog
			$(this.ui.urlField).attr("value", "");
			$(this.ui.labelField).attr("value", "");
			$(this.ui.targetSameField).attr("checked", "checked");
			$(this.ui.targetNewField).removeAttr("checked");

			this.data.currentLink = null;

		},

		stopPropagation: function (e) {
			e.stopPropagation();
		}

	};

	window.onbeforeunload = function() {
		if ( Navman.data.dirty ) {
			return 'You have made changes to your navigation that have not yet been saved.';
		}
		
		return;
	};

})(jQuery);

jQuery(document).ready( function($) {
	bu.plugins.navigation.views.Navman.initialize();
});
