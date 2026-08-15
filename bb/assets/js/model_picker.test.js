// Автономный тест без фреймворка, как live_picker.test.js. Запуск:
// node bb/assets/js/model_picker.test.js
'use strict';

var assert = require('assert');

global.window = global.window || {};
global.document = global.document || { addEventListener: function () {}, readyState: 'loading' };
require('./live_picker.js');
require('./model_picker.js');

var hooks = global.window.__modelPickerTestHooks;
assert.ok(hooks, '__modelPickerTestHooks должен быть выставлен для тестов');

// --- groupByName: схлопывает строки с одинаковым именем, собирает цвета ---
var rows = [
	{ id: 1, name: 'Fox', cat_id: 7, cat_name: 'Коляски 3-в-1', cat_dog_name: 'коляска', producer: 'Bugaboo', color: 'красный' },
	{ id: 2, name: 'Fox', cat_id: 7, cat_name: 'Коляски 3-в-1', cat_dog_name: 'коляска', producer: 'Bugaboo', color: 'синий' },
	{ id: 3, name: 'Cameleon', cat_id: 7, cat_name: 'Коляски 3-в-1', cat_dog_name: 'коляска', producer: 'Bugaboo', color: 'чёрный' }
];
var grouped = hooks.groupByName(rows);

assert.strictEqual(grouped.length, 2, 'Fox должен схлопнуться в одну запись');
assert.strictEqual(grouped[0].name, 'Fox');
assert.deepStrictEqual(grouped[0].colors, ['красный', 'синий']);
assert.strictEqual(grouped[0].cat_id, 7, 'категория берётся из первой строки группы');
assert.strictEqual(grouped[1].name, 'Cameleon');
assert.deepStrictEqual(grouped[1].colors, ['чёрный']);

assert.deepStrictEqual(hooks.groupByName([]), [], 'пустой вход -> пустой выход');

// --- resolveHint: что показать под полем модели ---
assert.strictEqual(
	hooks.resolveHint({ mode: 'new', query: '', hasFilter: false, groups: [] }),
	'empty',
	'пустой запрос без категории/фирмы -> подсказка начать вводить'
);
assert.strictEqual(
	hooks.resolveHint({ mode: 'new', query: '', hasFilter: true, groups: [{ name: 'Fox', colors: ['красный'] }] }),
	null,
	'пустой запрос, но категория/фирма уже выбраны -> просто список, без подсказки'
);
assert.strictEqual(
	hooks.resolveHint({ mode: 'new', query: 'Fox', hasFilter: true, groups: [{ name: 'Fox', colors: ['красный', 'синий'] }] }),
	'exists',
	'точное совпадение имени на вкладке создания -> подсказка «уже есть»'
);
assert.strictEqual(
	hooks.resolveHint({ mode: 'new', query: 'Fo', hasFilter: true, groups: [{ name: 'Fox', colors: ['красный'] }] }),
	null,
	'частичное совпадение — это не точный дубль, подсказки не нужно'
);
assert.strictEqual(
	hooks.resolveHint({ mode: 'edit', query: '', hasFilter: false, groups: [] }),
	'empty',
	'на вкладке редактирования тот же пустой-фокус-без-фильтра сценарий'
);
assert.strictEqual(
	hooks.resolveHint({ mode: 'edit', query: 'Fox', hasFilter: true, groups: [{ name: 'Fox', colors: ['красный'] }] }),
	null,
	'на вкладке редактирования подсказки «уже есть» не бывает — сюда и пришли, чтобы редактировать'
);

console.log('model_picker.test.js: OK (12 assertions)');
