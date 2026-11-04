const { chromium } = require('playwright');
const BASE = 'https://alicemyr.tech';
const EMAIL = process.argv[2];
const PASS = 'QApassw0rd!x';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', EMAIL);
  await page.fill('input[type="password"]', PASS);
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !/login/.test(u), { timeout: 30000 });
  await page.goto(`${BASE}/teacher/roster`, { waitUntil: 'networkidle' });
  const info = await page.$$eval('[class*="hover:border-"]', (els) =>
    els.map((e) => {
      const hits = (e.className.match(/hover:border-[^ "'"]+/g) || []).join(' ');
      return e.tagName + (e.getAttribute('data-filter-drawer-open') !== null ? ' [drawer-btn]' : '') + ' -> ' + hits;
    }).slice(0, 20));
  console.log(info.join('\n'));
  await browser.close();
})().catch((e) => console.error(e));
