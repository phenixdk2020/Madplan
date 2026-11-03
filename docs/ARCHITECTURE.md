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


## 0.1.9 – activation lifecycle ved pluginopdatering
- upgrader_pre_install priority 4 tager snapshot af oprindelig aktiv/netværksaktiv status.
- MP_Checkpoints::pre_install priority 5 opretter program- og datacheckpoint før WP core priority 10 deaktiverer.
- upgrader_post_install priority 20 genaktiverer stille, hvis WordPress havde deaktiveret et aktivt plugin.
- MP_Updater::install_now udfører desuden genaktiveringskontrol på både succes og fejl; redirect til Plugins ved genaktiveringsfejl.
- Der foretages ikke automatisk aktivering af plugins, der var inaktive.
- WP core's Plugin_Upgrader::deactivate_plugin_before_upgrade() står for deaktiveringen, ikke vores egen kode.
- Isolerede PHP-tests i tests/updater_reactivation.php.


## v0.1.10 – regionale streamingdatoer
- Datoprioritet: aktuel sæsons familievalgte danske første dato → dokumenteret dansk udgivelseskalender → TVmaze originaldato.
- Opt-in til ugentlig fremskrivning; kun særskilt bekræftede episodedatoer mærkes dk_confirmed.
- Option mp_dk_schedules_v1 pr. lokalt show-id med season, first_date, weekly, changed_by, changed_at. Der ændres ikke i WP databasetabeller.
- Sæsonovervågning bevares. Manuel DK-plan gælder kun den sæson, den blev angivet for.
- REST POST /shows/{id}/dk-schedule med WordPress REST nonce og can_write.
- En kurateret kilde til Marshals season 2 dokumenterer 9 og 30 oktober 2026 på danske SkyShowtime. Øvrige fredage er estimater.
- Tests: tests/dk_schedule.php og tv_portal_contract.py.


## v0.1.11 – optional user-specific streaming services
- Per-user subscriptions in mp_streaming_subscriptions_v1 user_meta; GET/POST streaming-services, GET streaming-lookup and POST shows/{id}/streaming-check.
- Optional admin API credential in wp_option mp_tmdb_token_v1 (autoload false) or wp-config MP_TMDB_API_TOKEN; never exposed in REST or client JS.
- TMDB search/tv maps an exact show name and premiere year; ambiguous titles rejected. tv/{id}/watch/providers uses region DK only and compares flatrate/free/ads with user subscriptions; rent/buy excluded.
- Only one matched subscription is automatically saved when no streaming service is chosen; otherwise remains Ukendt. Manually pinned provider is never overwritten.
- TMDB response transient cache six hours; fallback doesn't prevent adding/updating watchlist.
- Tests in streaming_check.php and streaming_contract.py.


## v0.1.12 – delt husholdningsindstilling
- MP_Streaming::chosen() henter den samme globale wp_option mp_household_streaming_services_v1 for alle brugere.
- MP_Streaming::save_shared() opdaterer den globale liste efter whitelistvalidering.
- Valgfri migration fra det aktuelle login's gamle user_meta mp_streaming_subscriptions_v1 foretages højst én gang, hvis global option mangler og en bruger allerede har data. En gemt tom global liste nulstilles ikke.
- REST /streaming-services GET/POST kræver WordPress-login og nonce for POST; ingen bruger-specifik præferencelagring.
- Auto-opslag, manuelt Tjek familiens tjenester og nye serier bruger samme globale liste.
- Farvetema forbliver WordPress user_meta og påvirkes ikke.

## v0.1.13 – film
- MP_Movies: delt WordPress-option mp_movies_v1 med TMDB metadata, mode streaming/cinema, manual_date, last_checked og service.
- API GET /movies, GET /movies/search, POST /movies, POST /movies/{id}, POST /movies/{id}/refresh og DELETE /movies/{id} (admin).
- TMDB release type 3 theatrical vs 4 digital DK; ingen fallback fra biograf til streaming.
- GET /week sammenlægger TV-afsnit med film, hvor kind=movie. Film vises én gang pr. premieredato.
- WP-Cron mp_sync_tv kører både MP_TVmaze::sync_all og MP_Movies::refresh_pending.
- Databackup inkluderer mp_movies_v1 samt fælles streaming- og TV-statusoptioner.

