#!/usr/bin/env python3
"""Shared household subscriptions; personal themes remain per user."""
from pathlib import Path
main=Path('hangar18-madplan.php').read_text()
stream=Path('includes/class-mp-streaming.php').read_text()
rest=Path('includes/class-mp-rest.php').read_text()
tv=Path('includes/class-mp-tvmaze.php').read_text()
js=Path('assets/portal.js').read_text()
for source,needle in [
 (main,' * Version: '),
 (main,"require_once MP_DIR . 'includes/class-mp-streaming.php'"),
 (main,'MP_Streaming::save_token($key)'),
 (stream,"'mp_household_streaming_services_v1'"),
 (stream,"update_option(self::PREF"),
 (stream,"get_option(self::PREF,null)"),
 (stream,"public static function save_shared("),
 (stream,"['flatrate','free','ads']"),
 (stream,"'not_configured'"),
 (stream,"'unmatched'"),
 (rest,"'/streaming-services'"),
 (rest,"'/streaming-lookup'"),
 (rest,"/streaming-check'"),
 (rest,"MP_Streaming::save_shared($services)"),
 (rest,"'shared'=>true"),
 (rest,"$service=count($streaming_result['matches'])===1"),
 (tv,"'premiered'=>sanitize_text_field"),
 (js,'mp-save-services'),
 (js,'check-streaming'),
 (js,'Familiens streamingtjenester'),
 (js,'Automatisk – søg blandt familiens tjenester'),
 (js,'JustWatch via TMDB')
]:
 if needle not in source:
  raise AssertionError('Missing integration: '+needle)
assert 'update_user_meta(' not in stream, 'Subscription changes must not be written per-user'
assert "get_user_meta($uid,self::LEGACY_USER_PREF" in stream, 'One-time migration should preserve prior selections'
assert 'MP_TMDB_API_TOKEN' not in js
assert "update_user_meta($uid, 'mp_portal_theme', $theme)" in rest, 'Theme choice remains personal'
print('Shared household subscription UI and server integration PASS')
