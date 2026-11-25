<?php
/**
 * SEARAH — Pricing: resolve rute + hitung tarif per rute.
 * ------------------------------------------------------------------
 * Sumber kebenaran harga = tabel `routes` + `fares`.
 *   - base/toll/protection tersimpan per (route, package).
 *   - price_unit: 'per_orang' (reguler & door_to_door) atau 'per_kendaraan' (private).
 *   - toll hanya ditagih bila road_type = 'tol'; protection hanya bila insurance = 1.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/Response.php'; // menyediakan AppException

final class Pricing
{
    public const PACKAGES = ['reguler', 'door_to_door', 'private'];

    /** Cari rute aktif dari nama kota asal & tujuan. */
    public static function resolveRoute(string $origin, string $dest): array
    {
        $st = db()->prepare(
            "SELECT r.id, r.origin_city_id, r.dest_city_id, r.distance_km,
                    o.name AS origin_city, d.name AS dest_city
             FROM routes r
             JOIN cities o ON o.id = r.origin_city_id
             JOIN cities d ON d.id = r.dest_city_id
             WHERE r.is_active = 1 AND lower(o.name) = lower(?) AND lower(d.name) = lower(?)"
        );
        $st->execute([$origin, $dest]);
        $route = $st->fetch();
        if (!$route) {
            throw new AppException("Rute {$origin} → {$dest} belum tersedia.", 404);
        }
        return $route;
    }

    /** Ambil tarif aktif utk (route, package). */
    public static function fare(int $routeId, string $package): array
    {
        $st = db()->prepare(
            "SELECT * FROM fares WHERE route_id = ? AND package_type = ? AND is_active = 1"
        );
        $st->execute([$routeId, $package]);
        $fare = $st->fetch();
        if (!$fare) {
            throw new AppException("Tarif paket '{$package}' untuk rute ini belum diatur.", 404);
        }
        return $fare;
    }

    /**
     * Hitung rincian harga.
     * @return array{unit:string,qty:int,base:int,toll:int,protection:int,total:int}
     */
    public static function compute(array $fare, int $pax, string $roadType, bool $insurance): array
    {
        if ($pax < 1 || $pax > 7 || !in_array($roadType, ['biasa','tol'], true)) throw new AppException('Jumlah penumpang atau jalan tidak valid.', 422);
        foreach (['base_price','toll_price','protection_price'] as $key) if ((int)$fare[$key] < 0) throw new AppException('Tarif belum valid.', 503);
        $perVehicle = $fare['price_unit'] === 'per_kendaraan';
        $qty   = $perVehicle ? 1 : max(1, $pax);
        $unit  = $perVehicle ? 'per_kendaraan' : 'per_orang';

        $base       = (int)$fare['base_price'] * $qty;
        $toll       = ($roadType === 'tol') ? (int)$fare['toll_price'] * $qty : 0;
        $protection = $insurance ? (int)$fare['protection_price'] * $qty : 0;
        $total      = $base + $toll + $protection;

        return [
            'unit'        => $unit,
            'qty'         => $qty,
            'base'        => $base,
            'toll'        => $toll,
            'protection'  => $protection,
            'total'       => $total,
            'unit_price'  => (int)$fare['base_price'],
        ];
    }

    /** Helper satu panggilan: rute + tarif + rincian (untuk endpoint /tarif). */
    public static function quote(string $origin, string $dest, string $package, int $pax, string $road, bool $insurance): array
    {
        $route = self::resolveRoute($origin, $dest);
        $fare  = self::fare((int)$route['id'], $package);
        $calc  = self::compute($fare, $pax, $road, $insurance);
        return ['route' => $route, 'fare' => $fare, 'calc' => $calc];
    }
}
