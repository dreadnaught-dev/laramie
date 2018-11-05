var handlebarsTemplates = [];
var filterIndex = 1;

$(document).ready(function() {
  loadHandlebarsTemplates();
  loadFilters();

  $(".js-toggle-page-settings").on("click", function() {
    $("#page-settings").toggleClass("is-active");
    $.event.trigger("modal-change");
  });
  dragula([ $("#selectable-fields")[0] ]);

  $(".js-toggle-save-report").on("click", function() {
    $("#save-report-modal").toggleClass("is-active");
    $.event.trigger("modal-change");
  });

  // We're posting this via the main list form as that's where all the
  // filters are -- and we need those filters for bulk actions as well.
  $("#save-report-form").submit(function() {
    var reportName = ($(this).find("#modal-report-name").val() || "").trim();

    if (!reportName) {
      alert("You must provide a report name.");
      return false;
    }

    $("#is-filtering").val("");
    var $listForm = $("#list-form");
    $listForm
      .attr("method", "post")
      .attr("action", $listForm.data("saveReportAction"))
      .append($('<div style="display:none"></div>').append($(this).find(":input").clone()))
      .submit();
    return false;
  });

  // We're posting this via the main list form as that's where all the
  // filters are -- and we need those filters for bulk actions as well.
  $("#save-list-prefs-form").submit(function() {
    $("#is-filtering").val("");
    var $listForm = $("#list-form");
    $listForm
      .attr("method", "post")
      .attr("action", $listForm.data("saveListPrefsAction"))
      .append($('<div style="display:none"></div>').append($(this).find(":input").clone()))
      .submit();
    return false;
  });

  $(".js-select-all, .js-item-id").click(function() {
    var isChecked = $(this).is(":checked");

    if ($(this).is(".js-select-all")) {
      $("#main-list-table .js-item-id").prop("checked", isChecked);
    } else if (!isChecked) {
      $(".js-select-all").prop("checked", false);
    }

    var isAllSelected = $('.js-select-all').is(':checked')
      || $('.js-item-id').length == $('.js-item-id:checked').length;

    if (isAllSelected && $("#bulk-action-helper").data("hasAdditionalPages") == "1") {
      $(".js-select-all").prop('checked', true);
      $("#bulk-action-helper").show();
    } else {
      $("#bulk-action-helper").hide().removeClass("all-selected");
      $("#bulk-action-all-selected").val("");
    }
    $("#main-list-table").trigger("reflow");
    updateBulkActionState();
  });

  $("#list-form").submit(function() {
    if ($("#is-filtering").val() == "1") {
      $(this).find(".post-only").remove();
    }
  });

  $(".js-delete").click(function() {
    if (confirm("Delete this item?")) {
      $row = $(this).closest("tr");
      $.post($(this).data("action"), { "_method": "DELETE" }, function(data) {
        if (data.success) {
          $row.remove();
        } else {
          alert(data.message);
        }
      });
    }
  });

  $(".js-bulk-select-all").click(function() {
    $("#bulk-action-helper").addClass("all-selected");
    $("#bulk-action-all-selected").val("1");
  });
  $(".js-clear-bulk-selection").click(function() {
    $("#bulk-action-helper").removeClass("all-selected");
    $("#bulk-action-all-selected").val("");
    $(".js-select-all").trigger("click");
  });

  $("#bulk-action-operation").change(function() {
    var operation = $(this).val();
    if (operation) {
      if (confirm("Are you sure you want to " + operation + " these items?")) {
        //<input type="hidden" id="bulk-action-all-selected" name="bulk-action-all-selected" value="">
        $("#is-filtering").val("");
        var $listForm = $("#list-form");
        $listForm.attr("method", "post").attr("action", $listForm.data("bulkAction")).submit();
        $(this).closest("form").submit();
      } else {
        $("#bulk-action-operation").val("");
      }
    }
    return false;
  });

  $(".js-advanced-search").click(function(){
    $('#filter-holder').toggle();
    if ($('#filter-holder').is(':visible') && $('.filter-set').length == 0) {
      addFilter();
    }
  });

  $("#filter-holder").on("click.add-filter", ".js-add-filter", addFilter);

  $("#filters").on("click.remove-filter", ".js-remove-filter", function() {
    $(this).closest(".filter-set").remove();
    $("#main-list-table").trigger("reflow");

    if ($("#filters > .filter-set").length == 0) {
      $('#filter-holder').hide();
    }

    // refresh the list page if removing the last filter (if viewing a filtered page)
    if (globals.filters.length > 0 && $("#filters > .filter-set").length == 0) {
      $("#list-form").submit();
    }
  });

  $(".js-set-default-report").click(function() {
    if (confirm("Use this report as default view for this page?")) {
      $.post($(this).data("action"));
      $("#page-settings").removeClass("is-active");
      $.event.trigger("modal-change");
    }
  });

  $(".js-delete-report").click(function() {
    if (confirm("Delete this saved report?")) {
      $.post($(this).data("action"));
      $(this).closest(".field").remove();
      $("#page-settings").removeClass("is-active");
      $.event.trigger("modal-change");
    }
  });

  $("#filters").on("click.remove-filter", ".js-remove-filter", function() {
    $(this).closest(".filter-set").remove();
    $("#main-list-table").trigger("reflow");

    // refresh the list page if removing the last filter (if viewing a filtered page)
    if (globals.filters.length > 0 && $("#filters > .filter-set").length == 0) {
      $("#list-form").submit();
    }
  });

  $(".js-meta").on("click", function() {
    $("#meta-modal-wrapper").toggleClass("is-active");
    $.event.trigger("modal-change");
    if (!$("#meta-modal-wrapper").hasClass("is-active")) {
      return;
    }
    var itemId = $(this).closest(".has-invisibles").find(".js-item-id:checkbox").val();
    loadMeta(itemId);
  });

  $(document).on("meta-change", function(e, data) {
    $("#row-" + data.id + " .tag-count").text(data.meta.tags.length);
    $("#row-" + data.id + " .comment-count").text(data.meta.comments.length);
  });
});

function addFilter(data) {
  data = data || {};
  data.filterIndex = filterIndex;
  filterIndex += 1;
  var template = handlebarsTemplates["list-filter"];
  var $filterItem = $(template(data));
  $("#filters").append($filterItem);
  $("#main-list-table").trigger("reflow");
  return $filterItem;
}

function updateBulkActionState() {
  var isEnabled = $("#main-list-table .js-item-id:checked").length > 0;
  $("#bulk-action-operation").attr("disabled", !isEnabled);
}

function loadHandlebarsTemplates(itemData) {
  $('script[type="text/x-handlebars-template"]').each(function() {
    var $template = $(this);
    handlebarsTemplates[$template.attr("id")] = Handlebars.compile($template.html());
  });
}

function loadFilters() {
  if (globals.filters.length > 0) {
    $("#filter-holder").show();
  }

  for (var i = 0; i < globals.filters.length; i++) {
    var filter = globals.filters[i];
    var $filterItem = addFilter();
    $filterItem.find('[name$="field"]').val(filter.field);
    $filterItem.find('[name$="operation"]').val(filter.operation);
    $filterItem.find('[name$="value"]').val(filter.value);
  }
}
