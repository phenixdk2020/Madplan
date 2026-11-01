#!/usr/bin/env python3
"""Shared store group and button-triggered Prej lookup integration contract."""
from pathlib import Path
m=Path('hangar18-madplan.php').read_text()
r=Path('includes/class-mp-rest.php').read_text()
s=Path('includes/class-mp-shopping-stores.php').read_text()
j=Path('assets/portal.js').read_text()
c=Path('assets/portal.css').read_text()
b=Path('includes/class-mp-checkpoints.php').read_text()
w=Path('.github/workflows/package.yml').read_text()
for source,needle in [
 (m,'Version: 0.1.16'),(m,"require_once MP_DIR . 'includes/class-mp-shopping-stores.php'"),
 (r,"'/shopping/stores'"),(r,"/store', 'POST'"),(r,"MP_ShoppingStores::save("),
 (r,"MP_ShoppingStores::preferred((string)$row['title'])"),
 (s,"'mp_shopping_favourite_stores_v1'"),(s,'function preferred('),(s,'function save('),
 (s,"'Tilbud'"),(s,"'Føtex'"),
 (j,"api('shopping/stores')"),(j,"/store','POST'"),(j,"mp-shopping-group-name"),
 (j,"mp-offer-check"),(j,"api('shopping/offers','POST',{ids:ids})"),
 (c,".mp-shopping-group-name"),(c,".mp-shopping-store"),
 (b,"'mp_shopping_favourite_stores_v1'=>get_option("),
 (w,"php tests/shopping_stores.php")
]:
 assert needle in source, f"Missing integration: {needle}"
assert "update_user_meta(" not in s, "Preferred stores are shared, not per user"
print('Household category and offer button contract PASS')