## v0.1.14 – velkomst

Portalheaderen bruger WordPress' wp_get_current_user()->first_name. Hvis tomt, første ord af display_name (ikke e-mail). Mangler begge, vises Velkommen tilbage. Escape med esc_html. Ingen lagring/cache af personnavne. Loginheaderen forbliver Hangar18. Test: tests/portal_greeting.php.

## v0.1.15 – Prej-tilbud
MP_Offers: wp_option mp_prej_api_key_v1, valgfri wp-config-konstant MP_PREJ_API_TOKEN; server-side kvote 20 kald per UTC-dag (mp_prej_usage_v1), max 10 ukøbte eksisterende indkøbsvarer per REST POST /shopping/offers, 6 timers delt transient-cache pr. søgeord. API GET /v1/products?q=...&on_offer=1&exclude_border_trade=1. Filtrér kun prices[].is_offer og gældende datoer. Ingen baggrundsscraping, køb eller billeder. API-nøgle server-side, intet bruger-specifikt (familieliste).


## v0.1.16 – butikshukommelse
- MP_ShoppingStores::preferred($title) bruger fælles wp_option mp_shopping_favourite_stores_v1 og sanitize_title($title) som stabil nøgle.
- POST /shopping/{id}/store gemmer butik for alle familiens brugere. Alle kan skrive, da rettigheden er is_user_logged_in().
- GET /shopping udvides med store, men beholder id, title, amount og checked.
- UI grupperer Købt nederst, Tilbud øverst og øvrige efter butik. Hver ukøbt vare kan søges via eksisterende Prej REST POST /shopping/offers med præcis ét vare-id.
- Ingen automatisk tilbudssøgning ved åbning/tilføjelse, og ingen automatisk flytning af varer efter tilbud. Prej API-kvote deles uændret.
- Backup gemmer præference-optionen. Ingen database-migrering.


## v0.1.17 – forsidegenvej til indkøb

MP_Portal::render() udstiller data-module=shopping på tredje hjemmekort. Klientens eksisterende module()-router oversætter shopping → food/shopping og bruger samme shopping()-renderer og eksisterende REST/endpoints, data og brugerrettigheder; ingen datamigration. setTab() sætter dynamisk overskrift efter aktiv fane. CSS grid er tre kolonner desktop (>=740px) og én kolonne mobil (<740px). Dybdelink #shopping genbruges.

## v0.1.18 – indkøbsvare-detaljer og bulk oprydning
- MP_ShoppingDetails gemmer validerede vare-detaljer pr. ID i option mp_shopping_item_details_v1; butik/kategori gemmes fortsat via MP_ShoppingStores på varenavn.
- Shared valgt Prej-butiksfilter i mp_shopping_offer_stores_v1; listen gemmes én gang for husstanden; tilbud filtreres på butik efter produktsøgningen.
- De eksisterende shopping-tabeller ændres ikke. REST /shopping returnerer details pr. vare.
- POST /shopping/{id}/details opdaterer varenavn, butik, antal (1–999), mængde, pris, note, favorit og stregkode. Varefoto kan uploades særskilt gennem POST /shopping/{id}/photo (3 MB/JPEG, PNG, WebP, login, nonce).
- POST /shopping/clear-purchased sletter udelukkende rækker med checked=1 med betinget WHERE og rydder kun disse varers option-detaljer; ingen slettede foretrukne butikker eller abonnementer. UI kræver window.confirm. Samme rettighed som skrivning af indkøbslisten (inklusive abonnent).
- Dataoptions medtages i databackup. Varefotos i uploads bør inkluderes i den normale WordPress-mediebackup.
- Regression: PHP mock-database med købte og ukøbte varer, validering, bruger-skifte, butiksfilter og gentagen rydning.


