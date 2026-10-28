<?php
declare(strict_types=1);
/** Isolated WordPress plugin-upgrader activation regression tests. */
$base=sys_get_temp_dir().'/mp-updater-'.bin2hex(random_bytes(5));
define('ABSPATH',$base.'/');
define('WP_PLUGIN_DIR',$base.'/wp-content/plugins');
@mkdir(ABSPATH.'wp-admin/includes',0770,true);
@mkdir(WP_PLUGIN_DIR.'/hangar18-madplan',0770,true);
file_put_contents(ABSPATH.'wp-admin/includes/plugin.php',"<?php\n");
$file=WP_PLUGIN_DIR.'/hangar18-madplan/hangar18-madplan.php';
file_put_contents($file,"<?php\n/* Plugin Name: Hangar18 Madplan & TV-plan */\n");
class WP_Error {
    public function __construct(public string $code,private string $message) {}
    public function get_error_message(): string { return $this->message; }
}
function is_wp_error($x): bool { return $x instanceof WP_Error; }
$GLOBALS['mp_active']=false;
$GLOBALS['mp_network_active']=false;
$GLOBALS['mp_activation_calls']=[];
$GLOBALS['mp_activation_failure']=false;
function is_plugin_active(string $id): bool { return $GLOBALS['mp_active']; }
function is_plugin_active_for_network(string $id): bool { return $GLOBALS['mp_network_active']; }
function activate_plugin(string $plugin,string $redirect='',bool $network_wide=false,bool $silent=false) {
    $GLOBALS['mp_activation_calls'][]=[$plugin,$network_wide,$silent];
    if ($GLOBALS['mp_activation_failure']) { return new WP_Error('test_failure','Mock activation failed'); }
    $GLOBALS['mp_active']=true;
    $GLOBALS['mp_network_active']=$network_wide;
    return null;
}
function get_current_user_id(): int { return 101; }
function set_transient(string $name,$value,int $seconds) { return true; }
require_once dirname(__DIR__).'/includes/class-mp-updater.php';
function ensure(bool $condition,string $message): void {
    if (!$condition) { throw new RuntimeException('FAIL: '.$message); }
}
$plugin=MP_Updater::PLUGIN;
$context=['plugin'=>$plugin,'type'=>'plugin','action'=>'update'];
$r=MP_Updater::restore_activation(false,false);
ensure($r===true && !$GLOBALS['mp_active'] && !count($GLOBALS['mp_activation_calls']),
    'Inactive plugin remains inactive');
$GLOBALS['mp_active']=true;
MP_Updater::capture_activation(true,$context);
$GLOBALS['mp_active']=false;
$r=MP_Updater::reactivate_after_install(true,$context);
ensure($r===true && $GLOBALS['mp_active'],'Previously active plugin is reactivated');
ensure(count($GLOBALS['mp_activation_calls'])===1,'Exactly one activation');
ensure($GLOBALS['mp_activation_calls'][0]===[$plugin,false,true],'Silent activation uses exact plugin slug');
$GLOBALS['mp_active']=true;
$GLOBALS['mp_network_active']=true;
MP_Updater::capture_activation(true,$context);
$GLOBALS['mp_active']=false;
$r=MP_Updater::reactivate_after_install(true,$context);
ensure($r===true && $GLOBALS['mp_network_active'],'Network activation preserved');
$before=count($GLOBALS['mp_activation_calls']);
$r=MP_Updater::restore_activation(true,false);
ensure($r===true && count($GLOBALS['mp_activation_calls'])===$before,'Do not activate twice');
$GLOBALS['mp_active']=false;
$r=MP_Updater::reactivate_after_install(true,[
    'plugin'=>'something-else/test.php','type'=>'plugin','action'=>'update'
]);
ensure($r===true && !$GLOBALS['mp_active'],'Other plugin ignored');
$GLOBALS['mp_activation_failure']=true;
$r=MP_Updater::restore_activation(true,false);
ensure($r instanceof WP_Error && str_contains($r->get_error_message(),'Mock activation failed'),
    'Reactivation errors surfaced');
$GLOBALS['mp_activation_failure']=false;
unlink($file);
$r=MP_Updater::restore_activation(true,false);
ensure($r instanceof WP_Error,'Missing plugin file is a recoverable error');
@unlink(ABSPATH.'wp-admin/includes/plugin.php');
@rmdir(ABSPATH.'wp-admin/includes');
@rmdir(ABSPATH.'wp-admin');
@rmdir(WP_PLUGIN_DIR.'/hangar18-madplan');
@rmdir(WP_PLUGIN_DIR);
@rmdir($base.'/wp-content');
@rmdir($base);
echo "WordPress plugin reactivation contract PASS\n";
