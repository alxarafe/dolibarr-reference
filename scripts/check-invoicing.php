<?php
// INVOICE-ROUTES-REF-001: reuse REF-003 HTTP form helpers; no Dolibarr source copied.
function http($path, $cookie, $fields = null, $apiKey = null) {
    $ch = curl_init('http://127.0.0.1'.$path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie, CURLOPT_TIMEOUT => 30]);
    if ($fields !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    if ($apiKey !== null) { curl_setopt($ch, CURLOPT_HTTPHEADER, ['DOLAPIKEY: '.$apiKey]); }
    $body = curl_exec($ch);
    if ($body === false) { throw new RuntimeException('HTTP transport failure'); }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body];
}
function formFields($html, $action = null) {
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $form = null;
    foreach ($xpath->query('//form') as $candidate) {
        if ($action === null || $xpath->query('.//input[@name="action" and @value="'.$action.'"]', $candidate)->length) {
            $form = $candidate; break;
        }
    }
    if (!$form) { throw new RuntimeException('Expected UI form not found'); }
    $fields = [];
    foreach ($xpath->query('.//input[@name] | .//select[@name] | .//textarea[@name]', $form) as $input) {
        $name = $input->getAttribute('name');
        $type = $input->getAttribute('type');
        if (in_array($type, ['submit', 'button', 'checkbox', 'radio'], true)) { continue; }
        if ($input->tagName === 'select') {
            $options = $xpath->query('.//option[@selected]', $input);
            if (!$options->length) { $options = $xpath->query('.//option', $input); }
            $fields[$name] = $options->length ? $options->item(0)->getAttribute('value') : '';
        } else {
            $fields[$name] = $input->tagName === 'textarea' ? $input->textContent : $input->getAttribute('value');
        }
    }
    return $fields;
}
function actionHref($html, $fragment) {
    $dom = new DOMDocument(); @$dom->loadHTML($html);
    foreach ($dom->getElementsByTagName('a') as $link) {
        $href = $link->getAttribute('href');
        if (str_contains($href, $fragment) && !str_contains($link->getAttribute('class'), 'Refused')) { return $href; }
    }
    throw new RuntimeException('Expected actionable UI link not found');
}
require '/var/www/html/master.inc.php';
foreach (['core/lib/admin.lib.php','societe/class/societe.class.php','user/class/user.class.php',
    'comm/propal/class/propal.class.php','commande/class/commande.class.php',
    'expedition/class/expedition.class.php','compta/facture/class/facture.class.php',
    'product/class/product.class.php','product/stock/class/entrepot.class.php',
    'product/stock/class/mouvementstock.class.php'] as $file) { require_once DOL_DOCUMENT_ROOT.'/'.$file; }
