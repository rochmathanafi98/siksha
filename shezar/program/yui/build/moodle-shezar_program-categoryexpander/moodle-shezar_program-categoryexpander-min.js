YUI.add("moodle-shezar_program-categoryexpander",function(e,t){var n={CONTENTNODE:"content",COLLAPSEALL:"collapse-all",DISABLED:"disabled",LOADED:"loaded",NOTLOADED:"notloaded",SECTIONCOLLAPSED:"collapsed",HASCHILDREN:"with_children"},r={LOADEDTREES:".with_children.loaded",CONTENTNODE:".content",CATEGORYLISTENLINK:".category .info .categoryname",CATEGORYSPINNERLOCATION:".categoryname",CATEGORYWITHCOLLAPSEDUNLOADEDCHILDREN:".category.with_children.notloaded.collapsed",CATEGORYWITHCOLLAPSEDLOADEDCHILDREN:".category.with_children.loaded.collapsed",CATEGORYWITHMAXIMISEDLOADEDCHILDREN:".category.with_children.loaded:not(.collapsed)",COLLAPSEEXPAND:".collapseexpand",PROGRAMBOX:".coursebox",PROGRAMBOXLISTENLINK:".coursebox .moreinfo",PROGRAMBOXSPINNERLOCATION:".name a",PROGRAMCATEGORYTREE:".course_category_tree",PARENTWITHCHILDREN:".category"},i="category",s=M.cfg.wwwroot+"/shezar/program/category.ajax.php";M.program=M.program||{};var o=M.program.categoryexpander=M.program.categoryexpander||{};o.init=function(){var t=e.one(e.config.doc);t.delegate("click",this.toggle_category_expansion,r.CATEGORYLISTENLINK,this),t.delegate("click",this.toggle_programbox_expansion,r.PROGRAMBOXLISTENLINK,this),t.delegate("click",this.collapse_expand_all,r.COLLAPSEEXPAND,this),t.once("key",this.setup_keyboard_listeners,"tab",this)},o.setup_keyboard_listeners=function(){var t=e.one(e.config.doc);t.all(r.CATEGORYLISTENLINK,r.PROGRAMBOXLISTENLINK,r.COLLAPSEEXPAND).setAttribute("tabindex","0"),e.one(e.config.doc).delegate("key",this.toggle_category_expansion,"enter",r.CATEGORYLISTENLINK,this),e.one(e.config.doc).delegate("key",this.toggle_programbox_expansion,"enter",r.PROGRAMBOXLISTENLINK,this),e.one(e.config.doc).delegate("key",this.collapse_expand_all,"enter",r.COLLAPSEEXPAND,this)},o.toggle_category_expansion=function(t){e.use("io-base","json-parse","moodle-core-notification","anim-node-plugin",function(){o.toggle_category_expansion=o._toggle_category_expansion,o.toggle_category_expansion(t)})},o.toggle_programbox_expansion=function(t){e.use("io-base","json-parse","moodle-core-notification","anim-node-plugin",function(){o.toggle_programbox_expansion=o._toggle_programbox_expansion,o.toggle_programbox_expansion(t)}),t.preventDefault()},o._toggle_programbox_expansion=function(e){var t;t=e.target.ancestor(r.PROGRAMBOX,!0),e.preventDefault();if(t.hasClass(n.LOADED)){this.run_expansion(t);return}YUI().use("querystring-parse",function(e){var n=e.QueryString.parse(window.location.search.substr(1));n.viewtype||(n.viewtype="program"),o._toggle_generic_expansion({parentnode:t,childnode:t.one(r.CONTENTNODE),spinnerhandle:r.PROGRAMBOXSPINNERLOCATION,data:{id:t.getData("programid"),categorytype:n.viewtype,type:"summary"}})})},o._toggle_category_expansion=function(e){var t,s,u;if(e.target.test("a")||e.target.test("img"))return;t=e.target.ancestor(r.PARENTWITHCHILDREN,!0);if(!t.hasClass(n.HASCHILDREN))return;if(t.hasClass(n.LOADED)){this.run_expansion(t);return}s=t.getData("categoryid"),u=t.getData("depth"),YUI().use("querystring-parse",function(e){var n=e.QueryString.parse(window.location.search.substr(1));n.viewtype||(n.viewtype="program"),o._toggle_generic_expansion({parentnode:t,childnode:t.one(r.CONTENTNODE),spinnerhandle:r.CATEGORYSPINNERLOCATION,data:{id:s,depth:u,type:i,categorytype:n.viewtype}})})},o._toggle_generic_expansion=function(t){var n={};t.spinnerhandle&&(n=M.util.add_spinner(e,t.parentnode.one(t.spinnerhandle)).show()),t.data.sesskey=M.cfg.sesskey,e.io(s,{method:"POST",context:this,on:{complete:this.process_results},data:t.data,arguments:{parentnode:t.parentnode,childnode:t.childnode,spinner:n}})},o.run_expansion=function(e){var t=e.one(r.CONTENTNODE),i=this,s=e.ancestor(r.PROGRAMCATEGORYTREE);this.add_animation(t),e.hasClass(n.SECTIONCOLLAPSED)?(t.setStyle("height","0"),e.removeClass(n.SECTIONCOLLAPSED),e.setAttribute("aria-expanded","true"),t.fx.set("reverse",!1),require(["core/templates"],function(t){t.renderIcon("expanded").done(function(t){e.get("aria-expanded")==="true"&&(e.one(".categoryname .flex-icon").remove(),e.one(".categoryname").prepend(t))})})):(t.fx.set("reverse",!0),t.fx.once("end",function(e,t){t.addClass(n.SECTIONCOLLAPSED),t.setAttribute("aria-expanded","false"),require(["core/templates"],function(e){e.renderIcon("collapsed").done(function(e){t.get("aria-expanded")==="false"&&(t.one(".categoryname .flex-icon").remove(),t.one(".categoryname").prepend(e))})})},this,e)),t.fx.once("end",function(e,t){t.setStyles({height:"",opacity:""}),this.destroy(),i.update_collapsible_actions(s)},t.fx,t),t.fx.run()},o.collapse_expand_all=function(t){e.use("io-base","json-parse","moodle-core-notification","anim-node-plugin",function(){o.collapse_expand_all=o._collapse_expand_all,o.collapse_expand_all(t)}),t.preventDefault()},o._collapse_expand_all=function(e){e.preventDefault();if(e.currentTarget.hasClass(n.DISABLED))return;var t=e.currentTarget.ancestor(r.PROGRAMCATEGORYTREE);if(!t)return;var i=t.one(r.COLLAPSEEXPAND);i.hasClass(n.COLLAPSEALL)?this.collapse_all(t):this.expand_all(t),this.update_collapsible_actions(t)},o.expand_all=function(t){var s=[],o={viewType:window.location.search.substr(1)};o.viewType||(o.viewType="program"),t.all(r.CATEGORYWITHCOLLAPSEDUNLOADEDCHILDREN).each(function(e){var t=e.getData("categoryid"),n=e.getData("depth");if(typeof t=="undefined"||typeof n=="undefined")return;this._toggle_generic_expansion({parentnode:e,childnode:e.one(r.CONTENTNODE),spinnerhandle:r.CATEGORYSPINNERLOCATION,data:{id:t,depth:n,type:i,categorytype:o.viewType}})},this),t.all(r.CATEGORYWITHCOLLAPSEDLOADEDCHILDREN).each(function(e){e.ancestor(r.CATEGORYWITHCOLLAPSEDLOADEDCHILDREN)?(e.removeClass(n.SECTIONCOLLAPSED),e.all(r.LOADEDTREES).removeClass(n.SECTIONCOLLAPSED)):s.push(e)},this),e.all(s).each(function(e){this.run_expansion(e)},this)},o.collapse_all=function(t){var i=[];t.all(r.CATEGORYWITHMAXIMISEDLOADEDCHILDREN).each(function(e){e.ancestor(r.CATEGORYWITHMAXIMISEDLOADEDCHILDREN)?i.push(e):this.run_expansion
(e)},this),e.all(i).each(function(e){e.addClass(n.SECTIONCOLLAPSED),e.all(r.LOADEDTREES).addClass(n.SECTIONCOLLAPSED)},this)},o.update_collapsible_actions=function(e){var t=!1,i=e.one(r.COLLAPSEEXPAND);if(!i)return;e.all(r.CATEGORYWITHMAXIMISEDLOADEDCHILDREN).each(function(e){return e.ancestor(r.CATEGORYWITHCOLLAPSEDLOADEDCHILDREN)?!1:(t=!0,!0)}),t?(i.setHTML(M.util.get_string("collapseall","moodle")).addClass(n.COLLAPSEALL).removeClass(n.DISABLED),require(["core/templates"],function(e){e.renderIcon("expanded").done(function(e){i.hasClass(n.COLLAPSEALL)&&(i.all(".flex-icon").remove(),i.prepend(e))})})):(i.setHTML(M.util.get_string("expandall","moodle")).removeClass(n.COLLAPSEALL).removeClass(n.DISABLED),require(["core/templates"],function(e){e.renderIcon("collapsed").done(function(e){i.hasClass(n.COLLAPSEALL)||(i.all(".flex-icon").remove(),i.prepend(e))})}))},o.process_results=function(t,r,i){var s,o;try{o=e.JSON.parse(r.responseText);if(o.error)return new M.core.ajaxException(o)}catch(u){return new M.core.exception(u)}s=e.Node.create(o),i.childnode.appendChild(s),i.parentnode.addClass(n.LOADED).removeClass(n.NOTLOADED),this.run_expansion(i.parentnode),i.spinner&&i.spinner.hide().destroy()},o.add_animation=function(t){return typeof t.fx!="undefined"?t:(t.plug(e.Plugin.NodeFX,{from:{height:0,opacity:0},to:{height:function(e){return e.get("scrollHeight")},opacity:1},duration:.2}),t)}},"@VERSION@",{requires:["node","event-key"]});
