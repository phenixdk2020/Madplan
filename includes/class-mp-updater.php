<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * GitHub update channel. All upgrades go through native WordPress upgrader.
 * Manifest and ZIP must match one exact trusted repository and SHA-256.
 */
final class MP_Updater {
    public const PLUGIN = 'hangar18-madplan/hangar18-madplan.php';
    private const SLUG = 'hangar18-madplan';
    private const REPO = 'https://github.com/phenixdk2020/Madplan';
    private const MANIFEST = 'https://raw.githubusercontent.com/phenixdk2020/Madplan/main/update.json';
    private const CACHE = 'mp_github_updater_v1';

    public static function register(): void {
        add_filter('pre_set_site_transient_update_plugins',[self::class,'updates']);
        // Native WordPress 5.8+ Update URI support for custom GitHub-hosted plugins.
        add_filter('update_plugins_github.com',[self::class,'github_update_uri'],10,4);
        add_filter('plugins_api',[self::class,'plugin_info'],20,3);
        add_filter('upgrader_pre_download',[self::class,'verify_download'],20,4);
        add_action('upgrader_process_complete',[self::class,'clear_cache'],10,2);
        add_action('admin_post_mp_update_check',[self::class,'manual_check']);
        add_action('admin_post_mp_test_backup',[self::class,'test_backup']);
        add_action('admin_post_mp_update_now',[self::class,'install_now']);
        add_action('admin_post_mp_rollback',[self::class,'rollback']);
        add_action('admin_menu',[self::class,'add_page']);
    }
    public static function clear_cache($upgrader = null, $extra = null): void {
        delete_site_transient(self::CACHE);
        delete_site_transient('update_plugins');
    }
    private static function valid(array $data): bool {
        $version = (string)($data['version'] ?? '');
        $sha = strtolower((string)($data['sha256'] ?? ''));
        if (($data['slug'] ?? '') !== self::SLUG ||
            !preg_match('/^\d+\.\d+\.\d+(?:-(?:alpha|beta|rc)\.\d+)?$/', $version) ||
            !preg_match('/^[a-f0-9]{64}$/', $sha)) { return false; }
        $expected = 'https://raw.githubusercontent.com/phenixdk2020/Madplan/main/dist/hangar18-madplan-v' . $version . '.zip';
        return hash_equals($expected, (string)($data['package'] ?? ''));
    }
    public static function manifest(bool $refresh = false): ?array {
        if (!$refresh) {
            $cached = get_site_transient(self::CACHE);
            if (is_array($cached) && self::valid($cached)) { return $cached; }
        }
        $res = wp_safe_remote_get(self::MANIFEST, [
            'timeout'=>15, 'redirection'=>2,
            'headers'=>['Accept'=>'application/json','Cache-Control'=>'no-cache'],
            'user-agent'=>'Hangar18-Madplan/' . MP_VERSION,
        ]);
        if (is_wp_error($res) || (int)wp_remote_retrieve_response_code($res)!==200) { return null; }
        $payload = json_decode((string)wp_remote_retrieve_body($res), true);
        if (!is_array($payload) || !self::valid($payload)) { return null; }
        set_site_transient(self::CACHE,$payload,30*MINUTE_IN_SECONDS);
        return $payload;
    }
    public static function status(bool $refresh = false): array {
        $m = self::manifest($refresh);
        return [
            'ok'=>is_array($m),'current'=>MP_VERSION,
            'latest'=>(string)($m['version'] ?? ''),
            'available'=>is_array($m) && version_compare((string)$m['version'], MP_VERSION, '>'),
        ];
    }
    private static function update_obj(array $m): stdClass {
        $obj = new stdClass();
        $obj->id = self::REPO;
        $obj->slug = self::SLUG;
        $obj->plugin = self::PLUGIN;
        $obj->new_version = (string)$m['version'];
        $obj->url = self::REPO;
        $obj->package = (string)$m['package'];
        $obj->requires = '6.4';
        $obj->requires_php = '8.0';
        return $obj;
    }
    public static function github_update_uri($result, array $plugin_data, string $plugin_file, array $locales) {
        if ($plugin_file !== self::PLUGIN || (string)($plugin_data['UpdateURI'] ?? '') !== self::REPO) { return $result; }
        $manifest = self::manifest();
        if (!$manifest) { return $result; }
        $item = (array)self::update_obj($manifest);
        $item['version'] = $item['new_version'];
        return $item;
    }
    private static function record_error(string $message): void {
        set_transient('mp_update_error_' . get_current_user_id(), sanitize_text_field($message), 10 * MINUTE_IN_SECONDS);
    }
    public static function updates($transient) {
        if (!is_object($transient) || !isset($transient->checked) || !is_array($transient->checked)) {
            return $transient;
        }
        $m = self::manifest();
        if (!$m) { return $transient; }
        $item = self::update_obj($m);
        if (version_compare((string)$m['version'], MP_VERSION,'>')) {
            $transient->response[self::PLUGIN] = $item;
            unset($transient->no_update[self::PLUGIN]);
        } else {
            $transient->no_update[self::PLUGIN] = $item;
            unset($transient->response[self::PLUGIN]);
        }
        return $transient;
    }
    public static function plugin_info($response, string $action, $args) {
        if ($action !== 'plugin_information' || !is_object($args) ||
            (string)($args->slug ?? '') !== self::SLUG) { return $response; }
        $m = self::manifest();
        if (!$m) { return $response; }
        $obj = new stdClass();
        $obj->name = 'Hangar18 Madplan & TV-plan';
        $obj->slug = self::SLUG;
        $obj->version = (string)$m['version'];
        $obj->author = 'Hangar18';
        $obj->homepage = self::REPO;
        $obj->requires = '6.4';
        $obj->requires_php = '8.0';
        $obj->download_link = (string)$m['package'];
        $obj->sections = [
            'description'=>'Fælles mobilvenlig madplan, TV-watchlist og opskrifter til Hangar18.',
            'changelog'=>'Version ' . esc_html((string)$m['version']) . '. Se GitHub README.',
        ];
        return $obj;
    }
    /**
     * Updater hooks have plugin in hook_extra for individual/bulk updates.
     * We refuse unpinned/altered packages before the installer is allowed to run.
     */
    public static function verify_download($response, string $package, $upgrader, array $extra = []) {
        if ($response !== false) { return $response; }
        $plugin = (string)($extra['plugin'] ?? '');
        $list = is_array($extra['plugins'] ?? null) ? $extra['plugins'] : [];
        if ($plugin !== self::PLUGIN && !in_array(self::PLUGIN, $list, true)) { return false; }
        $m = self::manifest(true);
        if (!$m || !hash_equals((string)$m['package'],$package)) {
            return new WP_Error('mp_untrusted_package','Downloadpakken stemmer ikke overens med Madplans publicerede GitHub-manifest.');
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $file = download_url($package, 120, false);
        if (is_wp_error($file)) { return $file; }
        $result = self::verify_zip($file, (string)$m['sha256'], (string)$m['version']);
        if (is_wp_error($result)) { @unlink($file); return $result; }
        return $file;
    }
    public static function verify_zip(string $file, string $sha, string $version = ''): true|WP_Error {
        if (!is_file($file) || !preg_match('/^[a-f0-9]{64}$/',$sha) ||
            !hash_equals($sha, strtolower((string)hash_file('sha256',$file)))) {
            return new WP_Error('mp_zip_sha','Opdatering afvist: SHA-256 matcher ikke.');
        }
        if (!class_exists(ZipArchive::class)) {
            return new WP_Error('mp_zip_extension','PHP ZipArchive mangler.');
        }
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) { return new WP_Error('mp_invalid_zip','Kan ikke åbne ZIP.'); }
        $main = $zip->getFromName(self::PLUGIN);
        if (!is_string($main) || !str_contains($main,'Plugin Name: Hangar18 Madplan & TV-plan')) {
            $zip->close();
            return new WP_Error('mp_zip_identity','ZIP indeholder ikke den forventede pluginfil.');
        }
        if ($version !== '' && !str_contains($main,'Version: ' . $version)) {
            $zip->close();
            return new WP_Error('mp_zip_version','ZIP-versionen stemmer ikke overens med manifestet.');
        }
        for ($i=0; $i<$zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            $parts = explode('/',$name);
            if (!str_starts_with($name,'hangar18-madplan/') || str_contains($name,'\\') ||
                in_array('..',$parts,true) || str_starts_with($name,'/')) {
                $zip->close();
                return new WP_Error('mp_zip_path','ZIP indeholder en ugyldig sti.');
            }
        }
        $zip->close();
        return true;
    }
    private static function redirect(string $status): never {
        wp_safe_redirect(add_query_arg('mp_result',$status,admin_url('admin.php?page=mp-updates')));
        exit;
    }
    public static function manual_check(): void {
        if (!current_user_can('update_plugins')) { wp_die('Ingen adgang.'); }
        check_admin_referer('mp_check_update');
        self::clear_cache();
        wp_clean_plugins_cache(true);
        require_once ABSPATH . 'wp-admin/includes/update.php';
        wp_update_plugins();
        $s = self::status(true);
        self::redirect(!$s['ok']?'network-error':($s['available']?'available':'current'));
    }
    public static function install_now(): void {
        if (!current_user_can('update_plugins')) { wp_die('Ingen adgang.'); }
        check_admin_referer('mp_install_update');
        $s = self::status(true);
        if (!$s['ok'] || !$s['available']) { self::redirect('no-update'); }
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/update.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        self::clear_cache();
        wp_clean_plugins_cache(true);
        wp_update_plugins();
        $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
        $result = $upgrader->upgrade(self::PLUGIN);
        if ($result === false || is_wp_error($result)) {
            $reason = is_wp_error($result) ? $result->get_error_message() : 'WordPress-opdateringen returnerede false. Kontroller filrettigheder og backupmappen.';
            self::record_error($reason);
            self::redirect('install-error');
        }
        self::clear_cache();
        self::redirect('installed');
    }
    public static function test_backup(): void {
        if (!current_user_can('update_plugins')) { wp_die('Ingen adgang.'); }
        check_admin_referer('mp_test_backup');
        $result = MP_Checkpoints::snapshot('Manuel testbackup');
        if (is_wp_error($result)) {
            self::record_error($result->get_error_message());
            self::redirect('backup-error');
        }
        self::redirect('backup-ok');
    }
    public static function rollback(): void {
        if (!current_user_can('update_plugins')) { wp_die('Ingen adgang.'); }
        $id = sanitize_text_field(wp_unslash($_POST['checkpoint_id'] ?? ''));
        check_admin_referer('mp_rollback_' . $id);
        $row = MP_Checkpoints::find($id);
        if (!$row) { self::redirect('rollback-error'); }
        $file = MP_Checkpoints::validated_file($row,'program');
        if (is_wp_error($file)) { self::redirect('rollback-error'); }
        $valid = self::verify_zip($file,(string)$row['program_sha'],(string)$row['version']);
        if (is_wp_error($valid)) { self::redirect('rollback-error'); }
        // A rollback must not discard newer user data. We snapshot both first.
        $checkpoint = MP_Checkpoints::snapshot('Før gendannelse af program ' . (string)$row['version']);
        if (is_wp_error($checkpoint)) { self::redirect('backup-error'); }
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $wasActive = is_plugin_active(self::PLUGIN);
        $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
        $result = $upgrader->install($file,['overwrite_package'=>true]);
        if ($result !== true) { self::redirect('rollback-error'); }
        if ($wasActive && !is_plugin_active(self::PLUGIN)) {
            $activation = activate_plugin(self::PLUGIN);
            if (is_wp_error($activation)) { self::redirect('rollback-error'); }
        }
        self::clear_cache();
        self::redirect('rolled-back');
    }
    public static function add_page(): void {
        add_submenu_page('mp-hangar18','Opdateringer','Opdateringer','update_plugins','mp-updates',[self::class,'page']);
    }
    public static function page(): void {
        if (!current_user_can('update_plugins')) { wp_die('Ingen adgang.'); }
        $s = self::status();
        $result = sanitize_key((string)($_GET['mp_result'] ?? ''));
        $notices = [
            'installed'=>'Opdatering installeret.','rolled-back'=>'Programkoden er gendannet. Data blev ikke rullet tilbage.',
            'current'=>'Pluginet er opdateret.','available'=>'En nyere version er tilgængelig.',
            'network-error'=>'Kunne ikke hente et gyldigt manifest fra GitHub.',
            'no-update'=>'Ingen nyere version er klar.','install-error'=>'WordPress kunne ikke installere opdateringen.',
            'rollback-error'=>'Programgendannelsen fejlede eller backupfilen kunne ikke valideres.',
            'backup-error'=>'Backup kunne ikke oprettes. Ingen opdatering/gendannelse blev gennemført.',
            'backup-ok'=>'Testbackup er oprettet og kontrolleret. Opdateringsforudsætningen er på plads.',
        ];
        echo '<div class="wrap"><h1>Hangar18 – Opdateringer</h1>';
        $lastError = (string)get_transient('mp_update_error_' . get_current_user_id());
        if ($lastError !== '') {
            echo '<div class="notice notice-error"><p><strong>Teknisk fejl:</strong> ' . esc_html($lastError) . '</p></div>';
        }
        if (isset($notices[$result])) {
            $cls = in_array($result,['installed','rolled-back','current'],true)?'notice-success':'notice-warning';
            echo '<div class="notice '.esc_attr($cls).'"><p>'.esc_html($notices[$result]).'</p></div>';
        }
        echo '<div class="card" style="max-width:none"><h2>Version</h2>';
        echo '<p>Installeret: <strong>'.esc_html(MP_VERSION).'</strong></p>';
        echo '<p>GitHub: <strong>'.esc_html($s['ok']?$s['latest']:'Manifest ikke tilgængeligt').'</strong></p>';
        if ($s['available']) { echo '<p><strong>Ny version tilgængelig</strong></p>'; }
        echo '<p>Opdateringspakken kontrolleres med SHA-256, og før opdateringen tages backup af både pluginfiler og Madplan-data. Backups skal ligge uden for webroden.</p>';
        $check = wp_nonce_url(admin_url('admin-post.php?action=mp_update_check'),'mp_check_update');
        echo '<p><a class="button" href="'.esc_url($check).'">Tjek GitHub-opdatering</a> ';
        if ($s['available']) {
            $install = wp_nonce_url(admin_url('admin-post.php?action=mp_update_now'),'mp_install_update');
            echo '<a class="button button-primary" href="'.esc_url($install).'">Opdater nu</a> ';
        }
        echo '<a class="button" href="'.esc_url(self::REPO.'/actions').'" target="_blank" rel="noopener">GitHub-builds</a></p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="mp_test_backup">';
        wp_nonce_field('mp_test_backup');
        echo '<p><button type="submit" class="button">Test backup og rettigheder</button> <span class="description">Opretter et program- og datacheckpoint, så fejl bliver vist før næste opdatering.</span></p></form>';
        echo '<p class="description">Ved fejl: Serveren skal kunne hente HTTPS fra raw.githubusercontent.com og gemme backups privat uden for webroden. Opdateringer installeres fra <code>update.json</code>.</p></div>';
        echo '<div class="card" style="max-width:none"><h2>Backup og rollback</h2>';
        echo '<p>Op til 12 checkpoints gemmes. <strong>Gendan program</strong> skifter kun plugin-kode tilbage, aldrig madplaner eller opskrifter. Data-checkpointet kan downloades til manuel gendannelse. Billedfiler i WordPress Mediebibliotek er ikke inkluderet.</p>';
        $rows = MP_Checkpoints::rows();
        if (!$rows) { echo '<p>Ingen checkpoints endnu. Det første dannes ved næste opdatering.</p>'; }
        else {
            echo '<table class="widefat striped"><thead><tr><th>Dato</th><th>Version</th><th>Program</th><th>Data</th><th>Handling</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $id=(string)($row['id']??'');
                if ($id==='') { continue; }
                echo '<tr><td>'.esc_html((string)($row['created']??'')).'</td><td>'.esc_html((string)($row['version']??'')).'</td>';
                foreach (['program','data'] as $kind) {
                    echo '<td><a href="'.esc_url(MP_Checkpoints::download_url($id,$kind)).'">Download '.esc_html($kind).'</a></td>';
                }
                echo '<td>';
                if ((string)($row['version']??'')!==MP_VERSION) {
                    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Gendan kun tidligere programkode? Nye data bevares.\')">';
                    echo '<input type="hidden" name="action" value="mp_rollback"><input type="hidden" name="checkpoint_id" value="'.esc_attr($id).'">';
                    wp_nonce_field('mp_rollback_'.$id);
                    echo '<button class="button" type="submit">Gendan program</button></form>';
                } else { echo 'Installeret version'; }
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div></div>';
    }
}
