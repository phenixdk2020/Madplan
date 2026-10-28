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
