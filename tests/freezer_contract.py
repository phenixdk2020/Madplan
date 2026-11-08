#!/usr/bin/env python3
"""Freezer feature contract: seven positions, integrations, security and backups."""
from pathlib import Path
main=Path('hangar18-madplan.php').read_text(encoding='utf-8')
back=Path('includes/class-mp-freezer.php').read_text(encoding='utf-8')
rest=Path('includes/class-mp-rest.php').read_text(encoding='utf-8')
portal=Path('includes/class-mp-portal.php').read_text(encoding='utf-8')
js=Path('assets/freezer.js').read_text(encoding='utf-8')
core=Path('assets/portal.js').read_text(encoding='utf-8')
backup=Path('includes/class-mp-checkpoints.php').read_text(encoding='utf-8')
style=Path('assets/freezer.css').read_text(encoding='utf-8')
for key in ['room1','basket1','room2','basket2','room3','basket3','room4']:
    assert "'"+key+"'=>" in back, f"Missing permitted position {key}"
    assert "'"+key+"'" in js
assert "'basket4'" not in back
assert 'array_key_exists($location,self::LOCATIONS)' in back
assert "MP_Storage::table('freezer')" in back
assert "dbDelta(" in back and "get_option(self::SCHEMA)" in back
for method in ["all", "add", "update", "remove", "from_shopping", "to_shopping"]:
    assert f'public static function {method}(' in back
assert "MP_Barcode::valid(" in back
assert "MP_Barcode::remember(" in back
assert "MP_ShoppingDetails::for_item(" in back
assert "MP_ShoppingDetails::save_item(" in back
assert "MP_Barcode::lookup(" in rest
for route in ["/freezer'", "/freezer/from-shopping", "to-shopping"]:
    assert route in rest
assert "'freezer_add', true" in rest and "'freezer_delete', true" in rest
assert "'permission_callback'" in rest
assert "data-module=\"freezer\"" in portal
assert "assets/freezer.js" in portal and "assets/freezer.css" in portal
assert "if(name==='freezer')" in core and "if(s.tab==='freezer')" in core
assert "s.freezerDraft" in core and "editDay(cfg.today,draft.title)" in core
assert "html5-qrcode@2.3.8" in js and "shopping/barcode/lookup" in js
assert "window.MP_FREEZER" in js and "scanId" in js
assert "window.confirm(" in js
assert "['meals','shows','episodes','shopping','freezer','audit']" in backup
assert "mp_freezer_schema_v1" in backup
assert "mp_freezer_schema_v2" in backup
assert "private const SCHEMA = 'mp_freezer_schema_v2'" in back
assert "notes varchar(500) NOT NULL DEFAULT ''" in back
assert "SHOW COLUMNS FROM $table LIKE %s" in back
assert "sanitize_textarea_field" in back and "mb_strlen($notes)>500" in back
assert "'notes'=>$notes" in back
assert "unit,notes,location" in back
assert "'notes'=>$r->get_param('notes')" in rest
assert 'name="notes"' in js and 'maxlength="500"' in js
assert "String(item.notes||'').toLocaleLowerCase('da-DK')" in js
assert "mp-freezer-item-note" in js and "esc(item.notes)" in js
assert "form.elements[key].value=item[key]||''" in js and "'best_before','notes'" in js
assert "data[key]=form.elements[key].value" in js
assert ".mp-freezer-notes-label textarea" in style
assert "mp-freezer-map" in style
assert '<details class="mp-freezer-disclosure" id="mp-freezer-register">' in js
assert '<details class="mp-freezer-disclosure" id="mp-freezer-shopping">' in js
assert "content.querySelector('#mp-freezer-register').open=true" in js
assert "mp-freezer-disclosure>summary" in style
assert "function quantityLabel(item)" in js
assert "quantity+' stk.'" in js and "' × '+unit" in js
assert "esc(quantityLabel(item))" in js
for action in ['edit','meal','shopping','delete']:
    assert "freezerAction('"+action+"'" in js
    assert "actionIcons[action]" in js
assert 'aria-label="' in js and "esc(label+' – '+item.title)" in js
assert "mp-freezer-icon-actions" in js and "mp-freezer-icon-btn" in js
assert "grid-template-columns:repeat(2,44px)" in style
assert 'id="mp-freezer-search"' in js
assert 'type="search"' in js
assert 'class="mp-freezer-controls"' in js
assert "search.oninput=redraw" in js
assert "matchesLocation&&matchesSearch" in js
assert "toLocaleLowerCase('da-DK')" in js
assert "Ingen madvarer matcher søgningen." in js
assert ".mp-freezer-controls{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))" in style
assert ".mp-freezer-item{display:grid;grid-template-columns:minmax(0,1fr) auto" in style
assert "php tests/freezer_duplicates.php" in Path('.github/workflows/package.yml').read_text(encoding='utf-8')
# Compact date inputs must appear together AFTER the notes input.
assert 'class="mp-freezer-date-fields"' in js
assert js.index('class="mp-freezer-notes-label"') < js.index('class="mp-freezer-date-fields"') < js.index('id="mp-freezer-submit"')
assert 'grid-template-columns:repeat(2,minmax(0,1fr))' in style
assert '.mp-freezer-date-fields input[type="date"]' in style
assert 'height:44px;min-height:44px;max-height:44px' in style
# Camera must be a modal and must release the stream on cancellation, closure or scan.
assert '<dialog class="mp-freezer-camera-dialog"' in js
assert 'cameraDialog.showModal()' in js
assert "cameraDialog.addEventListener('cancel'" in js
assert "cameraDialog.addEventListener('close'" in js
assert "cameraDialog.addEventListener('click'" in js
assert "content.querySelector('#mp-freezer-camera-close').onclick=stop" in js
assert "content.querySelector('#mp-freezer-camera-cancel').onclick=stop" in js
assert "if(cameraDialog.open)cameraDialog.close()" in js
assert "scanning=true;\n     stop();\n     lookup(code);" in js
assert "reader.hidden=true" in js
assert "mp-freezer-stop" not in js
assert 'mp-freezer-camera-dialog::backdrop' in style
print('Freezer inventory, security, scanner, integrations, backup contract PASS')
