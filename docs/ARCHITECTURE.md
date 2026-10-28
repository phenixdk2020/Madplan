# Arkitektur

Plugin: hangar18-madplan.php.
Klasser:
- MP_Storage: databaseinstallation, måltider, historik/audit og opskrifter
- MP_REST: validerede JSON endpoints og WordPress capabilities
- MP_TVmaze: søgning, original/international afsnitsdata og mærkede ugefremskrivninger
- MP_Portal: WordPress-login, shortcode og scripts

WordPress-tabeller:
- wp_mp_meals: én række pr dato+person, med own/same og personhenvisning
- wp_mp_shows: fælles watchlist og sæson-/udgivelsesregler
- wp_mp_episodes: afsnitsoplysninger fra TVmaze
- wp_mp_shopping: fælles manuelle varer
- wp_mp_audit: måltidsændringer med tidligere og ny værdi

Tabeller bruger reelt site-prefix, ikke et fast wp_-prefix.
Opskrifter er WordPress custom post type mp_recipe med revisionshistorik, WordPress Media Library og post_meta for ingredienser, galleri, minutter og portioner.
Billeder til enkelte trin indsættes via WordPress editor; gallery er en separat billedliste.

REST endpoints (alle under /wp-json/hangar18/v1):
- GET week?start=YYYY-MM-DD
- POST meal
- GET/POST shows
- DELETE shows/{id}
- POST shows/{id}/sync
- GET tv-search?q=...
- GET recipes
- GET recipe/{id}
- GET/POST shopping
- POST/DELETE shopping/{id}

Alle læseendpoints kræver login. Alle loggede brugere kan redigere den fælles madplan, tilføje TV-serier samt tilføje/afkrydse varer. Kun administratoren kan slette serier eller oprette avancerede opskrifter.

## Sikkerhed og QA

- Server-side validering og output escaping
- REST nonces for ændringer; WordPress-session frem for adgangskoder i lokal browserlagring
- Ved deaktivering slettes ingen data
- WP-Cron kan forsinkes på sites uden trafik
- TVmaze er ikke en autoritativ kilde til dansk streamingtid eller danske premieredatoer

Inden produktion: WordPress staging, PHP-lint, gæsteadgang til REST, test af person-cyklus, årsskifte/uge 53, portrait/landscape, TVmaze timeout, serie med batch-udgivelse, mediegalleri samt backup og genaktivering.

Senere: automatiske indkøb fra opskrifter, tilbud, dansk premieredatakilde, skema-migrationer, sikker GitHub-updater og rollback.


## 0.1.3: Mobil-redigering og adgang
- REST can_read/can_write: loggede WordPress-brugere.
- REST can_manage: WordPress-administrator.
- POST meal, POST shows og tv-search for loggede brugere. DELETE shows og DELETE shopping kun admin.
- Frontend canEdit for redigering og admin til sletning/WordPress-opskrifter.
- "+ Tilføj TV-program" på TV-ugeplanen går til watchlist-søgning.
- "+ Tilføj aftensmad" åbner dagen med fire personer.
- QA: test både admin og subscriber med login, REST nonce, add show, add meal og denied DELETE.


## 0.1.5 – TVmaze automatisk udfyldning
- Endpoint GET /tv-details?id=N (kræver WordPress-login, nonce i portalens fetch).
- TVmaze: shows/{id}, shows/{id}/seasons (episodeOrder, premiereDate), shows/{id}/episodes (season og airdate).
- Cache i seks timer. Ukendt antal afsnit markeres 0 frem for at gætte.
- UI: navn/søgning → vælg resultat → streamingtjeneste. Avancerede felter foldes ud efter behov.
- Fra webChannel foreslås original streamingudbyder, men aldrig dansk rettighed/udgivelsestid.
- Ugentlig forventet rytme kun hvis to efterfølgende kendte afsnit har præcis 7 dages mellemrum.
- Tilbage til forsiden er en ren JavaScript-overgang til de to menuknapper; session forbliver aktiv.
- Tests: tests/tv_auto.php (falske TVmaze JSON-svar), tests/tv_portal_contract.py og eksisterende updater_contract.py.


## 0.1.6 – kalender-layout
- REST week endpoint henter præcis mandag–søndag (7 dage).
- Frontend gengiver altid syv kort. Ingen skjulte dage ved stående mobil.
- Portrait: én kolonne og scroll. Landscape (fra 520px) og desktop (fra 960px): syv kolonner.
- Dagens kort markeres med mp-today; uge-navigationen er uændret.


## 0.1.7: brugerdefinerede temaer
- User meta mp_portal_theme, en række per WordPress-bruger, ingen ny databasetabel.
- Default er den tidligere blå palette; øvrige temaer dark, forest, purple og sand.
- Server-side aktivering ved render af portalens data-attribut og body_class; REST POST /theme.
- Inputvalidering mod statisk whitelist; WordPress REST nonce og login.
- Optimistisk JS-opdatering rulles tilbage ved fejl. Ingen lagring i localStorage.
- Tests: PHP user-meta isolering samt statisk integritetskontrol af frontend og tema-CSS.


## 0.1.8 – serieovervågning
- Watchlist tabellen bevares uændret; watchlist poster forbliver active=1, også efter sidste afsnit.
- MP_TVmaze::sync_one henter både /shows/{id}/episodes og /shows/{id}/seasons og sammenholder dem.
- Hvis ny sæson opdages, opdateres season, antal episoder og original first_air_date, og nye afsnit gemmes i eksisterende tabel. Ældre episoders data forbliver, men ugeplanen viser den nu fulgte sæson.
- Overvågningsmetadata i WP option mp_watch_monitor_v1 pr. lokal show-id: state, original provider/kind, første og næste dato, tjekdato. Ingen ekstra migrering.
- Status: monitoring, awaiting_next_season, upcoming eller new_season. Ukendt samlet afsnitsantal kan ikke markeres som afsluttet uden bekræftet slutdato.
- Service-feltet er manuelt valgt DK-tjeneste; provider i monitorering er TVmaze-original og ikke bekræftet dansk.
- Sæsonmetadata kan mangle eller fejle; serien må ikke fjernes eller afbrydes.
- REST /shows returnerer monitor/status; /shows/{id}/sync returnerer new_season og state.
- Der er ingen push-alarm; UI opdateres ved åbning og næste synkronisering.
