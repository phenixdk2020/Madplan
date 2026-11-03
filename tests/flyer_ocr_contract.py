#!/usr/bin/env python3
from pathlib import Path
main=Path('hangar18-madplan.php').read_text()
php=Path('includes/class-mp-flyer-ocr.php').read_text()
rest=Path('includes/class-mp-rest.php').read_text()
js=Path('assets/portal.js').read_text()
backup=Path('includes/class-mp-checkpoints.php').read_text()
wf=Path('.github/workflows/package.yml').read_text()
for source,needle in [
 (main,' * Version: '),
 (main,"require_once MP_DIR . 'includes/class-mp-flyer-ocr.php'"),
 (rest,"self::route('/shopping/ocr/upload', 'POST', 'shopping_ocr_upload', true, true)"),
 (rest,"self::route('/shopping/ocr/import-text', 'POST', 'shopping_ocr_import_text', true, true)"),
 (rest,"self::route('/shopping/ocr/matches', 'GET', 'shopping_ocr_matches')"),
 (rest,"self::route('/shopping/ocr/clear', 'DELETE', 'shopping_ocr_clear', true, true)"),
 (php,'private const MAX_IMAGE_BYTES=6*1024*1024'),
 (php,'is_uploaded_file('),
 (php,"getimagesize($path)"),
 (php,"proc_open($args"),
 (php,'public static function import_text('),
 (php,"'mp_grocery_ocr_candidates_v1'"),
 (php,'public static function match_for_item('),
 (backup,"'mp_grocery_ocr_candidates_v1'=>get_option("),
 (js,"api('shopping/ocr/status')"),
 (js,"shopping/ocr/upload"),
 (js,"api('shopping/ocr/import-text','POST'"),
 (js,"api('shopping/ocr/matches?id='"),
 (js,'OCR-test af én avisside'),
 (wf,'php tests/flyer_ocr.php')
]:
 assert needle in source,needle
assert "fetch('https://img.prej.app" not in js
assert 'MP_PREJ_API_TOKEN' not in js
assert "update_user_meta(" not in php
print("Manual, admin-only OCR pilot integration and privacy contract PASS")
