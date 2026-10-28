<?php
declare(strict_types=1);
/** Test personal WordPress user-meta theme persistence in isolation. */
define('ABSPATH','/fake-wordpress/');
class WP_Error {
    public function __construct(public string $code, public string $message, public array $data=[]) {}
}
class WP_REST_Request {
    public function __construct(private array $parameters) {}
    public function get_param(string $key): mixed { return $this->parameters[$key] ?? null; }
}
$GLOBALS['mp_current_test_user']=0;
$GLOBALS['mp_test_meta']=[];
function get_current_user_id(): int { return $GLOBALS['mp_current_test_user']; }
function is_user_logged_in(): bool { return get_current_user_id() > 0; }
function get_user_meta(int $id,string $key,bool $single) {
    return $GLOBALS['mp_test_meta'][$id][$key] ?? '';
}
function update_user_meta(int $id,string $key,string $value): bool {
    $GLOBALS['mp_test_meta'][$id][$key]=$value;
    return true;
}
function sanitize_key(string $s): string { return strtolower(preg_replace('/[^a-z0-9_-]/','',$s)); }
function test(bool $condition,string $description): void {
    if (!$condition) { throw new RuntimeException('FAIL: '.$description); }
}
require_once dirname(__DIR__) . '/includes/class-mp-rest.php';
test(MP_REST::current_theme()==='default','Guests always see Default');
$GLOBALS['mp_current_test_user']=101;
test(MP_REST::current_theme()==='default','New users default to original palette');
$result=MP_REST::save_theme(new WP_REST_Request(['theme'=>'dark']));
test($result['saved']===true && MP_REST::current_theme()==='dark','User 101 can store Dark');
$GLOBALS['mp_current_test_user']=202;
test(MP_REST::current_theme()==='default','User 202 is not affected');
MP_REST::save_theme(new WP_REST_Request(['theme'=>'forest']));
test(MP_REST::current_theme()==='forest','User 202 can store Forest');
$GLOBALS['mp_current_test_user']=101;
test(MP_REST::current_theme()==='dark','User 101 retains Dark across requests');
$bad=MP_REST::save_theme(new WP_REST_Request(['theme'=>'invalid-theme']));
test($bad instanceof WP_Error && MP_REST::current_theme()==='dark','Invalid themes rejected');
$reset=MP_REST::save_theme(new WP_REST_Request(['theme'=>'default']));
test($reset['saved']===true && MP_REST::current_theme()==='default','Default can be restored');
$GLOBALS['mp_current_test_user']=0;
$guest=MP_REST::save_theme(new WP_REST_Request(['theme'=>'purple']));
test($guest instanceof WP_Error && !isset($GLOBALS['mp_test_meta'][0]),'Guests cannot save');
foreach (['ocean','rose','sunset','mint','burgundy'] as $t) {
  $r=MP_REST::save_theme(new WP_REST_Request(['theme'=>$t]));
  test($r['saved'] && MP_REST::current_theme()===$t,'New personal theme '.$t);
}
echo "Per-user WordPress theme tests PASS\n";
