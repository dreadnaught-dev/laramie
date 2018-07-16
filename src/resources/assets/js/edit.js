var handlebarsTemplates = [];
var wysiwygEditors = {};
var initSaveBoxOffset = 100;
var timer;

$(window).scroll(function() {
  clearTimeout(timer);
  timer = setTimeout(
    function() {
      var top = $(window).scrollTop();
      var saveBoxIsPinned = $(".save-box").hasClass("is-pinned");
      if (top >= initSaveBoxOffset && !saveBoxIsPinned) {
        $(".save-box").addClass("is-pinned");
      } else if (top < initSaveBoxOffset && saveBoxIsPinned) {
        $(".save-box").removeClass("is-pinned");
      }
    },
    50,
  ); // set slight delay to prevent debounce
});

$(document).ready(function() {
  loadHandlebarsTemplates();

  $("#edit-form").find(".aggregate-holder").each(function() {
    var $holder = $(this);
    var itemData = objectGet(window, "globals.aggregates", {});
    loadAggregateFields($holder, itemData);
  });

  // Update wysiwyg editors (needed for those inside aggregates -- the linked
  // hidden field values are updated by `loadAggregateFields`, but the editor's
  // don't pick those changes up).
  $("trix-editor").each(function() {
    this.editor.loadHTML(this.editor.element.value);
  });

  $("#edit-tabs a").on("click", function() {
    $("#edit-tabs li").removeClass("is-active");
    $(this).closest("li").addClass("is-active");

    var selectedTab = $(this).data("tab");
    var hasTabSelected = selectedTab !== "_main";
    $("#edit-form").toggleClass("has-tab-selected", hasTabSelected);
    $('[name="_selectedTab"]').val(selectedTab);

    $(".aggregate-outer-wrapper").removeClass("is-active");
    if (hasTabSelected) {
      $(".aggregate-outer-wrapper.tab-" + selectedTab).addClass("is-active");
    }
  });

  // Add error class to tabs if some input within that tab contains an error
  var errorMessages = globals.errorMessages || {};
  Object.keys(errorMessages).forEach(function(x) {
    if (!/[_]/.test(x)) {
      return;
    }
    var $errorField = $('[name="' + x + '"]').closest(".field");
    $errorField.toggleClass("is-danger", true);
    var errors = errorMessages[x];
    errors.forEach(function(errorMessage) {
      $errorField.append('<p class="help is-danger">' + errorMessage + "</p>");
    });
  });

  if ($("#edit-tabs").length > 0) {
    $(".field.is-danger").each(function() {
      var $tab = $(this).closest(".is-tab");
      if ($tab.length) {
        var classes = $tab.attr("class").split(/\s+/);
        for (var i = 0; i < classes.length; i++) {
          if (/^tab-/.test(classes[i])) {
            $('#edit-tabs [data-tab="' + classes[i].replace(/^tab\-/, "") + '"]').toggleClass("is-danger", true);
          }
        }
      } else {
        $('#edit-tabs [data-tab="_main"]').toggleClass("is-danger", true);
      }
    });
  }

  // Get all reference fields and populate their selects:
  $("#edit-form").on("click.toggle-reference-search", ".js-toggle-reference-search", function() {
    $searchWrapper = $(this).closest(".reference-wrapper").find(".reference-search");
    $searchWrapper.toggle();
    if (!$searchWrapper.data("loaded")) {
      $searchWrapper.data("loaded", true);
      doSearch($searchWrapper);

      $searchWrapper.find(".keywords").keyup(
        debounce(
          function() {
            $searchWrapper.find(".js-select-reference").addClass("is-loading");
            doSearch($searchWrapper);
          },
          500,
        ),
      );
    }
  });

  // toggle tags / revisions viewing prefs
  $(".js-toggle-prefs").on("click", function() {
    var $e = $(this).toggleClass("open").closest(".card").children(".card-content").toggleClass("is-hidden");
    var hideTags = $("#tags-card-content").hasClass("is-hidden") ? "1" : "0";
    var hideRevisions = $("#revisions-card-content").hasClass("is-hidden") ? "1" : "0";
    $.post(globals.adminUrl + "/ajax/edit-prefs", { hideTags: hideTags, hideRevisions: hideRevisions });
  });

  $("#edit-form").on("click.clear-reference-selection", ".js-clear-reference-select", function() {
    $(this).closest(".reference-wrapper").find(":input:checked").prop("checked", false);
    $(this).parent().find(".js-select-reference").trigger("click");
  });

  $("#edit-form").on(
    "keyup.update-markdown",
    ".markdown",
    debounce(
      function() {
        var $textarea = $(this);
        $.post(globals.adminUrl + "/ajax/markdown", { markdown: $textarea.val() }, function(data) {
          $textarea.closest(".columns").find(".markdown-html").html(data.html);
        });
      },
      300,
    ),
  );

  $(".js-compare-revisions").on("click", function() {
    $("#revision-diff").html("Loading...");
    $(".modal").toggleClass("is-active");
    $.event.trigger("modal-change");
    $("#revision-diff").load($(this).attr("href"));
    return false;
  });

  $(".js-hide-modal").on("click", function() {
    $(".modal").toggleClass("is-active", false);
    $.event.trigger("modal-change");
  });

  $("#edit-form").on("click.now-timestamp", ".js-set-timestamp-now", function(e) {
    var d = new Date();

    var year = String(d.getFullYear());
    var month = String(d.getMonth() + 1).padStart(2, "0");
    var day = String(d.getDate()).padStart(2, "0");

    var hours = String(d.getHours()).padStart(2, "0");
    var minutes = String(d.getMinutes()).padStart(2, "0");
    var seconds = "00";

    var date = [ year, month, day ].join("-");
    var time = [ hours, minutes, seconds ].join(":");

    $field = $(this).closest(".field");
    $field.find('[name$="-date"]').val(date);
    $field.find('[name$="-time"]').val(time);
    $field.find('[name$="-timestamp"]').val(Intl.DateTimeFormat().resolvedOptions().timeZone);
  });

  $("#edit-form").on("click.now-timestamp", ".js-clear-timestamp", function(e) {
    $field = $(this).closest(".field");
    $field.find('[name$="-date"]').val("");
    $field.find('[name$="-time"]').val("");
  });

  $("#edit-form").on("click.add-aggregate", ".js-add-aggregate", function(e) {
    var $target = $(e.target);
    var $holder = $target.parent().next();
    var keys = $holder.data("keys");
    var numChildren = $holder.children(".media").length;
    var maxItems = $holder.data("maxItems");
    if (maxItems > 0 && numChildren >= $holder.data("maxItems")) {
      alert("You have maxed out the number of items you may add");
      return;
    }
    // Tap into the recursive loadAggregateFields function to add additional aggregates.
    loadAggregateFields($holder, {}, keys, true);
    defaultEmptyTimezonesToBrowserValue();
  });

  $("#edit-form").on("click.remove-aggregate", ".js-remove-aggregate", function(e) {
    var $target = $(e.target);
    var $aggregate = $target.closest(".media");
    var $holder = $target.closest(".aggregate-holder");
    var numChildren = $holder.children(".media").length;
    var minItems = $holder.data("minItems");
    if (numChildren <= minItems) {
      alert("You may not remove this item -- you must have at least " + minItems);
      return;
    }
    if (confirm("Remove this item?")) {
      $aggregate.remove();
    }
    var numItems = $holder.children().length;
    if (numItems == 0) {
      $holder.append('<p class="js-temporary">' + $holder.data("emptyMessage") + "</p>");
    }
  });

  $("#edit-form").on("click.select-reference", ".js-select-reference", function() {
    var isFileSelect = $(this).hasClass("is-file-select");
    var $referenceWrapper = $(this).closest(".reference-wrapper");
    var $searchWrapper = $referenceWrapper.find(".reference-search");
    var selectedItems = $searchWrapper.find(":input:checked").toArray();
    var $refIds = $referenceWrapper.find("input.reference-ids").val(
      selectedItems.map(function(e) {
        return $(e).val();
      }).join("|"),
    );
    if (isFileSelect) {
      $refIds.prop("checked", true);
    }
    var selectionInfoContent = "Nothing selected";
    if (selectedItems.length > 0) {
      selectionInfoContent = selectedItems.map(function(e) {
        return '<em><a href="' + $referenceWrapper.find(".selection-info").data("baseUrl").replace(/new$/, $(e).val()) +
          '"' +
          (isFileSelect ? ' onclick="dynamicFileHref(this)"' : "") +
          ' target="_blank">' +
          $(e).data("label") +
          "</a></em>";
      }).join(", ");
    }
    $referenceWrapper
      .toggleClass("has-file", selectedItems.length > 0)
      .find(".selection-info")
      .html(selectionInfoContent)
      .end()
      .find(".hide-when-file")
      .hide();
    $searchWrapper.toggle(false);
  });

  $(".js-dismissable").click(function() {
    $(this).closest(".dismissable-wrapper").hide();
  });

  $(".js-save").click(function() {
    $("#edit-form").submit();
  });

  $(".js-cancel").click(function() {
    if (confirm("Cancel edit? Any unsaved changes will be lost.")) {
      history.back();
    }
  });

  $(".js-delete").click(function() {
    if (confirm("Delete this item?")) {
      $("#delete-form").submit();
    }
  });

  $(".js-delete-revision").click(function() {
    if (confirm("Delete this revision?")) {
      $link = $(this);
      $.post($link.attr("href"), function(data) {
        $link.closest(".revision-item").hide();
      });
    }
  });

  $(".js-restore-revision").click(function() {
    // Are you sure you want to restore to this revision? The revision from December 28 at 3:07 PM will become your current revision.
    if (confirm("Restore this revision? Any unsaved changes will be lost.")) {
      $(this).closest(".revision-item").find(".restore-revision-form").submit();
    }
  });

  $("a.show-more-link").click(function() {
    $(this).closest(".revision-history").toggleClass("show-more", true);
  });
  $("a.show-less-link").click(function() {
    $(this).closest(".revision-history").toggleClass("show-more", false);
  });

  loadMeta(globals.metaId, function() {
    var qs = getQS(location.search);
    if (qs["highlight-comment"]) {
      $("#comment-" + qs["highlight-comment"]).addClass("is-highlighted");
      $("html, body").animate({ scrollTop: $("#comment-" + qs["highlight-comment"]).offset().top - 300 }, 150);
    }
  });

  defaultEmptyTimezonesToBrowserValue();
});

