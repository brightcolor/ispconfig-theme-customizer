'use strict';
/*
 * themes/cicada/assets/javascripts/cicada-charts.js against a stubbed Chart.js
 * and document: the dashboard's teal line takes the accent the stylesheet holds
 * in --cic-accent-text, which brand.php reassigns when an accent is stored.
 *
 *   node tests/js/cicada-charts.test.js
 *
 * No dependencies: node:assert and node:vm only.
 */
const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const SCRIPT = fs.readFileSync(
  path.join(__dirname, '..', '..', 'themes', 'cicada', 'assets', 'javascripts', 'cicada-charts.js'),
  'utf8'
);

// Run the script in a fresh context whose computed --cic-accent-text is
// `accent`, and return the plugin it registered with Chart.js.
function load(accent) {
  let plugin = null;
  const context = {
    Chart: {
      defaults: { scale: { grid: {}, ticks: {} }, plugins: { legend: {}, tooltip: {} } },
      register(p) { plugin = p; },
    },
    document: { documentElement: {} },
    getComputedStyle() {
      return { getPropertyValue(name) { return name === '--cic-accent-text' ? accent : ''; } };
    },
  };
  vm.createContext(context);
  vm.runInContext(SCRIPT, context);
  assert.ok(plugin, 'the script registers a plugin');
  return plugin;
}

// The chart the metrics dashlet builds: one dataset in its hard-coded teal.
function dashletChart() {
  return {
    config: {
      data: { datasets: [{ borderColor: 'rgb(75, 192, 192)', backgroundColor: 'rgba(75, 192, 192, 0.2)' }] },
      options: {},
    },
  };
}

let passed = 0;
function test(name, fn) {
  fn();
  passed++;
  console.log('ok   ' + name);
}

test('accent from the stylesheet', () => {
  const chart = dashletChart();
  load(' #123456').beforeInit(chart);
  const set = chart.config.data.datasets[0];
  assert.strictEqual(set.borderColor, '#123456');
  assert.strictEqual(set.backgroundColor, 'rgba(18, 52, 86, .18)');
});

test('shipped accent when the property is empty', () => {
  const chart = dashletChart();
  load('').beforeInit(chart);
  const set = chart.config.data.datasets[0];
  assert.strictEqual(set.borderColor, '#ef7d21');
  assert.strictEqual(set.backgroundColor, 'rgba(239, 125, 33, .18)');
});

test('an unreadable property falls back to the shipped accent', () => {
  const chart = dashletChart();
  load('var(--elsewhere)').beforeInit(chart);
  const set = chart.config.data.datasets[0];
  assert.strictEqual(set.borderColor, '#ef7d21');
  assert.strictEqual(set.backgroundColor, 'rgba(239, 125, 33, .18)');
});

test('a chart with its own colour is left alone', () => {
  const chart = {
    config: {
      data: { datasets: [{ borderColor: '#00ff00', backgroundColor: 'rgba(0, 255, 0, 0.5)' }] },
      options: {},
    },
  };
  load(' #123456').beforeInit(chart);
  const set = chart.config.data.datasets[0];
  assert.strictEqual(set.borderColor, '#00ff00');
  assert.strictEqual(set.backgroundColor, 'rgba(0, 255, 0, 0.5)');
});

console.log('# ' + passed + ' passed');
console.log('chart test passed');
