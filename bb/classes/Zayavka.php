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

    // $phone_yn / $fio_yn — МЁРТВЫЕ колонки rent_orders (никогда не заполнялись),
    // держатся только ради явных списков колонок в запросах ниже. Подробности — в bb/classes/bron.php.
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

    /**
     * Повторная заявка по уже активной (new/in_work) заявке: всплывает наверх списка,
     * продлевает срок и фиксирует историю в info2. Новая строка НЕ создаётся.
     */
    public function bumpAsRepeat(array $d): void
    {
        $changes = [];
        if (!empty($d['validity']) && (int)$d['validity'] !== (int)$this->validity) {
            $changes[] = 'срок: ' . date('d.m.Y', (int)$d['validity']);
        }
        if (!empty($d['family']) && trim((string)$d['family']) !== trim((string)$this->family)) {
            $changes[] = 'имя: ' . $d['family'];
        }

        $note = 'Повторная заявка';
        if ($changes) { $note .= ' — ' . implode(' | ', $changes); }
        if (!empty($d['info'])) { $note .= '. ' . $d['info']; }

        $hist = '<p class="bron_hist_unit">' . date('d.m H:i') . ': ' . $this->esc($note) . '</p>';
        $this->info2 = (string)$this->info2 . $hist;

        $sets = ["info2='" . $this->esc($this->info2) . "'"];
        if (!empty($d['validity'])) {
            $sets[] = 'validity=' . (int)$d['validity'];
            $this->validity = (int)$d['validity'];
        }
        // in_work → new: всплывает в верхнюю группу списка (сортировка z_status='new' DESC)
        if ($this->z_status === 'in_work') {
            $sets[] = "z_status='new'";
            $this->z_status = 'new';
        }
        $sets[] = 'ch_time=' . time();
        $this->ch_time = time();

        $sql = "UPDATE rent_orders SET " . implode(', ', $sets) . " WHERE order_id=" . (int)$this->order_id;
        if (!$this->conn->query($sql)) {
            throw new \RuntimeException('bumpAsRepeat failed: ' . $this->conn->error);
        }
    }

    public static function load(int $orderId, $conn = null): self
    {
        $c = $conn ?: Db::getInstance()->getConnection();
        // type2 guard: this loader is for заявки only — never load/mutate a bron etc. by crafted id
        $r = $c->query("SELECT * FROM rent_orders WHERE order_id=" . (int)$orderId . " AND type2='zayavka' LIMIT 1");
        if (!$r || $r->num_rows < 1) { throw new \RuntimeException('Zayavka not found: ' . $orderId); }
        return self::fromRow($r->fetch_assoc(), $c);
    }

    /** @param array $f keys: info?, planned_date?, last_ch_time (for optimistic lock) */
    public function update(array $f): void
    {
        if (array_key_exists('last_ch_time', $f) && (string)$f['last_ch_time'] !== (string)$this->ch_time) {
            throw new \RuntimeException('stale edit: zayavka changed by someone else');
        }
        $sets = [];
        if (!empty($f['info'])) {
            $hist = '<p class="bron_hist_unit">' . date('d.m H:i') . ': ' . $this->esc($f['info']) . '</p>';
            $this->info2 = (string)$this->info2 . $hist;
            $sets[] = "info2='" . $this->esc($this->info2) . "'";
        }
        if (array_key_exists('planned_date', $f)) {
            $sets[] = "planned_date=" . (empty($f['planned_date']) ? 'NULL' : "'" . $this->esc($f['planned_date']) . "'");
            $this->planned_date = $f['planned_date'] ?: null;
        }
        if (!empty($f['validity_date'])) {
            $ts = strtotime($f['validity_date']);
            if ($ts !== false) {
                $sets[] = "validity=" . (int)$ts;
                $this->validity = $ts;
            }
        }
        if ($this->z_status === 'new') { $sets[] = "z_status='in_work'"; $this->z_status = 'in_work'; }
        $sets[] = "ch_time=" . time();
        $this->ch_time = time();

        $sql = "UPDATE rent_orders SET " . implode(', ', $sets) . " WHERE order_id=" . (int)$this->order_id;
        if (!$this->conn->query($sql)) { throw new \RuntimeException('update failed: ' . $this->conn->error); }
    }

    public function changeModel(int $newModelId): void
    {
        $catId = 0;
        $mr = $this->conn->query("SELECT tovar_rent_cat_id FROM tovar_rent WHERE tovar_rent_id=" . (int)$newModelId . " LIMIT 1");
        if ($mr && $row = $mr->fetch_assoc()) { $catId = (int)$row['tovar_rent_cat_id']; }
        $hist = '<p class="bron_hist_unit">' . date('d.m H:i') . ': модель изменена #' . (int)$this->model_id . ' → #' . (int)$newModelId . '</p>';
        $this->info2 = (string)$this->info2 . $hist;
        $sql = "UPDATE rent_orders SET model_id=" . (int)$newModelId . ", cat_id=" . $catId
             . ", info2='" . $this->esc($this->info2) . "', ch_time=" . time()
             . " WHERE order_id=" . (int)$this->order_id;
        if (!$this->conn->query($sql)) { throw new \RuntimeException('changeModel failed: ' . $this->conn->error); }
        $this->model_id = $newModelId; $this->cat_id = $catId;
    }

    /** Terminal statuses (rejected/spam/deleted/done): write status+reason and archive */
    public function setStatus(string $status, ?string $reason = null, ?string $comment = null): void
    {
        $terminal = in_array($status, ['rejected', 'spam', 'deleted', 'done'], true);
        $sets = ["z_status='" . $this->esc($status) . "'", "ch_time=" . time()];
        if ($reason !== null) { $sets[] = "z_reject_reason='" . $this->esc($reason) . "'"; }
        if ($comment !== null && $comment !== '') {
            $hist = '<p class="bron_hist_unit">' . date('d.m H:i') . ': ' . $this->esc($comment) . '</p>';
            $this->info2 = (string)$this->info2 . $hist;
            $sets[] = "info2='" . $this->esc($this->info2) . "'";
        }
        $sql = "UPDATE rent_orders SET " . implode(', ', $sets) . " WHERE order_id=" . (int)$this->order_id;
        if (!$this->conn->query($sql)) { throw new \RuntimeException('setStatus failed: ' . $this->conn->error); }
        $this->z_status = $status; $this->z_reject_reason = $reason;

        if ($terminal) { $this->archiveAndRemove(); }
    }

    public function softDelete(?string $reason = null, ?string $comment = null): void
    {
        $this->setStatus('deleted', $reason, $comment);
    }

    private function archiveAndRemove(): void
    {
        $user = (int)($_SESSION['user_id'] ?? 0);
        $cols = "(arch_time, arch_who, order_id, `type`, order_date, phone, phone_yn, family, `name`, otch, fio_yn, `address`, validity, rent_days, date_from, date_to, inv_n, model_id, cat_id, type2, client_id, info, info2, web, cr_time, cr_who_id, ch_time, ch_who_id, `status`, appr_id, appr_time, cr_ip, place_status, rem_type, z_status, z_reject_reason, planned_date)";
        $sel  = time() . ", " . $user . ", order_id, `type`, order_date, phone, phone_yn, family, `name`, otch, fio_yn, `address`, validity, rent_days, date_from, date_to, inv_n, model_id, cat_id, type2, client_id, info, info2, web, cr_time, cr_who_id, ch_time, ch_who_id, `status`, appr_id, appr_time, cr_ip, place_status, rem_type, z_status, z_reject_reason, planned_date";
        $this->conn->begin_transaction();
        try {
            $arch = "INSERT INTO rent_orders_arch $cols SELECT $sel FROM rent_orders WHERE order_id=" . (int)$this->order_id;
            if (!$this->conn->query($arch)) { throw new \RuntimeException('archive failed: ' . $this->conn->error); }
            if (!$this->conn->query("DELETE FROM rent_orders WHERE order_id=" . (int)$this->order_id)) {
                throw new \RuntimeException('delete after archive failed: ' . $this->conn->error);
            }
            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function linkZvonok(int $zvId): void
    {
        $this->conn->query("UPDATE zvonki SET order_id=" . (int)$this->order_id . " WHERE zv_id=" . (int)$zvId);
    }

    public function linkAfterCreate(int $orderId, int $zvId): void
    {
        $this->conn->query("UPDATE zvonki SET order_id=" . (int)$orderId . " WHERE zv_id=" . (int)$zvId);
    }

    public function create(array $d, string $source): ZayavkaCreateResult
    {
        // $source is the origin tag (web_product/web_cart/web_modal/crm); reserved for attribution, not persisted yet
        $res = new ZayavkaCreateResult();
        $modelId = (int)($d['model_id'] ?? 0);
        $phone = isset($d['phone']) ? (int)preg_replace('/[^0-9]/', '', (string)$d['phone']) : 0;

        $existing = $this->findActiveDuplicate($modelId, $phone ?: null);
        if ($existing) {
            // active dup → no new row, but surface the repeat: bump to top, extend
            // validity, log history (spec: повторная заявка не должна молча теряться)
            $existing->bumpAsRepeat($d);
            $res->isDuplicate = true;
            $res->existing = $existing;
            return $res;
        }

        $catId = 0;
        $mr = $this->conn->query("SELECT tovar_rent_cat_id FROM tovar_rent WHERE tovar_rent_id=" . $modelId . " LIMIT 1");
        if ($mr && $row = $mr->fetch_assoc()) { $catId = (int)$row['tovar_rent_cat_id']; }

        // rent_days/date_from/date_to тут намеренно не заполняются: у заявки нет товара,
        // а значит и кнопки «нов.договор», которая этот срок читает. В списки архивации
        // колонки всё же добавлены, чтобы пара act/arch не разъезжалась.
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
