#!/usr/bin/env python3
"""Explicit backup input, restored into private disposable PostgreSQL; no TCP."""
import os,pathlib,pwd,subprocess,tempfile,sys
root=pathlib.Path(__file__).resolve().parents[2];pg=pathlib.Path('/usr/lib/postgresql/16/bin')
if len(sys.argv)!=2:raise SystemExit('Usage: clone_test.py /private/path/database.dump')
dump=str(pathlib.Path(sys.argv[1]).resolve())
with tempfile.TemporaryDirectory(prefix='cms-mcp-clone-') as temp:
    data=temp+'/data';user=pwd.getpwuid(os.getuid()).pw_name
    subprocess.run([str(pg/'initdb'),'-D',data,'-A','trust','--no-locale'],stdout=subprocess.DEVNULL,check=True)
    subprocess.run([str(pg/'pg_ctl'),'-D',data,'-l',temp+'/log','-o',"-k "+temp+" -c listen_addresses=''",'-w','start'],stdout=subprocess.DEVNULL,check=True)
    try:
        subprocess.run([str(pg/'createdb'),'-h',temp,'cms_mcp_clone'],check=True)
        with open(temp+'/restore.log','wb') as log:
            restore=subprocess.run([str(pg/'pg_restore'),'-h',temp,'-d','cms_mcp_clone','--no-owner','--no-acl',dump],stdout=log,stderr=log)
        if restore.returncode:raise RuntimeError('Clone restore failed; the disposable private restore log was not printed to avoid exposing database contents.')
        env=os.environ.copy();env['MCP_CLONE_DSN']='pgsql:host='+temp+';dbname=cms_mcp_clone';env['MCP_CLONE_USER']=user
        subprocess.run(['php',str(root/'tests/mcp/clone_check.php')],env=env,check=True,cwd=root)
    finally:
        subprocess.run([str(pg/'pg_ctl'),'-D',data,'-w','stop'],stdout=subprocess.DEVNULL,check=True)
