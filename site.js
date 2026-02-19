// CryptoUA — Multi-direction calculator + request creation
(function(){
  const state = {
    fromAsset: "UAH",
    toAsset: "USDT",
    quoteTtlSec: 300,
    quote: null,
    directions: [], // enabled directions from API
    assets: [],
    picking: null, // 'from' | 'to'
  };

  const $ = (id)=>document.getElementById(id);

  const fromAmount   = $("fromAmount");
  const toAmount     = $("toAmount");
  const contactEl    = $("contact");
  const receipt      = $("receipt");
  const form         = $("ppForm");

  const fromAssetText= $("fromAssetText");
  const toAssetText  = $("toAssetText");
  const fromFlag     = $("fromFlag");
  const toFlag       = $("toFlag");
  const rateText     = $("rateText");
  const feeWrap      = $("feeWrap");
  const feeText      = $("feeText");

  const UI           = (window.CALC_UI && typeof window.CALC_UI==="object") ? window.CALC_UI : {};
  const ICONS        = (window.CALC_ICONS && typeof window.CALC_ICONS==="object") ? window.CALC_ICONS : {};

  const fromAssetBtn = $("fromAssetBtn");
  const toAssetBtn   = $("toAssetBtn");
  const swapBtn      = $("swapBtn");

  const fromAssetSelect = $("fromAssetSelect");
  const toAssetSelect   = $("toAssetSelect");

  function getLang(){
    const p = new URLSearchParams(location.search);
    return (p.get("lang")==="en") ? "en" : "uk";
  }

  function decimalsForAsset(asset){
    asset = String(asset||"").toUpperCase();
    const map = {UAH:0,USD:2,EUR:2,USDT:2,USDC:2,BTC:6,ETH:6};
    return (asset in map) ? map[asset] : 4;
  }
  function trimZeros(s){
    s = String(s);
    if(s.indexOf('.') === -1) return s;
    s = s.replace(/0+$/,'');
    s = s.replace(/\.$/,'');
    return s;
  }
  function fmtNum(n, dec){
    if(!Number.isFinite(n)) return "—";
    dec = (typeof dec==="number") ? dec : 2;
    return trimZeros(Number(n).toFixed(dec));
  }
  function fmtRate(n){
    if(!Number.isFinite(n) || n<=0) return "—";
    // show more precision for small rates
    const dec = (n>=1000) ? 2 : (n>=10 ? 3 : (n>=1 ? 4 : 6));
    return fmtNum(n, dec);
  }

  function setFlag(el, asset){
    if(!el) return;
    asset = String(asset||"").toUpperCase();
    const p = ICONS && ICONS[asset];
    if(p && typeof p === "string" && p.trim()){
      el.innerHTML = `<img src="${p}" alt="${asset}" loading="lazy" />`;
    } else {
      const map = {USDT:"₮",USDC:"◎",BTC:"₿",ETH:"Ξ",USD:"$",EUR:"€"};
      el.textContent = (asset === "UAH") ? "🇺🇦" : (map[asset] || "💱");
    }
  }

  function dirKey(d){
    const fa = String(d.from_asset||d.from||"").toUpperCase();
    const ta = String(d.to_asset||d.to||"").toUpperCase();
    return fa + "→" + ta;
  }

  function findDir(from, to){
    from = String(from||"").toUpperCase();
    to   = String(to||"").toUpperCase();
    return (state.directions || []).find(d =>
      String(d.from_asset||d.from||"").toUpperCase()===from &&
      String(d.to_asset||d.to||"").toUpperCase()===to
    ) || null;
  }

  function unique(arr){
    return Array.from(new Set(arr));
  }

  function getFromAssets(){
    const dirs = state.directions || [];
    const list = dirs.map(d => String(d.from_asset||d.from||"").toUpperCase()).filter(Boolean);
    return unique(list).sort();
  }
  function getToAssets(fromAsset){
    fromAsset = String(fromAsset||"").toUpperCase();
    const dirs = state.directions || [];
    const list = dirs
      .filter(d => String(d.from_asset||d.from||"").toUpperCase()===fromAsset)
      .map(d => String(d.to_asset||d.to||"").toUpperCase())
      .filter(Boolean);
    return unique(list).sort();
  }

  function updateRateLine(){
    const d = findDir(state.fromAsset, state.toAsset);
    if(d && Number(d.rate||0)>0){
      rateText.textContent = `1 ${state.fromAsset} = ${fmtRate(Number(d.rate||0))} ${state.toAsset}`;
    }else{
      rateText.textContent = `1 ${state.fromAsset} = — ${state.toAsset}`;
    }
    updateFeeLine();
  }

  function updateFeeLine(){
    const show = !!(UI.show_fee ?? true);
    const pct = (state.quote && Number.isFinite(state.quote.feePct)) ? Number(state.quote.feePct) : null;
    if(show && pct !== null){
      feeWrap.style.display = "";
      const label = UI.fee_label || (getLang()==="en" ? "Service fee" : "Комісія сервісу");
      feeText.textContent = `${label}: ${fmtNum(pct, 2)}%`;
    } else {
      feeWrap.style.display = "none";
      feeText.textContent = "";
    }
  }

  
  function renderSelectOptions(){
    if(!fromAssetSelect || !toAssetSelect) return;

    const fromList = getFromAssets();
    fromAssetSelect.innerHTML = "";
    fromList.forEach(code=>{
      const opt = document.createElement("option");
      opt.value = code;
      opt.textContent = code;
      fromAssetSelect.appendChild(opt);
    });

    if(!fromList.includes(state.fromAsset)){
      state.fromAsset = fromList[0] || state.fromAsset;
    }
    fromAssetSelect.value = state.fromAsset;

    const toList = getToAssets(state.fromAsset);
    toAssetSelect.innerHTML = "";
    toList.forEach(code=>{
      const opt = document.createElement("option");
      opt.value = code;
      opt.textContent = code;
      toAssetSelect.appendChild(opt);
    });

    if(!toList.includes(state.toAsset)){
      state.toAsset = toList[0] || state.toAsset;
    }
    toAssetSelect.value = state.toAsset;

    setFlag(fromFlag, state.fromAsset);
    setFlag(toFlag, state.toAsset);

  

    try{ syncDesktopDropdowns(); }catch(e){}

  }

// ===== Pair / swap =====
  function setPair(from, to){
    from = String(from||"").toUpperCase();
    to   = String(to||"").toUpperCase();
    state.fromAsset = from;
    state.toAsset   = to;

    // keep selects + flags consistent with state and allowed directions
    renderSelectOptions();

    state.quote = null;
    receipt.textContent = "";

    toAmount.value = "0";
    updateRateLine();
    debounceQuote();
  }

  function ensureValidPair(){
    const dirs = state.directions || [];
    if(!dirs.length) return;

    const exists = !!findDir(state.fromAsset, state.toAsset);
    if(exists) return;

    // choose first direction as default
    const d0 = dirs[0];
    const fa = String(d0.from_asset||d0.from||"UAH").toUpperCase();
    const ta = String(d0.to_asset||d0.to||"USDT").toUpperCase();
    state.fromAsset = fa;
    state.toAsset = ta;
  }

  function animateSwap(){
    if(!swapBtn) return;
    swapBtn.classList.add("swapAnim");
    setTimeout(()=>swapBtn.classList.remove("swapAnim"), 420);
  }

  function swap(){
    animateSwap();
    const oldFrom = state.fromAsset;
    const oldTo   = state.toAsset;
    const swappedExists = !!findDir(oldTo, oldFrom);

    if(swappedExists){
      setPair(oldTo, oldFrom);
      return;
    }

    // fallback: keep new FROM as previous TO, choose first available TO
    state.fromAsset = oldTo;
    const allowed = getToAssets(state.fromAsset);
    state.toAsset = allowed[0] || oldFrom;
    if(!findDir(state.fromAsset, state.toAsset)){
      // ultimate fallback to first direction
      ensureValidPair();
    }
    setPair(state.fromAsset, state.toAsset);
  }

  // ===== Quote =====
  let quoteTimer = null;
  function debounceQuote(){
    if(quoteTimer) clearTimeout(quoteTimer);
    quoteTimer = setTimeout(createQuote, 260);
  }

  async function createQuote(){
    const amount = parseFloat(String(fromAmount.value||"0").replace(',','.'));
    if(!Number.isFinite(amount) || amount<=0){
      toAmount.value = "0";
      state.quote = null;
      updateFeeLine();
      return;
    }

    try{
      const body = {
        mode: `${state.fromAsset}_TO_${state.toAsset}`,
        fromAsset: state.fromAsset,
        toAsset: state.toAsset,
        fromAmount: amount
      };
      const r = await fetch("/api/quote.php", {
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body: JSON.stringify(body)
      });
      const j = await r.json().catch(()=> ({}));
      if(!r.ok){
        state.quote = null;
        toAmount.value = "0";
        const code = j.error || "error";
        if(code==="below_min"){
          const minA = (j.min_amount ?? j.min ?? null);
          const dec = decimalsForAsset(state.fromAsset);
          if(minA !== null && Number.isFinite(Number(minA))){
            const m = fmtNum(Number(minA), dec);
            receipt.textContent = (getLang()==="en")
              ? `Minimum amount: ${m} ${state.fromAsset}`
              : `Мінімальна сума: ${m} ${state.fromAsset}`;
          } else {
            receipt.textContent = (getLang()==="en") ? "Amount is below minimum." : "Сума менша за мінімальну.";
          }
        }else if(code==="above_max"){
          const maxA = (j.max_amount ?? j.max ?? null);
          const dec = decimalsForAsset(state.fromAsset);
          if(maxA !== null && Number.isFinite(Number(maxA))){
            const m = fmtNum(Number(maxA), dec);
            receipt.textContent = (getLang()==="en")
              ? `Maximum amount: ${m} ${state.fromAsset}`
              : `Максимальна сума: ${m} ${state.fromAsset}`;
          } else {
            receipt.textContent = (getLang()==="en") ? "Amount is above maximum." : "Сума більша за максимальну.";
          }
        }else if(code==="direction_not_found"){
          receipt.textContent = (getLang()==="en") ? "Direction not available." : "Напрям недоступний.";
        }else if(code==="rate_not_set"){
          receipt.textContent = (getLang()==="en") ? "Rate not set." : "Курс не задано.";
        }else{
          receipt.textContent = (getLang()==="en") ? "Cannot calculate." : "Не вдалося розрахувати.";
        }
        updateFeeLine();
        return;
      }

      state.quote = {
        id: j.id,
        expiresAt: j.expiresAt,
        rate: Number(j.rate||0),
        feePct: Number(j.feePct||0),
        toAmount: Number(j.toAmount||0)
      };

      const dec = decimalsForAsset(state.toAsset);
      toAmount.value = trimZeros(state.quote.toAmount.toFixed(dec));
      updateFeeLine();

      // Update rate line from quote rate (authoritative)
      if(Number.isFinite(state.quote.rate) && state.quote.rate>0){
        rateText.textContent = `1 ${state.fromAsset} = ${fmtRate(state.quote.rate)} ${state.toAsset}`;
      }
    }catch(e){
      state.quote = null;
      toAmount.value = "0";
      receipt.textContent = (getLang()==="en") ? "Network error." : "Помилка мережі.";
      updateFeeLine();
    }
  }

  // ===== Load directions =====
  async function loadRates(){
    try{
      const r = await fetch("/api/rates.php", {cache:"no-store"});
      if(!r.ok) return;
      const j = await r.json().catch(()=> ({}));
      state.quoteTtlSec = Number(j.quoteTtlSec||300) || 300;
      state.directions = Array.isArray(j.directions) ? j.directions : [];

      // Build assets list
      const as = [];
      state.directions.forEach(d=>{
        const fa = String(d.from_asset||"").toUpperCase();
        const ta = String(d.to_asset||"").toUpperCase();
        if(fa) as.push(fa);
        if(ta) as.push(ta);
      });
      state.assets = unique(as).sort();

      // validate pair
      ensureValidPair();
      renderSelectOptions();
      setPair(state.fromAsset, state.toAsset);
    }catch(e){}
  }

  
  // ===== Theme toggle (day/night) =====
  function applyThemeMode(mode){
    mode = (mode==='day') ? 'day' : 'night';
    document.documentElement.classList.toggle('theme-day', mode==='day');
    document.documentElement.classList.toggle('theme-night', mode!=='day');
    try{ localStorage.setItem('themeMode', mode); }catch(e){}
    document.querySelectorAll('.themeToggleBtn').forEach(btn=>{
      btn.setAttribute('aria-pressed', mode==='day' ? 'true' : 'false');
    });
  }

  function initThemeToggle(){
    const btns = Array.from(document.querySelectorAll('.themeToggleBtn'));
    let saved = null;
    try{ saved = localStorage.getItem('themeMode'); }catch(e){}
    if(saved==='day' || saved==='night'){
      applyThemeMode(saved);
    } else {
      const isDay = document.documentElement.classList.contains('theme-day');
      btns.forEach(btn=>btn.setAttribute('aria-pressed', isDay ? 'true' : 'false'));
    }
    if(!btns.length) return;
    btns.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const isDay = document.documentElement.classList.contains('theme-day');
        applyThemeMode(isDay ? 'night' : 'day');
      });
    });
  }

  // Expose theme helpers for admin/mobile menu dynamic UI
  try{
    window.CryptoUA = window.CryptoUA || {};
    window.CryptoUA.applyThemeMode = applyThemeMode;
    window.CryptoUA.initThemeToggle = initThemeToggle;
  }catch(e){}

