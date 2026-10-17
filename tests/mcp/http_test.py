#!/usr/bin/env python3
"""Real CMS router + endpoints on loopback, fixture DBs, no production bootstrap."""
import sys, base64, hashlib, http.cookiejar, json, os, pathlib, socket, subprocess, tempfile, time, urllib.request, urllib.parse, urllib.error, re
root=pathlib.Path(__file__).resolve().parents[2]
checks=0
def check(value,label):
    global checks
    if not value: raise AssertionError(label)
    checks+=1
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs): return None
with tempfile.TemporaryDirectory(prefix='cms-mcp-http-') as directory:
    env=os.environ.copy();env['MCP_CMS_PRIVATE_DIRECTORY']=directory+'/private';env['MCP_TEST_SESSIONS']=directory+'/sessions';os.mkdir(env['MCP_TEST_SESSIONS'])
    credentials=json.loads(subprocess.check_output(['php',str(root/'tests/mcp/http_seed.php')],env=env))
    with socket.socket() as sock: sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
    log=open(directory+'/server.log','w+')
    server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}',str(root/'tests/mcp/http_router.php')],env=env,stdout=log,stderr=log)
    opener=urllib.request.build_opener(NoRedirect,urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(path,method='GET',data=None,headers=None):
        h={'Host':'cms.example'};h.update(headers or {})
        if isinstance(data,dict): data=urllib.parse.urlencode(data).encode();h.setdefault('Content-Type','application/x-www-form-urlencoded')
        if isinstance(data,str):data=data.encode()
        req=urllib.request.Request(f'http://127.0.0.1:{port}{path}',data=data,headers=h,method=method)
        try: response=opener.open(req,timeout=5)
        except urllib.error.HTTPError as error:response=error
        return response.status,dict(response.headers),response.read().decode()
    try:
        for _ in range(100):
            try: status,_,_=request('/.well-known/oauth-protected-resource/mcp');break
            except (urllib.error.URLError,ConnectionError):time.sleep(.02)
        check(status==200,'protected resource discovery')
        status,headers,body=request('/.well-known/oauth-authorization-server/mcp-auth/cms');meta=json.loads(body)
        check(status==200 and meta['token_endpoint_auth_methods_supported']==['client_secret_basic'],'OAuth metadata')
        check(meta['code_challenge_methods_supported']==['S256'],'S256 metadata')
        status,headers,body=request('/mcp');check(status==401 and 'resource_metadata=' in headers.get('WWW-Authenticate',''),'unauthenticated MCP challenge')
        check('Set-Cookie' not in headers,'MCP remains stateless')
        for path in ['/mcp-auth/cms/token','/mcp-auth/cms/revoke']:
            status,_,_=request(path,'POST',{'grant_type':'authorization_code'});check(status==400,'OAuth machine endpoint without CSRF but authenticates client')
        verifier='V'*43;challenge=base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).decode().rstrip('=')
        params={'client_id':credentials['client'],'redirect_uri':'https://client.example/callback','response_type':'code','code_challenge':challenge,'code_challenge_method':'S256','resource':'https://cms.example/mcp','scope':'musedock-cms:read musedock-cms:drafts:create musedock-cms:requests:approve','state':'csrf-state'}
        path='/mcp-auth/cms/authorize?'+urllib.parse.urlencode(params)
        status,headers,body=request(path);check(status==200 and 'Set-Cookie' not in headers and '_ss=1' in body,'same-site bounce does not touch session')
        request('/__test/login')
        status,headers,body=request(path+'&_ss=1');check(status==200 and 'Conectar HTTP Test' in body,'authenticated consent page')
        rid=re.search(r'name="rid" value="([^"]+)"',body).group(1);csrf=re.search(r'name="_token" value="([^"]+)"',body).group(1)
        status,_,_=request('/mcp-auth/cms/authorize','POST',{'rid':rid,'decision':'approve'}, {'Accept':'application/json'});check(status==419,'consent requires CSRF')
        status,headers,body=request(path+'&_ss=1')
        rid=re.search(r'name="rid" value="([^"]+)"',body).group(1);csrf=re.search(r'name="_token" value="([^"]+)"',body).group(1)
        status,headers,_=request('/mcp-auth/cms/authorize','POST',{'rid':rid,'decision':'approve','_token':csrf})
        check(status==302 and headers.get('Location','').startswith('https://client.example/callback?'),'exact OAuth callback')
        returned=urllib.parse.parse_qs(urllib.parse.urlparse(headers['Location']).query)
        check(returned['state']==['csrf-state'] and returned['iss']==['https://cms.example/mcp-auth/cms'],'state and issuer identification')
        basic='Basic '+base64.b64encode((credentials['client']+':'+credentials['secret']).encode()).decode()
        exchange={'grant_type':'authorization_code','code':returned['code'][0],'redirect_uri':'https://client.example/callback','code_verifier':verifier,'resource':'https://cms.example/mcp'}
        bad=exchange.copy();bad['code_verifier']='W'*43
        status,_,_=request('/mcp-auth/cms/token','POST',bad,{'Authorization':basic});check(status==400,'bad PKCE denied')
        status,headers,body=request('/mcp-auth/cms/token','POST',exchange,{'Authorization':basic});tokens=json.loads(body)
        check(status==200 and 'access_token' in tokens and 'Set-Cookie' not in headers,'code exchange, no session cookie')
        status,_,_=request('/mcp-auth/cms/token','POST',exchange,{'Authorization':basic});check(status==400,'code cannot be reused')
        auth={'Authorization':'Bearer '+tokens['access_token'],'Content-Type':'application/json'}
        def rpc(method,args=None):return request('/mcp','POST',json.dumps({'jsonrpc':'2.0','id':1,'method':method,'params':args or {}}),auth)
        status,_,body=rpc('initialize',{'protocolVersion':'2025-11-25'});check(status==200 and json.loads(body)['result']['serverInfo']['name'].startswith('MuseDock CMS'),'MCP initialize')
        status,_,body=rpc('tools/list');catalog=json.loads(body)['result']['tools'];check('create_draft' in [t['name'] for t in catalog],'authorized explicit tools')
        for tool in ['get_post','get_post_details']:
            status,_,body=rpc('tools/call',{'name':tool,'arguments':{'post_id':1}})
            result=json.loads(body)['result'];text_post=json.loads(result['content'][0]['text'])
            check(status==200 and not result['isError'] and result['structuredContent']['content']==text_post['content'] and len(text_post['content'])>0,'HTTP full post is identical in structured and text responses')
        status,_,body=rpc('tools/call',{'name':'get_post','arguments':{'post_id':3}});check(json.loads(body)['result']['isError'],'HTTP foreign post denied')
        status,_,body=rpc('tools/call',{'name':'create_draft','arguments':{'title':'HTTP borrador','content':'Texto sin IA','apply':True}});check('pending_approval' in body,'HTTP write only creates approval request')
        pending=json.loads(json.loads(body)['result']['content'][0]['text'])
        status,_,body=rpc('tools/call',{'name':'approve_request','arguments':{'request_id':pending['request_id']}});check(not json.loads(body)['result']['isError'] and json.loads(json.loads(body)['result']['content'][0]['text'])['status']=='draft','HTTP explicit remote approval creates real draft')
        status,_,body=rpc('tools/call',{'name':'approve_request','arguments':{'request_id':pending['request_id']}});check(json.loads(body)['result']['isError'],'HTTP remote approval cannot replay')
        status,_,_=request('/mcp','GET',headers=auth);check(status==405,'authenticated GET has no SSE stream')
        status,_,_=request('/mcp','POST','not json',auth);check(status==400,'invalid JSON denied')
        status,_,_=request('/mcp','POST',json.dumps({'jsonrpc':'2.0','method':'notifications/initialized'}),auth);check(status==202,'MCP notification')
        status,_,_=request('/mcp','POST','{}',auth|{'Host':'evil.example'});check(status==404,'unexpected host rejected')
        status,_,_=request('/mcp/cross-publisher','POST','{}',auth);check(status==404,'unconfigured plugin stays unavailable')
        status,_,_=request('/mcp-auth/cms/revoke','POST',{'token':tokens['access_token']},{'Authorization':basic});check(status==200,'token revocation')
        status,_,_=rpc('tools/list');check(status==401,'revoked token denied')
        # Confirm actual router did not expose admin or arbitrary paths in this test surface.
        for path in ['/musedock/settings/api-keys','/login','/storage/private/mcp.sqlite']:
            status,_,_=request(path);check(status in (302,404),'administrative route not an MCP tool')
        check(os.stat(env['MCP_CMS_PRIVATE_DIRECTORY']+'/mcp.sqlite').st_mode & 0o777 == 0o600,'private OAuth database permissions')
        # Anonymous protected-content flow, using the native guard/unlock controller.
        opener=urllib.request.build_opener(NoRedirect,urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        status,headers,body=request('/__test/private')
        check(status==404 and 'private-body' not in body and 'no-store' in headers.get('Cache-Control',''),'private body withheld and never cacheable')
        status,headers,body=request('/__test/protected')
        check(status==401 and 'protected-body' not in body and 'no-store' in headers.get('Cache-Control',''),'password body withheld and never cacheable')
        password_csrf=re.search(r'name="_csrf" value="([^"]+)"',body).group(1)
        status,_,_=request('/blog/unlock/1','POST',{'password':'test-password'},{'Accept':'application/json'})
        check(status in (403,419),'password unlock requires CSRF')
        _,_,body=request('/__test/protected')
        password_csrf=re.search(r'name="_csrf" value="([^"]+)"',body).group(1)
        status,_,_=request('/blog/unlock/1','POST',{'password':'wrong','_csrf':password_csrf})
        check(status==401,'incorrect password denied')
        status,headers,_=request('/blog/unlock/1','POST',{'password':'test-password','_csrf':password_csrf})
        check(status==302 and headers.get('Location')=='/blog/principal','password unlock redirects to own article')
        status,headers,body=request('/__test/protected')
        check(status==200 and body=='protected-body' and 'no-store' in headers.get('Cache-Control',''),'unlocked body rendered without shared caching')
        print(f'PASS: {checks} HTTP checks; real CMS routes and endpoints, loopback only.')
    finally:
        server.terminate();server.wait(timeout=5);
        if sys.exc_info()[0]:
            log.seek(0);print(log.read()[-6000:],file=sys.stderr)
        log.close()
