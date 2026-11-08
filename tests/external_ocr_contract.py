#!/usr/bin/env python3
"""External OCR support must be explicitly enabled and never bypass source permissions."""
from pathlib import Path
main=Path('hangar18-madplan.php').read_text(encoding='utf-8')
ocr=Path('includes/class-mp-daily-ocr.php').read_text(encoding='utf-8')
rest=Path('includes/class-mp-rest.php').read_text(encoding='utf-8')
ui=Path('assets/portal.js').read_text(encoding='utf-8')
flow=Path('.github/workflows/madplan-ocr.yml').read_text(encoding='utf-8')
worker=Path('scripts/madplan_ocr_worker.py').read_text(encoding='utf-8')
pack=Path('.github/workflows/package.yml').read_text(encoding='utf-8')
for obj,needle in [
 (ocr,"public static function mode(): string"),
 (ocr,"$mode==='server' && !MP_FlyerOCR::status()['available']"),
 (ocr,"if (!self::enabled() || self::mode()!=='github')"),
 (ocr,"public static function external_queue():"),
 (ocr,"public static function external_result("),
 (ocr,"MP_FlyerOCR::parse_candidates("),
 (ocr,"self::retain_new($candidates)"),
 (ocr,"'external_last_poll'"),
 (ocr,"self::advance_page($page)"),
 (rest,"self::route('/shopping/daily-ocr/external-queue', 'GET'"),
 (rest,"self::route('/shopping/daily-ocr/external-result', 'POST'"),
 (rest,"'shopping_daily_ocr_external_queue', false, true"),
 (rest,"'shopping_daily_ocr_external_result', true, true"),
 (ui,'id="mp-daily-ocr-mode"'),
 (ui,"mode:content.querySelector('#mp-daily-ocr-mode').value"),
 (ui,"checkbox.disabled=ocrMode.value==='server'&&!dailyOCR.tesseract"),
 (ui,"MADPLAN_OCR_APP_PASSWORD"),
 (main,"MP_DailyOCR::mode()==='server'"),
 (flow,"timezone: Europe/Copenhagen"),
 (flow,"MADPLAN_OCR_APP_PASSWORD"),
 (flow,"MADPLAN_OCR_USERNAME"),
 (worker,"class NoRedirect"),
 (worker,"request_json(API + \"external-queue\")"),
 (worker,"request_json(API + \"external-result\""),
 (worker,"ALLOWED.fullmatch(url)"),
 (worker,"tempfile.TemporaryDirectory"),
 (pack,"python3 tests/external_ocr_contract.py"),
 (pack,"php tests/daily_ocr.php"),
]:
    assert needle in obj, "Missing "+needle
assert "https://hangar18.dk" in worker
assert "prej_" not in flow
assert "password" not in str(Path('includes/class-mp-daily-ocr.php').read_text().split("external_result")[1]).lower(), "No credential serialization in payload"
print("External OAuth-free WordPress Application Password OCR integration PASS")
