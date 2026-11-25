<?php
/** SEARAH — Pembaca tabel settings (konfigurasi runtime non-rahasia). */
require_once __DIR__ . '/../db.php';

final class Settings
{
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = sin(deg2rad($lat2-$lat1)/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin(deg2rad($lng2-$lng1)/2)**2;
        return 6371 * 2 * asin(sqrt(min(1,max(0,$a))));
    }

    public static function geometry(array $b): array
    {
        $st=db()->prepare('SELECT o.lat AS olat,o.lng AS olng,d.lat AS dlat,d.lng AS dlng FROM routes r JOIN cities o ON o.id=r.origin_city_id JOIN cities d ON d.id=r.dest_city_id WHERE r.id=?');
        $st->execute([$b['route_id']]); $r=$st->fetch();
        if (!$r || in_array(null,$r,true)) throw new RuntimeException('Geometri rute belum tersedia.',422);
        $p=[(float)($b['pickup_lat']??$r['olat']),(float)($b['pickup_lng']??$r['olng'])];
        $q=[(float)($b['drop_lat']??$r['dlat']),(float)($b['drop_lng']??$r['dlng'])];
        $bearing=static function($a,$b,$c,$d){$x=sin(deg2rad($d-$b))*cos(deg2rad($c));$y=cos(deg2rad($a))*sin(deg2rad($c))-sin(deg2rad($a))*cos(deg2rad($c))*cos(deg2rad($d-$b));return fmod(rad2deg(atan2($x,$y))+360,360);};
        $diff=abs($bearing($r['olat'],$r['olng'],$r['dlat'],$r['dlng'])-$bearing($p[0],$p[1],$q[0],$q[1]));
        $detour=max(0,self::distance($r['olat'],$r['olng'],$p[0],$p[1])+self::distance($p[0],$p[1],$q[0],$q[1])+self::distance($q[0],$q[1],$r['dlat'],$r['dlng'])-self::distance($r['olat'],$r['olng'],$r['dlat'],$r['dlng']));
        return ['detour_km'=>$detour,'bearing_delta'=>min($diff,360-$diff),'pickup'=>$p];
    }

