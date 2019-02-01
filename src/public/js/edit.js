var timer,handlebarsTemplates=[],wysiwygEditors={},initSaveBoxOffset=100;function getQS(e){var t={},a=(e=(e=e||"").replace("?","")).split("&");for(i=0;i<a.length;i++){var n=a[i].split("=");2==n.length&&(t[n[0]]=n[1])}return t}function dynamicFileHref(e){var t=$(e).text().trim(),a=/\.(gif|jpg|jpeg|png)$/i.test(t),i=$(e).closest("label").find("input:checkbox").val();return!!i&&($(e).attr("href",(a?globals.cropperBase:globals.fileDownloadBase)+i),!0)}function randString(e){var t="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";e=e||10;for(var a=[],i=0;i<e;i++)a.push(t.charAt(Math.floor(Math.random()*t.length)));return a.join("")}function doSearch(a){var e=(a.closest(".reference-wrapper").find(".reference-ids").val()||"").split(/[|,]/g),t=(a.find(":input:checked").each(function(){e.push($(this).val())}),a.find(".keywords").val()),i=a.data("lookupSubtype"),n="1"==a.data("isSingleReference");$.getJSON(globals.adminUrl+"/ajax/"+a.data("type")+"/"+a.data("lookupType"),{selectedItems:e.join("|"),keywords:t,lookupSubtype:i,itemId:globals.metaId},function(e){a.find(".js-select-reference").removeClass("is-loading"),a.find(".panel-block.option").remove();var t=handlebarsTemplates["reference-"+(n?"single":"many")+"-option"];a.find(".panel-block.search").after(t(e))})}function loadHandlebarsTemplates(e){$('script[type="text/x-handlebars-template"]').each(function(){var e=$(this);handlebarsTemplates[e.attr("id")]=Handlebars.compile(e.html())})}function loadAggregateFields(e,t,a,i){if(i=!!i,e.find(".js-temporary").remove(),!i&&"1"==e.data("processed"))return!0;var n=e.data("template"),s=e.data("type"),r="1"==e.data("isRepeatable"),o=objectGet(t,s,r?[]:{}),l=handlebarsTemplates[n];if(a=$.extend({},a||{}),e.data("keys",$.extend({},a)),o&&r){for(var c=i?1:Math.max(o.length,e.data("minItems")),d=0;d<c;d++){var m=d<o.length?o[d]:{};a[s+"Key"]=m._key||getKey(),$newItem=$(l(a)),e.append($newItem),loadAggregateFieldsHelper(m,$newItem),e.find(".aggregate-holder").each(function(){loadAggregateFields($(this),m,a)})}if(0==c&&e.append('<p class="js-temporary">'+e.data("emptyMessage")+"</p>"),!i){var f=dragula([e[0]],{moves:function(e,t,a){return a.classList.contains("drag-"+s)}});f.on("drag",function(){e.children(".media").addClass("shrink-for-move")}),f.on("dragend",function(){e.children(".media").removeClass("shrink-for-move")})}}else o&&(a[s+"Key"]=o._key||getKey(),$newItem=$(l(a)),e.append($newItem),loadAggregateFieldsHelper(o,$newItem),e.find(".aggregate-holder").each(function(){loadAggregateFields($(this),o,a)}));e.data("processed","1")}function loadAggregateFieldsHelper(e,t){for(var a in e){var i=e[a];if(null!==i){var n=t.find('[data-field-key="'+a+'"]');switch(n.data("fieldType")){case"file":n.find("[name$="+a+"]:checkbox").closest(".file-wrapper").addClass("has-file").find("[name$="+a+"]:checkbox").prop("checked",!0).val(i.uploadKey).parent().find(".js-file-name").html('<img class="filetype-icon" src="'+globals.adminUrl+"/assets/icon/"+i.uploadKey+'_50">'+i.name);break;case"password":n.find(".password-wrapper").addClass("has-password").find("[name$="+a+"]:checkbox").prop("checked",!0).val(i.encryptedValue);break;case"timestamp":n.find("[name$="+a+"-date]").val(i.date),n.find("[name$="+a+"-time]").val(i.time),n.find("[name$="+a+"-timezone]").val(i.timezone);break;case"markdown":n.find("[name$="+a+"]").val(i.markdown).closest(".columns").find(".markdown-html").html(i.html);break;case"checkbox":n.find("[name$="+a+"]:checkbox").prop("checked",i);break;case"boolean":n.find("[id$="+a+"-"+(i?"yes":"no")+"]").prop("checked",!0);break;case"radio":for(var s=n.find(":radio").toArray(),r=0,o=!1;r<s.length&&!o;r++){var l=$(s[r]);l.val()==i&&(o=!0,l.prop("checked",!0))}break;case"reference":var c=(Array.isArray(i)?i:[i]).filter(function(e){return!!e&&null!==e.id});n.find("[name$="+a+"]").val(c.map(function(e){return e.id}).join("|")),0<c.length&&n.find(".selection-info").html(c.map(function(e){return'<em><a href="'+n.find(".selection-info").data("baseUrl").replace(/new$/,e.id)+'?is-child=1" target="_blank">'+e._alias+"</a></em>"}).join(", "));break;case"select":if(Array.isArray(i))for(r=0;r<i.length;r++)n.find("[name$="+a+'\\[\\]] option[value="'+i[r]+'"]').prop("selected",!0);else n.find("[name$="+a+"]").val(i);n.find("[name$="+a+"\\[\\]]").trigger("change");break;default:n.find("[name$="+a+"]").val(i).trigger("change")}}}}function defaultEmptyTimezonesToBrowserValue(){$(".timezone-timestamp").each(function(){$timezone=$(this),$timezone.val()||$timezone.val(Intl.DateTimeFormat().resolvedOptions().timeZone)})}function transformSelectsToSelect2(){$("select[multiple]:not(.select2-hidden-accessible), select.select2:not(.select2-hidden-accessible)").select2()}function getKey(){return randString()}function objectGet(e,t,a){try{for(var i=0,n=(t=t.split(".")).length;i<n;i++)e=e[t[i]];return void 0!==e?e:a}catch(e){return a}}function hasProperty(e,t){return null!==e&&"object"==typeof e&&e.hasOwnProperty(t)}function debounce(i,n,s){var r;return function(){var e=this,t=arguments,a=s&&!r;clearTimeout(r),r=setTimeout(function(){r=null,s||i.apply(e,t)},n),a&&i.apply(e,t)}}$(window).scroll(function(){clearTimeout(timer),timer=setTimeout(function(){var e=$(window).scrollTop(),t=$(".save-box").hasClass("is-pinned");initSaveBoxOffset<=e&&!t?$(".save-box").addClass("is-pinned"):e<initSaveBoxOffset&&t&&$(".save-box").removeClass("is-pinned")},50)}),$(document).ready(function(){loadHandlebarsTemplates(),$(".edit-container").find(".aggregate-holder").each(function(){var e=$(this).closest(".edit-container").data("itemId");loadAggregateFields($(this),objectGet(window,"globals.aggregates."+e,{})),$.event.trigger("aggregates-loaded")}),$("trix-editor").each(function(){this.editor.loadHTML(this.editor.element.value)}),$("#edit-tabs a").on("click",function(){$("#edit-tabs li").removeClass("is-active"),$(this).closest("li").addClass("is-active");var e=$(this).data("tab"),t="_main"!==e;$("#edit-form").toggleClass("has-tab-selected",t),$('[name="_selectedTab"]').val(e),$(".aggregate-outer-wrapper").removeClass("is-active"),t&&$(".aggregate-outer-wrapper.tab-"+e).addClass("is-active")});var a=globals.errorMessages||{};Object.keys(a).forEach(function(e){if(/[_]/.test(e)){var t=$('[name="'+e+'"]').closest(".field");t.toggleClass("is-danger",!0),a[e].forEach(function(e){t.append('<p class="help is-danger">'+e+"</p>")})}}),0<$("#edit-tabs").length&&$(".field.is-danger").each(function(){var e=$(this).closest(".is-tab");if(e.length)for(var t=e.attr("class").split(/\s+/),a=0;a<t.length;a++)/^tab-/.test(t[a])&&$('#edit-tabs [data-tab="'+t[a].replace(/^tab\-/,"")+'"]').toggleClass("is-danger",!0);else $('#edit-tabs [data-tab="_main"]').toggleClass("is-danger",!0)}),$("#edit-form").on("click.toggle-reference-search",".js-toggle-reference-search",function(){$searchWrapper=$(this).closest(".reference-wrapper").find(".reference-search"),$searchWrapper.toggle(),$searchWrapper.data("loaded")||($searchWrapper.data("loaded",!0),doSearch($searchWrapper),$searchWrapper.find(".keywords").keyup(debounce(function(){$searchWrapper.find(".js-select-reference").addClass("is-loading"),doSearch($searchWrapper)},500)))}),$(".js-toggle-prefs").on("click",function(){$(this).toggleClass("open").closest(".card").children(".card-content").toggleClass("is-hidden");var e=$("#tags-card-content").hasClass("is-hidden")?"1":"0",t=$("#revisions-card-content").hasClass("is-hidden")?"1":"0";$.post(globals.adminUrl+"/ajax/edit-prefs",{hideTags:e,hideRevisions:t})}),$("#edit-form").on("click.clear-reference-selection",".js-clear-reference-select",function(){$(this).closest(".reference-wrapper").find(":input:checked").prop("checked",!1),$(this).parent().find(".js-select-reference").trigger("click")}),$("#edit-form").on("keyup.update-markdown",".markdown",debounce(function(){var t=$(this);t.closest(".columns").find(".markdown-html").is(":visible")&&$.post(globals.adminUrl+"/ajax/markdown",{markdown:t.val()},function(e){t.closest(".columns").find(".markdown-html").html(e.html)})},300)),$(document).on("click.preview-markdown",".js-preview-markdown",function(e){var t=$("#markdown-preview-modal").find(".content").html("Loading...").end().toggleClass("is-active"),a=$(e.target).closest(".columns").find("textarea");$.post(globals.adminUrl+"/ajax/markdown",{markdown:a.val()},function(e){t.find(".content").html(e.html)})}),$(".js-compare-revisions").on("click",function(){return $("#revision-diff").html("Loading..."),$("#revisions-modal").toggleClass("is-active"),$.event.trigger("modal-change"),$("#revision-diff").load($(this).attr("href")),!1}),$(".js-hide-modal").on("click",function(){$(".modal.is-active").toggleClass("is-active",!1),$.event.trigger("modal-change")}),$("#edit-form").on("click.now-timestamp",".js-set-timestamp-now",function(e){var t=new Date,a=String(t.getFullYear()),i=String(t.getMonth()+1).padStart(2,"0"),n=String(t.getDate()).padStart(2,"0"),s=String(t.getHours()).padStart(2,"0"),r=String(t.getMinutes()).padStart(2,"0"),o=[a,i,n].join("-"),l=[s,r,"00"].join(":");$field=$(this).closest(".field"),$field.find('[name$="-date"]').val(o),$field.find('[name$="-time"]').val(l),$field.find('[name$="-timestamp"]').val(Intl.DateTimeFormat().resolvedOptions().timeZone)}),$("#edit-form").on("click.now-timestamp",".js-clear-timestamp",function(e){$field=$(this).closest(".field"),$field.find('[name$="-date"]').val(""),$field.find('[name$="-time"]').val("")}),$("#edit-form").on("click.add-aggregate",".js-add-aggregate",function(e){var t=$(e.target).parent().next(),a=t.data("keys"),i=t.children(".media").length;0<t.data("maxItems")&&i>=t.data("maxItems")?alert("You have maxed out the number of items you may add"):(loadAggregateFields(t,{},a,!0),defaultEmptyTimezonesToBrowserValue(),transformSelectsToSelect2())}),$("#edit-form").on("click.remove-aggregate",".js-remove-aggregate",function(e){var t=$(e.target),a=t.closest(".media"),i=t.closest(".aggregate-holder"),n=i.children(".media").length,s=i.data("minItems");n<=s?alert("You may not remove this item -- you must have at least "+s):(confirm("Remove this item?")&&a.remove(),0==i.children().length&&i.append('<p class="js-temporary">'+i.data("emptyMessage")+"</p>"))}),$("#edit-form").on("click.select-reference",".js-select-reference",function(){var t=$(this).hasClass("is-file-select"),a=$(this).closest(".reference-wrapper"),e=a.find(".reference-search"),i=e.find(":input:checked").toArray(),n=a.find("input.reference-ids").val(i.map(function(e){return $(e).val()}).join("|"));t&&n.prop("checked",!0);var s="Nothing selected";0<i.length&&(s=i.map(function(e){return'<em><a href="'+a.find(".selection-info").data("baseUrl").replace(/new$/,$(e).val())+'"'+(t?' onclick="dynamicFileHref(this)"':"")+' target="_blank">'+$(e).data("label")+"</a></em>"}).join(", ")),a.toggleClass("has-file",0<i.length).find(".selection-info").html(s).end().find(".hide-when-file").hide(),e.toggle(!1)}),$(".js-dismissable").click(function(){$(this).closest(".dismissable-wrapper").hide()}),$(".js-save").click(function(){$("#edit-form").submit()}),$(".js-cancel-edit").click(function(){return confirm("Cancel edit? Any unsaved changes will be lost.")}),$(".js-delete").click(function(){confirm("Delete this item?")&&$("#delete-form").submit()}),$(".js-delete-revision").click(function(){confirm("Delete this revision?")&&($link=$(this),$.post($link.attr("href"),function(e){$link.closest(".revision-item").hide()}))}),$(".js-restore-revision").click(function(){confirm("Restore this revision? Any unsaved changes will be lost.")&&$(this).closest(".revision-item").find(".restore-revision-form").submit()}),$("a.show-more-link").click(function(){$(this).closest(".revision-history").toggleClass("show-more",!0)}),$("a.show-less-link").click(function(){$(this).closest(".revision-history").toggleClass("show-more",!1)}),loadMeta(globals.metaId,function(){var e=getQS(location.search);e["highlight-comment"]&&($("#comment-"+e["highlight-comment"]).addClass("is-highlighted"),$("html, body").animate({scrollTop:$("#comment-"+e["highlight-comment"]).offset().top-300},150))}),defaultEmptyTimezonesToBrowserValue(),transformSelectsToSelect2()});