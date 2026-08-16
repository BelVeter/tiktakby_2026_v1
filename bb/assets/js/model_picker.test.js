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

// --- resolveEditPhaseUI: что показать/скрыть/заблокировать для режим+фаза ---
assert.deepStrictEqual(
	hooks.resolveEditPhaseUI('new', 'locate', false),
	{ showEditStart: false, showSubmit: true, showCancel: false, showResetSearch: true, fieldsDisabled: false, createControlsVisible: true, filterActive: false },
	'вкладка «Новая модель» — всегда как сегодня, фаза не влияет; «начать заново» доступна'
);
assert.deepStrictEqual(
	hooks.resolveEditPhaseUI('edit', 'locate', false),
	{ showEditStart: false, showSubmit: false, showCancel: false, showResetSearch: true, fieldsDisabled: true, createControlsVisible: false, filterActive: true },
	'locate без выбранной модели — всё заблокировано, кнопки «Внести изменения» ещё нет, но «начать заново» есть'
);
assert.deepStrictEqual(
	hooks.resolveEditPhaseUI('edit', 'locate', true),
	{ showEditStart: true, showSubmit: false, showCancel: false, showResetSearch: true, fieldsDisabled: true, createControlsVisible: false, filterActive: true },
	'locate с выбранной моделью — предпросмотр, появляется «Внести изменения», «начать заново» тоже видна'
);
assert.deepStrictEqual(
	hooks.resolveEditPhaseUI('edit', 'unlocked', true),
	{ showEditStart: false, showSubmit: true, showCancel: true, showResetSearch: false, fieldsDisabled: false, createControlsVisible: true, filterActive: false },
	'unlocked — полное редактирование, «Сохранить»/«Отмена», фильтр выключен; «начать заново» скрыта — тут уже «Отмена»'
);

// --- findDuplicateMatch: точный дубль (имя+цвет), кроме самой редактируемой записи ---
var dupRows = [
	{ id: 10, name: 'Fox', color: 'красный', producer: 'Bugaboo', cat_name: 'Коляски' },
	{ id: 11, name: 'Fox', color: 'синий', producer: 'Bugaboo', cat_name: 'Коляски' }
];
assert.strictEqual(
	hooks.findDuplicateMatch(dupRows, { name: 'Fox', color: 'красный', excludeId: 10 }),
	null,
	'совпадение только с самой редактируемой записью (id=10) — не дубль'
);
assert.strictEqual(
	hooks.findDuplicateMatch(dupRows, { name: 'Fox', color: 'красный', excludeId: 999 }),
	dupRows[0],
	'совпадение имя+цвет с ДРУГОЙ записью — дубль'
);
assert.strictEqual(
	hooks.findDuplicateMatch(dupRows, { name: 'Fox', color: 'зелёный', excludeId: 999 }),
	null,
	'то же имя, но другой цвет — не дубль (это и есть штатная штатная новая вариация)'
);
assert.strictEqual(
	hooks.findDuplicateMatch(dupRows, { name: 'Cameleon', color: 'красный', excludeId: 999 }),
	null,
	'другое имя — не дубль'
);

console.log('model_picker.test.js: OK (20 assertions)');
