"""HTTP integration against an explicitly selected tenant. Synthetic data, removed in finally. No email."""
import subprocess,json,secrets,tempfile,re,urllib.parse,os,sys
host=sys.argv[1] if len(sys.argv)>1 else 'aca.musedock.org'
if host!='aca.musedock.org':raise SystemExit('Provide a reviewed tenant mapping before running elsewhere')
boot='require "/var/www/vhosts/musedock.com/.codex/backups/festival-style-20261023/db.php";'
runid='check-'+secrets.token_hex(8)
code=boot+'''$q=$db->prepare("INSERT INTO md_forms (tenant_id,slug,title,fields,source,source_id) VALUES (71,?,'Integration fixture',?,'integration-test',?) RETURNING id");$q->execute([$argv[1],json_encode([["key"=>"email","type"=>"email","label"=>"Email","required"=>true]]),$argv[1]]);$id=$q->fetchColumn();$q=$db->prepare("INSERT INTO md_members (tenant_id,email,username,password_hash,display_name,status,profile,source,source_id) VALUES (71,?,?,?, ?,?, ?,?,?) RETURNING id");$q->execute([$argv[1]."@example.test",$argv[1],password_hash("Fixture-Only-Password-2026",PASSWORD_DEFAULT),"Fixture synthetic member","approved",json_encode(["DNI"=>"PRIVATE-FIXTURE"]),"integration-test",$argv[1]]);echo json_encode(["id"=>$id,"member_id"=>$q->fetchColumn()]);'''
state=json.loads(subprocess.run(['php','-r',code,runid],capture_output=True,text=True,check=True).stdout)
jar=tempfile.NamedTemporaryFile(delete=False);jar.close();os.chmod(jar.name,0o600)
def request(path,data=None):
 args=['curl','-sS','--max-time','15','-b',jar.name,'-c',jar.name,'-w','\nHTTP:%{http_code}','https://'+host+path]
 if data is not None:args+=['--data-binary','@-']
 r=subprocess.run(args,input=urllib.parse.urlencode(data) if data is not None else None,capture_output=True,text=True,check=True);body,code=r.stdout.rsplit('\nHTTP:',1);return body,int(code)
try:
 body,status=request('/md-forms/form/'+runid);assert status==200
 token=re.search(r'name="_csrf" value="([^"]+)"',body).group(1)
 body,status=request('/md-forms/submit/'+runid,{'_csrf':token,'email':'fixture@example.test','privacy_ack':'1'});assert status==200 and 'Solicitud recibida' in body,(status,body[:100])
 body,status=request('/md-forms/submit/'+runid,{'_csrf':'invalid','email':'fixture@example.test','privacy_ack':'1'});assert status in (302,403,419),(status,body[:100])
 body,status=request('/md-forms/manage');assert status in (302,401,403)
 body,status=request('/md-members/manage');assert status in (302,401,403)
 verify=boot+'$q=$db->prepare("SELECT COUNT(*) FROM md_form_submissions WHERE tenant_id=71 AND form_id=?");$q->execute([$argv[1]]);echo $q->fetchColumn();'
 assert subprocess.run(['php','-r',verify,str(state['id'])],capture_output=True,text=True,check=True).stdout.strip()=='1'
 body,status=request('/md-members/login');assert status==200
 token=re.search(r'name="_csrf" value="([^"]+)"',body).group(1)
 body,status=request('/md-members/login',{'_csrf':token,'identifier':runid,'password':'Fixture-Only-Password-2026'});assert status==303,(status,body[:100])
 body,status=request('/md-members/account');assert status==200 and 'PRIVATE-FIXTURE' in body
 body,status=request('/md-members/directory');assert 'PRIVATE-FIXTURE' not in body and 'Fixture synthetic member' not in body
 body,status=request('/md-members/manage');assert status in (302,401,403)
 print('OK HTTP: formulario nativo, sesión/CSRF, envío real de prueba, rechazo CSRF inválido y paneles protegidos.')
finally:
 cleanup=boot+'$db->beginTransaction();$q=$db->prepare("DELETE FROM md_form_submissions WHERE tenant_id=71 AND form_id=?");$q->execute([$argv[1]]);$q=$db->prepare("DELETE FROM md_members WHERE tenant_id=71 AND source=\'integration-test\' AND source_id=?");$q->execute([$argv[2]]);$q=$db->prepare("DELETE FROM md_forms WHERE tenant_id=71 AND id=? AND source=\'integration-test\'");$q->execute([$argv[1]]);$db->commit();'
 subprocess.run(['php','-r',cleanup,str(state['id']),runid],capture_output=True,text=True,check=True);os.unlink(jar.name)
