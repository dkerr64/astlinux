/**
 * Chartist.js plugin to display a target line on a chart.
 * With code from @gionkunz in https://github.com/gionkunz/chartist-js/issues/235
 * and @OscarGodson in https://github.com/gionkunz/chartist-js/issues/491.
 * Based on https://github.com/gionkunz/chartist-plugin-pointlabels
 * @dkerr64 added support for displaying a text label
 */
/* global Chartist */
(function(window, document, Chartist) {
  'use strict';

  var defaultOptions = {
    class: 'ct-target-line',
    labelClass: 'ct-target-line-label',
    value: null,
    label: null,
    labelOffset: -15,  // TODO x and y, match chartist convention
  }

  Chartist.plugins = Chartist.plugins || {};
  Chartist.plugins.ctTargetLine = function(options) {

    options = Chartist.extend({}, defaultOptions, options);

    return function ctTargetLine(chart) {
      function projectY(chartRect, bounds, value) {
        return chartRect.y1 - (chartRect.height() / bounds.max * value)
      }

      chart.on('created', function (context) {
        var targetLineY = projectY(context.chartRect, context.bounds, options.value);

        context.svg.elem('line', {
          x1: context.chartRect.x1,
          x2: context.chartRect.x2,
          y1: targetLineY,
          y2: targetLineY
        }, options.class);
        if (options.label) {
          // Use foreignObject rather than text so we can apply HTML styles (like background)
          context.svg.foreignObject('<p><span class="'+options.labelClass+'">' + options.label + '</span></p>', {
            x: context.chartRect.x1,
            y: targetLineY + options.labelOffset,
            width: Math.round(context.chartRect.x2 - context.chartRect.x1) + 'px',
          }, options.labelClass, false); 
        }
      });
    }
  }
}(window, document, Chartist));

