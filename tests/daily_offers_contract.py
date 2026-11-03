#!/usr/bin/env python3
"""Daily cron-backed offer snapshot, consent gate and visible coverage."""
from pathlib import Path
p=Path('includes/class-mp-daily-offers.php').read_text()
main=Path('hangar18-madplan.php').read_text()
rest=Path('includes/class-mp-rest.php').read_text()
js=Path('assets/portal.js').read_text()
wf=Path('.github/workflows/package.yml').read_text()
for source,term in [
(main,'Version: 0.1.23'),
(main,"require_once MP_DIR . 'includes/class-mp-daily-offers.php'"),
(main,"add_action('mp_daily_offer_tick', [MP_DailyOffers::class, 'tick'])"),
(p,"private const MAX_DAILY_CALLS=8"),
(p,"public static function ensure_if_enabled()"),
(p,"private static function day()"),
(p,"'mp_daily_offer_settings_v1'"),
(p,"'mp_daily_offer_state_v1'"),
(p,"MP_Offers::reserve_flyer_call()"),
(p,"current_datetime()->format('Hi') < '0530'"),
(p,"'ocr_automatic'=>false"),
(rest,"'/shopping/daily/status'"),
(rest,"'/shopping/daily/settings'"),
(rest,"'/shopping/daily/run'"),
(js,"api('shopping/daily/status')"),
(js,"api('shopping/daily/settings','POST'"),
(js,"api('shopping/daily/matches?id='"),
(js,"mp-daily-progress"),
(wf,'php tests/daily_offers.php'),
]:
 assert term in source,term
assert 'api.prej.app' not in js
assert 'wp_remote_get' not in js
assert "if(item.expanded&&item.message) {\n   markup+='<p class=\"mp-help mp-flyer-summary\">'" not in js, 'Old duplicate notice still present'
print("Daily structured-offer scheduling and UI contract PASS")
