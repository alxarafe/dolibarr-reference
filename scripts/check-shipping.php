<?php
// ORDER-SHIPMENT-REF-001: official business-class/API operations; SQL reads only.
require '/var/www/html/master.inc.php';
foreach (['core/lib/admin.lib.php', 'societe/class/societe.class.php',
    'commande/class/commande.class.php', 'expedition/class/expedition.class.php',
    'product/class/product.class.php', 'product/stock/class/entrepot.class.php',
    'product/stock/class/mouvementstock.class.php', 'user/class/user.class.php'] as $file) {
    require_once DOL_DOCUMENT_ROOT.'/'.$file;
}
function check($ok, $label) {
    if (!$ok) { throw new RuntimeException($label); }
    echo 'PASS: '.$label."\n";
}
function readRows($sql) {
    global $db;
    $res = $db->query($sql);
    if (!$res) { throw new RuntimeException('Read-only observation failed'); }
    $rows = [];
    while ($row = $db->fetch_object($res)) { $rows[] = $row; }
    return $rows;
}
function setting($name, $value) {
    global $db, $conf;
    check(dolibarr_set_const($db, $name, (string) $value, 'chaine', 0, '', $conf->entity) > 0, 'set '.$name.'='.$value);
    $conf->global->$name = (string) $value;
}
function mode($name) {
    setting('STOCK_CALCULATE_ON_VALIDATE_ORDER', 0);
    setting('STOCK_CALCULATE_ON_BILL', 0);
    setting('STOCK_CALCULATE_ON_SHIPMENT', $name === 'validation' ? 1 : 0);
    setting('STOCK_CALCULATE_ON_SHIPMENT_CLOSE', $name === 'close' ? 1 : 0);
}
function context($name) {
    global $db, $user;
    $warehouse = new Entrepot($db);
    $warehouse->label = 'REF004-'.$name;
    $warehouse->statut = 1;
    check($warehouse->create($user) > 0, 'create open warehouse '.$name);
    $products = [];
    foreach (['A', 'B'] as $key) {
        $product = new Product($db);
        $product->ref = 'REF004-'.$name.'-'.$key;
        $product->label = $product->ref;
        $product->type = Product::TYPE_PRODUCT;
        $product->status = 1; $product->status_buy = 1;
        $product->stockable_product = Product::ENABLED_STOCK;
        check($product->create($user) > 0, 'create stockable product '.$key);
        $movement = new MouvementStock($db);
        check($movement->reception($user, $product->id, $warehouse->id, 10, 5, 'REF004 opening stock') > 0, 'receive ten units '.$key);
        $products[] = $product->id;
    }
    $soc = new Societe($db); $soc->name = 'REF004 '.$name; $soc->client = 1;
    check($soc->create($user) > 0, 'create customer');
    $order = new Commande($db);
    $order->socid = $soc->id; $order->date = dol_now();
    $order->cond_reglement_id = 1; $order->mode_reglement_id = 1;
    check($order->create($user) > 0, 'create draft order');
    foreach ([5, 3] as $i => $qty) {
        check($order->addline('REF004 line '.($i + 1), 10, $qty, 21, 0, 0, $products[$i]) > 0, 'add product order line');
    }
    check($order->fetch($order->id) > 0 && $order->valid($user) > 0, 'validate order');
    check($order->fetch($order->id) > 0, 'refresh observed order');
    return [$order, $warehouse->id, $products];
}
function shipment($order, $warehouse, $quantities) {
    global $db, $user;
    $s = new Expedition($db);
    $s->socid = $order->socid;
    $s->origin = 'commande'; $s->origin_type = 'commande'; $s->origin_id = $order->id;
    foreach ($quantities as $i => $qty) {
        check($s->addline($warehouse, $order->lines[$i]->id, $qty) > 0, 'prepare shipment line');
    }
    check($s->create($user) > 0 && $s->fetch($s->id) > 0, 'persist linked draft shipment');
    $link = readRows('SELECT COUNT(*) AS n FROM '.$db->prefix()."element_element WHERE sourcetype='commande' AND targettype='shipping' AND fk_source=".(int)$order->id.' AND fk_target='.(int)$s->id);
    check((int)$link[0]->n === 1, 'persisted order-to-shipment origin link');
    return $s;
}
function snapshot($scenario, $stage, $order, $warehouse, $products, $s = null) {
    global $db;
    $order->fetch($order->id);
    $counts = [];
    foreach ([-1, 1, 2] as $filter) {
        check($order->loadExpeditions($filter) >= 0, 'read quantities for status filter '.$filter);
        $counts[(string) $filter] = array_map(fn($line) => (float) ($order->expeditions[$line->id] ?? 0), $order->lines);
    }
    $stock = [];
    foreach ($products as $id) {
        $r = readRows('SELECT COALESCE(SUM(reel),0) AS qty FROM '.$db->prefix().'product_stock WHERE fk_product='.(int)$id.' AND fk_entrepot='.(int)$warehouse);
        $stock[] = (float)$r[0]->qty;
    }
    $movementRows = readRows('SELECT COUNT(*) AS n FROM '.$db->prefix().'stock_mouvement WHERE fk_product IN ('.implode(',', array_map('intval',$products)).')');
    $movements = readRows('SELECT rowid, fk_product, fk_entrepot, value, type_mouvement, fk_origin, origintype FROM '.$db->prefix().'stock_mouvement WHERE fk_product IN ('.implode(',', array_map('intval',$products)).') ORDER BY rowid');
    $lines = []; $links = []; $status = null;
    if ($s) {
        check($s->fetch($s->id) > 0, 'refresh observed shipment'); $status = (int)$s->status;
        $lines = readRows('SELECT fk_elementdet, fk_entrepot, qty FROM '.$db->prefix().'expeditiondet WHERE fk_expedition='.(int)$s->id.' ORDER BY rowid');
        $links = readRows('SELECT fk_source, sourcetype, fk_target, targettype FROM '.$db->prefix()."element_element WHERE sourcetype='commande' AND targettype='shipping' AND fk_source=".(int)$order->id.' AND fk_target='.(int)$s->id.' ORDER BY rowid');
    }
    $row = ['scenario'=>$scenario, 'stage'=>$stage, 'order_id'=>(int)$order->id,
        'order_status'=>(int)$order->status, 'shipment_id'=>$s ? (int)$s->id : null,
        'shipment_status'=>$status, 'ordered'=>array_map(fn($line)=>(float)$line->qty,$order->lines),
        'order_line_ids'=>array_map(fn($line)=>(int)$line->id,$order->lines),
        'warehouse_id'=>(int)$warehouse, 'quantities_by_min_status'=>$counts,
        'remaining_validated'=>array_map(fn($line,$qty)=>(float)$line->qty-$qty, $order->lines, $counts['1']),
        'stock'=>$stock, 'movement_count'=>(int)$movementRows[0]->n, 'stock_movements'=>$movements,
        'shipment_lines'=>$lines, 'origin_links'=>$links];
    echo 'OBS '.json_encode($row, JSON_UNESCAPED_SLASHES)."\n";
    return $row;
}
function repeatClose($scenario, $order, $warehouse, $products, $shipment, $before) {
    check($shipment->setClosed() === 0, 'already closed shipment is a no-op');
    $after = snapshot($scenario, 'repeated_close', $order, $warehouse, $products, $shipment);
    foreach (['stock', 'movement_count', 'order_status', 'shipment_status',
        'quantities_by_min_status', 'remaining_validated', 'shipment_lines', 'stock_movements', 'origin_links'] as $field) {
        check($after[$field] == $before[$field], 'repeated close preserves '.$field);
    }
}
function api($method, $path, $key, $data = []) {
    $ch = curl_init('http://127.0.0.1/api/index.php'.$path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>$method,
        CURLOPT_HTTPHEADER=>['DOLAPIKEY: '.$key, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT=>30]);
    if ($method !== 'GET') { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
    if (curl_exec($ch) === false) { throw new RuntimeException('API transport failure'); }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    echo 'HTTP '.json_encode(['method'=>$method,'path'=>$path,'status'=>$code])."\n";
    return $code;
}
try {
    global $db, $user, $conf;
    check(DOL_VERSION === '24.0.1', 'exact Dolibarr version');
    check(getDolGlobalString('MAIN_LANG_DEFAULT') === 'es_ES'
        && getDolGlobalString('MAIN_MONNAIE') === 'EUR'
        && (explode(':', getDolGlobalString('MAIN_INFO_SOCIETE_COUNTRY'))[1] ?? '') === 'ES'
        && date_default_timezone_get() === 'Europe/Madrid', 'initialized shipping-ES locale and company country');
    foreach (['api','societe','commande','expedition','product','stock'] as $module) {
        check(isModEnabled($module), 'module enabled '.$module);
    }
    $user = new User($db);
    check($user->fetch(0, getenv('DOLI_ADMIN_LOGIN')) > 0, 'load administrator');
    $user->loadRights();
    check((int)$user->admin === 1, 'business fixture actor is administrator');
    check(!isModEnabled('workflow') && !isModEnabled('facture'), 'workflow and invoicing inactive');
    echo 'CONTEXT '.json_encode(['version'=>DOL_VERSION, 'entity'=>(int)$conf->entity,
        'language'=>getDolGlobalString('MAIN_LANG_DEFAULT'), 'currency'=>getDolGlobalString('MAIN_MONNAIE'),
        'country'=>getDolGlobalString('MAIN_INFO_SOCIETE_COUNTRY'), 'timezone'=>date_default_timezone_get(),
        'business_actor'=>'administrator', 'positive_mutation_boundary'=>'internal classes',
        'api_scope'=>'restricted reader GET and mutation denials'])."\n";
    setting('MAIN_USE_ADVANCED_PERMS', 0);
    setting('STOCK_MUST_BE_ENOUGH_FOR_SHIPMENT', 1);
    setting('STOCK_WAREHOUSE_NOT_REQUIRED_FOR_SHIPMENTS', 0);
    setting('MAIN_DONT_SHIP_MORE_THAN_ORDERED', 1);
    setting('STOCK_SUPPORTS_SERVICES', 0);
    foreach (['validation', 'close', 'none'] as $mode) {
        mode($mode);
        [$order,$wh,$products] = context($mode);
        $initial = snapshot($mode,'order_validated',$order,$wh,$products);
        check($initial['stock'] === [10.0,10.0] && $initial['movement_count'] === 2, 'order validation does not decrement stock');
        $guard = new Expedition($db);
        check($guard->addline(0,$order->lines[0]->id,1) === -1, 'warehouse required by addline for stockable product');
        $guard = new Expedition($db);
        check($guard->addline($wh,$order->lines[0]->id,11) === -3, 'configured stock sufficiency rejects excess in warehouse');
        $s1 = shipment($order,$wh,[2,1]);
        $draft = snapshot($mode,'partial_draft',$order,$wh,$products,$s1);
        check($draft['stock'] === [10.0,10.0] && $draft['quantities_by_min_status']['-1'] === [2.0,1.0] && $draft['quantities_by_min_status']['1'] === [0.0,0.0], 'draft is counted unfiltered but not as validated shipment or stock movement');
        check($s1->valid($user) > 0, 'validate partial shipment');
        $partial = snapshot($mode,'partial_validated',$order,$wh,$products,$s1);
        check($partial['order_status'] === 2 && $partial['shipment_status'] === 1 && $partial['remaining_validated'] === [3.0,2.0], 'partial validation starts shipping and leaves quantities pending');
        check($partial['stock'] === ($mode==='validation' ? [8.0,9.0] : [10.0,10.0]), 'stock at partial validation follows selected timing');
        check($s1->setClosed() > 0, 'close partial shipment');
        $closed = snapshot($mode,'partial_closed',$order,$wh,$products,$s1);
        check($closed['order_status'] === 2 && $closed['shipment_status'] === 2, 'partial close does not close incomplete order');
        check($closed['stock'] === ($mode==='none' ? [10.0,10.0] : [8.0,9.0]), 'stock at partial close follows selected timing');
        $s2 = shipment($order,$wh,[3,2]);
        check($s2->valid($user) > 0, 'validate subsequent shipment');
        $next = snapshot($mode,'subsequent_validated',$order,$wh,$products,$s2);
        check($next['order_status'] === 2 && $next['remaining_validated'] === [0.0,0.0], 'fully validated quantities alone do not close order');
        check($next['stock'] === ($mode==='validation' ? [5.0,7.0] : ($mode==='close' ? [8.0,9.0] : [10.0,10.0])), 'subsequent validation stock timing');
        check($s2->setClosed() > 0, 'close subsequent shipment');
        $done = snapshot($mode,'all_closed',$order,$wh,$products,$s2);
        check($done['order_status'] === 3 && $done['quantities_by_min_status']['2'] === [5.0,3.0], 'all closed shipment quantities close order for authorized actor');
        check($done['stock'] === ($mode==='none' ? [10.0,10.0] : [5.0,7.0]) && $done['movement_count'] === ($mode==='none' ? 2 : 6), 'final stock and exact movement count; no double decrement');
        repeatClose($mode, $order, $wh, $products, $s2, $done);
        foreach ($done['shipment_lines'] as $i=>$line) {
            check((int)$line->fk_elementdet===(int)$order->lines[$i]->id && (int)$line->fk_entrepot===(int)$wh, 'shipment line references source order line and warehouse');
        }
    }
    mode('validation');
    [$order,$wh,$products]=context('full');
    $full=shipment($order,$wh,[5,3]);
    $draft=snapshot('full','full_draft',$order,$wh,$products,$full);
    check($draft['stock']===[10.0,10.0] && $draft['shipment_status']===0
        && $draft['remaining_validated']===[5.0,3.0], 'single full draft has no stock decrement or validated quantity');
    check($full->valid($user)>0,'validate single full shipment');
    $v=snapshot('full','full_validated',$order,$wh,$products,$full);
    check($v['remaining_validated']===[0.0,0.0] && $v['order_status']===2 && $v['stock']===[5.0,7.0],'single full validation ships all quantities but order remains in progress');
    check($full->setClosed()>0,'close single full shipment');
    $v=snapshot('full','full_closed',$order,$wh,$products,$full);
    check($v['order_status']===3 && $v['shipment_status']===2 && $v['movement_count']===4 && $v['stock']===[5.0,7.0],'single complete shipment closes order without duplicate stock decrease');
    repeatClose('full', $order, $wh, $products, $full, $v);
    // Cancellation composition mirrors the UI action: cancel helper then status.
    foreach ([['validation',false],['close',false],['close',true]] as [$timing,$restore]) {
        $name='cancel-'.$timing.'-'.($restore?'restore':'default'); mode($timing);
        [$order,$wh,$products]=context($name);
        $s=shipment($order,$wh,[2,1]); check($s->valid($user)>0,'validate cancellation fixture');
        if($timing==='close') {check($s->setClosed()>0,'close cancellation fixture');}
        check($s->cancel($user,0,$restore)>0,'cancel through official helper');
        check($s->setStatut(Expedition::STATUS_CANCELED)>0,'apply canceled status as UI action does');
        $after=snapshot($name,'canceled',$order,$wh,$products,$s);
        check($after['shipment_status']===-1 && count($after['shipment_lines'])===0 && $after['order_status']===1,'cancellation removes shipment lines, restores order validated status when none remain');
        check($after['stock']===($timing==='validation'||$restore ? [10.0,10.0] : [8.0,9.0]),'cancellation stock compensation depends on timing and explicit flag');
    }
    mode('validation');
    [$order,$wh,$products]=context('permissions'); $s=shipment($order,$wh,[2,1]);
    $restricted=new User($db); $restricted->login='ref004-readonly'; $restricted->lastname='Reference shipping reader';
    $restricted->pass=bin2hex(random_bytes(24)); $restricted->admin=0;
    check($restricted->create($user)>0 && $restricted->delrights(0,'','',$conf->entity)>0,'create restricted user without default rights');
    foreach(readRows('SELECT id FROM '.$db->prefix()."rights_def WHERE (module='expedition' AND perms='lire') OR (module='societe' AND perms IN ('lire','client'))") as $right) {
        check($restricted->addrights($right->id)>0,'grant shipment read / customer visibility');
    }
    $restricted->clearrights();$restricted->loadRights();
    check(!$restricted->hasRight('expedition','creer'),'restricted user has no shipment creation permission');
    $restricted->api_key=bin2hex(random_bytes(32)); check($restricted->update($user)>0,'provision restricted API key');
    check(api('GET','/shipments/'.$s->id,$restricted->api_key)===200,'restricted API user can read shipment');
    check(api('POST','/shipments',$restricted->api_key)===403,'API denies creation without shipment creation permission');
    check(api('POST','/shipments/'.$s->id.'/validate',$restricted->api_key,['notrigger'=>0])===403,'API denies validation without permission');
    check(api('POST','/shipments/'.$s->id.'/close',$restricted->api_key,['notrigger'=>0])===403,'API denies close without permission');
    check($s->valid($restricted)<0 && $s->fetch($s->id)>0 && (int)$s->status===0,'business-class validation also denies permission and leaves draft');
    echo "SHIPPING FUNCTIONAL CHECK PASSED\n";
} catch(Throwable $e) {
    file_put_contents('php://stderr','SHIPPING FUNCTIONAL CHECK FAILED: '.$e->getMessage()."\n"); exit(1);
}
