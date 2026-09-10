/**
 * Smoke-тесты: проверка основных маршрутов и редиректов.
 * Запуск: сервер должен быть поднят; BASE_URL по умолчанию http://localhost:8080
 *   SMOKE_BASE_URL=https://staging.example.com node tests/smoke/smoke.js
 *   npm run test:smoke
 */

import { readFileSync } from 'node:fs';

const BASE_URL = process.env.SMOKE_BASE_URL || process.env.BASE_URL || 'http://localhost:8080';

async function request(method, path, { followRedirect = true } = {}) {
  const url = path.startsWith('http') ? path : BASE_URL.replace(/\/$/, '') + (path.startsWith('/') ? path : '/' + path);
  const res = await fetch(url, {
    method,
    redirect: followRedirect ? 'follow' : 'manual',
    headers: { Accept: 'text/html,application/json' },
  });
  return { status: res.status, url: res.url, contentType: res.headers.get('content-type') || '' };
}

async function run() {
  const checks = [];
  let ok = 0;
  let fail = 0;

  // GET / → 200, text/html
  try {
    const r = await request('GET', '/');
    const pass = r.status === 200 && r.contentType.includes('text/html');
    checks.push({ name: 'GET / → 200, text/html', pass, status: r.status, contentType: r.contentType });
    pass ? ok++ : fail++;
  } catch (e) {
    checks.push({ name: 'GET / → 200, text/html', pass: false, error: e.message });
    fail++;
  }

  // GET /contacts/ → 200
  try {
    const r = await request('GET', '/contacts/');
    const pass = r.status === 200;
    checks.push({ name: 'GET /contacts/ → 200', pass, status: r.status });
    pass ? ok++ : fail++;
  } catch (e) {
    checks.push({ name: 'GET /contacts/ → 200', pass: false, error: e.message });
    fail++;
  }

  // GET /nonexistent/ → 404
  try {
    const r = await request('GET', '/nonexistent/');
    const pass = r.status === 404;
    checks.push({ name: 'GET /nonexistent/ → 404', pass, status: r.status });
    pass ? ok++ : fail++;
  } catch (e) {
    checks.push({ name: 'GET /nonexistent/ → 404', pass: false, error: e.message });
    fail++;
  }

  // GET /en/ — только для мультиязычных деплойментов; одноязычный сайт обязан отдавать 404
  try {
    const global = JSON.parse(readFileSync(new URL('../../data/json/global.json', import.meta.url), 'utf8'));
    const langs = Array.isArray(global.languages?.items) ? global.languages.items : global.languages || [];
    const multilingual = langs.some((item) => (typeof item === 'string' ? item : item.code) === 'en');
    const r = await request('GET', '/en/');
    const expected = multilingual ? 200 : 404;
    const pass = r.status === expected;
    checks.push({ name: `GET /en/ → ${expected}`, pass, status: r.status });
    pass ? ok++ : fail++;
  } catch (e) {
    checks.push({ name: 'GET /en/', pass: false, error: e.message });
    fail++;
  }

  // GET /health → 200, application/json
  try {
    const r = await request('GET', '/health');
    const pass = r.status === 200 && r.contentType.includes('application/json');
    checks.push({ name: 'GET /health → 200, JSON', pass, status: r.status });
    pass ? ok++ : fail++;
  } catch (e) {
    checks.push({ name: 'GET /health → 200, JSON', pass: false, error: e.message });
    fail++;
  }

  // Канонический адрес — без хвостового слеша: /contacts отдаётся сразу, /contacts/ редиректит на него
  try {
    const direct = await request('GET', '/contacts');
    const slashed = await request('GET', '/contacts/');
    const pass =
      direct.status === 200 &&
      !direct.url.replace(/^https?:\/\/[^/]+/, '').endsWith('/') &&
      slashed.status === 200 &&
      !slashed.url.replace(/^https?:\/\/[^/]+/, '').endsWith('/');
    checks.push({ name: 'Канонический /contacts без слеша', pass, status: direct.status, url: direct.url });
    pass ? ok++ : fail++;
  } catch (e) {
    checks.push({ name: 'Канонический /contacts без слеша', pass: false, error: e.message });
    fail++;
  }

  for (const c of checks) {
    console.log(c.pass ? '✓' : '✗', c.name, c.status !== undefined ? `(${c.status})` : '', c.error || '');
  }
  console.log('\n' + ok + ' passed, ' + fail + ' failed');
  process.exit(fail > 0 ? 1 : 0);
}

run().catch((e) => {
  console.error(e);
  process.exit(1);
});
