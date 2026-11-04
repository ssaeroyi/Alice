const { chromium } = require('playwright');
const BASE = 'https://alicemyr.tech';
const SEMAIL = 'qa.s1@qa.local';
const PASS = 'QApassw0rd!x';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', SEMAIL);
  await page.fill('input[type="password"]', PASS);
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !/login/.test(u), { timeout: 30000 });

  for (const path of ['/story/rikki-tikki-tavi', '/inbox']) {
    await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
    const html = await page.content();
    console.log(`${path}: guards=${(html.match(/data-submit-guard/g) || []).length} actives=${(html.match(/data-submit-active/g) || []).length} idles=${(html.match(/data-submit-idle/g) || []).length}`);
  }

  // double-click favourite: count POSTs
  await page.goto(`${BASE}/story/rikki-tikki-tavi`, { waitUntil: 'networkidle' });
  let favPosts = 0;
  page.on('request', (r) => { if (r.url().includes('/favorite')) favPosts++; });
  const btn = page.locator('form[action*="favorite"] button');
  await btn.click();
  await btn.click({ force: true }).catch(() => {});
  await page.waitForTimeout(2500);
  console.log('fav POSTs:', favPosts);
  console.log('fav aria-pressed:', await btn.getAttribute('aria-pressed'));
  await browser.close();
})().catch((e) => { console.error('FAIL', e); process.exit(1); });
