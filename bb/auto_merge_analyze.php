<?php
/**
 * Analyze remaining duplicates in score 80-129 range
 * and auto-merge safe cases (same full name OR passport match across ALL members)
 *
 * Usage:
 *   php auto_merge_analyze.php              — analyze + dry-run
 *   php auto_merge_analyze.php --execute    — merge safe groups
 */

$execute = in_array('--execute', $argv ?? []);

$host = getenv('DB_HOST') ?: 'db';
$mysqli = new mysqli($host, 'tiktakby_tiktak', 'Vai7evahch', 'tiktakby_tiktak');
if ($mysqli->connect_error) die("DB error: " . $mysqli->connect_error . "\n");
$mysqli->set_charset('utf8mb4');

// ── Helpers (same as auto_merge_duplicates.php) ───────────────────────────────
function clean_phone($p) { return preg_replace('/[^0-9]/', '', $p); }
function uf_find(&$p, $i) { if ($p[$i]==$i) return $i; return $p[$i]=uf_find($p,$p[$i]); }
function uf_union(&$p, $i, $j) { $ri=uf_find($p,$i); $rj=uf_find($p,$j); if ($ri!=$rj) $p[$ri]=$rj; }
function calc_confidence($c1, $c2) {
    $s = 0;
    if (!empty($c1['pas_ln']) && $c1['pas_ln']===$c2['pas_ln'] && mb_strlen($c1['pas_ln'])>=14) $s+=50;
    $p11=clean_phone($c1['phone_1']); $p12=clean_phone($c1['phone_2']);
    $p21=clean_phone($c2['phone_1']); $p22=clean_phone($c2['phone_2']);
    if (($p11&&($p11===$p21||$p11===$p22))||($p12&&($p12===$p21||$p12===$p22))) $s+=40;
    $f1=mb_strtoupper(trim($c1['family'])); $n1=mb_strtoupper(trim($c1['name'])); $o1=mb_strtoupper(trim($c1['otch']));
    $f2=mb_strtoupper(trim($c2['family'])); $n2=mb_strtoupper(trim($c2['name'])); $o2=mb_strtoupper(trim($c2['otch']));
    $fm=($f1&&$f1===$f2); $nm=($n1&&$n1===$n2); $om=($o1&&$o1===$o2);
    if ($fm&&$nm&&$om) $s+=40; elseif ($fm&&$nm) $s+=20;
    if (!empty($c1['pas_n'])&&$c1['pas_n']===$c2['pas_n']) $s+=20;
    $s1=mb_strtoupper(trim($c1['str'])); $d1=mb_strtoupper(trim($c1['dom']));
    $s2=mb_strtoupper(trim($c2['str'])); $d2=mb_strtoupper(trim($c2['dom']));
    if ($s1&&$s1===$s2&&$d1===$d2) $s+=10;
    if (!empty($c1['pas_date'])&&$c1['pas_date']!=0&&$c1['pas_date']==$c2['pas_date']) $s+=10;
    return $s;
}

