<?php
// Controlled characterization inside the disposable commercial-check container.
// Uses Dolibarr business classes for setup and real HTTP forms for UI actions.
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

function verify($ok, $label) {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS: $label\n";
}
function configValue($key, $value) {
    global $db, $conf;
    verify(dolibarr_set_const($db, $key, (string) $value, 'chaine', 0, '', $conf->entity) > 0, "configure $key=$value");
    $conf->global->$key = (string) $value;
}
function rows($sql) {
    global $db;
    $result = $db->query($sql);
    if (!$result) { throw new RuntimeException('Read-only observation query failed'); }
    $rows = [];
    while ($row = $db->fetch_object($result)) { $rows[] = $row; }
    return $rows;
}
function linkedOrders($proposal) {
    global $db;
    return rows('SELECT fk_target AS id FROM '.$db->prefix()."element_element WHERE sourcetype='propal' AND targettype='commande' AND fk_source=".(int) $proposal);
}
function orderCount() {
    global $db;
    return (int) rows('SELECT COUNT(*) AS n FROM '.$db->prefix().'commande')[0]->n;
}
function fetchOrder($id) {
    global $db;
    $o = new Commande($db);
    verify($o->fetch($id) > 0, 'fetch created order');
    return $o;
}
function proposal($socid, $admin) {
    global $db;
    $p = new Propal($db);
    $p->socid = $socid;
    $p->date = dol_now();
    $p->cond_reglement_id = 1;
    $p->mode_reglement_id = 1;
    verify($p->create($admin) > 0, 'create proposal through business class');
    verify($p->addline('REF003 selected service', 50, 2, 21, 0, 0, 0, 0, 'HT', 0, 0, 1) > 0, 'add priced line A');
    verify($p->addline('REF003 omitted service', 30, 1, 21, 0, 0, 0, 0, 'HT', 0, 0, 1) > 0, 'add priced line B');
    verify($p->fetch($p->id) > 0, 'reload proposal lines');
    verify($p->valid($admin) > 0, 'validate proposal');
    verify($p->fetch($p->id) > 0, 'reload validated proposal');
    return $p;
}
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
function login($name, $password, $cookie) {
    [$code, $html] = http('/index.php', $cookie);
    $fields = formFields($html);
    $fields['username'] = $name; $fields['password'] = $password; $fields['actionlogin'] = 'login';
    [$code] = http('/index.php', $cookie, $fields);
    verify(in_array($code, [200, 302, 303], true), 'submit UI login');
    [$code, $html] = http('/admin/workflow.php', $cookie);
    return $html;
}
function actionHref($html, $fragment) {
    $dom = new DOMDocument(); @$dom->loadHTML($html);
    foreach ($dom->getElementsByTagName('a') as $link) {
        $href = $link->getAttribute('href');
        if (str_contains($href, $fragment) && !str_contains($link->getAttribute('class'), 'Refused')) { return $href; }
    }
    throw new RuntimeException('Expected actionable UI link not found');
}
function manualCreate($p, $cookie, $selected, $cancel = false) {
    $path = '/commande/card.php';
    [$code, $html] = http('/comm/propal/card.php?id='.$p->id, $cookie);
    $href = actionHref($html, '/commande/card.php?action=create&origin=propal');
    [$code, $html] = http($href, $cookie);
    verify($code === 200, 'open UI order creation form');
    $fields = formFields($html, 'add');
    $fields['toselect'] = $selected;
    if ($cancel) { $fields['cancel'] = 'Cancel'; }
    [$code] = http($path, $cookie, $fields);
    verify(in_array($code, [302, 303], true), $cancel ? 'UI cancel redirects' : 'UI creation redirects');
}

