#!/usr/bin/env python3
"""Safety, access controls, scheduling and source guardrails for daily OCR."""
from pathlib import Path
main=Path('hangar18-madplan.php').read_text()
ocr=Path('includes/class-mp-daily-ocr.php').read_text()
local=Path('includes/class-mp-flyer-ocr.php').read_text()
rest=Path('includes/class-mp-rest.php').read_text()
js=Path('assets/portal.js').read_text()
wf=Path('.github/workflows/package.yml').read_text()
for src,needle in [
 (main,' * Version: '),
 (main,"require_once MP_DIR . 'includes/class-mp-daily-ocr.php'"),
 (main,"add_action('mp_daily_ocr_tick', [MP_DailyOCR::class, 'tick'])"),
 (ocr,"'mp_daily_ocr_settings_v1'"),
 (ocr,"'mp_daily_ocr_state_v1'"),
 (ocr,"private const MAX_PAGES_PER_DAY=192"),
 (ocr,"private const MAX_PAGES_PER_FLYER=80"),
 (ocr,"'mp_daily_ocr_archive_v1'"),
 (ocr,"self::retain_new($data)"),
 (ocr,"'mp_five_minutes'"),
 (ocr,"'rights_confirmed'"),
 (ocr,"!MP_FlyerOCR::status()['available']"),
 (ocr,"MP_FlyerLibrary::listing()"),
 (ocr,"https://img\\.prej\\.app/flyers/"),
 (ocr,"'redirection'=>0"),
 (ocr,"'limit_response_size'=>self::MAX_BYTES+1"),
 (ocr,"MP_FlyerOCR::text_from_local_image("),
 (ocr,"@unlink($tmp)"),
 (ocr,"'day'=>self::day()"),
 (ocr,"'mp_daily_ocr_lock_v1'"),
 (local,'public static function text_from_local_image('),
 (rest,"self::route('/shopping/daily-ocr/settings', 'POST', 'shopping_daily_ocr_settings', true, true)"),
 (rest,"self::route('/shopping/daily-ocr/run', 'POST', 'shopping_daily_ocr_run', true, true)"),
 (js,"api('shopping/daily-ocr/status')"),
 (js,'mp-daily-ocr-rights'),
 (js,"api('shopping/daily-ocr/settings','POST'"),
 (js,"api('shopping/daily-ocr/matches?id='"),
 (wf,'php tests/daily_ocr.php'),
]:
 assert needle in src, 'Missing requirement: '+needle
assert "wp_insert_attachment" not in ocr
assert "wp_upload_bits" not in ocr
assert 'api.prej.app' not in js
print("Daily permissioned OCR queue and ephemeral image processing PASS")
