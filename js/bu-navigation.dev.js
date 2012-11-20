/**
 * ========================================================================
 * BU Navigation plugin - main script
 * ========================================================================
 */

/*jslint browser: true, todo: true */
/*global bu: true, jQuery: false, console: false, window: false, document: false */

var bu = bu || {};

bu.plugins = bu.plugins || {};
bu.plugins.navigation = {};

(function ($) {
	'use strict';

	// Simple pub/sub pattern
	bu.signals = (function () {
		var api = {};

		// Attach a callback function to respond for the given event
		api.listenFor = function (event, callback) {
			var listeners = this._listeners;
			if (listeners[event] === undefined) {
				listeners[event] = [];
			}

			listeners[event].push(callback);
		};

		// Broadcast a specific event, optionally providing context data
		api.broadcast = function (event, data) {
			var i, listeners = this._listeners;
			if (listeners[event]) {
				for (i = 0; i < listeners[event].length; i = i + 1) {
					listeners[event][i].apply(this, data || []);
				}
			}
		};

		// Objects that wish to broadcast signals must register themselves first
		return {
			register: function (obj) {
				obj._listeners = {};
				$.extend(true, obj, api);
			}
		};

	}());

	// Simple filter mechanism, modeled after Plugins API
	// @todo partially implemented
	bu.hooks = (function () {
		var filters = {};

		return {
			addFilter: function (name, func) {
				if (filters[name] === undefined) {
					filters[name] = [];
				}

				filters[name].push(func);
				return this;

			},
			applyFilters: function (name, obj) {
				if (filters[name] === undefined) {
					return obj;
				}

				var args = Array.prototype.slice.apply(arguments),
					extra = args.slice(1),
					rslt = obj,
					i;

				for (i = 0; i < filters[name].length; i = i + 1) {
					rslt = filters[name][i].apply(this, extra);
				}

				return rslt;
			}
		};
	}());
}(jQuery));

