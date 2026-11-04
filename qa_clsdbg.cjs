const { chromium } = require('playwright');
const BASE = 'https://alicemyr.tech';
const EMAIL = 'qa.t2@qa.local';
const PASS = 'QApassw0rd!x';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1320, height: 900 } });
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', EMAIL);
  await page.fill('input[type="password"]', PASS);
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !/login/.test(u), { timeout: 30000 });
  await page.goto(`${BASE}/teacher/classes/15`, { waitUntil: 'networkidle' });
  console.log('title:', await page.title());
  const html = await page.content();
  console.log('has COMPLETION tile:', /completion/i.test(html));
  console.log('has students block:', /Students/i.test(html));
  console.log('has drawer opens:', (html.match(/data-story-drawer-open/g) || []).length);
  console.log('has hero word:', /Quiz score/i.test(html));
  console.log('body sample:', html.replace(/\s+/g, ' ').slice(html.indexOf('TEACHING'), html.indexOf('TEACHING') + 400));
  await browser.close();
})().catch((e) => console.error(e));
