#!/usr/bin/env python3
"""v0.1.17: shopping must be a visible and direct homepage choice."""
from pathlib import Path
portal=Path('includes/class-mp-portal.php').read_text(encoding='utf-8')
js=Path('assets/portal.js').read_text(encoding='utf-8')
css=Path('assets/portal.css').read_text(encoding='utf-8')
main=Path('hangar18-madplan.php').read_text(encoding='utf-8')
assert 'Version: 0.1.17' in main
assert portal.count('class="mp-home-card" data-module="shopping"') == 1
assert portal.count('class="mp-home-card" data-module="tv"') == 1
assert portal.count('class="mp-home-card" data-module="food"') == 1
assert '🛒' in portal
assert 'Fælles indkøb, butikker og aktuelle tilbud' in portal
assert "if(name==='shopping'){name='food';tab='shopping';}" in js
assert "if(s.tab==='shopping')return shopping();" in js
assert "api('shopping')" in js
assert "else if(hash==='shopping'||hash==='indkoeb')module('food','shopping');" in js
assert "t==='shopping'?'Indkøbsliste':'Madplan'" in js
assert 'grid-template-columns:repeat(3,minmax(0,1fr))' in css
assert 'grid-template-columns:minmax(0,1fr)' in css
assert "data-module=\"shopping\"" in portal
print('Homepage direct shopping link and mobile layout PASS')
