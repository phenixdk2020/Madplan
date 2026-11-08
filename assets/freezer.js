(function(){
'use strict';
window.MP_FREEZER=async function(ctx){
 var content=ctx.content,api=ctx.api,esc=ctx.esc,msg=ctx.msg,fail=ctx.fail,cfg=ctx.cfg,s=ctx.s;
 content.innerHTML='<p class="mp-help">Henter fryseren…</p>';
 var inventory,shopping;
 try{var fetched=await Promise.all([api('freezer'),api('shopping')]);inventory=fetched[0];shopping=fetched[1];}
 catch(e){fail(e);return;}
 var items=inventory.items||[],names=inventory.locations||{};
 var keys=['room1','basket1','room2','basket2','room3','basket3','room4'];
 var chosen='room1';
 function options(){return keys.map(function(k){return '<option value="'+k+'">'+esc(names[k]||k)+'</option>';}).join('');}
 function count(k){return items.filter(function(v){return v.location===k;}).reduce(function(a,v){return a+Number(v.quantity||0);},0);}
 var html='<section class="mp-card"><h3>❄️ Fryser – syv placeringer</h3>'+
  '<p class="mp-help">Fryseren har fire rum fra venstre mod højre, med en ekstra kurv i hvert af de tre venstre rum. Tryk på en placering.</p>'+
  '<div class="mp-freezer-map" aria-label="Fryserens placeringer">';
 for(var room=1;room<=4;room++){
  html+='<div class="mp-freezer-column"><strong>Rum '+room+'</strong>';
  var roomKey='room'+room;
  html+='<button class="mp-freezer-location" data-location="'+roomKey+'" type="button">❄️ Rum '+room+'<small>'+count(roomKey)+' stk.</small></button>';
  if(room<4){
   var basket='basket'+room;
   html+='<button class="mp-freezer-location mp-freezer-basket" data-location="'+basket+'" type="button"><span class="mp-freezer-basket-icon" aria-hidden="true">🧺</span><span>Kurv '+room+'</span><small>'+count(basket)+' stk.</small></button>';
  }else html+='<div class="mp-freezer-blank">Ingen kurv</div>';
  html+='</div>';
 }
 html+='</div></section>'+
  '<section class="mp-card mp-freezer-panel"><details class="mp-freezer-disclosure" id="mp-freezer-register"><summary id="mp-freezer-heading">Registrer madvare</summary>'+
  '<form id="mp-freezer-form" class="mp-freezer-form"><input type="hidden" name="item_id">'+
  '<label>Varenavn<input name="title" required maxlength="255" placeholder="Fx hakket oksekød"></label>'+
  '<label>Stregkode (EAN/GTIN)<input name="barcode" inputmode="numeric" pattern="[0-9]*" maxlength="14" placeholder="Scan eller skriv koden"></label>'+
  '<div class="mp-freezer-actions"><button class="mp-btn" type="button" id="mp-freezer-lookup">Find vare</button>'+
  '<button class="mp-btn" type="button" id="mp-freezer-camera">📷 Scan stregkode</button>'+
  '<button class="mp-btn" type="button" id="mp-freezer-stop" hidden>Stop kamera</button></div>'+
  '<p id="mp-freezer-scanner-message" class="mp-help" role="status" aria-live="polite"></p>'+
  '<div id="mp-freezer-scanner" class="mp-freezer-scanner" hidden></div>'+
  '<div class="mp-freezer-fields"><label>Antal<input name="quantity" type="number" min="1" max="999" step="1" required value="1"></label>'+
  '<label>Enhed/pakning<input name="unit" maxlength="80" value="stk" placeholder="Fx pakke 500 g"></label>'+
  '<label>Placering<select name="location">'+options()+'</select></label>'+
  '<label>Indfrysningsdato<input name="frozen_at" type="date" required value="'+esc(cfg.today)+'"></label>'+
  '<label>Bedst før (valgfrit)<input name="best_before" type="date"></label></div>'+
  '<div class="mp-freezer-actions"><button class="mp-btn mp-btn-primary" type="submit" id="mp-freezer-submit">Gem i fryser</button>'+
  '<button class="mp-btn" type="button" id="mp-freezer-reset">Nulstil</button></div></form></details></section>'+
  '<section class="mp-card mp-freezer-panel"><details class="mp-freezer-disclosure" id="mp-freezer-shopping"><summary>Hent fra indkøbslisten</summary>'+
  '<p class="mp-help">Kopiér en købt vare til fryseren. Den bliver stående som købt på indkøbslisten.</p>'+
  '<form id="mp-freezer-from-shopping" class="mp-freezer-actions"><select name="shopping_id" required aria-label="Vælg købt vare">'+
  '<option value="">Vælg købt vare</option>'+
  shopping.filter(function(v){return Number(v.checked)>0;}).map(function(v){return '<option value="'+Number(v.id)+'">'+esc(v.title)+'</option>';}).join('')+
  '</select><button class="mp-btn" type="submit">Læg i fryser</button></form></details></section>'+
  '<section class="mp-card"><h3>Fryserens indhold</h3><label>Filtrér placering <select id="mp-freezer-filter">'+
  '<option value="">Alle placeringer</option>'+options()+'</select></label>'+
  '<div id="mp-freezer-items"></div></section>';
 content.innerHTML=html;
 var form=content.querySelector('#mp-freezer-form'),filter=content.querySelector('#mp-freezer-filter');
 var reader=content.querySelector('#mp-freezer-scanner'),status=content.querySelector('#mp-freezer-scanner-message');
 var stopButton=content.querySelector('#mp-freezer-stop');
 var scanner=null,scanId=0,scanning=false;
 function stop(){
  scanId++;scanning=false;
  if(scanner){var old=scanner;scanner=null;Promise.resolve().then(function(){return old.stop();}).catch(function(){}).then(function(){try{old.clear();}catch(ignore){}});}
  reader.hidden=true;stopButton.hidden=true;
 }
 s.freezerStop=stop;stopButton.onclick=stop;
 function quantityLabel(item){
  var quantity=Number(item.quantity||0),unit=String(item.unit||'').trim();
  return quantity+' stk.'+(unit&&!/^(?:stk\\.?|stykker)$/i.test(unit)?' × '+unit:'');
 }
 function freezerIcon(paths){
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'+paths+'</svg>';
 }
 var actionIcons={
  edit:freezerIcon('<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>'),
  meal:freezerIcon('<path d="M4 3v7a3 3 0 0 0 6 0V3"/><path d="M7 3v19"/><path d="M20 3v19"/><path d="M20 3c-5 2-5 9 0 9"/>'),
  shopping:freezerIcon('<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 2h3l3 14h13l3-10H5"/>'),
  delete:freezerIcon('<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 15H6L5 6"/><path d="M10 11v6M14 11v6"/>')
 };
 function freezerAction(action,label,item){
  return '<button class="mp-freezer-icon-btn'+(action==='delete'?' mp-freezer-icon-delete':'')+'" type="button" data-freezer="'+action+'" title="'+esc(label)+'" aria-label="'+esc(label+' – '+item.title)+'">'+actionIcons[action]+'</button>';
 }
 function redraw(){
  var selected=filter.value||'';
  content.querySelectorAll('.mp-freezer-location').forEach(function(b){b.classList.toggle('mp-freezer-selected',b.dataset.location===(selected||chosen));});
  var shown=items.filter(function(item){return !selected||item.location===selected;});
  var result=content.querySelector('#mp-freezer-items');
  if(!shown.length){result.innerHTML='<p class="mp-help">Ingen varer på denne placering.</p>';return;}
  result.innerHTML=shown.map(function(item){return '<article class="mp-freezer-item" data-id="'+Number(item.id)+'">'+
   '<div class="mp-freezer-item-info"><strong>'+esc(item.title)+'</strong>'+
   '<small>'+esc(names[item.location]||item.location)+' · '+esc(quantityLabel(item))+'</small>'+
   '<small>Indfrosset '+esc(item.frozen_at)+(item.best_before?' · Bedst før '+esc(item.best_before):'')+'</small></div>'+
   '<div class="mp-freezer-icon-actions" role="group" aria-label="Handlinger for '+esc(item.title)+'">'+
   freezerAction('edit','Ret/flyt',item)+
   freezerAction('meal','Til madplan',item)+
   freezerAction('shopping','Til indkøbsliste',item)+
   freezerAction('delete','Slet',item)+'</div></article>';}).join('');
  result.querySelectorAll('[data-freezer]').forEach(function(button){button.onclick=async function(){
   var id=Number(button.closest('[data-id]').dataset.id);
   var item=items.find(function(x){return Number(x.id)===id;});
   if(!item)return;
   var action=button.dataset.freezer;
   if(action==='edit'){
    ['title','barcode','quantity','unit','location','frozen_at','best_before'].forEach(function(key){form.elements[key].value=item[key]||'';});
    form.elements.item_id.value=item.id;
    content.querySelector('#mp-freezer-heading').textContent='Ret eller flyt madvare';
    content.querySelector('#mp-freezer-submit').textContent='Gem ændringer';
    content.querySelector('#mp-freezer-register').open=true;
    form.scrollIntoView({behavior:'smooth',block:'start'});return;
   }
   if(action==='meal'){
    s.freezerDraft={title:item.title};s.start=ctx.mon(cfg.today);ctx.setTab('week');return;
   }
   if(action==='delete'&&!window.confirm('Slet '+item.title+' fra fryseren?'))return;
   button.disabled=true;
   try{
    if(action==='shopping'){await api('freezer/'+id+'/to-shopping','POST',{});msg('Varen er lagt på indkøbslisten. Fryserens antal er uændret.');}
    if(action==='delete'){await api('freezer/'+id,'DELETE');stop();await window.MP_FREEZER(ctx);msg('Varen er slettet fra fryseren.');}
   }catch(e){fail(e);}
   finally{if(button.isConnected)button.disabled=false;}
  };});
 }
 content.querySelectorAll('.mp-freezer-location').forEach(function(b){b.onclick=function(){
  chosen=b.dataset.location;form.elements.location.value=chosen;filter.value=chosen;redraw();
 };});
 filter.onchange=redraw;redraw();
 content.querySelector('#mp-freezer-reset').onclick=function(){
  form.reset();form.elements.item_id.value='';form.elements.frozen_at.value=cfg.today;
  form.elements.location.value=chosen;content.querySelector('#mp-freezer-heading').textContent='Registrer madvare';
  content.querySelector('#mp-freezer-submit').textContent='Gem i fryser';
 };
 form.onsubmit=async function(event){
  event.preventDefault();var data={};
  ['title','barcode','quantity','unit','location','frozen_at','best_before'].forEach(function(key){data[key]=form.elements[key].value;});
  var id=form.elements.item_id.value;
  var submit=content.querySelector('#mp-freezer-submit');submit.disabled=true;
  try{await api(id?'freezer/'+id:'freezer','POST',data);stop();await window.MP_FREEZER(ctx);msg(id?'Varen er opdateret.':'Varen er lagt i fryseren.');}
  catch(e){fail(e);submit.disabled=false;}
 };
 content.querySelector('#mp-freezer-from-shopping').onsubmit=async function(event){
  event.preventDefault();var id=Number(this.elements.shopping_id.value);if(!id)return;
  try{await api('freezer/from-shopping','POST',{shopping_id:id,location:form.elements.location.value});
   stop();await window.MP_FREEZER(ctx);msg('Købt vare er kopieret til fryseren.');}
  catch(e){fail(e);}
 };
 async function lookup(value){
  var code=String(value||'').trim();
  if(!/^(?:\d{8}|\d{12,14})$/.test(code)){status.textContent='Stregkoden skal være 8, 12, 13 eller 14 cifre.';return;}
  try{
   status.textContent='Slår varen op…';
   var product=await api('shopping/barcode/lookup','POST',{barcode:code});
   form.elements.barcode.value=code;
   if(product.found){form.elements.title.value=product.title;
    if(product.quantity_label)form.elements.unit.value=product.quantity_label;}
   status.textContent=product.found?'Varen er fundet. Vælg placering og tryk Gem.':'Ukendt stregkode. Skriv varenavnet manuelt og gem.';
  }catch(e){status.textContent=e.message||'Varen kunne ikke slås op.';}
 }
 content.querySelector('#mp-freezer-lookup').onclick=function(){lookup(form.elements.barcode.value);};
 content.querySelector('#mp-freezer-camera').onclick=async function(){
  if(!window.isSecureContext||!navigator.mediaDevices){
   status.textContent='Kamera kræver HTTPS og kameratilladelse. Indtast stregkoden manuelt.';return;
  }
  stop();var token=++scanId;status.textContent='Åbner kameraet…';
  try{
   if(typeof window.Html5Qrcode!=='function'){
    await new Promise(function(resolve,reject){
     var script=document.createElement('script');
     script.src='https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js';
     script.crossOrigin='anonymous';script.referrerPolicy='no-referrer';
     script.onload=resolve;script.onerror=function(){reject(new Error('Scannerbiblioteket kunne ikke hentes.'));};
     document.head.appendChild(script);
    });
   }
   if(token!==scanId||!reader.isConnected)return;
   var fmts=window.Html5QrcodeSupportedFormats;
   scanner=new window.Html5Qrcode('mp-freezer-scanner',{
    formatsToSupport:[fmts.EAN_13,fmts.EAN_8,fmts.UPC_A,fmts.UPC_E],useBarCodeDetectorIfSupported:false
   },false);
   reader.hidden=false;stopButton.hidden=false;
   await scanner.start({facingMode:'environment'},{fps:10,qrbox:{width:260,height:130}},
    function(value){if(token!==scanId||scanning)return;
     var code=String(value||'').trim();
     if(!/^(?:\d{8}|\d{12,14})$/.test(code))return;
     scanning=true;stop();lookup(code);
    },function(){});
   if(token===scanId)status.textContent='Hold stregkoden foran kameraet.';
  }catch(e){
   stop();status.textContent='Kameraet kunne ikke startes: '+(e.message||'ukendt fejl')+'. Indtast koden manuelt.';
  }
 };
};
})();