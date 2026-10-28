<?php
declare(strict_types=1);
define('ABSPATH','/fake/');
define('HOUR_IN_SECONDS',3600);
class WP_Error { public function __construct(public string $code,public string $message,public array $data=[]){} }
$GLOBALS['uid']=101;
$GLOBALS['meta']=[101=>['mp_streaming_subscriptions_v1'=>['Netflix']],202=>['mp_streaming_subscriptions_v1'=>['Prime Video']]];
$GLOBALS['opt']=['mp_tmdb_token_v1'=>'test-key-with-enough-length-012345'];
$GLOBALS['cache']=[];
function get_current_user_id(){return $GLOBALS['uid'];}
function get_user_meta($id,$key,$single){return $GLOBALS['meta'][$id][$key]??[];}
function update_user_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;return true;}
function get_option($key,$default=''){return array_key_exists($key,$GLOBALS['opt'])?$GLOBALS['opt'][$key]:$default;}
function update_option($key,$val,$a=false){$GLOBALS['opt'][$key]=$val;return true;}
function delete_option($key){unset($GLOBALS['opt'][$key]);}
function get_transient($key){return $GLOBALS['cache'][$key]??false;}
function set_transient($key,$value,$ttl){$GLOBALS['cache'][$key]=$value;return true;}
function absint($v){return abs((int)$v);}
function esc_url_raw($v){return $v;}
function is_wp_error($v){return $v instanceof WP_Error;}
function wp_safe_remote_get($url,$options){
 $path=parse_url($url,PHP_URL_PATH);
 if($path==='/3/search/tv')return ['code'=>200,'body'=>json_encode(['results'=>[['id'=>555,'name'=>'Demo','first_air_date'=>'2025-01-01']]])];
 if($path==='/3/tv/555/watch/providers')return ['code'=>200,'body'=>json_encode(['results'=>['DK'=>['flatrate'=>[['provider_name'=>'Netflix'],['provider_name'=>'Amazon Prime Video']],'rent'=>[['provider_name'=>'Apple TV+']]]]])];
 return ['code'=>404,'body'=>'{}'];
}
function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
class MP_TVmaze { public static function details($id){return ['title'=>'Demo','premiered'=>'2025-01-01'];} }
require dirname(__DIR__).'/includes/class-mp-streaming.php';
function verify($ok,$label){if(!$ok)throw new RuntimeException('FAIL: '.$label);}

verify(MP_Streaming::chosen()===['Netflix'],'legacy selections migrated once to shared option');
verify(($GLOBALS['opt']['mp_household_streaming_services_v1']??null)===['Netflix'],'migration stored in wp_options');
$GLOBALS['uid']=202;
verify(MP_Streaming::chosen()===['Netflix'],'second family user sees same services despite old personal choice');
verify(is_wp_error(MP_Streaming::save_shared(['Bad'])),'invalid services rejected');
verify(is_wp_error(MP_Streaming::save_shared([123])),'non-string services rejected');
verify(MP_Streaming::chosen()===['Netflix'],'failed updates do not change shared choice');
$res=MP_Streaming::save_shared(['Prime Video','Prime Video']);
verify($res['saved']===true && $res['shared']===true,'shared save confirmed');
verify(MP_Streaming::chosen()===['Prime Video'],'shared save deduplicates');
$GLOBALS['uid']=101;
verify(MP_Streaming::chosen()===['Prime Video'],'original family user immediately sees update from second user');
verify(MP_Streaming::lookup(5)['matches']===['Prime Video'],'TMDB lookup uses shared Prime Video');
$GLOBALS['uid']=202;
MP_Streaming::save_shared(['Netflix','Prime Video']);
verify(MP_Streaming::lookup(5)['matches']===['Netflix','Prime Video'],'lookup finds both shared subscriptions');
MP_Streaming::save_shared(['Apple TV+']);
verify(MP_Streaming::lookup(5)['status']==='not_listed','rental not treated as subscribed');
verify(MP_Streaming::canonical_provider('Apple TV')==='','Apple TV storefront not Apple TV+');
$twins=[['id'=>1,'name'=>'Demo','first_air_date'=>'2000-01-01'],['id'=>2,'name'=>'Demo','first_air_date'=>'2025-01-01']];
verify(MP_Streaming::identify_show($twins,'Demo','')===0,'ambiguous remakes refused');
verify(MP_Streaming::identify_show($twins,'Demo','2025-01-01')===2,'correct year match');
$GLOBALS['uid']=101;
MP_Streaming::save_shared([]);
verify(MP_Streaming::chosen()===[],'all subscriptions may be unchecked');
$GLOBALS['uid']=202;
verify(MP_Streaming::chosen()===[],'empty global list must not resurrect old user preferences');
verify(MP_Streaming::lookup(5)['status']==='no_services','no services fallback');
MP_Streaming::save_shared(['Netflix']);
$GLOBALS['opt']['mp_tmdb_token_v1']='';
verify(MP_Streaming::lookup(5)['status']==='not_configured','no key fallback');
echo "DK shared household streaming subscriptions PASS\n";
