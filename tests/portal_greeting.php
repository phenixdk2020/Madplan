<?php
declare(strict_types=1);
/** Personal greeting uses WordPress first name, never shares another account's name. */
define('ABSPATH','/mock/');
function sanitize_text_field(string $text): string { return trim(strip_tags($text)); }
function is_email(string $value): bool { return filter_var($value,FILTER_VALIDATE_EMAIL)!==false; }
function add_action(...$args): void {}
require dirname(__DIR__).'/includes/class-mp-portal.php';
function expect(bool $result,string $label): void { if (!$result) throw new RuntimeException('FAIL: '.$label); }
$maria=(object)['first_name'=>'Maria','display_name'=>'Maria Andersen'];
$other=(object)['first_name'=>'Allan','display_name'=>'Allan Jensen'];
expect(MP_Portal::greeting($maria)==='Velkommen Maria','Maria uses own first name');
expect(MP_Portal::greeting($other)==='Velkommen Allan','Account switch updates greeting');
expect(MP_Portal::greeting_name((object)['first_name'=>'','display_name'=>'Maria Andersen'])==='Maria','Falls back to first display word');
expect(MP_Portal::greeting((object)['first_name'=>'','display_name'=>'maria@example.com'])==='Velkommen tilbage','No email address used as greeting');
expect(MP_Portal::greeting((object)['first_name'=>'','display_name'=>''])==='Velkommen tilbage','Empty profile has friendly fallback');
expect(MP_Portal::greeting_name((object)['first_name'=>'Anne Marie','display_name'=>'Something Else'])==='Anne Marie','Compound preferred first name preserved');
expect(MP_Portal::greeting_name((object)['first_name'=>'<b>Maria</b>','display_name'=>'Other'])==='Maria','Unsafe markup is stripped');
echo "First-name portal greeting tests PASS\n";
