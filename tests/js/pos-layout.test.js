import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const source = fs.readFileSync(path.resolve('public/js/pos-layout.js'), 'utf8');
const sandbox = { window: {} };
vm.createContext(sandbox);
vm.runInContext(source, sandbox);

const { normalize, toCssState } = sandbox.window.PosLayoutTools;

const classic = normalize({ preset: 'classic' }, 'classic');
assert.equal(classic.products_width, 33);
assert.equal(classic.products_tall, false);

const focus = normalize({ preset: 'focus' }, 'focus');
assert.equal(focus.products_tall, true);
assert.equal(focus.summary_position, 'side');

const clamped = normalize({ preset: 'custom', products_width: 10, grid_columns: 99, products_tall: true }, 'classic');
assert.equal(clamped.products_width, 25);
assert.equal(clamped.grid_columns, 8);
assert.equal(clamped.products_tall, true);

const rtlEnd = toCssState({ preset: 'classic', bill_side: 'end' });
assert.equal(rtlEnd.classes.billFirst, false);
assert.equal(rtlEnd.vars['--pos-summary-width'], '17%');
assert.equal(rtlEnd.vars['--pos-bill-width'], '50%');

const billFirst = toCssState({ preset: 'custom', bill_side: 'start', summary_position: 'under', products_width: 40 });
assert.equal(billFirst.classes.billFirst, true);
assert.equal(billFirst.vars['--pos-bill-width'], '60%');
assert.match(billFirst.vars['--pos-layout-template'], /^minmax/);

console.log('pos-layout tests passed');