function getQS(querystring) {
  querystring = querystring || "";
  querystring = querystring.replace("?", "");
  var qs = {};
  var kvps = querystring.split("&");
  for (i = 0; i < kvps.length; i++) {
    var kvp = kvps[i].split("=");
    if (kvp.length != 2) {
      continue;
    }
    qs[kvp[0]] = kvp[1];
  }
  return qs;
}

function dynamicFileHref(e) {
  var tmp = $(e).text().trim();
  var isImage = /\.(gif|jpg|jpeg|png)$/i.test(tmp);
  var assetKey = $(e).closest("label").find("input:checkbox").val();

  if (assetKey) {
    $(e).attr("href", (isImage ? globals.cropperBase : globals.fileDownloadBase) + assetKey);
    return true;
  }

  return false;
}

function randString(length) {
  var alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
  length = length || 10;

  var output = [];
  for (var i = 0; i < length; i++) {
    output.push(alphabet.charAt(Math.floor(Math.random() * alphabet.length)));
  }

  return output.join("");
}

function doSearch($searchWrapper) {
  var selectedItems = ($searchWrapper.closest(".reference-wrapper").find(".reference-ids").val() || "").split(/[|,]/g);
  var tmp = $searchWrapper.find(":input:checked").each(function() {
    selectedItems.push($(this).val());
  });
  var keywords = $searchWrapper.find(".keywords").val();
  var lookupSubtype = $searchWrapper.data("lookupSubtype");
  var isSingleReference = $searchWrapper.data("isSingleReference") == "1";
  $.getJSON(
    globals.adminUrl + "/ajax/" + $searchWrapper.data("type") + "/" + $searchWrapper.data("lookupType"),
    {
      selectedItems: selectedItems.join("|"),
      keywords: keywords,
      lookupSubtype: lookupSubtype,
      itemId: globals.metaId,
    },
    function(data) {
      $searchWrapper.find(".js-select-reference").removeClass("is-loading");
      $searchWrapper.find(".panel-block.option").remove();
      var template = handlebarsTemplates["reference-" + (isSingleReference ? "single" : "many") + "-option"];
      $searchWrapper.find(".panel-block.search").after(template(data));
    },
  );
}

