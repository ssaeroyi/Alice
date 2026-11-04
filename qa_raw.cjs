const { chromium } = require('playwright');
const BASE = 'https://alicemyr.tech';
const EMAIL = process.argv[2];
const PASS = 'QApassw0rd!x';
const PATHS = ['/teacher/students', '/teacher/assignments', '/teacher/inbox'];

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', EMAIL);
  await page.fill('input[type="password"]', PASS);
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !/login/.test(u), { timeout: 30000 });

  for (const path of PATHS) {
    await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
    const found = await page.$$eval('[class*="hover:border-"]', (els) =>
      els.map((e) => ({ tag: e.tagName, text: (e.textContent || '').trim().slice(0, 24), hover: (e.className.match(/hover:border-[^ "'"]+/g) || []).join(','), drawerBtn: e.getAttribute('data-filter-drawer-open') !== null })));
    console.log(`\n=== ${path}`);
    for (const f of found.slice(0, 25)) {
      console.log(`${f.drawerBtn ? 'DRAWERBTN' : f.tag} | hover=${f.hover} | "${f.text}"`);
    }
  }
  await browser.close();
})().catch((e) => { console.error('FAIL', e); process.exit(1); });
