#!/usr/bin/env python3
"""Regression: all five themes persist in user_meta and are accessible in the portal."""
from pathlib import Path
php=Path('includes/class-mp-rest.php').read_text(encoding='utf-8')
view=Path('includes/class-mp-portal.php').read_text(encoding='utf-8')
template=Path('templates/portal-shell.php').read_text(encoding='utf-8')
js=Path('assets/portal.js').read_text(encoding='utf-8')
css=Path('assets/portal.css').read_text(encoding='utf-8')
main=Path('hangar18-madplan.php').read_text(encoding='utf-8')
assert 'Version: 0.1.8' in main
assert "private const THEMES = ['default', 'dark', 'forest', 'purple', 'sand', 'ocean', 'rose', 'sunset', 'mint', 'burgundy']" in php
assert "update_user_meta($uid, 'mp_portal_theme', $theme)" in php
assert "get_user_meta(get_current_user_id(), 'mp_portal_theme', true)" in php
assert "self::route('/theme', 'POST', 'save_theme', true)" in php
assert "'theme'=>MP_REST::current_theme()" in view
assert 'id="mp-theme-select"' in view
assert 'selected($theme, $value)' in view
assert "'default' => 'Default'" in view
assert 'data-mp-theme="' in view
assert 'MP_REST::current_theme()' in template
assert "api('theme','POST',{theme:selected})" in js
assert 'localStorage.setItem' not in js and 'localStorage.getItem' not in js
assert "applyTheme(previous)" in js
assert 'themeSelect.disabled=true' in js
assert "root.setAttribute('data-mp-theme',value)" in js
for theme in ['default','dark','forest','purple','sand']:
    assert f'data-mp-theme="{theme}"' in css, theme
assert 'body.mp-full-page.mp-theme-dark' in css
assert '.mp-theme-control' in css
print('Personal theme static contract PASS')
