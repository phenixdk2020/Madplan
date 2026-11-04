# Hangar18 Madplan & TV-plan

WordPress-plugin med mobil-first familieportal og desktop-orienteret opskriftseditor.
Repository: https://github.com/phenixdk2020/Madplan

## Version 0.1.8 — TV-guide, serieovervågning og temaer

- WordPress-login med Husk mig, ingen egen passwordopbevaring.
- Én fælles TV-ugeplan/watchlist og én madplan for alle loggede brugere.
- Alle syv dage (mandag–søndag) vises altid. Lodret mobil: syv kort under hinanden; vandret mobil/PC: syv kolonner.
- Madplan med fire navngivne personer under hinanden; egen ret eller "samme som" en af de andre. Cirkulære henvisninger afvises.
- Planer fra tidligere uger gemmes permanent efter dato; ændringer i måltider logføres.
- Opskrifter i WordPress-administrationen på PC: ingredienser, portioner, billeder til tilberedningstrin, ét coverbillede og billedgalleri med mange billeder.
- Fælles manuel indkøbsliste med afkrydsning.
- Serier kan søges via TVmaze, afsnit hentes og opdateres automatisk via WP-Cron.
- International/original premieredato vises tydeligt som sådan, ikke som en bekræftet dansk premieredato.
- Mulighed for at indtaste antal afsnit og beregne ugentlige forventede udgivelser med tydelig markering.

## Installation

1. Hent den installerbare ZIP fra GitHub Actions, eller pak plugin-filerne i mappen hangar18-madplan.
2. WordPress → Plugins → Tilføj nyt → Upload plugin → Aktivér.
3. Pluginet opretter/finder siden Familieplan og sætter den automatisk som WordPress-forside. Eksisterende sider overskrives aldrig.
4. Forsiden hangar18.dk/ viser nu en selvstændig loginportal – uden WordPress-temaets standardoverskrift, navigation og "Hello world".
5. Under Hangar18-indstillinger angives de fire personnavne.
6. Brug Hangar18 → Opskrifter fra PC-browseren. Billeder vælges fra WordPress Mediebibliotek.

## Adgang

Alle loggede brugere kan se og redigere den fælles madplan, tilføje TV-serier til watchlisten og tilføje/afkrydse varer. Administratorer kan desuden slette TV-serier, administrere personnavne og oprette opskrifter. WordPress står for adgangskoder og login-session. HTTPS og bypass af offentlig side-cache er påkrævet.

## TV-datoer

TVmaze leverer oprindelige/internationale udsendelsesdatoer, ikke verificerede danske streamingpremierer. Streamingudbyder og dansk klokkeslæt skal angives manuelt. Hvis sæsonen har 8 afsnit og første dato er kendt, kan systemet beregne 8 forventede ugentlige datoer. Beregninger mærkes Forventet, og pauser eller batch-udgivelser kan gøre dem unøjagtige.

TVmaze: https://www.tvmaze.com/api
TV-opdateringer bruger daglig WP-Cron, som kræver sitebesøg eller serverbaseret cron.

## Ikke færdigt i 0.1.7

- Automatisk indkøbsliste baseret på opskrifter/portioner
- Danske verificerede streamingpremierer og streamingklokkeslæt
- Opskriftssnapshots i historikken
- Prissammenligning og supermarkedstilbud
- Automatisk restaurering af databasen ved rollback (program-rollback gendanner ikke data)
- End-to-end-test i WordPress og på faktiske mobilskærme

Se docs/ARCHITECTURE.md for tekniske detaljer og testplan.


## GitHub-opdateringer (fra 0.1.1)

WordPress → **Hangar18 → Opdateringer** giver **Tjek GitHub-opdatering**, **Opdater nu**, SHA-256-verificeret download og versionshistorik. WordPress → Plugins bruger samme opdateringskilde. GitHub Actions publicerer automatisk versioneret ZIP under `dist/` og et `update.json`-manifest på `main`.

Ved plugin-opdatering oprettes automatisk to SHA-256-verificerede backups: program-ZIP og data-ZIP. Data omfatter egne Madplan-tabeller, opskrifter, relevante metadata og indstillinger. **Uploadede WordPress-mediebilleder er ikke inkluderet** (de påvirkes normalt ikke af plugin-opdateringer). Op til 12 checkpoint-par beholdes, og filer kan kun downloades gennem et administrator-autoriseret WordPress-endpoint. Rollback kan geninstallere den tidligere programkode; nye data bevares. En data-gendannelse kræver manuel behandling.

**Sikker backupplacering:** Som standard anvendes mappen `hangar18-madplan-backups` i overmappen til WordPress-installationen. Den skal være skrivbar og befinde sig udenfor webroden. På hosting med en anden struktur angives eksempelvis i `wp-config.php`:

```php
define('MP_BACKUP_DIR', '/home/ACCOUNT/private-backups/madplan');
```

Vælg en reel, privat sti — ikke en mappe under `public_html`, `www`, `wp-content` eller en anden webtilgængelig mappe. Kan checkpointet ikke oprettes, **stoppes opdateringen**. Kræver PHP ZipArchive. Tag separat backup af WordPress-databasen og mediebiblioteket før produktionsinstallation.

**Bootstrap:** Hvis 0.1.0 er installeret, kræver overgangen til 0.1.7 en sidste manuel ZIP-installation. Alle efterfølgende publicerede versioner kan opdateres via Hangar18 → Opdateringer. Udviklingsversioner fra GitHub Actions er ikke automatisk en del af update-kanalen, før GitHub Actions har publiceret `update.json` og `dist/` på `main`.


## 0.1.7 – forside, TV-guide og opdateringsdiagnose

**Vigtigt ved eksisterende installation:** Første besøg efter installation/opgradering til 0.1.7 finder/opretter siden Familieplan og gør den til **statisk forside**. Den tidligere indstilling for blog/statisk forside gemmes til senere gendannelse. Andre eksisterende sider/indlæg slettes ikke. Hvis /familieplan/ allerede eksisterer med andet indhold, oprettes i stedet en separat side under /hangar18-familieplan/. 

