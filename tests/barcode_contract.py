#!/usr/bin/env python3
"""Barcode scan must be saved to family list and not leak camera frames or Prej token."""
from pathlib import Path
files={p:Path(p).read_text() for p in [
 'hangar18-madplan.php','includes/class-mp-barcode.php',
 'includes/class-mp-rest.php','includes/class-mp-offers.php',
 'includes/class-mp-shopping-details.php','includes/class-mp-checkpoints.php',
 'assets/portal.js','assets/portal.css','.github/workflows/package.yml']}
for path,needle in [
 ('hangar18-madplan.php','Version: 0.1.25'),
 ('hangar18-madplan.php',"require_once MP_DIR . 'includes/class-mp-barcode.php'"),
 ('includes/class-mp-barcode.php',"'mp_shopping_barcode_catalog_v1'"),
 ('includes/class-mp-barcode.php','https://api.prej.app/v1/scan/'),
 ('includes/class-mp-barcode.php','https://world.openfoodfacts.org/api/v2/product/'),
 ('includes/class-mp-barcode.php','MP_Offers::reserve_barcode_call()'),
 ('includes/class-mp-barcode.php',"MP_ShoppingDetails::save_item("),
 ('includes/class-mp-barcode.php',"public static function valid("),
 ('includes/class-mp-barcode.php',"'needs_name'=>true"),
 ('includes/class-mp-rest.php',"self::route('/shopping/barcode/add', 'POST', 'shopping_barcode_add', true)"),
 ('includes/class-mp-rest.php',"self::route('/shopping/barcode/lookup', 'POST', 'shopping_barcode_lookup', true)"),
 ('includes/class-mp-shopping-details.php','MP_Barcode::remember('),
 ('includes/class-mp-offers.php','reserve_barcode_call()'),
 ('includes/class-mp-checkpoints.php',"'mp_shopping_barcode_catalog_v1'=>get_option("),
 ('assets/portal.js','id="mp-open-barcode"'),
 ('assets/portal.js','BarcodeDetector.getSupportedFormats'),
 ('assets/portal.js','getUserMedia('),
 ('assets/portal.js','barcodeStream.getTracks()'),
 ('assets/portal.js',"api('shopping/barcode/add','POST'"),
 ('assets/portal.js','id="mp-barcode-code"'),
 ('assets/portal.js','mp-barcode-new-title'),
 ('assets/portal.css','.mp-barcode-dialog'),
 ('.github/workflows/package.yml','php tests/barcode.php')]:
 assert needle in files[path],f'Missing {needle} in {path}'
assert "prej_dev_" not in files['assets/portal.js']
assert "api.prej.app" not in files['assets/portal.js']
assert "toDataURL(" not in files['assets/portal.js']
print('Private family barcode scanner and camera fallback contract PASS')