// ── Build candidate set (same SQL) ───────────────────────────────────────────
$mysqli->query("DROP TEMPORARY TABLE IF EXISTS temp_dup_ids");
$mysqli->query("CREATE TEMPORARY TABLE temp_dup_ids (client_id INT PRIMARY KEY)");
$mysqli->query("INSERT IGNORE INTO temp_dup_ids SELECT client_id FROM clients WHERE pas_ln IN (SELECT pas_ln FROM clients WHERE LENGTH(pas_ln)>=14 GROUP BY pas_ln HAVING COUNT(*)>1)");
$cp1="REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone_1,' ',''),'-',''),'(',''),')',''),'+','')";
$cp2="REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone_2,' ',''),'-',''),'(',''),')',''),'+','')";
$mysqli->query("DROP TEMPORARY TABLE IF EXISTS temp_phones");
$mysqli->query("CREATE TEMPORARY TABLE temp_phones (client_id INT, clean_phone VARCHAR(30), INDEX idx_cp(clean_phone))");
$mysqli->query("INSERT INTO temp_phones SELECT client_id,$cp1 FROM clients WHERE LENGTH($cp1)>=7");
$mysqli->query("INSERT INTO temp_phones SELECT client_id,$cp2 FROM clients WHERE LENGTH($cp2)>=7");
$mysqli->query("INSERT IGNORE INTO temp_dup_ids SELECT DISTINCT client_id FROM temp_phones WHERE clean_phone IN (SELECT clean_phone FROM (SELECT clean_phone FROM temp_phones GROUP BY clean_phone HAVING COUNT(DISTINCT client_id)>1) dp)");
$mysqli->query("DROP TEMPORARY TABLE IF EXISTS temp_phones");
$mysqli->query("INSERT IGNORE INTO temp_dup_ids SELECT c.client_id FROM clients c JOIN (SELECT family,name,otch FROM clients WHERE family!='' AND name!='' GROUP BY family,name,otch HAVING COUNT(*)>1) dup ON c.family=dup.family AND c.name=dup.name AND c.otch=dup.otch");

$res = $mysqli->query("SELECT * FROM clients WHERE client_id IN (SELECT client_id FROM temp_dup_ids)");
$clients=[]; $map_ln=[]; $map_phone=[]; $map_name=[];
while ($c=$res->fetch_assoc()) {
    $clients[$c['client_id']]=$c;
    if (mb_strlen($c['pas_ln'])>=14) $map_ln[$c['pas_ln']][]=$c['client_id'];
    $p1=clean_phone($c['phone_1']); if (strlen($p1)>=7) $map_phone[$p1][]=$c['client_id'];
    $p2=clean_phone($c['phone_2']); if (strlen($p2)>=7) $map_phone[$p2][]=$c['client_id'];
    $f=mb_strtolower(trim($c['family'])); $n=mb_strtolower(trim($c['name'])); $o=mb_strtolower(trim($c['otch']));
    if ($f&&$n&&$o) $map_name[$f.'|'.$n.'|'.$o][]=$c['client_id'];
}
$parent=[]; foreach($clients as $cid=>$_) $parent[$cid]=$cid;
foreach($map_ln    as $ids) if(count($ids)>1) for($i=1;$i<count($ids);$i++) uf_union($parent,$ids[0],$ids[$i]);
foreach($map_phone as $ids) if(count($ids)>1) for($i=1;$i<count($ids);$i++) uf_union($parent,$ids[0],$ids[$i]);
foreach($map_name  as $ids) if(count($ids)>1) for($i=1;$i<count($ids);$i++) uf_union($parent,$ids[0],$ids[$i]);
$clusters=[];
foreach($clients as $cid=>$c) { $root=uf_find($parent,$cid); $clusters[$root][]=$c; }

// ── Score and classify remaining groups (80–129 only) ────────────────────────
$groups_safe = [];   // same full name across ALL members — definitely same person
$groups_skip = [];   // different names — could be spouses

