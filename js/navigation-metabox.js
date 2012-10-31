if("undefined"===typeof bu||"undefined"===typeof bu.plugins||"undefined"===typeof bu.plugins.navigation)throw new TypeError("BU Navigation Metabox dependencies have not been met!");
(function(b){bu.plugins.navigation.views=bu.plugins.navigation.views||{};var d;bu.plugins.navigation.views.Metabox={el:"#bupageparentdiv",ui:{treeContainer:"#edit_page_tree",moveBtn:"#select-parent",breadcrumbs:"#bu_nav_attributes_location_breadcrumbs"},inputs:{label:'[name="nav_label"]',visible:'[name="nav_display"]',postID:'[name="post_ID"]',originalStatus:'[name="original_post_status"]',parent:'[name="parent_id"]',order:'[name="menu_order"]',autoDraft:'[name="auto_draft"]'},data:{modalTree:void 0,
breadcrumbs:"",label:""},initialize:function(){var a,c,d,g,h;this.settings=nav_metabox_settings;this.settings.el=this.ui.treeContainer;this.settings.isNewPost=1==b(this.inputs.autoDraft).val()?!0:!1;a=b(this.inputs.originalStatus).val();c=parseInt(b(this.inputs.parent).val(),10);d=parseInt(b(this.inputs.order).val(),10);g=b(this.inputs.label).val()||"(no title)";h=b(this.inputs.visible).attr("checked")||!1;this.settings.currentPost={ID:parseInt(b(this.inputs.postID).val(),10),title:g,meta:{excluded:!h},
parent:c,menu_order:d,status:"auto-draft"==a?"new":a};this.$el=b(this.el);this.loadNavTree();this.attachHandlers()},loadNavTree:function(){"undefined"===typeof this.data.modalTree&&(this.data.modalTree=ModalPostTree(this.settings),this.data.modalTree.listenFor("locationUpdated",b.proxy(this.onLocationUpdated,this)))},attachHandlers:function(){this.$el.delegate(this.ui.moveBtn,"click",this.data.modalTree.open);this.$el.delegate(this.inputs.label,"blur",b.proxy(this.onLabelChange,this));this.$el.delegate(this.inputs.visible,
"click",b.proxy(this.onToggleVisibility,this))},onLabelChange:function(){var a=b(this.inputs.label).attr("value");this.settings.currentPost.title=a;d.updatePost(this.settings.currentPost);d.save();this.updateBreadcrumbs(this.settings.currentPost)},onToggleVisibility:function(a){var c=b(a.target).attr("checked");c&&!this.isAllowedInNavigationLists(this.settings.currentPost)?(a.preventDefault(),this.notify('Displaying top-level pages in the navigation is disabled. To change this behavior, go to Site Design > Primary Navigation and enable "Allow Top-Level Pages."')):
(this.settings.currentPost.meta.excluded=!c,d.updatePost(this.settings.currentPost),d.save())},onLocationUpdated:function(a){b(this.inputs.parent).val(a.parent);b(this.inputs.order).val(a.menu_order);this.updateBreadcrumbs(a);this.settings.currentPost=a},updateBreadcrumbs:function(a){var a=d.getAncestors(a.ID),c=a.join("&nbsp;&raquo;&nbsp;");1<a.length?b(this.ui.breadcrumbs).html("<p>"+c+"</p>"):b(this.ui.breadcrumbs).html("<p>Top level page</p>")},isAllowedInNavigationLists:function(b){return 0===
b.parent?this.settings.allowTop:!0},notify:function(b){alert(b)}};ModalPostTree=bu.plugins.navigation.views.ModalPostTree=function(a){var c={},e=c.conf={treeContainer:"#edit_page_tree",toolbarContainer:".page_location_toolbar",navSaveBtn:"#bu_page_parent_save",navCancelBtn:"#bu_page_parent_cancel"},e=b.extend(e,a);b.extend(!0,c,bu.signals);c.open=function(a){var e=b(window).width(),i=b(window).height(),e=720<e?720:e,j=a.target.title||a.target.name||null,f=a.target.href||a.target.alt,a=a.target.rel||
!1,f=f.replace(/&width=[0-9]+/g,""),f=f.replace(/&height=[0-9]+/g,"");tb_show(j,f+"&width="+(e-80)+"&height="+(i-85),a);d.scrollToSelection();b("#TB_window").bind("unload tb_unload",function(){c.saving?c.saving=!1:d.restore()});return!1};c.onUpdateLocation=function(b){b.preventDefault();c.broadcast("locationUpdated",[d.getCurrentPost()]);d.save();c.saving=!0;tb_remove()};c.onCancelMove=function(b){b.preventDefault();tb_remove()};d=bu.plugins.navigation.tree("edit_post",e);a=b(e.toolbarContainer);
a.delegate(e.navSaveBtn,"click",c.onUpdateLocation);a.delegate(e.navCancelBtn,"click",c.onCancelMove);return c}})(jQuery);var tb_position;
(function(b){tb_position=function(){var d=b("#TB_window"),a=b(window).width(),c=b(window).height(),e=720<a?720:a;d.size()&&(d.width(e-50).height(c-45),b("#TB_inline").width(e-80).height(c-90),d.css({"margin-left":"-"+parseInt((e-50)/2,10)+"px"}),"undefined"!=typeof document.body.style.maxWidth&&d.css({top:"20px","margin-top":"0"}));return b("a.thickbox").each(function(){var a=b(this).attr("href");a&&(a=a.replace(/&width=[0-9]+/g,""),a=a.replace(/&height=[0-9]+/g,""),b(this).attr("href",a+"&width="+
(e-80)+"&height="+(c-85)))})};b(window).resize(function(){tb_position()})})(jQuery);jQuery(document).ready(function(){bu.plugins.navigation.views.Metabox.initialize()});