    public static function available(string $kind, int $id, string $date, string $start, int $excludeBooking=0, int $excludeTrip=0): bool
    {
        if (!in_array($kind,['vehicle','driver'],true)) throw new RuntimeException('Resource invalid.',422);
        $minutes=max(1,self::int('match_duration_minutes',240));
        $ts=strtotime($date.' '.$start); $end=date('H:i',$ts+$minutes*60);
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$date) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$start) || date('Y-m-d',$ts+$minutes*60)!==$date) return false;
        $col=$kind==='vehicle'?'vehicle_id':'driver_user_id';
        $st=db()->prepare("SELECT 1 FROM {$kind}_availability WHERE $col=? AND work_date=? AND status<>'available' AND start_time<? AND end_time>? AND (trip_id IS NULL OR trip_id<>?) LIMIT 1");
        $st->execute([$id,$date,$end,$start,$excludeTrip]); if($st->fetchColumn())return false;
        $col=$kind==='vehicle'?'vehicle_id':'driver_id';
        $st=db()->prepare("SELECT 1 FROM trips WHERE $col=? AND trip_date=? AND status IN ('draft','dikonfirmasi','berlangsung') AND id<>? AND departure_time<? AND time(departure_time,'+'||?||' minutes')>? LIMIT 1");
        $st->execute([$id,$date,$excludeTrip,$end,$minutes,$start]); if($st->fetchColumn())return false;
        $col=$kind==='vehicle'?'selected_vehicle_id':'selected_driver_id';
        $st=db()->prepare("SELECT 1 FROM bookings WHERE $col=? AND trip_date=? AND package_type='private' AND id<>? AND (status IN ('dibayar','menunggu_pencocokan','menunggu_konfirmasi_admin','dikonfirmasi','berlangsung') OR (status='menunggu_pembayaran' AND datetime(created_at)>datetime(?,'-15 minutes'))) AND preferred_time<? AND time(preferred_time,'+'||?||' minutes')>? AND NOT EXISTS(SELECT 1 FROM trip_bookings tb WHERE tb.booking_id=bookings.id AND tb.trip_id=?) LIMIT 1");
        $st->execute([$id,$date,$excludeBooking,date('Y-m-d H:i:s'),$end,$minutes,$start,$excludeTrip]);return !$st->fetchColumn();
    }

    public static function choices(array $in): array
    {
        $route=Pricing::resolveRoute((string)($in['origin']??''),(string)($in['dest']??''));
        $b=['route_id'=>$route['id'],'pickup_lat'=>$in['pickup_lat']??null,'pickup_lng'=>$in['pickup_lng']??null,'drop_lat'=>$in['drop_lat']??null,'drop_lng'=>$in['drop_lng']??null];
        $geo=self::geometry($b);
        if($geo['detour_km']>(float)self::get('match_max_detour_km',20)||$geo['bearing_delta']>(float)self::get('match_max_bearing_degrees',45))throw new RuntimeException('Detour/arah di luar toleransi.',422);
        $date=(string)($in['trip_date']??'');$time=(string)($in['time']??'');$pkg=$in['package']??'private';$pax=(int)($in['pax']??1);
        if(!in_array($pkg,Pricing::PACKAGES,true)||$pax<1||$pax>7)throw new RuntimeException('Paket/pax invalid.',422);
        $vehicles=[];
        foreach(db()->query("SELECT v.* FROM vehicles v JOIN garages g ON g.id=v.current_garage_id WHERE v.status='aktif' AND g.is_active=1")->fetchAll() as $v){
            if($v['serves_package']!==$pkg || $v['seat_capacity']<$pax)continue;
            $busy=0;$tripId=null;
            if($pkg==='door_to_door'){
                $st=db()->prepare("SELECT * FROM trips WHERE vehicle_id=? AND route_id=? AND trip_date=? AND package_type='door_to_door' AND road_type=? AND status='draft' AND seats_booked+?<=capacity ORDER BY seats_booked DESC");$st->execute([$v['id'],$route['id'],$date,$in['road']??'biasa',$pax]);
                foreach($st->fetchAll() as $t)if(abs(strtotime($date.' '.$time)-strtotime($date.' '.$t['departure_time']))<=self::int('match_time_window_minutes',60)*60){$busy=(int)$t['seats_booked'];$tripId=(int)$t['id'];break;}
            }
            if(!self::available('vehicle',(int)$v['id'],$date,$time,0,$tripId??0))continue;
            $v['seats_booked']=$busy;$v['target_trip_id']=$tripId;$vehicles[]=$v;
        }
        usort($vehicles,fn($a,$b)=>$b['seats_booked']<=>$a['seats_booked'] ?: $a['id']<=>$b['id']);
        $drivers=[];
        foreach(db()->query("SELECT d.*,u.name,g.lat,g.lng FROM drivers d JOIN users u ON u.id=d.user_id JOIN garages g ON g.id=d.current_garage_id WHERE u.status='aktif' AND d.status IN ('disetujui','peringatan') AND g.is_active=1")->fetchAll() as $d){
            $distance=self::distance((float)$d['lat'],(float)$d['lng'],$geo['pickup'][0],$geo['pickup'][1]);
            if($distance>(float)($d['assign_radius_km']??self::get('default_assign_radius_km',30)) || !self::available('driver',(int)$d['user_id'],$date,$time))continue;
            $drivers[]=['user_id'=>$d['user_id'],'name'=>$d['name'],'rating'=>$d['rating'],'rating_count'=>$d['rating_count'],'distance_km'=>$distance];
        }
        return ['vehicles'=>$vehicles,'drivers'=>$drivers,'geometry'=>$geo,'reservation'=>false];
    }

    public static function get(string $key, $default = null)
    {
        $st = db()->prepare("SELECT value FROM settings WHERE key = ?");
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return ($v === false || $v === null) ? $default : $v;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key, null);
        return $v === null ? $default : (int)$v;
    }
}
