(function(){
'use strict';
var root=document.getElementById('mp-app'), cfg=window.MP_PORTAL;if(!root||!cfg)return;
var content=root.querySelector('#mp-content'),status=root.querySelector('#mp-status'),home=root.querySelector('#mp-home'),main=root.querySelector('#mp-main'),tabs=root.querySelector('#mp-tabs');
var s={module:'',tab:'week',start:mon(cfg.today),week:null,next:null,recipes:null};
/* Personal preferences are stored in WordPress user_meta, not localStorage. */
var themeOptions=['default','dark','forest','purple','sand'];
var themeSelect=root.querySelector('#mp-theme-select');
function applyTheme(value){
 if(themeOptions.indexOf(value)===-1)value='default';
 root.setAttribute('data-mp-theme',value);
 for(var i=0;i<themeOptions.length;i++)document.body.classList.remove('mp-theme-'+themeOptions[i]);
 document.body.classList.add('mp-theme-'+value);
 if(themeSelect)themeSelect.value=value;
}
applyTheme(cfg.theme||'default');
if(themeSelect)themeSelect.addEventListener('change',async function(){
 var previous=(cfg.theme||'default'),selected=themeSelect.value;
 if(themeOptions.indexOf(selected)===-1){applyTheme(previous);return;}
 applyTheme(selected);
 themeSelect.disabled=true;
 try{
  var saved=await api('theme','POST',{theme:selected});
  if(!saved.saved||saved.theme!==selected)throw new Error('Serveren kunne ikke gemme temaet.');
  cfg.theme=selected;
  msg('Farvetemaet er gemt på din bruger og bruges ved næste login.');
 }catch(error){
  applyTheme(previous);
  fail(error);
 }finally{
  themeSelect.disabled=false;
 }
});

function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function url(v){v=String(v||'');return /^(https?:\/\/|\/)/i.test(v)?esc(v):'';}
function dt(s){return new Date(s+'T12:00:00Z');}
function fmt(d){return d.toISOString().slice(0,10);}
function add(s,n){var d=dt(s);d.setUTCDate(d.getUTCDate()+n);return fmt(d);}
function mon(s){return add(s,-((dt(s).getUTCDay()+6)%7));}
function label(s){return new Intl.DateTimeFormat('da-DK',{weekday:'long',day:'numeric',month:'short',timeZone:'UTC'}).format(dt(s));}
function weekNo(t){var d=dt(t);d.setUTCDate(d.getUTCDate()+4-(d.getUTCDay()||7));var first=new Date(Date.UTC(d.getUTCFullYear(),0,1));return Math.ceil((((d-first)/86400000)+1)/7);}
function msg(m,error){status.textContent=m||'';status.classList.toggle('mp-error',!!error);}
function fail(e){msg(e&&e.message?e.message:'Ukendt fejl',true);}
async function api(path,method,body){var opt={method:method||'GET',credentials:'same-origin',headers:{'X-WP-Nonce':cfg.nonce}};if(body!==undefined){opt.headers['Content-Type']='application/json';opt.body=JSON.stringify(body);}var r=await fetch(cfg.api+path,opt),j=await r.json().catch(function(){return {};});if(!r.ok)throw new Error(j.message||'Serverfejl');return j;}
function setTab(t){s.tab=t;tabs.innerHTML=(s.module==='tv'?[['week','Ugeplan'],['watch','Watchlist']]:[['week','Madplan'],['recipes','Opskrifter'],['shopping','Indkøbsliste']]).map(function(x){return '<button type="button" class="mp-tab" data-tab="'+x[0]+'" '+(t===x[0]?'aria-current="page"':'')+'>'+x[1]+'</button>';}).join('');tabs.querySelectorAll('button').forEach(function(b){b.onclick=function(){setTab(b.dataset.tab);render();};});render();}
function module(name,tab){s.module=name;home.hidden=true;main.hidden=false;root.querySelector('#mp-module-title').textContent=name==='tv'?'TV-ugeplan':'Madplan';setTab(tab||'week');}
root.querySelectorAll('[data-module]').forEach(function(b){b.onclick=function(){module(b.dataset.module);};});
function openHash(){
 var hash=(window.location.hash||'').replace(/^#/,'').toLowerCase();
 if(hash==='tv'||hash==='tv-guide')module('tv','week');
 else if(hash==='tv-watch'||hash==='watchlist')module('tv','watch');
 else if(hash==='food'||hash==='madplan')module('food','week');
 else if(hash==='shopping'||hash==='indkoeb')module('food','shopping');
 else if(hash==='recipes'||hash==='opskrifter')module('food','recipes');
}
window.addEventListener('hashchange',openHash);
openHash();
function backToMenu(){
 home.hidden=false;main.hidden=true;s.module='';s.tab='week';msg('');
 if(window.history&&window.history.replaceState){window.history.replaceState(null,'',window.location.pathname+window.location.search);}
 window.scrollTo({top:0,behavior:'smooth'});
}
root.querySelector('#mp-back').onclick=backToMenu;
root.querySelector('#mp-back-bottom').onclick=backToMenu;
function render(){msg('');if(s.tab==='week')return week();if(s.tab==='watch')return watch();if(s.tab==='recipes')return recipes();if(s.tab==='shopping')return shopping();}
function toolbar(){return '<div class="mp-toolbar"><h3>Uge '+weekNo(s.start)+'</h3><div class="mp-toolbar-actions"><button type="button" class="mp-btn" id="prev">‹</button><button type="button" class="mp-btn" id="now">I dag</button><button type="button" class="mp-btn" id="next">›</button></div></div>';}
function bindToolbar(){content.querySelector('#prev').onclick=function(){s.start=add(s.start,-7);week();};content.querySelector('#next').onclick=function(){s.start=add(s.start,7);week();};content.querySelector('#now').onclick=function(){s.start=mon(cfg.today);week();};}
function rows(){return (s.week.meals||[]).concat(s.next?s.next.meals:[]);}
function episodes(){return (s.week.episodes||[]).concat(s.next?s.next.episodes:[]);}
function meal(date,p){return rows().find(function(r){return r.meal_date===date&&Number(r.person)===p;});}
function mealTitle(date,p,seen){seen=seen||[];if(seen.indexOf(p)>=0)return 'Ugyldig henvisning';var x=meal(date,p);if(!x)return 'Ikke planlagt';return x.mode==='same'?mealTitle(date,Number(x.source_person),seen.concat([p])):(x.title||'Ikke planlagt');}
async function load(){s.week=await api('week?start='+encodeURIComponent(s.start));s.next=null;}
async function week(){
content.innerHTML=toolbar()+'<p>Henter planen…</p>';bindToolbar();
try{await load();}catch(e){return fail(e);}
var current=s.start===mon(cfg.today),limit=7;
var addText=s.module==='tv'?'+ Tilføj TV-program':'+ Tilføj aftensmad';
var html=toolbar()+'<div class="mp-mobile-actions"><button type="button" id="mp-quick-add" class="mp-btn mp-btn-primary mp-add-primary">'+addText+'</button></div>'+
'<p class="mp-help">'+(s.module==='tv'?'TVmaze-datoer er internationale/originale. Gule datoer er forventede, og dansk klokkeslæt er manuelt angivet.':'Hver dag har fire personrækker. Alle tidligere uger gemmes.')+'</p><div class="mp-days">';
for(var i=0;i<limit;i++){
 var date=add(s.start,i);
 html+='<section class="mp-day '+(date===cfg.today?'mp-today':'')+'" data-day="'+date+'"><h4>'+esc(label(date))+(date===cfg.today?'<small>I dag</small>':'')+'</h4>';
 if(s.module==='tv'){
  var dayEpisodes=episodes().filter(function(e){return e.date===date;});
  if(!dayEpisodes.length)html+='<p class="mp-help">Ingen afsnit</p>';
  dayEpisodes.forEach(function(e){html+='<div class="mp-episode"><span class="mp-time">'+esc(e.time_label||'—')+'</span><div class="mp-episode-body"><strong>'+esc(e.show)+'</strong><small>S'+esc(e.season)+' · A'+esc(e.episode)+(e.episode_title?' · '+esc(e.episode_title):'')+'</small><small>'+esc(e.service||'Streaming ukendt')+'</small><span class="mp-tag '+(e.status==='estimated'?'mp-estimate':'')+'">'+(e.status==='estimated'?'Forventet':'TVmaze-dato')+'</span><small>'+esc(e.note)+'</small></div></div>';});
 }else{
  s.week.people.forEach(function(name,n){var p=n+1,x=meal(date,p);html+='<div class="mp-person"><strong>'+esc(name)+'</strong><span class="mp-meal-label">'+esc(mealTitle(date,p))+'</span>'+(x&&x.mode==='same'?'<span class="mp-same-as">Samme som '+esc(s.week.people[Number(x.source_person)-1])+'</span>':'')+'</div>';});
  if(cfg.canEdit)html+='<button type="button" class="mp-btn edit-day" data-date="'+date+'">+ Tilføj / rediger</button>';
 }
 html+='</section>';
}
content.innerHTML=html+'</div>';bindToolbar();
content.querySelector('#mp-quick-add').onclick=function(){
 if(s.module==='tv') { setTab('watch'); }
 else { editDay(current?cfg.today:s.start); }
};
content.querySelectorAll('.edit-day').forEach(function(b){b.onclick=function(){editDay(b.dataset.date);};});
}
async function getRecipes(){if(s.recipes===null)s.recipes=await api('recipes');return s.recipes;}
async function editDay(date){
var recipesList=[];try{recipesList=await getRecipes();}catch(e){fail(e);}
var html='<div class="mp-toolbar"><h3>'+esc(label(date))+'</h3><button type="button" class="mp-btn" id="back-week">Til ugeplan</button></div><div class="mp-card"><p class="mp-help">Egen ret eller samme som en anden person. Opskrifter oprettes på PC.</p>';
s.week.people.forEach(function(name,n){
 var p=n+1,x=meal(date,p),value=x&&x.mode==='same'?String(x.source_person):'own';
 html+='<div class="mp-person edit-person" data-person="'+p+'"><strong>'+esc(name)+'</strong><div class="mp-edit-controls"><select class="mode"><option value="own">Egen ret</option>';
 s.week.people.forEach(function(other,j){if(j!==n)html+='<option value="'+(j+1)+'" '+(value===String(j+1)?'selected':'')+'>Samme som '+esc(other)+'</option>';});
 html+='</select><div class="own" '+(value!=='own'?'hidden':'')+'><input class="meal-name" type="text" maxlength="255" placeholder="Aftensmad" value="'+esc(x&&x.mode==='own'?x.title:'')+'"><select class="recipe"><option value="0">Ingen tilknyttet opskrift</option>';
 recipesList.forEach(function(r){html+='<option value="'+r.id+'" '+(x&&Number(x.recipe_id)===r.id?'selected':'')+'>'+esc(r.title)+'</option>';});
 html+='</select></div></div></div>';
});
html+='<button type="button" id="save-meals" class="mp-btn mp-btn-primary">Gem alle fire</button></div>';
content.innerHTML=html;content.querySelector('#back-week').onclick=week;
content.querySelectorAll('.mode').forEach(function(el){el.onchange=function(){el.parentElement.querySelector('.own').hidden=el.value!=='own';};});
content.querySelectorAll('.recipe').forEach(function(el){el.onchange=function(){var r=recipesList.find(function(x){return x.id===Number(el.value);});if(r)el.parentElement.querySelector('.meal-name').value=r.title;};});
content.querySelector('#save-meals').onclick=async function(){
 var data=Array.from(content.querySelectorAll('.edit-person')).map(function(el){var p=Number(el.dataset.person),mode=el.querySelector('.mode').value;return {date:date,person:p,mode:mode==='own'?'own':'same',source_person:mode==='own'?0:Number(mode),title:el.querySelector('.meal-name').value,recipe_id:Number(el.querySelector('.recipe').value)};});
 // Own rows first, then references. Server rejects cycles.
 data.sort(function(a,b){return Number(a.mode==='same')-Number(b.mode==='same');});
 try{for(var i=0;i<data.length;i++)await api('meal','POST',data[i]);await week();msg('Madplan gemt.');}catch(e){fail(e);}
};
}
async function watch(){
content.innerHTML='<p class="mp-help">Henter fælles watchlist…</p>';
var items;try{items=await api('shows');}catch(e){return fail(e);}
var html='';
if(cfg.canEdit){
 html+='<section class="mp-card mp-tv-add-card"><h3>+ Tilføj TV-serie</h3>'+
 '<p class="mp-help">Søg serien frem og vælg streamingtjenesten. Sæson og kendte premieredatoer hentes automatisk.</p>'+
 '<form id="mp-find-series"><div class="mp-field"><label for="search-term">Seriens navn</label>'+
 '<input id="search-term" name="search-term" placeholder="Skriv seriens navn" autocomplete="off" required minlength="2"></div>'+
 '<button type="submit" class="mp-btn mp-btn-primary">Find serie</button></form>'+
 '<div id="show-results" aria-live="polite"></div>'+
 '<form id="show-form" hidden><div id="mp-series-summary" class="mp-series-summary"></div>'+
 '<div class="mp-field"><label for="service">Streamingtjeneste i Danmark</label>'+
 '<input id="service" list="mp-service-list" required placeholder="Fx Netflix, Max, Disney+ eller DRTV" maxlength="100">'+
 '<datalist id="mp-service-list"><option value="Netflix"><option value="Max"><option value="Disney+"><option value="Prime Video"><option value="Apple TV+"><option value="Viaplay"><option value="TV 2 Play"><option value="DRTV"><option value="SkyShowtime"></datalist></div>'+
 '<p class="mp-help" id="mp-provider-note">Streamingudbyder vælges af dig. Kilden bekræfter ikke dansk tilgængelighed.</p>'+
 '<details class="mp-advanced"><summary>Vis eller ret sæsonoplysninger (valgfrit)</summary>'+
 '<div class="mp-form-row"><label class="mp-field">Sæson<select id="season"></select></label>'+
 '<label class="mp-field">Antal afsnit<input id="total" type="number" min="0" max="200" value="0"></label>'+
 '<label class="mp-field">Første kendte dato<input id="first" type="date"></label>'+
 '<label class="mp-field">Dansk klokkeslæt – kun hvis kendt<input id="time" placeholder="Fx 09:00"></label></div>'+
 '<p><label><input id="weekly" type="checkbox"> Beregn forventet afsnit hver 7. dag, når officielle datoer mangler</label></p>'+
 '</details><button type="submit" class="mp-btn mp-btn-primary mp-save-series">Tilføj til watchlist</button></form></section>';
}
html+='<section class="mp-card"><h3>Fælles watchlist</h3>';
if(!items.length)html+='<p>Ingen serier tilføjet endnu.</p>';
items.forEach(function(x){
 html+='<div class="mp-show"><div><strong>'+esc(x.title)+'</strong><small>'+
 esc(x.service||'Streaming ukendt')+' · Sæson '+esc(x.season)+' · '+(Number(x.season_total)||'ukendt antal')+' afsnit</small>'+
 '<small>'+(Number(x.weekly)?'Ugentlige forventede datoer tilladt':'Kun kendte afsnitsdatoer')+'</small></div>';
 if(cfg.canEdit)html+='<div class="mp-show-actions"><button class="mp-btn sync-show" data-id="'+x.id+'" type="button">Opdater</button>'+
 (cfg.admin?'<button class="mp-btn remove-show" data-id="'+x.id+'" type="button">Fjern</button>':'')+'</div>';
 html+='</div>';
});
html+='</section>';
content.innerHTML=html;
content.querySelectorAll('.sync-show').forEach(function(b){b.onclick=async function(){
 try{await api('shows/'+b.dataset.id+'/sync','POST',{});msg('Afsnit er opdateret.');}catch(e){fail(e);}
};});
content.querySelectorAll('.remove-show').forEach(function(b){b.onclick=async function(){
 if(!window.confirm('Fjern serien fra den fælles watchlist?'))return;
 try{await api('shows/'+b.dataset.id,'DELETE');await watch();msg('Serie fjernet.');}catch(e){fail(e);}
};});
if(!cfg.canEdit)return;

var selected=null,details=null,chosenSeason=0;
var searchForm=content.querySelector('#mp-find-series'),
 resultsEl=content.querySelector('#show-results'),
 form=content.querySelector('#show-form'),
 serviceEl=content.querySelector('#service'),
 seasonEl=content.querySelector('#season');
function setSeason(){
 var sn=Number(seasonEl.value),row=(details.seasons||[]).find(function(x){return Number(x.season)===sn;});
 chosenSeason=sn;
 content.querySelector('#total').value=row?Number(row.total):0;
 content.querySelector('#first').value=row?row.first_date||'':'';
 content.querySelector('#weekly').checked=!!(row&&row.weekly);
 var preview='Sæson '+sn+' · ';
 preview+=row&&Number(row.total)>0?row.total+' planlagte afsnit':(row&&row.known?row.known+' kendte afsnit, samlet antal ukendt':'Antal afsnit ukendt');
 preview+=' · '+(row&&row.first_date?('Første oprindelige premieredato '+row.first_date):'Ingen premieredato endnu');
 if(row&&row.weekly)preview+=' · Ugentlig rytme fundet i kendte datoer';
 content.querySelector('#mp-series-summary').innerHTML='<strong>'+esc(details.title)+'</strong><p class="mp-help">'+esc(preview)+'</p>';
}
async function choose(result){
 selected=result;resultsEl.innerHTML='<p class="mp-help">Henter sæsoner, afsnit og kendte premieredatoer…</p>';
 try {
  details=await api('tv-details?id='+encodeURIComponent(result.id));
  if(!details||!details.id)throw new Error('Ingen sæsonoplysninger fundet.');
  seasonEl.innerHTML='';
  var available=(details.seasons||[]);
  if(!available.length)available=[{season:1,total:0,first_date:'',known:0,weekly:false}];
  available.forEach(function(row){
   var option=document.createElement('option');
   option.value=String(row.season);
   option.textContent='Sæson '+row.season;
   seasonEl.appendChild(option);
  });
  seasonEl.value=String(details.suggested_season);
  if(!seasonEl.value)seasonEl.value=String(available[available.length-1].season);
  setSeason();
  // If TVmaze identifies a web channel it can be suggested, NOT presented
  // as a Danish rights/availability confirmation.
  serviceEl.value=details.provider_kind==='web'?details.original_provider:'';
  content.querySelector('#mp-provider-note').textContent=details.original_provider?
   'TVmaze angiver '+details.original_provider+' som oprindelig '+(details.provider_kind==='web'?'streamingtjeneste':'TV-kanal')+'. Bekræft selv streamingtjenesten i Danmark.':
   'TVmaze har ikke en kendt streamingtjeneste. Angiv den danske tjeneste manuelt.';
  form.hidden=false;
  resultsEl.innerHTML='';
  form.scrollIntoView({block:'nearest',behavior:'smooth'});
 }catch(e){fail(e);resultsEl.innerHTML='<p class="mp-help">Seriedetaljer kunne ikke hentes. Søg igen.</p>';}
}
searchForm.onsubmit=async function(ev){
 ev.preventDefault();var query=content.querySelector('#search-term').value.trim();
 if(query.length<2)return msg('Skriv mindst 2 bogstaver.',true);
 selected=null;details=null;form.hidden=true;resultsEl.innerHTML='<p class="mp-help">Søger…</p>';
 try{
  var list=await api('tv-search?q='+encodeURIComponent(query));
  resultsEl.innerHTML=list.length?'':'<p>Ingen serier fundet.</p>';
  list.forEach(function(row){
   var b=document.createElement('button');
   b.type='button';b.className='mp-result';
   b.innerHTML='<strong>'+esc(row.name)+'</strong><small>'+esc(row.premiered||'Udgivelsesår ukendt')+'</small>';
   b.onclick=function(){choose(row);};
   resultsEl.appendChild(b);
  });
 }catch(e){resultsEl.innerHTML='';fail(e);}
};
seasonEl.onchange=setSeason;
form.onsubmit=async function(ev){
 ev.preventDefault();
 if(!selected||!details)return msg('Vælg først en serie.',true);
 var provider=serviceEl.value.trim();
 if(!provider)return msg('Vælg streamingtjenesten, fx Netflix, Viaplay eller DRTV.',true);
 var fields={
  tvmaze_id:Number(selected.id),
  service:provider,
  season:Number(seasonEl.value),
  season_total:Number(content.querySelector('#total').value),
  first_air_date:content.querySelector('#first').value,
  time_label:content.querySelector('#time').value,
  weekly:content.querySelector('#weekly').checked,
 };
 var saveButton=form.querySelector('.mp-save-series');
 saveButton.disabled=true;saveButton.textContent='Tilføjer og henter afsnit…';
 try{
  var saved=await api('shows','POST',fields);
  await watch();
  msg(saved.sync&&saved.sync.warning?'Serien er tilføjet, men afsnitsopdateringen fejlede: '+saved.sync.warning:
  'Serien og de kendte afsnit er føjet til den fælles TV-plan.');
 }catch(e){fail(e);saveButton.disabled=false;saveButton.textContent='Tilføj til watchlist';}
};
}
async function recipes(){
content.innerHTML='<p>Henter opskrifter…</p>';var list;
try{list=await getRecipes();}catch(e){return fail(e);}
var html='<div class="mp-toolbar"><h3>Opskrifter</h3>'+(cfg.admin?'<a class="mp-btn mp-btn-primary" target="_blank" rel="noopener" href="'+url(cfg.recipeCreate)+'">Opret på PC</a>':'')+'</div><p class="mp-help">Opskrifter oprettes og redigeres bedst i WordPress på PC. Her kan de læses på mobilen.</p><div class="mp-recipes">';
list.forEach(function(x){html+='<button type="button" class="mp-recipe-card" data-id="'+x.id+'">'+(url(x.cover)?'<img loading="lazy" alt="" src="'+url(x.cover)+'">':'')+'<strong>'+esc(x.title)+'</strong></button>';});
content.innerHTML=html+'</div>';
content.querySelectorAll('.mp-recipe-card').forEach(function(b){b.onclick=function(){recipeDetail(Number(b.dataset.id));};});
}
async function recipeDetail(id){
var r;content.innerHTML='<p>Henter opskrift…</p>';
try{r=await api('recipe/'+id);}catch(e){return fail(e);}
var html='<div class="mp-toolbar"><h3>'+esc(r.title)+'</h3><button type="button" id="back-recipes" class="mp-btn">Tilbage</button></div><div class="mp-card mp-recipe-detail">';
if(url(r.cover))html+='<img class="mp-cover" src="'+url(r.cover)+'" alt="">';
html+='<p>'+esc(r.servings)+' portioner · '+esc(r.minutes)+' minutter</p><h3>Ingredienser</h3><ul>'+r.ingredients.map(function(x){return '<li>'+esc([x.amount,x.unit,x.name].filter(Boolean).join(' '))+'</li>';}).join('')+'</ul><h3>Fremgangsmåde</h3><div class="mp-recipe-content">'+r.html+'</div>';
if(r.images.length)html+='<h3>Flere billeder</h3><div class="mp-recipe-gallery">'+r.images.map(function(x){return '<a target="_blank" rel="noopener" href="'+url(x.url)+'"><img loading="lazy" src="'+url(x.url)+'" alt="'+esc(x.alt)+'"></a>';}).join('')+'</div>';
content.innerHTML=html+'</div>';content.querySelector('#back-recipes').onclick=recipes;
}
async function shopping(){
content.innerHTML='<p>Henter indkøbslisten…</p>';var list;
try{list=await api('shopping');}catch(e){return fail(e);}
var html='<div class="mp-card"><h3>Fælles indkøbsliste</h3><p class="mp-help">Manuel liste i første version. Automatisk opskriftsberegning kommer senere.</p><form class="mp-shopping-form" id="add-item"><input id="item-title" maxlength="255" required placeholder="Vare"><input id="item-amount" maxlength="80" placeholder="Mængde"><button type="submit" class="mp-btn mp-btn-primary">Tilføj</button></form>';
list.forEach(function(x){html+='<div class="mp-shopping-item '+(Number(x.checked)?'mp-checked':'')+'"><label><input class="item-check" type="checkbox" data-id="'+x.id+'" '+(Number(x.checked)?'checked':'')+'><span class="mp-shopping-name">'+esc(x.title)+'</span><small>'+esc(x.amount)+'</small></label>'+(cfg.admin?'<button class="mp-btn item-delete" type="button" data-id="'+x.id+'">×</button>':'')+'</div>';});
content.innerHTML=html+'</div>';
content.querySelector('#add-item').onsubmit=async function(ev){ev.preventDefault();try{await api('shopping','POST',{title:content.querySelector('#item-title').value,amount:content.querySelector('#item-amount').value});shopping();}catch(e){fail(e);}};
content.querySelectorAll('.item-check').forEach(function(b){b.onchange=async function(){try{await api('shopping/'+b.dataset.id,'POST',{checked:b.checked});shopping();}catch(e){fail(e);}};});
content.querySelectorAll('.item-delete').forEach(function(b){b.onclick=async function(){try{await api('shopping/'+b.dataset.id,'DELETE');shopping();}catch(e){fail(e);}};});
}
})();