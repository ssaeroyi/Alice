const { chromium } = require('playwright');
const BASE = 'https://alicemyr.tech';
const EMAIL = process.argv[2];
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
  log('logged in:', page.url());

  // Pages to check: hover every white/neutral button + FILTER button,
  // and read the computed border-color under hover.
  const check = async (path, sel) => {
    await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
    const els = page.locator(sel).filter({ visible: true });
    const n = await els.count();
    if (!n) { log(`${path}: no targets`); return; }
    const results = [];
    for (let i = 0; i < Math.min(n, 4); i++) {
      const el = els.nth(i);
      await el.hover();
      await page.waitForTimeout(120);
      const border = await el.evaluate((e) => getComputedStyle(e).borderColor);
      results.push(border);
    }
    const amber = results.filter((c) => c.includes('244') || c.toLowerCase().includes('fbbf24') || c.toLowerCase().includes('fcd34d')).length;
    log(`${path}: ${n} targets | sampled ${results.length} | amber hovers: ${results.join(' ; ')}`);
  };

  // Neutral white buttons share 'hover:border-amber-300' — test by class probe
  const probeClass = async (path) => {
    await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
    const count = await page.locator('[class*="hover:border-amber-300"]').count();
    const black = await page.locator('[class*="hover:border-\\[#2D231E\\]"]').count();
    log(`${path}: amber-outline hovers=${count} black-outline hovers=${black}`);
  };

  await probeClass('/teacher/students/roster');
  await probeClass('/teacher/roster');
  // discover actual route paths from the nav
  const navs = await page.$$eval('a[href^="/teacher"]', (as) => [...new Set(as.map((a) => a.getAttribute('href')))]);
  log('teacher nav links:', navs.join(', '));
  for (const link of navs.slice(0, 6)) {
    await probeClass(link);
  }

  await browser.close();
})().catch((e) => { console.error('FAIL', e); process.exit(1); });
