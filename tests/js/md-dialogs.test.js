// Prueba de public/assets/js/md-dialogs.js con un DOM mínimo simulado (sin navegador).
//   node tests/js/md-dialogs.test.js
const fs = require('fs');
const vm = require('vm');
const path = require('path');

let fails = 0, n = 0;
const ok = (c, m) => { n++; if (!c) { fails++; console.log('  FALLA: ' + m); } };

function el(attrs) {
    const a = Object.assign({}, attrs);
    return {
        getAttribute: k => (k in a ? a[k] : null),
        removeAttribute: k => { delete a[k]; },
        setAttribute: (k, v) => { a[k] = v; },
        requestSubmit: function (s) { this.submitted = (this.submitted || 0) + 1; this.submitter = s; },
        submit: function () { this.submitted = (this.submitted || 0) + 1; },
        click: function () { this.clicked = (this.clicked || 0) + 1; },
        closest: function () { return this; },
        _attrs: a,
    };
}

function run(answerYes, withSwal = true) {
    const listeners = {};
    const calls = { fire: [], nativeConfirm: [], nativeAlert: [] };
    const sandbox = {
        document: { documentElement: { lang: 'es' }, addEventListener: (t, f, cap) => { listeners[t] = { f, cap }; } },
        window: {},
        setTimeout: fn => fn(),
        Promise,
    };
    sandbox.window = sandbox;
    sandbox.confirm = m => { calls.nativeConfirm.push(m); return true; };
    sandbox.alert = m => { calls.nativeAlert.push(m); };
    if (withSwal) {
        sandbox.Swal = { fire: o => { calls.fire.push(o); return Promise.resolve({ isConfirmed: answerYes }); } };
    }
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/assets/js/md-dialogs.js'), 'utf8'), sandbox);
    return { sandbox, listeners, calls };
}
const evt = target => ({ target, prevented: false, stopped: false, preventDefault() { this.prevented = true; }, stopImmediatePropagation() { this.stopped = true; }, submitter: null });
const tick = () => new Promise(r => setImmediate(r));

(async () => {
    console.log('== submit con confirm() en el atributo');
    let t = run(true);
    ok(t.listeners.submit.cap === true && t.listeners.click.cap === true, 'escucha en fase de captura (antes que el atributo inline)');
    let form = el({ onsubmit: "return confirm('¿Eliminar este rol?')" });
    let e = evt(form); t.listeners.submit.f(e); await tick();
    ok(e.prevented && e.stopped, 'detiene el aviso nativo');
    ok(t.calls.fire.length === 1 && t.calls.fire[0].text === '¿Eliminar este rol?' && t.calls.fire[0].confirmButtonColor === '#dc3545', 'modal con el mismo texto y botón rojo si es destructivo');
    ok(form.submitted === 1 && form._attrs.onsubmit === "return confirm('¿Eliminar este rol?')", 'al aceptar envía el formulario y restaura el atributo');

    console.log('== cancelar');
    t = run(false); form = el({ onsubmit: 'return confirm("¿Seguro?");' }); e = evt(form); t.listeners.submit.f(e); await tick();
    ok(!form.submitted && e.prevented, 'cancelar no envía nada');

    console.log('== click con confirm() y comillas escapadas');
    t = run(true); const btn = el({ onclick: "return confirm('¿Eliminar \\'todo\\'?')" }); e = evt(btn); t.listeners.click.f(e); await tick();
    ok(t.calls.fire[0].text === "¿Eliminar 'todo'?" && btn.clicked === 1, 'texto con comilla escapada y clic repetido al aceptar');

    console.log('== lo que NO debe tocar');
    t = run(true); form = el({ onsubmit: "return confirm('x') && hacerOtraCosa()" }); e = evt(form); t.listeners.submit.f(e); await tick();
    ok(!e.prevented && t.calls.fire.length === 0, 'código inline más complejo se deja intacto');
    form = el({}); e = evt(form); t.listeners.submit.f(e); ok(!e.prevented, 'formularios sin confirm no se tocan');
    t = run(true, false); form = el({ onsubmit: "return confirm('¿Eliminar?')" }); e = evt(form); t.listeners.submit.f(e);
    ok(!e.prevented, 'sin SweetAlert2 se conserva el aviso nativo');

    console.log('== mdConfirm y alert');
    t = run(true); ok((await t.sandbox.mdConfirm('¿Publicar la página?')) === true && t.calls.fire[0].confirmButtonColor === undefined, 'mdConfirm resuelve true y no pinta rojo lo no destructivo');
    t = run(false); ok((await t.sandbox.mdConfirm('¿Eliminar?')) === false, 'mdConfirm resuelve false al cancelar');
    t = run(true); t.sandbox.alert('Guardado'); ok(t.calls.fire.length === 1 && t.calls.fire[0].text === 'Guardado' && t.calls.nativeAlert.length === 0, 'alert() sale como modal');
    t = run(true, false); t.sandbox.alert('x'); ok(t.calls.nativeAlert.length === 1, 'alert() nativo si no hay Swal');

    console.log(fails ? `RESULTADO: ${fails} de ${n} FALLAN` : `RESULTADO: las ${n} comprobaciones pasan`);
    process.exit(fails ? 1 : 0);
})();
