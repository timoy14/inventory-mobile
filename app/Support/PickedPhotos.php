<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Photos chosen through the native gallery/camera, held as temporary file paths in the device database
 * (the native events endpoint runs without a session, so nothing here may depend on one).
 * The plugin never deletes its files, so every path is unlinked once used or discarded.
 * Paths are only ever written by the native event listener, never taken from a request.
 */
class PickedPhotos
{
    private const TABLE = 'picked_photos';

    public const MAX_BYTES = 2 * 1024 * 1024;

    /**
     * Only paths still inside an allowed root are ever returned, so nothing outside can be uploaded or unlinked.
     *
     * @return list<string>
     */
    public static function paths(): array
    {
        return array_values(array_filter(
            array_map(self::confine(...), DB::table(self::TABLE)->orderBy('id')->pluck('path')->all())
        ));
    }

    /**
     * Where the camera plugin writes: Android {noBackupFilesDir}/Camera|Gallery, iOS
     * ~/Library/Application Support/Camera|Gallery, plus the system temp dir.
     *
     * @return list<string>
     */
    public static function roots(): array
    {
        // Not memoized: the plugin creates Camera/Gallery on first use, so a root can appear mid-process.
        $bases = [dirname(base_path()), dirname(base_path(), 2), dirname(base_path(), 3), dirname(base_path(), 4)];
        if ($home = getenv('HOME')) {
            $bases[] = $home;
        }

        $candidates = [sys_get_temp_dir()];
        foreach ($bases as $base) {
            foreach (['Camera', 'Gallery'] as $dir) {
                $candidates[] = "{$base}/no_backup/{$dir}";
                $candidates[] = "{$base}/Library/Application Support/{$dir}";
            }
        }

        return array_values(array_unique(array_filter(array_map('realpath', $candidates))));
    }

    /** The real path of $path when it exists and lies strictly under an allowed root, else null. */
    public static function confine(string $path): ?string
    {
        $real = realpath($path);

        if ($real === false || ! is_file($real)) {
            return null;
        }

        foreach (self::roots() as $root) {
            if (str_starts_with($real, rtrim($root, '/').'/')) {
                return $real;
            }
        }

        return null;
    }

    /** @param  list<string>  $paths */
    public static function add(array $paths): void
    {
        $accepted = array_filter(array_map(self::confine(...), $paths));

        foreach ($accepted as $path) {
            DB::table(self::TABLE)->updateOrInsert(['path' => $path]);
        }
    }

    public static function discard(int $index): void
    {
        $paths = self::paths();

        if (isset($paths[$index])) {
            self::release([$paths[$index]]);
        }
    }

    /**
     * Props for the form: a name and a size per pending photo, never the raw path.
     *
     * @return array<int, array{name: string, usable: bool}>
     */
    public static function summary(): array
    {
        return collect(self::paths())->map(fn (string $path) => [
            'name' => basename($path),
            'usable' => self::usable($path),
        ])->all();
    }

    private static function remove(string $path): void
    {
        if (($real = self::confine($path)) !== null) {
            @unlink($real);
        }
    }

    public static function usable(string $path): bool
    {
        return self::confine($path) !== null && filesize($path) <= self::MAX_BYTES && @getimagesize($path) !== false;
    }

    /**
     * Unlink the given paths and forget them.
     *
     * @param  list<string>  $used
     */
    public static function release(array $used): void
    {
        $keys = array_map(fn ($p) => self::confine($p) ?? $p, $used);

        foreach ($used as $path) {
            self::remove($path);
        }

        DB::table(self::TABLE)->whereIn('path', $keys)->delete();
        // Also forget rows that no longer resolve to an allowed file.
        foreach (DB::table(self::TABLE)->pluck('path') as $stored) {
            if (self::confine($stored) === null) {
                DB::table(self::TABLE)->where('path', $stored)->delete();
            }
        }
    }

    /** Unlink and forget everything pending (sign-out). */
    public static function purge(): void
    {
        self::release(self::paths());
        DB::table(self::TABLE)->delete();
    }
}