## v0.1.19 – historik til indkøbsautofuldførelse
- MP_ShoppingHistory WordPress option mp_shopping_purchased_history_v1: højst 250 titler, nøgle i små bogstaver, senest købt ISO-UTC, uden person-ID.
- Første brug: importer kun checked=1-rækker fra wp_mp_shopping; tidligere ryddede køb kan ikke rekonstrueres.
- MP_REST::toggle_shopping gemmer titlen efter succesfuld markering som købt; clear_purchased gemmer varenavnet efter vellykket sletning. Fælles WordPress-abonnenter kan skrive.
- GET /shopping/history kun for indloggede; frontend downloader listen én gang ved åbning og udfører client-side filter uden API-kald per tastetryk.
- Semantisk combobox/listbox, maks otte forslag, både touch og tastatur. Samme aktive indkøbsliste og butiksvalg. Option sikkerhedskopieres.


## v0.1.20 – Prej udvidet tilbudsavis
- Skel mellem almindelig produktliste `/v1/products?q=...` og tilbudsavisens strukturerede tilbud `/v1/offers?chain=...&source=flyer`. Sidstnævnte har ikke q-filter.
- GET /v1/offers: maks. 4×100 resultater på brugerklik, hver side cached 6 timer per chain slug; REST POST /shopping/flyer-search kræver signed-in og nonce, eksisterende ukøbt vare og én af husstandens valgte kæder.
- Genbrug MP_Offers::reserve_flyer_call() og 20-kaldsgrænse per UTC-døgn; ingen automatisk kø, ændring af foretrukne butik eller scraping.
- Match hvert relevant søgeord (Unicode-case-insensitive) mod tilbuddets titel og returnér maks. 24 aktuelle matches. Skeln mellem komplet og delvist gennemgået, vis tilbudspriser i øre og eventuelle multikøb separat.


## v0.1.21 – flyer library (metadata/links only)
- MP_FlyerLibrary GET /v1/flyers, at most 2×100 offers per manual user action and 6hr global transient cache; quota shared with products/offers.
- Only selected household chain slugs, active date range, img.prej.app HTTPS original page_urls under /flyers/... accepted and exposed. Never fetch, proxy, mirror, OCR or store third-party images.
- GET /shopping/flyers is login-protected read endpoint. Client renders link to first page or all pages in new tabs. Partial listings reported.


## v0.1.22 – OCR pilot
- Local Tesseract via proc_open array args (no shell), detects binary, max image 6MB / 36MP, only uploaded image, MIME/image validation. Optional MP_TESSERACT_PATH, dan+eng fallback to eng. If server disallows proc_open, manual OCR text import. No automatic download of flyer page images or external OCR API.
- Scope: manual one-page test, admin-only upload/import/clear and WordPress REST nonce; all logged-in household members can match against shared option mp_grocery_ocr_candidates_v1. File stays in PHP temporary upload only and text gets discarded after extraction; max 300 short candidate records; expiry and store filters.
- OCR candidates are unverified; product-price pairing from visually laid out ads can be mistaken. A shopping item may have OCR suggestions in addition to Prej data, shown with clear provenance. No paid dependencies and no extra Prej calls while searching index.


## v0.1.23 – daily structured-offer snapshot
- MP_DailyOffers settings `mp_daily_offer_settings_v1`: admin opt-in disabled by default.
- Cron `mp_daily_offer_tick` each 15 min, but no API calls before 05:30 local. One provider call per tick; at most eight per day in the daily job; shared `MP_Offers::reserve_flyer_call()` protects the overall 20-call local cap and free 25-call Prej plan.
- Per-day option `mp_daily_offer_state_v1`: selection signature, progress cursor, page, calls, matched offers (max 100), checked stores, partial coverage and error. Index hidden on dates after the saved day. Maximum 2 API pages per chain, 100 offers per page; no bulk dataset copy.
- Server matches only currently unpurchased shopping product titles. REST GET /shopping/daily/status, GET /shopping/daily/matches, admin POST /shopping/daily/settings, admin POST /shopping/daily/run. WordPress permission callback and nonce as existing.
- On cron-disabled hosts use an actual server cron; WP-Cron is traffic-triggered. No automatic flyer image retrieval/OCR pending rights clearance and host capability.
- Duplicate missing-offer notification in JS removed.
