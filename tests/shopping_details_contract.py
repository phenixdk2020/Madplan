#!/usr/bin/env python3
"""Screenshot-inspired item details, shared offer-store checkboxes and clear bought items."""
from pathlib import Path
main=Path('hangar18-madplan.php').read_text()
rest=Path('includes/class-mp-rest.php').read_text()
details=Path('includes/class-mp-shopping-details.php').read_text()
js=Path('assets/portal.js').read_text()
css=Path('assets/portal.css').read_text()
offers=Path('includes/class-mp-offers.php').read_text()
backup=Path('includes/class-mp-checkpoints.php').read_text()
workflow=Path('.github/workflows/package.yml').read_text()
for src,needle in [
 (main,'Version: 0.1.18'),
 (main,"require_once MP_DIR . 'includes/class-mp-shopping-details.php'"),
 (rest,"self::route('/shopping/offer-stores', 'POST', 'save_offer_stores', true)"),
 (rest,"/details', 'POST', 'save_shopping_details', true)"),
 (rest,"/photo', 'POST', 'upload_shopping_photo', true)"),
 (rest,"'/shopping/clear-purchased', 'POST', 'clear_purchased', true"),
 (rest,'MP_ShoppingDetails::delete_item($id)'),
 (rest,"['id'=>$id,'checked'=>1]"),
 (rest,'MP_ShoppingDetails::for_item($row)'),
 (details,"'mp_shopping_item_details_v1'"),
 (details,"'mp_shopping_offer_stores_v1'"),
 (details,"'quantity'"),(details,"'package_size'"),
 (details,"'price_ore'"),(details,"'notes'"),
 (details,"'barcode'"),(details,"'photo_url'"),
 (details,"'favorite'"),
 (details,'public static function save_stores('),
 (js,'mp-shop-detail-form'),(js,'mp-shop-dialog-label'),
 (js,'mp-qty-plus'),(js,'mp-qty-minus'),
 (js,'mp-clear-purchased'),(js,"api('shopping/clear-purchased','POST',{})"),
 (js,'mp-save-offer-stores'),(js,"api('shopping/offer-stores','POST'"),
 (js,"new FormData()"),(js,'dialog.showModal()'),
 (offers,'$selectedStores'),(offers,'in_array((string)($offer'),
 (backup,"'mp_shopping_item_details_v1'=>get_option("),
 (backup,"'mp_shopping_offer_stores_v1'=>get_option("),
 (workflow,'php tests/shopping_details.php'),(css,'.mp-shopping-dialog')
]:
 assert needle in src, f'Missing integration: {needle}'
assert "window.confirm('Vil du slette '+" in js, 'Destructive bulk action requires confirmation'
assert "update_user_meta(" not in details, 'Shared metadata must not be personal'
print('Shared item details, store filters and clearing contract PASS')