foreach ($clusters as $group) {
    if (count($group)<2) continue;

    $group_score = 999; $n=count($group);
    for ($i=0;$i<$n;$i++) for ($j=$i+1;$j<$n;$j++) {
        $s=calc_confidence($group[$i],$group[$j]);
        if ($s<$group_score) $group_score=$s;
    }
    if ($group_score<80 || $group_score>=130) continue;  // only 80-129 range

    // Check: are ALL members the same full name?
    $names = array_unique(array_map(fn($c)=>
        mb_strtoupper(trim($c['family'])).'|'.mb_strtoupper(trim($c['name'])).'|'.mb_strtoupper(trim($c['otch'])),
        $group));
    $all_same_name = (count($names)===1 && strpos(array_values($names)[0], '||') !== 0);

    // Check: is there at least one valid passport match inside the group?
    $has_passport_match = false;
    for ($i=0;$i<$n&&!$has_passport_match;$i++) {
        for ($j=$i+1;$j<$n&&!$has_passport_match;$j++) {
            if (!empty($group[$i]['pas_ln']) && mb_strlen($group[$i]['pas_ln'])>=14
                && $group[$i]['pas_ln']===$group[$j]['pas_ln']) $has_passport_match=true;
        }
    }

    // Classify reasons for match
    $reasons = [];
    for ($i=0;$i<$n;$i++) for ($j=$i+1;$j<$n;$j++) {
        $c1=$group[$i]; $c2=$group[$j];
        if (!empty($c1['pas_ln'])&&$c1['pas_ln']===$c2['pas_ln']&&mb_strlen($c1['pas_ln'])>=14) $reasons[]='паспорт';
        $p11=clean_phone($c1['phone_1']); $p12=clean_phone($c1['phone_2']);
        $p21=clean_phone($c2['phone_1']); $p22=clean_phone($c2['phone_2']);
        if (($p11&&($p11===$p21||$p11===$p22))||($p12&&($p12===$p21||$p12===$p22))) $reasons[]='телефон';
        $f1=mb_strtoupper(trim($c1['family'])); $n1=mb_strtoupper(trim($c1['name'])); $o1=mb_strtoupper(trim($c1['otch']));
        $f2=mb_strtoupper(trim($c2['family'])); $n2=mb_strtoupper(trim($c2['name'])); $o2=mb_strtoupper(trim($c2['otch']));
        if ($f1&&$f1===$f2&&$n1&&$n1===$n2&&$o1&&$o1===$o2) $reasons[]='ФИО';
        elseif ($f1&&$f1===$f2&&$n1&&$n1===$n2) $reasons[]='Фам+Имя';
    }
    $reasons = array_unique($reasons);

    usort($group, fn($a,$b)=>$b['cr_time']<=>$a['cr_time']);
    $entry = ['master'=>$group[0],'slaves'=>array_slice($group,1),'score'=>$group_score,
              'reasons'=>implode('+',$reasons),'all_same_name'=>$all_same_name,'has_passport'=>$has_passport_match];

    if ($all_same_name || $has_passport_match) {
        $groups_safe[] = $entry;
    } else {
        $groups_skip[] = $entry;
    }
}

usort($groups_safe, fn($a,$b)=>$b['score']<=>$a['score']);
usort($groups_skip, fn($a,$b)=>$b['score']<=>$a['score']);

$safe_slaves = array_sum(array_map(fn($g)=>count($g['slaves']),$groups_safe));
$skip_slaves = array_sum(array_map(fn($g)=>count($g['slaves']),$groups_skip));

echo "\n=== АНАЛИЗ ОСТАВШИХСЯ ДУБЛЕЙ (score 80–129) ===\n";
echo "Mode: " . ($execute ? "ВЫПОЛНЕНИЕ" : "DRY RUN") . "\n\n";
echo "БЕЗОПАСНО (одинаковое ФИО или совпадает паспорт): " . count($groups_safe) . " групп, $safe_slaves slave\n";
echo "ПРОПУСКАЕМ (разные имена — возможно супруги/родственники): " . count($groups_skip) . " групп, $skip_slaves slave\n\n";