Den nye forsideskabelon viser kun Hangar18-appen (ikke WordPress-temaets header, men med WordPress' scripts og hooks). Start på https://hangar18.dk/ og log ind med din almindelige WordPress-bruger.

**Direkte navigation:** I WordPress-menuen findes nu Hangar18 → Oversigt, TV-guide, Madplan, Opskrifter, Opdateringer. Links:
- / – fælles loginportal og to modulvalg.
- /#tv – TV-ugeplan.
- /#tv-watch – den fælles watchlist, hvor administrator tilføjer serier (TVmaze).
- /#food – madplanen.
- /#shopping – indkøbslisten.
- /#recipes – opskrifter til mobilvisning.

TV-ugen er **tom**, indtil der er tilføjet en serie i watchlisten. TVmaze-datoer er oprindelige/internationale, ikke bekræftede danske premieredatoer eller klokkeslæt.

**Opdateringer:** Version 0.1.7 inkluderer WordPress' native Update URI-hook, opdateringsknap samt "Test backup og rettigheder" under Hangar18 → Opdateringer. Resultater og fejlmeddelelser vises på siden. Hvis hosting blokerer private backups eller GitHub-HTTPS, kan WordPress-opdateringen ikke gennemføres. Manuel ZIP-installation/erstatning er en engangs-fallback for overgang fra 0.1.1.

**Gendan tidligere forside:** Hangar18 → Oversigt → Gendan tidligere WordPress-forside. Opskrifter og madplan-data påvirkes ikke.


## 0.1.7 – mobiloprettelse af TV-programmer og aftensmad

Efter login er hovedmenuen TV-guide og Madplan. På mobil kan **alle loggede brugere**:
- Trykke **TV-guide → + Tilføj TV-program**. Søg efter en serie på TVmaze, vælg sæson, antal afsnit, første forventede dato og streamingtjeneste, og tilføj til den fælles watchlist.
- Trykke **Madplan → + Tilføj aftensmad** for at redigere dagens fire personrækker, eller **+ Tilføj / rediger** ved en anden dag.
- Vælge en ret for hver person eller "Samme som person 1, 2, 3 eller 4".
- Se den fælles indkøbsliste og sætte flueben ved købte varer.

Sletning af en TV-serie fra watchlisten og oprettelse/redigering af opskrifter i WordPress-administrationen forbeholdes administratorer. Alle kalenderdata deles mellem brugerne; historik bevares.

Mobilvisningen bruger store knapper og iPhone-venlige tekstfelter. Ingen ekstra API-abonnementer.


## 0.1.7 – WordPress opdateringsmenu og fejldiagnose

Opdateringsmenuen åbnes fra **Hangar18 → Opdateringer** eller direkte på:
https://hangar18.dk/wp-admin/admin.php?page=mp-updates

**Bemærk:** `/wp-admin/mp-updates` er ikke en WordPress-administrationsadresse og vil give 404. Pluginets oversigt og plugin-listen har nu direkte "Opdateringer"-links til den korrekte admin.php-adresse.

Opdateringsmenuen registreres efter Hangar18-hovedmenuen (WordPress admin_menu priority 50), så submenuen vises pålideligt. Funktionerne er baseret på Visual Designer Manager: GitHub-manifest, versionskontrol, WordPress native Plugin_Upgrader, SHA-256-pakkekontrol, program- og data-checkpoints, rollback af programkode.

Ved serverfejl vises årsagen: GitHub netværksfejl/HTTP-kode, ugyldigt manifest, mislykket backup eller installationsfejl. **Test backup og rettigheder** på opdateringssiden, inden ny version installeres.

Hvis hosten ikke har en skrivbar privat backup-mappe uden for webroden, stoppes opdateringen med en fejl for at undgå usikre/offentlige backupfiler. Kontakt hosting og opsæt `MP_BACKUP_DIR` i wp-config.php. Manuel ZIP upload er mulig som alternativ; tag databackup først.


## 0.1.7 – serie med navn + streamingtjeneste, og tilbage til portalmenuen

- TV-guide → + Tilføj TV-program: søg seriens navn i TVmaze og vælg den rigtige fra søgeresultaterne. Vælg/bekræft streamingtjenesten i Danmark. Klik "Tilføj til watchlist". Alt andet hentes automatisk når TVmaze har oplysninger.
- TVmaze /shows/{id}, /shows/{id}/seasons og /shows/{id}/episodes anvendes til sæson, planlagt sæsonantal (episodeOrder), første kendte premieredato og episodehistorik. Data caches i 6 timer. Hvis samlet antal episoder er ukendt, vises kun kendte episoder.
- TVmaze kan foreslå den **oprindelige** web-kanal/streamingtjeneste eller TV-netværk. Dette bekræfter **ikke** dansk streamingtilgængelighed; brugeren angiver den danske tjeneste.
- Ugentlige forventede datoer foreslås kun, når mindst to efterfølgende afsnit har kendte premieredatoer med syv dages mellemrum. Pauser og batchudgivelser gør fremskrivning usikker; fremtidige beregninger mærkes "Forventet".
- Ekstra felter for sæson, afsnitsantal, første dato og dansk klokkeslæt findes under "Vis eller ret sæsonoplysninger" for dem, der ønsker at korrigere.
- Hver TV- og Madplan-undervisning viser både i toppen og nederst **← Tilbage til forsiden**, som skifter til de to menuknapper i appen **uden at logge ud**. Kun "Log ud" logger brugeren af.

Ingen ekstra betalte API'er. TVmaze er en gratis kilde til originale afsnitsdata. Danmarksspecifikke streamingrettigheder og klokkeslæt kræver en separat datakilde eller manuel bekræftelse.


## 0.1.7 – hele ugen på alle skærme

Ugeplanen i **både TV-guide og Madplan** viser nu altid syv dage fra mandag til søndag. På en mobil i lodret format står dagene under hinanden og kan scrolles; på mobilen i vandret format og PC vises de i syv kolonner. Dagens dato fremhæves. Ugen indeholder præcis syv kalenderdage, ikke et skjult eller ekstra udsnit af den følgende uge. Forrige/næste uge virker stadig.


## 0.1.7 – personlige farvetemaer

Den oprindelige blå farvepalette hedder **Default** og er fortsat standard for nye brugere. I topmenuen på mobil og PC vælges ét af fem temaer:

1. **Default:** Oprindeligt blå.
2. **Mørk:** Mørk blå/sort med lys tekst.
3. **Skovgrøn:** Grønne accenter og lyst grønt underlag.
4. **Lilla:** Lilla nuancer.
5. **Sand:** Varme beige- og brune farver.

Temaet gemmes i WordPress `user_meta` under nøglen `mp_portal_theme` på den **indloggede bruger**, ikke i cookies eller browserens localStorage. Dermed følger temaet brugeren på tværs af PC/mobil og nye login. En anden bruger kan vælge en anden farve. Kun den fælles madplan/TV-watchlist deles.

API: `POST /wp-json/hangar18/v1/theme` med JSON `{"theme":"dark"}`. Kræver login, WordPress REST-nonce og en tilladt temaværdi. Serveren afviser ukendte temaer. Browseren viser et preview med det samme; hvis lagringen fejler, gendannes den tidligere farve, og fejlen vises.

Login-skærmen før man er logget ind anvender altid Default. Efter login læses brugerens gemte tema på serveren og sættes på siden allerede i HTML, så farven også vises før JavaScript starter. Alle fem temaer er understøttet på mobil og desktop.


## v0.1.8 – seriekort med plakat, tjeneste-dropdown og sæsonovervågning

TV-søgning viser op til 10 TVmaze-serier i trykbare kort med plakat, startår, oprindelig kanal og sæsonantal. Sæsonantal hentes løbende fra TVmaze, med op til 3 samtidige opslag og 12-timers cache. Hvis data ikke findes, vises det som ukendt. Serien vælges ved tryk på hele kortet.

Dansk streamingtjeneste vælges i en dropdown (Netflix, HBO Max, Disney+, Prime Video, Apple TV+, SkyShowtime, Viaplay, TV 2 Play, DRTV, BritBox, Nordisk Film+, MUBI, Crunchyroll, hayu, Pluto TV, Filmstriben, Allente Stream, YouSee Play, Norlys Play eller Ukendt). At en tjeneste findes i Danmark betyder **ikke** at den valgte serie kan ses der. TVmaze kan oplyse **oprindelig** tjeneste og premieredato, men ikke garantere dansk tilgængelighed eller dansk premieredato.

### Watchlist kører videre efter sidste afsnit
- Serier forbliver på den fælles watchlist, indtil en administrator vælger "Fjern".
- Daglig WordPress-Cron (når webstedet besøges) tjekker serien, også når alle kendte afsnit fra seneste sæson er udgivet. Maks. 10 serier pr. jobkørsel, ældst opdaterede først, så større watchlists gennemløbes over flere kørsler.
- Når TVmaze registrerer sæson N+1, skifter serien automatisk til den nye sæson i ugeplanen, henter kendte afsnit og viser "Ny sæson registreret". Dato og original udbyder oplyses, hvis TVmaze har dem.
- Efter en færdig sæson vises "Afventer næste sæson"; ukendt sæsonantal vises fortsat som "Overvåges", ikke fejlagtigt som færdig. Kommende kendte premieredatoer vises som kommende.
- **Valgt dansk streamingtjeneste overskrives aldrig** ved sæsonskifte. Originalpremiereudbyder vises særskilt og mærkes "ikke bekræftet dansk premiere". Klokkeslæt fra tidligere sæson kopieres ikke til den nye sæson.
- Tryk "Tjek nu" på watchlisten for øjeblikkelig synkronisering. Seneste status gemmes i WordPress option `mp_watch_monitor_v1`. Daglige kontroller og oprindelige premieredatoer er ikke et pris- eller rettighedsovervågningsabonnement.
- Der foretages ikke automatisk opslag på betalte eller uofficielle streamingkatalog-API'er. En verificeret dansk premiere hos Netflix/Prime Video/TV 2 Play kræver en kilde med danske rettigheder og kalenderoplysninger.

Temaer: eksisterende Default, Mørk, Skovgrøn, Lilla, Sand samt nye Havblå, Rosa, Solnedgang, Mint og Bordeaux. Alle 10 gemmes på WordPress-brugeren.


## Version 0.1.9 – rettelse af inaktivt plugin efter WordPress-opdatering

**Konkret fejl rettet:** WordPress' Plugin_Upgrader::upgrade() kalder deactivate_plugin_before_upgrade i ikke-cron-baserede opdateringer. Madplans selvbyggede GUI anvendte Automatic_Upgrader_Skin, som ikke genaktiverer pluginet. Efter en ellers vellykket opdatering var Hangar18-menuen derfor væk med "Du har ikke adgang til denne side" ved redirect.

- Installeren gemmer aktiv status (og multisite-netværksstatus) før WordPress deaktiverer.
- Ved gennemført upgrade genaktiveres pluginet automatisk og stille via activate_plugin().
- Også ved rapporteret mislykket upgrade forsøges genaktivering af den installerede pluginfil, når den oprindeligt var aktiv.
- Genaktivering gennemføres kun, hvis pluginet var aktivt før opdateringen; et bevidst inaktivt plugin skal forblive inaktivt.
- Backup via upgrader_pre_install kører før WordPress' standarddeaktivering, så et backup-problem ikke længere deaktiverer pluginet.
- Hvis genaktivering ikke lykkes, vises WordPress Plugins-siden i stedet for redirect til et Hangar18-adminmenupunkt, der ikke længere findes.
- PHP 8.0-kompatibilitet rettet: redirect bruger void, ikke PHP 8.1-returntypen never.

**Recovery for eksisterende installationer, der blev deaktiveret:** Åbn /wp-admin/plugins.php → Aktivér Hangar18 Madplan & TV-plan. Upload derefter ZIP v0.1.9 og vælg "Erstat eksisterende", eller installer via en opdateringsmetode, der garanterer aktivering. Undgå knappen "Opdater nu" fra ældre Hangar18-versioner, da buggen er i dem. Slet ikke pluginet og slet ikke data.

**Test:** Isoleret PHP regression tester normal og multisite-aktivering, ikke-genaktivering af inaktive plugins, andres plugins, allerede aktiv, aktiveringsfejl og manglende pluginfil. Integration i WordPress-webhotel skal stadig testes.


## v0.1.10 – danske streamingdatoer før TVmaze originaldatoer

Fejl eksempel: Marshals sæson 2 udsendes oprindeligt om søndagen, men danske SkyShowtime har premierer om fredagen. SkyShowtime bekræfter fredag 9. oktober 2026 som dansk sæsonpremiere; den danske streamingkalender viser også nyt afsnit fredag 30. oktober 2026.

- TV-guide vælger dansk udgivelsesdato **før** den oprindelige TVmaze-dato, når en dansk plan findes.
- Indbygget dokumenteret undtagelse: **Marshals sæson 2 + SkyShowtime**: 9. oktober 2026, ugentlig fredag, med bekræftede specifikke datoer 9. oktober og 30. oktober. Andre fredage beregnes og mærkes **Forventet DK**; de påstås ikke individuelt bekræftet.
- Watchlist viser status/kilde og link til SkyShowtime Danmark. Under **Ret dansk premieredag** kan en indlogget bruger angive en anden første dansk dato og vælge ugentlig rytme.
- REST POST `/shows/{id}/dk-schedule` gemmer en familieangivet dato for **aktuel sæson**. Tom dato fjerner den manuelle overstyring. Overstyringer lagres i `mp_dk_schedules_v1` som WordPress option og påvirker hele familiens fælles TV-guide.
- Manuelle rettelser overstyrer kuraterede kilder, og en gammel sæsons overstyring påvirker ikke automatisk næste sæson.
- Der er klare markeringer: **Bekræftet DK** (konkret dokumenteret), **Forventet DK** (beregnet), **Angivet af familien**, **TVmaze-dato** og **Forventet originaldato**.
- Oprindelige amerikanske premieredatoer vises stadig, når der ikke er en dansk kilde eller manuel overstyring.
- Intet automatisk abonnement på SkyShowtime eller tredjeparter; koden foretager ikke uautoriserede API-kald til streamingtjenester. Dansk datadækning udvides med kildebelagte regler fremover.

Kilder:
- https://www.skyshowtime.com/dk/its-time-for/marshals-a-yellowstone-story
- https://digitalt.tv/nyt-paa-skyshowtime-oktober-2026/
- https://digitalt.tv/SkyShowtime/?wpv_paged=2&wpv_view_count=909221-CATTR97b879431a810c8fcbeb23adba90b4ae

Test: `php tests/dk_schedule.php` verificerer, at fredag 30. oktober vinder over den amerikanske søndag, skelner mellem bekræftede og estimerede datoer, og at manuel sæson-korrektion har prioritet.


## v0.1.11 – Mine streamingtjenester og dansk katalogopslag

Alle loggede WordPress-brugere kan vælge deres abonnementer under TV-guide → Watchlist → Mine streamingtjenester. Gemmes per bruger i user-meta mp_streaming_subscriptions_v1, så valget følger både mobil og PC. Familiens madplan og fælles TV-watchlist deles stadig.

Ved oprettelse af en serie kan dropdownen stå på Automatisk – søg blandt mine tjenester. Hvis administrator har konfigureret den gratis TMDB API, prøver pluginet at identificere serien entydigt ud fra navn og premiereår. Derefter hentes streamingtilgængelighed fra JustWatch via TMDB for Danmark (DK). Kun flatrate-abonnementer eller gratis/annoncefinansierede tilbud på brugerens valgte tjenester regnes som match.

- Præcis ét match: streamingtjenesten udfyldes automatisk ved tilføjelse af serien.
- Flere match: vis tjenesterne, men vælg ikke vilkårligt; brugeren kan vælge manuelt.
- Ingen sikker match, ingen TMDB-nøgle eller midlertidig fejl: gem Ukendt, men bevar serien på watchlisten.
- Eksisterende serier, hvor streamingtjenesten er Ukendt, har knappen Tjek mine tjenester. Manuelt valgte streamingtjenester overskrives aldrig.
- Katalogdata siger ikke sikkert, om en bestemt sæson er med i abonnementet; danske premieredatoer og klokkeslæt kræver fortsat selvstændige kilder.

Administratorkonfiguration: Gå til Hangar18 → Oversigt → Streamingtjenester og indsæt en gratis TMDB API Read Access Token eller v3 API key fra https://developer.themoviedb.org/docs/getting-started . TMDB developer API er gratis for ikke-kommerciel brug under deres vilkår og krav om attribution. Administrator kan også sætte konstanten MP_TMDB_API_TOKEN i wp-config.php. Nøglen sendes aldrig til browseren eller GitHub.

Datakilder: TVmaze for afsnit og sæsoner, JustWatch via TMDB for danske streamingtjenester, seks timers cache. TMDB-logo og kreditering i TV-guidens Om streamingdata og kilder. This product uses the TMDB API but is not endorsed or certified by TMDB.


## v0.1.12 – fælles streamingtjenester for hele familien

**Ændret efter familiebeslutning:** Tjenesteabonnementer er fælles og ikke brugerafhængige. Under **TV-guide → Watchlist → Familiens streamingtjenester** kan alle loggede familiemedlemmer sætte flueben ved de tjenester, husstanden har adgang til. Gemte valg ligger i WordPress `wp_options` under `mp_household_streaming_services_v1`, og vises identisk på alle familiemedlemmers mobil og PC. Ændring fra én bruger slår igennem hos alle ved næste visning af watchlisten.

Har et familiemedlem afkrydset tjenester i v0.1.11, kan disse valg indlæses én gang som husstandens startliste ved første besøg efter opgraderingen, hvis en fælles liste ikke allerede findes. Derefter bruges altid den fælles liste. En bevidst tom fælles liste forbliver tom; den bliver ikke genetableret fra gamle personlige valg.

Automatisk DK streamingkatalogopslag via valgfri gratis TMDB API-nøgle bruger den **fælles husstandsliste** ved enhver oprettelse eller nyt opslag – uanset hvilken bruger der er logget ind. Kun ét sikkert match udfyldes automatisk; tvetydige eller ubekræftede resultater bevarer `Ukendt`. Medlemskaber verificeres ikke, og TMDB alene bekræfter ikke dansk afsnitspremiere.

**Kun farvetemaet er personligt**, gemt separat som `mp_portal_theme` i WordPress user_meta. Opskrifter, watchlist, madplan og streamingtjenester er fælles.

Ingen ekstra abonnementer introduceres. Eksterne streamingopslag virker kun, hvis administrator selv konfigurerer en gratis TMDB API-nøgle.

Regressionstest i `tests/streaming_check.php` efterligner to WordPress-brugere og kontrollerer delte valg, overgang fra gammel indstilling, tom liste, flatrate-vs-leje og TMDB-fallback.

## v0.1.13 – film og filmpremierer

Hangar18 TV-guide understøtter nu både **TV-serier** og **film**. Serier overvåges som før for nye sæsoner og afsnit. Film har en separat fælles watchlist og vises **præcis én gang** på sin premieredato. Begge vises i samme syvdageskalender.

- **TV-guide → Film** viser kommende premierer og film uden dato. Tidligere premieredatoer ligger i et sammenklappeligt historikafsnit indtil fjernelse.
- Filmsøgning benytter TMDB: plakat, titel og international dato. Kræver den eksisterende gratis TMDB API-nøgle, men **manuel oprettelse** virker uden nøgle.
- Vælg **Streaming/digital DK** (standard) eller **Biograf DK**. TMDB-udgivelsestype 4 er digital; type 3 er biograf. Biografdato bruges **aldrig** som streamingdato. Streamingdato kendes ikke: film bliver stående som *Afventer premieredato*.
- Dansk digitaldato bekræfter **ikke** udgivelsestidspunkt på en specifik abonnementstjeneste. En manuelt angivet dato er tydeligt mærket som familieangivet.
- Filmtjeneste kan vælges eller foreslås automatisk, hvis **præcis én** af familiens tjenester matches i TMDB's danske abonnements-/gratis-katalog; ellers Ukendt.
- Filmen vises én gang i ugeplanen på den valgte dato. Efter premieren bliver den på watchlisten som historik, ikke som ugentlig tilbagevendende serie.
- **Tjek premieredato** opdaterer filmdata. Den eksisterende daglige WP-Cron-kørsel undersøger desuden op til otte kommende/udaterede film ad gangen. Familieangivne premieredatoer bevares.
- Film ligger i husstandens delte WordPress-option mp_movies_v1, maks. 150 film, og indgår i Hangar18s databackup.
- Kun administrator kan fjerne en film; alle loggede kan tilføje eller korrigere.
- Ingen nye betalte API-abonnementer.

Datakilder: https://developer.themoviedb.org/docs/search-and-query-for-details og https://developer.themoviedb.org/reference/movie-release-dates.


## v0.1.14 – personlig velkomstoverskrift

Efter login viser familieportalens topbar **Velkommen Maria** i stedet for Hangar18, hvis den aktuelle bruger hedder Maria. **Familieplan** står uændret nedenunder. Fornavnet hentes fra WordPress-brugerens **Fornavn**; hvis dette felt er tomt, bruges første ord af **Vis navn offentligt som**. Hvis intet brugbart navn findes, står der **Velkommen tilbage**. Andre brugeres navne blandes ikke sammen, og login-skærmen hedder fortsat Hangar18.

Da fornavnet allerede vises i topbaren, fjernes det dobbelte visningsnavn til højre; farvetema og Log ud er uændret. Lange fornavne kan ombrydes på mobil uden at ødelægge menuen. Tests: tests/portal_greeting.php.


## v0.1.15 – Tilbud på familiens indkøbsliste

Frivillig gratis, privat ikke-kommerciel integration med [Prej API](https://prej.dk/api). Admin opsætter en Prej Bearer-nøgle under **Hangar18 → Oversigt → Indkøbsliste og tilbudsaviser** eller i `wp-config.php` som `MP_PREJ_API_TOKEN`. WordPress gemmer nøglen server-side (option `mp_prej_api_key_v1`). Ingen ekstra abonnementer eller automatisk køb.

Under **Madplan → Indkøbsliste** findes **Find tilbud** på ukøbte varer og **Find tilbud på indkøbslisten** (maks 10 varer per klik). Resultater viser mulige produktmatches: varenavn, butik, pris i kroner, størrelse/enhedspris hvis registreret, kilde som Avis-tilbud eller Katalogpris (fra Prej) og gyldighedsdato. Købte varer, udløbne og fremtidige tilbud udelades. Resultater er **forslag**, ikke garanti for præcis vare eller pakkestørrelse. Butikspriser kan variere lokalt.

Prej Gratis er 25 opslag per døgn, og integrationen stopper ved 20 udgående opslag per UTC-døgn og cacher forespørgsler i 6 timer på tværs af familien. Ingen scraping, ingen automatisk cron/opslag, ingen vareliste til offentliggørelse. Prej kilder krediteres, se [Prej API-vilkår](https://prej.dk/vilkaar-api). Gratisplanen er kun til **ikke-kommercielle projekter**, uden reklamer og affiliate-indtægter. Tilbudsbilleder/avisfotos kopieres ikke.

REST: `POST /wp-json/hangar18/v1/shopping/offers` med indkøbs-ID'er og login/nonce. Kun eksisterende ukøbte varer kan udløse forespørgsler. Tests: `tests/offers_check.php` og `tests/offers_contract.py`.


## v0.1.16 – foretrukken butik og tilbud på hver vare

Familien kan gemme **en foretrukken butik for hver vare**. For eksempel grupperes *Hakket oksekød* automatisk under Føtex, når dette først er valgt. En ny vare med samme navn får også Føtex. Butiksvalg gemmes i fælles WordPress-option `mp_shopping_favourite_stores_v1`; alle WordPress-abonnenter ser samme gruppering.

Under hver vare findes en butiksvælger og knappen **Se tilbud**. Knappen henter *mulige* aktuelle tilbud hos flere kæder via den eksisterende Prej-integration. Resultatet åbnes lige under varen, grupperet efter butik og med produktnavn, pakning, pris samt udløbsdato. **Valget af foretrukken butik ændres ikke, når man undersøger andre butikkers tilbud.**

Butikken `Tilbud` er en manuel kategori for varer, man ønsker at finde på tilbud; dette udløser ikke automatisk API-kald. `Alle` bruges, før der er valgt butik. Købte varer ligger fortsat i `Købt`, og historik/indkøbslisteindhold slettes ikke ved opgradering.

Eksisterende Prej gratisplan, kvotebegrænsning (20 API-kald per UTC-døgn), cache, kildevisning og validering genbruges. Der tilføjes ikke betalte funktioner. Kataloget kan være ufuldstændigt, og pakkestørrelser skal sammenlignes manuelt. Der er ikke lavet automatisk skift til billigste butik.

Nye API-kald: `GET /shopping/stores`, `POST /shopping/{id}/store`. Eksisterende `POST /shopping/offers` bevares. Den fælles butikspræference inkluderes i WordPress-databackupen. Regressionstest i `tests/shopping_stores.php` og `tests/shopping_store_contract.py`.


## v0.1.17 – Indkøbsliste på forsiden

Familiens forside har nu tre store genveje: **TV-guide**, **Madplan** og **Indkøbsliste**. Det nye indkøbskort (🛒) åbner direkte den allerede eksisterende fælles indkøbsliste, herunder favoritbutikker og Prej-tilbud (hvis API-nøglen er konfigureret). Der oprettes hverken ekstra lister eller nye databasetabeller. Alle loggede brugere, også Abonnenter, har samme funktioner som før. En `#shopping`-dybdelink virkede i forvejen og er bevaret. Overskriften viser `Indkøbsliste` når fanen er aktiv. På desktop vises tre lige store kort side om side, og på mobil ligger de i én kolonne med store klikflader. Integrationstest: `tests/home_shopping_contract.py`.


## v0.1.18 – detaljer pr. indkøbsvare, fælles butiksfiltre og ryd købte varer

**Tryk på Ret ud for en vare** for at åbne et mobilvenligt redigeringsvindue med:
- Varenavn, **butik/kategori** (den eksisterende fælles butiksgruppering), **antal** med minus/plus-knapper, **pakningsstørrelse**, frivillig **pris i kr.**, **noter**, **favorit** og valgfri **stregkode** (EAN/UPC/GTIN).
- Valgfrit varefoto fra mobil/PC (JPG, PNG eller WebP, højst 3 MB). Billedet gemmes i WordPress' uploads-map, så bemærk at mediefiler ikke er indeholdt i Hangar18s særskilte databackup.
- Alle detaljer er delte for alle indloggede brugere, inklusive Abonnenter. Ændringer af varenavn opdaterer eksisterende indkøbspost; købte/ukøbte markeringer bevares.

Over indkøbslisten findes **Butikker til tilbudssøgning** med afkrydsninger. Familien vælger de dagligvarekæder, der skal vises i tilbudsfund; et fælles WordPress-option `mp_shopping_offer_stores_v1` bevarer valget. Standard er alle tilgængelige kæder. En tom valgt liste betyder ingen viste tilbud. Butiksfiltreringen sker efter et enkelt Prej-produktsøgning, så der bruges færre gratis API-kald. Der hentes højst 75 produktmatch pr. vare og vises op til 6 aktuelle resultater fra de valgte kæder; resultatet er derfor ikke et fuldstændigt katalog. Gratis Prej-nøgle er stadig valgfri, og eksisterende kvote og 6-timers cache respekteres.

**Ryd købte varer (N)** fjerner, efter et bekræftelsesspørgsmål, **kun** de indkøbsposter, der er markeret som købt. Det kan udføres af alle indloggede familiemedlemmer. Ukøbte varer bevares, ligesom favorittens butiksindstilling for et varenavn og familiens valgte tilbudsbutikker. Tilknyttede vare-detaljer for de købte poster ryddes. Billeder i WordPress uploads slettes ikke automatisk.

Teknisk: `MP_ShoppingDetails` benytter fælles WP-options `mp_shopping_item_details_v1` pr. vare-ID og `mp_shopping_offer_stores_v1`. Ingen ændringer i eksisterende databasetabeller og ingen datamigration. REST POST `/shopping/{id}/details`, `/shopping/{id}/photo`, `/shopping/offer-stores`, `/shopping/clear-purchased` kræver login og WordPress REST nonce. Options inkluderes i Hangar18s backup. Tester: `tests/shopping_details.php` og `tests/shopping_details_contract.py`.


## v0.1.19 – købshistorik og autofuldførelse på indkøbslisten

Når en vare markeres købt, gemmes varenavnet i familiens fælles `mp_shopping_purchased_history_v1` (maks. 250 unikke varer, uden persondata). Ved første besøg efter opdatering tages eksisterende købte rækker med som historik. Varer, der blev ryddet før opdateringen, kan ikke genskabes.

På **Indkøbsliste → Tilføj vare** vises op til otte forslag fra historikken, når man har skrevet mindst to tegn. Eksempel: `hak` kan give `Hakket oksekød`, hvis dette tidligere blev købt. Forslagene søger både i begyndelsen og inde i varenavnet; præfiks-matches vises først. Vælg med mus, finger eller piletaster og Enter. Escape lukker listen. Intet Prej- eller andet API-kald foretages, når man skriver: forslagene filtreres lokalt, efter én gang at have hentet de fælles data.

Ryd købte varer bevarer fremover varehistorikken og sletter kun de købte rækker fra selve indkøbslisten. Historikken indgår i Hangar18s sikkerhedskopi. Samme historik ses af alle familiemedlemmer uanset WordPress-rolle; indkøbslisten og butikkerne fungerer uændret.

Prej's dækning er ikke komplet. Et tilbud i en butiksavis betyder ikke nødvendigvis, at det kan findes med `/v1/products?q=...`, da Prej ikke kan matche alle kæders avisvarer via stregkode. Vores aktuelle produktsøgning henter op til 75 varer (ikke 12) pr. forespørgsel; `/v1/offers` og `/v1/flyers` er alternative data-endpoints til senere forbedring. <https://prej.dk/api/daekning>.


## v0.1.20 – Udvidet søgning direkte i strukturerede tilbudsaviser

Efter almindelig **Se tilbud** på en indkøbsvare kan familien vælge **Udvid søgning i en tilbudsavis** og en af familiens valgte butikker (f.eks. SuperBrugsen) og trykke **Søg i butikkens tilbudsavis**. Et konkret eksempel er Faxe Kondi, der står på forsiden af SuperBrugsens tilbudsavis 30. oktober–5. november 2026, men ikke altid fanges af produktsøgningen.

Teknik: Den nye WordPress REST-rute `POST /shopping/flyer-search` (login/nonce, samme skriveadgang som indkøbslisten) validerer én aktiv/ukøbt vare og én butik fra den fælles husstandsliste. `MP_OfferFlyers` bruger Prejs dokumenterede `GET /v1/offers?chain=superbrugsen&source=flyer&page=N&limit=100`, fordi `/v1/offers` **ikke** har en fritekst-parameter. Derefter matches varenavnet lokalt mod de tilbud, Prej har struktureret.

Der hentes maks. fire sider á 100 tilbud per brugerhandling og maks. 24 match returneres. Sidesvar caches seks timer per butik og sidetal på tværs af alle familiemedlemmer. **Hvert** API-kald bruger den eksisterende konservative grænse på 20 Prej-kald per UTC-døgn (gratisleverandørgrænsen er 25); ved delvist resultatsæt/kvote vises tydeligt antal gennemgåede tilbud og forbehold. Knappen er manuel og ændrer **ikke** varens foretrukne butik. Ingen baggrundshøst, scraping eller betalt API.

Både `/v1/products` og `/v1/offers` viser de strukturerede produkt- og tilbudsdata, der er tilgængelige. En fysisk avis kan have et opslag som billede, som ikke findes med matchbart varenavn eller slet ikke er struktureret i API'et. Derfor betyder "ingen resultater" ikke "ingen tilbud hos SuperBrugsen". Genkendelsen er tekstbaseret, så mere generiske avisoverskrifter kan mangle. Vi udgiver ikke kopi af hele tilbudsaviser eller avisbilleder.

Dokumentation:
- https://prej.dk/api/dokumentation
- https://prej.dk/api/eksempler
- https://prej.dk/api/daekning

Tests `php tests/flyer_search.php` og `python3 tests/flyer_search_contract.py` med simuleret SuperBrugsen-avis og tre paginerede 100-resultatsider. Integration på webhotel kræver korrekt gemt Prej-nøgle.


## v0.1.21 – familiens aktuelle tilbudsaviser

Under **Indkøbsliste → Aktuelle tilbudsaviser → Hent aktuelle tilbudsaviser** kan alle loggede husstandsmedlemmer nu åbne Prejs aktuelle tilbudsaviser for de **fælles afkrydsede butikker**. Klik **Åbn forside** eller **Se alle sider** for at åbne originalbilleder i en ny browserfane. Der downloades, hostes, kopieres eller OCR-behandles **ikke** reklameavisbilleder på Hangar18s server. Det bruger kun originale billedlinks fra Prej med eksakt tilladt HTTPS-vært. Kildekreditering og gyldighedsdatoer vises.

Serveren henter Prejs `GET /v1/flyers?page=N&limit=100` ved brugerklik, maksimalt to sider (200 aviser), og gemmer de strukturerede svar i WordPress' 6-timers transient-cache. Ved større datamængder står der eksplicit **ufuldstændig liste**. Resultater filtreres server-side efter familiens valgte butikker og datoens gyldighed. En ændring af butiksvalg gælder straks også for cachede data. Det bruger samme maksimum 20 API-kald/UTC-dag som de øvrige tilbudsfunktioner.

**Hvorfor ikke OCR på alle sider?** Prej leverer avisers billedlinks, ikke søgbar fuldtekst. Gratis API-planen understøtter privat, ikke-kommerciel brug, men giver ikke selv billedlicens fra butikkerne; automatisk kopiering af avisbilleder og systematisk billed-OCR er derfor ikke aktiveret uden rettighedsgrundlag. Fritekstsøgning fortsætter kun på tilbudsdata fra /v1/products og /v1/offers, og kan mangle varer på billedsiderne. Avisoversigten gør det muligt manuelt at åbne eksempelvis SuperBrugsens forside og tjekke Faxe Kondi, når det strukturerede opslag ikke finder produktet.

Kilder: https://prej.dk/api/eksempler, https://prej.dk/api/dokumentation og https://prej.dk/vilkaar-api. Test: `tests/flyer_library.php` og `tests/flyer_library_contract.py`.


## v0.1.22 – OCR-pilot på én avisside, uden ekstra abonnement

**Indkøbsliste → OCR-test af én avisside (administrator):** Upload en JPG/PNG/WebP-side (maks. 6 MB/36 MP) fra en avis, som du har lovligt adgang til at behandle, vælg én af familiens afkrydsede kæder og angiv tilbudsavisens slutdato. Serveren prøver gratis Tesseract OCR med `dan+eng`, derefter `eng`. Det kræver Tesseract installeret og tilladelse til PHP `proc_open` på webhotellet. **Vi har ikke verificeret, om webhotellet understøtter dette.** Statusvisning i portalen siger om motoren findes. Administrator kan sætte `MP_TESSERACT_PATH` i wp-config.php.

Hvis Tesseract ikke er tilgængelig på webhotellet, kan man indsætte OCR-tekst fra lokal PC i **Alternativ: indsæt OCR-tekst fra din PC**. Begge veje laver samme simple automatiske pris-/vareudtræk, hvor fx
```
Faxe Kondi
24 x 33 cl
65,-
```
bliver en **mulig**, ikke verificeret, prislinje **Faxe Kondi — 65,00 kr**. Da layout, størrelser og pris ikke altid hænger sammen i OCR, skal forslagene altid kontrolleres mod kilden.

**Dataopbevaring:** Et billede behandles fra PHP's midlertidige uploadfil og bliver aldrig gemt i Mediebiblioteket eller WordPress' database. Den komplette OCR-tekst gemmes heller ikke. Der gemmes op til 300 unikke korte navne/prisforslag med butik og udløbsdato i fælles `mp_grocery_ocr_candidates_v1`; udløbne resultater vises ikke. Alle familiemedlemmer kan se **Mulige OCR-fund (ikke verificeret)** ved Se tilbud for en vare, uden ekstra Prej-kald. Kun WordPress-administratorer må uploade billeder eller importere tekst; GET-søgninger er kun for indloggede brugere. Index medtages i Hangar18s eksisterende backup.

**Rettighedsgrænse:** Funktionen henter **ikke** automatisk Prej-billedsider, og behandler ikke uden tilladelse hele kæders tilbudsavisarkiver. Private, ikke-kommercielle projekter kan benytte Prejs gratis API-plan, men Prejs TDM-forbehold og tredjeparts billedrettigheder skal respekteres. Før en automatisk helavis-scanner aktiveres, skal brugsretten afklares med relevant kilde. Se https://prej.dk/vilkaar-api og https://prej.dk/vilkaar.

Teknik: `MP_FlyerOCR`, REST `GET /shopping/ocr/status`, `GET /shopping/ocr/matches?id=`, admin-only `POST /shopping/ocr/upload`, admin-only `POST /shopping/ocr/import-text`, admin-only `DELETE /shopping/ocr/clear`. Status kræver ingen Prej-nøgle; OCR bruger kun lokale værktøjer. Tests `php tests/flyer_ocr.php`, `python3 tests/flyer_ocr_contract.py`.


## v0.1.23 – frivillig daglig tilbudsopdatering og fejlfikset SuperBrugsen-visning

Under **Indkøbsliste → Dagligt tilbudsindeks** kan en WordPress-administrator aktivere en fælles automatisk daglig scanning. Den er **slået fra som standard** og kræver den allerede konfigurerede Prej API-nøgle. WP-Cron læser **Prejs strukturerede tilbud** fra familievalgte butikker, tidligst kl. 05:30 lokal tid efter Prejs natlige dataopdatering. Én WordPress-Cron-batch kan lave højst ét API-kald, og automatisk scanning er begrænset til otte kald pr. UTC-døgn. Alle kald trækkes fra den almindelige konservative grænse på **20 kald pr. dag**, der også deles med almindelige tilbudssøgninger.

Søgningen gennemgår op til **to resultatsider á 100 tilbud per valgt butik**, indtil den daglige kvote er brugt. **Der er ikke fuld katalogdækning** ved flere butikker eller mange produkter: status viser **ufuldstændigt** og hvor mange butikker der faktisk er kontrolleret. Der gemmes højst 100 små vare/pris-resultater, og **kun** produkter, der matcher familiens aktive/ukøbte indkøbsvarer. Dagsindekset i WordPress er datomærket og bruges aldrig som dagens kilde efter midnat, før en frisk kørsel er gennemført. Ved “Se tilbud” vises eventuelle **Dagens gemte tilbud** sammen med de normale direkte søgninger.

**Vigtigt:** Dette er en automatisering af **strukturerede tilbud via det officielle Prej API**, ikke en automatisk OCR-læsning af alle avissidebilleder. Automatisk hentning/billed-OCR på tredjeparts avisbilleder kræver separat brugsret og OCR-driftsmiljø. Den manuelle lokale Tesseract OCR-pilot fra v0.1.22 er uændret.

Der er samtidig rettet en konkret UI-fejl: beskeden **“Ingen tekstmatch i de gennemgåede tilbud”** vises ikke længere dobbelt ved resultatløs udvidet SuperBrugsen-søgning.

WP-Cron afhænger af besøg på webstedet og garanterer ikke et præcist klokkeslæt. For stabil daglig opdatering kræves server-cron, som regel mod WordPress' wp-cron.php. **Ingen automatisk opgradering til betalt Prej-plan.**

Kilder: https://prej.dk/api , https://prej.dk/vilkaar-api . Tests: `tests/daily_offers.php`, `tests/daily_offers_contract.py`.


## v0.1.24 – opt-in daglig OCR-kø for kilder med billedrettigheder

Denne version tilføjer også **Indkøbsliste → Daglig OCR af tilbudsaviser**. Administratoren kan vælge **Aktiver daglig OCR** **kun**, efter afkrydsning af “Jeg har indhentet de nødvendige rettigheder til automatiseret OCR af de valgte avisbilleder”, og kun hvis webhotellet har en fungerende **Tesseract**-installation. Aktiveringen er **slået fra som standard**. En privat hjemmeside og en Prej API-nøgle er ikke i sig selv en billedlicens; tilladelsen skal dække systematisk automatiseret OCR af tredjeparts avisbilleder. Prejs udtrykkelige TDM-forbehold og billedrettigheder gælder fortsat. Vi har ikke bekræftet dine rettigheder eller dit webhotels Tesseract-installation.

Når admin har aktiveret funktionen, henter den en begrænset **aktuel** avisoversigt via Prejs officielle API. Hver avis får maksimalt 24 sider i OCR-køen. Der oprettes maksimalt **48 sider per kalenderdag** i køen, fordelt round-robin mellem familiens valgte butikker; ved flere sider markeres status **ufuldstændig**. WordPress-Cron behandler **én side pr. 15-minutterskørsel** og starter ikke før **kl. 06 dansk tid**. Billeder hentes kun fra den eksakte tilladte `img.prej.app/flyers/...`-adresse uden redirects, maks. 6 MB, og behandles som midlertidig fil, der slettes umiddelbart efter OCR. Der gemmes ikke avisbilleder eller rå OCR-tekst.

Det daglige OCR-indeks gemmer op til 300 **uvaliderede produkt-/prisforslag** med butik og gyldighedsperiode; disse vises under **Se tilbud** ved indkøbsvarerne. Hele indekset er datomærket og bliver usynligt næste dag, indtil dagens kørsel er i gang. Det er **ikke** en komplet kopi af alle reklameaviser og er ikke garanteret at finde alle tilbud; lange kataloger, kvotegrænser, hostingtidsgrænser og OCR-fejl giver eksplicit delvis dækning. Kørslen er trafikstyret af WP-Cron; et rigtigt server-cronjob er nødvendigt for stabil kørsel på et site med få besøg.

**Kilde/ret:** API-vilkår https://prej.dk/vilkaar-api og Prej brugsregler https://prej.dk/vilkaar. Funktionen må kun aktiveres, når du har det nødvendige grundlag til billedbehandlingen. Ingen ekstra betalte API-kald eller AI-abonnementer, kun lokal Tesseract. Tests: `tests/daily_ocr.php` og `tests/daily_ocr_contract.py`.


## v0.1.28 – præcise pris- og kildeetiketter

Tilbudssøgningen skelner nu mellem **Katalogpris · Prej** (pris fra kædens produkt-/priskatalog) og **Avis-tilbud · Prej** (struktureret tilbud registreret med kilde `flyer`). Butikken vises fortsat som overskrift. Gyldighedsdato vises i dansk format, eksempelvis **Gælder til 05.11.2026**, eller som periode når både start- og slutdato findes. Manglende slutdato vises tydeligt. Den samme datovisning bruges ved daglige gemte fund og OCR-fund. Tidligere cachede source-navne konverteres ved visning. Et konkret fysisk avisnummer eller avisnavn kan ikke udledes af de aktuelle Prej-produktoplysninger og opfindes derfor ikke. Ingen ændringer i scanner, gratis API-kvote eller indkøbsdata.

## v0.1.27 – rettelse til indlæsning af Safari-scanner

Fjerner en ikke-verificeret Subresource Integrity-værdi, der kunne blokere indlæsningen af Safari-scannerens JavaScript. Biblioteket hentes fortsat over HTTPS fra en versionsfast adresse (`html5-qrcode@2.3.8`), kun når scanning startes. Når en officiel checksum er verificeret, kan SRI genindføres. Samsung/Android-koden er uændret.

## v0.1.26 – iPhone/Safari kamera-fallback

Safari på iPhone har som standard ikke `BarcodeDetector` aktiveret. Den tidligere scanner standsede derfor, selv om iPhone havde et fungerende kamera. Nu bruges fortsat den oprindelige hurtige `BarcodeDetector` på understøttede telefoner (bl.a. Samsung), mens iPhone/Safari automatisk får en JavaScript-baseret EAN/UPC-scanner via `html5-qrcode` 2.3.8 (Apache-2.0), indlæst efter tryk på **Start kamera**. Biblioteket hentes fra jsDelivr med fast version; forbindelsen kræver internet og må ikke blokeres af Content-Security-Policy.

Brugeren bliver spurgt af browseren om kameraadgang; webstedet kan ikke selv tvinge Safari til at huske tilladelsen. Kamera-video behandles lokalt i browseren. Kun valideret GTIN sendes til WordPress, og scanneren stoppes, når dialogen lukkes eller en vare genkendes. Manuel indtastning er stadig mulig.

## v0.1.25 – scan stregkode fra mobilkameraet

Under **Indkøbsliste → Scan stregkode** kan familien scanne EAN/UPC på emballagen. I understøttede mobilbrowsere (bl.a. Chrome på nyere Android-telefoner med HTTPS) åbnes bagkameraet via `getUserMedia`, og den indbyggede `BarcodeDetector` læser EAN-8, EAN-13 eller UPC. Ingen kamerabilleder sendes til WordPress eller en ekstern server. Browseren beder om tilladelse til kamera. Alle browsere får også **manuel stregkodeindtastning** (8, 12, 13 eller 14 cifre med GS1-kontrolciffer). Firefox understøtter typisk ikke `BarcodeDetector`; dér bruges manuelt nummer.

Opslag sker server-side i denne rækkefølge: (1) familiens private `mp_shopping_barcode_catalog_v1` i WordPress, (2) Prejs dokumenterede `GET /v1/scan/{gtin}` med **den eksisterende nøgle og samme gratis fælles API-kvote**, (3) det gratis Open Food Facts-produktregister. Prej-resultater caches 30 dage ved fund, ukendte i 12 timer; alle oplysninger mærkes med kilde. Open Food Facts' data krediteres. Ingen betalt API eller ny nøgle.

**Kendt vare tilføjes automatisk** som en almindelig ukøbt vare med varenavn, eventuel pakningsstørrelse og gemt stregkode. Hvis samme stregkode allerede står ukøbt på familiens liste, vises "allerede på listen" i stedet for en dublet. Hvis et produkt ikke findes, får brugeren formularen **Navngiv varen én gang**; herefter genkendes stregkoden af hele familien, også efter at købte varer ryddes. Varenavn og stregkode kan redigeres under **Ret**. Brugere med WordPress-rollen Abonnent kan scanne og tilføje på lige fod med øvrige familiemedlemmer.

**Sikkerhed:** WordPress-login og REST-nonce kræves ved både opslag og tilføjelse. Serveren validerer GTIN-kontrolcifferet; kun den godkendte Prej- og Open Food Facts-adresse kontaktes. Kamera stoppes efter scanning, ved lukning og ved navigation; der gemmes ingen kamerabilleder. Den fælles stregkodeliste indgår i Hangar18s eksisterende checkpoints.

**Eksisterende automatisk OCR** af aktuelle tilbudsaviser fra v0.1.24 er bevaret og har sin egen opt-in, daglige kvote, kontrol af Tesseract og rettighedsbekræftelse. Stregkoder bruger ikke OCR-køen.

Kilder: https://prej.dk/api/dokumentation , https://openfoodfacts.github.io/documentation/docs/Product-Opener/v2/products/get-product-by-code/ , https://developer.mozilla.org/en-US/docs/Web/API/Barcode_Detection_API .
