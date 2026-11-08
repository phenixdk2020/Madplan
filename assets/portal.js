(function(){
'use strict';
var root=document.getElementById('mp-app'), cfg=window.MP_PORTAL;if(!root||!cfg)return;
var content=root.querySelector('#mp-content'),status=root.querySelector('#mp-status'),home=root.querySelector('#mp-home'),main=root.querySelector('#mp-main'),tabs=root.querySelector('#mp-tabs');
var s={module:'',tab:'week',start:mon(cfg.today),week:null,next:null,recipes:null,freezerDraft:null,freezerStop:null};
/* Personal preferences are stored in WordPress user_meta, not localStorage. */
var themeOptions=['default','dark','forest','purple','sand','ocean','rose','sunset','mint','burgundy'];
var themeSelect=root.querySelector('#mp-theme-select');
var settingsMenu=root.querySelector('#mp-settings-menu');
var shoppingSettingsLink=root.querySelector('#mp-open-shopping-settings');
if(shoppingSettingsLink)shoppingSettingsLink.onclick=function(){
 if(s.tab!=='shopping'||main.hidden)return;
 var dialog=content.querySelector('#mp-shopping-settings-dialog');
 if(!dialog||typeof dialog.showModal!=='function'){msg('Opsætningen kunne ikke åbnes i denne browser.',true);return;}
 if(settingsMenu)settingsMenu.open=false;
 if(!dialog.open)dialog.showModal();
};
if(settingsMenu){
 document.addEventListener('click',function(e){if(settingsMenu.open&&!settingsMenu.contains(e.target))settingsMenu.open=false;});
 document.addEventListener('keydown',function(e){if(e.key==='Escape'&&settingsMenu.open){settingsMenu.open=false;settingsMenu.querySelector('summary').focus();}});
}
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
function setTab(t){
 if(s.freezerStop){s.freezerStop();s.freezerStop=null;}
 s.tab=t;
 if(shoppingSettingsLink)shoppingSettingsLink.hidden=t!=='shopping';
 var isTv=s.module==='tv';
 root.querySelector('#mp-module-title').textContent=isTv?'TV-ugeplan':
  (t==='freezer'?'Fryser':(t==='shopping'?'Indkøbsliste':(t==='recipes'?'Opskrifter':'Madplan')));
 // The four food destinations live on the front page, not in a second navigation row.
 tabs.hidden=!isTv;
 tabs.innerHTML=isTv?[['week','Ugeplan'],['watch','Serier'],['movies','Film']].map(function(x){
  return '<button type="button" class="mp-tab" data-tab="'+x[0]+'" '+(t===x[0]?'aria-current="page"':'')+'>'+x[1]+'</button>';
 }).join(''):'';
 if(isTv){tabs.querySelectorAll('button').forEach(function(b){b.onclick=function(){setTab(b.dataset.tab);};});}
 render();
}
function module(name,tab){if(name==='shopping'){name='food';tab='shopping';}if(name==='freezer'){name='food';tab='freezer';}s.module=name;home.hidden=true;main.hidden=false;setTab(tab||'week');}
root.querySelectorAll('[data-module]').forEach(function(b){b.onclick=function(){module(b.dataset.module);};});
function openHash(){
 var hash=(window.location.hash||'').replace(/^#/,'').toLowerCase();
 if(hash==='tv'||hash==='tv-guide')module('tv','week');
 else if(hash==='movies'||hash==='film')module('tv','movies');
 else if(hash==='tv-watch'||hash==='watchlist')module('tv','watch');
 else if(hash==='food'||hash==='madplan')module('food','week');
 else if(hash==='shopping'||hash==='indkoeb')module('food','shopping');
 else if(hash==='freezer'||hash==='fryser')module('food','freezer');
 else if(hash==='recipes'||hash==='opskrifter')module('food','recipes');
}
window.addEventListener('hashchange',openHash);
openHash();
function backToMenu(){
 if(s.freezerStop){s.freezerStop();s.freezerStop=null;}
 home.hidden=false;main.hidden=true;s.module='';s.tab='week';msg('');
 if(shoppingSettingsLink)shoppingSettingsLink.hidden=true;
 if(window.history&&window.history.replaceState){window.history.replaceState(null,'',window.location.pathname+window.location.search);}
 window.scrollTo({top:0,behavior:'smooth'});
}
root.querySelector('#mp-back').onclick=backToMenu;
root.querySelector('#mp-back-bottom').onclick=backToMenu;
function render(){msg('');if(s.tab==='week')return week();if(s.tab==='watch')return watch();if(s.tab==='movies')return movies();if(s.tab==='recipes')return recipes();if(s.tab==='shopping')return shopping();if(s.tab==='freezer'){if(window.MP_FREEZER)return window.MP_FREEZER({content:content,api:api,esc:esc,msg:msg,fail:fail,cfg:cfg,s:s,setTab:setTab,mon:mon});content.innerHTML='<p class="mp-help">Fryser kunne ikke indlæses.</p>';}}
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
var html=toolbar()+'<div class="mp-mobile-actions"><button type="button" id="mp-quick-add" class="mp-btn mp-btn-primary mp-add-primary">'+addText+'</button>'+(s.module==='tv'?'<button type="button" id="mp-movie-add" class="mp-btn mp-add-primary">+ Tilføj film</button>':'')+'</div>'+ (s.module==='tv'?'':'<button type="button" id="mp-open-recipes" class="mp-btn mp-week-recipes">📖 Se opskrifter</button>')+
'<p class="mp-help">'+(s.module==='tv'?'Serier følger afsnit/sæsoner; film vises kun én gang på premieredatoen. Danske streamingdatoer og biografdatoer mærkes særskilt.':'Hver dag har fire personrækker. Alle tidligere uger gemmes.')+'</p><div class="mp-days">';
for(var i=0;i<limit;i++){
 var date=add(s.start,i);
 html+='<section class="mp-day '+(date===cfg.today?'mp-today':'')+'" data-day="'+date+'"><h4>'+esc(label(date))+(date===cfg.today?'<small>I dag</small>':'')+'</h4>';
 if(s.module==='tv'){
  var dayEpisodes=episodes().filter(function(e){return e.date===date;});
  if(!dayEpisodes.length)html+='<p class="mp-help">Ingen afsnit eller filmpremierer</p>';
  dayEpisodes.forEach(function(e){
    if(e.kind==='movie'){
      var movieLabel={movie_digital:'Digital DK · tjeneste ikke bekræftet',
        movie_cinema:'Biografpremiere DK',movie_manual:'Dato angivet af familien'}[e.status]||'Filmpremiere';
      html+='<div class="mp-episode mp-film-premiere">'+
        (e.poster?'<img class="mp-calendar-poster" src="'+url(e.poster)+'" alt="" loading="lazy">':'')+
        '<div class="mp-episode-body"><strong>🎬 '+esc(e.title)+'</strong>'+
        '<small>'+esc(e.release_type||'Film')+' · '+esc(e.service||'Ukendt')+'</small>'+
        '<span class="mp-tag '+(e.status==='movie_manual'?'mp-estimate':'')+'">'+esc(movieLabel)+'</span>'+
        '<small>'+esc(e.note||'')+'</small>'+
        (e.source_url?'<small><a href="'+url(e.source_url)+'" target="_blank" rel="noopener noreferrer">Kilde: TMDB</a></small>':'')+
        '</div></div>';
      return;
    }
    var label={
     dk_confirmed:'Bekræftet DK',dk_estimated:'Forventet DK',
     dk_manual:'Angivet af familien',estimated:'Forventet originaldato',original:'TVmaze-dato'
    }[e.status]||'Dato ikke verificeret';
    var statusClass=(e.status==='estimated'||e.status==='dk_estimated')?'mp-estimate':
      e.status==='dk_confirmed'?'mp-dk-confirmed':'';
    html+='<div class="mp-episode"><span class="mp-time">'+esc(e.time_label||'—')+
    '</span><div class="mp-episode-body"><strong>'+esc(e.show)+'</strong><small>S'+
    esc(e.season)+' · A'+esc(e.episode)+(e.episode_title?' · '+esc(e.episode_title):'')+
    '</small><small>'+esc(e.service||'Streaming ukendt')+'</small><span class="mp-tag '+
    statusClass+'">'+esc(label)+'</span><small>'+esc(e.note)+'</small>'+
    (e.source_url?'<small><a href="'+url(e.source_url)+'" target="_blank" rel="noopener noreferrer">Se dansk kilde</a></small>':'')+
    '</div></div>';
   });
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
var movieAdd=content.querySelector('#mp-movie-add');if(movieAdd)movieAdd.onclick=function(){setTab('movies');};
var openRecipes=content.querySelector('#mp-open-recipes');if(openRecipes)openRecipes.onclick=function(){setTab('recipes');};
content.querySelectorAll('.edit-day').forEach(function(b){b.onclick=function(){editDay(b.dataset.date);};});
 if(s.freezerDraft){var draft=s.freezerDraft;s.freezerDraft=null;editDay(cfg.today,draft.title);}
}
async function getRecipes(){if(s.recipes===null)s.recipes=await api('recipes');return s.recipes;}
async function editDay(date,freezerTitle){
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
 if(freezerTitle){var row=Array.from(content.querySelectorAll('.edit-person')).find(function(el){return !el.querySelector('.meal-name').value.trim();})||content.querySelector('.edit-person');if(row){row.querySelector('.mode').value='own';row.querySelector('.own').hidden=false;row.querySelector('.meal-name').value=freezerTitle;msg('Fryservaren er foreslået. Tilpas måltidet og gem madplanen.');}}
content.querySelectorAll('.mode').forEach(function(el){el.onchange=function(){el.parentElement.querySelector('.own').hidden=el.value!=='own';};});
content.querySelectorAll('.recipe').forEach(function(el){el.onchange=function(){var r=recipesList.find(function(x){return x.id===Number(el.value);});if(r)el.parentElement.querySelector('.meal-name').value=r.title;};});
content.querySelector('#save-meals').onclick=async function(){
 var data=Array.from(content.querySelectorAll('.edit-person')).map(function(el){var p=Number(el.dataset.person),mode=el.querySelector('.mode').value;return {date:date,person:p,mode:mode==='own'?'own':'same',source_person:mode==='own'?0:Number(mode),title:el.querySelector('.meal-name').value,recipe_id:Number(el.querySelector('.recipe').value)};});
 // Own rows first, then references. Server rejects cycles.
 data.sort(function(a,b){return Number(a.mode==='same')-Number(b.mode==='same');});
 try{for(var i=0;i<data.length;i++)await api('meal','POST',data[i]);await week();msg('Madplan gemt.');}catch(e){fail(e);}
};
}

function movieBadge(movie){
 var labels={movie_digital:'Digital DK (tjeneste ikke bekræftet)',
 movie_cinema:'Biografpremiere DK',movie_manual:'Dato angivet af familien',
 movie_pending:'Afventer premieredato'};
 return labels[movie.status]||'Ubekræftet';
}
async function movies(){
 content.innerHTML='<p class="mp-help">Henter film-watchlist…</p>';
 var rows,prefs;
 try {
  var response=await Promise.all([api('movies'),api('streaming-services')]);
  rows=response[0];prefs=response[1];
 }catch(e){return fail(e);}
 var html='<section class="mp-card mp-film-header"><h3>🎬 Film og kommende premierer</h3>'+
 '<p class="mp-help">Film kommer kun én gang i ugeplanen på deres danske biografdato eller digitale udgivelsesdato. Ingen gentagne uger. Ukendte datoer overvåges fortsat.</p>';
 if(cfg.canEdit){
  html+='<div class="mp-film-search">'+
  '<form id="mp-film-search-form"><label class="mp-field">Søg efter filmens navn (TMDB)'+
  '<input id="mp-film-query" required minlength="2" placeholder="Filmnavn"></label>'+
  '<button class="mp-btn mp-btn-primary" type="submit">Find film</button></form>'+
  '<p class="mp-help">'+(prefs.configured?'Danske filmdata fra TMDB.':'Filmsøgning kræver gratis TMDB API-nøgle. Du kan stadig tilføje filmen manuelt nedenfor.')+'</p>'+
  '<div id="mp-film-results" aria-live="polite"></div>'+
  '<form id="mp-add-movie" class="mp-film-edit">'+
  '<div id="mp-film-chosen" class="mp-help">Vælg en film fra søgeresultaterne eller skriv selv en titel.</div>'+
  '<label class="mp-field">Filmens titel <input id="mp-film-title" maxlength="255" required placeholder="Filmtitel"></label>'+
  '<div class="mp-form-row"><label class="mp-field">Følg premieredato for<select id="mp-film-mode">'+
  '<option value="streaming">Streaming / digital DK</option><option value="cinema">Biograf DK</option></select></label>'+
  '<label class="mp-field">Streamingtjeneste (valgfri)<select id="mp-film-service">'+
  '<option value="">Automatisk / ukendt</option>'+
  prefs.options.map(function(name){return '<option value="'+esc(name)+'">'+esc(name)+'</option>';}).join('')+
  '</select></label></div>'+
  '<label class="mp-field">Dansk premieredato (valgfri, kun når den er kendt)'+
  '<input id="mp-film-manual-date" type="date"></label>'+
  '<p class="mp-help">Hvis datoen ikke er kendt, gemmes filmen og overvåges. En TMDB-digitaldato bekræfter ikke datoen på en bestemt tjeneste.</p>'+
  '<button class="mp-btn mp-btn-primary" id="mp-film-save" type="submit">Tilføj film til watchlist</button>'+
  '</form></div>';
 }
 html+='</section><section class="mp-card"><h3>Film på watchlisten</h3>';
 if(!rows.length)html+='<p>Ingen film endnu. Tilføj den første ovenfor.</p>';
 var upcoming=rows.filter(function(m){return !m.released;});
 var past=rows.filter(function(m){return m.released;});
 function movieRow(m){
  return '<article class="mp-film-row" data-id="'+m.id+'">'+
  (m.poster?'<img class="mp-film-poster" alt="" loading="lazy" src="'+url(m.poster)+'">':'<span class="mp-tv-no-poster" aria-hidden="true">🎬</span>')+
  '<div class="mp-film-body"><strong>'+esc(m.title)+'</strong>'+
  '<small>'+esc(m.release_type)+' · '+esc(m.service||'Ukendt')+'</small>'+
  '<span class="mp-watch-status">'+esc(movieBadge(m))+'</span>'+
  '<small>'+esc(m.date||'Dansk premieredato ikke offentliggjort')+'</small>'+
  '<small>'+esc(m.date_note||'')+'</small>'+
  (m.original_date?'<small>International premieredato: '+esc(m.original_date)+'</small>':'')+
  (cfg.canEdit?'<details class="mp-film-correct"><summary>Ret premiere eller tjeneste</summary>'+
    '<form class="mp-film-update" data-id="'+m.id+'">'+
    '<label>Type<select class="mp-film-update-mode">'+
     '<option value="streaming" '+(m.mode==='streaming'?'selected':'')+'>Streaming/digital</option>'+
     '<option value="cinema" '+(m.mode==='cinema'?'selected':'')+'>Biograf</option></select></label>'+
    '<label>Tjeneste<select class="mp-film-update-service"><option value="Ukendt">Ukendt</option>'+
      prefs.options.map(function(name){return '<option value="'+esc(name)+'" '+(name===m.service?'selected':'')+'>'+esc(name)+'</option>';}).join('')+
      '</select></label>'+
    '<label>Manuel premieredato<input class="mp-film-update-date" type="date" value="'+esc(m.manual_date||'')+'"></label>'+
    '<button type="submit" class="mp-btn mp-btn-primary">Gem ændring</button>'+
    '<small>Tøm datofeltet for at genoptage TMDB-datoen.</small></form></details>'+
    '<div class="mp-film-actions">'+
      (m.tmdb_id?'<button type="button" class="mp-btn mp-film-refresh" data-id="'+m.id+'">Tjek premieredato</button>':'')+
      (cfg.admin?'<button type="button" class="mp-btn mp-film-delete" data-id="'+m.id+'">Fjern</button>':'')+
    '</div>':'')+
  '</div></article>';
 }
 upcoming.forEach(function(m){html+=movieRow(m);});
 if(past.length){
  html+='<details class="mp-film-history"><summary>Tidligere premierer ('+past.length+')</summary>';
  past.forEach(function(m){html+=movieRow(m);});
  html+='</details>';
 }
 html+='</section><p class="mp-help">Filmdata: TMDB. Dansk streamingtilgængelighed er katalogdata – ikke en garanti for at en bestemt film eller premiere ligger i jeres abonnement. <a href="https://www.themoviedb.org" target="_blank" rel="noopener">TMDB</a>. This product uses the TMDB API but is not endorsed or certified by TMDB.</p>';
 content.innerHTML=html;
 content.querySelectorAll('.mp-film-refresh').forEach(function(b){
  b.onclick=async function(){
   b.disabled=true;
   try{await api('movies/'+b.dataset.id+'/refresh','POST',{});await movies();msg('Filmens premieredato er kontrolleret.');}
   catch(e){fail(e);b.disabled=false;}
  };
 });
 content.querySelectorAll('.mp-film-delete').forEach(function(b){
  b.onclick=async function(){
   if(!window.confirm('Fjern filmen fra familiens watchlist?'))return;
   try{await api('movies/'+b.dataset.id,'DELETE');await movies();msg('Filmen er fjernet.');}catch(e){fail(e);}
  };
 });
 content.querySelectorAll('.mp-film-update').forEach(function(form){
  form.onsubmit=async function(ev){
   ev.preventDefault();
   var b=form.querySelector('button[type=submit]');b.disabled=true;
   try{
    await api('movies/'+form.dataset.id,'POST',{
     mode:form.querySelector('.mp-film-update-mode').value,
     service:form.querySelector('.mp-film-update-service').value,
     manual_date:form.querySelector('.mp-film-update-date').value
    });
    await movies();msg('Premiere og tjeneste er gemt for hele familien.');
   }catch(e){fail(e);b.disabled=false;}
  };
 });
 if(!cfg.canEdit)return;
 var chosen=null;
 var results=content.querySelector('#mp-film-results'),title=content.querySelector('#mp-film-title');
 content.querySelector('#mp-film-search-form').onsubmit=async function(ev){
  ev.preventDefault();results.textContent='Søger efter film…';
  try{
   var found=await api('movies/search?q='+encodeURIComponent(content.querySelector('#mp-film-query').value.trim()));
   results.innerHTML='';
   if(!found.length)results.textContent='Ingen film fundet. Du kan oprette den manuelt.';
   found.forEach(function(m){
    var b=document.createElement('button');b.type='button';b.className='mp-result mp-tv-result';
    b.innerHTML=(m.poster?'<img class="mp-tv-poster" alt="" src="'+url(m.poster)+'" loading="lazy">':
      '<span class="mp-tv-no-poster">🎬</span>')+
      '<span class="mp-tv-result-info"><strong>'+esc(m.title)+'</strong>'+
      '<small>International dato: '+esc(m.original_date||'Ikke kendt')+'</small>'+
      '<span class="mp-result-choose">Vælg film</span></span>';
    b.onclick=function(){
      chosen=m;title.value=m.title;results.innerHTML='';
      content.querySelector('#mp-film-chosen').textContent='Valgt: '+m.title+' – dansk premieredato hentes ved gem.';
    };
    results.appendChild(b);
   });
  }catch(e){results.textContent='Filmsøgning ikke mulig. Brug manuel filmoprettelse nedenfor.';fail(e);}
 };
 title.oninput=function(){if(chosen&&title.value!==chosen.title){chosen=null;content.querySelector('#mp-film-chosen').textContent='Manuel film: angiv premieredato hvis den er kendt.';}};
 content.querySelector('#mp-add-movie').onsubmit=async function(ev){
  ev.preventDefault();
  var b=content.querySelector('#mp-film-save');b.disabled=true;
  try{
   var added=await api('movies','POST',{
    tmdb_id:chosen?chosen.tmdb_id:0,title:title.value.trim(),
    mode:content.querySelector('#mp-film-mode').value,
    service:content.querySelector('#mp-film-service').value,
    manual_date:content.querySelector('#mp-film-manual-date').value
   });
   await movies();msg('Filmen "'+added.title+'" er på watchlisten. Premieren vises kun én gang.');
  }catch(e){fail(e);b.disabled=false;}
 };
}
async function watch(){
content.innerHTML='<p class="mp-help">Henter fælles watchlist…</p>';
var items,prefs;try{var got=await Promise.all([api('shows'),api('streaming-services')]);items=got[0];prefs=got[1];}catch(e){return fail(e);}
var html='';
if(cfg.canEdit){
 html+='<section class="mp-card mp-my-services"><details id="mp-services-details">'+
 '<summary>Familiens streamingtjenester ('+prefs.selected.length+' valgt)</summary>'+
 '<p class="mp-help">Sæt flueben ved de tjenester, familien har adgang til. Listen deles af alle brugere på både mobil og PC.</p>'+
 '<form id="mp-save-services"><div class="mp-service-choices">';
 prefs.options.forEach(function(name){
  html+='<label class="mp-service-choice"><input type="checkbox" value="'+esc(name)+'" '+(prefs.selected.indexOf(name)!==-1?'checked':'')+'><span>'+esc(name)+'</span></label>';
 });
 html+='</div><button class="mp-btn mp-btn-primary" type="submit">Gem familiens tjenester</button></form>'+
 '<p class="mp-help">'+(prefs.configured?'Dansk streamingopslag er aktiveret.':
 'Automatisk søgning kræver, at administrator tilføjer en gratis TMDB API-nøgle under Hangar18 → Oversigt.')+'</p>'+
 '</details></section>';
 html+='<section class="mp-card mp-tv-add-card"><h3>+ Tilføj TV-serie</h3>'+
 '<p class="mp-help">Søg efter serien og tryk på billedet. Lad streamingtjenesten stå på Automatisk, så undersøger vi familiens valgte tjenester i Danmark.</p>'+
 '<form id="mp-find-series"><div class="mp-field"><label for="search-term">Seriens navn</label>'+
 '<input id="search-term" name="search-term" placeholder="Skriv seriens navn" autocomplete="off" required minlength="2"></div>'+
 '<button type="submit" class="mp-btn mp-btn-primary">Find serie</button></form>'+
 '<div id="show-results" aria-live="polite"></div>'+
 '<form id="show-form" hidden><div id="mp-series-summary" class="mp-series-summary"></div>'+
 '<div class="mp-field"><label for="service">Streamingtjeneste i Danmark</label>'+
 '<select id="service">'+
 '<option value="" selected>Automatisk – søg blandt familiens tjenester</option>'+
 '<optgroup label="Film og serier">'+
 '<option value="Netflix">Netflix</option>'+
 '<option value="HBO Max">HBO Max</option>'+
 '<option value="Disney+">Disney+</option>'+
 '<option value="Prime Video">Prime Video</option>'+
 '<option value="Apple TV+">Apple TV+</option>'+
 '<option value="SkyShowtime">SkyShowtime</option>'+
 '<option value="Viaplay">Viaplay</option>'+
 '<option value="TV 2 Play">TV 2 Play</option>'+
 '<option value="BritBox">BritBox</option>'+
 '<option value="Nordisk Film+">Nordisk Film+</option>'+
 '<option value="MUBI">MUBI</option>'+
 '<option value="Crunchyroll">Crunchyroll</option>'+
 '<option value="hayu">hayu</option>'+
 '</optgroup><optgroup label="Gratis tjenester">'+
 '<option value="DRTV">DRTV</option>'+
 '<option value="Pluto TV">Pluto TV</option>'+
 '<option value="Filmstriben">Filmstriben</option>'+
 '</optgroup><optgroup label="TV- og streamingpakker">'+
 '<option value="Allente Stream">Allente Stream</option>'+
 '<option value="YouSee Play">YouSee Play</option>'+
 '<option value="Norlys Play">Norlys Play</option>'+
 '</optgroup><option value="Ukendt">Ved ikke / ikke afklaret</option>'+
 '</select></div>'+
 '<p class="mp-help" id="mp-auto-status" aria-live="polite"></p>'+
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
var w=x.monitor||{},state=w.state||'monitoring';
 var stateText={
  new_season:'Ny sæson registreret',
  upcoming:'Kommende premiere/afsnit',
  awaiting_next_season:'Afventer næste sæson',
  monitoring:'Overvåges for nye sæsoner'
 }[state]||'Overvåges for nye sæsoner';
 var origin=w.original_provider?
  'Original premiere: '+w.original_provider+(w.provider_kind==='web'?' (streaming)':' (TV)'):
  'Oprindelig premiereudbyder ukendt';
 html+='<div class="mp-show"><div><strong>'+esc(x.title)+'</strong><small>'+
 esc(x.service||'Streaming ikke afklaret')+' · Sæson '+esc(x.season)+' · '+(Number(x.season_total)||'ukendt antal')+' afsnit</small>'+
 '<span class="mp-watch-status '+(state==='new_season'?'mp-watch-new':'')+'">'+esc(stateText)+'</span>'+
 '<small>'+esc(origin)+'</small>'+
 (w.first_air_date?'<small>Original sæsonpremiere: '+esc(w.first_air_date)+'</small>':'')+
 (w.next_air_date?'<small>Næste registrerede originaldato: '+esc(w.next_air_date)+'</small>':'')+
 (x.dk_schedule?
 '<span class="mp-watch-status">'+esc(x.dk_schedule.source_label)+'</span>'+
 '<small>Dansk første dato: '+esc(x.dk_schedule.first_date)+(x.dk_schedule.weekly?' · ugentlig':'')+'</small>'+
 (x.dk_schedule.source_url?'<small><a target="_blank" rel="noopener noreferrer" href="'+url(x.dk_schedule.source_url)+'">Se dansk premiere-kilde</a></small>':''):
 '<small class="mp-help">Dansk premieredato endnu ikke bekræftet. TVmaze viser original udsendelse.</small>')+
 '<small class="mp-help">Serien bliver fortsat overvåget for nye sæsoner.</small></div>';
 if(cfg.canEdit)html+='<div class="mp-show-actions"><button class="mp-btn sync-show" data-id="'+x.id+'" type="button">Tjek nu</button>'+
 ((x.service==='Ukendt'||!x.service)?'<button type="button" class="mp-btn check-streaming" data-id="'+x.id+'">Tjek familiens tjenester</button>':'')+
 '<details class="mp-dk-edit"><summary>Ret dansk premieredag</summary>'+
 '<form class="mp-dk-form" data-id="'+x.id+'"><label>Første danske premieredato'+
 '<input type="date" class="mp-dk-first" value="'+esc(x.dk_schedule?x.dk_schedule.first_date:'')+'"></label>'+
 '<label><input type="checkbox" class="mp-dk-weekly" '+(x.dk_schedule&&x.dk_schedule.weekly?'checked':'')+
 '> Nyt afsnit hver uge</label>'+
 '<button type="submit" class="mp-btn mp-btn-primary">Gem dansk plan</button>'+
 '<small>En manuel dato overstyrer TVmaze. Lad dato stå tom for at bruge den oprindelige eller dokumenterede plan igen.</small>'+
 '</form></details>'+
 (cfg.admin?'<button class="mp-btn remove-show" data-id="'+x.id+'" type="button">Fjern</button>':'')+'</div>';
 html+='</div>';
});
html+='</section>';
html+='<details class="mp-source-credits"><summary>Om streamingdata og kilder</summary>'+
 '<p><a href="https://www.themoviedb.org" target="_blank" rel="noopener"><img class="mp-tmdb-logo" src="https://www.themoviedb.org/assets/2/v4/logos/v2/blue_square_2-d537fb228cf3ded904ef09b136fe3fec72548ebc1fea3fbbd1ad9e36364db38b.svg" alt="TMDB" loading="lazy"></a> <strong>Streamingtjenester:</strong> JustWatch via TMDB (Danmark). Katalogdata garanterer ikke indholdet af dit abonnement eller bestemte sæsoner.</p>'+
 '<p>This product uses the TMDB API but is not endorsed or certified by TMDB.</p>'+
 '<p>Serie-/afsnitsdata: <a href="https://www.tvmaze.com" target="_blank" rel="noopener">TVmaze</a>. Bekræft altid hos streamingtjenesten.</p></details>';
content.innerHTML=html;
var servicesForm=content.querySelector('#mp-save-services');
if(servicesForm)servicesForm.onsubmit=async function(ev){
 ev.preventDefault();
 var selected=Array.from(servicesForm.querySelectorAll('input:checked')).map(function(x){return x.value;});
 var button=servicesForm.querySelector('button[type=submit]');button.disabled=true;
 try{
  var result=await api('streaming-services','POST',{services:selected});
  prefs.selected=result.selected;
  await watch();msg('Familiens '+result.selected.length+' streamingtjenester er gemt for alle brugere.');
 }catch(e){fail(e);button.disabled=false;}
};
content.querySelectorAll('.check-streaming').forEach(function(b){b.onclick=async function(){
 b.disabled=true;
 try{
  var result=await api('shows/'+b.dataset.id+'/streaming-check','POST',{});
  var description=result.auto_assigned?'Fundet hos '+result.auto_assigned+' i Danmark. Tjenesten blev tilføjet.':
    result.status==='not_configured'?'Den gratis TMDB API-nøgle mangler. Administrator skal konfigurere den.':
    result.status==='no_services'?'Sæt først flueben ved dine tjenester ovenfor.':
    result.status==='found'?'Flere af familiens tjenester er registreret: '+result.matches.join(', ')+'. Vælg manuelt.':
    'Ingen sikker match blandt familiens tjenester. Serien forbliver på watchlisten.';
  await watch();msg(description);
 }catch(e){fail(e);b.disabled=false;}
};});
content.querySelectorAll('.sync-show').forEach(function(b){b.onclick=async function(){
 try{
 var result=await api('shows/'+b.dataset.id+'/sync','POST',{});
 await watch();
 msg(result.new_season?'Ny sæson '+result.season+' blev fundet og følges nu automatisk.':
 'Overvågningen er opdateret – serien bliver på watchlisten.');
}catch(e){fail(e);}
};});
content.querySelectorAll('.mp-dk-form').forEach(function(form){
 form.onsubmit=async function(ev){
  ev.preventDefault();
  var date=form.querySelector('.mp-dk-first').value;
  var weekly=form.querySelector('.mp-dk-weekly').checked;
  var btn=form.querySelector('button[type="submit"]');
  btn.disabled=true;
  try{
   await api('shows/'+encodeURIComponent(form.dataset.id)+'/dk-schedule','POST',{
    first_date:date,weekly:weekly
   });
   await watch();msg(date?'Dansk streamingplan gemt for denne sæson.':'Manuel overstyring nulstillet.');
  }catch(e){fail(e);btn.disabled=false;}
 };
});
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
 content.querySelector('#mp-series-summary').innerHTML='<div class="mp-selected-series">'+
  (selected&&selected.image?'<img src="'+url(selected.image)+'" alt="" loading="lazy" decoding="async">':'')+
  '<div><strong>'+esc(details.title)+'</strong><p class="mp-help">'+esc(preview)+'</p></div></div>';
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
  // TVmaze original channel alone cannot confirm Danish rights.
  serviceEl.value='';
  var autoStatus=content.querySelector('#mp-auto-status');
  if(!prefs.selected.length){autoStatus.textContent='Vælg familiens streamingtjenester i feltet ovenfor først.';}
  else if(!prefs.configured){autoStatus.textContent='Administrator skal tilføje en gratis TMDB API-nøgle for at aktivere opslag.';}
  else{
   autoStatus.textContent='Undersøger familiens tjenester i Danmark via TMDB…';
   api('streaming-lookup?tvmaze_id='+encodeURIComponent(result.id)).then(function(found){
    if(!selected||selected.id!==result.id)return;
    if(found.status==='found'){
     autoStatus.textContent=found.matches.length===1?
      'Fundet i det danske streamingkatalog: '+found.matches[0]+'. Udfyldes automatisk ved gem.':
      'Fundet hos flere af familiens tjenester: '+found.matches.join(', ')+'. Vælg gerne én i dropdownen.';
    }else if(found.status==='unmatched'){
     autoStatus.textContent='Serien kunne ikke matches sikkert, så ingen tjeneste vælges automatisk.';
    }else if(found.status==='not_listed'||found.status==='no_dk_data'){
     autoStatus.textContent='Ingen registreret match i Danmark. Det beviser ikke, at serien er utilgængelig.';
    }else{autoStatus.textContent='Opslaget kunne ikke gennemføres. Serien kan stadig gemmes.';}
   }).catch(function(){if(selected&&selected.id===result.id)autoStatus.textContent='Streamingopslag kunne ikke gennemføres.';});
  }
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
  if(list.length)resultsEl.innerHTML='<p class="mp-help">Tryk på billedet eller kortet for at vælge serien. Sæsonantal hentes separat fra TVmaze.</p>';
  var cards=[];
  list.forEach(function(row){
   var b=document.createElement('button');
   b.type='button';b.className='mp-result mp-tv-result';
   var image=row.image?
     '<img class="mp-tv-poster" src="'+url(row.image)+'" alt="" loading="lazy" decoding="async">':
     '<span class="mp-tv-no-poster" aria-hidden="true">📺</span>';
   var year=String(row.premiered||'').slice(0,4)||'Ukendt år';
   b.innerHTML=image+'<span class="mp-tv-result-info"><strong>'+esc(row.name)+'</strong>'+
      '<small>'+esc(year)+(row.original_channel?' · '+esc(row.original_channel):'')+'</small>'+
      '<span class="mp-season-count">Henter sæsoner…</span>'+
      '<span class="mp-result-choose">Tryk for at vælge serien</span></span>';
   b.onclick=function(){choose(row);};
   resultsEl.appendChild(b);
   cards.push({id:Number(row.id), badge:b.querySelector('.mp-season-count')});
  });
  // Posters and buttons are immediately usable. Season counts load progressively
  // with max 3 parallel requests, avoiding excessive bursts against TVmaze.
  var next=0;
  async function fillSeasons(){
    while(next<cards.length){
      var entry=cards[next++];
      if(!entry.badge.isConnected)continue;
      try {
        var d=await api('tv-seasons?id='+encodeURIComponent(entry.id));
        if(!entry.badge.isConnected)continue;
        var number=Number(d.season_count)||0,latest=Number(d.latest_season)||0;
        entry.badge.textContent=number?
          number+' '+(number===1?'sæson':'sæsoner')+(latest>number?' · seneste S'+latest:''):
          'Sæsoner endnu ikke registreret';
      }catch(e){
        if(entry.badge.isConnected)entry.badge.textContent='Sæsonantal ikke tilgængeligt';
      }
    }
  }
  for(var worker=0;worker<Math.min(3,cards.length);worker++)fillSeasons();
 }catch(e){resultsEl.innerHTML='';fail(e);}
};
seasonEl.onchange=setSeason;
form.onsubmit=async function(ev){
 ev.preventDefault();
 if(!selected||!details)return msg('Vælg først en serie.',true);
 var provider=serviceEl.value.trim();
 // Blank triggers auto-detection among shared household subscriptions.
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
  var message=saved.sync&&saved.sync.warning?'Serien er tilføjet, men afsnitsopdateringen fejlede: '+saved.sync.warning:
   (saved.streaming_lookup&&saved.streaming_lookup.matches&&saved.streaming_lookup.matches.length===1?
    'Serien er tilføjet. Fundet på '+saved.service+' i det danske streamingkatalog (TMDB/JustWatch).':
    'Serien er tilføjet og overvåges. Streamingtjeneste: '+(saved.service==='Ukendt'?'ikke bekræftet.':saved.service+'.'));
  msg(message);
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
 var oldSettingsDialog=content.querySelector('#mp-shopping-settings-dialog');
 var reopenSettings=!!(oldSettingsDialog&&oldSettingsDialog.open);
 var expandedSettings=oldSettingsDialog?Array.from(oldSettingsDialog.querySelectorAll('details')).map(function(d,i){return d.open?i:-1;}).filter(function(i){return i>=0;}):[];
 content.innerHTML='<p class="mp-help">Henter indkøbslisten…</p>';
 var list,settings,history,daily,dailyOCR;
 try{
  var data=await Promise.all([api('shopping'),api('shopping/stores'),api('shopping/history'),api('shopping/daily/status'),api('shopping/daily-ocr/status')]);
  list=data[0];settings=data[1];history=data[2]||[];daily=data[3]||{};dailyOCR=data[4]||{};
 }catch(e){return fail(e);}
 var options=settings.options||['Alle'];
 function storeSelect(selected){
  return options.map(function(store){
   return '<option value="'+esc(store)+'" '+(store===selected?'selected':'')+'>'+esc(store)+'</option>';
  }).join('');
 }
 var offerOptions=settings.offer_options||[];
 var offerSelected=settings.offer_selected||[];
 var purchased=list.filter(function(item){return Number(item.checked)!==0;});
 var pending=list.filter(function(item){return !Number(item.checked);});
 function offerToggleMarkup(name){return '<label class="mp-offer-store-checkbox"><input type="checkbox" class="mp-filter-store" value="'+esc(name)+'" '+(offerSelected.indexOf(name)>=0?'checked':'')+'>'+esc(name)+'</label>';}

 var html='<section class="mp-card mp-shopping-main"><h3>Fælles indkøbsliste</h3>'+
 '<p class="mp-help">Varerne placeres automatisk i familiens foretrukne butik. Vælg “Tilbud” for varer, du vil finde et godt tilbud på.</p>'+
 '<form class="mp-shopping-form" id="add-item">'+
 '<div class="mp-history-wrap"><input id="item-title" maxlength="255" required autocomplete="off" aria-label="Vare" '+
 'role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="mp-history-matches" '+
 'placeholder="Vare, fx hakket oksekød">'+
 '<div id="mp-history-matches" class="mp-history-matches" role="listbox" hidden></div></div>'+
 '<input id="item-amount" maxlength="80" aria-label="Mængde" placeholder="Mængde">'+
 '<button type="submit" class="mp-btn mp-btn-primary">Tilføj</button></form>'+
 '<button type="button" id="mp-open-barcode" class="mp-btn mp-barcode-open">📷 Scan stregkode</button>'+
 '<div class="mp-shopping-bulk-actions"><button type="button" id="mp-clear-purchased" class="mp-btn" '+
 (!purchased.length?'disabled':'')+'>Ryd købte varer ('+purchased.length+')</button>'+
 '<button type="button" id="mp-check-offers-all" class="mp-btn mp-shopping-bulk" '+(!pending.length?'disabled':'')+
 '>Søg gemte tilbud på op til 10 varer</button></div><p id="mp-offer-note" class="mp-help" aria-live="polite"></p></section>'+
 '<dialog id="mp-shopping-settings-dialog" class="mp-shopping-settings-dialog" aria-labelledby="mp-shopping-settings-title">'+
 '<div class="mp-shop-dialog-heading mp-shopping-settings-heading"><h3 id="mp-shopping-settings-title">Indkøbslistens opsætning</h3><button type="button" class="mp-btn" id="mp-shopping-settings-close" aria-label="Luk indstillinger">Luk</button></div>'+
 '<p class="mp-help">Butikker, tilbudsaviser, OCR og vedligeholdelse af indkøbslisten.</p>'+
 '<p id="mp-shopping-settings-feedback" class="mp-help" role="status" aria-live="polite"></p>'+
 '<details class="mp-shop-filter"><summary>Butikker til tilbudssøgning ('+offerSelected.length+' valgt)</summary>'+
 '<p class="mp-help">Kun tilbud fra de valgte butikker vises. Valget gælder hele familien.</p>'+
 '<div class="mp-shop-filter-grid">'+offerOptions.map(offerToggleMarkup).join('')+'</div>'+
 '<button type="button" class="mp-btn mp-btn-primary" id="mp-save-offer-stores">Gem valgte butikker</button></details>'+
 '<details class="mp-shop-filter mp-flyer-library"><summary>Aktuelle tilbudsaviser</summary>'+ '<p class="mp-help">Se de originale avisforsider og sider for familiens valgte butikker. Avisbilleder er ikke tekstsøgbar data.</p>'+ '<button type="button" id="mp-load-flyers" class="mp-btn mp-btn-primary" '+(settings.configured?'':'disabled')+'>Hent aktuelle tilbudsaviser</button>'+ '<div id="mp-flyer-library-results" aria-live="polite"></div></details>'+
 (cfg.admin?
 '<details class="mp-shop-filter mp-ocr-pilot"><summary>OCR-test af én avisside (administrator)</summary>'+
 '<p class="mp-help">Vælg et avisbillede, du har tilladelse til at behandle. Billedet bliver kun brugt midlertidigt. Vi gemmer alene mulige varer og priser til familiens søgning.</p>'+
 '<div id="mp-ocr-status" class="mp-help">OCR-motoren kontrolleres, når du åbner testen.</div>'+
 '<form id="mp-ocr-import" class="mp-ocr-form">'+
 '<label>Butik<select name="store" required>'+offerSelected.map(function(n){
   return '<option value="'+esc(n)+'">'+esc(n)+'</option>';
 }).join('')+'</select></label>'+
 '<label>Tilbudsavis gælder til<input name="valid_to" type="date" required></label>'+
 '<label>Én avisside som JPG, PNG eller WebP (maks. 6 MB)<input name="image" type="file" accept="image/jpeg,image/png,image/webp"></label>'+
 '<button type="submit" class="mp-btn mp-btn-primary">Læs billedet med lokal OCR</button>'+
 '</form>'+
 '<details class="mp-ocr-text"><summary>Alternativ: indsæt OCR-tekst fra din PC</summary>'+
 '<textarea id="mp-ocr-pasted" rows="5" maxlength="200000" placeholder="Faxe Kondi&#10;24 x 33 cl&#10;65,-"></textarea>'+
 '<button id="mp-ocr-import-text" type="button" class="mp-btn">Indlæs OCR-tekst</button></details>'+
 '<div id="mp-ocr-import-result" class="mp-help" aria-live="polite"></div>'+
 '</details>':'')+
 '<details class="mp-shop-filter mp-daily-index"><summary>Dagligt tilbudsindeks'+
 (daily.enabled?' · aktivt':' · slået fra')+'</summary>'+
 '<p class="mp-help">Prejs strukturerede tilbud på familiens indkøbsliste opdateres automatisk efter kl. 05.30, hvis WordPress Cron kører. Indekset gælder kun for den aktuelle dag. Avisbilleder OCR-læses ikke automatisk.</p>'+
 '<div class="mp-daily-progress" aria-live="polite">'+
 (daily.updated_today?'Sidste opdatering: '+esc(daily.last_checked)+' · ':'Endnu ikke opdateret i dag · ')+
 esc(daily.offers||0)+' mulige fund · '+esc((daily.checked_stores||[]).length)+' butikker gennemgået'+
 (daily.partial?' · ufuldstændig dækning':'')+
 (daily.error?' · '+esc(daily.error):'')+'</div>'+
 (cfg.admin?'<label class="mp-daily-switch"><input type="checkbox" id="mp-daily-enabled" '+
 (daily.enabled?'checked':'')+'> Opdatér automatisk hver dag (maks. 8 Prej API-kald/døgn)</label>'+
 '<div class="mp-daily-buttons"><button type="button" class="mp-btn mp-btn-primary" id="mp-daily-save">Gem daglig opdatering</button>'+
 '<button type="button" class="mp-btn" id="mp-daily-now" '+(!daily.enabled?'disabled':'')+'>Kør næste lille batch nu</button></div>':'')+
 '<p class="mp-help">Indekset gemmer kun matchende varer og priser – ikke en kopi af samtlige kataloger. Hvis alle valgte butikker ikke kan gennemgås inden kvoten, vises det som ufuldstændigt.</p></details>'+
 '<details class="mp-shop-filter mp-daily-ocr"><summary>Daglig OCR af tilbudsaviser'+
 (dailyOCR.enabled?' · aktiv':' · slået fra')+'</summary>'+
 '<p class="mp-help">Frivillig, rettighedsstyret billed-OCR. En avisside behandles ad gangen, maks. '+esc(dailyOCR.cap||48)+' sider pr. dag. Billeder og den fulde OCR-tekst slettes efter behandlingen.</p>'+
 '<p class="mp-help">Dette kræver Tesseract på serveren og tilladelse fra rettighedshaverne til systematisk billedbehandling. At siden er privat giver ikke i sig selv denne tilladelse.</p>'+
 '<p class="mp-daily-progress" aria-live="polite">'+
 (dailyOCR.tesseract?'Tesseract tilgængelig':'Tesseract ikke tilgængelig')+
 ' · I dag: '+esc(dailyOCR.processed||0)+'/'+esc(dailyOCR.total||0)+' sider OCR-behandlet'+
 ' · '+esc(dailyOCR.offers||0)+' mulige tilbud'+
 (dailyOCR.partial?' · ufuldstændigt':'')+
 (dailyOCR.error?' · '+esc(dailyOCR.error):'')+'</p>'+
 (cfg.admin?'<label class="mp-daily-switch"><input type="checkbox" id="mp-daily-ocr-rights" '+
 (dailyOCR.rights_confirmed?'checked':'')+'> Jeg har indhentet de nødvendige rettigheder til automatiseret OCR af de valgte avisbilleder.</label>'+
 '<label class="mp-daily-switch"><input type="checkbox" id="mp-daily-ocr-enabled" '+
 (dailyOCR.enabled?'checked':'')+(!dailyOCR.tesseract?' disabled':'')+
 '> Aktiver daglig OCR-behandling</label>'+
 '<div class="mp-daily-buttons"><button type="button" class="mp-btn mp-btn-primary" id="mp-daily-ocr-save">Gem OCR-indstillinger</button>'+
 '<button type="button" class="mp-btn" id="mp-daily-ocr-next" '+(!dailyOCR.enabled?'disabled':'')+'>Behandl næste avisside nu</button></div>':'')+
 '<small class="mp-help">OCR-fund bliver markeret som usikre. Ved mange avissider bliver kørslen ufuldstændig og fortsætter ikke ubegrænset.</small></details>'+
 '</dialog>';
 var groups={};
 list.forEach(function(x){
  var group=Number(x.checked)?'Købt':(x.store||'Alle');
  if(!groups[group])groups[group]=[];
  groups[group].push(x);
 });
 var names=Object.keys(groups).sort(function(a,b){
  if(a==='Købt')return 1;if(b==='Købt')return -1;
  if(a==='Tilbud')return -1;if(b==='Tilbud')return 1;
  if(a==='Alle')return 1;if(b==='Alle')return -1;
  return a.localeCompare(b,'da');
 });
 if(!names.length)html+='<section class="mp-card"><p>Indkøbslisten er tom. Tilføj en vare ovenfor.</p></section>';
 names.forEach(function(group){
  html+='<section class="mp-shopping-group"><h3 class="mp-shopping-group-name">'+
   (group==='Tilbud'?'🐷 ':group==='Købt'?'✓ ':'⌂ ')+esc(group)+'</h3>';
  groups[group].forEach(function(item){
   var checked=!!Number(item.checked);
   html+='<article class="mp-shopping-item mp-shopping-entry '+(checked?'mp-checked':'')+'" data-id="'+item.id+'">'+
    '<label class="mp-shopping-label"><input type="checkbox" class="item-check" data-id="'+item.id+'" '+
    (checked?'checked':'')+'><span class="mp-shopping-name">'+esc(item.title)+'</span>'+
    (item.details&&item.details.favorite?'<span class="mp-item-favourite" title="Favorit">★</span>':'')+
    '<small class="mp-shopping-amount">'+esc(item.details&&item.details.has_details?
      item.details.quantity+' stk'+(item.details.package_size?' · '+item.details.package_size:''):
      (item.amount||'1 stk'))+'</small></label>'+
    '<button type="button" class="mp-btn mp-shopping-details-open" data-id="'+item.id+'" aria-label="Rediger '+esc(item.title)+'">Ret</button>'+
    (!checked?'<div class="mp-shopping-tools"><label class="mp-shopping-store-wrap"><span>Butik</span>'+
      '<select class="mp-shopping-store" data-id="'+item.id+'" aria-label="Butik for '+esc(item.title)+'">'+
      storeSelect(item.store||'Alle')+'</select></label>'+
      '<button class="mp-btn mp-offer-check" type="button" data-id="'+item.id+'">Se tilbud</button></div>':'')+
    (cfg.admin?'<button type="button" class="mp-btn item-delete" data-id="'+item.id+'" aria-label="Slet '+esc(item.title)+'">×</button>':'')+
    '<div class="mp-item-offers" id="mp-item-offers-'+item.id+'" hidden></div></article>';
  });
  html+='</section>';
 });
 html+='<dialog id="mp-shopping-detail-dialog" class="mp-shopping-dialog" aria-labelledby="mp-shop-dialog-label"><form id="mp-shop-detail-form" class="mp-shop-details-form">'+
 '<div class="mp-shop-dialog-heading"><h3 id="mp-shop-dialog-label">Vareoplysninger</h3><button class="mp-btn" type="button" id="mp-shop-close" aria-label="Luk">Luk</button></div>'+
 '<div class="mp-shop-photo-preview" id="mp-shop-photo-preview"></div>'+
 '<label>Varenavn<input name="title" maxlength="255" required></label>'+
 '<label>Butik / kategori<select name="store">'+storeSelect('Alle')+'</select></label>'+
 '<label>Antal <div class="mp-shop-stepper"><button type="button" id="mp-qty-minus" aria-label="Træk én fra">−</button><input type="number" min="1" max="999" step="1" name="quantity" required><button type="button" id="mp-qty-plus" aria-label="Læg én til">+</button></div></label>'+
 '<label>Pakningsstørrelse<input name="package_size" maxlength="80" placeholder="fx 400 g, 1 L"></label>'+
 '<label>Pris i kr.<input name="price" inputmode="decimal" placeholder="fx 29,95"></label>'+
 '<label>Stregkode<input name="barcode" inputmode="numeric" pattern="[0-9]*" maxlength="14" placeholder="EAN/UPC (8, 12, 13 eller 14 cifre)"></label>'+
 '<label>Noter<textarea name="notes" rows="3" maxlength="1000" placeholder="Skriv en note"></textarea></label>'+
 '<label class="mp-shop-fav-label"><input type="checkbox" name="favorite"> Markér som favorit ★</label>'+
 '<label>Varefoto (valgfrit, maks. 3 MB)<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label>'+
 '<button type="submit" class="mp-btn mp-btn-primary">Gem ændringer</button></form></dialog>';
 html+='<p class="mp-help mp-shopping-source">Tilbudsdata fra <a href="https://prej.dk/api" target="_blank" rel="noopener noreferrer">Prej</a>. Varevarianter og pakningsstørrelser kan være forskellige. Tilbudskvoten er begrænset på gratisplanen. '+
 (settings.configured?'Tryk på Se tilbud for at tjekke en vare.':
 'Administrator kan aktivere tilbudssøgning med gratis Prej API-nøgle under Hangar18 → Oversigt.')+
 '</p>';
 content.innerHTML=html;
 var settingsDialog=content.querySelector('#mp-shopping-settings-dialog');
 content.querySelector('#mp-shopping-settings-close').onclick=function(){settingsDialog.close();};
 settingsDialog.addEventListener('click',function(ev){if(ev.target===settingsDialog)settingsDialog.close();});
 settingsDialog.addEventListener('cancel',function(){/* Browser Escape closes the modal. */});
 if(reopenSettings){
  var allSettingsDetails=Array.from(settingsDialog.querySelectorAll('details'));
  expandedSettings.forEach(function(i){if(allSettingsDetails[i])allSettingsDetails[i].open=true;});
  settingsDialog.showModal();
  content.querySelector('#mp-shopping-settings-feedback').textContent='Indstillingerne er opdateret.';
 }
 // Camera decoding happens in the browser; only the GTIN is sent to WordPress.
 // No camera frames or pictures are uploaded or stored.
 content.insertAdjacentHTML('beforeend',
  '<dialog id="mp-barcode-dialog" class="mp-barcode-dialog" aria-labelledby="mp-barcode-heading">'+
  '<h3 id="mp-barcode-heading">Scan stregkode</h3>'+
  '<p class="mp-help">Hold kameraet over varens EAN-stregkode. Kendte varer tilføjes automatisk til familiens liste.</p>'+
  '<video id="mp-barcode-video" autoplay muted playsinline hidden></video>'+
  '<div id="mp-barcode-reader" class="mp-barcode-reader" hidden></div>'+
  '<p id="mp-barcode-message" class="mp-help" role="status" aria-live="polite"></p>'+
  '<div class="mp-barcode-actions"><button type="button" class="mp-btn mp-btn-primary" id="mp-barcode-camera">Start kamera</button>'+
  '<button type="button" class="mp-btn" id="mp-barcode-close">Luk</button></div>'+
  '<form id="mp-barcode-code-form"><label>Indtast EAN/UPC manuelt'+
  '<input id="mp-barcode-code" type="text" inputmode="numeric" autocomplete="off" pattern="[0-9]*" maxlength="14" required placeholder="8, 12, 13 eller 14 cifre"></label>'+
  '<button type="submit" class="mp-btn mp-btn-primary">Find og tilføj vare</button></form>'+
  '<form id="mp-barcode-new-form" hidden><strong>Stregkoden blev ikke genkendt</strong>'+
  '<p class="mp-help">Angiv varenavnet én gang. Herefter husker familien denne stregkode.</p>'+
  '<label>Varenavn<input id="mp-barcode-new-title" maxlength="255" required placeholder="Fx Faxe Kondi"></label>'+
  '<label>Pakningsstørrelse (valgfri)<input id="mp-barcode-new-quantity" maxlength="80" placeholder="Fx 1,5 liter"></label>'+
  '<button type="submit" class="mp-btn mp-btn-primary">Gem og tilføj vare</button></form>'+
  '<p class="mp-help">Kamera kræver HTTPS og din tilladelse. Scanning behandles på telefonen; kun stregkoden sendes til WordPress.</p>'+
  '</dialog>');
 var barcodeDialog=content.querySelector('#mp-barcode-dialog');
 var barcodeVideo=content.querySelector('#mp-barcode-video');
 var barcodeMessage=content.querySelector('#mp-barcode-message');
 var barcodeInput=content.querySelector('#mp-barcode-code');
 var barcodeStart=content.querySelector('#mp-barcode-camera');
 var barcodeUnknown=content.querySelector('#mp-barcode-new-form');
 var barcodeStream=null,barcodeRun=0,barcodeWorking=false,barcodeUnknownCode='';
 var barcodeReader=content.querySelector('#mp-barcode-reader');
 var barcodeFallback=null,barcodeFallbackLoader=null;
 function barcodeLoadFallback(){
  if(typeof window.Html5Qrcode==='function')return Promise.resolve();
  if(!barcodeFallbackLoader){
   barcodeFallbackLoader=new Promise(function(resolve,reject){
    var script=document.createElement('script');
    script.src='https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js';
    script.crossOrigin='anonymous';
    script.referrerPolicy='no-referrer';
    script.onload=function(){
     if(typeof window.Html5Qrcode==='function')resolve();
     else reject(new Error('Scannerbiblioteket kunne ikke indlæses.'));
    };
    script.onerror=function(){reject(new Error('Scannerbiblioteket blev blokeret eller kunne ikke hentes.'));};
    document.head.appendChild(script);
   }).catch(function(err){barcodeFallbackLoader=null;throw err;});
  }
  return barcodeFallbackLoader;
 }
 function barcodeStop(){
  barcodeRun++;
  if(barcodeStream){barcodeStream.getTracks().forEach(function(track){track.stop();});barcodeStream=null;}
  if(barcodeFallback){
   var oldScanner=barcodeFallback;barcodeFallback=null;
   Promise.resolve().then(function(){return oldScanner.stop();}).catch(function(){}).then(function(){
    try{oldScanner.clear();}catch(ignore){}
   });
  }
  barcodeVideo.hidden=true;barcodeVideo.srcObject=null;barcodeReader.hidden=true;
  barcodeStart.disabled=false;
 }
 function barcodeClose(){
  barcodeStop();barcodeUnknown.hidden=true;barcodeUnknownCode='';
  if(typeof barcodeDialog.close==='function')barcodeDialog.close();
  else barcodeDialog.removeAttribute('open');
 }
 var barcodeCloseButton=content.querySelector('#mp-barcode-close');
 barcodeCloseButton.onclick=barcodeClose;
 barcodeDialog.addEventListener('close',barcodeStop);
 content.querySelector('#mp-open-barcode').onclick=function(){
  barcodeUnknown.hidden=true;barcodeUnknownCode='';barcodeInput.value='';
  barcodeMessage.textContent='Tryk Start kamera eller indtast stregkoden.';
  if(typeof barcodeDialog.showModal==='function')barcodeDialog.showModal();
  else barcodeDialog.setAttribute('open','');
 };
 async function barcodeAdd(code,title,quantity){
  barcodeStop();
  var codeText=String(code||'').trim();
  if(!/^(?:[0-9]{8}|[0-9]{12,14})$/.test(codeText)){
   barcodeMessage.textContent='Skriv 8, 12, 13 eller 14 cifre fra stregkoden.';return;
  }
  if(barcodeWorking)return;
  barcodeWorking=true;barcodeMessage.textContent='Finder varen via familien, Prej og Open Food Facts…';
  try{
   var payload={barcode:codeText};
   if(title)payload.title=title;
   if(quantity)payload.quantity=quantity;
   var found=await api('shopping/barcode/add','POST',payload);
   if(found.needs_name){
    barcodeUnknownCode=codeText;barcodeUnknown.hidden=false;
    barcodeInput.value=codeText;
    barcodeMessage.textContent='Produktet er ikke registreret. Skriv varens navn nedenfor.';
    content.querySelector('#mp-barcode-new-title').focus();return;
   }
   barcodeClose();
   await shopping();
   msg(found.existing?'Varen "'+found.title+'" er allerede på indkøbslisten.':
    'Varen "'+found.title+'" er tilføjet til indkøbslisten.');
  }catch(e){barcodeMessage.textContent=e.message||'Stregkoden kunne ikke behandles.';}
  finally{barcodeWorking=false;}
 }
 content.querySelector('#mp-barcode-code-form').onsubmit=function(ev){
  ev.preventDefault();barcodeAdd(barcodeInput.value);
 };
 barcodeUnknown.onsubmit=function(ev){
  ev.preventDefault();
  barcodeAdd(barcodeUnknownCode,content.querySelector('#mp-barcode-new-title').value.trim(),
   content.querySelector('#mp-barcode-new-quantity').value.trim());
 };
 barcodeStart.onclick=async function(){
  if(!window.isSecureContext){
   barcodeMessage.textContent='Kamera kræver HTTPS. Åbn https://hangar18.dk/ i Safari eller Chrome.';
   return;
  }
  if(!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia){
   barcodeMessage.textContent='Browseren tillader ikke kameraadgang. Brug Safari eller Chrome, eller indtast EAN-koden.';
   return;
  }
  // Safari on iOS has no BarcodeDetector by default. Use an in-browser decoder instead.
  // The bundled library is fetched only when needed; camera frames never leave the device.
  if(typeof window.BarcodeDetector!=='function'){
   barcodeStop();
   var fallbackRequest=++barcodeRun;
   barcodeStart.disabled=true;
   barcodeMessage.textContent='Indlæser kamerascanner til Safari…';
   try{
    await barcodeLoadFallback();
    if(barcodeRun!==fallbackRequest || !barcodeDialog.open)return;
    if(typeof window.Html5QrcodeSupportedFormats!=='object')
     throw new Error('Browserens scannerformater er ikke tilgængelige.');
    var scanFormats=window.Html5QrcodeSupportedFormats;
    var scanner=new window.Html5Qrcode('mp-barcode-reader',{
     formatsToSupport:[scanFormats.EAN_13,scanFormats.EAN_8,scanFormats.UPC_A,scanFormats.UPC_E],
     useBarCodeDetectorIfSupported:false
    },false);
    barcodeFallback=scanner;
    barcodeReader.hidden=false;
    barcodeMessage.textContent='Tillad kameraadgang i Safari, når du bliver spurgt…';
    await scanner.start({facingMode:'environment'},{fps:10,qrbox:{width:260,height:130}},
     function(value){
      if(barcodeRun!==fallbackRequest || barcodeWorking)return;
      var code=String(value||'').trim();
      if(/^(?:[0-9]{8}|[0-9]{12,14})$/.test(code))barcodeAdd(code);
     },function(){});
    if(barcodeRun!==fallbackRequest || !barcodeDialog.open){barcodeStop();return;}
    barcodeMessage.textContent='Hold stregkoden stille i kameraets ramme.';
   }catch(e){
    barcodeStop();
    var permissionDenied=e.name==='NotAllowedError'||/permission|denied|tilladelse/i.test(String(e.message||''));
    barcodeMessage.textContent=permissionDenied?
     'Kameraadgang blev afvist. Giv hangar18.dk adgang under Safari → Webstedsindstillinger → Kamera, og prøv igen.':
     'Kameraet kunne ikke startes: '+(e.message||'ukendt fejl')+'. Prøv igen, eller indtast EAN-koden.';
   }finally{barcodeStart.disabled=false;}
   return;
  }
  barcodeStop();
  var request=++barcodeRun;
  barcodeStart.disabled=true;barcodeMessage.textContent='Åbner kameraet…';
  try{
   var supported=typeof BarcodeDetector.getSupportedFormats==='function'?
     await BarcodeDetector.getSupportedFormats():['ean_13','ean_8','upc_a','upc_e'];
   var formats=['ean_13','ean_8','upc_a','upc_e','itf'].filter(function(f){return supported.indexOf(f)>=0;});
   if(!formats.length)throw new Error('Browseren understøtter ikke EAN/UPC-stregkoder.');
   var detector=new BarcodeDetector({formats:formats});
   var stream=await navigator.mediaDevices.getUserMedia({
    audio:false,video:{facingMode:{ideal:'environment'}}
   });
   if(barcodeRun!==request || !barcodeDialog.open){stream.getTracks().forEach(function(t){t.stop();});return;}
   barcodeStream=stream;barcodeVideo.srcObject=stream;barcodeVideo.hidden=false;
   await barcodeVideo.play();
   barcodeMessage.textContent='Hold stregkoden stille foran kameraet.';
   var busy=false,lastScan=0;
   async function frame(now){
    if(barcodeRun!==request)return;
    if(!barcodeVideo.isConnected || !barcodeDialog.open){barcodeStop();return;}
    if(!busy && barcodeVideo.readyState>=2 && now-lastScan>220){
     busy=true;lastScan=now;
     try{
      var detections=await detector.detect(barcodeVideo);
      if(barcodeRun!==request)return;
      var value=detections.map(function(d){return d.rawValue;}).find(function(v){
       return /^(?:[0-9]{8}|[0-9]{12,14})$/.test(String(v||''));
      });
      if(value){barcodeAdd(value);return;}
     }catch(ignore){}
     finally{busy=false;}
    }
    if(barcodeRun===request)requestAnimationFrame(frame);
   }
   requestAnimationFrame(frame);
  }catch(e){
   barcodeStop();
   barcodeMessage.textContent='Kameraet kunne ikke startes: '+(e.message||'tillad kameraadgang')+
    '. Skriv stregkoden manuelt i stedet.';
  }
 };

 var flyerBtn=content.querySelector('#mp-load-flyers');
 var flyerArea=content.querySelector('#mp-flyer-library-results');
 if(flyerBtn) flyerBtn.onclick=async function(){
  flyerBtn.disabled=true;
  flyerArea.textContent='Henter familiens aktuelle tilbudsaviser…';
  try{
   var result=await api('shopping/flyers');
   var flyers=result.flyers||[];
   if(!flyers.length) {
    flyerArea.innerHTML='<p class="mp-help">Ingen aktuelle aviser fundet for de valgte butikker. Kontrollér butikkernes afkrydsninger.</p>';
   } else {
    var html='<p class="mp-help">'+flyers.length+' aktuelle aviser fundet.'+
       (result.partial?' Oversigten er ufuldstændig: Prej har flere aviser end de hentede sider.':'')+'</p>';
    flyers.forEach(function(flyer){
     html+='<article class="mp-flyer-card"><div><strong>'+esc(flyer.chain)+'</strong>'+
      '<small>Gælder fra '+esc(flyer.valid_from)+' til '+esc(flyer.valid_to)+
      ' · '+esc(flyer.page_count)+' sider</small></div>';
     if(flyer.page_urls&&flyer.page_urls.length) {
      html+='<a href="'+url(flyer.page_urls[0])+'" target="_blank" rel="noopener noreferrer" class="mp-btn mp-flyer-first">Åbn forside</a>'+
      '<details class="mp-flyer-pages"><summary>Se alle sider</summary><div class="mp-flyer-pages-grid">';
      flyer.page_urls.forEach(function(page,i){
       html+='<a href="'+url(page)+'" target="_blank" rel="noopener noreferrer">Side '+(i+1)+'</a>';
      });
      html+='</div></details>';
     }
     html+='</article>';
    });
    html+='<p class="mp-help">Kilde: <a href="https://prej.dk" target="_blank" rel="noopener noreferrer">Prej</a>. Avisens sider åbnes hos udbyderen. Der foretages ikke automatisk tekstgenkendelse (OCR).</p>';
    flyerArea.innerHTML=html;
   }
   var usage=result.usage||{};
   msg('Tilbudsaviser indlæst. Prej-kvote: '+(usage.used||0)+' / '+(usage.limit||20)+'.');
  }catch(e){
   flyerArea.textContent=e.message||'Tilbudsaviser kunne ikke indlæses.';
   fail(e);
  }finally{flyerBtn.disabled=false;}
 };
 var ocrForm=content.querySelector('#mp-ocr-import');
 if(ocrForm){
  var ocrResult=content.querySelector('#mp-ocr-import-result');
  var ocrText=content.querySelector('#mp-ocr-pasted');
  var ocrStatus=content.querySelector('#mp-ocr-status');
  var ocrOpen=content.querySelector('.mp-ocr-pilot');
  ocrOpen.addEventListener('toggle',async function(){
   if(!ocrOpen.open||ocrStatus.dataset.checked)return;
   ocrStatus.textContent='Kontrollerer lokal Tesseract…';
   try{
    var data=await api('shopping/ocr/status');
    ocrStatus.textContent=data.message;
    ocrStatus.dataset.checked='1';
   }catch(e){ocrStatus.textContent=e.message||'Kunne ikke kontrollere OCR-motoren.';}
  });
  function printOcrResult(data){
   var examples=data.examples||[];
   ocrResult.innerHTML='<strong>'+esc(data.imported)+' OCR-kandidater indlæst.</strong>'+
    '<p class="mp-help">Priserne er automatiske forslag og skal kontrolleres i avisen.</p>'+
    (examples.length?'<ul>'+examples.map(function(v){
     return '<li>'+esc(v.name)+' · '+esc(v.price_label)+' · '+esc(v.store)+'</li>';
    }).join('')+'</ul>':'');
  }
  ocrForm.onsubmit=async function(ev){
   ev.preventDefault();
   var file=ocrForm.elements.image.files&&ocrForm.elements.image.files[0];
   if(!file){ocrResult.textContent='Vælg et billede først.';return;}
   if(file.size>6*1024*1024){ocrResult.textContent='Billedet må højst være 6 MB.';return;}
   var btn=ocrForm.querySelector('button[type=submit]');btn.disabled=true;
   ocrResult.textContent='OCR-behandler billedsiden…';
   try{
    var payload=new FormData();
    payload.append('image',file);
    payload.append('store',ocrForm.elements.store.value);
    payload.append('valid_to',ocrForm.elements.valid_to.value);
    var response=await fetch(cfg.api+'shopping/ocr/upload',{
     method:'POST',headers:{'X-WP-Nonce':cfg.nonce},body:payload,credentials:'same-origin'
    });
    var data=await response.json().catch(function(){return {};});
    if(!response.ok)throw new Error(data.message||'OCR-behandlingen mislykkedes.');
    printOcrResult(data);
    ocrForm.elements.image.value='';
   }catch(e){ocrResult.textContent=e.message||'OCR-behandlingen mislykkedes.';fail(e);}
   finally{btn.disabled=false;}
  };
  var importButton=content.querySelector('#mp-ocr-import-text');
  importButton.onclick=async function(){
   importButton.disabled=true;ocrResult.textContent='Indlæser OCR-tekst…';
   try{
    var data=await api('shopping/ocr/import-text','POST',{
     text:ocrText.value,store:ocrForm.elements.store.value,
     valid_to:ocrForm.elements.valid_to.value
    });
    printOcrResult(data);
   }catch(e){ocrResult.textContent=e.message||'Kunne ikke indlæse teksten.';fail(e);}
   finally{importButton.disabled=false;}
  };
 }
 var dailySave=content.querySelector('#mp-daily-save');
 var dailyNow=content.querySelector('#mp-daily-now');
 if(dailySave)dailySave.onclick=async function(){
  dailySave.disabled=true;
  try{
   await api('shopping/daily/settings','POST',{enabled:content.querySelector('#mp-daily-enabled').checked});
   await shopping();msg('Den daglige tilbudsopdatering er gemt for hele familien.');
  }catch(e){fail(e);dailySave.disabled=false;}
 };
 if(dailyNow)dailyNow.onclick=async function(){
  dailyNow.disabled=true;
  try{
   var result=await api('shopping/daily/run','POST',{});
   await shopping();
   msg('Dagens tilbudsindeks: '+result.offers+' mulige fund. '+
       (result.complete?'Kørslen er afsluttet.':'Flere små batches mangler.'));
  }catch(e){fail(e);dailyNow.disabled=false;}
 };
 var ocrDailySave=content.querySelector('#mp-daily-ocr-save');
 var ocrDailyRun=content.querySelector('#mp-daily-ocr-next');
 if(ocrDailySave)ocrDailySave.onclick=async function(){
  ocrDailySave.disabled=true;
  try{
   var data=await api('shopping/daily-ocr/settings','POST',{
    enabled:content.querySelector('#mp-daily-ocr-enabled').checked,
    rights_confirmed:content.querySelector('#mp-daily-ocr-rights').checked
   });
   await shopping();msg(data.enabled?'Daglig OCR aktiveret.':'Daglig OCR slået fra.');
  }catch(e){fail(e);ocrDailySave.disabled=false;}
 };
 if(ocrDailyRun)ocrDailyRun.onclick=async function(){
  ocrDailyRun.disabled=true;
  try{
   var result=await api('shopping/daily-ocr/run','POST',{});
   await shopping();
   msg('OCR: '+result.processed+'/'+result.total+' sider i dag.'+
    (result.error?' '+result.error:''));
  }catch(e){fail(e);ocrDailyRun.disabled=false;}
 };
 var offerSave=content.querySelector('#mp-save-offer-stores');
 offerSave.onclick=async function(){
  var stores=Array.from(content.querySelectorAll('.mp-filter-store:checked')).map(function(input){return input.value;});
  offerSave.disabled=true;
  try{await api('shopping/offer-stores','POST',{stores:stores});await shopping();msg('Familiens tilbudsbutikker er gemt.');}
  catch(e){fail(e);offerSave.disabled=false;}
 };
 var clearBtn=content.querySelector('#mp-clear-purchased');
 clearBtn.onclick=async function(){
  if(!purchased.length)return;
  if(!window.confirm('Vil du slette '+purchased.length+' købte varer fra familiens indkøbsliste? Ukøbte varer bevares.'))return;
  clearBtn.disabled=true;
  try{
   var result=await api('shopping/clear-purchased','POST',{});
   await shopping();msg('Ryddet '+result.deleted+' købte varer. Ukøbte varer er bevaret.');
  }catch(e){fail(e);clearBtn.disabled=false;}
 };
 var dialog=content.querySelector('#mp-shopping-detail-dialog');
 var form=content.querySelector('#mp-shop-detail-form');
 var editing=null;
 content.querySelector('#mp-shop-close').onclick=function(){dialog.close();};
 dialog.onclick=function(ev){if(ev.target===dialog)dialog.close();};
 content.querySelector('#mp-qty-minus').onclick=function(){var input=form.elements.quantity;input.value=Math.max(1,(Number(input.value)||1)-1);};
 content.querySelector('#mp-qty-plus').onclick=function(){var input=form.elements.quantity;input.value=Math.min(999,(Number(input.value)||1)+1);};
 content.querySelectorAll('.mp-shopping-details-open').forEach(function(b){
  b.onclick=function(){
   editing=list.find(function(item){return String(item.id)===b.dataset.id;});
   if(!editing)return;
   var d=editing.details||{};
   form.elements.title.value=editing.title;
   form.elements.store.value=editing.store||'Alle';
   form.elements.quantity.value=d.quantity||1;
   form.elements.package_size.value=d.package_size||'';
   form.elements.price.value=d.price_ore==null?'':(Number(d.price_ore)/100).toFixed(2).replace('.',',');
   form.elements.barcode.value=d.barcode||'';
   form.elements.notes.value=d.notes||'';
   form.elements.favorite.checked=!!d.favorite;
   form.elements.photo.value='';
   content.querySelector('#mp-shop-photo-preview').innerHTML=d.photo_url?
     '<img alt="Varefoto" src="'+url(d.photo_url)+'">':'';
   dialog.showModal();
  };
 });
 form.onsubmit=async function(ev){
  ev.preventDefault();
  if(!editing)return;
  var submit=form.querySelector('button[type="submit"]');submit.disabled=true;
  try{
   await api('shopping/'+editing.id+'/details','POST',{
    title:form.elements.title.value.trim(),
    store:form.elements.store.value,
    quantity:Number(form.elements.quantity.value),
    package_size:form.elements.package_size.value,
    price:form.elements.price.value,
    barcode:form.elements.barcode.value,
    notes:form.elements.notes.value,
    favorite:form.elements.favorite.checked
   });
   var file=form.elements.photo.files&&form.elements.photo.files[0];
   if(file){
    if(file.size>3*1024*1024)throw new Error('Billedet må højst fylde 3 MB. De øvrige oplysninger er gemt.');
    var data=new FormData();data.append('photo',file);
    var response=await fetch(cfg.api+'shopping/'+editing.id+'/photo',{
      method:'POST',credentials:'same-origin',headers:{'X-WP-Nonce':cfg.nonce},body:data
    });
    var result=await response.json().catch(function(){return {};});
    if(!response.ok)throw new Error((result.message||'Foto kunne ikke gemmes.')+' De øvrige oplysninger er gemt.');
   }
   dialog.close();await shopping();msg('Vareoplysningerne er gemt for hele familien.');
  }catch(e){fail(e);}
  finally{submit.disabled=false;}
 };
 // Suggestions are matched locally against the family's *purchased* history.
 // No API call is made on each keystroke and no private profile info is used.
 var titleInput=content.querySelector('#item-title');
 var historyBox=content.querySelector('#mp-history-matches');
 var choices=[],chosenIndex=-1;
 function normalizedTitle(value){
  var t=String(value||'').trim().toLocaleLowerCase('da-DK');
  return t.normalize('NFD').replace(/[\u0300-\u036f]/g,'');
 }
 function hideHistory(){
  historyBox.hidden=true;
  historyBox.innerHTML='';
  titleInput.setAttribute('aria-expanded','false');
  titleInput.removeAttribute('aria-activedescendant');
  choices=[];chosenIndex=-1;
 }
 function chooseHistory(index){
  if(!choices[index])return;
  titleInput.value=choices[index].title;
  hideHistory();titleInput.focus();
 }
 function renderHistory(){
  var phrase=normalizedTitle(titleInput.value);
  if(phrase.length<2){hideHistory();return;}
  choices=(Array.isArray(history)?history:[]).filter(function(entry){
   return entry&&typeof entry.title==='string'&&normalizedTitle(entry.title).includes(phrase);
  }).sort(function(a,b){
   var ap=normalizedTitle(a.title).startsWith(phrase),bp=normalizedTitle(b.title).startsWith(phrase);
   if(ap!==bp)return ap?-1:1;
   return String(b.last_purchased||'').localeCompare(String(a.last_purchased||''));
  }).slice(0,8);
  chosenIndex=-1;
  if(!choices.length){hideHistory();return;}
  historyBox.innerHTML=choices.map(function(entry,i){
   return '<button type="button" id="mp-history-choice-'+i+'" role="option" aria-selected="false" class="mp-history-choice" data-index="'+i+'">'+
    esc(entry.title)+'</button>';
  }).join('');
  historyBox.hidden=false;titleInput.setAttribute('aria-expanded','true');
  historyBox.querySelectorAll('.mp-history-choice').forEach(function(btn){
   btn.onpointerdown=function(ev){ev.preventDefault();chooseHistory(Number(btn.dataset.index));};
   btn.onclick=function(){chooseHistory(Number(btn.dataset.index));};
  });
 }
 titleInput.addEventListener('input',renderHistory);
 titleInput.addEventListener('keydown',function(ev){
  if(historyBox.hidden)return;
  if(ev.key==='Escape'){ev.preventDefault();hideHistory();return;}
  if(ev.key==='ArrowDown'||ev.key==='ArrowUp'){
   ev.preventDefault();
   chosenIndex=(chosenIndex+(ev.key==='ArrowDown'?1:-1)+choices.length)%choices.length;
   historyBox.querySelectorAll('.mp-history-choice').forEach(function(btn,i){
    btn.classList.toggle('mp-history-active',i===chosenIndex);
    btn.setAttribute('aria-selected',i===chosenIndex?'true':'false');
   });
   titleInput.setAttribute('aria-activedescendant','mp-history-choice-'+chosenIndex);
  }
  if(ev.key==='Enter'&&chosenIndex>=0){ev.preventDefault();chooseHistory(chosenIndex);}
 });
 titleInput.addEventListener('blur',function(){
  // Pointer selection is handled before blur; avoid stale suggestions.
  hideHistory();
 });
 content.querySelector('#add-item').onsubmit=async function(ev){
  ev.preventDefault();
  try{
   await api('shopping','POST',{title:content.querySelector('#item-title').value,
    amount:content.querySelector('#item-amount').value});
   await shopping();msg('Varen er tilføjet under familiens foretrukne butik.');
  }catch(e){fail(e);}
 };
 content.querySelectorAll('.item-check').forEach(function(b){b.onchange=async function(){
  try{await api('shopping/'+b.dataset.id,'POST',{checked:b.checked});await shopping();}
  catch(e){fail(e);}
 };});
 content.querySelectorAll('.item-delete').forEach(function(b){b.onclick=async function(){
  try{await api('shopping/'+b.dataset.id,'DELETE');await shopping();}
  catch(e){fail(e);}
 };});
 content.querySelectorAll('.mp-shopping-store').forEach(function(sel){
  sel.onchange=async function(){
   sel.disabled=true;
   try{
    await api('shopping/'+sel.dataset.id+'/store','POST',{store:sel.value});
    await shopping();msg('Familiens foretrukne butik er gemt til næste gang.');
   }catch(e){fail(e);sel.disabled=false;}
  };
 });
 function displayOfferResult(item){
  var panel=content.querySelector('#mp-item-offers-'+item.id);
  if(!panel)return;
  var offers=item.offers||[];
  var heading=item.expanded?'Tilbudsavis: '+(item.store||''):'Mulige tilbud på '+item.title;
  var markup='<strong>'+esc(heading)+'</strong>'+
   '<p class="mp-help">Sammenlign varenavn, mærke og pakningsstørrelse. Tilbudspriser kan variere lokalt.</p>';
  if(!offers.length){
   markup+='<p class="mp-help mp-flyer-summary">'+esc(item.message||'Ingen aktuelle tilbud fundet i datakilden.')+'</p>';
  }else{
   if(item.expanded&&item.message){
    markup+='<p class="mp-help mp-flyer-summary">'+esc(item.message)+'</p>';
   }
   var byStore={};
   offers.forEach(function(o){
    if(!byStore[o.store])byStore[o.store]=[];
    byStore[o.store].push(o);
   });
   Object.keys(byStore).sort(function(a,b){return a.localeCompare(b,'da');}).forEach(function(store){
    markup+='<h4 class="mp-offer-store">'+esc(store)+'</h4>';
    byStore[store].forEach(function(o){
     markup+='<div class="mp-offer-row"><div><strong>'+esc(o.name)+'</strong>'+
      '<small>'+esc(o.source)+(o.multi_buy?' · '+esc(o.multi_buy):'')+'</small>'+
      '<small>'+esc([o.quantity,o.unit_price].filter(Boolean).join(' · ')||'Kontrollér pakningsstørrelse')+'</small></div>'+
      '<div class="mp-offer-price"><strong>'+esc(o.price_label)+'</strong>'+
      '<small>'+(o.ends?'Til '+esc(o.ends):'Slutdato ikke oplyst')+'</small></div></div>';
    });
   });
  }
  // Explicit full-chain search; do not change a product's preferred store.
  if(offerSelected.length){
   var chosenStore=item.expanded&&item.store?item.store:
    ((list.find(function(x){return Number(x.id)===Number(item.id);})||{}).store||'');
   if(offerSelected.indexOf(chosenStore)===-1)chosenStore=offerSelected[0];
   markup+='<div class="mp-flyer-search"><label>Udvid søgning i en tilbudsavis'+
    '<select class="mp-flyer-store" aria-label="Vælg butik til udvidet tilbudssøgning">'+
    offerSelected.map(function(store){return '<option value="'+esc(store)+'" '+
      (store===chosenStore?'selected':'')+'>'+esc(store)+'</option>';}).join('')+
    '</select></label>'+
    '<button type="button" class="mp-btn mp-btn-primary mp-flyer-search-btn">Søg i butikkens tilbudsavis</button>'+
    '<small>Gennemgår op til 400 registrerede tilbud fra den valgte kæde. Kan bruge op til fire Prej-kald, men samme kæde caches til familien i 6 timer.</small></div>';
  }
  if(item.local_index){markup+='<div class="mp-local-prej-action"><button type="button" class="mp-btn mp-prej-live-btn">Søg også hos Prej (bruger API-kvote)</button></div>';}
  markup+='<div class="mp-ocr-matches" aria-live="polite"></div>';
  panel.innerHTML=markup;panel.hidden=false;panel.dataset.loaded='1';
  var live=panel.querySelector('.mp-prej-live-btn');
  if(live)live.onclick=function(){scanLive([Number(item.id)],live);};
  if(!item.local_index){
  // Daily index is shared and local; it never triggers an external Prej API call.
  var dailyArea=document.createElement('div');
  dailyArea.className='mp-daily-matches';
  panel.insertBefore(dailyArea,panel.querySelector('.mp-ocr-matches'));
  api('shopping/daily/matches?id='+encodeURIComponent(item.id)).then(function(result){
   if(!dailyArea.isConnected)return;
   var matches=result.offers||[];
   if(!matches.length)return;
   dailyArea.innerHTML='<strong>Dagens gemte tilbud ('+esc(result.day)+')</strong>'+
    matches.map(function(v){
     return '<div class="mp-offer-row"><div><strong>'+esc(v.name)+'</strong>'+
      '<small>'+esc(v.store)+' · '+esc(v.source)+'</small></div>'+
      '<div class="mp-offer-price"><strong>'+esc(v.price_label)+'</strong>'+
      '<small>'+(v.ends?'Til '+esc(v.ends):'Slutdato ukendt')+'</small></div></div>';
    }).join('');
  }).catch(function(){});
  // Daily scheduled OCR candidates have their own date-stamped index.
  var scheduledArea=document.createElement('div');
  scheduledArea.className='mp-daily-matches';
  panel.insertBefore(scheduledArea,panel.querySelector('.mp-ocr-matches'));
  api('shopping/daily-ocr/matches?id='+encodeURIComponent(item.id)).then(function(result){
   if(!scheduledArea.isConnected)return;
   var matches=result.offers||[];
   if(!matches.length)return;
   scheduledArea.innerHTML='<strong>Dagens automatisk OCR-læste fund (ikke verificeret)</strong>'+
    matches.map(function(v){
     return '<div class="mp-offer-row"><div><strong>'+esc(v.name)+'</strong>'+
      '<small>'+esc(v.store)+' · OCR fra tilbudsavis</small></div>'+
      '<div class="mp-offer-price"><strong>'+esc(v.price_label)+'</strong>'+
      '<small>Gælder til '+esc(v.valid_to)+'</small></div></div>';
    }).join('');
  }).catch(function(){});
  var ocrArea=panel.querySelector('.mp-ocr-matches');
  // Local stored results only; no Prej call and no image download.
  api('shopping/ocr/matches?id='+encodeURIComponent(item.id)).then(function(found){
   if(!ocrArea.isConnected)return;
   var matches=found.matches||[];
   if(!matches.length){return;}
   ocrArea.innerHTML='<strong>Mulige OCR-fund (ikke verificeret)</strong>'+
    '<p class="mp-help">Disse priser er læst fra et avisbillede og skal kontrolleres manuelt.</p>'+
    matches.map(function(v){
     return '<div class="mp-offer-row"><div><strong>'+esc(v.name)+'</strong>'+
      '<small>'+esc(v.store)+' · '+esc(v.source)+'</small></div>'+
      '<div class="mp-offer-price"><strong>'+esc(v.price_label)+'</strong>'+
      '<small>Gælder til '+esc(v.valid_to)+'</small></div></div>';
    }).join('');
  }).catch(function(){ /* Shopping offers remain usable without OCR. */ });
  }

  var expand=panel.querySelector('.mp-flyer-search-btn');
  if(expand)expand.onclick=async function(){
   var store=panel.querySelector('.mp-flyer-store').value;
   expand.disabled=true;
   var note=content.querySelector('#mp-offer-note');
   note.textContent='Søger i '+store+'s registrerede tilbudsavis…';
   try{
    var result=await api('shopping/flyer-search','POST',{id:Number(item.id),store:store});
    (result.items||[]).forEach(displayOfferResult);
    var usage=result.usage||{};
    note.textContent='Avisopslag færdigt. Prej-kvote: '+(usage.used||0)+' / '+(usage.limit||20)+' i dag.';
   }catch(e){note.textContent=e.message||'Avisopslaget fejlede.';fail(e);expand.disabled=false;}
  };
 }
 async function scanLive(ids,b){
  if(b)b.disabled=true;
  var note=content.querySelector('#mp-offer-note');
  note.textContent='Slår op hos Prej (bruger API-kvote)…';
  try{
   var results=await api('shopping/offers','POST',{ids:ids});
   (results.items||[]).forEach(displayOfferResult);
   var usage=results.usage||{};
   note.textContent='Prej-opslag: '+(usage.used||0)+' / '+(usage.limit||20)+' i dag.';
  }catch(e){note.textContent=e.message||'Prej-opslag fejlede.';fail(e);}
  finally{if(b)b.disabled=false;}
 }
 async function scan(ids,b){
  if(b)b.disabled=true;
  var note=content.querySelector('#mp-offer-note');
  note.textContent='Søger i familiens gemte tilbud og OCR-data…';
  try{
   var results=await api('shopping/local-offers','POST',{ids:ids});
   (results.items||[]).forEach(displayOfferResult);
   note.textContent='Kontrolleret '+(results.items||[]).length+
    ' vare(r) i den lokale tilbudsdatabase – 0 Prej-kald.';
  }catch(e){note.textContent=e.message||'Kunne ikke søge i gemte tilbud.';fail(e);}
  finally{if(b)b.disabled=false;}
 }
 content.querySelector('#mp-check-offers-all').onclick=function(){
  scan(pending.slice(0,10).map(function(x){return Number(x.id);}),this);
 };
 content.querySelectorAll('.mp-offer-check').forEach(function(b){
  b.onclick=function(){
   var panel=content.querySelector('#mp-item-offers-'+b.dataset.id);
   if(!panel)return;
   if(!panel.hidden){panel.hidden=true;b.textContent='Se tilbud';return;}
   b.textContent='Skjul tilbud';
   if(panel.dataset.loaded==='1'){panel.hidden=false;return;}
   panel.innerHTML='<p class="mp-help">Søger efter tilbud…</p>';panel.hidden=false;
   scan([Number(b.dataset.id)],b);
  };
 });
}

})();