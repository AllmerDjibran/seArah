<?php
/**
 * SEARAH — Scope wilayah admin.
 * Admin tanpa user_region_scopes = SUPER ADMIN (semua wilayah, return null).
 * Koordinator wilayah -> array region_id.
 */
require_once __DIR__ . '/../db.php';

final class Scope
{
    /** @return int[]|null null = semua wilayah */
    public static function regionIds(array $user): ?array
    {
        if (($user['role'] ?? '') !== 'admin') throw new RuntimeException('Akses ditolak.', 403);
        $cfg = require __DIR__ . '/../config.php';
        $ids = array_filter(array_map('intval', explode(',', getenv('SEARAH_SUPER_ADMIN_IDS') ?: '')));
        if (in_array((int)$user['id'], $ids, true)) return null;
        $st = db()->prepare("SELECT region_id FROM user_region_scopes WHERE user_id = ?");
        $st->execute([$user['id']]);
        $rows = $st->fetchAll(PDO::FETCH_COLUMN);
        if (!$rows) throw new RuntimeException('Admin belum memiliki scope wilayah eksplisit.', 403);
        return array_map('intval', $rows);
    }

    public static function assertRegion(array $user, ?int $region): void
    {
        $ids = self::regionIds($user);
        if ($ids !== null && ($region === null || !in_array($region, $ids, true))) throw new RuntimeException('Di luar wilayah wewenang Anda.', 403);
    }

    public static function assertRoute(array $user, int $route): void
    {
        $st = db()->prepare('SELECT c.region_id FROM routes r JOIN cities c ON c.id=r.origin_city_id WHERE r.id=?');
        $st->execute([$route]);
        $region = $st->fetchColumn();
        if (!$region) throw new RuntimeException('Rute tidak valid.', 422);
        self::assertRegion($user, (int)$region);
    }

    /** Bangkitkan klausa SQL filter region utk kota asal rute. */
    public static function cityRegionFilter(?array $regionIds, string $alias = 'c'): string
    {
        if ($regionIds === null) return '';
        $in = implode(',', array_fill(0, count($regionIds), '?'));
        return " AND $alias.region_id IN ($in)";
    }
}
