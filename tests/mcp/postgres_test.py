#!/usr/bin/env python3
"""Run CMS fixtures in disposable PostgreSQL, with a private socket and no TCP."""
import os,pathlib,pwd,subprocess,tempfile
root=pathlib.Path(__file__).resolve().parents[2]
pg=pathlib.Path('/usr/lib/postgresql/16/bin')
with tempfile.TemporaryDirectory(prefix='cms-mcp-pg-') as temp:
    data=temp+'/data'; user=pwd.getpwuid(os.getuid()).pw_name
    subprocess.run([str(pg/'initdb'),'-D',data,'-A','trust','--no-locale'],stdout=subprocess.DEVNULL,check=True)
    subprocess.run([str(pg/'pg_ctl'),'-D',data,'-l',temp+'/log','-o',"-k "+temp+" -c listen_addresses=''",'-w','start'],stdout=subprocess.DEVNULL,check=True)
    try:
        subprocess.run([str(pg/'createdb'),'-h',temp,'cms_mcp_test'],check=True)
        env=os.environ.copy();env['MCP_TEST_PG_DSN']='pgsql:host='+temp+';dbname=cms_mcp_test';env['MCP_TEST_PG_USER']=user
        subprocess.run(['php',str(root/'tests/mcp/run.php')],env=env,check=True,cwd=root)
        print('PASS: PostgreSQL suite; private socket, no production connection.')
    finally:
        subprocess.run([str(pg/'pg_ctl'),'-D',data,'-w','stop'],stdout=subprocess.DEVNULL,check=True)
