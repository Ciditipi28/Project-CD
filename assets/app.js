'use strict';
const $=(s,r=document)=>r.querySelector(s);
const $$=(s,r=document)=>[...r.querySelectorAll(s)];
function toast(message){const el=$('#toast');if(!el)return;el.textContent=message;el.classList.add('show');clearTimeout(window.toastTimer);window.toastTimer=setTimeout(()=>el.classList.remove('show'),2600);}
$('#menu-toggle')?.addEventListener('click',()=>$('#sidebar').classList.toggle('open'));
document.addEventListener('click',e=>{if(innerWidth<=1000&&!e.target.closest('#sidebar')&&!e.target.closest('#menu-toggle'))$('#sidebar')?.classList.remove('open');});
$$('[data-print]').forEach(el=>el.addEventListener('click',()=>window.print()));
$$('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{if(!confirm(el.dataset.confirm))e.preventDefault();}));
const pos=$('#pos');
if(pos){
 const menu=JSON.parse(pos.dataset.menu), byId=new Map(menu.map(m=>[Number(m.daily_menu_item_id),m]));
 const storage='carinderease-cart-'+(pos.dataset.user||'local')+'-'+(menu[0]?.menu_date||'today');
 let cart=new Map();try{for(const x of JSON.parse(sessionStorage.getItem(storage)||'[]'))if(byId.has(x.id)&&Number.isInteger(x.qty)&&x.qty>0&&x.qty<=100)cart.set(x.id,x.qty);}catch{}
 const fmt=v=>new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP'}).format(v/100);
 const price=m=>Math.round(Number(m.selling_price)*100);
 let total=0, category='all';
 function persist(){try{sessionStorage.setItem(storage,JSON.stringify([...cart].map(([id,qty])=>({id,qty}))));}catch{}}
 function render(){
  const container=$('#cart-lines');container.replaceChildren();total=0;let count=0;
  if(!cart.size){container.innerHTML='<div class="empty"><span class="cart-emoji"><img class="food-icon" src="assets/food/combo.svg" alt=""></span><h3>A fresh order starts here.</h3><p>Choose something delicious from the menu.</p></div>';}
  for(const [id,qty]of cart){const m=byId.get(id);total+=price(m)*qty;count+=qty;const row=document.createElement('div');row.className='cart-line';const name=document.createElement('div');const strong=document.createElement('strong');strong.textContent=m.name;const small=document.createElement('small');small.textContent=fmt(price(m))+' each'+(m.includes_unli_rice?' · Unli for dine-in':'');name.append(strong,small);const control=document.createElement('div');control.className='quantity-control';for(const [text,delta]of [['−',-1],['+',1]]){const button=document.createElement('button');button.type='button';button.textContent=text;button.setAttribute('aria-label',(delta>0?'Add one ':'Remove one ')+m.name);button.addEventListener('click',()=>change(id,delta));control.append(button);if(delta<0){const n=document.createElement('span');n.textContent=qty;control.append(n);}}const bottom=document.createElement('div');bottom.className='line-bottom';const remove=document.createElement('button');remove.type='button';remove.className='remove-item';remove.textContent='Remove';remove.addEventListener('click',()=>{cart.delete(id);render();});const amount=document.createElement('b');amount.textContent=fmt(price(m)*qty);bottom.append(remove,amount);row.append(name,control,bottom);container.append(row);}
  $('#cart-count').textContent=count;$('#cart-total').textContent=fmt(total);$('#cart-json').value=JSON.stringify([...cart].map(([id,qty])=>({id,qty})));cash();persist();
 }
 function change(id,delta){const m=byId.get(id);if(!m)return;const qty=(cart.get(id)||0)+delta;if(qty>Math.min(100,Number(m.remaining))){toast('Only '+m.remaining+' portions available.');return;}if(qty<=0)cart.delete(id);else cart.set(id,qty);render();}
 function cash(){const raw=$('#cash-received').value;const tender=Math.round(Number(raw||0)*100);$('#cash-change').textContent=tender<total?'Short '+fmt(total-tender):fmt(tender-total);}
 $$('[data-add]').forEach(b=>b.addEventListener('click',()=>change(Number(b.dataset.add),1)));
 $('#cash-received').addEventListener('input',cash);
 $$('[data-cash]').forEach(b=>b.addEventListener('click',()=>{$('#cash-received').value=(b.dataset.cash==='exact'?total/100:Number(b.dataset.cash)).toFixed(2);cash();}));
 $('#clear-cart').addEventListener('click',()=>{if(!cart.size||confirm('Clear this order?')){cart.clear();$('#cash-received').value='';render();}});
 function filter(){const query=$('#dish-search').value.toLowerCase().trim();$$('.dish-card').forEach(card=>{card.hidden=!(card.dataset.name.includes(query)&&(category==='all'||card.dataset.cat===category));});}
 $('#dish-search').addEventListener('input',filter);$$('[data-category]').forEach(b=>b.addEventListener('click',()=>{category=b.dataset.category;$$('[data-category]').forEach(x=>x.classList.toggle('active',x===b));filter();}));
 $('#refresh-menu').addEventListener('click',()=>location.reload());
 document.addEventListener('keydown',e=>{if(e.key==='/'&&!['INPUT','SELECT','TEXTAREA'].includes(document.activeElement.tagName)){e.preventDefault();$('#dish-search').focus();}});
 $('.checkout-form').addEventListener('submit',e=>{if(!cart.size){e.preventDefault();toast('Add a dish to start this order.');return;}if(Math.round(Number($('#cash-received').value)*100)<total){e.preventDefault();toast('Cash received is below the total.');return;}});
 render();
}
if($('.receipt-paper')){try{Object.keys(sessionStorage).filter(k=>k.startsWith('carinderease-cart-')).forEach(k=>sessionStorage.removeItem(k));}catch{}}
// Block rapid double submits locally; server tokens and database transactions remain authoritative.
$$('form[method="post"]').forEach(form=>form.addEventListener('submit',e=>{if(e.defaultPrevented)return;if(form.dataset.submitting){e.preventDefault();return;}form.dataset.submitting='1';setTimeout(()=>$$('button',form).forEach(b=>b.disabled=true),0);}));
