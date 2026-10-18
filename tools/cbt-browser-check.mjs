/** Uji JS CBT dengan DOM/fetch tiruan di Node; bukan uji browser end-to-end. */
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const template = fs.readFileSync(new URL('../resources/views/cbt/student/exam.blade.php', import.meta.url), 'utf8');
const source = template.split('<script>')[1].split('</script>')[0]
    .replace(/@json\(csrf_token\(\)\)/g, '"csrf-test"')
    .replace(/@json\(route\('cbt.student.answer', \$result\)\)/g, '"/answer"')
    .replace(/@json\(route\('cbt.student.integrity', \$result\)\)/g, '"/integrity"')
    .replace(/@json\(route\('cbt.student.result', \$result\)\)/g, '"/result"')
    .replace(/@json\(\$result->deadline_at->getTimestamp\(\) \* 1000\)/g, '60000')
    .replace(/@json\(now\(\)->getTimestamp\(\) \* 1000\)/g, '0');
assert(!source.includes('@json'), 'Seluruh ekspresi Blade JS harus diganti untuk pengujian.');

function harness(fetchImpl = async () => ({ok: true, status: 200})) {
    const nodes = new Map();
    const events = {};
    const intervals = [];
    const calls = [];
    const redirects = [];
    let now = 0;
    let confirmed = true;
    function node(id) {
        if (!nodes.has(id)) nodes.set(id, {textContent: '', disabled: false, listeners: {},
            addEventListener(name, fn) { this.listeners[name] = fn; },
            requestSubmit() { this.listeners.submit({preventDefault() {}}); }
        });
        return nodes.get(id);
    }
    const doc = {hidden: false, getElementById: node,
        addEventListener(name, fn) { events['doc:' + name] = fn; }};
    const sandbox = {
        document: doc, window: {addEventListener(name, fn) { events[name] = fn; }},
        performance: {now: () => now}, location: {assign: url => redirects.push(url)},
        confirm: () => confirmed, AbortController,
        setTimeout: () => 1, clearTimeout: () => {},
        setInterval: (fn, delay) => intervals.push([fn, delay]),
        fetch: async (url, options) => { calls.push({url, options}); return fetchImpl(url, options); }
    };
    vm.runInNewContext(source, sandbox);
    return {
        node, events, calls, redirects, doc,
        setTime(value) { now = value; }, setConfirm(value) { confirmed = value; },
        change(id, value) { node('exam-form').listeners.change({target: {matches: () => true, dataset: {question: String(id)}, value}}); },
        interval(delay) { intervals.find(i => i[1] === delay)[0](); },
        submit() { let prevented = false; node('exam-form').listeners.submit({preventDefault() { prevented = true; }}); return prevented; }
    };
}
const flush = () => new Promise(resolve => setImmediate(resolve));
let passed = 0;
async function check(label, fn) { await fn(); passed++; console.log('PASS ' + label); }

await check('Autosave membawa CSRF, id soal, dan opsi tanpa skor', async () => {
    const h = harness(); h.change(17, 'B'); await flush();
    assert.equal(h.calls[0].options.headers['X-CSRF-TOKEN'], 'csrf-test');
    assert.deepEqual(JSON.parse(h.calls[0].options.body), {question_id: 17, answer: 'B'});
    assert.match(h.node('save-status').textContent, /tersimpan di server/);
});
await check('Kegagalan koneksi dipertahankan untuk retry', async () => {
    let failed = true;
    const h = harness(async () => { if (failed) throw Error('offline'); return {ok: true, status: 200}; });
    h.change(17, 'A'); await flush();
    assert.match(h.node('save-status').textContent, /BELUM tersimpan/);
    failed = false; h.interval(8000); await flush();
    assert.equal(h.calls.length, 2);
    assert.match(h.node('save-status').textContent, /tersimpan di server/);
});
await check('Perubahan cepat tidak hilang saat fetch sebelumnya berjalan', async () => {
    let resolve;
    let count = 0;
    const h = harness(() => { if (count++ === 0) return new Promise(r => {resolve = r;}); return {ok: true, status: 200}; });
    h.change(17, 'A'); h.change(17, 'D');
    assert.equal(h.calls.length, 1);
    resolve({ok: true, status: 200}); await flush(); await flush();
    assert.equal(h.calls.length, 2);
    assert.equal(JSON.parse(h.calls[1].options.body).answer, 'D');
    assert.match(h.node('save-status').textContent, /tersimpan di server/);
});
await check('HTTP 409 mengarahkan ke hasil', async () => {
    const h = harness(async () => ({ok: false, status: 409}));
    h.change(17, 'A'); await flush(); assert.deepEqual(h.redirects, ['/result']);
});
await check('Timer memakai durasi elapsed dan mengumpulkan saat habis', async () => {
    const h = harness(); assert.equal(h.node('timer').textContent, '1:00');
    h.setTime(60000); h.interval(1000);
    assert.equal(h.node('timer').textContent, '0:00');
    assert.equal(h.node('submit-exam').disabled, true);
});
await check('Pembatalan konfirmasi tidak mengumpulkan ujian', async () => {
    const h = harness(); h.setConfirm(false); assert.equal(h.submit(), true);
    assert.equal(h.node('submit-exam').disabled, false);
});
await check('Klik kumpulkan ganda dicegah', async () => {
    const h = harness(); assert.equal(h.submit(), false); assert.equal(h.submit(), true);
});
await check('Pindah tab hanya mengirim telemetri tanpa nilai', async () => {
    const h = harness(); h.doc.hidden = true; h.events['doc:visibilitychange'](); await flush();
    assert.equal(h.calls[0].url, '/integrity');
    assert.deepEqual(JSON.parse(h.calls[0].options.body), {type: 'tab_hidden'});
});
console.log(`Ringkasan JS tiruan: ${passed}/8 lulus`);
