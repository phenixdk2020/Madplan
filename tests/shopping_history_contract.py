#!/usr/bin/env python3
"""History-based shopping autocomplete, shared WordPress option, no per-keystroke APIs."""
from pathlib import Path
h=Path('includes/class-mp-shopping-history.php').read_text()
main=Path('hangar18-madplan.php').read_text()
rest=Path('includes/class-mp-rest.php').read_text()
js=Path('assets/portal.js').read_text()
css=Path('assets/portal.css').read_text()
checkpoint=Path('includes/class-mp-checkpoints.php').read_text()
for source,term in [
 (main,'Version: 0.1.19'),
 (main,"require_once MP_DIR . 'includes/class-mp-shopping-history.php'"),
 (h,"'mp_shopping_purchased_history_v1'"),
 (h,'SELECT title FROM $table WHERE checked=1'),
 (h,'public static function remember('),
 (h,'public static function suggestions('),
 (rest,"self::route('/shopping/history', 'GET', 'shopping_history')"),
 (rest,'MP_ShoppingHistory::remember('),
 (rest,'MP_ShoppingHistory::suggestions()'),
 (checkpoint,"'mp_shopping_purchased_history_v1'=>get_option("),
 (js,"api('shopping/history')"),
 (js,'mp-history-matches'),
 (js,"titleInput.addEventListener('input',renderHistory)"),
 (js,"ev.key==='ArrowDown'"),
 (js,"ev.key==='Escape'"),
 (js,"ev.key==='Enter'"),
 (css,'.mp-history-matches'),
]:
 assert term in source, f'Missing: {term}'
assert "api('shopping/history')" in js
assert "oninput=async" not in js
assert 'update_user_meta(' not in h
print('Purchased grocery autocomplete integration PASS')
