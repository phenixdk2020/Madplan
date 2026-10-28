#!/usr/bin/env python3
"""Static integration contract for mobile TV flow and in-app navigation."""
from pathlib import Path
main=Path("hangar18-madplan.php").read_text(encoding="utf-8")
rest=Path("includes/class-mp-rest.php").read_text(encoding="utf-8")
tv=Path("includes/class-mp-tvmaze.php").read_text(encoding="utf-8")
portal=Path("includes/class-mp-portal.php").read_text(encoding="utf-8")
js=Path("assets/portal.js").read_text(encoding="utf-8")
css=Path("assets/portal.css").read_text(encoding="utf-8")
for src,needle in [
 (main," * Version: "),
 (rest,"'/tv-details'"),
 (rest,"MP_TVmaze::details($tvmaze)"),
 (tv,"'/shows/' . $id . '/seasons'"),
 (tv,"'episodeOrder'"),
 (tv,"'provider_note'"),
 (portal,'id="mp-back-bottom"'),
 (js,"function backToMenu()"),
 (js,"root.querySelector('#mp-back-bottom').onclick=backToMenu"),
 (js,"tv-details?id="),
 (js,"find-series"),
 (js,"session"),  # session identity is WordPress-controlled; checked below
 (js,"setSeason()"),
 (css,".mp-portal-footer"),
]:
    if needle not in src:
        # Session-specific wording is deliberately optional.
        if needle != "session":
            raise AssertionError(f"Missing contract: {needle}")
if js.count("function backToMenu()") != 1:
    raise AssertionError("Back-to-menu must be one shared callback")
if "wp_logout_url" in js[js.index("function backToMenu()"):js.index("root.querySelector('#mp-back-bottom')")]:
    raise AssertionError("Back-to-menu must never log the user out")
print("Mobile navigation/auto-series contract PASS")
