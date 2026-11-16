/*
 * cicada-charts.js — dark defaults for the ISPConfig 3.3 dashboard charts.
 *
 * The metrics dashlet builds its charts with Chart.js defaults that assume a
 * light page: axis ticks and grid lines are drawn in near-black. On Cicada's
 * ground those are invisible. Chart.js reads these defaults when a chart is
 * constructed, so this file only has to run before the dashlet does — the
 * template loads it directly after chart.umd.js, and dashlets arrive later
 * with the AJAX page content.
 *
 * Loaded only when the panel ships chart.umd.js: lib/shell.sh links it right
 * after Chart.js and leaves it out of a stock shell without Chart.js.
 */
(function () {
	'use strict';

	if (typeof Chart === 'undefined') {
		return;
	}

	/* Kept in sync with cicada.css by hand — Chart.js draws onto a canvas and
	   cannot read CSS custom properties, so every colour it gets is a plain
	   value. The accent is the exception: brand.php reassigns it when the
	   operator stores one, so it is read from the computed style once per chart
	   (Chart.js loads in the body, after every stylesheet), and these two are
	   its shipped values. */
	var TEXT = '#bac3ca';           /* --cic-text      */
	var GRID = 'rgba(186, 195, 202, .14)';
	var ACCENT = '#ef7d21';         /* --cic-accent-text, as shipped */
	var ACCENT_FILL = 'rgba(239, 125, 33, .18)';

	/* The accent text colour this page really has: brand.php's when an accent
	   is stored, cicada.css's otherwise. Anything but #rrggbb falls back. */
	function readAccent() {
		if (typeof getComputedStyle !== 'function' || !document.documentElement) {
			return ACCENT;
		}
		var value = String(getComputedStyle(document.documentElement)
			.getPropertyValue('--cic-accent-text') || '').trim();
		return /^#[0-9a-f]{6}$/i.test(value) ? value : ACCENT;
	}

	/* That accent as the area under the line, at the shipped 18 % opacity. */
	function accentFill(hex) {
		if (hex.toLowerCase() === ACCENT) {
			return ACCENT_FILL;
		}
		return 'rgba(' + parseInt(hex.substr(1, 2), 16) + ', ' + parseInt(hex.substr(3, 2), 16)
			+ ', ' + parseInt(hex.substr(5, 2), 16) + ', .18)';
	}

	/* The colour the dashlet hard-codes for every line. Only datasets still
	   carrying it get recoloured, so a chart that sets its own colour on
	   purpose is left alone. */
	var DASHLET_LINE = 'rgb(75, 192, 192)';
	var DASHLET_FILL = 'rgba(75, 192, 192, 0.2)';

	Chart.defaults.color = TEXT;
	Chart.defaults.borderColor = GRID;

	if (Chart.defaults.scale && Chart.defaults.scale.grid) {
		Chart.defaults.scale.grid.color = GRID;
	}
	if (Chart.defaults.scale && Chart.defaults.scale.ticks) {
		Chart.defaults.scale.ticks.color = TEXT;
	}
	if (Chart.defaults.plugins && Chart.defaults.plugins.legend) {
		Chart.defaults.plugins.legend.labels = Chart.defaults.plugins.legend.labels || {};
		Chart.defaults.plugins.legend.labels.color = TEXT;
	}
	/* Tooltip: --cic-overlay for the panel, --cic-text-dim for its edge. The
	   obvious pick, --cic-line-loud, sits at 2.5:1 against the page and 1.7:1
	   against the panel — below the 3:1 WCAG asks of a meaningful graphic
	   boundary. --cic-text-dim clears both (5.4:1 and 3.7:1). */
	if (Chart.defaults.plugins && Chart.defaults.plugins.tooltip) {
		Chart.defaults.plugins.tooltip.backgroundColor = '#383838'; /* --cic-overlay  */
		Chart.defaults.plugins.tooltip.borderColor = '#8b9298';     /* --cic-text-dim */
		Chart.defaults.plugins.tooltip.borderWidth = 1;
	}

	/* Repaint the dashlet's teal line in the theme accent. Registered as a
	   plugin so it applies to charts built after this file runs, which is what
	   the AJAX-loaded dashboard does. */
	Chart.register({
		id: 'cicadaDarkLine',
		beforeInit: function (chart) {
			var conf = chart.config;
			if (!conf || conf._cicadaDone) {
				return;
			}
			conf._cicadaDone = true;

			var accent = readAccent();
			var fill = accentFill(accent);

			var sets = conf.data && conf.data.datasets;
			if (sets) {
				sets.forEach(function (set) {
					if (set.borderColor === DASHLET_LINE) {
						set.borderColor = accent;
					}
					if (set.backgroundColor === DASHLET_FILL) {
						set.backgroundColor = fill;
					}
				});
			}

			/* The dashlet supplies its own generateLabels() and paints the
			   legend swatch white — a glaring block on a dark ground. Its own
			   comment says the box was meant to be hidden, so drop it: one
			   dataset per chart, and the line underneath already carries the
			   colour. Wrap the function rather than replace it, so the labels
			   it builds stay intact. */
			var opts = conf.options;
			var labels = opts && opts.plugins && opts.plugins.legend && opts.plugins.legend.labels;
			if (!labels) {
				return;
			}
			if (labels.color === undefined) {
				labels.color = TEXT;
			}
			labels.boxWidth = 0;
			labels.boxHeight = 0;
			if (typeof labels.generateLabels === 'function') {
				var inner = labels.generateLabels;
				labels.generateLabels = function (c) {
					return inner.call(this, c).map(function (item) {
						/* boxWidth 0 removes the swatch's footprint; making it
						   transparent as well keeps a stray outline from being
						   stroked at zero width. */
						if (item.fillStyle === 'white') {
							item.fillStyle = 'rgba(0, 0, 0, 0)';
						}
						if (item.strokeStyle === 'white') {
							item.strokeStyle = 'rgba(0, 0, 0, 0)';
						}
						/* Where a chart brings its own generateLabels(), the
						   legend text follows item.fontColor and ignores
						   labels.color — measured on the canvas: setting only
						   labels.color left the text black. */
						if (item.fontColor === undefined) {
							item.fontColor = TEXT;
						}
						return item;
					});
				};
			}
		}
	});
})();
