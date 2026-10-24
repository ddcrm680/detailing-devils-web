(async function(){
  const loaderModule = new URL(
    document.currentScript?.src ? './frame-loader.js' : '/assets/js/modules/frame-loader.js',
    document.currentScript?.src || document.baseURI,
  );
  const reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;
  let lenis=null;

  // Shared navigation lives in navigation.js, including on non-home pages.
  // Keep Lenis paused while the full-screen navigation menu is open.
  window.addEventListener('dd:menu-toggle', (event) => {
    if (lenis) {
      event.detail.open ? lenis.stop() : lenis.start();
    }
  });

  /* ---------- scroll-driven hero ----------
     1) complete three-car line-up, centred with side gaps → all three move forward together (one camera move)
     2) camera continues into the McLaren interior (video frames)   3) fade to black → next section */
  const N=window.DD_FRAMES.length,src=i=>window.DD_FRAMES[i];
  const cv=document.getElementById('cv'),cx=cv.getContext('2d'),seq=document.getElementById('seq');
  const htop=document.getElementById('htop'),hbot=document.getElementById('hbot'),hint=document.getElementById('hint'),meter=document.querySelector('.meter'),dim=document.querySelector('.stage .dim');
  const fnum=document.getElementById('fnum'),fbar=document.getElementById('fbar'),loader=document.getElementById('loader');
  const BG='#070707';
  const { FrameLoader } = await import(loaderModule.href);
  const frames = new FrameLoader({
    count: N, source: src, frameBytes: 1920 * 1080 * 4,
    nativeImages: () => true, fetchConcurrency: (compact) => compact ? 2 : 4,
    onProgress: (fraction, ready) => {
      loader.style.width = `${fraction * 100}%`;
      if (ready) loader.style.opacity = 0;
    },
  });
  let last='';
  const clamp=(v,a,b)=>Math.min(b,Math.max(a,v)),lerp=(a,b,t)=>a+(b-a)*t;
  const ease=t=>1-Math.pow(1-t,3),inout=t=>t<.5?4*t*t*t:1-Math.pow(-2*t+2,3)/2;
  function size(){const d=Math.min(devicePixelRatio||1,2),w=Math.round(cv.clientWidth*d),h=Math.round(cv.clientHeight*d);if(w===cv.width&&h===cv.height)return;cv.width=w;cv.height=h;last=''}
  const IW=1920,IH=1080,EW=1920;                                       // uploaded banner frame size
  function scales(){
    const W=cv.width,H=cv.height,wide=W/H>1.1;
    const s0=W/EW;                                                    // full-width banner; rebuilt edges keep a small gap beside each G-Wagon
    const s1=wide?Math.max(W/IW,H/IH)*1.02:(W/IW)*1.9;                 // camera has moved in; frame edges now off-screen
    return {W,H,wide,s0,s1};
  }
  function paint(im,s,cy,W,H,edge){
    const w=(im.naturalWidth||im.width)*s,h=(im.naturalHeight||im.height)*s,x=(W-w)/2,y=cy-h/2;
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
    const start=frames.cache.get(0),current=frames.get(fi),im=current?.image;
    const key=[W,H,fi,k.toFixed(4),!!start,current?.version].join('|');if(key===last)return fi;last=key;
    cx.fillStyle=BG;cx.fillRect(0,0,W,H);
    const s=sp<=P1?lerp(s0,s1,k):s1*(1+.05*clamp((sp-P1)/(P2-P1),0,1));
    const h0=IH*s0,cy0=wide?Math.max(H*.5,H-h0*.47):H*.56,cy=lerp(cy0,H*.5,k);
    const u=1-clamp(sp/.035,0,1);                                    // full uncropped line-up at rest; hands over within the first scroll
    if(start&&u>0){cx.globalAlpha=u;paint(start,s,cy,W,H,true);cx.globalAlpha=1}
    if(im){const w=IW*s,x=(W-w)/2;
      if(u>=1&&start){}                                              // at rest the widened frame is the whole picture
      else paint(im,s,cy,W,H,wide?false:true);}
    else if(start&&u<=0)paint(start,s,cy,W,H,true);
    return fi;
  }
  let sp=0;
  function tick(){
    const bounds=seq.getBoundingClientRect(),p=clamp(-bounds.top/(bounds.height-innerHeight),0,1);
    sp+=reduce?(p-sp):(p-sp)*.14;if(Math.abs(p-sp)<.0002)sp=p;
    frames.update(Math.round(clamp(sp/P2,0,1)*(N-1)),bounds.bottom>0&&bounds.top<innerHeight);
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

  /* ---------- smooth scroll + GSAP ---------- */
  if(reduce||!window.gsap||!window.ScrollTrigger)return;
  gsap.registerPlugin(ScrollTrigger);
  if(window.Lenis){
    lenis=new Lenis({lerp:.085});
    if(document.documentElement.classList.contains('menu-open'))lenis.stop();
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
