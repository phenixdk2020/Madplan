<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/** Creates paired program/data checkpoints before upgrading this plugin. */
final class MP_Checkpoints {
    private const KEY = 'mp_update_checkpoints_v1';
    private static bool $created = false;

    public static function register(): void {
        add_filter('upgrader_pre_install', [self::class, 'pre_install'], 5, 2);
        add_action('admin_post_mp_backup_download', [self::class, 'download']);
    }
    public static function rows(): array {
        $rows = get_option(self::KEY, []);
        return is_array($rows) ? $rows : [];
    }
    private static function folder(): string|WP_Error {
        $directory = defined('MP_BACKUP_DIR') ? (string)MP_BACKUP_DIR :
            dirname(untrailingslashit(ABSPATH)) . '/hangar18-madplan-backups';
        if (!is_dir($directory) && !wp_mkdir_p($directory)) {
            return new WP_Error('mp_backup_folder', 'Kunne ikke oprette privat backupmappe. Indstil MP_BACKUP_DIR i wp-config.php.');
        }
        $real = realpath($directory);
        $site = realpath(ABSPATH);
        $content = realpath(WP_CONTENT_DIR);
        if (!$real || !$site || !$content) {
            return new WP_Error('mp_backup_path', 'Backupplacering kunne ikke verificeres.');
        }
        foreach ([$site, $content] as $unsafe) {
            if ($real === $unsafe || str_starts_with($real, rtrim($unsafe, '/') . '/')) {
                return new WP_Error('mp_backup_public', 'Backupmappen må ikke ligge under WordPress webroden. Angiv MP_BACKUP_DIR udenfor.');
            }
        }
        return $real;
    }
    public static function pre_install($result, array $extra = []) {
        if (is_wp_error($result) || self::$created) { return $result; }
        $plugin = (string)($extra['plugin'] ?? '');
        $plugins = is_array($extra['plugins'] ?? null) ? $extra['plugins'] : [];
        if ($plugin !== MP_Updater::PLUGIN && !in_array(MP_Updater::PLUGIN, $plugins, true)) { return $result; }
        $saved = self::snapshot('Opdatering via WordPress');
        if (is_wp_error($saved)) {
            return new WP_Error('mp_backup_failed', 'Opdateringen blev stoppet. Program- og databackup fejlede: ' . $saved->get_error_message());
        }
        self::$created = true;
        return $result;
    }
    public static function snapshot(string $reason): array|WP_Error {
        if (!class_exists(ZipArchive::class)) { return new WP_Error('mp_zip_extension', 'PHP ZipArchive mangler.'); }
        $dir = self::folder();
        if (is_wp_error($dir)) { return $dir; }
        $id = bin2hex(random_bytes(12));
        $prefix = 'madplan-' . sanitize_file_name(MP_VERSION) . '-' . gmdate('Ymd-His') . '-' . $id;
        $program = $dir . '/' . $prefix . '-program.zip';
        $data = $dir . '/' . $prefix . '-data.zip';
        $result = self::program_zip($program);
        if (is_wp_error($result)) { @unlink($program); return $result; }
        $result = self::data_zip($data);
        if (is_wp_error($result)) { @unlink($program); @unlink($data); return $result; }
        $ph = hash_file('sha256', $program);
        $dh = hash_file('sha256', $data);
        if (!$ph || !$dh || filesize($program) < 100 || filesize($data) < 50) {
            @unlink($program); @unlink($data);
            return new WP_Error('mp_backup_empty', 'Backups kunne ikke verificeres.');
        }
        $row = [
            'id'=>$id, 'created'=>gmdate('c'), 'version'=>MP_VERSION,
            'reason'=>sanitize_text_field($reason),
            'program'=>basename($program), 'data'=>basename($data),
            'program_sha'=>$ph, 'data_sha'=>$dh,
            'program_bytes'=>filesize($program), 'data_bytes'=>filesize($data),
        ];
        $rows = self::rows(); array_unshift($rows, $row);
        update_option(self::KEY, array_slice($rows, 0, 12), false);
        foreach (array_slice($rows, 12) as $old) {
            foreach (['program','data'] as $kind) {
                $file = (string)($old[$kind] ?? '');
                if (preg_match('/^madplan-[a-zA-Z0-9._-]+-(?:program|data)\.zip$/', $file)) {
                    @unlink($dir . '/' . basename($file));
                }
            }
        }
        return $row;
    }
    private static function program_zip(string $path): bool|WP_Error {
        $base = realpath(MP_DIR);
        if (!$base) { return new WP_Error('mp_program_missing','Pluginmappen mangler.'); }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return new WP_Error('mp_zip_open','Programbackup kunne ikke åbnes.');
        }
        try {
            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
            );
            $count = 0;
            foreach ($iter as $file) {
                if (!$file->isFile() || $file->isLink()) { continue; }
                $real = $file->getRealPath();
                if (!$real || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) { continue; }
                $relative = 'hangar18-madplan/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($base) + 1));
                if (!$zip->addFile($real, $relative) || ++$count > 10000) {
                    throw new RuntimeException('Kunne ikke pakke programfiler.');
                }
            }
            if (!$count || !$zip->close()) { throw new RuntimeException('Programbackup kunne ikke afsluttes.'); }
        } catch (Throwable $e) {
            $zip->close();
            return new WP_Error('mp_program_zip',$e->getMessage());
        }
        return true;
    }
    private static function data_zip(string $path): bool|WP_Error {
        global $wpdb;
        $payload = [
            'format'=>'hangar18-data-checkpoint-1', 'created'=>gmdate('c'), 'version'=>MP_VERSION,
            'media_files_included'=>false,
            'options'=>[
                'mp_people'=>get_option('mp_people'),
                'mp_portal_page'=>get_option('mp_portal_page'),
                'mp_db_version'=>get_option('mp_db_version'),
            ], 'tables'=>[], 'recipes'=>[], 'recipe_meta'=>[],
        ];
        foreach (['meals','shows','episodes','shopping','audit'] as $name) {
            $table = MP_Storage::table($name);
            $rows = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
            if ($rows === null) { return new WP_Error('mp_table_backup','Databaselæsning fejlede: ' . $name); }
            $payload['tables'][$name] = $rows;
        }
        $posts = $wpdb->posts; $meta = $wpdb->postmeta;
        $payload['recipes'] = $wpdb->get_results(
            "SELECT * FROM $posts WHERE post_type='mp_recipe' OR (post_type='revision' AND post_parent IN (SELECT ID FROM $posts WHERE post_type='mp_recipe'))",
            ARRAY_A
        ) ?: [];
        $ids = array_map('intval', array_column($payload['recipes'], 'ID'));
        foreach (array_chunk($ids, 100) as $chunk) {
            $params = implode(',', array_fill(0,count($chunk),'%d'));
            $sql = $wpdb->prepare("SELECT * FROM $meta WHERE post_id IN ($params)", ...$chunk);
            $payload['recipe_meta'] = array_merge($payload['recipe_meta'], $wpdb->get_results($sql,ARRAY_A) ?: []);
        }
        $json = wp_json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || strlen($json) > 50 * 1024 * 1024) {
            return new WP_Error('mp_data_limit','Databackup overstiger 50 MB eller kan ikke serialiseres.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return new WP_Error('mp_data_zip','Databackup kunne ikke oprettes.');
        }
        if (!$zip->addFromString('hangar18-madplan-data.json',$json) || !$zip->close()) {
            return new WP_Error('mp_data_zip_write','Databackup kunne ikke gemmes.');
        }
        return true;
    }
    public static function find(string $id): ?array {
        foreach (self::rows() as $row) {
            if (is_array($row) && hash_equals((string)($row['id'] ?? ''), $id)) { return $row; }
        }
        return null;
    }
    public static function validated_file(array $row, string $kind): string|WP_Error {
        if (!in_array($kind, ['program','data'], true)) { return new WP_Error('mp_backup_type','Ugyldig backuptype.'); }
        $dir = self::folder();
        if (is_wp_error($dir)) { return $dir; }
        $name = (string)($row[$kind] ?? '');
        $digest = (string)($row[$kind.'_sha'] ?? '');
        if (!preg_match('/^madplan-[a-zA-Z0-9._-]+-(?:program|data)\.zip$/', $name) ||
            basename($name) !== $name || !preg_match('/^[a-f0-9]{64}$/',$digest)) {
            return new WP_Error('mp_backup_invalid','Ugyldig backupfil.');
        }
        $path = $dir . '/' . $name;
        if (!is_file($path) || !is_readable($path) ||
            !hash_equals($digest, (string)hash_file('sha256',$path))) {
            return new WP_Error('mp_backup_integrity','Backup mangler eller har forkert SHA-256.');
        }
        return $path;
    }
    public static function download_url(string $id, string $kind): string {
        return wp_nonce_url(admin_url('admin-post.php?action=mp_backup_download&id=' . rawurlencode($id) . '&kind=' . $kind),
            'mp_backup_download_' . $id . '_' . $kind);
    }
    public static function download(): void {
        if (!current_user_can('update_plugins')) { wp_die('Ingen adgang.'); }
        $id = sanitize_text_field(wp_unslash($_GET['id'] ?? ''));
        $kind = sanitize_key((string)($_GET['kind'] ?? ''));
        check_admin_referer('mp_backup_download_' . $id . '_' . $kind);
        $row = self::find($id);
        $file = $row ? self::validated_file($row,$kind) : new WP_Error('mp_backup_unknown','Backup ikke fundet.');
        if (is_wp_error($file)) { wp_die(esc_html($file->get_error_message())); }
        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . (int)filesize($file));
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }
}
