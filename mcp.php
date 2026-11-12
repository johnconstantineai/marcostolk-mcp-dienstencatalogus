<?php
if (!defined('ABSPATH')) { exit; }
function mcs_mcp_reply($id, $result=null, $error=null, $status=200) {
    status_header($status); header('Content-Type: application/json; charset=UTF-8'); nocache_headers();
    $response=array('jsonrpc'=>'2.0','id'=>$id);
    if ($error) { $response['error']=$error; } else { $response['result']=$result; }
    echo wp_json_encode($response, JSON_UNESCAPED_UNICODE); exit;
}
add_action('parse_request', function($wp) {
    if (trim($wp->request,'/') !== 'mcp') { return; }
    mcs_public_api_limit();
    // Public, stateless catalog only. No database or customer information is exposed.
    $origin=isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    if ($origin && $origin !== rtrim(home_url('/'),'/')) { status_header(403);exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { status_header(405);header('Allow: POST');exit; }
    $raw=file_get_contents('php://input',false,null,0,16385);
    if (strlen($raw)>16384) { mcs_mcp_reply(null,null,array('code'=>-32600,'message'=>'Request exceeds 16 KiB.'),413); }
    $request=json_decode($raw,true);
    if (json_last_error() !== JSON_ERROR_NONE) { mcs_mcp_reply(null,null,array('code'=>-32700,'message'=>'Invalid JSON.'),400); }
    if (!is_array($request) || ($request['jsonrpc'] ?? '') !== '2.0' || !is_string($request['method'] ?? null)) { mcs_mcp_reply(null,null,array('code'=>-32600,'message'=>'Expected a JSON-RPC 2.0 request.'),400); }
    if (!array_key_exists('id',$request)) { status_header(202);exit; }
    $id=$request['id'];
    if (!is_string($id) && !is_int($id)) { mcs_mcp_reply(null,null,array('code'=>-32600,'message'=>'Invalid request id.'),400); }
    $method=$request['method'];$params=$request['params'] ?? array();
    if (!is_array($params)) { mcs_mcp_reply($id,null,array('code'=>-32602,'message'=>'Params must be an object.')); }
    $tool=array('name'=>'get_service_catalog','description'=>'Read Marco Collin Stolk’s public AI coaching services in Limburg, introductory-session price and contact route. No booking, messaging or payment. No account or API key required.','inputSchema'=>array('type'=>'object','properties'=>(object)array(),'additionalProperties'=>false),'annotations'=>array('readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false));
    $spec=json_decode(file_get_contents(get_template_directory().'/agent-resources/openapi.json'),true);
    $tool['outputSchema']=$spec['paths']['/api/v1/services.json']['get']['responses']['200']['content']['application/json']['schema'];
    if ($method==='initialize') { mcs_mcp_reply($id,array('protocolVersion'=>'2025-06-18','capabilities'=>array('tools'=>(object)array()),'serverInfo'=>array('name'=>'marco-collin-stolk-public-catalog','version'=>'1.0.0'),'instructions'=>'Use get_service_catalog to compare services and find the human contact route. Public read-only information; no sign-up needed.')); }
    if ($method==='ping') { mcs_mcp_reply($id,(object)array()); }
    if ($method==='tools/list') { mcs_mcp_reply($id,array('tools'=>array($tool))); }
    if ($method==='tools/call') {
        if (($params['name'] ?? '') !== 'get_service_catalog' || !empty($params['arguments'])) { mcs_mcp_reply($id,null,array('code'=>-32602,'message'=>'Use get_service_catalog with an empty arguments object.')); }
        $catalog=file_get_contents(get_template_directory().'/agent-resources/api/services.json');
        mcs_mcp_reply($id,array('content'=>array(array('type'=>'text','text'=>$catalog)),'structuredContent'=>json_decode($catalog,true),'isError'=>false));
    }
    mcs_mcp_reply($id,null,array('code'=>-32601,'message'=>'Method not found. Supported: initialize, ping, tools/list, tools/call.'));
});
