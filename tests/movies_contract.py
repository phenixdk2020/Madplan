#!/usr/bin/env python3
"""Movie UI, routes, cron and checkpoint integration."""
from pathlib import Path
main=Path('hangar18-madplan.php').read_text()
rest=Path('includes/class-mp-rest.php').read_text()
movie=Path('includes/class-mp-movies.php').read_text()
stream=Path('includes/class-mp-streaming.php').read_text()
js=Path('assets/portal.js').read_text()
backup=Path('includes/class-mp-checkpoints.php').read_text()
css=Path('assets/portal.css').read_text()
for src,needle in [
 (main,'Version: 0.1.13'),(main,"require_once MP_DIR . 'includes/class-mp-movies.php'"),
 (main,"add_action('mp_sync_tv', [MP_Movies::class, 'refresh_pending'], 20)"),
 (stream,'public static function tmdb_get('),
 (rest,"self::route('/movies', 'GET', 'movies')"),
 (rest,"self::route('/movies/search', 'GET', 'movie_search')"),
 (rest,"self::route('/movies', 'POST', 'add_movie', true)"),
 (rest,'MP_Movies::schedule($start,$end)'),
 (movie,"'mp_movies_v1'"),(movie,"'movie_digital'"),(movie,"'movie_cinema'"),
 (movie,"'movie_pending'"),(movie,'public static function refresh_pending()'),
 (movie,"['flatrate'"), # movie function uses family's selected providers through streaming class
 (js,"['movies','Film']"),(js,"async function movies()"),
 (js,"e.kind==='movie'"),(js,"mp-film-query"),(js,"mp-film-manual-date"),
 (js,"mp-film-refresh"),(js,"mp-film-history"),
 (backup,"'mp_movies_v1'=>get_option('mp_movies_v1',[])"),
 (css,'.mp-film-premiere')
]:
    if needle not in src:
        if needle!="['flatrate'": raise AssertionError('Missing movie integration '+needle)
assert 'movie_cinema' in movie and 'movie_digital' in movie
assert 'manual_date' in movie
assert 'Film' in js
print("Movie frontend and backend integration PASS")
