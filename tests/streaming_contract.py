#!/usr/bin/env python3
from pathlib import Path
main=Path('hangar18-madplan.php').read_text()
stream=Path('includes/class-mp-streaming.php').read_text()
rest=Path('includes/class-mp-rest.php').read_text()
tv=Path('includes/class-mp-tvmaze.php').read_text()
js=Path('assets/portal.js').read_text()
for source,needle in [
 (main,'Version: 0.1.11'),
 (main,"require_once MP_DIR . 'includes/class-mp-streaming.php'"),
 (main,'MP_Streaming::save_token($key)'),
 (stream,"'mp_streaming_subscriptions_v1'"),
 (stream,"get_user_meta($user_id,self::PREF,true)"),
 (stream,"update_user_meta($user_id,self::PREF"),
 (stream,"['flatrate','free','ads']"),
 (stream,"'not_configured'"),
 (stream,"'unmatched'"),
 (rest,"'/streaming-services'"),
 (rest,"'/streaming-lookup'"),
 (rest,"/streaming-check'"),
 (rest,"$service=count($streaming_result['matches'])===1"),
 (tv,"'premiered'=>sanitize_text_field"),
 (js,'mp-save-services'),(js,'check-streaming'),
 (js,'Automatisk – søg blandt mine tjenester'),(js,'JustWatch via TMDB')
]:
 if needle not in source:raise AssertionError('Missing integration: '+needle)
assert 'MP_TMDB_API_TOKEN' not in js
print('Subscription UI and server integration PASS')
