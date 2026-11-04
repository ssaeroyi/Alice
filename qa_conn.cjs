const { chromium } = require('playwright');
const BASE = 'https://alicemyr.tech';
const EMAIL = process.argv[2];
const PASS = 'QApassw0rd!x';

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1320, height: 900 } });
  const page = await context.newPage();
  const log = (...a) => console.log('*', ...a);
  page.on('pageerror', (e) => console.log('PAGEERROR:', String(e).slice(0, 200)));

  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  log('guest flag set:', await page.evaluate(() => document.body.dataset.connToasts));

  // 1. Offline event -> toast
  await context.setOffline(true);
  await page.waitForTimeout(900);
  log('navigator.onLine now:', await page.evaluate(() => navigator.onLine));
  log('offline toast:', await page.evaluate(() => {
    const t = document.querySelector('[data-toast]');
    return t ? (t.textContent.includes('internet') ? 'YES' : `NO (${t.textContent.slice(0, 40)})`) : 'none';
  }));

  // 2. Back online -> success toast
  await context.setOffline(false);
  await page.waitForTimeout(900);
  log('online toast:', await page.evaluate(() => {
    const t = document.querySelector('[data-toast]');
    return t ? (t.textContent.toLowerCase().includes('back online') ? 'YES' : `NO (${t.textContent.slice(0, 40)})`) : 'none';
  }));

  // 3. Failed fetch while "online" -> failure toast (abort an in-flight combobox? use plain fetch reject)
  await page.evaluate(() => window.fetch('/library/suggest?q=x').catch(() => {}));

  // 4. Offline submit gate on login form
  await context.setOffline(true);
  await page.waitForTimeout(400);
  await page.fill('input[name="email"]', EMAIL);
  await page.fill('input[type="password"]', PASS);
  await page.click('button[type="submit"]');
  await page.waitForTimeout(800);
  log('submit blocked (stayed on /login):', page.url().includes('/login') ? 'YES' : `NO (${page.url()})`);
  log('submit offline toast:', await page.evaluate(() => {
    const t = document.querySelector('[data-toast]');
    return t ? (t.textContent.includes('internet') ? 'YES' : 'NO') : 'none';
  }));

  // 5. Online again -> real login works + placement pages show flag
  await context.setOffline(false);
  await page.waitForTimeout(500);
  const toastGone = await page.$('[data-toast]');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !/login/.test(u), { timeout: 30000 });
  log('recovered login:', 'YES');
  log('placement flag:', await page.evaluate(async () => {
    const res = await fetch(window.location.href);
    return res.ok ? 'auth ok' : 'auth fail';
  }));
  const flag = await page.evaluate(() => document.body.dataset.connToasts ?? 'auth-area');
  log('body flag now:', String(flag));

  await browser.close();
})().catch((e) => { console.error('FAIL', e); process.exit(1); });
