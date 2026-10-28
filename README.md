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
