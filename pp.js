const ASSETS = ["USDT","BTC","ETH"];
const state = {
  mode: "CRYPTO_TO_UAH",
  fromAsset: "USDT",
  toAsset: "UAH",
  feePctC2U: 0.8,
  feePctU2C: 0.8,
  quoteTtlSec: 300,
  rates: {},
  quote: null
};

const $ = (id)=>document.getElementById(id);
const fromAmount = $("fromAmount");
const toAmount   = $("toAmount");
const nameEl     = $("name");
const contactEl  = $("contact");
const payoutEl   = $("payout");

const fromAssetText = $("fromAssetText");
const toAssetText   = $("toAssetText");
const rateText  = $("rateText");
const feeText   = $("feeText");
const netText   = $("netText");
const quoteText = $("quoteText");

const tabC2U = $("tabC2U");
const tabU2C = $("tabU2C");

const modal = $("modal");
const receipt = $("receipt");

function openModal(){ modal.classList.add("is-open"); modal.setAttribute("aria-hidden","false"); }
function closeModal(){ modal.classList.remove("is-open"); modal.setAttribute("aria-hidden","true"); }
modal.addEventListener("click",(e)=>{ if(e.target?.dataset?.close) closeModal(); });
document.addEventListener("keydown",(e)=>{ if(e.key==="Escape") closeModal(); });

function format(n, dec=2){
  if (!isFinite(n)) return "—";
  return Number(n).toLocaleString("uk-UA",{minimumFractionDigits:dec,maximumFractionDigits:dec});
}

function getRate(fromA,toA){
  if (fromA !== "UAH" && toA === "UAH") return state.rates[`${fromA}_UAH`] || 0;
  if (fromA === "UAH" && toA !== "UAH") return state.rates[`${toA}_UAH`] || 0;
  return 0;
}
function feePct(){ return state.mode==="CRYPTO_TO_UAH" ? state.feePctC2U : state.feePctU2C; }
function applyFee(v){ return v * (1 - feePct()/100); }

function updateLabels(){
  fromAssetText.textContent = state.fromAsset;
  toAssetText.textContent = state.toAsset;
  feeText.textContent = `${feePct().toFixed(1)}%`;
}

function setMode(mode){
  state.mode = mode;
  state.quote = null;
  if(mode==="CRYPTO_TO_UAH"){
    state.fromAsset = state.fromAsset==="UAH" ? "USDT" : state.fromAsset;
    state.toAsset = "UAH";
  } else {
    state.fromAsset = "UAH";
    state.toAsset = state.toAsset==="UAH" ? "USDT" : state.toAsset;
  }
  updateLabels();
  recalc();
}

tabC2U.addEventListener("click",()=>setMode("CRYPTO_TO_UAH"));
tabU2C.addEventListener("click",()=>setMode("UAH_TO_CRYPTO"));

$("fromAssetBtn").addEventListener("click",()=>{
  if(state.mode==="UAH_TO_CRYPTO") return;
  const i = ASSETS.indexOf(state.fromAsset);
  state.fromAsset = ASSETS[(i+1)%ASSETS.length];
  updateLabels();
  recalc();
  scheduleQuote();
});
$("toAssetBtn").addEventListener("click",()=>{
  if(state.mode==="CRYPTO_TO_UAH") return;
  const i = ASSETS.indexOf(state.toAsset);
  state.toAsset = ASSETS[(i+1)%ASSETS.length];
  updateLabels();
  recalc();
  scheduleQuote();
});

async function fetchRates(){
  const res = await fetch(`/api/rates.php`, {cache:"no-store"});
  if(!res.ok) throw new Error("rates");
  const data = await res.json();
  state.rates = data.rates || {};
  state.feePctC2U = typeof data.feePctC2U==="number"?data.feePctC2U:state.feePctC2U;
  state.feePctU2C = typeof data.feePctU2C==="number"?data.feePctU2C:state.feePctU2C;
  state.quoteTtlSec = typeof data.quoteTtlSec==="number"?data.quoteTtlSec:state.quoteTtlSec;
  updateLabels();
  recalc();
}

