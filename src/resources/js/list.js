var handlebarsTemplates = [];
var filterIndex = 1;

$(document).ready(function() {
    loadHandlebarsTemplates();
    loadFilters();

    window.onblur = function() {
        $("#bulk-action-operation").val("");
    };

    $(".js-toggle-page-settings").on("click", function() {
        $("#page-settings").toggleClass("is-active");
        $.event.trigger("modal-change");
    });
    dragula([$("#selectable-fields")[0]]);

    $(".js-toggle-save-report").on("click", function() {
        $("#save-report-modal").toggleClass("is-active");
        $.event.trigger("modal-change");
    });

    $(".js-clear-search").on("click", function(e) {
        $("#quick-search").val("");
        $(e.target)
            .closest("form")
            .submit();
    });

    // We're posting this via the main list form as that's where all the
    // filters are -- and we need those filters for bulk actions as well.
    $("#save-report-form").submit(function(e) {
        var $item = $(e.target);
        var reportName = ($item.find("#modal-report-name").val() || "").trim();

        if (!reportName) {
            alert("You must provide a report name.");
            return false;
        }

        $("#is-filtering").val("");
        var $listForm = $("#list-form");
        $listForm
            .attr("method", "post")
            .attr("action", $listForm.data("saveReportAction"))
            .append($('<div style="display:none"></div>').append($item.find(":input").clone()))
            .submit();
        return false;
    });

    // We're posting this via the main list form as that's where all the
    // filters are -- and we need those filters for bulk actions as well.
    $("#save-list-prefs-form").submit(function(e) {
        $("#is-filtering").val("");
        var $listForm = $("#list-form");
        $listForm
            .attr("method", "post")
            .attr("action", $listForm.data("saveListPrefsAction"))
            .append(
                $('<div style="display:none"></div>').append(
                    $(e.target)
                        .find(":input")
                        .clone(),
                ),
            )
            .submit();
        return false;
    });

    $(document).on("keyup keydown", function(e) {
        globals.isShiftDetected = e.shiftKey;
    });

    $(".js-select-all, .js-item-id").click(function(e) {
        var $item = $(e.target);
        var isChecked = $item.is(":checked");

        if ($item.is(".js-select-all")) {
            $("#main-list-table .js-item-id").prop("checked", isChecked).trigger('change');
        } else if (!isChecked) {
            $(".js-select-all").prop("checked", false);
        } else if (isChecked) {
            var $tr = $item.closest("tr");
            if (globals.lastRowSelected && globals.isShiftDetected) {
                $trs = $("#main-list-table tbody tr");
                var start = $trs.index($tr);
                var end = $trs.index(globals.lastRowSelected);
                for (var i = $trs.index($tr); i != $trs.index(globals.lastRowSelected); i += end >= start ? 1 : -1) {
                    $trs.eq(i)
                        .find(".js-item-id")
                        .prop("checked", true);
                }
            }
            globals.lastRowSelected = $tr;
        }

        var isAllSelected =
            $(".js-select-all").is(":checked") || $(".js-item-id").length == $(".js-item-id:checked").length;

        if (isAllSelected && $("#bulk-action-helper").data("hasAdditionalPages") == "1") {
            $(".js-select-all").prop("checked", true);
            $("#bulk-action-helper").show();
        } else if (isAllSelected) {
            $("#bulk-action-all-selected").val("1");
        } else {
            $("#bulk-action-helper")
                .hide()
                .removeClass("all-selected");
            $("#bulk-action-all-selected").val("");
        }
        $("#main-list-table").trigger("reflow");
        updateBulkActionState();
    });

    $("#list-form").submit(function(e) {
        if ($("#is-filtering").val() == "1") {
            $(e.target)
                .find(".post-only")
                .remove();
        }
    });

    $(".js-delete").click(function(e) {
        if (confirm("Delete this item?")) {
            var $item = $(e.target);
            var $row = $item.closest("tr");
            $.post($item.data("action"), { _method: "DELETE" }, function(data) {
                if (data.success) {
                    $row.remove();
                    ["viewing-end", "viewing-total"].forEach(function(item) {
                        $("#" + item).text((Number($("#" + item).text()) || 1) - 1);
                    });
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

    $("#bulk-action-operation").change(function(e) {
        var $item = $(e.target);
        var operation = $item
            .val()
            .toLowerCase()
            .replace(/\([^\)]+\)/, "")
            .trim();
        if (operation) {
            if (confirm("Are you sure you want to " + operation + " these items?")) {
                //<input type="hidden" id="bulk-action-all-selected" name="bulk-action-all-selected" value="">
                $("#is-filtering").val("");
                var $listForm = $("#list-form");
                $listForm
                    .attr("method", "post")
                    .attr("action", $listForm.data("bulkAction"))
                    .submit();
            } else {
                $("#bulk-action-operation").val("");
            }
        }
        return false;
    });

    $(".js-advanced-search").click(function() {
        $("#filter-holder").toggle();
        if ($("#filter-holder").is(":visible") && $(".filter-set").length == 0) {
            addFilter();
        }
    });

    $("#filter-holder").on("click.add-filter", ".js-add-filter", addFilter);

    $("#filters").on("click.remove-filter", ".js-remove-filter", function(e) {
        $(e.target)
            .closest(".filter-set")
            .remove();
        $("#main-list-table").trigger("reflow");

        if ($("#filters > .filter-set").length == 0) {
            $("#filter-holder").hide();
        }

        // refresh the list page if removing the last filter (if viewing a filtered page)
        if (globals.filters.length > 0 && $("#filters > .filter-set").length == 0) {
            $("#list-form").submit();
        }
    });

    $(".js-set-default-report").click(function(e) {
        if (confirm("Use this report as default view for this page?")) {
            $.post($(e.target).data("action"));
            $("#page-settings").removeClass("is-active");
            $.event.trigger("modal-change");
        }
    });

    $(".js-delete-report").click(function(e) {
        var $item = $(e.target);
        if (confirm("Delete this saved report?")) {
            $.post($item.data("action"));
            $item.closest(".field").remove();
            $("#page-settings").removeClass("is-active");
            $.event.trigger("modal-change");
        }
    });

    $("#filters").on("click.remove-filter", ".js-remove-filter", function(e) {
        var $item = $(e.target);
        $item.closest(".filter-set").remove();
        $("#main-list-table").trigger("reflow");

        // refresh the list page if removing the last filter (if viewing a filtered page)
        if (globals.filters.length > 0 && $("#filters > .filter-set").length == 0) {
            $("#list-form").submit();
        }
    });

    $(".js-meta").on("click", function(e) {
        var $item = $(e.target);
        $("#meta-modal-wrapper").toggleClass("is-active");
        $.event.trigger("modal-change");
        if (!$("#meta-modal-wrapper").hasClass("is-active")) {
            return;
        }
        var itemId = $item
            .closest(".has-invisibles")
            .find(".js-item-id:checkbox")
            .val();
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
    $('script[type="text/x-handlebars-template"]').each(function(i, item) {
        var $template = $(item);
        handlebarsTemplates[$template.attr("id")] = Handlebars.compile($template.html());
    });
}

function loadFilters() {
    if ($("#filter-holder").length == 0) {
      return;
    }

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