// ===== Desktop custom dropdowns (glassy) =====
  const DD = { from:null, to:null, docClick:false };
  function emojiFor(asset){
    const map = {USDT:"₮",USDC:"◎",BTC:"₿",ETH:"Ξ",USD:"$",EUR:"€"};
    return (asset==="UAH") ? "🇺🇦" : (map[asset] || "💱");
  }
  function iconHTML(asset){
    asset = String(asset||"").toUpperCase();
    const p = ICONS && ICONS[asset];
    if(p && typeof p==="string" && p.trim()){
      return `<img src="${p}" alt="${asset}" loading="lazy" />`;
    }
    return `<span class="emoji">${emojiFor(asset)}</span>`;
  }
  function isDesktopDD(){
    try{
      return window.matchMedia('(min-width:1024px)').matches && window.matchMedia('(pointer:fine)').matches;
    }catch(e){ return window.innerWidth>=1024; }
  }
  function buildDD(selectEl){
    if(!selectEl) return null;
    const wrap = selectEl.closest('.assetSelectWrap');
    if(!wrap) return null;
    const ddEl = wrap.querySelector('.assetDD') || null;
    if(!ddEl) return null;
    ddEl.innerHTML = `
      <button type="button" class="assetDDBtn" aria-haspopup="listbox" aria-expanded="false"></button>
      <div class="assetDDList" role="listbox"></div>
    `;
    const btn = ddEl.querySelector('.assetDDBtn');
    const list = ddEl.querySelector('.assetDDList');

    btn.addEventListener('click', (e)=>{
      e.preventDefault();
      if(!isDesktopDD()) return;
      const open = ddEl.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true':'false');
    });

    // close on outside click
    if(!DD.docClick){
      DD.docClick = true;
      document.addEventListener('click', (e)=>{
        document.querySelectorAll('.assetDD.open').forEach(d=>{
          if(!d.contains(e.target)){
            d.classList.remove('open');
            const b = d.querySelector('.assetDDBtn');
            if(b) b.setAttribute('aria-expanded','false');
          }
        });
      });
      document.addEventListener('keydown', (e)=>{
        if(e.key==='Escape'){
          document.querySelectorAll('.assetDD.open').forEach(d=>{
            d.classList.remove('open');
            const b = d.querySelector('.assetDDBtn');
            if(b) b.setAttribute('aria-expanded','false');
          });
        }
      });
    }
    return { ddEl, btn, list, selectEl };
  }

  function syncDD(dd){
    if(!dd || !dd.selectEl) return;
    if(!isDesktopDD()) { 
      dd.ddEl.classList.remove('open');
      dd.btn.setAttribute('aria-expanded','false');
      return; 
    }
    const v = String(dd.selectEl.value||"").toUpperCase();
    dd.btn.innerHTML = `<span class=\"assetDDItemIcon\">${iconHTML(v)}</span><span style=\"margin-left:10px\">${v}</span>`;
    dd.list.innerHTML = "";
    const opts = Array.from(dd.selectEl.options || []);
    opts.forEach(opt=>{
      const code = String(opt.value||"").toUpperCase();
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'assetDDItem' + (code===v ? ' active':'');
      item.innerHTML = `<span class="assetDDItemIcon">${iconHTML(code)}</span><span>${code}</span>`;
      item.addEventListener('click', ()=>{
        dd.selectEl.value = code;
        dd.selectEl.dispatchEvent(new Event('change', {bubbles:true}));
        dd.ddEl.classList.remove('open');
        dd.btn.setAttribute('aria-expanded','false');
      });
      dd.list.appendChild(item);
    });
  }

  function initDesktopDropdowns(){
    if(!fromAssetSelect || !toAssetSelect) return;
    DD.from = buildDD(fromAssetSelect);
    DD.to   = buildDD(toAssetSelect);
    syncDD(DD.from);
    syncDD(DD.to);
  }
  function syncDesktopDropdowns(){
    syncDD(DD.from);
    syncDD(DD.to);
  }

