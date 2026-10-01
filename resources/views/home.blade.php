<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<base href="{{ url('/') }}/">
<title>{{ $site['brand'] }}</title>
<meta name="description" content="{{ $site['description'] }}">
<link rel="icon" href="img/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,300..900&family=Hanken+Grotesk:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap">
@verbatim
<style>
:root{
  color-scheme:dark;
  --bg:#070707;--bg-2:#0E0E0E;--line:#212120;--text:#EDEAE4;--mute:#8E8B86;--soft:#BAB6AF;
  --red:#D7232C;--green:#3E8A63;
  --display:"Archivo","Arial Black",Helvetica,sans-serif;
  --serif:"Instrument Serif","Times New Roman",Georgia,serif;
  --body:"Hanken Grotesk","Helvetica Neue",Arial,sans-serif;
  --mono:"JetBrains Mono",ui-monospace,Menlo,monospace;
  --g:clamp(16px,3.4vw,48px);
  --ease:cubic-bezier(.7,0,.2,1);
}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:var(--body);font-size:16px;line-height:1.6;margin:0;overflow-x:hidden;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
img{display:block;max-width:100%}
:focus-visible{outline:1px solid var(--text);outline-offset:4px}
.wrap{padding-inline:var(--g)}
.lbl{font-family:var(--mono);font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--mute)}
.disp{font-family:var(--display);font-stretch:125%;font-weight:800;text-transform:uppercase;line-height:.92;letter-spacing:-.01em;margin:0;text-wrap:balance}
.w{display:inline-block;overflow:hidden;vertical-align:top;padding-bottom:.1em;margin-bottom:-.1em}
.w>span{display:inline-block;will-change:transform}
.cta{display:inline-flex;align-items:center;gap:14px;font-family:var(--mono);font-size:12px;letter-spacing:.16em;text-transform:uppercase;padding:18px 26px;border:1px solid rgba(237,234,228,.35);position:relative;overflow:hidden;isolation:isolate;transition:color .5s var(--ease),border-color .5s;cursor:pointer;background:none;color:var(--text)}
.cta::before{content:"";position:absolute;inset:0;background:var(--text);transform:scaleY(0);transform-origin:bottom;transition:transform .5s var(--ease);z-index:-1}
.cta:hover{color:var(--bg);border-color:var(--text)}
.cta:hover::before{transform:scaleY(1)}
.cta i{font-style:normal;transition:transform .5s var(--ease)}
.cta:hover i{transform:translateX(5px)}
.cta.red{background:var(--red);border-color:var(--red);color:#fff}

/* photos */
.ph{position:relative;overflow:hidden;margin:0;background:#0c0c0c}
.ph img{width:100%;height:100%;object-fit:cover;transform:scale(1.14);will-change:transform}
.ph figcaption{position:absolute;left:16px;right:16px;bottom:14px;display:flex;justify-content:space-between;gap:12px;font-family:var(--mono);font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:rgba(237,234,228,.7);z-index:2}
.ph::after{content:"";position:absolute;inset:0;background:linear-gradient(0deg,rgba(0,0,0,.5),transparent 35%);pointer-events:none}

/* ---------- HEADER ---------- */
.hdr{position:fixed;top:0;left:0;right:0;z-index:50;padding-top:env(safe-area-inset-top,0px);transition:background .4s,backdrop-filter .4s}
.hdr.solid{background:rgba(7,7,7,.72);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}
.hdr .wrap{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;height:76px}
.hdr .left{display:flex;gap:18px}
.hdr .left a,.hdr .ph-no{font-family:var(--mono);font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:rgba(237,234,228,.7);transition:color .3s}
.hdr .left a:hover{color:var(--text)}
.brand{display:flex;align-items:center;gap:10px;font-family:var(--display);font-stretch:125%;font-weight:800;font-size:15px;letter-spacing:.18em;text-transform:uppercase}
.brand svg{width:22px;height:22px}
.brand .logo{height:30px;width:auto;display:block}
footer .brand .logo{height:40px}
.hdr .right{display:flex;justify-content:flex-end;align-items:center;gap:22px}
.burger{all:unset;cursor:pointer;display:flex;align-items:center;gap:12px;font-family:var(--mono);font-size:11px;letter-spacing:.16em;text-transform:uppercase}
.burger span{display:grid;gap:5px}
.burger span b{display:block;width:24px;height:1px;background:var(--text);transition:transform .5s var(--ease)}
.menu-open .burger span b:first-child{transform:translateY(3px) rotate(45deg)}
.menu-open .burger span b:last-child{transform:translateY(-3px) rotate(-45deg)}
@media (max-width:760px){.hdr .left,.hdr .ph-no{display:none}.hdr .wrap{grid-template-columns:auto 1fr}}

.menu{position:fixed;inset:0;z-index:40;background:var(--bg);clip-path:inset(0 0 100% 0);transition:clip-path .9s var(--ease);display:grid;grid-template-columns:1.3fr 1fr;gap:var(--g);padding:110px var(--g) 40px}
.menu-open .menu{clip-path:inset(0 0 0 0)}
.menu nav{display:grid;align-content:center;gap:2px}
.menu nav a{font-family:var(--display);font-stretch:125%;font-weight:800;text-transform:uppercase;font-size:clamp(40px,7vw,100px);line-height:1.02;display:flex;align-items:baseline;gap:18px;color:#3a3936;transition:color .35s;overflow:hidden}
.menu nav a span{display:inline-block;transform:translateY(105%);transition:transform .9s var(--ease)}
.menu-open .menu nav a span{transform:none}
.menu nav a:nth-child(2) span{transition-delay:.05s}.menu nav a:nth-child(3) span{transition-delay:.1s}.menu nav a:nth-child(4) span{transition-delay:.15s}.menu nav a:nth-child(5) span{transition-delay:.2s}.menu nav a:nth-child(6) span{transition-delay:.25s}
.menu nav a:hover{color:var(--text)}
.menu nav a small{font-family:var(--mono);font-size:12px;font-weight:400;letter-spacing:.12em;color:var(--mute)}
.menu .ph{height:100%}
.menu .ph img{transform:scale(1.2);transition:transform 1.4s var(--ease)}
.menu-open .menu .ph img{transform:scale(1)}
@media (max-width:860px){.menu{grid-template-columns:1fr}.menu .ph{display:none}}

/* ---------- SCROLL BANNER ---------- */
.seq{position:relative;height:560vh}
.stage{position:sticky;top:0;height:100vh;height:100svh;overflow:hidden;background:var(--bg)}
.stage canvas{position:absolute;inset:0;width:100%;height:100%;display:block}
.stage .dim{position:absolute;inset:0;background:rgba(0,0,0,.15);pointer-events:none}
.stage .shade{position:absolute;inset:0;background:linear-gradient(180deg,rgba(7,7,7,.7) 0%,transparent 22%,transparent 72%,rgba(7,7,7,.85) 100%);pointer-events:none}
.htxt{position:absolute;left:0;right:0;text-align:center;padding-inline:var(--g);pointer-events:none;will-change:opacity,transform;color:#fff}
.htxt.top{top:clamp(112px,15vh,176px)}
.hred{display:block;font-family:var(--mono);font-size:clamp(10px,.85vw,12px);letter-spacing:.18em;text-transform:uppercase;color:var(--red);margin-bottom:16px}
.hred i{font-style:normal;opacity:.6;margin-inline:.5em}
.htxt .hsub{margin:14px auto 0;font-family:var(--mono);font-size:clamp(10px,.8vw,12px);letter-spacing:.16em;text-transform:uppercase;color:#CFCBC4;max-width:none}

.htxt h1{font-size:clamp(22px,2.85vw,57px);line-height:1.02;letter-spacing:.005em;color:#fff;text-shadow:0 4px 30px rgba(0,0,0,.6)}
.htxt.bot{bottom:clamp(58px,9.5vh,110px)}
.stage .hint{bottom:10px}
.stage .hint i{height:18px}
@media (max-aspect-ratio:11/10){
  .htxt.top{top:auto;bottom:calc(44svh + 28.2vw + 12px)}
  .htxt.bot{bottom:auto;top:calc(56svh + 28.2vw + 16px)}
}
.htxt p{max-width:36ch;margin:0 auto;color:#D9D5CE;font-size:clamp(13px,1.05vw,16px);line-height:1.55;text-shadow:0 2px 20px rgba(0,0,0,.8)}
.hint{position:absolute;left:50%;bottom:20px;transform:translateX(-50%);font-family:var(--mono);font-size:10px;letter-spacing:.2em;text-transform:uppercase;color:var(--mute);display:grid;justify-items:center;gap:8px}
.hint i{display:block;width:1px;height:28px;background:linear-gradient(var(--text),transparent);animation:drip 1.8s ease-in-out infinite}
@keyframes drip{0%{transform:scaleY(0);transform-origin:top}50%{transform:scaleY(1);transform-origin:top}51%{transform-origin:bottom}100%{transform:scaleY(0);transform-origin:bottom}}
.meter{position:absolute;right:var(--g);top:50%;transform:translateY(-50%);display:grid;gap:10px;justify-items:end;font-family:var(--mono);font-size:10px;letter-spacing:.14em;color:var(--mute);font-variant-numeric:tabular-nums}
.meter .bar{width:2px;height:120px;background:rgba(255,255,255,.15);position:relative}
.meter .bar b{position:absolute;inset:0;background:var(--red);transform-origin:top;transform:scaleY(0)}
.loader{position:absolute;left:0;bottom:0;height:2px;background:var(--red);width:0;transition:width .2s,opacity .6s}
@media (max-width:700px){.meter{display:none}.htxt.top{top:19vh}.hred i{display:block;height:0;overflow:hidden}}

/* ---------- INTRO ---------- */
.intro{padding-block:clamp(28px,4vw,56px) 0;text-align:center}
.intro h2{font-size:clamp(30px,4.4vw,62px);max-width:18ch;margin:18px auto 0}
.marq{margin-top:64px;border-block:1px solid var(--line);overflow:hidden;padding-block:26px;-webkit-mask:linear-gradient(90deg,transparent,#000 12%,#000 88%,transparent);mask:linear-gradient(90deg,transparent,#000 12%,#000 88%,transparent)}
.marq .tr{display:flex;width:max-content;animation:mq 44s linear infinite}
.marq span{font-family:var(--display);font-stretch:125%;font-weight:600;font-size:20px;letter-spacing:.2em;text-transform:uppercase;color:#4d4b47;padding-inline:36px;white-space:nowrap}
@keyframes mq{to{transform:translateX(-50%)}}
.intro .sub{max-width:52ch;margin:56px auto 0;color:var(--soft)}
.intro .cta{margin-top:32px}

/* ---------- APPROACH (horizontal) ---------- */
.approach{padding-block:clamp(90px,10vw,140px)}
.approach .head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:end;gap:24px;margin-bottom:48px}
.approach .head h2{font-size:clamp(38px,6vw,92px)}
.h-pin{overflow:hidden}
.h-track{display:flex;gap:clamp(16px,2vw,28px);padding-inline:var(--g);width:max-content}
.slide{width:min(80vw,1120px);display:grid;grid-template-columns:1.35fr 1fr;gap:clamp(20px,3vw,48px);align-items:end}
.slide .ph{aspect-ratio:4/3}
.slide .n{font-family:var(--mono);font-size:12px;letter-spacing:.16em;color:var(--mute)}
.slide h3{font-size:clamp(40px,5vw,78px);margin:14px 0 18px}
.slide p{margin:0 0 24px;color:var(--soft);max-width:38ch}
.slide .spec{font-family:var(--mono);font-size:11px;letter-spacing:.1em;color:var(--mute);border-top:1px solid var(--line);padding-top:14px;margin-bottom:28px;text-transform:uppercase}
@media (max-width:860px){.h-track{flex-direction:column;width:auto}.slide{width:100%;grid-template-columns:1fr}}

/* ---------- STATEMENT ---------- */
.stmt{display:grid;grid-template-columns:1fr 1.1fr;gap:clamp(28px,5vw,90px);align-items:center;padding-block:clamp(60px,8vw,120px)}
.stmt .ph{aspect-ratio:4/5}
.stmt h2{font-size:clamp(30px,3.8vw,56px);line-height:1}
.stmt p{color:var(--soft);max-width:48ch;margin:28px 0 36px}
@media (max-width:860px){.stmt{grid-template-columns:1fr}}

/* ---------- NUMBERS ---------- */
.nums{display:grid;grid-template-columns:repeat(4,1fr);border-block:1px solid var(--line)}
.nums div{padding:36px var(--g) 32px;border-right:1px solid var(--line)}
.nums div:last-child{border-right:0}
.nums b{display:block;font-family:var(--display);font-stretch:125%;font-weight:700;font-size:clamp(36px,4.4vw,64px);line-height:1;font-variant-numeric:tabular-nums}
.nums span{display:block;margin-top:12px}
@media (max-width:760px){.nums{grid-template-columns:1fr 1fr}.nums div:nth-child(2){border-right:0}.nums div:nth-child(-n+2){border-bottom:1px solid var(--line)}}

/* ---------- SERVICES ---------- */
.svc{padding-block:clamp(90px,10vw,140px)}
.svc .top{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:end;gap:24px;margin-bottom:48px}
.svc .top h2{font-size:clamp(38px,6vw,92px)}
.svc-in{display:grid;grid-template-columns:1fr 1fr;gap:clamp(28px,5vw,90px);align-items:start}
.svc-list{list-style:none;margin:0;padding:0}
.svc-list li{border-bottom:1px solid var(--line)}
.svc-list li:first-child{border-top:1px solid var(--line)}
.svc-list button{all:unset;box-sizing:border-box;cursor:pointer;width:100%;display:grid;grid-template-columns:48px 1fr;gap:8px;padding:24px 0}
.svc-list .k{font-family:var(--mono);font-size:11px;letter-spacing:.14em;color:var(--mute);padding-top:10px}
.svc-list h3{font-size:clamp(26px,3.2vw,46px);color:#44423e;transition:color .4s}
.svc-list .body{display:grid;grid-template-rows:0fr;transition:grid-template-rows .6s var(--ease)}
.svc-list .body>div{overflow:hidden}
.svc-list .body p{margin:16px 0 4px;color:var(--soft);max-width:46ch}
.svc-list .body .lbl{display:block;margin-top:10px}
.svc-list button:hover h3,.svc-list button[aria-expanded="true"] h3{color:var(--text)}
.svc-list button[aria-expanded="true"] .body{grid-template-rows:1fr}
.svc-vis{position:sticky;top:100px;aspect-ratio:4/5;max-width:100%}
.svc-vis .ph{position:absolute;inset:0;clip-path:inset(0 0 0 100%);transition:clip-path 1s var(--ease)}
.svc-vis .ph img{transform:scale(1.08);transition:transform 1.6s var(--ease)}
.svc-vis .ph.on{clip-path:inset(0 0 0 0)}
.svc-vis .ph.on img{transform:scale(1)}
@media (max-width:860px){.svc-in{grid-template-columns:1fr}.svc-vis{position:relative;top:0;aspect-ratio:4/3;order:-1}}

/* ---------- DULL ENDS HERE (top-view drive) ---------- */
.ends{position:relative;height:260vh;border-top:1px solid var(--line)}
.ends-stage{position:sticky;top:0;height:100vh;height:100svh;overflow:hidden;display:grid;place-items:center;background:radial-gradient(70% 60% at 50% 50%,#121212 0%,var(--bg) 70%)}
.lanes{position:absolute;top:-20%;bottom:-20%;left:50%;width:min(88vw,760px);transform:translateX(-50%);pointer-events:none;z-index:1}
.lanes::before,.lanes::after{content:"";position:absolute;top:0;bottom:0;width:2px;background:repeating-linear-gradient(180deg,rgba(237,234,228,.16) 0 70px,transparent 70px 160px);background-position:0 var(--road,0px)}
.lanes::before{left:33.3%}.lanes::after{left:66.6%}
.ends-txt{position:relative;z-index:2;text-align:center;display:grid;justify-items:center;gap:6px;padding-inline:var(--g)}
.ends .word{font-family:var(--display);font-stretch:125%;font-weight:800;text-transform:uppercase;font-size:clamp(84px,21vw,340px);line-height:.8;letter-spacing:-.02em;white-space:nowrap;position:relative;display:inline-block;color:#2c2b29}
.ends .word::after{content:"";position:absolute;left:-2%;right:-2%;top:52%;height:.05em;background:var(--red);transform:scaleX(var(--strike,1));transform-origin:left}
.ends .fin h2{font-size:clamp(52px,9vw,140px)}
.ends .fin p{color:var(--soft);max-width:40ch;margin:18px auto 0}
.topcars{position:absolute;left:0;right:0;margin-inline:auto;top:0;width:min(88vw,760px);z-index:3;transform:translateY(62vh);filter:drop-shadow(0 50px 50px rgba(0,0,0,.85));will-change:transform;pointer-events:none}

/* ---------- LINE-UP (five cars) ---------- */
.lineup{position:relative;overflow:hidden;background:var(--bg);padding-block:clamp(70px,9vw,140px) clamp(60px,8vw,120px)}
.lineup-h{position:relative;z-index:3;text-align:center;font-size:clamp(34px,5.6vw,92px);padding-inline:var(--g);margin-bottom:clamp(-60px,-3vw,-16px)}
.lineup-in{position:relative;width:min(100%,1700px);margin-inline:auto}
.lineup-in img{width:100%;height:auto;transform-origin:50% 60%;will-change:transform}
.lineup-in::before,.lineup-in::after{content:"";position:absolute;inset:-1px;pointer-events:none;z-index:2}
.lineup-in::before{background:linear-gradient(90deg,var(--bg) 0%,transparent 9%,transparent 91%,var(--bg) 100%)}
.lineup-in::after{background:linear-gradient(180deg,var(--bg) 0%,transparent 28%,transparent 74%,var(--bg) 100%)}



/* ---------- CARDS ---------- */
.contact{font-style:normal;display:grid;gap:6px;margin-top:18px;color:var(--soft);font-size:15px}
.contact a{color:var(--text)}
.contact a:hover{color:var(--red)}
.contact.big{margin-top:32px;font-size:17px;gap:8px}
.acc{color:var(--red)}
.fr .acc{color:var(--bg)}
.cards{display:grid;grid-template-columns:1fr 1fr;gap:clamp(10px,1.6vw,24px);padding-bottom:clamp(90px,10vw,150px)}
.card{position:relative;display:block;overflow:hidden}
.card .ph{aspect-ratio:4/5}
.card .ph img{transition:transform 1.4s var(--ease)}
.card:hover .ph img{transform:scale(1.2)!important}
.card .ov{position:absolute;inset:auto 0 0 0;z-index:3;padding:clamp(20px,3vw,40px);background:linear-gradient(0deg,rgba(0,0,0,.9),transparent);display:grid;gap:12px}
.card h3{font-size:clamp(34px,4.4vw,68px)}
.card p{margin:0;color:#C9C5BE;max-width:40ch}
.card .go{font-family:var(--mono);font-size:11px;letter-spacing:.16em;text-transform:uppercase;display:flex;gap:10px;margin-top:8px}
@media (max-width:760px){.cards{grid-template-columns:1fr}}

/* ---------- FRANCHISE STRIP ---------- */
.fr{background:var(--red);color:#fff;padding-block:clamp(80px,9vw,130px)}
.fr-in{display:grid;grid-template-columns:1.1fr 1fr;gap:clamp(28px,5vw,90px);align-items:start}
.fr .lbl{color:#FFD5CF}
.fr h2{font-size:clamp(44px,7vw,110px);margin-top:14px}
.fr p{max-width:48ch;color:#FFE6E1;margin:24px 0 32px;font-size:17px}
.fr .cta{border-color:#fff;color:#fff}
.fr .cta::before{background:#fff}
.fr .cta:hover{color:var(--red)}
.fr ol{list-style:none;margin:0;padding:0;border-top:1px solid rgba(255,255,255,.35)}
.fr li{display:grid;grid-template-columns:48px 1fr;gap:12px;padding:20px 0;border-bottom:1px solid rgba(255,255,255,.35)}
.fr li .n{font-family:var(--mono);font-size:12px;color:#FFD5CF;padding-top:4px}
.fr li b{display:block;font-family:var(--display);font-stretch:125%;font-weight:700;text-transform:uppercase;font-size:20px;letter-spacing:.02em}
.fr li span{color:#FFE6E1;font-size:15px}
@media (max-width:860px){.fr-in{grid-template-columns:1fr}}

/* ---------- FINAL ---------- */
.final{position:relative;overflow:hidden;padding-block:clamp(90px,11vw,160px)}
.final>.ph{position:absolute;inset:0}
.final>.ph::after{background:linear-gradient(90deg,rgba(7,7,7,.88) 0%,rgba(7,7,7,.5) 50%,rgba(7,7,7,.35) 100%)}
.final-in{position:relative;z-index:2;display:grid;grid-template-columns:1.1fr 1fr;gap:clamp(32px,5vw,90px);align-items:end}
.final .pre{font-family:var(--display);font-stretch:125%;font-weight:300;text-transform:uppercase;font-size:clamp(20px,2.2vw,30px);letter-spacing:.06em;color:var(--soft)}
.final h2{font-size:clamp(60px,10vw,160px);margin-top:10px}
.final h2 em{font-style:normal;color:var(--red)}
form{display:grid;grid-template-columns:1fr 1fr;gap:26px 24px;background:rgba(7,7,7,.6);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);padding:clamp(20px,3vw,36px);border:1px solid var(--line)}
.f{display:grid;gap:6px}
.f.full{grid-column:1/-1}
.f label{font-family:var(--mono);font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--mute)}
.f input,.f select{font:inherit;font-size:17px;color:var(--text);background:transparent;border:0;border-bottom:1px solid #3a3936;padding:10px 0;border-radius:0;width:100%;transition:border-color .3s}
.f input:focus,.f select:focus{outline:none;border-color:var(--text)}
.f select option{background:var(--bg)}
.ff{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:18px;align-items:center}
.note{margin:0;font-size:14px;color:#E9CF7A}
@media (max-width:860px){.final-in{grid-template-columns:1fr}}
@media (max-width:520px){form{grid-template-columns:1fr}}

footer{border-top:1px solid var(--line);padding-block:40px 0}
footer .row{display:grid;grid-template-columns:1.2fr 1fr 1fr 1fr;gap:32px}
footer h4{font-family:var(--mono);font-size:11px;font-weight:400;letter-spacing:.16em;text-transform:uppercase;color:var(--mute);margin:0 0 14px}
footer ul{list-style:none;margin:0;padding:0;display:grid;gap:8px}
footer ul a{color:var(--soft);font-size:15px}
footer ul a:hover{color:var(--text)}
footer .about{color:var(--soft);max-width:34ch;font-size:15px;margin:16px 0 0}
.legal{display:flex;flex-wrap:wrap;justify-content:space-between;gap:16px;margin-top:48px;padding-top:20px;border-top:1px solid var(--line)}
.giant{font-family:var(--display);font-stretch:125%;font-weight:800;text-transform:uppercase;font-size:clamp(40px,10.4vw,200px);line-height:.8;white-space:nowrap;color:#141413;margin-top:40px;overflow:hidden;letter-spacing:-.02em}
@media (max-width:760px){footer .row{grid-template-columns:1fr 1fr}}

@media (prefers-reduced-motion:reduce){
  .marq .tr,.line>span,.hint i{animation:none}
  .menu,.menu nav a span,.svc-vis .ph{transition:none}
  .ph img{transform:none}
}
</style>
@endverbatim
</head>
<body>

<header class="hdr" id="hdr">
  <div class="wrap">
    <div class="left">@foreach($site['social'] as $name => $url)<a href="{{ $url }}" target="_blank" rel="noopener">{{ $name }}</a>@endforeach</div>
    <a class="brand" href="#top" aria-label="Detailing Devils home"><img class="logo" src="img/logo.png" alt="{{ $site['brand'] }}" width="1600" height="377"></a>
    <div class="right"><a class="ph-no" href="tel:{{ $site['phone_link'] }}">{{ $site['phone'] }}</a><button class="burger" id="burger" aria-expanded="false" aria-controls="menu">Menu<span><b></b><b></b></span></button></div>
  </div>
</header>

<div class="menu" id="menu" aria-hidden="true">
  <nav aria-label="Main">
    <a href="#approach"><small>01</small><span>Approach</span></a>
    <a href="#services"><small>02</small><span>Services</span></a>
    <a href="#work"><small>03</small><span>Our Work</span></a>
    <a href="#franchise"><small>04</small><span>Franchise</span></a>
    <a href="#studios"><small>05</small><span>Studios</span></a>
    <a href="#contact"><small>06</small><span>Contact</span></a>
  </nav>
  <figure class="ph"><img src="img/interior.webp" alt="McLaren cockpit with black leather seats and green belts"></figure>
</div>

<main id="top">
  <!-- 1. SCROLL BANNER -->
  <section class="seq" id="seq" aria-label="Intro">
    <div class="stage">
      <canvas id="cv" aria-hidden="true"></canvas>
      <div class="dim"></div>
      <div class="shade"></div>
      <div class="htxt top" id="htop">
        <span class="hred">{!! collect($site['hero']['tagline'])->map(fn ($t) => e($t))->implode(' <i>|</i> ') !!}</span>
        <h1 class="disp">{{ $site['hero']['line1'] }}<br>{{ $site['hero']['line2'] }}</h1>
      </div>
      <div class="htxt bot" id="hbot"><p class="hsub">{{ $site['hero']['subline'] }}</p></div>
      <div class="hint" id="hint">Scroll<i></i></div>
      <div class="meter" aria-hidden="true"><span id="fnum">001 / {{ sprintf('%03d', $site['hero']['frames']) }}</span><div class="bar"><b id="fbar"></b></div></div>
      <div class="loader" id="loader"></div>
    </div>
  </section>

  <!-- 2. INTRO -->
  <section class="intro">
    <div class="wrap">
      <span class="lbl">Trusted with every marque</span>
      <h2 class="disp">Our approach to every <span class="acc">car</span></h2>
    </div>
    <div class="marq" aria-label="Car brands we work on">
      <div class="tr">
        <span>McLaren</span><span>Mercedes-AMG</span><span>Porsche</span><span>Lamborghini</span><span>Ferrari</span><span>Bentley</span><span>Rolls-Royce</span><span>Land Rover</span><span>BMW M</span><span>Audi Sport</span><span>Aston Martin</span><span>Maserati</span>
        <span aria-hidden="true">McLaren</span><span aria-hidden="true">Mercedes-AMG</span><span aria-hidden="true">Porsche</span><span aria-hidden="true">Lamborghini</span><span aria-hidden="true">Ferrari</span><span aria-hidden="true">Bentley</span><span aria-hidden="true">Rolls-Royce</span><span aria-hidden="true">Land Rover</span><span aria-hidden="true">BMW M</span><span aria-hidden="true">Audi Sport</span><span aria-hidden="true">Aston Martin</span><span aria-hidden="true">Maserati</span>
      </div>
    </div>
    <div class="wrap">
      <p class="sub">Every step is deliberate and every product has a purpose. We match the finish to your car, how you drive it and the standard you expect.</p>
      <a class="cta" href="#contact">Book Your Detail <i>→</i></a>
    </div>
  </section>

  <!-- 3. APPROACH -->
  <section class="approach" id="approach">
    <div class="head wrap">
      <h2 class="disp">Inspect<br>Correct<br><span class="acc">Protect</span></h2>
      <span class="lbl">Three stages, in this order, on every car</span>
    </div>
    <div class="h-pin" id="hpin">
      <div class="h-track" id="htrack">
        <article class="slide">
          <figure class="ph"><img src="img/inspect.webp" style="object-position:66% 50%" alt="Detailer checking black paint with an inspection light and a paint depth gauge" loading="lazy"><figcaption><span>Inspection</span><span>Paint &amp; lights</span></figcaption></figure>
          <div><span class="n">01 / 03</span><h3 class="disp">Inspect</h3><p>Every job starts under inspection lights. We measure paint depth and map every swirl, scratch and water spot before any product touches the car.</p><div class="spec">Paint depth gauge · swirl lights · written report</div><a class="cta" href="#contact">Start Your Detail <i>→</i></a></div>
        </article>
        <article class="slide">
          <figure class="ph"><img src="img/correct.webp" style="object-position:40% 50%" alt="Detailer machine-polishing black paint" loading="lazy"><figcaption><span>Correction</span><span>Machine polish</span></figcaption></figure>
          <div><span class="n">02 / 03</span><h3 class="disp">Correct</h3><p>Multi-step machine polishing removes defects layer by layer, taking off only what the paint can safely spare. Real gloss comes from a flat, clean surface.</p><div class="spec">1 to 3 step polish · measured removal</div><a class="cta" href="#contact">Start Your Detail <i>→</i></a></div>
        </article>
        <article class="slide">
          <figure class="ph"><img src="img/protect.webp" alt="Installer fitting paint protection film to a front wing" loading="lazy"><figcaption><span>Protection</span><span>Ceramic · PPF</span></figcaption></figure>
          <div><span class="n">03 / 03</span><h3 class="disp">Protect</h3><p>Ceramic coating or paint protection film locks the finish in, so it keeps beading water and resisting dust, sun and stone chips long after you drive out.</p><div class="spec">Ceramic · graphene · PPF · aftercare</div><a class="cta" href="#contact">Start Your Detail <i>→</i></a></div>
        </article>
      </div>
    </div>
  </section>

  <!-- 4. STATEMENT -->
  <section class="stmt wrap">
    <figure class="ph"><img src="img/statement.webp" alt="Mirror-finish black paint reflecting studio lights beside a red tail light" loading="lazy"><figcaption><span>Finish</span><span>Deep gloss</span></figcaption></figure>
    <div>
      <span class="lbl">The Devils standard</span>
      <h2 class="disp" style="margin-top:18px">A car should turn <span class="acc">heads</span> before it moves. Every panel, every edge, every reflection is considered.</h2>
      <p>From paint correction and ceramic coating to PPF, interiors and window film, each service sharpens your car's character without changing what makes it yours.</p>
      <a class="cta" href="#contact">Start Your Detail <i>→</i></a>
    </div>
  </section>

  <!-- 5. NUMBERS -->
  <section class="nums" aria-label="Detailing Devils in numbers">
    @foreach($site['numbers'] as $num)
    <div><b>{{ $num['value'] }}</b><span class="lbl">{{ $num['label'] }}</span></div>
    @endforeach
  </section>

  <!-- 6. SERVICES -->
  <section class="svc" id="services">
    <div class="wrap">
      <div class="top"><h2 class="disp">Services</h2><span class="lbl">Same process and products at every studio</span></div>
      <div class="svc-in">
        <ul class="svc-list" id="svcList"></ul>
        <div class="svc-vis" id="svcVis"></div>
      </div>
    </div>
  </section>

  <!-- 7. DULL ENDS HERE -->
  <section class="ends" id="work">
    <div class="ends-stage">
      <div class="lanes" aria-hidden="true"></div>
      <div class="ends-txt">
        <span class="word" id="dull">Dull</span>
        <div class="fin">
          <h2 class="disp">Ends <span class="acc">Here</span></h2>
          <p>Finishes that show taste, care and attention to detail. Deep colour, sharp reflections and protection that lasts.</p>
        </div>
      </div>
      <img class="topcars" id="topcars" src="img/top-cars.webp" alt="Top view of two black G-Wagons and a green McLaren driving side by side" loading="lazy">
    </div>
  </section>

  <!-- 8. CARDS -->
  <section class="cards wrap" id="studios">
    <a class="card" href="#contact">
      <figure class="ph"><img src="img/recent-work.webp" alt="Satin blue Dodge Charger SRT outside a Detailing Devils studio" loading="lazy"></figure>
      <div class="ov"><span class="lbl">Portfolio</span><h3 class="disp">Recent Work</h3><p>Supercars, SUVs and daily drivers, corrected and protected in our studios.</p><span class="go">Explore work →</span></div>
    </a>
    <a class="card" href="#contact">
      <figure class="ph"><img src="img/find-studio.webp" alt="Empty Detailing Devils studio with numbered bays at the entrance of a Detailing Devils studio" loading="lazy"></figure>
      <div class="ov"><span class="lbl">145+ locations</span><h3 class="disp">Find a Studio</h3><p>There's a Detailing Devils studio in most major Indian cities. Find the one nearest you.</p><span class="go">Find a studio →</span></div>
    </a>
  </section>

  <!-- 8b. LINE-UP -->
  <section class="lineup" aria-label="The Devils line-up">
    <h2 class="disp lineup-h">145+ Studios. <span class="acc">One</span> Standard.</h2>
    <div class="lineup-in"><img id="lineupImg" src="img/five-cars.webp" alt="Two black G-Wagons, a yellow BMW M4, a yellow Porsche 911 and a green McLaren in a dark studio" loading="lazy"></div>
  </section>

  <!-- 9. FRANCHISE -->
  <section class="fr" id="franchise">
    <div class="wrap fr-in">
      <div>
        <span class="lbl">Franchise</span>
        <h2 class="disp">Own a <span class="acc">Devils</span> studio</h2>
        <p>Join India's largest detailing network. You bring the location and the drive. We bring the brand, training, products and systems to run it.</p>
        <a class="cta" href="#contact" data-franchise>Franchise Enquiry <i>→</i></a>
      </div>
      <ol>
        @foreach($site['franchise_steps'] as $i => $step)
        <li><span class="n">{{ sprintf('%02d', $i + 1) }}</span><div><b>{{ $step['title'] }}</b><span>{{ $step['text'] }}</span></div></li>
        @endforeach
      </ol>
    </div>
  </section>

  <!-- 10. FINAL CTA -->
  <section class="final" id="contact">
    <figure class="ph"><img data-speed="10" src="img/back-view.webp" alt="" loading="lazy"></figure>
    <div class="wrap final-in">
      <div>
        <span class="pre">Are you ready to</span>
        <h2 class="disp">Refuse<br><span class="acc">Dull</span></h2>
        <address class="contact big">
          <a href="tel:{{ $site['phone_link'] }}">{{ $site['phone'] }}</a>
          <a href="mailto:{{ $site['email'] }}">{{ $site['email'] }}</a>
          <span>{{ $site['address'] }}</span>
        </address>
      </div>
      <form id="form" method="POST" action="{{ route('enquiry.store') }}" novalidate>
        @csrf
        <div class="f"><label for="n">Name</label><input id="n" name="name" autocomplete="name" required></div>
        <div class="f"><label for="p">Phone</label><input id="p" name="phone" type="tel" autocomplete="tel" placeholder="+91" required></div>
        <div class="f"><label for="c">City</label><input id="c" name="city" autocomplete="address-level2"></div>
        <div class="f"><label for="m">Car</label><input id="m" name="car" placeholder="Make and model"></div>
        <div class="f full"><label for="s">I'm interested in</label>
          <select id="s" name="interest">@foreach($site['services'] as $svc)<option>{{ $svc['title'] }}</option>@endforeach<option>Franchise enquiry</option></select>
        </div>
        <div class="ff"><button class="cta red" type="submit">Start Your Detail <i>→</i></button><p class="note" id="note" role="status"></p></div>
      </form>
    </div>
  </section>
</main>

<footer>
  <div class="wrap">
    <div class="row">
      <div>
        <a class="brand" href="#top" aria-label="Detailing Devils home"><img class="logo" src="img/logo.png" alt="{{ $site['brand'] }}" width="1600" height="377"></a>
        <p class="about">{{ $site['footer_about'] }}</p>
        <address class="contact">
          <a href="tel:{{ $site['phone_link'] }}">{{ $site['phone'] }}</a>
          <a href="mailto:{{ $site['email'] }}">{{ $site['email'] }}</a>
          <span>{{ $site['address'] }}</span>
        </address>
      </div>
      <div><h4>Services</h4><ul>@foreach(array_slice($site['services'], 0, 4) as $svc)<li><a href="#services">{{ $svc['title'] }}</a></li>@endforeach</ul></div>
      <div><h4>Company</h4><ul><li><a href="#approach">Approach</a></li><li><a href="#work">Our Work</a></li><li><a href="#franchise">Franchise</a></li><li><a href="#studios">Studios</a></li></ul></div>
      <div><h4>Follow</h4><ul>@foreach($site['social'] as $name => $url)<li><a href="{{ $url }}" target="_blank" rel="noopener">{{ $name }}</a></li>@endforeach<li><a href="#contact">Contact</a></li></ul></div>
    </div>
    <div class="legal"><span class="lbl">© {{ date('Y') }} {{ $site['brand'] }}</span><span class="lbl">Privacy · Terms · Cookies</span><a class="lbl" href="#top">Back to top ↑</a></div>
    <div class="giant" aria-hidden="true">Detailing Devils</div>
  </div>
</footer>

<script>
  window.DD_SERVICES = @json($site['services']);
  window.DD_FRAMES = @json(collect(range(1, $site['hero']['frames']))->map(fn ($i) => sprintf($site['hero']['frame_pattern'], $i))->values());
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/lenis@1.1.13/dist/lenis.min.js"></script>
@verbatim
<script>
(function(){
  const reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;
  let lenis=null;

  /* ---------- header + menu ---------- */
  const hdr=document.getElementById('hdr'),burger=document.getElementById('burger'),menu=document.getElementById('menu');
  function setMenu(open){document.body.classList.toggle('menu-open',open);burger.setAttribute('aria-expanded',open);menu.setAttribute('aria-hidden',!open);if(lenis){open?lenis.stop():lenis.start()}}
  burger.addEventListener('click',()=>setMenu(!document.body.classList.contains('menu-open')));
  menu.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>setMenu(false)));
  addEventListener('keydown',e=>{if(e.key==='Escape')setMenu(false)});
  addEventListener('scroll',()=>hdr.classList.toggle('solid',scrollY>innerHeight*.5),{passive:true});

  /* ---------- scroll-driven hero ----------
     1) complete three-car line-up, centred with side gaps → all three move forward together (one camera move)
     2) camera continues into the McLaren interior (video frames)   3) fade to black → next section */
  const N=window.DD_FRAMES.length,src=i=>window.DD_FRAMES[i];
  const cv=document.getElementById('cv'),cx=cv.getContext('2d'),seq=document.getElementById('seq');
  const htop=document.getElementById('htop'),hbot=document.getElementById('hbot'),hint=document.getElementById('hint'),meter=document.querySelector('.meter'),dim=document.querySelector('.stage .dim');
  const fnum=document.getElementById('fnum'),fbar=document.getElementById('fbar'),loader=document.getElementById('loader');
  const BG='#070707';
  const imgs=new Array(N);let loaded=0,last='';
  const start=new Image();start.decoding='async';start.onload=()=>{start.ok=true;last=''};start.src=src(0);
  const clamp=(v,a,b)=>Math.min(b,Math.max(a,v)),lerp=(a,b,t)=>a+(b-a)*t;
  const ease=t=>1-Math.pow(1-t,3),inout=t=>t<.5?4*t*t*t:1-Math.pow(-2*t+2,3)/2;
  function size(){const d=Math.min(devicePixelRatio||1,2),w=Math.round(cv.clientWidth*d),h=Math.round(cv.clientHeight*d);if(w===cv.width&&h===cv.height)return;cv.width=w;cv.height=h;last=''}
  // phones/tablets: frames are downloaded once, but only ~40 are kept decoded around the current position,
  // so memory stays low and touch scrolling never stalls or skips
  const TOUCH=matchMedia('(pointer:coarse)').matches||matchMedia('(max-width:900px)').matches;
  const urls=new Array(N),cache=new Map();let curFi=0,dir=1;
  function ensure(i){if(i<0||i>=N||!urls[i]||cache.has(i))return;const im=new Image();im.decoding='async';im.src=urls[i];cache.set(i,im);
    (im.decode?im.decode():new Promise(r=>im.onload=r)).then(()=>{im.ok=true;last=''}).catch(()=>{})}
  function prime(fi){if(fi!==curFi){dir=fi>curFi?1:-1;curFi=fi}
    for(let k=0;k<=24;k++)ensure(fi+dir*k);for(let k=1;k<=8;k++)ensure(fi-dir*k);
    if(cache.size>44)for(const [i,im] of cache){if(Math.abs(i-fi)>22){im.src='';cache.delete(i)}}}
  function nearest(i){
    if(TOUCH){prime(i);for(let k=0;k<N;k++){const a=cache.get(i-k),b=cache.get(i+k);if(a&&a.ok)return a;if(b&&b.ok)return b}return i===0&&start.ok?start:null}
    for(let k=0;k<N;k++){if(imgs[i-k]&&imgs[i-k].ok)return imgs[i-k];if(imgs[i+k]&&imgs[i+k].ok)return imgs[i+k]}return null}
  const IW=1920,IH=1080,EW=1920;                                       // uploaded banner frame size
  function scales(){
    const W=cv.width,H=cv.height,wide=W/H>1.1;
    const s0=W/EW;                                                    // full-width banner; rebuilt edges keep a small gap beside each G-Wagon
    const s1=wide?Math.max(W/IW,H/IH)*1.02:(W/IW)*1.9;                 // camera has moved in; frame edges now off-screen
    return {W,H,wide,s0,s1};
  }
  function paint(im,s,cy,W,H,edge){
    const w=im.naturalWidth*s,h=im.naturalHeight*s,x=(W-w)/2,y=cy-h/2;
    cx.drawImage(im,x,y,w,h);
    // melt every edge of the frame into the studio black so it never reads as a box (car band stays untouched)
    const T='rgba(7,7,7,0)',lin=(x0,y0,x1,y1)=>{const g=cx.createLinearGradient(x0,y0,x1,y1);g.addColorStop(0,BG);g.addColorStop(1,T);return g};
    const fx=w*.025,ft=h*.18,fb=h*(edge?.26:.14),fl=w*.15,fy0=y+h*.76;
    cx.fillStyle=lin(x,0,x+fx,0);cx.fillRect(x-1,y,fx+1,h);
    cx.fillStyle=lin(x+w,0,x+w-fx,0);cx.fillRect(x+w-fx,y,fx+1,h);
    cx.fillStyle=lin(0,y,0,y+ft);cx.fillRect(x,y-1,w,ft+1);
    cx.fillStyle=lin(0,y+h,0,y+h-fb);cx.fillRect(x,y+h-fb,w,fb+1);
    if(edge){cx.fillStyle=lin(x,0,x+fl,0);cx.fillRect(x-1,fy0,fl+1,y+h-fy0);cx.fillStyle=lin(x+w,0,x+w-fl,0);cx.fillRect(x+w-fl,fy0,fl+1,y+h-fy0);}
  }
  const P1=.18,P2=1,TXT=.03;
  function draw(sp){
    const {W,H,wide,s0,s1}=scales();
    const q=clamp(sp/P2,0,1),fi=Math.round(q*(N-1));                 // video frames advance from the very first scroll
    const k=ease(clamp(sp/P1,0,1));                                  // camera closes in at the same time
    const key=[W,H,fi,k.toFixed(4),!!start.ok,loaded].join('|');if(key===last)return fi;last=key;
    cx.fillStyle=BG;cx.fillRect(0,0,W,H);
    const s=sp<=P1?lerp(s0,s1,k):s1*(1+.05*clamp((sp-P1)/(P2-P1),0,1));
    const h0=IH*s0,cy0=wide?Math.max(H*.5,H-h0*.47):H*.56,cy=lerp(cy0,H*.5,k);
    const u=1-clamp(sp/.035,0,1);                                    // full uncropped line-up at rest; hands over within the first scroll
    if(start.ok&&u>0){cx.globalAlpha=u;paint(start,s,cy,W,H,true);cx.globalAlpha=1}
    const im=nearest(fi);
    if(im){const w=IW*s,x=(W-w)/2;
      if(u>=1&&start.ok){}                                           // at rest the widened frame is the whole picture
      else paint(im,s,cy,W,H,wide?false:true);}
    else if(start.ok&&u<=0)paint(start,s,cy,W,H,true);
    return fi;
  }
  function load(i){return new Promise(r=>{const im=new Image();im.decoding='async';im.onload=()=>{im.ok=true;imgs[i]=im;loaded++;loader.style.width=(loaded/N*100)+'%';if(loaded===N)loader.style.opacity=0;r()};im.onerror=r;im.src=src(i)})}
  function fetchFrame(i){return fetch(src(i)).then(r=>r.ok?r.blob():null).then(b=>{if(b){urls[i]=URL.createObjectURL(b);if(Math.abs(i-curFi)<=24)ensure(i)}loaded++;loader.style.width=(loaded/N*100)+'%';if(loaded===N)loader.style.opacity=0}).catch(()=>{loaded++})}
  if(TOUCH){(async()=>{for(let i=0;i<N;i+=6)await Promise.all([0,1,2,3,4,5].map(k=>i+k<N?fetchFrame(i+k):null))})()}
  else load(0).then(()=>{(async()=>{for(let i=1;i<N;i+=6)await Promise.all([0,1,2,3,4,5].map(k=>i+k<N?load(i+k):null))})()});
  function prog(){const r=seq.getBoundingClientRect(),t=r.height-innerHeight;return clamp(-r.top/t,0,1)}
  let sp=0;
  function tick(){
    const p=prog();sp+=reduce?(p-sp):(p-sp)*.14;if(Math.abs(p-sp)<.0002)sp=p;
    const fi=draw(sp);
    fnum.textContent=String(fi+1).padStart(3,'0')+' / '+String(N).padStart(3,'0');fbar.style.transform=`scaleY(${clamp(sp/P2,0,1)})`;
    const t=ease(clamp(sp/TXT,0,1));                                  // hero text drifts up and fades over the first few scrolls
    htop.style.opacity=1-t;htop.style.transform=`translateY(${-t*60}px)`;htop.style.visibility=t>=1?'hidden':'visible';
    hbot.style.opacity=1-t;hbot.style.transform=`translateY(${-t*30}px)`;hbot.style.visibility=htop.style.visibility;
    hint.style.opacity=clamp(1-sp/.03,0,1);
    // last frame is reached exactly as the banner releases, so "Our approach to every car" follows straight on
    driveTick();lineupTick();
    requestAnimationFrame(tick);
  }
  /* top-view cars drive up over "Dull Ends Here"; the red line strikes through as they pass */
  const ends=document.querySelector('.ends'),cars=document.getElementById('topcars'),dull=document.getElementById('dull'),lanes=document.querySelector('.lanes');
  let dp=0;
  function driveTick(){
    const r=ends.getBoundingClientRect();if(r.bottom<-innerHeight||r.top>innerHeight*2)return;
    const t=r.height-innerHeight,pp=clamp(-r.top/t,0,1);dp+=reduce?(pp-dp):(pp-dp)*.12;if(Math.abs(pp-dp)<.0002)dp=pp;
    const y0=innerHeight*.62,y1=-(cars.offsetHeight+innerHeight*.15);
    cars.style.transform=`translateY(${lerp(y0,y1,dp)}px)`;
    dull.style.setProperty('--strike',inout(clamp((dp-.28)/.34,0,1)));
    lanes.style.setProperty('--road',(dp*1100)+'px');
  }
  /* five-car line-up: settles into place as it scrolls through */
  const lineup=document.querySelector('.lineup'),lineupImg=document.getElementById('lineupImg');
  function lineupTick(){
    const r=lineup.getBoundingClientRect();if(r.bottom<0||r.top>innerHeight)return;
    const k=clamp((innerHeight-r.top)/(innerHeight+r.height),0,1);
    lineupImg.style.transform=`translateY(${lerp(6,-6,k)}%) scale(${lerp(1.14,1,ease(clamp(k*1.6,0,1)))})`;
  }
  addEventListener('resize',size);size();requestAnimationFrame(tick);

  /* ---------- services ---------- */
  const S=window.DD_SERVICES.map(v=>[v.title,v.text,v.meta,v.image,v.alt]);
  const list=document.getElementById('svcList'),vis=document.getElementById('svcVis');
  S.forEach((s,i)=>{
    list.insertAdjacentHTML('beforeend',`<li><button aria-expanded="${i===0}" aria-controls="sv${i}"><span class="k">0${i+1}</span><span><h3 class="disp">${s[0]}</h3><span class="body"><div><p>${s[1]}</p><span class="lbl">${s[2]}</span></div></span></span></button></li>`);
    vis.insertAdjacentHTML('beforeend',`<figure class="ph${i===0?' on':''}" id="sv${i}"><img src="img/${s[3]}" alt="${s[4]}" loading="lazy"><figcaption><span>${s[0]}</span><span>0${i+1} / 0${S.length}</span></figcaption></figure>`);
  });
  const btns=[...list.querySelectorAll('button')],shots=[...vis.children];
  btns.forEach((b,i)=>{
    const go=()=>{btns.forEach((x,j)=>x.setAttribute('aria-expanded',j===i));shots.forEach((x,j)=>x.classList.toggle('on',j===i));if(window.ScrollTrigger)setTimeout(()=>ScrollTrigger.refresh(),650)};
    b.addEventListener('click',go);
    b.addEventListener('mouseenter',()=>{if(matchMedia('(hover:hover)').matches)go()});
  });

  /* ---------- form ---------- */
  document.querySelectorAll('[data-franchise]').forEach(a=>a.addEventListener('click',()=>{document.getElementById('s').value='Franchise enquiry'}));
  document.getElementById('form').addEventListener('submit',async e=>{
    e.preventDefault();
    const f=e.target,note=document.getElementById('note'),btn=f.querySelector('button[type=submit]');
    const n=document.getElementById('n').value.trim(),p=document.getElementById('p').value.trim();
    if(!n||!p){note.textContent='Add your name and phone number so the studio can call you.';return}
    btn.disabled=true;note.textContent='Sending…';
    try{
      const r=await fetch(f.action,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:new FormData(f)});
      const d=await r.json().catch(()=>({}));
      if(r.ok){note.textContent=d.message||'Thank you. Our team will call you shortly.';f.reset()}
      else{note.textContent=(d.errors&&Object.values(d.errors)[0][0])||d.message||'Something went wrong. Please call us instead.'}
    }catch(err){note.textContent='Could not send. Please check your connection or call us.'}
    btn.disabled=false;
  });


  /* ---------- smooth scroll + GSAP ---------- */
  if(reduce||!window.gsap||!window.ScrollTrigger)return;
  gsap.registerPlugin(ScrollTrigger);
  if(window.Lenis){
    lenis=new Lenis({lerp:.085});
    lenis.on('scroll',ScrollTrigger.update);
    gsap.ticker.add(t=>lenis.raf(t*1000));gsap.ticker.lagSmoothing(0);
    document.querySelectorAll('a[href^="#"]').forEach(a=>a.addEventListener('click',e=>{const id=a.getAttribute('href');if(id==='#'){e.preventDefault();return}const el=document.querySelector(id);if(el){e.preventDefault();lenis.scrollTo(el,{offset:id==='#top'?0:-40,duration:1.4})}}));
  }
  // photo parallax inside frames
  document.querySelectorAll('main .ph img').forEach(img=>{
    if(img.closest('.svc-vis'))return;
    const sp=parseFloat(img.dataset.speed||10);
    gsap.fromTo(img,{yPercent:-sp/2},{yPercent:sp/2,ease:'none',scrollTrigger:{trigger:img.parentElement,start:'top bottom',end:'bottom top',scrub:true}});
  });
  // images open from a letterbox as they scroll in (stay partly visible at rest)
  document.querySelectorAll('.stmt .ph,.cards .ph,.slide .ph').forEach(f=>{
    gsap.fromTo(f,{clipPath:'inset(14% 6% 14% 6%)'},{clipPath:'inset(0% 0% 0% 0%)',ease:'none',scrollTrigger:{trigger:f,start:'top bottom',end:'top 40%',scrub:true}});
  });
  // headings: words rise out of a mask, staggered
  function splitWords(el){
    const walk=node=>{[...node.childNodes].forEach(n=>{
      if(n.nodeType===3){const frag=document.createDocumentFragment();n.textContent.split(/(\s+)/).forEach(part=>{if(!part)return;if(/^\s+$/.test(part)){frag.appendChild(document.createTextNode(' '));return}const w=document.createElement('span');w.className='w';const i=document.createElement('span');i.textContent=part;w.appendChild(i);frag.appendChild(w)});n.replaceWith(frag)}
      else if(n.nodeType===1&&n.tagName!=='BR')walk(n);
    })};walk(el);return el.querySelectorAll('.w>span');
  }
  document.querySelectorAll('main section:not(.seq) h2.disp,.slide h3.disp,.card h3.disp').forEach(h=>{
    const words=splitWords(h);
    gsap.from(words,{yPercent:110,duration:1.1,ease:'expo.out',stagger:.06,scrollTrigger:{trigger:h,start:'top 88%'}});
  });
  // counters
  document.querySelectorAll('.nums b').forEach(b=>{
    const m=b.textContent.match(/^(\d+)(.*)$/);if(!m)return;const o={v:0},end=+m[1];
    gsap.to(o,{v:end,duration:1.6,ease:'power2.out',scrollTrigger:{trigger:b,start:'top 90%'},onUpdate:()=>b.textContent=Math.round(o.v)+m[2]});
  });
  // pinned horizontal approach
  gsap.matchMedia().add('(min-width: 861px)',()=>{
    const track=document.getElementById('htrack'),dist=()=>track.scrollWidth-innerWidth;
    gsap.to(track,{x:()=>-dist(),ease:'none',scrollTrigger:{trigger:'#hpin',start:'center center',end:()=>'+='+dist(),pin:true,scrub:1,invalidateOnRefresh:true,anticipatePin:1}});
  });
})();
</script>
@endverbatim
</body>
</html>