// Show safe groups
echo str_repeat('─',70) . "\n";
echo "БЕЗОПАСНЫЕ ГРУППЫ (первые 20):\n\n";
foreach (array_slice($groups_safe,0,20) as $i=>$g) {
    $m=$g['master'];
    printf("[%3d] score=%-3d  match=%-20s  МАСТЕР  ID=%-6d %s %s %s  (создан: %s, сделок: %d)\n",
        $i+1,$g['score'],$g['reasons'],$m['client_id'],$m['family'],$m['name'],$m['otch'],date('d.m.Y',$m['cr_time']),$m['arch_n']);
    foreach ($g['slaves'] as $s) {
        // Show what's different
        $diff=[];
        if (mb_strtoupper($s['family'])!==mb_strtoupper($m['family'])||mb_strtoupper($s['name'])!==mb_strtoupper($m['name'])) $diff[]='ФИО:'.$s['family'].' '.$s['name'].' '.$s['otch'];
        if ($s['pas_ln']!==$m['pas_ln'] && !empty($s['pas_ln'])) $diff[]='ЛН:'.$s['pas_ln'];
        $ph_s=clean_phone($s['phone_1']); $ph_m=clean_phone($m['phone_1']);
        if ($ph_s && $ph_s!==$ph_m) $diff[]='тел:'.$s['phone_1'];
        if ($s['reg_str']!==$m['reg_str']&&!empty($s['reg_str'])) $diff[]='ул:'.$s['reg_str'];
        printf("           → ДУБЛЬ   ID=%-6d (сделок: %d) DIFF: %s\n",
            $s['client_id'],$s['arch_n'],implode(' | ',$diff)?:'только адрес/доп.поля');
    }
    echo "\n";
}
if (count($groups_safe)>20) echo "... и ещё ".(count($groups_safe)-20)." безопасных групп\n\n";

// Show skipped groups (sample)
echo str_repeat('─',70) . "\n";
echo "ПРОПУСКАЕМЫЕ ГРУППЫ — разные имена (первые 15):\n\n";
foreach (array_slice($groups_skip,0,15) as $i=>$g) {
    $m=$g['master'];
    printf("[%3d] score=%-3d  match=%-20s  ID=%-6d %s %s %s\n",
        $i+1,$g['score'],$g['reasons'],$m['client_id'],$m['family'],$m['name'],$m['otch']);
    foreach ($g['slaves'] as $s) {
        printf("           → ID=%-6d %s %s %s\n",$s['client_id'],$s['family'],$s['name'],$s['otch']);
    }
    echo "\n";
}
if (count($groups_skip)>15) echo "... и ещё ".(count($groups_skip)-15)." пропускаемых групп\n\n";

if (!$execute) {
    echo "Для слияния безопасных групп: php " . basename(__FILE__) . " --execute\n\n";
    exit(0);
}

// ── Execute safe merges ───────────────────────────────────────────────────────
$row = $mysqli->query("SELECT user_id FROM users LIMIT 1");
$arch_who_id = $row ? (int)($row->fetch_assoc()['user_id'] ?? 0) : 0;

