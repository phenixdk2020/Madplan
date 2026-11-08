#!/usr/bin/env python3
"""v0.1.17: shopping must be a visible and direct homepage choice."""
from pathlib import Path
portal=Path('includes/class-mp-portal.php').read_text(encoding='utf-8')
js=Path('assets/portal.js').read_text(encoding='utf-8')
css=Path('assets/portal.css').read_text(encoding='utf-8')
main=Path('hangar18-madplan.php').read_text(encoding='utf-8')
assert ' * Version: ' in main
assert portal.count('class="mp-home-card" data-module="shopping"') == 1
assert portal.count('class="mp-home-card" data-module="tv"') == 1
assert portal.count('class="mp-home-card" data-module="food"') == 1
assert '🛒' in portal
assert 'Fælles indkøb, butikker og aktuelle tilbud' in portal
assert "if(name==='shopping'){name='food';tab='shopping';}" in js
assert "if(s.tab==='shopping')return shopping();" in js
assert "api('shopping')" in js
assert "else if(hash==='shopping'||hash==='indkoeb')module('food','shopping');" in js
assert "t==='shopping'?'Indkøbsliste'" in js
assert "t==='recipes'?'Opskrifter':'Madplan'" in js
assert '.mp-portal .mp-home-cards{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}' in css
assert '.mp-portal .mp-home-card{min-width:0;min-height:108px;' in css
assert 'grid-template-columns:repeat(4,minmax(0,1fr))' in css
assert portal.count('class="mp-home-card" data-module=') == 4
assert "tabs.hidden=!isTv;" in js
assert "tabs.innerHTML=isTv?" in js
assert "['week','Ugeplan'],['watch','Serier'],['movies','Film']" in js
assert "id=\"mp-open-recipes\"" in js
assert "setTab('recipes')" in js
assert "#mp-tabs[hidden]{display:none!important}" in css
assert "data-module=\"shopping\"" in portal
assert 'id="mp-open-shopping-settings"' in portal
assert 'hidden>⚙ Indkøbslistens opsætning' in portal
assert "shoppingSettingsLink.hidden=t!=='shopping'" in js
assert "if(shoppingSettingsLink)shoppingSettingsLink.hidden=true;" in js
assert "var oldSettingsDialog=content.querySelector('#mp-shopping-settings-dialog');" in js
assert 'id="mp-shopping-settings-dialog"' in js
assert 'id="mp-shopping-settings-close"' in js
assert "settingsDialog.showModal()" in js
assert "expandedSettings.forEach" in js
assert 'id="mp-clear-purchased"' in js and 'id="mp-check-offers-all"' in js
assert js.index('id="mp-clear-purchased"') < js.index('<dialog id="mp-shopping-settings-dialog"')
assert js.index('id="mp-check-offers-all"') < js.index('<dialog id="mp-shopping-settings-dialog"')
assert js.index('<div class="mp-shopping-bulk-actions">') < js.index('<dialog id="mp-shopping-settings-dialog"')
assert js.index('id="mp-offer-note"') < js.index('<dialog id="mp-shopping-settings-dialog"')
assert js.index('Scan stregkode</button></section>') < js.index('<dialog id="mp-shopping-settings-dialog"')
assert ".mp-shopping-settings-dialog:not([open]){display:none}" in css
print('Homepage direct shopping link and mobile layout PASS')