function loadHandlebarsTemplates(itemData) {
  $('script[type="text/x-handlebars-template"]').each(function() {
    var $template = $(this);
    handlebarsTemplates[$template.attr("id")] = Handlebars.compile($template.html());
  });
}

function loadAggregateFields($holder, itemData, keys, isAddNew) {
  isAddNew = !!isAddNew;

  $holder.find(".js-temporary").remove();

  // Because of how `loadAggregateFields` works -- which is by iterating
  // over child .aggregate-holders, we need to perform a check so that we
  // don't dive into an aggregate, pop out of recursion, and then dive
  // back into children that have already been processed -- specifically
  // this could happen when recursing into a repeated aggregate (when
  // popping back to the sibling level and diving back in again).
  // Do a check to ensure we don't dive into items repeatedly
  if (!isAddNew && $holder.data("processed") == "1") {
    return true; // aka, `continue` in jQuery
  }

  var templateName = $holder.data("template");
  var type = $holder.data("type");
  var isRepeatable = $holder.data("isRepeatable") == "1";

  var data = objectGet(itemData, type, isRepeatable ? [] : {});
  var template = handlebarsTemplates[templateName];
  keys = $.extend({}, keys || {});
  $holder.data("keys", $.extend({}, keys));
  if (data && isRepeatable) {
    // Min number of items to show on forms -- also used in css to hide the delete button from n < minItems items
    var minItems = isAddNew ? 1 : Math.max(data.length, $holder.data("minItems"));
    for (var i = 0; i < minItems; i++) {
      var subData = i < data.length ? data[i] : {};
      keys[type + "Key"] = subData["_key"] || getKey();
      $newItem = $(template(keys));
      $holder.append($newItem);

      // Load data
      loadAggregateFieldsHelper(subData, $newItem);

      // Dive into children
      $holder.find(".aggregate-holder").each(function() {
        loadAggregateFields($(this), subData, keys);
      });
    }
    if (minItems == 0) {
      $holder.append('<p class="js-temporary">' + $holder.data("emptyMessage") + "</p>");
    }

    if (!isAddNew) {
      // Make repeated items draggable
      var drake = dragula([ $holder[0] ], {
        moves: function(el, container, handle) {
          return handle.classList.contains("drag-" + type);
        },
      });
      drake.on("drag", function() {
        $holder.children(".media").addClass("shrink-for-move");
      });
      drake.on("dragend", function() {
        $holder.children(".media").removeClass("shrink-for-move");
      });
    }
  } else if (data) {
    keys[type + "Key"] = data["_key"] || getKey();
    $newItem = $(template(keys));
    $holder.append($newItem);

    loadAggregateFieldsHelper(data, $newItem);

    // Dive into children
    $holder.find(".aggregate-holder").each(function() {
      loadAggregateFields($(this), data, keys);
    });
  }

  // Set state so we don't dive into items repeatedly (repeatable children)
  $holder.data("processed", "1");
}

