$(document).ready(function() {
  $("#nav-toggle").click(function() {
    $(this).toggleClass("is-active");
    $("#nav-menu").toggleClass("is-active");
  });

  var tribute = new Tribute({
    values: globals.systemUsers.map(function(item) {
      item = item.replace(/@.*$/, "");
      return { key: item, value: item };
    }),
    noMatchTemplate: function() {
      return null;
    },
  });

  tribute.attach($(".mentionable"));

  $.ajaxSetup({ headers: { "X-CSRF-TOKEN": globals._token } });

  $(".modal-background").click(function() {
    $(this)
      .closest(".modal")
      .removeClass("is-active");
    $.event.trigger("modal-change");
  });

  $(document).on("modal-change", function() {
    var isPreventBodyScroll = $(".modal.is-active").length > 0;
    $("html").toggleClass("prevent-scroll", isPreventBodyScroll);
  });

  $(".is-dismiss-alert").on("click.dismiss-alert", function() {
    var $item = $(this);
    var $holder = $(this).closest(".navbar-dropdown");
    $.post(globals.adminUrl + "/ajax/dismiss-alert/" + $(this).data("id"), function(data) {
      $item.closest(".navbar-item").slideUp(function() {
        $(this).remove();
        if ($holder.children().length == 0) {
          $holder.append('<div class="navbar-item"><p>No new notifications.</p></div>');
        }
      });
    });
  });

  $(".meta-wrapper").on("click.remove-meta", ".is-delete", function() {
    var type = $(this).hasClass("is-comment-delete") ? "comment" : "tag";
    var itemId = $(".meta-wrapper").data("itemId");
    var metaId = $(this).data("id");
    if (confirm("Remove this " + type + "?")) {
      var $deleteLink = $(this);
      var action = $(".meta-wrapper")
        .data("loadMetaEndpoint")
        .replace("_id_", "delete/" + metaId);
      $.post(action, function(data) {
        $deleteLink.closest("article, .control").remove();
        $.event.trigger("meta-change", { id: itemId, meta: data });
      });
    }
  });

  $(".meta-form").submit(function() {
    var form = $(this);
    var type = $(this).data("type");
    var itemId = $(".meta-wrapper").data("itemId");
    var action = $(".meta-wrapper")
      .data("loadMetaEndpoint")
      .replace("_id_", itemId + "/add-" + type);
    $.post(action, { meta: $(".meta-" + type).val() }, function(data) {
      var template = handlebarsTemplates[type + "-list-template"];
      $("." + type + "s-wrapper").html(template(data));
      form.find("input, textarea").val("");
      $.event.trigger("meta-change", { id: itemId, meta: data });
    });
  });
});

function loadMeta(itemId, callback) {
  callback = callback || null;
  $(".tags-wrapper").html("Loading...");
  $(".comments-wrapper").html("Loading...");
  $(".meta-wrapper").data("itemId", itemId);
  var action = $(".meta-wrapper")
    .data("loadMetaEndpoint")
    .replace("_id_", itemId);
  $.getJSON(action, function(data) {
    var template = handlebarsTemplates["tag-list-template"];
    $(".tags-wrapper").html(template(data));
    template = handlebarsTemplates["comment-list-template"];
    $(".comments-wrapper").html(template(data));
    if (typeof callback == "function") {
      setTimeout(callback, 100);
    }
  });
}

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

function flattenQSObject(qsObject) {
  var data = [];
  for (var key in qsObject) {
    data.push(encodeURIComponent(key) + "=" + encodeURIComponent(qsObject[key]));
  }
  return data.join('&');
}

