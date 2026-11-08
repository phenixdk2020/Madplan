#!/usr/bin/env python3
"""Regression: a shopping offer search must use local indexes, with optional explicit Prej."""
from pathlib import Path
main=Path('hangar18-madplan.php').read_text(encoding='utf-8')
rest=Path('includes/class-mp-rest.php').read_text(encoding='utf-8')
js=Path('assets/portal.js').read_text(encoding='utf-8')
local=Path('includes/class-mp-local-offers.php').read_text(encoding='utf-8')
wf=Path('.github/workflows/package.yml').read_text(encoding='utf-8')
assert "require_once MP_DIR . 'includes/class-mp-local-offers.php'" in main
assert "self::route('/shopping/local-offers', 'POST', 'shopping_local_offers', true)" in rest
assert "return MP_LocalOffers::scan($ids)" in rest
assert "MP_DailyOCR::matches($id)" in local
assert "MP_DailyOffers::matches($id)" in local
assert "MP_FlyerOCR::match_for_item($id)" in local
assert "wp_safe_remote_get(" not in local
assert "api('shopping/local-offers','POST',{ids:ids})" in js
assert "api('shopping/offers','POST',{ids:ids})" in js
assert "mp-prej-live-btn" in js
assert "if(!item.local_index){" in js
assert js.index("async function scanLive(ids,b)") < js.index("async function scan(ids,b)")
assert "php tests/local_offers.php" in wf
assert "python3 tests/local_offers_contract.py" in wf
print("Local-first index and explicit Prej fallback PASS")
