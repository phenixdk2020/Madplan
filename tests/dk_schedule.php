<?php
declare(strict_types=1);
/** Danish streaming schedules: verified Friday beats original Sunday. */
define('ABSPATH', '/mock-wordpress/');
define('HOUR_IN_SECONDS',3600);
define('DAY_IN_SECONDS',86400);
define('ARRAY_A','ARRAY_A');
class WP_Error {
    public function __construct(public string $code,public string $message,public array $data=[]) {}
}
function is_wp_error($v): bool { return $v instanceof WP_Error; }
function absint($v): int { return abs((int)$v); }
function sanitize_text_field(string $v): string { return strip_tags($v); }
function get_current_user_id(): int { return 42; }
function wp_timezone(): DateTimeZone { return new DateTimeZone('Europe/Copenhagen'); }
$GLOBALS['mock_options']=[];
function get_option(string $name,$default=false) { return $GLOBALS['mock_options'][$name]??$default; }
function update_option(string $name,$value,$autoload=false): bool {
    $GLOBALS['mock_options'][$name]=$value;return true;
}
class MP_Storage { public static function table(string $suffix): string { return 'wp_mp_'.$suffix; } }
class FakeDb {
    public array $show=['id'=>77,'title'=>'Marshals: A Yellowstone Story','service'=>'SkyShowtime','season'=>2];
    public function prepare(string $sql,...$args): string { return $sql; }
    public function get_row(string $sql,$format): array { return $this->show; }
}
$GLOBALS['wpdb']=new FakeDb();
require dirname(__DIR__).'/includes/class-mp-tvmaze.php';
function check(bool $v,string $desc): void { if (!$v)throw new RuntimeException($desc); }
$marshals=['id'=>77,'title'=>'Marshals','service'=>'SkyShowtime','season'=>2];
$dk=MP_TVmaze::dk_schedule($marshals);
check($dk!==null && $dk['source_type']==='verified','Official Danish override present');
check($dk['first_date']==='2026-10-09' && $dk['weekly'],'9 Oct 2026 is Danish Friday start');
check($dk['source_url']==='https://www.skyshowtime.com/dk/its-time-for/marshals-a-yellowstone-story','Official DK source');
$us=['air_date'=>'2026-10-25','title'=>'US premiere on Sunday'];
$ep4=MP_TVmaze::episode_date($marshals,4,$us,$dk);
check($ep4['date']==='2026-10-30','Friday Oct 30 wins over US Sunday Oct 25');
check($ep4['status']==='dk_confirmed','Oct 30 has dated DK schedule evidence');
$ep5=MP_TVmaze::episode_date($marshals,5,['air_date'=>'2026-11-01'],$dk);
check($ep5['date']==='2026-11-06','Next expected Friday Nov 6, not Sunday');
check($ep5['status']==='dk_estimated','Further Fridays marked estimates, not confirmed');
check(MP_TVmaze::dk_schedule(['id'=>5,'title'=>'Marshals','service'=>'Netflix','season'=>2])===null,
    'Do not claim SkyShowtime schedule for Netflix');
check(MP_TVmaze::dk_schedule(['id'=>5,'title'=>'Marshals','service'=>'SkyShowtime','season'=>1])===null,
    'Only season two has verified override');
check(MP_TVmaze::dk_schedule(['id'=>5,'title'=>'Other','service'=>'SkyShowtime','season'=>2])===null,
    'Do not override unrelated shows');
$manual=MP_TVmaze::set_dk_schedule(77,'2026-10-10',true);
check(!is_wp_error($manual),'Manual DK edit stored');
check($manual['schedule']['source_type']==='manual','Manual plan takes precedence');
check(MP_TVmaze::episode_date($marshals,4,$us,$manual['schedule'])['date']==='2026-10-31',
    'Manual Saturday schedule takes precedence over curated Friday');
$bad=MP_TVmaze::set_dk_schedule(77,'2026-02-31',true);
check(is_wp_error($bad),'Invalid dates rejected');
$reset=MP_TVmaze::set_dk_schedule(77,'',false);
check($reset['schedule']['source_type']==='verified','Clearing manual plan restores curated DK Friday');
$GLOBALS['wpdb']->show['season']=3;
$next=MP_TVmaze::set_dk_schedule(77,'2026-10-10',true);
check($next['schedule']['source_type']==='manual','Manual date can apply to new season');
check(MP_TVmaze::dk_schedule($marshals)['source_type']==='verified',
    'Old manually set season must not contaminate prior season');
echo "Danish Friday priority and per-season overrides PASS\n";