function loadAggregateFieldsHelper(data, $newItem) {
  for (var inputKey in data) {
    var inputValue = data[inputKey];
    // All file fields will have an extension (true? what about files without?):
    if (hasProperty(inputValue, "extension")) {
      window.tmp = $newItem;
      $newItem
        .find("[name$=" + inputKey + "]:checkbox")
        .closest(".file-wrapper")
        .addClass("has-file")
        .find("[name$=" + inputKey + "]:checkbox")
        .prop("checked", true)
        .val(inputValue.uploadKey)
        .parent()
        .find(".js-file-name")
        .html(
          '<img class="filetype-icon" src="' + globals.adminUrl + "/assets/icon/" + inputValue.uploadKey + '_50">' +
            inputValue.name,
        );
    } else if (hasProperty(inputValue, "encryptedValue")) {
      $newItem
        .find(".password-wrapper")
        .addClass("has-password")
        .find("[name$=" + inputKey + "]:checkbox")
        .prop("checked", true)
        .val(inputValue.password);
    } else if (hasProperty(inputValue, "timestamp")) {
      $newItem.find("[name$=" + inputKey + "-date]").val(inputValue.date);
      $newItem.find("[name$=" + inputKey + "-time]").val(inputValue.time);
      $newItem.find("[name$=" + inputKey + "-timezone]").val(inputValue.timezone);
    } else if (hasProperty(inputValue, "markdown")) {
      $newItem
        .find("[name$=" + inputKey + "]")
        .val(inputValue.markdown)
        .closest(".columns")
        .find(".markdown-html")
        .html(inputValue.html);
    } else if (typeof inputValue == "boolean") {
      $newItem.find("[id$=" + inputKey + "-" + (inputValue ? "yes" : "no") + "]").prop("checked", true);
    } else if (
      hasProperty(inputValue, "id") ||
        Array.isArray(inputValue) && inputValue.length > 0 && hasProperty(inputValue[0], "id")
    ) {
      var tmp = (Array.isArray(inputValue) ? inputValue : [ inputValue ]).filter(function(e) {
        return !!e && e.id !== null;
      });
      $newItem.find("[name$=" + inputKey + "]").val(
        tmp.map(function(e) {
          return e.id;
        }).join("|"),
      );
      if (tmp.length > 0) {
        $newItem.find(".selection-info").html(
          tmp.map(function(e) {
            return '<em><a href="' + $newItem.find(".selection-info").data("baseUrl").replace(/new$/, e.id) +
              '" target="_blank">' +
              e._alias +
              "</a></em>";
            //return '<em>'+ e._alias +'</em>';
          }).join(", "),
        );
      }
    } else {
      $newItem.find("[name$=" + inputKey + "]").val(inputValue).trigger("change");
    }
  }
}

// Default unselected timestamp timezones to use the browser's timezone.
function defaultEmptyTimezonesToBrowserValue() {
  $(".timezone-timestamp").each(function() {
    $timezone = $(this);
    if (!$timezone.val()) {
      $timezone.val(Intl.DateTimeFormat().resolvedOptions().timeZone);
    }
  });
}

function getKey() {
  return randString();
}

// NOTE: for various ways to accomplish this, see: http://stackoverflow.com/a/41532415
function objectGet(obj, path, returnValueIfNull) {
  try {
    for (var i = 0, path = path.split("."), pathLength = path.length; i < pathLength; i++) {
      obj = obj[path[i]];
    }

    return obj !== undefined ? obj : returnValueIfNull;
  } catch (e) {
    return returnValueIfNull;
  }
}

function hasProperty(obj, key) {
  return obj !== null && typeof obj === "object" && obj.hasOwnProperty(key);
}

//http://davidwalsh.name/javascript-debounce-function
function debounce(func, wait, immediate) {
  var timeout;
  return function() {
    var context = this, args = arguments;
    var later = function() {
      timeout = null;
      if (!immediate)
        func.apply(context, args);
    };
    var callNow = immediate && !timeout;
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
    if (callNow)
      func.apply(context, args);
  };
}
