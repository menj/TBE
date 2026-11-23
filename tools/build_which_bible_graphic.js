#!/usr/bin/env node
/*
 * Draw the "Which Bible?" graphic (assets/img/which-bible.png, 1080 x 1350) for the page /which-bible/ (0.10.28).
 *
 * Five panels in the escalating format of a well-known meme, but about a claim rather than about people: "1 Bible",
 * then the book counts of the churches side by side, then the oldest manuscripts ending Mark at 16:8, then the
 * question and the site's address. Every number comes from the page's sources (data/canons.json and the
 * manuscripts section). Drawn in the site's colours and its interface font (Montserrat) by Playwright's Chromium.
 *
 * Usage:  node tools/build_which_bible_graphic.js
 */
const path = require('path');
const { chromium } = require('playwright');

const root = path.join(__dirname, '..');
const font = 'file://' + path.join(root, 'assets/fonts/Montserrat-Regular.woff2');
const out = path.join(root, 'assets/img/which-bible.png');

const book = (label, count, colour, h = 150) => `
  <div class="book" style="--c:${colour};height:${h}px">
    <span class="t">${label}</span><span class="n">${count}</span>
  </div>`;

const html = `<!doctype html><html><head><meta charset="utf-8"><style>
@font-face { font-family: M; src: url('${font}'); font-weight: 100 900; }
* { box-sizing: border-box; margin: 0; }
body { width: 1080px; height: 1350px; background: #f4eedc; color: #231d14; font-family: M, sans-serif; }
.panel { height: 270px; display: grid; grid-template-columns: 560px 1fr; align-items: center; border-bottom: 4px solid #231d14; padding: 0 36px; }
.left { display: flex; gap: 14px; align-items: flex-end; justify-content: center; }
.right { font-weight: 800; font-size: 64px; line-height: 1.05; text-align: center; }
.right small { display: block; font-size: 24px; font-weight: 600; color: #6e1f2e; margin-top: 10px; line-height: 1.25; }
.book { width: 78px; background: var(--c); border: 3px solid #a67c2e; border-radius: 6px 10px 10px 6px; box-shadow: 4px 6px 0 rgba(35,29,20,.25);
  display: flex; flex-direction: column; justify-content: space-between; align-items: center; padding: 10px 4px; color: #f4eedc; }
.book .t { font-size: 10px; letter-spacing: 0; word-break: break-word; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; text-align: center; line-height: 1.15; }
.book .n { font-size: 34px; font-weight: 800; color: #f2d27a; }
.big .book { width: 180px; }
.big .book .t { font-size: 19px; }
.big .book .n { font-size: 52px; }
.ms { width: 230px; height: 190px; background: #e6d9b5; border: 3px solid #8a6a2e; border-radius: 4px; padding: 14px; font-size: 19px; font-weight: 700; color: #3b2f1e; line-height: 1.3; position: relative; }
.ms em { display: block; font-style: normal; font-weight: 600; font-size: 16px; color: #6e1f2e; margin-top: 8px; }
.ms .end { position: absolute; bottom: 12px; left: 14px; right: 14px; border-top: 3px dashed #6e1f2e; padding-top: 6px; font-size: 16px; color: #6e1f2e; }
.last { height: 270px; border: 0; background: #6e1f2e; color: #f4eedc; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 14px; }
.last .q { font-size: 96px; font-weight: 800; }
.last .u { font-size: 34px; font-weight: 700; color: #f2d27a; letter-spacing: .02em; }
.red { color: #6e1f2e; }
</style></head><body>
<div class="panel"><div class="left big">${book('Holy Bible', '1', '#231d14', 220)}</div><div class="right">“1 Bible”</div></div>
<div class="panel"><div class="left big">${book('Protestant', '66', '#4a3b28', 220)}${book('Catholic', '73', '#6e1f2e', 220)}</div>
  <div class="right">“STILL 1”<small>66 books or 73? Trent fixed 73 in 1546.</small></div></div>
<div class="panel"><div class="left">${book('Samaritan', '5', '#5f7f3a', 110)}${book('Jewish', '24', '#a67c2e', 140)}${book('Protes&shy;tant', '66', '#4a3b28', 170)}${book('Catholic', '73', '#6e1f2e', 190)}${book('Orthodox', '76+', '#2f4f6f', 205)}${book('Ethiopian', '81', '#2a6f8a', 225)}</div>
  <div class="right">“1”<small>5, 24, 66, 73, 76+ or 81 books.</small></div></div>
<div class="panel"><div class="left"><div class="ms">Codex Sinaiticus<em>4th century</em><div class="end">Mark ends at 16:8</div></div><div class="ms">Codex Vaticanus<em>4th century</em><div class="end">Mark ends at 16:8</div></div></div>
  <div class="right">“1”<small>The oldest copies have no <span style="white-space:nowrap">Mark 16:9–20</span>, no <span style="white-space:nowrap">John 7:53–8:11</span>, no <span style="white-space:nowrap">1 John 5:7</span>.</small></div></div>
<div class="panel last"><div class="q">Which Bible?</div><div class="u">thebible.exposed/which-bible</div></div>
</body></html>`;

(async () => {
	const browser = await chromium.launch();
	const page = await browser.newPage({ viewport: { width: 1080, height: 1350 }, deviceScaleFactor: 1 });
	await page.setContent(html, { waitUntil: 'load' });
	await page.evaluate(() => document.fonts.ready);
	await page.screenshot({ path: out });
	await browser.close();
	console.log('wrote', out);
})();
