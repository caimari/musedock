#!/usr/bin/env python3
"""Unprivileged, private Unix-socket MariaDB; no access to the production instance."""
import os, pathlib, pwd, shutil, subprocess, tempfile, time
root=pathlib.Path(__file__).resolve().parents[2]
installer=shutil.which('mariadb-install-db');daemon=shutil.which('mariadbd');client=shutil.which('mariadb')
if not all([installer,daemon,client]):raise SystemExit('Install MariaDB tools to run this isolated SQL suite.')
with tempfile.TemporaryDirectory(prefix='cms-mcp-mariadb-') as temp:
    data=temp+'/data';sock=temp+'/mysql.sock'
    result=subprocess.run([installer,'--datadir='+data,'--auth-root-authentication-method=normal','--skip-test-db'],stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True)
    if result.returncode:raise RuntimeError(result.stdout)
    log=open(temp+'/console.log','w+')
    server=subprocess.Popen([daemon,'--no-defaults','--datadir='+data,'--socket='+sock,'--skip-networking','--pid-file='+temp+'/pid','--log-error='+temp+'/error.log','--innodb-buffer-pool-size=32M','--user='+pwd.getpwuid(os.getuid()).pw_name],stdout=log,stderr=log)
    try:
        for _ in range(200):
            if server.poll() is not None:raise RuntimeError(pathlib.Path(temp+'/error.log').read_text())
            if pathlib.Path(sock).exists():
                ping=subprocess.run([client,'--no-defaults','--socket='+sock,'-uroot','-e','SELECT 1'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
                if ping.returncode==0:break
            time.sleep(.05)
        else:raise RuntimeError('Isolated MariaDB did not start.')
        subprocess.run([client,'--no-defaults','--socket='+sock,'-uroot','-e','CREATE DATABASE cms_mcp_test'],check=True)
        env=os.environ.copy();env['MCP_TEST_MYSQL_DSN']='mysql:unix_socket='+sock+';dbname=cms_mcp_test;charset=utf8mb4'
        subprocess.run(['php',str(root/'tests/mcp/run.php')],env=env,check=True,cwd=root)
        print('PASS: content and REST suite against isolated MariaDB; no network listener.')
    finally:
        server.terminate();server.wait(timeout=15);log.close()
