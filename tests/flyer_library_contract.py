#!/usr/bin/env python3
"""Verify real page links, no page-image copying or automatic OCR."""
from pathlib import Path
main=Path('hangar18-madplan.php').read_text()
lib=Path('includes/class-mp-flyer-library.php').read_text()
rest=Path('includes/class-mp-rest.php').read_text()
js=Path('assets/portal.js').read_text()
css=Path('assets/portal.css').read_text()
for src,needle in [
 (main,'Version: 0.1.21'),
 (main,"require_once MP_DIR . 'includes/class-mp-flyer-library.php'"),
 (lib,'https://api.prej.app/v1/flyers?'),
 (lib,"$body['flyers']"),
 (lib,'MP_OfferFlyers::chain_slug('),
 (lib,"$parts['host']??''"),
 (lib,'MP_Offers::reserve_flyer_call()'),
 (lib,'get_transient(self::CACHE)'),
 (lib,'current_datetime()->format'),
 (rest,"self::route('/shopping/flyers', 'GET', 'shopping_flyers')"),
 (js,"api('shopping/flyers')"),
 (js,'Hent aktuelle tilbudsaviser'),
 (js,'Åbn forside'),(js,'Se alle sider'),
 (css,'.mp-flyer-library'),
]:
 assert needle in src,f'Missing flyer library feature: {needle}'
assert 'img.prej.app' not in js
assert 'wp_remote_get(' not in js
print('Original linked flyer pages in household shopping GUI PASS')