function recalc(){
  const amt = parseFloat(fromAmount.value||"0");
  if(!amt){ rateText.textContent="—"; netText.textContent="—"; toAmount.value=""; return; }

  if(state.mode==="CRYPTO_TO_UAH"){
    const r = getRate(state.fromAsset,"UAH");
    rateText.textContent = r ? `1 ${state.fromAsset} ≈ ${format(r,2)} UAH` : "—";
    const gross = amt * r;
    toAmount.value = gross ? gross.toFixed(2) : "";
    const net = applyFee(gross);
    netText.textContent = gross ? `${format(net,2)} UAH` : "—";
  } else {
    const r = getRate("UAH",state.toAsset);
    rateText.textContent = r ? `1 ${state.toAsset} ≈ ${format(r,2)} UAH` : "—";
    const gross = r ? (amt / r) : 0;
    toAmount.value = gross ? gross.toFixed(8) : "";
    const net = applyFee(gross);
    netText.textContent = gross ? `${net.toFixed(8)} ${state.toAsset}` : "—";
  }

  if(state.quote && Date.now() < state.quote.expiresAt){
    const dt = new Date(state.quote.expiresAt);
    quoteText.textContent = `#${state.quote.id} • до ${dt.toLocaleTimeString([], {hour:"2-digit", minute:"2-digit"})}`;
  } else {
    quoteText.textContent = "—";
  }
}

fromAmount.addEventListener("input",()=>{ recalc(); scheduleQuote(); });

let qTimer=null;
function scheduleQuote(){
  if(qTimer) clearTimeout(qTimer);
  qTimer=setTimeout(()=>createQuote().catch(()=>{}), 500);
}

async function createQuote(){
  const amt = parseFloat(fromAmount.value||"0");
  if(!amt || amt<=0) return;
  const payload={
    mode: state.mode,
    fromAsset: state.mode==="CRYPTO_TO_UAH"?state.fromAsset:"UAH",
    toAsset: state.mode==="CRYPTO_TO_UAH"?"UAH":state.toAsset,
    fromAmount: amt
  };
  const res = await fetch(`/api/quote.php`,{
    method:"POST",
    headers:{"Content-Type":"application/json"},
    body:JSON.stringify(payload)
  });
  if(!res.ok) return;
  const q = await res.json();
  state.quote = { id:q.id, expiresAt:q.expiresAt, rate:q.rate };
  recalc();
}

document.querySelectorAll(".hot-lang").forEach(btn=>{
  btn.addEventListener("click", ()=>{
    const lang = btn.dataset.lang;
    if(lang==="en") window.location.href="/?lang=en";
    else window.location.href="/?lang=uk";
  });
});

$("ppForm").addEventListener("submit", async (e)=>{
  e.preventDefault();
  if(!state.quote || Date.now()>=state.quote.expiresAt){
    await createQuote();
  }
  if(!state.quote) return;

  const payload = {
    lang: (new URLSearchParams(location.search).get("lang")||"uk"),
    quoteId: state.quote.id,
    mode: state.mode,
    from: { amount: fromAmount.value, asset: state.mode==="CRYPTO_TO_UAH"?state.fromAsset:"UAH" },
    to: { amount: toAmount.value, asset: state.mode==="CRYPTO_TO_UAH"?"UAH":state.toAsset },
    name: nameEl.value,
    contact: contactEl.value,
    payout: payoutEl.value
  };

  const res = await fetch(`/api/order.php`,{
    method:"POST",
    headers:{"Content-Type":"application/json"},
    body:JSON.stringify(payload)
  });

  if(!res.ok){
    receipt.textContent="Помилка створення заявки.";
    openModal();
    return;
  }

  const out = await res.json();
  const exp = out.quoteExpiresAt ? new Date(out.quoteExpiresAt) : null;

  receipt.textContent =
`ORDER: #${out.orderId}
QUOTE: #${payload.quoteId} ${exp ? "до "+exp.toLocaleTimeString([], {hour:"2-digit", minute:"2-digit"}) : ""}
MODE:  ${payload.mode}
FROM:  ${payload.from.amount} ${payload.from.asset}
TO:    ${payload.to.amount} ${payload.to.asset}
NAME:  ${payload.name}
CONTACT: ${payload.contact}
UAH:   ${payload.payout || "—"}`;

  openModal();
});

(async function init(){
  updateLabels();
  try{ await fetchRates(); }catch{}
  setInterval(()=>fetchRates().catch(()=>{}), 30000);
})();