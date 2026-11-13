[javascript_tool:javascript_exec] pelani21 DeuTEi9GR_5
kareemdunestorm DerqCnRI6S_
tigerstripe.id DetV3FxmTGM
"text":"Array.from(document.querySelectorAll('a[href*=\"/post/\"]')).filter(a => !a.href.includes('/media')).map(a => { const m = a.getAttribute('href').match(/^\\/@([^/]+)\\/post\\/([^/?#]+)/); return m ? m[1] + ' ' + m[2] : null; }).filter(Boolean).filter((v, i, arr) => arr.indexOf(v) === i).join('\\n')"