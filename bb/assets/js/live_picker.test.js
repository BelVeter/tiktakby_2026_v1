'use strict';

var assert = require('assert');

global.window = global.window || {};
require('./live_picker.js');
var LivePicker = global.window.LivePicker;

assert.strictEqual(typeof LivePicker, 'function', 'live_picker.js должен выставить window.LivePicker');
assert.strictEqual(typeof LivePicker.resolveBlurAction, 'function', 'resolveBlurAction ещё не добавлена');

// Текст совпадает с последним выбором — ничего не делаем.
assert.strictEqual(LivePicker.resolveBlurAction('Bugaboo', 'Bugaboo'), 'keep');
assert.strictEqual(LivePicker.resolveBlurAction('  Bugaboo  ', 'Bugaboo'), 'keep', 'пробелы по краям не считаются изменением');

// Поле стёрли полностью — это явный сброс.
assert.strictEqual(LivePicker.resolveBlurAction('', 'Bugaboo'), 'clear');
assert.strictEqual(LivePicker.resolveBlurAction('   ', 'Bugaboo'), 'clear');

// Напечатали что-то другое и не выбрали вариант — откат к последнему выбору.
assert.strictEqual(LivePicker.resolveBlurAction('Buga', 'Bugaboo'), 'revert');

// Ничего раньше не было выбрано, поле так и осталось пустым.
assert.strictEqual(LivePicker.resolveBlurAction('', ''), 'keep');

console.log('live_picker.test.js: OK (6 assertions)');
