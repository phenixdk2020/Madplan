(function(){
'use strict';
var root=document.getElementById('mp-app'), cfg=window.MP_PORTAL;if(!root||!cfg)return;
var content=root.querySelector('#mp-content'),status=root.querySelector('#mp-status'),home=root.querySelector('#mp-home'),main=root.querySelector('#mp-main'),tabs=root.querySelector('#mp-tabs');
var s={module:'',tab:'week',start:mon(cfg.today),week:null,next:null,recipes:null};
/* Personal preferences are stored in WordPress user_meta, not localStorage. */
var themeOptions=['default','dark','forest','purple','sand','ocean','rose','sunset','mint','burgundy'];
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
function setTab(t){s.tab=t;tabs.innerHTML=(s.module==='tv'?[['week','Ugeplan'],['watch','Serier'],['movies','Film']]:[['week','Madplan'],['recipes','Opskrifter'],['shopping','Indkøbsliste']]).map(function(x){return '<button type="button" class="mp-tab" data-tab="'+x[0]+'" '+(t===x[0]?'aria-current="page"':'')+'>'+x[1]+'</button>';}).join('');tabs.querySelectorAll('button').forEach(function(b){b.onclick=function(){setTab(b.dataset.tab);render();};});render();}
function module(name,tab){s.module=name;home.hidden=true;main.hidden=false;root.querySelector('#mp-module-title').textContent=name==='tv'?'TV-ugeplan':'Madplan';setTab(tab||'week');}
root.querySelectorAll('[data-module]').forEach(function(b){b.onclick=function(){module(b.dataset.module);};});
function openHash(){
 var hash=(window.location.hash||'').replace(/^#/,'').toLowerCase();
 if(hash==='tv'||hash==='tv-guide')module('tv','week');
 else if(hash==='movies'||hash==='film')module('tv','movies');
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
function render(){msg('');if(s.tab==='week')return week();if(s.tab==='watch')return watch();if(s.tab==='movies')return movies();if(s.tab==='recipes')return recipes();if(s.tab==='shopping')return shopping();}
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
var html=toolbar()+'<div class="mp-mobile-actions"><button type="button" id="mp-quick-add" class="mp-btn mp-btn-primary mp-add-primary">'+addText+'</button>'+(s.module==='tv'?'<button type="button" id="mp-movie-add" class="mp-btn mp-add-primary">+ Tilføj film</button>':'')+'</div>'+
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