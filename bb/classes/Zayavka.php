<?php
namespace bb\classes;

use bb\Db;

class ZayavkaCreateResult
{
    public bool $isDuplicate = false;
    public ?int $orderId = null;
    public ?Zayavka $existing = null;
    public ?int $zvonokId = null;   // populated by caller when linking a zvonok (later task)
    public bool $isRepeat = false;          // true when a prior archived request exists for same model+phone
    public ?Zayavka $priorArchived = null;  // the most recent archived match (informational)
}

class Zayavka
{
    /** @var \mysqli */
    private $conn;

    public $order_id, $type, $order_date, $phone, $phone_yn, $family, $name, $otch,
           $fio_yn, $address, $validity, $inv_n, $model_id, $cat_id, $type2, $client_id,
           $info, $info2, $web, $cr_time, $cr_who_id, $ch_time, $ch_who_id, $status,
           $appr_id, $appr_time, $cr_ip, $place_status, $rem_type,
           $z_status, $z_reject_reason, $planned_date;

    const DEDUP_WINDOW_MONTHS = 6;

    public function __construct($conn = null)
    {
        $this->conn = $conn ?: Db::getInstance()->getConnection();
    }

    private function esc($v): string { return $this->conn->real_escape_string((string)$v); }

    public static function fromRow(array $r, $conn = null): self
    {
        $z = new self($conn);
        foreach ($r as $k => $v) { if (property_exists($z, $k)) { $z->$k = $v; } }
        if ($z->order_id !== null) { $z->order_id = (int)$z->order_id; }
        return $z;
    }

    /**
     * Returns an active (new/in_work) заявка for the same model+phone, or null.
     * Only checks the ACTIVE table — archived records are handled by findRecentArchived().
     */
    public function findActiveDuplicate(int $modelId, ?int $phone): ?self
    {
        if (!$phone || $phone <= 1 || $modelId <= 0) { return null; }

        $sql = "SELECT * FROM rent_orders WHERE type2='zayavka' AND model_id=" . (int)$modelId
             . " AND phone=" . (int)$phone . " AND z_status IN ('new','in_work') ORDER BY cr_time DESC LIMIT 1";
        $r = $this->conn->query($sql);
        if ($r && $r->num_rows > 0) { return self::fromRow($r->fetch_assoc(), $this->conn); }

        return null;
    }

    /**
     * Returns the most recent archived заявка for the same model+phone within DEDUP_WINDOW_MONTHS,
     * or null. Used to flag repeat requests without suppressing the new insert.
     */
    public function findRecentArchived(int $modelId, ?int $phone): ?self
    {
        if (!$phone || $phone <= 1 || $modelId <= 0) { return null; }
        $since = strtotime('-' . self::DEDUP_WINDOW_MONTHS . ' months');
        $sql = "SELECT * FROM rent_orders_arch WHERE type2='zayavka' AND model_id=" . (int)$modelId
             . " AND phone=" . (int)$phone . " AND cr_time>" . (int)$since
             . " ORDER BY cr_time DESC LIMIT 1";
        $r = $this->conn->query($sql);
        if ($r && $r->num_rows > 0) { return self::fromRow($r->fetch_assoc(), $this->conn); }
        return null;
    }

    public function create(array $d, string $source): ZayavkaCreateResult
    {
        // $source is the origin tag (web_product/web_cart/web_modal/crm); reserved for attribution, not persisted yet
        $res = new ZayavkaCreateResult();
        $modelId = (int)($d['model_id'] ?? 0);
        $phone = isset($d['phone']) ? (int)preg_replace('/[^0-9]/', '', (string)$d['phone']) : 0;

        $existing = $this->findActiveDuplicate($modelId, $phone ?: null);
        if ($existing) {
            $res->isDuplicate = true;
            $res->existing = $existing;
            return $res; // active dup → no new row
        }

        $catId = 0;
        $mr = $this->conn->query("SELECT tovar_rent_cat_id FROM tovar_rent WHERE tovar_rent_id=" . $modelId . " LIMIT 1");
        if ($mr && $row = $mr->fetch_assoc()) { $catId = (int)$row['tovar_rent_cat_id']; }

        $now = time();
        $planned = !empty($d['planned_date']) ? "'" . $this->esc($d['planned_date']) . "'" : 'NULL';
        $sql = "INSERT INTO rent_orders
            (`type`, order_date, phone, phone_yn, family, `name`, otch, fio_yn, `address`, validity, inv_n, model_id, cat_id, type2, client_id, info, info2, web, cr_time, cr_who_id, ch_time, ch_who_id, `status`, appr_id, appr_time, cr_ip, place_status, rem_type, z_status, z_reject_reason, planned_date)
            VALUES ('zayavka', $now, " . (int)$phone . ", 0, '" . $this->esc($d['family'] ?? '') . "', '', '', 0, '', "
            . (int)($d['validity'] ?? ($now + 14 * 86400)) . ", 0, " . $modelId . ", " . $catId . ", 'zayavka', 0, '"
            . $this->esc($d['info'] ?? '') . "', '', " . (int)($d['web'] ?? 0) . ", $now, 0, 0, 0, '', 0, 0, '', '', '', 'new', NULL, $planned)";
        if (!$this->conn->query($sql)) {
            throw new \RuntimeException('Zayavka create failed: ' . $this->conn->error);
        }
        $res->orderId = (int)$this->conn->insert_id;

        // informational: was there a prior closed request for same model+phone?
        $prior = $this->findRecentArchived($modelId, $phone ?: null);
        if ($prior) {
            $res->isRepeat = true;
            $res->priorArchived = $prior;
        }
        return $res;
    }
}