$merged=0; $errors=0;
foreach ($groups_safe as $g) {
    $master_id=(int)$g['master']['client_id'];
    $res_m=$mysqli->query("SELECT * FROM clients WHERE client_id=$master_id");
    if (!$res_m||$res_m->num_rows===0) { echo "ПРОПУСК: мастер $master_id не найден\n"; $errors++; continue; }
    $master=$res_m->fetch_assoc();

    $mysqli->begin_transaction();
    try {
        foreach ($g['slaves'] as $sd) {
            $slave_id=(int)$sd['client_id'];
            $res_s=$mysqli->query("SELECT * FROM clients WHERE client_id=$slave_id");
            if (!$res_s||$res_s->num_rows===0) continue;
            $slave=$res_s->fetch_assoc();

            foreach (['rent_deals_act','rent_deals_arch','deals','rent_orders','rent_orders_arch'] as $t)
                $mysqli->query("UPDATE $t SET client_id=$master_id WHERE client_id=$slave_id");
            $mysqli->query("UPDATE karn_brons SET cl_id=$master_id WHERE cl_id=$slave_id");
            $mysqli->query("UPDATE karn_brons_arch SET cl_id=$master_id WHERE cl_id=$slave_id");

            $mysqli->query("UPDATE clients SET arch_amount=arch_amount+".(float)$slave['arch_amount'].",arch_n=arch_n+".(int)$slave['arch_n']." WHERE client_id=$master_id");

            $new_info=$master['info']; $added=false;
            if (!empty($slave['phone_1'])&&$slave['phone_1']!==$master['phone_1']&&$slave['phone_1']!==$master['phone_2'])
                { $new_info.=($new_info?" | ":"")."Тел дубля: ".$slave['phone_1']; $added=true; }
            if (!empty($slave['phone_2'])&&$slave['phone_2']!==$master['phone_1']&&$slave['phone_2']!==$master['phone_2'])
                { $new_info.=($new_info?" | ":"")."Тел дубля: ".$slave['phone_2']; $added=true; }
            if (!empty(trim($slave['info'])))
                { $new_info.=($new_info?" | ":"")."Инфо дубля: ".trim($slave['info']); $added=true; }
            if ($added) {
                $st=$mysqli->prepare("UPDATE clients SET info=? WHERE client_id=?");
                $st->bind_param("si",$new_info,$master_id); $st->execute(); $st->close();
                $master['info']=$new_info;
            }
            if (empty($master['phone_1'])&&!empty($slave['phone_1'])) {
                $st=$mysqli->prepare("UPDATE clients SET phone_1=? WHERE client_id=?");
                $st->bind_param("si",$slave['phone_1'],$master_id); $st->execute(); $st->close();
                $master['phone_1']=$slave['phone_1'];
            }
            if (empty($master['phone_2'])&&!empty($slave['phone_2'])) {
                $st=$mysqli->prepare("UPDATE clients SET phone_2=? WHERE client_id=?");
                $st->bind_param("si",$slave['phone_2'],$master_id); $st->execute(); $st->close();
                $master['phone_2']=$slave['phone_2'];
            }
            if (!empty($slave['phone_1'])&&empty($master['phone_2'])&&$slave['phone_1']!==$master['phone_1']) {
                $st=$mysqli->prepare("UPDATE clients SET phone_2=? WHERE client_id=?");
                $st->bind_param("si",$slave['phone_1'],$master_id); $st->execute(); $st->close();
                $master['phone_2']=$slave['phone_1'];
            }

            $ins=$mysqli->prepare("INSERT INTO clients_arch
                (arch_time,arch_who_id,main_cl_id,client_id,family,name,otch,city,str,dom,kv,
                 pas_n,pas_ln,pas_date,pas_who,reg_city,reg_str,reg_dom,reg_kv,
                 phone_1,phone_2,info,status,cr_time,arch_n,arch_amount,arch_l_date,cr_who,source)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $at=(int)time(); $spd=(int)$slave['pas_date']; $sct=(int)$slave['cr_time'];
            $san=(int)$slave['arch_n']; $saa=(float)$slave['arch_amount'];
            $sald=(int)$slave['arch_l_date']; $scw=(int)$slave['cr_who'];
            $ins->bind_param("iiiisssssssssisssssssssiidiis",
                $at,$arch_who_id,$master_id,$slave['client_id'],
                $slave['family'],$slave['name'],$slave['otch'],$slave['city'],$slave['str'],$slave['dom'],$slave['kv'],
                $slave['pas_n'],$slave['pas_ln'],$spd,$slave['pas_who'],
                $slave['reg_city'],$slave['reg_str'],$slave['reg_dom'],$slave['reg_kv'],
                $slave['phone_1'],$slave['phone_2'],$slave['info'],$slave['status'],$sct,
                $san,$saa,$sald,$scw,$slave['source']);
            $ins->execute(); $ins->close();
            $mysqli->query("DELETE FROM clients WHERE client_id=$slave_id");
        }
        $mysqli->commit(); $merged++;
        printf("OK  ID=%-6d %s %s ← [%s]\n",
            $master['client_id'],$master['family'],$master['name'],
            implode(',',array_map(fn($s)=>$s['client_id'],$g['slaves'])));
    } catch (Exception $e) {
        $mysqli->rollback(); $errors++;
        echo "ERR master=$master_id: ".$e->getMessage()."\n";
    }
}

echo "\n=== ИТОГ ===\n";
echo "Слито: $merged групп\n";
echo "Ошибок: $errors\n";
echo "Клиентов в базе сейчас: ".$mysqli->query("SELECT COUNT(*) c FROM clients")->fetch_assoc()['c']."\n";
echo "Пропущено (разные имена): ".count($groups_skip)." групп — проверь вручную\n\n";
