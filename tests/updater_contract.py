#!/usr/bin/env python3
"""Source-contract checks for Hangar18 updater and admin routing.
Runs in CI alongside PHP lint and JavaScript parsing.
"""
from pathlib import Path
import re

main=Path('hangar18-madplan.php').read_text(encoding='utf-8')
up=Path('includes/class-mp-updater.php').read_text(encoding='utf-8')
check=Path('includes/class-mp-checkpoints.php').read_text(encoding='utf-8')
flow=Path('.github/workflows/package.yml').read_text(encoding='utf-8')

def must(src,needle,label):
    if needle not in src:
        raise AssertionError(f'Missing {label}: {needle}')

version=re.search(r'^\s*\*\s*Version:\s*(\S+)',main,re.M).group(1)
must(main,f"define('MP_VERSION', '{version}')", 'version identity')
must(main,"admin_url('admin.php?page=mp-updates')", 'WordPress admin path')
must(main,"plugin_action_links_", 'plugin action shortcut')
must(main,"MP_Updater::register()", 'updater bootstrap')
must(main,"MP_Checkpoints::register()", 'backup bootstrap')
must(up,"add_action('admin_menu',[self::class,'add_page'], 50)", 'admin menu after parent')
must(up,"admin_url('admin.php?page=mp-updates')", 'native admin menu URL')
must(up,"add_submenu_page('mp-hangar18','Opdateringer'", 'submenu registration')
must(up,"wp_update_plugins()", 'native WordPress update check')
must(up,"new Plugin_Upgrader(", 'native WordPress upgrader')
must(up,"'pre_set_site_transient_update_plugins'", 'WordPress update notification')
must(up,"'upgrader_pre_download'", 'download verification')
must(up,"hash_file('sha256'", 'SHA-256 verification')
must(check,"'upgrader_pre_install'", 'pre-install checkpoint')
must(check,"[self::class, 'pre_install'], 5, 2", 'backup runs before core deactivation')
must(up,"[self::class,'capture_activation'],4,2", 'capture before core deactivate')
must(up,"[self::class,'reactivate_after_install'],20,2", 'restore after update')
must(up,"activate_plugin(self::PLUGIN, '', $network_active, true)", 'explicit silent reactivation')
must(up,"self::restore_activation($was_active, $was_network_active)", 'recovery on failed upgrades')
must(check,"self::snapshot(", 'data and program snapshot')
must(up,"'mp_test_backup'", 'preflight backup test')
must(flow,"sha256", 'GitHub package hash')
must(flow,"zip -X", 'deterministic ZIP')
if '/wp-admin/mp-updates' in up:
    raise AssertionError('Invalid URL route found in plugin code')
print(f'Updater contract PASS, version {version}')
