<?php
function mcs_public_api_limit() {
    $now=time(); $reset=60-($now%60); $limit=60;
    $key='mcs_rl_'.hash_hmac('sha256',($_SERVER['REMOTE_ADDR'] ?? 'unknown').':'.intdiv($now,60),wp_salt('nonce'));
    $count=(int)get_transient($key)+1; set_transient($key,$count,$reset+1);
    header('RateLimit-Limit: '.$limit); header('RateLimit-Remaining: '.max(0,$limit-$count)); header('RateLimit-Reset: '.$reset);
    header('RateLimit-Policy: 60;w=60');
    if ($count>$limit) {
        status_header(429); nocache_headers(); header('Retry-After: '.$reset); header('Content-Type: application/problem+json; charset=UTF-8');
        echo wp_json_encode(array('type'=>'about:blank','title'=>'Too Many Requests','status'=>429,'detail'=>'Wait for Retry-After seconds before trying again.')); exit;
    }
}
