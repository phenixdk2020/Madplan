#!/usr/bin/env python3
"""Explicit, bounded, chain-specific flyer searches for household shopping items."""
from pathlib import Path
main=Path('hangar18-madplan.php').read_text()
rest=Path('includes/class-mp-rest.php').read_text()
flyer=Path('includes/class-mp-offer-flyers.php').read_text()
offers=Path('includes/class-mp-offers.php').read_text()
js=Path('assets/portal.js').read_text()
css=Path('assets/portal.css').read_text()
for src,term in [
 (main,'Version: 0.1.20'),
 (main,"require_once MP_DIR . 'includes/class-mp-offer-flyers.php'"),
 (rest,"self::route('/shopping/flyer-search', 'POST', 'shopping_flyer_search', true)"),
 (rest,'MP_OfferFlyers::scan('),
 (flyer,'https://api.prej.app/v1/offers?'),
 (flyer,"'chain'=>$chain_slug"),
 (flyer,"'source'=>'flyer'"),
 (flyer,'private const MAX_PAGES=4'),
 (flyer,'private const PAGE_LIMIT=100'),
 (flyer,'MP_ShoppingDetails::selected_stores()'),
 (flyer,'MP_Offers::reserve_flyer_call()'),
 (flyer,'MP_Storage::table('),
 (offers,'public static function reserve_flyer_call()'),
 (js,"api('shopping/flyer-search','POST'"),
 (js,'Søg i butikkens tilbudsavis'),
 (js,'mp-flyer-store'),
 (css,'.mp-flyer-search'),
]:
 assert term in src, 'Missing expanded flyer integration: '+term
assert 'api.prej.app' not in js
assert 'prej_dev_' not in js
print('Bounded Prej flyer search integration PASS')