// =============================================//
// BU Navigation plugin settings & tree objects //
// =============================================//
(function ($) {

	// Plugin alias
	var Nav = bu.plugins.navigation;

	// Default global settings
	Nav.settings = {
		'lazyLoad': true,
		'showCounts': true,
		'showStatuses': true,
		'deselectOnDocumentClick': true
	};

	// DOM ready -- browser classes
	$(document).ready(function () {
		if( $.browser.msie === true && parseInt($.browser.version, 10) == 7 )
			$(document.body).addClass('ie7');
		if( $.browser.msie === true && parseInt($.browser.version, 10) == 8 )
			$(document.body).addClass('ie8');
		if( $.browser.msie === true && parseInt($.browser.version, 10) == 9 )
			$(document.body).addClass('ie9');
	});

	// Tree constructor
	Nav.tree = function( type, config ) {
		if (typeof type === 'undefined') {
			type = 'base';
		}

		return Nav.trees[type](config).initialize();
	};

	// Tree instances
	Nav.trees = {

		// ---------------------------------------//
		// Base navigation tree type - extend me! //
		// ---------------------------------------//
		base: function( config, my ) {
			var that = {};
			my = my || {};

			// Implement the signals interface
			bu.signals.register(that);

			// Instance settings
			that.config = $.extend({}, Nav.settings, config || {} );

			// Public data
			that.data = {
				treeConfig: {},
				rollback: undefined
			};

			// Aliases
			var c = that.config;
			var d = that.data;

			// Need valid tree element to continue
			var $tree = that.$el = $(c.el);

			if( $tree.length === 0 )
				throw new TypeError('Invalid DOM selector, can\'t create BU Navigation Tree');

			// Prefetch tree assets
			if (c.themePath && document.images) {
				var themeSprite = new Image();
				var themeLoader = new Image();
				themeSprite.src = c.themePath + "/sprite.png";
				themeLoader.src = c.themePath + "/throbber.gif";
			}

			// Allow clients to stop certain actions and UI interactions via filters
			var checkMove = function( m ) {
				var post = my.nodeToPost( m.o ), parent;
				var allowed = true;

				var isTopLevelMove = m.cr === -1;
				var isVisible = post.meta['excluded'] === false || post.type === 'link';
				var wasTop = !post.originalExclude && (post.originalParent === 0 || post.status === 'new');

				// Don't allow top level posts if global option prohibits it
				if (isTopLevelMove && !wasTop && isVisible && !c.allowTop) {
					// console.log('Move denied, top level posts cannot be created!');
					// @todo pop up a friendlier notice explaining this
					allowed = false;
				}

				// Don't allow published posts to be moved under unpublished posts
				if (m.np.length && m.np.attr('id') !== $tree.attr('id'))  {
					parent = my.nodeToPost( m.np );
					if (post.status == 'publish' && parent.status != 'publish') {
						allowed = false;
					}
				}

				return bu.hooks.applyFilters( 'moveAllowed', allowed, m, that );
			};

			var canSelectNode = function( node ) {
				return bu.hooks.applyFilters( 'canSelectNode', node, that );
			};

			var canHoverNode = function( node ) {
				return bu.hooks.applyFilters( 'canHoverNode', node, that );
			};

			var canDragNode = function( node ) {
				return bu.hooks.applyFilters( 'canDragNode', node, that );
			};

			// jsTree Settings object
			d.treeConfig = {
				"plugins" : ["themes", "types", "json_data", "ui", "dnd", "crrm", "bu"],
				"core" : {
					"animation" : 0,
					"html_titles": true
				},
				"ui" : {
					"selected_parent_close": false
				},
				"themes" : {
					"theme": "bu",
					"load_css": false
				},
				"types" : {
					"types" : {
						"default" : {
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all",
							"select_node"		: canSelectNode,
							"hover_node"		: canHoverNode,
							"start_drag"		: canDragNode
						},
						"page": {
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all",
							"select_node"		: canSelectNode,
							"hover_node"		: canHoverNode,
							"start_drag"		: canDragNode
						},
						"section": {
							"max_children"		: -1,
							"max_depth"			: -1,
							"valid_children"	: "all",
							"select_node"		: canSelectNode,
							"hover_node"		: canHoverNode,
							"start_drag"		: canDragNode
						},
						"link": {
							"max_children"		: 0,
							"max_depth"			: 0,
							"valid_children"	: "none",
							"select_node"		: canSelectNode,
							"hover_node"		: canHoverNode,
							"start_drag"		: canDragNode
						}
					}
				},
				"json_data": {
					"ajax" : {
						"url" : c.rpcUrl,
						"type" : "POST",
						"data" : function (n) {
							return {
								child_of : n.attr ? my.stripNodePrefix(n.attr("id")) : 0,
								post_types : c.postTypes,
								post_statuses : c.postStatuses,
								instance : c.instance,
								prefix : c.nodePrefix
							};
						}
					},
					"progressive_render" : true
				},
				"crrm": {
					"move": {
						"default_position" : "first",
						"check_move": checkMove
					}
				},
				"bu": {
					"lazy_load": c.lazyLoad
				}
			};

			if( c.showCounts ) {
				// counting needs a fully loaded DOM
				d.treeConfig['json_data']['progressive_render'] = false;
			}

			if( c.initialTreeData ) {
				d.treeConfig['json_data']['data'] = c.initialTreeData;
			}

			// For meddlers
			d.treeConfig = bu.hooks.applyFilters( 'buNavTreeSettings', d.treeConfig, $tree );

			// ======= Public API ======= //

			that.initialize = function() {
				$tree.jstree( d.treeConfig );
				return that;
			};

			that.selectPost = function( post, deselect_all ) {
				deselect_all = deselect_all || true;
				var $node = my.getNodeForPost(post);

				if (deselect_all) {
					$tree.jstree('deselect_all');
				}

				$tree.jstree('select_node', $node);
			};

			that.getSelectedPost = function() {
				var $node = $tree.jstree('get_selected');
				if ($node.length) {
					return my.nodeToPost($node);
				}
				return false;
			};

			that.deselectAll = function () {
				$tree.jstree('deselect_all');
			};

			that.getPost = function( id ) {
				var $node = my.getNodeForPost( id );
				if ($node) {
					return my.nodeToPost($node);
				}
				return false;
			};

			// Custom version of jstree.get_json, optimized for our needs
			that.getPosts = function( child_of ) {
				var result = [], current_post = {}, parent, post_id, post_type;

				if (child_of) {
					parent = $.jstree._reference($tree)._get_node('#' + child_of);
				} else {
					parent = $tree;
				}

				// Iterate over children of current node
				parent.find('> ul > li').each(function (i, child) {
					child = $(child);

					post_id = child.attr('id');
					post_type = child.data('post_type');
					if (post_type != 'new') {
						post_id = my.stripNodePrefix(post_id);
					}

					// Convert to post data
					current_post = {
						ID: post_id,
						type: post_type,
						status: child.data('post_status'),
						title: $tree.jstree('get_text',child),
						content: child.data('post_content'),
						meta: child.data('post_meta')
					};

					// Recurse through children if this post has any
					if( child.find('> ul > li').length ) {
						current_post.children = that.getPosts(child.attr('id'));
					}

					// Store post + descendents
					result.push(current_post);
				});

				// Result = post tree starting with child_of
				return result;
			};

			that.showAll = function() {
				$tree.jstree('open_all');
			};

			that.hideAll = function() {
				$tree.jstree('close_all');
			};

			that.getPostLabel = function( post ) {

				var $node = my.getNodeForPost( post );
				return $tree.jstree('get_text', $node );

			};

			that.setPostLabel = function( post, label ) {

				var $node = my.getNodeForPost( post );
				$tree.jstree('set_text', $node, label );

			};

			that.insertPost = function( post ) {
				if (typeof post === 'undefined') {
					throw new TypeError('Post argument for insertPost must be defined!');
				}

				var $inserted, $parent, $sibling, parent, orderIndex, args, node, pos;

				// Assert parent and menu order values exist and are valid
				post.parent = post.parent || 0;
				post.menu_order = post.menu_order || 1;

				// Translate post parent field to node
				if (post.parent) {
					$parent = my.getNodeForPost( post.parent );
					parent = that.getPost( post.parent );
				} else {
					$parent = $tree;
				}

				// Post will be first
				if (1 == post.menu_order) {
					pos = 'before';
					$sibling = $parent.find('> ul > li').get(0);
				} else {
					// Translate menu order to list item index of sibling to insert post after
					orderIndex = post.menu_order - 2;
					if (orderIndex >= 0) {
						pos = 'after';
						$sibling = $parent.find('> ul > li').get(orderIndex);
					} else {
						pos = 'before';
						$sibling = null;
					}
				}

				// Setup create args based on values translated from parent/menu_order
				args = {
					which: $sibling,
					position: pos,
					skip_rename: true,
					callback: function($node) { $tree.jstree('deselect_all'); $tree.jstree('select_node', $node); }
				};

				post = bu.hooks.applyFilters('preInsertPost', post, parent );

				// Translate post object to node format for jstree consumption
				node = my.postToNode( post );

				// Create tree node and update with insertion ID if post ID was not previously set
				$inserted = $tree.jstree( 'create', args.which, args.position, node, args.callback, args.skip_rename );
				if (!post.ID) {
					post.ID = $inserted.attr('id');
				}

				return post;
			};

			that.updatePost = function( post ) {
				var $node = my.getNodeForPost( post ),
					original, updated;

				if ($node) {

					// Merge original values with updates
					original = my.nodeToPost($node);
					updated = $.extend(true, {}, original, post);

					// Set node text with navigation label
					$tree.jstree('set_text', $node, updated.title);

					// Update metadata cache with node
					// @todo do this dynamically by looping through post props
					$node.data('post_content', updated.content);
					$node.data('post_title', updated.title);
					$node.data('post_status', updated.status);
					$node.data('post_type', updated.type);
					$node.data('post_parent', parseInt(updated.parent, 10));
					$node.data('menu_order', parseInt(updated.menu_order, 10));
					$node.data('post_meta', updated.meta);

					// Refresh post status badges (recursively)
					// @todo move to callback
					if (c.showStatuses) {
						$node.find('li').andSelf().each(function (){
							setStatusBadges($(this));
						});
					}

					that.broadcast('postUpdated', [updated]) ;

					return updated;

				}

				return false;
			};

			// Remove post
			that.removePost = function( post ) {
				var node;

				if ( post && typeof post === 'undefined' ) {
					node = $tree.jstree('get_selected');
					post = my.nodeToPost(node);
				} else {
					node = my.getNodeForPost( post );
				}

				$tree.jstree('remove', node );

			};

			// Get post ancestors (by title)
			that.getAncestors = function( postID ) {
				var $node = my.getNodeForPost( postID );
				return $tree.jstree('get_path', $node);
			};

			// Save tree state
			that.save = function() {

				// Cache current rollback object
				d.rollback = $tree.jstree( 'get_rollback' );

			};

			// Restore tree state
			that.restore = function() {
				if (typeof d.rollback === 'undefined')
					return;

				// HACK: Don't restore previous selections by removing them before rolling back
				// jstree has some buggy behavior with the ui/dnd plugins and selections
				// These bugs can be worked around by not attempting to restore selections
				// on rollbacks.

				// @todo fix the buggy behavior rather then hacking it here
				// @todo look at 1.0 release of jstree to see if it has been fixed
				d.rollback.d.ui.selected = $([]);

				// Run rollback
				$.jstree.rollback(d.rollback);

				// Reset cached rollback
				d.rollback = $tree.jstree('get_rollback');

			};

			that.lock = function() {
				$tree.jstree('lock');
			};

			that.unlock = function() {
				$tree.jstree('unlock');
			};

			// ======= Protected ======= //

			my.nodeToPost = function( node ) {
				if (typeof node === 'undefined')
					throw new TypeError('Invalid node!');

				var id = node.attr('id');

				if (id.indexOf('post-new') === -1) {
					id = parseInt(my.stripNodePrefix(id),10);
				}

				var post = {
					ID: id,
					title: $tree.jstree('get_text', node),
					content: node.data('post_content'),
					status: node.data('post_status'),
					type: node.data('post_type'),
					parent: parseInt(node.data('post_parent'), 10),
					menu_order: node.index() + 1,
					meta: node.data('post_meta') || {},
					url: node.data('url'),
					originalParent: parseInt(node.data('originalParent'), 10),
					originalOrder: parseInt(node.data('originalOrder'), 10),
					originalExclude: node.data('originalExclude')
				};

				return bu.hooks.applyFilters('nodeToPost', post, node);
			};

			my.postToNode = function( post ) {
				if (typeof post === 'undefined')
					throw new TypeError('Invalid post!');

				var default_post, p, node, post_id;

				default_post = {
					title: '(no title)',
					content: '',
					status: 'new',
					type: 'page',
					parent: 0,
					menu_order: 1,
					meta: {},
					url: ''
				};

				p = $.extend({}, default_post, post);

				// Generate post ID if none previously existed
				post_id = p.ID ? c.nodePrefix + p.ID : 'post-new-' + my.getNextPostID();

				node = {
					"attr": {
						"id": post_id,
						"rel" : p.type
					},
					"data": {
						"title": p.title
					},
					"metadata": {
						"post_status": p.status,
						"post_type": p.type,
						"post_content": p.content,
						"post_parent": p.parent,
						"menu_order": p.menu_order,
						"post_meta": p.meta,
						"url" : p.url,
						"originalParent": p.originalParent,
						"originalOrder": p.originalOrder,
						"originalExclude": p.originalExclude
					}
				};

				return bu.hooks.applyFilters('postToNode', node, p);
			};

			my.getNodeForPost = function( post ) {
				if (typeof post === 'undefined')
					throw new TypeError('Invalid post!');

				var node_id, $node;

				// Allow post object or ID
				if (post && typeof post === 'object') {
					node_id = post.ID.toString();
					if (node_id.indexOf('post-new') === -1) {
						node_id = c.nodePrefix + node_id;
					}
				} else {
					node_id = post.toString();
					if (node_id.indexOf('post-new') === -1) {
						node_id = c.nodePrefix + node_id;
					}
				}

				$node = $.jstree._reference($tree)._get_node('#' + node_id);

				if ($node.length) {
					return $node;
				}

				return false;
			};

			my.getNextPostID = function() {
				var newPosts = $('[id*="post-new-"]');
				return newPosts.length;

			};

			my.stripNodePrefix = function( str ) {
				return str.replace( c.nodePrefix, '');
			};

			// ======= Private ======= //

			var calculateCounts = function($node, includeDescendents) {
				var count;

				// Use DOM to calculate descendent count
				count = $node.find('li').length;

				// Update markup
				setCount($node, count);

				if (includeDescendents) {
					// Recurse to children
					$node.find('li').each(function (){
						calculateCounts($(this));
					});
				}
			};

			var setCount = function ($node, count) {
				var $a = $node.children('a'), $count;
				if ($a.children('.title-count').children('.count').length === 0) {
					$a.children('.title-count').append('<span class="count"></span>');
				}

				$count = $a.find('> .title-count > .count').empty();

				if (count) {
					// Set current count
					$count.text('(' + count + ')');
				} else {
					// Remove count if empty
					$count.text('');
				}
			};

			// Convert post meta data in to status badges
			var setStatusBadges = function ($node) {
				var $a = $node.children('a');
				if ($a.children('.post-statuses').length === 0) {
					$a.append('<span class="post-statuses"></span>');
				}

				var post = my.nodeToPost( $node ), $statuses, statuses, excluded, restricted, i;

				// Default metadata badges
				excluded = post.meta['excluded'] || false;
				restricted = post.meta['restricted'] || false;

				$statuses = $a.children('.post-statuses').empty();
				statuses = [];

				if (post.status != 'publish')
					statuses.push({ "class": post.status, "label": post.status });
				if (excluded)
					statuses.push({ "class": 'excluded', "label": 'not in nav' });
				if (restricted)
					statuses.push({ "class": 'restricted', "label": 'restricted' });

				// @todo implement this behavior through hooks for extensibility
				// statuses = bu.hooks.applyFilters( 'navPostStatuses', statuses, post );

				// Append markup
				for (i = 0; i < statuses.length; i = i + 1) {
					$statuses.append('<span class="post_status ' + statuses[i]['class'] + '">' + statuses[i]['label'] + '</span>');
				}

			};

			var updateBranch = function ( $post ) {
				var $section;

				// Maybe update rel attribute
				if ($post.children('ul').length === 0) {
					$post.attr('rel', 'page');
				} else {
					$post.attr('rel', 'section');
				}

				// Recalculate counts
				if (c.showCounts) {

					// Start from root
					if ($post.parent('ul').parent('div').attr('id') != $tree.attr('id')) {
						$section = $post.parents('li:last');
					} else {
						$section = $post;
					}

					calculateCounts($section, true);
				}
			};

			// ======= jsTree Event Handlers ======= //

			// Tree instance is loaded (before initial opens/selections are made)
			$tree.bind('loaded.jstree', function( event, data ) {

				// jstree breaks spectacularly if the stylesheet hasn't set an li height
				// when the tree is created -- this is what they call a hack...
				var $li = $tree.find("> ul > li:first-child");
				var nodeHeight = $li.height() >= 18 ? $li.height() : 32;
				$tree.jstree('data').data.core.li_height = nodeHeight;

				that.broadcast('postsLoaded');
			});

			// Run after initial node openings and selections have completed
			$tree.bind('reselect.jstree', function( event, data ) {

				that.broadcast('postsSelected');

			});

			// Run after lazy load operation has completed
			$tree.bind('lazy_loaded.jstree', function (event, data) {

				that.broadcast('lazyLoadComplete');

			});

			// After node is loaded from server using json_data
			$tree.bind('load_node.jstree', function( event, data ) {
				if( data.rslt.obj !== -1 ) {
					var $node = data.rslt.obj;

					if (c.showCounts) {
						calculateCounts($node, true);
					}
				}
			});

			// Append extra markup to each tree node
			$tree.bind('clean_node.jstree', function( event, data ) {
				var $nodes = data.rslt.obj;

				// skip root node
				if ($nodes && $nodes !== -1) {
					$nodes.each(function(i, node) {
						var $node = $(node);

						// Only add once
						if ($node.data('bu-nav-extras-added')) return;

						// Status badges
						if (c.showStatuses) {

							// Append post statuses inside node anchor
							setStatusBadges($node);

						}

						$node.data('bu-nav-extras-added', true);

					});
				}
			});

			$tree.bind('before.jstree', function (event, data) {
				var $node;
				
				switch (data.func) {
					case 'select_node':
					case 'hover_node':
					case 'start_drag':
						// Restrict select, hover and drag operations for denied posts
						$node = data.inst._get_node(data.args[0]);
						if ($node.hasClass('denied')) {
							return false;
						}
						break;
				}
				
			});

			$tree.bind('create_node.jstree', function(event, data ) {
				var $node = data.rslt.obj;
				var post = my.nodeToPost( $node );
				that.broadcast( 'postCreated', [ post ] );
			});

			$tree.bind('select_node.jstree', function(event, data ) {
				var post = my.nodeToPost(data.rslt.obj);
				that.broadcast('postSelected', [post]);
			});

			$tree.bind('create.jstree', function (event, data) {
				var	$node = data.rslt.obj,
					$parent = data.rslt.parent,
					position = data.rslt.position,
					post = my.nodeToPost($node),
					postParent = null;

				// Notify ancestors of our existence
				if( $parent !== -1 ) {
					postParent = my.nodeToPost($parent);
					updateBranch($parent);
				}

				// Set parent and menu order
				post['parent'] = postParent ? postParent.ID : 0;
				post['menu_order'] = position + 1;

				that.broadcast('postInserted', [post]);
			});

			$tree.bind('remove.jstree', function (event, data) {
				var $node = data.rslt.obj,
					post = my.nodeToPost($node),
					$oldParent = data.rslt.parent,
					child;

				// Notify former ancestors of our removal
				if( $oldParent !== -1 ) {
					updateBranch($oldParent);
				}

				that.broadcast('postRemoved', [post]);

				// Notify of descendent removals as well
				$node.find('li').each(function () {
					child = my.nodeToPost($(this));
					if (child) {
						that.broadcast('postRemoved', [child]);
					}
				});
			});

			$tree.bind('deselect_node.jstree', function(event, data ) {
				var post = my.nodeToPost( data.rslt.obj );
				that.broadcast('postDeselected', [post]);
			});

			$tree.bind('deselect_all.jstree', function (event, data) {
				that.broadcast('postsDeselected');
			});

			$tree.bind('move_node.jstree', function (event, data ) {
				var $moved = data.rslt.o;

				// Repeat move behavior for each moved node (handles multi-select)
				$moved.each(function (i, node) {
					var $node = $(node),
						post = my.nodeToPost( $node ),
						$newParent = data.rslt.np,
						$oldParent = data.rslt.op,
						menu_order = $node.index() + 1,
						parent_id = 0, oldParent, oldParentID = 0, oldOrder = 1;

					// Set new parent ID
					if( $tree.attr('id') !== $newParent.attr('id')) {
						// Notify new ancestors of changes
						updateBranch($newParent);
						parent_id = parseInt(my.stripNodePrefix($newParent.attr('id')),10);
					}

					// If we've changed sections, notify former ancestors as well
					if ($tree.attr('id') !== $oldParent.attr('id') &&
						!$newParent.is('#' + $oldParent.attr('id')) ) {
						updateBranch($oldParent);
						oldParent = my.nodeToPost( $oldParent );
						oldParentID = oldParent.ID;
					}

					oldOrder = post['menu_order'];

					// Extra post parameters that may be helpful to consumers
					post['parent'] = parent_id;
					post['menu_order'] = menu_order;

					that.updatePost(post);

					that.broadcast( 'postMoved', [post, oldParentID, oldOrder]);
				});
			});

			// Deselect all nodes on document clicks outside of a tree element or
			// context menu item
			var deselectOnDocumentClick = function (e) {
				var clickedTree = $.contains( $tree[0], e.target );
				var clickedMenuItem = $.contains( $('#vakata-contextmenu')[0], e.target );

				if (!clickedTree && !clickedMenuItem) {
					$tree.jstree('deselect_all');
				}
			};

			if (c.deselectOnDocumentClick ) {
				$(document).bind( "click", deselectOnDocumentClick );
			}

			return that;
		},

		// ----------------------------
		// Edit order (Navigation manager) tree
		// ----------------------------
		navman: function( config, my ) {
			var that = {};
			my = my || {};

			that = Nav.trees.base( config, my );

			var $tree = that.$el;
			var d = that.data;

			var showOptionsMenu = function (node) {
				var url = node.data('url'), type = node.data('post_type');

				var options = {
					"edit" : {
						"label" : "Edit",
						"action" : editPost
					},
					"view" : {
						"label" : "View",
						"action" : viewPost
					},
					"remove" : {
						"label" : "Move to Trash",
						"action" : removePost
					}
				};

				// Can't view an item with no URL
				if (!url) {
					delete options['view'];
				}

				// Special behavior for links
				if (type === 'link') {
					// Links are permanently deleted -- "Move To Trash" is misleading
					options['remove']['label'] = 'Delete';
				}

				return bu.hooks.applyFilters('navmanOptionsMenuItems', options, node);
			};

			var editPost = function( node ) {
				var post = my.nodeToPost(node);
				that.broadcast('editPost', [post]);
			};

			var viewPost = function (node) {
				var post = my.nodeToPost(node);
				if (post.url) {
					window.open(post.url);
				}
			};

			var removePost = function( node ) {
				var post = my.nodeToPost(node);
				that.removePost(post);
			};

			// Add context menu plugin
			d.treeConfig["plugins"].push("contextmenu");

			d.treeConfig["contextmenu"] = {
				'show_at_node': false,
				"items": showOptionsMenu
			};

			// Prevent default right click behavior
			$tree.bind('loaded.jstree', function(e,data) {

				$tree.undelegate('a', 'contextmenu.jstree');

			});

			// Append options menu to each node
			$tree.bind('clean_node.jstree', function( event, data ) {
				var $nodes = data.rslt.obj;
				// skip root node
				if ($nodes && $nodes != -1) {
					$nodes.each(function(i, node) {
						var $node = $(node);
						var $a = $node.children('a');

						if( $a.children('.edit-options').length ) return;

						var $button = $('<button class="edit-options"><ins class="jstree-icon">&#160;</ins>options</button>');
						var $statuses = $a.children('.post-statuses');

						// Button should appear before statuses
						if( $statuses.length ) {
							$statuses.before($button);
						} else {
							$a.append($button);
						}

					});
				}
			});

			// @todo move all of this custom contextmenu behavior to our fork of the
			// jstree contextmenu plugin
			var currentMenuTarget = null;

			$tree.delegate(".edit-options", "click", function (e) {
				e.preventDefault();
				e.stopPropagation();

				var pos = $(this).offset();
				var yOffset = $(this).height() + 5;
				var obj = $(this).parent('a').parent('li');

				$tree.jstree('deselect_all');
				$(this).addClass('clicked');
				$tree.jstree('select_node', obj );
				$tree.jstree('show_contextmenu', obj, pos.left, pos.top + yOffset );

				if (currentMenuTarget && currentMenuTarget.attr('id') != obj.attr('id')) {
					removeMenu( currentMenuTarget );
				}
				currentMenuTarget = obj;
			});

			// Remove active state on edit options button when the menu is removed
			$(document).bind('context_hide.vakata', function(e, data){
				removeMenu( currentMenuTarget );
			});

			var removeMenu = function ( target ) {
				if (target) {
					target.find('> a > .edit-options').removeClass('clicked');
				}
			};

			$tree.addClass('bu-navman');

			return that;
		},

		// ----------------------------
		// Edit post tree
		// ----------------------------
		edit_post: function( config, my ) {
			my = my || {};

			// Functional inheritance
			var that = Nav.trees.base( config, my );

			// Aliases
			var d = that.data;
			var c = $.extend(that.config, config || {});	// instance configuration

			var $tree = that.$el;
			var currentPost = c.currentPost;

			// Extra configuration
			var extraTreeConfig = {};

			// Build initial open and selection arrays from current post / ancestors
			var toOpen = [], i;

			if (c.ancestors && c.ancestors.length) {
				// We want old -> young, which is not how they're passed
				var ancestors = c.ancestors.reverse();
				for (i = 0; i < ancestors.length; i = i + 1) {
					toOpen.push( '#' + c.nodePrefix + c.ancestors[i] );
				}
			}
			if (toOpen.length) {
				extraTreeConfig['core'] = {
					"initially_open": toOpen
				};
			}

			// Merge base tree config with extras
			$.extend( true, d.treeConfig, extraTreeConfig );

			// Assert current post for select, hover and drag operations
			var assertCurrentPost = function( node, inst ) {
				if (inst.$el.is(that.$el.selector)) {
					var postId = my.stripNodePrefix(node.attr('id'));
					return postId == currentPost.ID;
				}
			};

			bu.hooks.addFilter( 'canSelectNode', assertCurrentPost );
			bu.hooks.addFilter( 'canHoverNode', assertCurrentPost );
			bu.hooks.addFilter( 'canDragNode', assertCurrentPost );

			$tree.bind('reselect.jstree', function (e, data) {
				var $current = my.getNodeForPost(currentPost);

				// Insert post if it isn't already represented in the tree (new, draft, or pending posts)
				if (!$current) {
					that.insertPost(currentPost);
				}

				// Select current post if it isn't already selected
				if ($tree.jstree('get_selected').length === 0) {
					that.selectPost( currentPost );
					that.save();
				}

			});

			// Public
			that.getCurrentPost = function() {
				var $node, post;

				$node = my.getNodeForPost(currentPost);

				if ($node) {
					post = my.nodeToPost( $node );
					return post;
				}

				return false;
			};

			that.setCurrentPost = function( post ) {
				currentPost = post;
			};

			// @todo consider moving to ModalTree
			that.scrollToSelection = function() {
				var $node = $tree.jstree('get_selected');

				if ($node.length) {

					var $container = $(document);

					if( $tree.css('overflow') === 'scroll' )
						$container = $tree;

					var treeHeight = $tree.innerHeight();
					var nodeOffset = $node.position().top + ( $node.height() / 2 ) - ( treeHeight / 2 );

					if (nodeOffset > 0) {
						// $tree.animate({ scrollTop: nodeOffset }, 350 );
						$tree.scrollTop( nodeOffset );
					}
				}

			};

			$tree.addClass('bu-edit-post');

			return that;
		}
	};
})(jQuery);
