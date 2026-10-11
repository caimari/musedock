const vm = require('node:vm');
const fs = require('node:fs');
const assert = require('node:assert/strict');
(async () => {
    const nodes = {};
    for (const id of ['mcp-progress', 'mcp-progress-summary', 'mcp-progress-list', 'mcp-progress-spinner', 'mcp-progress-close']) {
        nodes[id] = { addEventListener() {}, showModal() {}, replaceChildren() {}, append() {} };
    }
    let submit;
    const button = {};
    const form = {
        action: { toString: () => '[object RadioNodeList]' },
        getAttribute: () => '/musedock/settings/mcp?resource=plugin%3Across-publisher',
        elements: { request: { value: 'test-request' } },
        addEventListener: (_, callback) => { submit = callback; },
        querySelectorAll: () => [button]
    };
    const calls = [];
    vm.runInNewContext(fs.readFileSync('public/assets/superadmin/js/mcp-progress.js', 'utf8'), {
        document: { getElementById: id => nodes[id], querySelectorAll: () => [form], createElement: () => ({ append() {} }) },
        location: { href: 'https://musedock.com/musedock/settings/mcp?resource=plugin%3Across-publisher#mcp-requests' },
        URL, FormData: class { set(key, value) { this[key] = value; } },
        setTimeout: callback => callback(),
        fetch: async (url, options) => {
            assert.equal(nodes['mcp-progress-close'].disabled, false, 'The modal must remain closable during processing');
            calls.push({ url, action: options.body.action });
            return { ok: true, headers: { get: () => 'application/json' }, json: async () => calls.length === 1 ? { ok: true, result: { status: 'queued' } } : { ok: true, status: 'completed' } };
        }
    });
    submit({ preventDefault() {}, submitter: { value: 'approve' } });
    for (let i = 0; i < 20 && !nodes['mcp-progress-summary'].textContent?.includes('Cola finalizada'); i++) await new Promise(resolve => setImmediate(resolve));
    assert.equal(calls.length, 2);
    for (const call of calls) assert.equal(call.url, 'https://musedock.com/musedock/settings/mcp?resource=plugin%3Across-publisher');
    assert.deepEqual(calls.map(call => call.action), ['approve', 'operation_status']);
    assert.equal(button.disabled, true);
    assert.equal(nodes['mcp-progress-summary'].textContent, 'Cola finalizada: 1 correctas; 0 con incidencias.');
    console.log('PASS: named action controls cannot redirect approval or polling; simulated queue completes. No network or provider calls.');
})().catch(error => { console.error(error); process.exitCode = 1; });
