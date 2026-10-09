const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const vm = require('node:vm');

// Exercise the asynchronous boundary against ACF's field/model interface.
// Native control markup and value parity are checked separately in WordPress.
function harness({ lazy = true, cached = false } = {}) {
  const actions = new Map();
  const requests = [];
  const rows = [];
  const clones = new Map();
  const button = {
    busy: false,
    attr() { this.busy = true; },
    removeAttr() { this.busy = false; },
  };
  function clone(layout, controls = false) {
    return {
      length: 1, controls, disabled: false, max: 0,
      find() { return { length: this.controls ? 1 : 0 }; },
      filter() { return this; },
      attr() { return layout; },
      data() { return this.max; },
      replaceWith(rendered) { clones.set(layout, rendered); },
    };
  }
  clones.set('acf/example', clone('acf/example', cached));
  clones.set('acf/other', clone('acf/other'));
  const field = {
    cid: 'field-model',
    $el: { 0: { isConnected: true }, attr() { return lazy ? '1' : undefined; } },
    $clone(layout) { return clones.get(layout); },
    $button() { return button; },
    get(key) { return { key: 'field_parent', nonce: 'field-nonce' }[key]; },
    getInputName() { return 'acf-block_id[field_parent]'; },
    showNotice(args) { this.notice = args; },
    removeNotice() { this.notice = null; },
    validateAdd() { return rows.length < 3; },
    countLayouts(layout) { return rows.filter(row => row.layout === layout).length; },
    add(args) {
      if (!this.validateAdd()) return false;
      const row = { ...args };
      rows.push(row);
      return row;
    },
  };
  const nativeAdd = field.add;
  const $ = value => value;
  $.ajax = args => new Promise((resolve, reject) => requests.push({ args, resolve, reject }));
  const acf = {
    addAction(name, callback) { actions.set(name, callback); },
    __(value) { return value; },
    get(key) { return { ajaxurl: '/admin-ajax.php', post_id: 123 }[key]; },
    prepareForAjax(value) { return value; },
    disable(value) { value.disabled = true; },
  };
  vm.runInNewContext(readFileSync(join(__dirname, '../../js/inner-blocks-layouts.js'), 'utf8'), {
    jQuery: $, window: { acf, location: { href: 'https://wp.example/wp-admin/post.php?post=123' } }, URL,
  });
  actions.get('new_field/type=flexible_content')(field);
  actions.get('ready_field/type=flexible_content')(field);
  return {
    field, rows, requests, button, clones, nativeAdd,
    resolve(index = 0, layout = 'acf/example') {
      const rendered = clone(layout, true);
      requests[index].resolve({ success: true, data: { html: rendered } });
      return rendered;
    },
  };
}

test('ordinary fields and populated templates keep the native synchronous add method', () => {
  const ordinary = harness({ lazy: false });
  assert.equal(ordinary.field.add, ordinary.nativeAdd);
  const cached = harness({ cached: true });
  assert.equal(cached.field.add({ layout: 'acf/example' }), cached.rows[0]);
  assert.equal(cached.requests.length, 0);
});

test('first addition fetches the current input path; repeat additions reuse disabled clone controls', async () => {
  const h = harness();
  const result = h.field.add({ layout: 'acf/example' });
  assert.equal(h.rows.length, 0);
  assert.equal(h.button.busy, true);
  assert.equal(h.requests[0].args.data.input_name, 'acf-block_id[field_parent]');
  assert.equal(h.requests[0].args.data.post_id, 123);
  assert.equal(h.requests[0].args.data.nonce, 'field-nonce');
  const template = h.resolve();
  assert.equal(await result, h.rows[0]);
  assert.equal(template.disabled, true);
  assert.equal(h.button.busy, false);
  assert.equal(h.field.notice, null);
  assert.equal(h.field.add({ layout: 'acf/example' }), h.rows[1]);
  assert.equal(h.requests.length, 1);
});

test('concurrent additions share one request and respect the layout limit when it completes', async () => {
  const h = harness();
  const first = h.field.add({ layout: 'acf/example' });
  const second = h.field.add({ layout: 'acf/example' });
  assert.equal(h.requests.length, 1);
  const template = h.resolve();
  template.max = 1;
  const results = await Promise.all([first, second]);
  assert.equal(h.rows.length, 1);
  assert.equal(results[1], false);
});

test('global row limit is checked again after a pending request', async () => {
  const h = harness();
  const first = h.field.add({ layout: 'acf/example' });
  h.rows.push({ layout: 'acf/other' }, { layout: 'acf/other' }, { layout: 'acf/other' });
  h.resolve();
  assert.equal(await first, false);
  assert.equal(h.rows.length, 3);
});

test('multiple layout loads retain the loading indicator until all requests finish', async () => {
  const h = harness();
  const first = h.field.add({ layout: 'acf/example' });
  const second = h.field.add({ layout: 'acf/other' });
  h.resolve();
  await first;
  assert.equal(h.button.busy, true);
  h.resolve(1, 'acf/other');
  await second;
  assert.equal(h.button.busy, false);
  assert.equal(h.field.notice, null);
});

test('request failure and invalid markup leave the rows intact and allow retries', async () => {
  const h = harness();
  const first = h.field.add({ layout: 'acf/example' });
  h.requests[0].reject(new Error('Network unavailable'));
  assert.equal(await first, false);
  assert.equal(h.rows.length, 0);
  assert.equal(h.field.notice.type, 'error');
  assert.equal(h.button.busy, false);
  const second = h.field.add({ layout: 'acf/example' });
  h.resolve(1, 'acf/other');
  assert.equal(await second, false);
  const third = h.field.add({ layout: 'acf/example' });
  h.resolve(2);
  assert.equal(await third, h.rows[0]);
  assert.equal(h.requests.length, 3);
});

test('completed requests cannot append into unmounted forms or removed insertion anchors', async () => {
  for (const detached of ['form', 'anchor']) {
    const h = harness();
    const before = [{ isConnected: true }];
    const pending = h.field.add({ layout: 'acf/example', before });
    if (detached === 'form') h.field.$el[0].isConnected = false;
    else before[0].isConnected = false;
    h.resolve();
    assert.equal(await pending, false);
    assert.equal(h.rows.length, 0);
  }
});