// ===== Events =====
  if(fromAssetSelect){
    fromAssetSelect.addEventListener("change", ()=>{
      state.fromAsset = String(fromAssetSelect.value||"").toUpperCase();
      const toList = getToAssets(state.fromAsset);
      if(!toList.includes(state.toAsset)) state.toAsset = toList[0] || state.toAsset;
      setPair(state.fromAsset, state.toAsset);
    });
  }
  if(toAssetSelect){
    toAssetSelect.addEventListener("change", ()=>{
      state.toAsset = String(toAssetSelect.value||"").toUpperCase();
      setPair(state.fromAsset, state.toAsset);
    });
  }

  if(swapBtn) swapBtn.addEventListener("click", swap);

  if(fromAmount) fromAmount.addEventListener("input", debounceQuote);

  if(form) form.addEventListener("submit", async (e)=>{
    e.preventDefault();
    receipt.textContent = "";

    const amount = parseFloat(String(fromAmount.value||"0").replace(',','.'));
    if(!Number.isFinite(amount) || amount<=0){
      receipt.textContent = (getLang()==="en") ? "Enter amount." : "Введіть суму.";
      return;
    }
    const contactVal = String(contactEl && contactEl.value || '').trim();
    if(!contactVal){
      receipt.textContent = (getLang()==='en') ? 'Phone is required.' : 'Телефон обовʼязковий.';
      try{ contactEl && contactEl.focus(); }catch(e){}
      return;
    }

    if(!state.quote || !state.quote.id){
      await createQuote();
      if(!state.quote || !state.quote.id) return;
    }

    const payload = {
      lang: getLang(),
      quoteId: state.quote.id,
      mode: `${state.fromAsset}_TO_${state.toAsset}`,
      from: { asset: state.fromAsset, amount: amount },
      to:   { asset: state.toAsset, amount: Number(toAmount.value||0) },
      name: "",
      contact: contactVal,
      payout: ""
    };

    try{
      const r = await fetch("/api/order.php", {
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body: JSON.stringify(payload)
      });
      const j = await r.json().catch(()=> ({}));
      if(!r.ok){
        receipt.textContent = (getLang()==="en") ? "Cannot create request." : "Не вдалося створити заявку.";
        return;
      }

      const orderNo = (j.orderId ?? j.id ?? j.order_id ?? j.orderID ?? j.data?.orderId ?? j.data?.id ?? '—');

      receipt.textContent = (getLang()==="en")
        ? `Request #${orderNo} created. Operator will contact you.`
        : `Заявка #${orderNo} створена. Оператор звʼяжеться з вами.`;

      fromAmount.value = "0";
      toAmount.value = "0";
      state.quote = null;
      updateRateLine();
      updateFeeLine();
    }catch(err){
      receipt.textContent = (getLang()==="en") ? "Network error." : "Помилка мережі.";
    }
  });

  // Smooth scroll for anchors (landing nav)
  document.querySelectorAll('a[href^="#"]').forEach(a=>{
    a.addEventListener("click",(e)=>{
      const id = a.getAttribute("href");
      if (!id || id === "#") return;
      const el = document.querySelector(id);
      if (!el) return;
      e.preventDefault();
      el.scrollIntoView({behavior:"smooth", block:"start"});
    });
  });

  // Init
  try{ initThemeToggle(); }catch(e){}
  try{ initDesktopDropdowns(); }catch(e){}
  loadRates();
})();

