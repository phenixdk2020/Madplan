from pathlib import Path
m=Path("hangar18-madplan.php").read_text()
r=Path("includes/class-mp-rest.php").read_text()
p=Path("includes/class-mp-offers.php").read_text()
j=Path("assets/portal.js").read_text()
w=Path(".github/workflows/package.yml").read_text()
for source,needle in [
 (m," * Version: "),(m,"require_once MP_DIR . 'includes/class-mp-offers.php'"),
 (m,"MP_Offers::save_key($key)"),(p,"private const DAILY_LIMIT=20"),
 (p,"https://api.prej.app/v1/products?"),(p,"'on_offer'=>1"),
 (p,"'exclude_border_trade'=>1"),(p,"'offer_ends'"),
 (p,"MP_Storage::table('shopping')"),(r,"'/shopping/offers'"),
 (j,"api('shopping/offers','POST',{ids:ids})"),(j,"mp-check-offers-all"),
 (j,"Tilbudsdata fra"),(w,"php tests/offers_check.php")
]:
 assert needle in source,needle
assert "MP_PREJ_API_TOKEN" not in j
print("Shopping offers WordPress contract PASS")
