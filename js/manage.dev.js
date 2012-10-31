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
			movesField: '#navman-moves',
			insertsField: '#navman-inserts',
			updatesField: '#navman-updates',
			deletionsField: '#navman-deletions',
			expandAllBtn: '#navman_expand_all',
			collapseAllBtn: '#navman_collapse_all',
			container: '#navman-body'
		},

		data: {
			dirty: false,
			deletions: [],
			insertions: {},
			updates: {},
			moves: {}
		},

		initialize: function( config ) {
			// Create post navigation tree from server-provided instance settings object
			var settings = bu_navman_settings;
			settings.el = this.el;

			Navtree = bu.plugins.navigation.tree('navman', settings );

			// Initialize link manager
			Linkman.initialize();

			// Subscribe to relevant tree signals
			Navtree.listenFor('editPost', $.proxy( this.editPost, this ));

			Navtree.listenFor('postRemoved', $.proxy( this.postRemoved, this ));
			Navtree.listenFor('postMoved', $.proxy( this.postMoved, this ));
			Linkman.listenFor('linkInserted', $.proxy(this.linkInserted, this));
			Linkman.listenFor('linkUpdated', $.proxy(this.linkUpdated, this));

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

		linkInserted: function (link) {

			this.data.insertions[link.ID] = link;
			this.data.dirty = true;

		},

		linkUpdated: function (link) {

			if ('new' === link.status) {
				// Update to new link (not yet commited to DB)
				this.data.insertions[link.ID] = link;
			} else {
				// Update to previously existing link
				this.data.updates[link.ID] = link;
			}

			this.data.dirty = true;

		},

		postRemoved: function (post) {
			var id = post.ID;

			if (id) {
				
				if (typeof this.data.insertions[id] !== 'undefined' ) {
					
					// Newly inserted posts aren't yet commited to DB, so just
					// remove it from the insertions cache and move on
					delete this.data.insertions[id];
					
				} else if (typeof this.data.updates[id] !== 'undefined' ) {

					// Post was marked to be updated -- remove from updates cache
					// and push to deletions
					delete this.data.updates[id];
					this.data.deletions.push(id);
					this.data.dirty = true;
					
				} else if (typeof this.data.moves[id] !== 'undefined' ) {
					
					// Post was marked to be moved -- remove from moves cache
					// and push to deletions
					delete this.data.moves[id];
					this.data.deletions.push(id);
					this.data.dirty = true;
					
				} else {
					
					// Deletion was not previously in any category, just add to deletions cache
					// and mark page as dirty
					this.data.deletions.push(id);
					this.data.dirty = true;
					
				}
			}
		},

		postMoved : function (post) {

			// New post moves are tracked via the insertions cache
			if ('new' == post.status) {
				return;
			}

			// If post parent or menu order has changed, track this as a move
			if (post.parent != post.originalParent || post.menu_order != post.originalOrder) {
				this.data.moves[post.ID] = post;
				this.data.dirty = true;		
			}

		},

		save: function(e) {
			var deletions = this.data.deletions, moves = {}, updates = {}, insertions = {}, current;

			// Process insertions
			$.each( this.data.insertions, function (postID, post) {
				current = Navtree.getPost(postID);
				if (current) {
					insertions[current.ID] = current;
				}
			});

			// Process updates
			$.each( this.data.updates, function (postID, post) {
				current = Navtree.getPost(postID);
				if (current) {
					updates[current.ID] = current;
				}
			});

			// Process moves
			$.each( this.data.moves, function (postID, post) {
				current = Navtree.getPost(postID);
				if (current) {
					moves[current.ID] = current;
				}
			});

			// Push pending deletions, insertions, updates and moves to hidden inputs for POST'ing
			$(this.ui.deletionsField).attr("value", JSON.stringify(deletions));
			$(this.ui.insertsField).attr("value", JSON.stringify(insertions));
			$(this.ui.updatesField).attr("value", JSON.stringify(updates));
			$(this.ui.movesField).attr("value", JSON.stringify(moves));

			// Let us through the window.unload check now that all pending moves are ready to go
			this.data.dirty = false;
		}

	};

	Linkman = bu.plugins.navigation.views.Linkman = {

		el: '#navman-link-editor',

		ui: {
			form: '#navman_editlink_form',
			addBtn: '#navman_add_link',
			urlField: '#editlink_address',
			labelField: '#editlink_label',
			targetNewField: '#editlink_target_new',
			targetSameField: '#editlink_target_same'
		},

		data: {
			currentLink: null
		},

		initialize: function() {

			// Add signals
			$.extend( true, this, bu.signals );

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

			// Setup new link
			this.data.currentLink = { "status": "new", "type": "link", "meta": {} };
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
				var link = this.data.currentLink, saved;

				// Extract updates from form
				link.content = $(this.ui.urlField).attr("value");
				link.title = $(this.ui.labelField).attr("value");
				link.meta.bu_link_target = $("input[name='editlink_target']:checked").attr("value");

				var selected = Navtree.getSelectedPost();

				if (selected) {
					link.parent = selected.parent;
					link.menu_order = selected.menu_order + 1;
				} else {
					link.parent = 0;
					link.menu_order = 1;
				}

				// Insert or update link
				if (link.status === 'new' && !link.ID ) {

					saved = Navtree.insertPost( link );
					this.broadcast('linkInserted', [saved]);

				} else {

					saved = Navtree.updatePost( link );
					this.broadcast('linkUpdated', [saved]);

				}

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
