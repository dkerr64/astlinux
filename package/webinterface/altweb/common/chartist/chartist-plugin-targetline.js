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
    labelOffset: {
      x: 0,
      y: 0
    },
    textAlign: 'left'
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
          var span = document.createElement('span');
          span.setAttribute("class", options.labelClass);
          span.innerHTML = options.label;
          var fobj = context.svg.foreignObject(span, { x: -1000, y: -1000 }, '', false);
          var rect = span.getBoundingClientRect();
          fobj._node.setAttribute('width', rect.width + 'px');
          fobj._node.setAttribute('height', rect.height + 'px');
          fobj._node.setAttribute('y', targetLineY + options.labelOffset.y + 'px');
          if (options.textAlign == 'right') {
            fobj._node.setAttribute('x', context.chartRect.x2 - rect.width + options.labelOffset.x + 'px');
          } else if (options.textAlign == 'center') {
            fobj._node.setAttribute('x', context.chartRect.x1 + ((context.chartRect.x2 - context.chartRect.x1 - rect.width)/2) + options.labelOffset.x + 'px');
          } else { /* default to 'left' */
            fobj._node.setAttribute('x', context.chartRect.x1 + options.labelOffset.x + 'px');
          }
        }
      });
    }
  }
}(window, document, Chartist));
