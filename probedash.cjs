const { chromium } = require('playwright');
const fs = require('fs');
const BASE = 'https://alicemyr.tech';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1320, height: 900 } });
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', 'qa.tp@qa.local');
  await page.fill('input[type="password"]', 'QApassw0rd!x');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !/login/.test(u), { timeout: 30000 });

  await page.goto(`${BASE}/teacher`, { waitUntil: 'networkidle' });
  const html = await page.content();
  fs.writeFileSync('C:/Users/Martin/AppData/Local/Temp/dash.html', html);

  const checks = {
    navDashboard: /Dashboard/.test(html),
    navOverview: /Overview/.test(html),
    panelWhereClassStands: /Where the class stands/.test(html),
    headingReadingLevels: /Reading levels/.test(html),
    canvas: /data-reading-canvas/.test(html),
    independentLegend: /Independent/.test(html),
    panelStoriesToWatch: /Stories to watch/.test(html),
    panelTextDifficulty: /Text difficulty/.test(html),
    panelClassAssignments: /Class & assignments/.test(html),
    dueWeekPill: /due this week/.test(html),
    lysanne30: /lysanne30/.test(html),
    lostCoinTitle: /Read The Lost Coin/.test(html),
    className: /TP 7 - Probe/.test(html),
    attentionWord: /attention/.test(html),
  };
  for (const [k, v] of Object.entries(checks)) console.log(k.padEnd(30), v ? 'YES' : 'no');
  await browser.close();
})().catch((e) => { console.error('FAIL', e); process.exit(1); });
