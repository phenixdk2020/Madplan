# Hangar18 Madplan & TV-plan

WordPress-plugin med mobil-first familieportal og desktop-orienteret opskriftseditor.
Repository: https://github.com/phenixdk2020/Madplan

## Version 0.1.2 — første testversion

- WordPress-login med Husk mig, ingen egen passwordopbevaring.
- Én fælles TV-ugeplan/watchlist og én madplan for alle loggede brugere.
- På stående telefon: i dag og de næste to dage; på liggende telefon: hele ugen i 7 kolonner.
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

Alle loggede brugere kan se fælles TV-plan, madplan og opskrifter samt tilføje/afkrydse varer. Administratorer kan redigere madplaner, watchlist og opskrifter. WordPress står for adgangskoder og login-session. HTTPS og bypass af offentlig side-cache er påkrævet.

## TV-datoer

TVmaze leverer oprindelige/internationale udsendelsesdatoer, ikke verificerede danske streamingpremierer. Streamingudbyder og dansk klokkeslæt skal angives manuelt. Hvis sæsonen har 8 afsnit og første dato er kendt, kan systemet beregne 8 forventede ugentlige datoer. Beregninger mærkes Forventet, og pauser eller batch-udgivelser kan gøre dem unøjagtige.

TVmaze: https://www.tvmaze.com/api
TV-opdateringer bruger daglig WP-Cron, som kræver sitebesøg eller serverbaseret cron.

## Ikke færdigt i 0.1.2

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

**Bootstrap:** Hvis 0.1.0 er installeret, kræver overgangen til 0.1.2 en sidste manuel ZIP-installation. Alle efterfølgende publicerede versioner kan opdateres via Hangar18 → Opdateringer. Udviklingsversioner fra GitHub Actions er ikke automatisk en del af update-kanalen, før GitHub Actions har publiceret `update.json` og `dist/` på `main`.


## 0.1.2 – forside, TV-guide og opdateringsdiagnose

**Vigtigt ved eksisterende installation:** Første besøg efter installation/opgradering til 0.1.2 finder/opretter siden Familieplan og gør den til **statisk forside**. Den tidligere indstilling for blog/statisk forside gemmes til senere gendannelse. Andre eksisterende sider/indlæg slettes ikke. Hvis /familieplan/ allerede eksisterer med andet indhold, oprettes i stedet en separat side under /hangar18-familieplan/. 

Den nye forsideskabelon viser kun Hangar18-appen (ikke WordPress-temaets header, men med WordPress' scripts og hooks). Start på https://hangar18.dk/ og log ind med din almindelige WordPress-bruger.

**Direkte navigation:** I WordPress-menuen findes nu Hangar18 → Oversigt, TV-guide, Madplan, Opskrifter, Opdateringer. Links:
- / – fælles loginportal og to modulvalg.
- /#tv – TV-ugeplan.
- /#tv-watch – den fælles watchlist, hvor administrator tilføjer serier (TVmaze).
- /#food – madplanen.
- /#shopping – indkøbslisten.
- /#recipes – opskrifter til mobilvisning.

TV-ugen er **tom**, indtil der er tilføjet en serie i watchlisten. TVmaze-datoer er oprindelige/internationale, ikke bekræftede danske premieredatoer eller klokkeslæt.

**Opdateringer:** Version 0.1.2 inkluderer WordPress' native Update URI-hook, opdateringsknap samt "Test backup og rettigheder" under Hangar18 → Opdateringer. Resultater og fejlmeddelelser vises på siden. Hvis hosting blokerer private backups eller GitHub-HTTPS, kan WordPress-opdateringen ikke gennemføres. Manuel ZIP-installation/erstatning er en engangs-fallback for overgang fra 0.1.1.

**Gendan tidligere forside:** Hangar18 → Oversigt → Gendan tidligere WordPress-forside. Opskrifter og madplan-data påvirkes ikke.
