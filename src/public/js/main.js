"use strict";function loadMeta(e,a){a=a||null,$(".tags-wrapper").html("Loading..."),$(".comments-wrapper").html("Loading..."),$(".meta-wrapper").data("itemId",e);var t=$(".meta-wrapper").data("loadMetaEndpoint");t&&$.getJSON(t.replace("_id_",e),function(e){var t=handlebarsTemplates["tag-list-template"];$(".tags-wrapper").html(t(e));t=handlebarsTemplates["comment-list-template"];$(".comments-wrapper").html(t(e)),"function"==typeof a&&setTimeout(a,100)})}function getQS(e){for(var t={},a=(e=(e=e||"").replace("?","")).split("&"),n=0;n<a.length;n++){var r=a[n].split("=");2==r.length&&(t[r[0]]=r[1])}return t}function flattenQSObject(e){var t=[];for(var a in e)t.push(encodeURIComponent(a)+"="+encodeURIComponent(e[a]));return t.join("&")}$(document).ready(function(){$("#nav-toggle").click(function(e){$(e.target).toggleClass("is-active"),$("#nav-menu").toggleClass("is-active")}),new Tribute({values:globals.systemUsers.map(function(e){return{key:e=e.replace(/@.*$/,""),value:e}}),noMatchTemplate:function(){return null}}).attach($(".mentionable")),$.ajaxSetup({headers:{"X-CSRF-TOKEN":globals._token}}),$(".modal-background").click(function(e){$(e.target).closest(".modal").removeClass("is-active"),$.event.trigger("modal-change")}),$(document).on("modal-change",function(){var e=0<$(".modal.is-active").length;$("html").toggleClass("prevent-scroll",e)}),$(".is-dismiss-alert").on("click.dismiss-alert",function(e){var t=$(e.target),a=t.closest(".navbar-dropdown");$.post(globals.adminUrl+"/ajax/dismiss-alert/"+t.data("id"),function(e){t.closest(".navbar-item").slideUp(function(){t.remove(),0==a.children().length&&a.append('<div class="navbar-item"><p>No new notifications.</p></div>')})})}),$(".meta-wrapper").on("click.remove-meta",".is-delete",function(e){var t=$(e.target),a=t.hasClass("is-comment-delete")?"comment":"tag",n=$(".meta-wrapper").data("itemId"),r=t.data("id");if(confirm("Remove this "+a+"?")){var l=$(".meta-wrapper").data("loadMetaEndpoint").replace("_id_","delete/"+r);$.post(l,function(e){t.closest("article, .control").remove(),$.event.trigger("meta-change",{id:n,meta:e})})}}),$(".meta-form").submit(function(e){var a=$(e.target),n=a.data("type"),r=$(".meta-wrapper").data("itemId"),t=$(".meta-wrapper").data("loadMetaEndpoint").replace("_id_",r+"/add-"+n);$.post(t,{meta:$(".meta-"+n).val()},function(e){var t=handlebarsTemplates[n+"-list-template"];$("."+n+"s-wrapper").html(t(e)),a.find("input, textarea").val(""),$.event.trigger("meta-change",{id:r,meta:e})})})});