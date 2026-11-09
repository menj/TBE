// WCAG 2.2 AA scan with axe-core and Playwright.
// usage: AXE_CORE=/path/to/axe.min.js [PLAYWRIGHT=/path/to/playwright] [BASE=http://127.0.0.1:8820] [THEME_COOKIE=murtadd] node tools/axe-audit.js <width> [admin] /path /path ...
// Prints each rule that fails per URL. Exit code 1 when a serious or critical rule fails.
const { chromium } = require(process.env.PLAYWRIGHT||'playwright');const fs=require('fs');
(async()=>{const theme=process.env.THEME_COOKIE||'';const base=process.env.BASE||'http://127.0.0.1:8820';let fail=0;const [width,...rest]=process.argv.slice(2);const admin=rest[0]==='admin';const urls=admin?rest.slice(1):rest;
const b=await chromium.launch();const c=await b.newContext({viewport:{width:+width,height:900}});
if(theme)await c.addCookies([{name:'t',value:theme,url:base}]);
const p=await c.newPage();
if(admin){await p.goto(base+'/wp-login.php?t='+theme);await p.fill('#user_login','a');await p.fill('#user_pass','verify-pass-123');await Promise.all([p.waitForNavigation(),p.click('#wp-submit')]);}
await p.route(u=>!/^(http:\/\/127\.0\.0\.1|data:)/.test(u.href),r=>r.abort());
const axe=fs.readFileSync(process.env.AXE_CORE||__dirname+'/axe.min.js','utf8');
for(const u of urls){try{await p.goto(base+u,{waitUntil:'load'});await p.addScriptTag({content:axe});
const v=await p.evaluate(async()=>(await axe.run(document,{runOnly:['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa','best-practice']})).violations.map(v=>({id:v.id,impact:v.impact,n:v.nodes.length,ex:v.nodes.slice(0,2).map(n=>n.target.join(' ')+' :: '+(n.any[0]&&n.any[0].message||n.failureSummary||'').slice(0,140))})));
console.log('## '+u+' @'+width+' -> '+v.length+' rules');v.forEach(x=>{if(/serious|critical/.test(x.impact))fail=1;console.log(' - '+x.impact+' '+x.id+' x'+x.n+' | '+x.ex.join(' || '))});}catch(e){fail=1;console.log('## '+u+' ERR '+e.message.slice(0,100))}}
await b.close();process.exit(fail);})();
