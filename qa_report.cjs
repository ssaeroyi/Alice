const { chromium } = require('playwright');
const BASE = 'https://alicemyr.tech';
const EMAIL = 'qa.t2@qa.local';
const PASS = 'QApassw0rd!x';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1320, height: 900 } });
  const log = (...a) => console.log('*', ...a);

  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', EMAIL);
  await page.fill('input[type="password"]', PASS);
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !/login/.test(u), { timeout: 30000 });

  // ===== CLASS PAGE =====
  await page.goto(`${BASE}/teacher/classes`, { waitUntil: 'networkidle' });
  const clsLink = page.locator('a[href*="/teacher/classes/"]').filter({ hasNot: page.locator('[href$="create"]') }).filter({ hasText: 'QA 7' }).first();
  await page.goto(`${BASE}/teacher/classes/15`, { waitUntil: 'networkidle' });
  const body = await page.textContent('body');
  log('class tiles visible:', /completion/i.test(body) && /quiz score/i.test(body) ? 'YES' : 'NO');
  log('roster is cards not table:', (await page.$$('table')).length === 0 ? 'YES (no tables on page)' : 'NO');
  log('card stats present:', /stories/i.test(body) && /last quiz/i.test(body) ? 'YES' : 'NO');

  // Drawer opens on the class page (no navigation)
  await page.locator('[data-story-drawer-open]').first().click();
  await page.waitForTimeout(900);
  const drawerText = await page.textContent('[data-story-drawer-body]');
  log('drawer opened in-page:', /quiz history|reading/i.test(drawerText) ? `YES (${(drawerText.match(/quiz history/i) ? 'has history' : 'light')})` : 'NO');
  const urlAfterDrawer = page.url();
  await page.locator('[data-story-drawer-close]').first().click().catch(() => {});
  log('still on class page:', urlAfterDrawer.includes('/classes/') ? 'YES' : 'NO');

  // ===== ASSIGNMENT REPORT =====
  await page.goto(`${BASE}/teacher/assignments`, { waitUntil: 'networkidle' });
  await page.locator('a[href*="assignments/"]').filter({ hasText: 'QA Assignment' }).first().click();
  await page.waitForTimeout(1500);
  const rbody = await page.textContent('body');
  log('hero tiles:', /Completion/i.test(rbody) && /Quiz accuracy/i.test(rbody) && /Pass rate/i.test(rbody) && /Participation/i.test(rbody) ? '4 YES' : 'MISSING');
  log('participants tab default:', /Order/i.test(rbody) ? 'YES' : 'NO');
  log('accuracy column:', /Not taken/i.test(rbody) ? 'YES' : 'NO');

  // Questions tab via URL state
  const aurl = page.url();
  await page.goto(aurl.replace(/tab=\w+/, 'tab=questions') + (aurl.includes('tab=') ? '' : '&tab=questions'), { waitUntil: 'networkidle' });
  const qbody = await page.textContent('body');
  log('questions bars:', /fully correct/i.test(qbody) ? 'YES' : 'NO');

  // Overview heat grid
  await page.goto(aurl.includes('tab=') ? aurl.replace(/tab=\w+/, 'tab=overview') : aurl + '&tab=overview', { waitUntil: 'networkidle' });
  const heat = await page.$$eval('[data-story-drawer-body]', () => 0);
  const cells = await page.$$eval('table tbody td span[title]', (els) => els.slice(0, 6).map((e) => e.getAttribute('title').slice(0, 40)));
  log('heat cells with hover data:', cells.length, JSON.stringify(cells).slice(0, 220));
  await browser.close();
})().catch((e) => { console.error('FAIL', e); process.exit(1); });
