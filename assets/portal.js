(function(){
'use strict';
var root=document.getElementById('mp-app'), cfg=window.MP_PORTAL;if(!root||!cfg)return;
var content=root.querySelector('#mp-content'),status=root.querySelector('#mp-status'),home=root.querySelector('#mp-home'),main=root.querySelector('#mp-main'),tabs=root.querySelector('#mp-tabs');
var s={module:'',tab:'week',start:mon(cfg.today),week:null,next:null,recipes:null};
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
 // In-app navigation only: preserve the WordPress authentication cookie.
 home.hidden=false;main.hidden=true;s.module='';s.tab='week';msg('');
 if(window.history&&window.history.replaceState){
  window.history.replaceState(null,'',window.location.pathname+window.location.search);
 }
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
async function load(){var tasks=[api('week?start='+encodeURIComponent(s.start))];if(s.start===mon(cfg.today))tasks.push(api('week?start='+encodeURIComponent(add(s.start,7))));var list=await Promise.all(tasks);s.week=list[0];s.next=list[1]||null;}
async function week(){
content.innerHTML=toolbar()+'<p>Henter planen…</p>';bindToolbar();
try{await load();}catch(e){return fail(e);}
var current=s.start===mon(cfg.today),focus=current?(dt(cfg.today).getUTCDay()+6)%7:0,limit=current?9:7;
var addText=s.module==='tv'?'+ Tilføj TV-program':'+ Tilføj aftensmad';
var html=toolbar()+'<div class="mp-mobile-actions"><button type="button" id="mp-quick-add" class="mp-btn mp-btn-primary mp-add-primary">'+addText+'</button></div>'+
'<p class="mp-help">'+(s.module==='tv'?'TVmaze-datoer er internationale/originale. Gule datoer er forventede, og dansk klokkeslæt er manuelt angivet.':'Hver dag har fire personrækker. Alle tidligere uger gemmes.')+'</p><div class="mp-days">';
for(var i=0;i<limit;i++){
 var date=add(s.start,i),showPortrait=i>=focus&&i<focus+3;
 html+='<section class="mp-day '+(date===cfg.today?'mp-today ':'')+(!showPortrait?'mp-portrait-hide ':'')+(i>=7?'mp-landscape-hide':'')+'"><h4>'+esc(label(date))+(date===cfg.today?'<small>I dag</small>':'')+'</h4>';
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
content.innerHTML='<p>Henter watchlist…</p>';var items;
try{items=await api('shows');}catch(e){return fail(e);}
var html='<div class="mp-card"><h3>Fælles watchlist</h3>';
if(!items.length)html+='<p>Der er endnu ingen serier.</p>';
items.forEach(function(x){html+='<div class="mp-show"><div><strong>'+esc(x.title)+'</strong><small>'+esc(x.service||'Streaming ukendt')+' · Sæson '+esc(x.season)+' · '+(Number(x.season_total)||'?')+' afsnit</small><small>'+(Number(x.weekly)?'Ugentlig beregning':'Kun kendte afsnitsdatoer')+'</small></div>'+(cfg.admin?'<div class="mp-show-actions"><button class="mp-btn sync-show" data-id="'+x.id+'">Opdater</button><button class="mp-btn remove-show" data-id="'+x.id+'">Fjern</button></div>':'')+'</div>';});
html+='</div>';
if(cfg.canEdit)html+='<div class="mp-card"><h3>+ Tilføj TV-program fra mobilen</h3><div class="mp-field"><label>Søg på TVmaze</label><input id="search-term" placeholder="Seriens navn"></div><button type="button" class="mp-btn" id="search-shows">Søg</button><div id="show-results"></div><form id="show-form" hidden><p><strong id="selected-show"></strong></p><input type="hidden" id="show-id"><div class="mp-form-row"><label class="mp-field">Streaming i Danmark (manuel)<input id="service" placeholder="Netflix"></label><label class="mp-field">Sæson<input id="season" type="number" min="1" max="100" value="1"></label><label class="mp-field">Antal afsnit<input id="total" type="number" min="0" max="200" value="0"></label><label class="mp-field">Første dato (valgfri)<input id="first" type="date"></label><label class="mp-field">Dansk klokkeslæt (manuel)<input id="time" placeholder="09:00"></label></div><p><label><input id="weekly" type="checkbox"> Beregn forventet udgivelse hver 7. dag</label></p><p class="mp-help">Internationale TVmaze-datoer er ikke bekræftede danske datoer.</p><button type="submit" class="mp-btn mp-btn-primary">Tilføj</button></form></div>';
content.innerHTML=html;
var addCard=content.querySelector('#show-form');
if(addCard){
 addCard=addCard.closest('.mp-card');
 if(addCard) content.insertBefore(addCard,content.firstChild);
}
content.querySelectorAll('.sync-show').forEach(function(b){b.onclick=async function(){try{await api('shows/'+b.dataset.id+'/sync','POST',{});msg('Afsnit opdateret.');}catch(e){fail(e);}};});
content.querySelectorAll('.remove-show').forEach(function(b){b.onclick=async function(){if(!window.confirm('Fjern serien fra watchlisten?'))return;try{await api('shows/'+b.dataset.id,'DELETE');watch();}catch(e){fail(e);}};});
if(!cfg.canEdit)return;
content.querySelector('#search-shows').onclick=async function(){
 var q=content.querySelector('#search-term').value.trim();if(q.length<2)return msg('Skriv mindst to bogstaver.',true);
 try{var list=await api('tv-search?q='+encodeURIComponent(q)),results=content.querySelector('#show-results');results.innerHTML=list.length?'':'<p>Ingen serier fundet.</p>';
 list.forEach(function(x){var b=document.createElement('button');b.type='button';b.className='mp-result';b.innerHTML='<strong>'+esc(x.name)+'</strong><small>'+esc(x.premiered||'Premieredato ukendt')+'</small>';b.onclick=function(){content.querySelector('#show-id').value=x.id;content.querySelector('#selected-show').textContent=x.name;content.querySelector('#show-form').hidden=false;results.innerHTML='';};results.appendChild(b);});}catch(e){fail(e);}
};
content.querySelector('#show-form').onsubmit=async function(ev){ev.preventDefault();try{
 var p={tvmaze_id:Number(content.querySelector('#show-id').value),title:content.querySelector('#selected-show').textContent,service:content.querySelector('#service').value,season:Number(content.querySelector('#season').value),season_total:Number(content.querySelector('#total').value),first_air_date:content.querySelector('#first').value,time_label:content.querySelector('#time').value,weekly:content.querySelector('#weekly').checked};
 var result=await api('shows','POST',p);await watch();msg(result.sync&&result.sync.warning?'Tilføjet, men TVmaze gav en fejl.':'Serie tilføjet.');}catch(e){fail(e);}};
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