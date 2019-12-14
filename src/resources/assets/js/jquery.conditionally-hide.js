(function($){
  $.fn.conditionallyHide = function(options) {
    var settings = $.extend({ wrapperSelector: null }, options);

    return this.each(function(){
      var $e = $(this);
      var rule = $e.data("showWhen");
      var elementToHide = $e;

      var elementToHideIdentifier = (elementToHide.attr('id') || elementToHide.attr('name') || elementToHide.attr('class')) + "  ::SHOW WHEN::  " + rule;

      if (settings.wrapperSelector != null) {
        var wrapperSelector = $e.is('[data-override-wrapper-selector]') ? $e.data('overrideWrapperSelector') : settings.wrapperSelector;
        if (wrapperSelector) {
          var elementToHide = $e.closest(wrapperSelector);
        }
      }

      rule = rule.split("|");
      var selector = rule[0];
      var target = $("#" + selector +", input[name='" + selector +"'], select[name='" + selector +"']");
      var value = String(rule[1]);
      var comparisonOp = $.fn.conditionallyHide.neq;
      if (value.indexOf('!') === 0) {
        value = value.substring(1);
        comparisonOp = $.fn.conditionallyHide.eq;
      }
      else if (value.indexOf('in_') === 0) {
        value = value.substring(3);
        comparisonOp = $.fn.conditionallyHide.is_not_in;
      }
      else if (value.indexOf('not_in_') === 0) {
        value = value.substring(7);
        comparisonOp = $.fn.conditionallyHide.is_in;
      }
      else if (value.indexOf('has_value') === 0) {
        value = '';
        comparisonOp = $.fn.conditionallyHide.has_no_value;
      }
      else if (value.indexOf('has_no_value') === 0) {
        value = '';
        comparisonOp = $.fn.conditionallyHide.has_value;
      }
      else if (value.indexOf('gt_') === 0) {
        value = value.substring(3);
        comparisonOp = $.fn.conditionallyHide.less_than;
      }

      if ((target.length > 1 && comparisonOp(target.filter(":checked").val(), value)) // radios
        || (target.length == 1 && target.is('[type="checkbox"]') && comparisonOp(target.filter(":checked").val(), value)) // checkboxes
        || (target.length == 1 && !target.is('[type="checkbox"]') && comparisonOp(target.val(), value)) // regular inputs
      ) {
        elementToHide.hide();
        // console.log({'hiding': elementToHideIdentifier})
      }

      target.bind('updated', function(){
        var target = $(this);
        if ((target.is('[type="checkbox"]') && comparisonOp(target.filter(":checked").val(), value)) // checkboxes
          || (!target.is('[type="checkbox"]') && comparisonOp(target.val(), value)) // everything else
        ) {
          // Hide elements and unset any form values that are under them
          elementToHide.hide().unsetInputs();
          // console.log({'hiding': elementToHideIdentifier})
        } else {
          elementToHide.fadeIn();
          // console.log({'showing': elementToHideIdentifier})
        }
        document.body.className = document.body.className; // ie 8 reflow fix
      });

      if (!target.data('conditionallyHidden')) {
        var eventToListenFor = target.is(':radio') ? 'click' : 'change';
        target.on(eventToListenFor, function() {
          $(this).trigger('updated');
        });
        target.data('conditionallyHidden', 1);
      }
    });
  };
  $.fn.conditionallyHide.neq = function(a, b) {
    if (a && typeof(a) == "object") {
      return a.indexOf(b) < 0;
    }
    return a != b;
  }
  $.fn.conditionallyHide.eq = function(a, b) {
    if (a && typeof(a) == "object") {
      return a.indexOf(b) > -1;
    }
    return a == b;
  }
  $.fn.conditionallyHide.is_not_in = function(a, b) { var tmp = b.split(/\s*,\s*/); return tmp.indexOf(a) == -1; }
  $.fn.conditionallyHide.is_in = function(a, b) { var tmp = b.split(/\s*,\s*/); return tmp.indexOf(a) > -1; }
  $.fn.conditionallyHide.has_value = function(a, b) { return !/^(0)?$/.test(a); }
  $.fn.conditionallyHide.has_no_value = function(a, b) { return /^(0)?$/.test(a); }
  $.fn.conditionallyHide.less_than = function(a, b) { a = Number(a || 0); b = Number(b || 0); return a <= b; }
}(jQuery));

(function($){
  $.fn.unsetInputs = function() {

    return this.each(function(){
      $(this).find(':input')
        .filter(':not(:checkbox):not(:radio)').val('').end()
        .filter(':checkbox,:radio').attr('checked', false).end()
        .trigger('change');
    });
  }
}(jQuery));