$cookies = [];
$exitCode = 0;
try {
    global $user, $db, $conf;
    $admin = new User($db);
    verify($admin->fetch(0, getenv('DOLI_ADMIN_LOGIN')) > 0, 'load laboratory administrator');
    $admin->loadRights();
    $user = $admin;
    configValue('MAIN_USE_ADVANCED_PERMS', 0);
    configValue('WORKFLOW_PROPAL_AUTOCREATE_ORDER', 0);
    configValue('ORDER_VALID_AFTER_CLOSE_PROPAL', 0);
    $soc = new Societe($db); $soc->name = 'REF003 isolated customer'; $soc->client = 0;
    verify($soc->create($admin) > 0, 'create initially non-customer third party');
    $cookie = tempnam('/tmp', 'ref003-admin-'); $cookies[] = $cookie;
    $html = login(getenv('DOLI_ADMIN_LOGIN'), getenv('DOLI_ADMIN_PASSWORD'), $cookie);
    verify(str_contains($html, 'WORKFLOW_PROPAL_AUTOCREATE_ORDER'), 'authenticated workflow UI');

    $p = proposal($soc->id, $admin);
    verify($p->closeProposal($admin, Propal::STATUS_SIGNED) > 0, 'accept proposal with workflow disabled');
    verify(count(linkedOrders($p->id)) === 0, 'disabled workflow creates no order');
    $soc->fetch($soc->id); verify((int) $soc->client === 1, 'acceptance reclassifies non-customer as customer');
    configValue('ORDER_VALID_AFTER_CLOSE_PROPAL', 1);
    manualCreate($p, $cookie, [(int) $p->lines[0]->id]);
    $links = linkedOrders($p->id); verify(count($links) === 1, 'manual UI creates linked order');
    $o = fetchOrder($links[0]->id);
    verify(count($o->lines) === 1 && $o->lines[0]->desc === 'REF003 selected service', 'partial selection copies only selected line');
    verify((int) $o->status === 0, 'manual UI remains draft even with immediate option enabled');
    verify((float) $o->lines[0]->qty === 2.0 && (float) $o->lines[0]->subprice === 50.0 && (float) $o->lines[0]->tva_tx === 21.0, 'manual selected-line quantity, unit price and VAT');
    verify((float) $o->total_ht === 100.0 && (float) $o->total_tva === 21.0 && (float) $o->total_ttc === 121.0, 'manual selected-line totals 100/21/121');

    $empty = proposal($soc->id, $admin); $empty->closeProposal($admin, Propal::STATUS_SIGNED);
    manualCreate($empty, $cookie, []);
    $links = linkedOrders($empty->id); verify(count($links) === 1, 'empty selection creates linked order');
    $emptyOrder = fetchOrder($links[0]->id);
    verify(count($emptyOrder->lines) === 0 && (int) $emptyOrder->status === 0, 'empty selection creates zero-line draft');
    $cancel = proposal($soc->id, $admin); $cancel->closeProposal($admin, Propal::STATUS_SIGNED);
    $before = orderCount(); manualCreate($cancel, $cookie, [], true);
    verify(orderCount() === $before && count(linkedOrders($cancel->id)) === 0, 'explicit cancellation creates no order');

    $restricted = new User($db);
    $restricted->login = 'ref003-restricted'; $restricted->lastname = 'Reference Restricted';
    $restricted->pass = bin2hex(random_bytes(24)); $password = $restricted->pass; $restricted->admin = 0;
    verify($restricted->create($admin) > 0, 'create non-admin laboratory user');
    verify($restricted->delrights(0, '', '', $conf->entity) > 0, 'remove default permissions');
    // Resolve permission identifiers from runtime metadata, rather than hardcoding IDs.
    foreach ([['propale', 'lire'], ['propale', 'creer'], ['societe', 'lire'], ['societe', 'client'], ['commande', 'lire']] as [$module, $permission]) {
        $rights = rows('SELECT id FROM '.$db->prefix()."rights_def WHERE module='".$db->escape($module)."' AND perms='".$db->escape($permission)."'");
        foreach ($rights as $right) { verify($restricted->addrights($right->id) > 0, "grant $module/$permission"); }
    }
    $restricted->clearrights(); $restricted->loadRights();
    verify(!$restricted->admin && !$restricted->hasRight('commande', 'creer') && $restricted->hasRight('propal', 'creer'), 'restricted user can manage proposals but cannot create orders');
    $restricted->api_key = bin2hex(random_bytes(32));
    verify($restricted->update($admin) > 0, 'provision ephemeral restricted API key');
    $restrictedCookie = tempnam('/tmp', 'ref003-user-'); $cookies[] = $restrictedCookie;
    login($restricted->login, $password, $restrictedCookie);
    [$code, $html] = http('/comm/propal/card.php?id='.$p->id, $restrictedCookie);
    verify($code === 200 && str_contains($html, $p->ref) && !str_contains($html, 'name="actionlogin"') && !str_contains(html_entity_decode($html), '/commande/card.php?action=create&origin=propal'), 'restricted UI offers no actionable Add order link');

    configValue('WORKFLOW_PROPAL_AUTOCREATE_ORDER', 1);
    configValue('ORDER_VALID_AFTER_CLOSE_PROPAL', 0);
    $automatic = proposal($soc->id, $admin);
    [$code] = http('/api/index.php/proposals/'.$automatic->id.'/close?status=2', $restrictedCookie, [], $restricted->api_key);
    verify($code === 200, 'restricted proposal API close succeeds');
    $links = linkedOrders($automatic->id); verify(count($links) === 1, 'automatic workflow creates order without order creation permission');
    $automaticOrder = fetchOrder($links[0]->id);
    verify((int) $automaticOrder->status === 0 && count($automaticOrder->lines) === 2, 'automatic conversion copies both lines into draft');
    foreach ($automatic->lines as $index => $sourceLine) {
        $targetLine = $automaticOrder->lines[$index];
        verify($sourceLine->desc === $targetLine->desc, 'automatic line description fidelity');
        foreach (['qty', 'subprice', 'tva_tx', 'total_ht', 'total_tva', 'total_ttc'] as $field) {
            verify((float) $sourceLine->$field === (float) $targetLine->$field, 'automatic line '.$field.' fidelity');
        }
    }
    verify((float) $automaticOrder->total_ht === 130.0 && (float) $automaticOrder->total_tva === 27.3 && (float) $automaticOrder->total_ttc === 157.3, 'automatic two-line totals 130/27.30/157.30');
    $before = orderCount();
    verify($automatic->fetch($automatic->id) > 0 && $automatic->closeProposal($admin, Propal::STATUS_SIGNED) > 0, 'repeat accepted close through service');
    verify(orderCount() === $before && count(linkedOrders($automatic->id)) === 1, 'existing link prevents additional automatic order in sequential execution');

    [$code, $html] = http('/commande/card.php?id='.$automaticOrder->id, $restrictedCookie);
    verify($code === 200 && str_contains($html, $automaticOrder->ref) && !str_contains($html, 'action=validate&'), 'restricted UI offers no validation action');
    verify($automaticOrder->valid($restricted) < 0 && $automaticOrder->error === 'NotEnoughPermissions', 'service rejects subsequent validation without order permission');
    [$code] = http('/api/index.php/orders/'.$automaticOrder->id.'/validate', $restrictedCookie, [], $restricted->api_key);
    verify($code === 403, 'API rejects subsequent validation without order permission (HTTP 403)');
    [$code, $html] = http('/commande/card.php?id='.$automaticOrder->id, $cookie);
    verify($code === 200 && str_contains($html, 'action=validate'), 'authorized UI offers validation action');
    $href = actionHref($html, 'action=validate&');
    [$code, $html] = http($href, $cookie);
    // The default UI confirmation is a JavaScript dialog, not an HTML form.
    // Follow its emitted confirmation URL and CSRF token; do not disable CSRF.
    verify(preg_match("/var pageyes = '([^']*action=confirm_validate[^']*)'/", $html, $target) === 1
        && preg_match('/var options = "token=([^"&]+)"/', $html, $token) === 1, 'UI emits confirmation URL and CSRF token');
    [$code] = http(stripcslashes($target[1]).'&token='.$token[1], $cookie);
    $automaticOrder->fetch($automaticOrder->id);
    verify((int) $automaticOrder->status === 1, 'authorized user validates draft through UI confirmation');

    configValue('ORDER_VALID_AFTER_CLOSE_PROPAL', 1);
    $immediate = proposal($soc->id, $admin);
    verify($immediate->closeProposal($admin, Propal::STATUS_SIGNED) > 0, 'admin automatic close with immediate validation');
    $links = linkedOrders($immediate->id); verify(count($links) === 1 && (int) fetchOrder($links[0]->id)->status === 1, 'authorized automatic conversion validates immediately');
    $failedValidation = proposal($soc->id, $admin);
    [$code] = http('/api/index.php/proposals/'.$failedValidation->id.'/close?status=2', $restrictedCookie, [], $restricted->api_key);
    $links = linkedOrders($failedValidation->id);
    verify($code === 200 && count($links) === 1 && (int) fetchOrder($links[0]->id)->status === 0, 'restricted immediate validation leaves created order in draft despite successful close');
    echo "COMMERCIAL FUNCTIONAL CHECK PASSED (basic permissions; no advanced scenarios)\n";
} catch (Throwable $e) {
    file_put_contents('php://stderr', 'COMMERCIAL FUNCTIONAL CHECK FAILED: '.$e->getMessage()."\n");
    $exitCode = 1;
} finally {
    foreach ($cookies as $cookie) { if (is_file($cookie)) { unlink($cookie); } }
}

exit($exitCode);