const INVOICE_API = '/api/index.php/invoices';
const INVOICE_CARD = '/compta/facture/card.php';
$evidence = ['assertions'=>[], 'http'=>[], 'observations'=>[], 'configurations'=>[]];
$cookies = [];
function verify($ok, $label) {
    global $evidence;
    if (!$ok) { throw new RuntimeException($label); }
    $evidence['assertions'][]=$label;
    echo 'PASS: '.$label."\n";
}
function rows($sql) {
    global $db;
    $result=$db->query($sql);
    if (!$result) { throw new RuntimeException('Read-only evidence query failed: '.$db->lasterror()); }
    $out=[]; while ($row=$db->fetch_object($result)) { $out[]=$row; } return $out;
}
function setting($key,$value) {
    global $db,$conf;
    verify(dolibarr_set_const($db,$key,(string)$value,'chaine',0,'',$conf->entity)>0,'configure '.$key.'='.$value);
    $conf->global->$key=(string)$value;
}
function stockMode($mode) {
    global $evidence;
    $flags=['STOCK_CALCULATE_ON_VALIDATE_ORDER'=>$mode==='order'?1:0,
        'STOCK_CALCULATE_ON_SHIPMENT'=>in_array($mode,['shipment','combined'],true)?1:0,
        'STOCK_CALCULATE_ON_SHIPMENT_CLOSE'=>0,
        'STOCK_CALCULATE_ON_BILL'=>in_array($mode,['invoice','combined'],true)?1:0];
    foreach($flags as $key=>$value) { setting($key,$value); }
    $evidence['configurations'][]=['mode'=>$mode,'flags'=>$flags];
}
function context($name,$fromProposal=false) {
    global $db,$user;
    $wh=new Entrepot($db);$wh->label='REF005-'.$name;$wh->statut=1;
    verify($wh->create($user)>0,'create isolated warehouse '.$name);
    $products=[];
    foreach(['A','B'] as $key) {
        $p=new Product($db);$p->ref='REF005-'.$name.'-'.$key;$p->label=$p->ref;
        $p->type=Product::TYPE_PRODUCT;$p->status=1;$p->status_buy=1;$p->stockable_product=Product::ENABLED_STOCK;
        verify($p->create($user)>0,'create stockable product');
        $m=new MouvementStock($db);verify($m->reception($user,$p->id,$wh->id,10,5,'REF005 opening stock')>0,'opening receipt');
        $products[]=$p->id;
    }
    $soc=new Societe($db);$soc->name='REF005 '.$name;$soc->client=1;verify($soc->create($user)>0,'create isolated customer');
    $o=new Commande($db);$proposal=null;
    if($fromProposal) {
        $proposal=new Propal($db);$proposal->socid=$soc->id;$proposal->date=dol_now();
        $proposal->cond_reglement_id=1;$proposal->mode_reglement_id=1;
        verify($proposal->create($user)>0,'create upstream proposal fixture');
        foreach([5,3] as $i=>$qty) { verify($proposal->addline('REF005 product',10,$qty,21,0,0,$products[$i])>0,'proposal product line'); }
        verify($proposal->fetch($proposal->id)>0 && $proposal->valid($user)>0,'validate upstream proposal');
        verify($proposal->closeProposal($user,Propal::STATUS_SIGNED)>0,'accept upstream proposal without workflow');
        verify($proposal->fetch($proposal->id)>0 && $o->createFromProposal($proposal,$user)>0,'reuse proposal-to-order setup');
    } else {
        $o->socid=$soc->id;$o->date=dol_now();$o->cond_reglement_id=1;$o->mode_reglement_id=1;
        verify($o->create($user)>0,'create order');
        foreach([5,3] as $i=>$qty) { verify($o->addline('REF005 product',10,$qty,21,0,0,$products[$i])>0,'order product line'); }
    }
    verify($o->fetch($o->id)>0 && $o->valid($user,$wh->id)>0,'validate source order');$o->fetch($o->id);
    return ['order'=>$o,'warehouse'=>$wh->id,'products'=>$products,'proposal'=>$proposal];
}
function shipment($ctx) {
    global $db,$user;
    $o=$ctx['order'];$s=new Expedition($db);$s->socid=$o->socid;$s->origin=$s->origin_type='commande';$s->origin_id=$o->id;
    foreach([2,1] as $i=>$qty) { verify($s->addline($ctx['warehouse'],$o->lines[$i]->id,$qty)>0,'prepare partial shipment fixture'); }
    verify($s->create($user)>0 && $s->fetch($s->id)>0 && $s->valid($user)>0,'create and validate partial shipment fixture');
    $s->fetch($s->id);return $s;
}
function api($method,$path,$key,$payload=null) {
    global $evidence;
    $ch=curl_init('http://127.0.0.1/api/index.php'.$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,
        CURLOPT_HTTPHEADER=>['DOLAPIKEY: '.$key,'Content-Type: application/json'],CURLOPT_TIMEOUT=>30]);
    if($payload!==null) { curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload)); }
    $body=curl_exec($ch);if($body===false) { throw new RuntimeException('API transport failure'); }
    $status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    $evidence['http'][]=['method'=>$method,'path'=>$path,'status'=>$status];
    return [$status,json_decode($body,true)];
}
function fetchInvoice($id) {
    global $db;
    $f=new Facture($db);verify($f->fetch($id)>0,'fetch observed invoice');return $f;
}
function apiFromOrder($ctx,$key) {
    [$code,$body]=api('POST','/invoices/createfromorder/'.$ctx['order']->id,$key,[]);
    verify($code===200 && !empty($body['id']),'API order conversion succeeds');return fetchInvoice((int)$body['id']);
}
function uiLogin() {
    global $cookies;
    $cookie=tempnam('/tmp','ref005-');$cookies[]=$cookie;
    [, $html]=http('/index.php',$cookie);$fields=formFields($html);
    $fields['username']=getenv('DOLI_ADMIN_LOGIN');$fields['password']=getenv('DOLI_ADMIN_PASSWORD');$fields['actionlogin']='login';
    [$code]=http('/index.php',$cookie,$fields);verify(in_array($code,[200,302,303],true),'UI session login');return $cookie;
}
function uiInvoice($ctx,$origin,$cookie,$percent=100,$selected=null,$s=null) {
    global $evidence;
    $src=$s??$ctx['order'];$card=$s?'/expedition/card.php':'/commande/card.php';
    [$code,$html]=http($card.'?id='.$src->id,$cookie);
    verify($code===200 && !str_contains($html,'name="actionlogin"'),'authenticated origin card');
    $href=actionHref($html,INVOICE_CARD.'?action=create');
    verify(str_contains($href,'origin='.$origin),'UI emits expected invoice origin');
    [$code,$html]=http($href,$cookie);verify($code===200,'UI invoice creation form');
    $fields=formFields($html,'add');
    $fields['type']=Facture::TYPE_STANDARD;$fields['valuestandardinvoice']=$percent;
    $fields['toselect']=$selected??array_map(fn($line)=>(int)$line->id,$src->lines);
    $before=(int)rows('SELECT COUNT(*) AS n FROM '. $GLOBALS['db']->prefix().'facture')[0]->n;
    [$code]=http(INVOICE_CARD,$cookie,$fields);
    $evidence['http'][]=['method'=>'POST','path'=>INVOICE_CARD,'origin'=>$origin,'status'=>$code,'percent'=>$percent,'selected_line_ids'=>$fields['toselect']];
    $all=rows('SELECT rowid FROM '.$GLOBALS['db']->prefix().'facture ORDER BY rowid');
    verify(count($all)===$before+1 && in_array($code,[302,303],true),'UI creates exactly one draft invoice');
    return fetchInvoice((int)end($all)->rowid);
}
function snapshot($scenario,$stage,$ctx,$invoice=null,$s=null) {
    global $db,$evidence;
    $o=$ctx['order'];verify($o->fetch($o->id)>0,'refresh source order');
    $invoiceRows=rows('SELECT f.rowid, f.fk_statut, f.paye, f.total_ht FROM '.$db->prefix().'facture f JOIN '.$db->prefix()."element_element ee ON ee.fk_target=f.rowid AND ee.targettype='facture' WHERE ee.sourcetype='commande' AND ee.fk_source=".(int)$o->id.' ORDER BY f.rowid');
    $ids=array_map(fn($r)=>(int)$r->rowid,$invoiceRows);$totals=['all'=>[],'validated'=>[]];
    foreach($ctx['products'] as $product) {
        foreach(['all','validated'] as $filter) {
            $q=empty($ids)?[]:rows('SELECT COALESCE(SUM(fd.qty),0) AS qty FROM '.$db->prefix().'facturedet fd JOIN '.$db->prefix().'facture f ON fd.fk_facture=f.rowid WHERE fd.fk_product='.(int)$product.' AND f.rowid IN ('.implode(',',$ids).')'.($filter==='validated'?' AND f.fk_statut=1':''));
            $totals[$filter][]=empty($q)?0.0:(float)$q[0]->qty;
        }
    }
    $stock=[];foreach($ctx['products'] as $product) { $r=rows('SELECT COALESCE(SUM(reel),0) AS qty FROM '.$db->prefix().'product_stock WHERE fk_product='.(int)$product.' AND fk_entrepot='.(int)$ctx['warehouse']);$stock[]=(float)$r[0]->qty; }
    $moves=rows('SELECT rowid, fk_product, fk_entrepot, value, type_mouvement, fk_origin, origintype FROM '.$db->prefix().'stock_mouvement WHERE fk_product IN ('.implode(',',$ctx['products']).') ORDER BY rowid');
    $links=rows('SELECT fk_source, sourcetype, fk_target, targettype FROM '.$db->prefix().'element_element WHERE (fk_source='.(int)$o->id." AND sourcetype='commande')".($ctx['proposal']?' OR (fk_source='.(int)$ctx['proposal']->id." AND sourcetype='propal')":'').($s?' OR (fk_source='.(int)$s->id." AND sourcetype='shipping')":'').' ORDER BY rowid');
    $f=null;if($invoice) { $invoice=fetchInvoice($invoice->id);$f=['id'=>(int)$invoice->id,'status'=>(int)$invoice->status,'paid'=>(int)$invoice->paye,'lines'=>rows('SELECT rowid, fk_product, qty, fk_parent_line, total_ht FROM '.$db->prefix().'facturedet WHERE fk_facture='.(int)$invoice->id.' ORDER BY rang,rowid')]; }
    if($s) {$s->fetch($s->id);}
    $row=['scenario'=>$scenario,'stage'=>$stage,'order_id'=>(int)$o->id,'order_status'=>(int)$o->status,'order_billed'=>(int)$o->billed,
        'proposal_id'=>$ctx['proposal']?(int)$ctx['proposal']->id:null,'order_line_ids'=>array_map(fn($l)=>(int)$l->id,$o->lines),
        'product_ids'=>$ctx['products'],'warehouse_id'=>(int)$ctx['warehouse'],'linked_invoices'=>$invoiceRows,'invoice'=>$f,
        'invoiced_quantities'=>$totals,'arithmetic_remaining_validated'=>array_map(fn($l,$q)=>(float)$l->qty-$q,$o->lines,$totals['validated']),
        'shipment_id'=>$s?(int)$s->id:null,'shipment_status'=>$s?(int)$s->status:null,'shipment_billed'=>$s?(int)$s->billed:null,
        'links'=>$links,'stock'=>$stock,'stock_movements'=>$moves];
    $evidence['observations'][]=$row;return $row;
}
function quantities($f) { $f=fetchInvoice($f->id);return array_map(fn($l)=>(float)$l->qty,$f->lines); }
function validateApi($f,$key,$warehouse=0) {
    [$status]=api('POST','/invoices/'.$f->id.'/validate',$key,['idwarehouse'=>$warehouse,'notrigger'=>0]);
    verify($status===200,'API invoice validation succeeds');
}
$exitCode=0;
try {
    global $db,$user,$conf;
    verify(DOL_VERSION==='24.0.1','pinned Dolibarr version');
    foreach(['api','societe','propal','commande','expedition','product','stock','invoice'] as $module) { verify(isModEnabled($module),'enabled '.$module); }
    verify(!isModEnabled('workflow'),'automatic workflow disabled for manual routes');
    $user=new User($db);verify($user->fetch(0,getenv('DOLI_ADMIN_LOGIN'))>0,'load administrator');$user->loadRights();
    $adminKey=getenv('DOLIBARR_REFERENCE_API_KEY');
    foreach(['MAIN_USE_ADVANCED_PERMS'=>0,'WORKFLOW_DISABLE_CREATE_INVOICE_FROM_ORDER'=>0,'WORKFLOW_BILL_ON_SHIPMENT'=>1,
        'STOCK_SUPPORTS_SERVICES'=>0,'STOCK_MUST_BE_ENOUGH_FOR_SHIPMENT'=>1,'STOCK_WAREHOUSE_NOT_REQUIRED_FOR_SHIPMENTS'=>0] as $key=>$value) { setting($key,$value); }
    $evidence['context']=['version'=>DOL_VERSION,'entity'=>(int)$conf->entity,'language'=>getDolGlobalString('MAIN_LANG_DEFAULT'),
        'currency'=>getDolGlobalString('MAIN_MONNAIE'),'country'=>getDolGlobalString('MAIN_INFO_SOCIETE_COUNTRY'),
        'timezone'=>date_default_timezone_get(),'workflow'=>false,'advanced_permissions'=>false];
    $cookie=uiLogin();

    stockMode('none');$ctx=context('ui-order',true);
    $f=uiInvoice($ctx,'commande',$cookie);verify(quantities($f)===[5.0,3.0],'UI full order quantities');
    snapshot('INV-ROUTE-001','full_draft',$ctx,$f);validateApi($f,$adminKey);
    $r=snapshot('INV-ROUTE-001','full_validated',$ctx,$f);verify($r['stock']===[10.0,10.0],'disabled timing leaves stock unchanged');
    $duplicate=uiInvoice($ctx,'commande',$cookie);verify(quantities($duplicate)===[5.0,3.0],'UI repeat copies original quantities rather than net remainder');
    validateApi($duplicate,$adminKey);$r=snapshot('INV-ROUTE-001','repeat_validated',$ctx,$duplicate);
    verify($r['invoiced_quantities']['validated']===[10.0,6.0],'UI permits repeated full quantities in tested composition');

    $ctx=context('ui-partial');$f=uiInvoice($ctx,'commande',$cookie,50);verify(quantities($f)===[2.5,1.5],'UI percentage scales quantities');
    snapshot('INV-ROUTE-001-P','partial_draft',$ctx,$f);validateApi($f,$adminKey);snapshot('INV-ROUTE-001-P','partial_validated',$ctx,$f);
    $f=uiInvoice($ctx,'commande',$cookie,100,[(int)$ctx['order']->lines[0]->id]);verify(quantities($f)===[5.0],'UI selection copies one full source line');
    validateApi($f,$adminKey);snapshot('INV-ROUTE-001-P','selected_line_validated',$ctx,$f);

    $ctx=context('api-order');$f=apiFromOrder($ctx,$adminKey);verify(quantities($f)===[5.0,3.0],'API copies full order');
    snapshot('INV-ROUTE-002','draft',$ctx,$f);validateApi($f,$adminKey);snapshot('INV-ROUTE-002','validated',$ctx,$f);
    $f=apiFromOrder($ctx,$adminKey);validateApi($f,$adminKey);snapshot('INV-ROUTE-002','repeat_validated',$ctx,$f);
    verify($ctx['order']->classifyBilled($user)>0,'explicitly classify source order billed');
    [$code]=api('POST','/invoices/createfromorder/'.$ctx['order']->id,$adminKey,[]);verify($code===405,'API refuses explicitly billed order');
    snapshot('INV-ROUTE-002','explicitly_billed',$ctx,$f);

    $ctx=context('class-order');$f=new Facture($db);verify($f->createFromOrder($ctx['order'],$user)>0,'direct class order conversion');
    verify(quantities($f)===[5.0,3.0],'direct class full quantities');snapshot('INV-ROUTE-003','draft',$ctx,$f);
    verify($f->validate($user)>0,'direct class validation');snapshot('INV-ROUTE-003','validated',$ctx,$f);

    stockMode('shipment');$ctx=context('ui-shipment');$s=shipment($ctx);snapshot('INV-ROUTE-004','shipment_validated',$ctx,null,$s);
    $f=uiInvoice($ctx,'shipping',$cookie,100,null,$s);verify(quantities($f)===[2.0,1.0],'shipment UI copies shipment quantities');
    snapshot('INV-ROUTE-004','draft',$ctx,$f,$s);validateApi($f,$adminKey,$ctx['warehouse']);
    $r=snapshot('INV-ROUTE-004','validated',$ctx,$f,$s);verify($r['stock']===[8.0,9.0],'invoice adds no movement with shipment-only timing');
    $f=uiInvoice($ctx,'shipping',$cookie,100,null,$s);validateApi($f,$adminKey,$ctx['warehouse']);snapshot('INV-ROUTE-004','repeat_validated',$ctx,$f,$s);

    stockMode('none');$ctx=context('generic-api');$o=$ctx['order'];
    [$code,$id]=api('POST','/invoices',$adminKey,['socid'=>$o->socid,'type'=>Facture::TYPE_STANDARD,
        'origin'=>'commande','origin_type'=>'commande','origin_id'=>$o->id,'linked_objects'=>['commande'=>$o->id],
        'lines'=>[['desc'=>'REF005 caller supplied','subprice'=>10,'qty'=>1,'tva_tx'=>21,'fk_product'=>$ctx['products'][0],'product_type'=>0]]]);
    verify($code===200 && is_numeric($id),'generic API creates caller-supplied linked invoice');$f=fetchInvoice((int)$id);
    verify(quantities($f)===[1.0],'generic API does not auto-copy order lines');snapshot('INV-ROUTE-005','draft',$ctx,$f);
    validateApi($f,$adminKey);snapshot('INV-ROUTE-005','validated',$ctx,$f);

    foreach(['order','invoice'] as $mode) {
        stockMode($mode);$ctx=context('stock-'.$mode);$f=apiFromOrder($ctx,$adminKey);
        snapshot('INV-STOCK-'.$mode,'draft',$ctx,$f);validateApi($f,$adminKey,$ctx['warehouse']);
        $r=snapshot('INV-STOCK-'.$mode,'validated',$ctx,$f);verify($r['stock']===[5.0,7.0],'single stock timing produces one decrease '.$mode);
    }
    stockMode('invoice');$ctx=context('invoice-no-warehouse');$f=apiFromOrder($ctx,$adminKey);validateApi($f,$adminKey,0);
    $r=snapshot('INV-STOCK-warehouse','validated',$ctx,$f);verify($r['stock']===[10.0,10.0],'invoice timing with zero warehouse has no movement');
    stockMode('combined');$ctx=context('combined');$s=shipment($ctx);$f=uiInvoice($ctx,'shipping',$cookie,100,null,$s);
    snapshot('INV-STOCK-combined','draft',$ctx,$f,$s);validateApi($f,$adminKey,$ctx['warehouse']);
    $r=snapshot('INV-STOCK-combined','validated',$ctx,$f,$s);verify($r['stock']===[6.0,8.0],'shipment and invoice timing each decrement same shipped quantities');

    stockMode('none');$ctx=context('permissions');$f=apiFromOrder($ctx,$adminKey);
    $reader=new User($db);$reader->login='ref005-reader';$reader->lastname='Reference invoice reader';$reader->pass=bin2hex(random_bytes(24));$reader->admin=0;
    verify($reader->create($user)>0 && $reader->delrights(0,'','',$conf->entity)>0,'restricted invoice reader');
    foreach(rows('SELECT id FROM '.$db->prefix()."rights_def WHERE (module='facture' AND perms='lire') OR (module='commande' AND perms='lire') OR (module='societe' AND perms IN ('lire','client'))") as $right) { verify($reader->addrights($right->id)>0,'grant basic read/customer visibility'); }
    $reader->clearrights();$reader->loadRights();$reader->api_key=bin2hex(random_bytes(32));verify($reader->update($user)>0,'provision ephemeral reader key');
    [$code]=api('GET','/invoices/'.$f->id,$reader->api_key);verify($code===200,'reader GET succeeds');
    foreach(['/invoices','/invoices/createfromorder/'.$ctx['order']->id,'/invoices/'.$f->id.'/validate'] as $path) { [$code]=api('POST',$path,$reader->api_key,[]);verify($code===403,'reader denied '.$path); }
    verify($f->validate($reader)<0,'class denies invoice validation without creation permission');
    snapshot('INV-PERM-001','reader_denied',$ctx,$f);
    foreach(rows('SELECT id FROM '.$db->prefix()."rights_def WHERE module='facture' AND perms='creer'") as $right) { verify($reader->addrights($right->id)>0,'grant basic invoice creation'); }
    $reader->clearrights();$reader->loadRights();$f=apiFromOrder($ctx,$reader->api_key);validateApi($f,$reader->api_key);
    snapshot('INV-PERM-001','creator_validated',$ctx,$f);
    [$code]=api('POST','/invoices/'.$f->id.'/validate',$reader->api_key,[]);verify($code===304,'revalidation API reports nothing done');
    echo 'EVIDENCE '.json_encode($evidence,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    echo "INVOICING FUNCTIONAL CHECK PASSED\n";
} catch(Throwable $e) {
    $evidence['failure']=$e->getMessage();
    echo 'FAILED_EVIDENCE '.json_encode($evidence,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    file_put_contents('php://stderr','INVOICING FUNCTIONAL CHECK FAILED: '.$e->getMessage()."\n");$exitCode=1;
}
finally { foreach($cookies as $cookie) { if(is_file($cookie)) { unlink($cookie); } } }
exit($exitCode);
