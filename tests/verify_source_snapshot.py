#!/usr/bin/env python3
"""QA checks against external user's JSON. No private file is included in release."""
import json, re, sys, datetime as dt
import xml.etree.ElementTree as ET
from collections import Counter

if len(sys.argv)!=3:
    raise SystemExit('Usage: python verify_source_snapshot.py snapshot.json exported.xml')
source=json.load(open(sys.argv[1],encoding='utf-8'))
root=ET.parse(sys.argv[2]).getroot()
ns={'m':'http://schemas.microsoft.com/project'}
rows=[]
for e in root.findall('./m:Tasks/m:Task',ns):
    rows.append({f:e.findtext(f'm:{f}','',ns) for f in ['UID','ID','Name','OutlineLevel','WBS','Start','Finish','Deadline','Notes','Milestone','Duration']}
          | {'links':[x.findtext('m:PredecessorUID','',ns) for x in e.findall('m:PredecessorLink',ns)]})
assert rows[0]['UID']=='0'
cards={str(c['technical']['sourceId']):c for c in source['cards']}
cardrows={r['UID']:r for r in rows if r['UID'] in cards}
assert len(cards)==len(cardrows)==36,(len(cards),len(cardrows))
assert len(set(r['UID'] for r in rows))==len(rows),'Duplicate UID'
assert len(set(r['ID'] for r in rows))==len(rows),'Duplicate XML ID'
assert sum(len(c['detail']['subtasks']) for c in cards.values())==116
assert '116' in rows[0]['Notes']
assert sum(bool(c['technical']['sourceParentId']) for c in cards.values())==13
assert all(not r['links'] for r in rows),'This project has no genuine precedence dependencies'
assert all(r['Name'].startswith('Prazo Kanboard:') for r in rows[1:] if r['UID'] not in cards)
for cid,c in cards.items():
    base=c['base'];r=cardrows[cid]
    assert r['Name'].startswith(base['title']),cid
    a=base['date_started'];b=base['date_due']
    orig_start=a not in ('','Iniciar agora')
    orig_due=bool(b)
    if orig_start:assert r['Start']==dt.datetime.strptime(a,'%d/%m/%Y %H:%M').isoformat(),(cid,a,r['Start'])
    if orig_start and orig_due:
        seconds=int((dt.datetime.strptime(b,'%d/%m/%Y %H:%M')-dt.datetime.strptime(a,'%d/%m/%Y %H:%M')).total_seconds())
        adjusted=seconds-(seconds//86400)*60
        want=f'PT{adjusted//3600}H{(adjusted%3600)//60}M{adjusted%60}S'
        assert r['Duration']==want,(cid,r['Duration'],want)
    if orig_due:
        exact=dt.datetime.strptime(b,'%d/%m/%Y %H:%M').isoformat()
        assert r['Deadline']==exact,(cid,b,r['Deadline'])
        if cid not in {'14','19','22','33','10'} and not c['technical']['sourceChildrenIds']:
            assert r['Finish']==exact,(cid,b,r['Finish'])
    if not orig_start and orig_due and not c['technical']['sourceChildrenIds']:
        assert r['Start']==r['Finish']==r['Deadline'],(cid,r)
        assert r['Milestone']=='1',(cid,r)
    if not orig_start and not orig_due:
        assert 'SEM DATAS' in r['Name'],cid
    assert (('Original Kanboard start: '+a) if orig_start else 'Original Kanboard start: NOT SET') in r['Notes'],cid
    if c['technical']['sourceParentId']:
        parent=cardrows[c['technical']['sourceParentId']]
        assert int(r['OutlineLevel'])==int(parent['OutlineLevel'])+1,(cid,parent['UID'])
    for sub in c['detail']['subtasks']:
        assert f"#{sub['id']} {sub['title']}" in r['Notes'],(cid,sub['id'])
markers=[r for r in rows if r['UID'] not in cards and r['UID']!='0']
assert len(markers)==sum(bool(c['technical']['sourceChildrenIds'] and c['base']['date_due']) for c in cards.values()),len(markers)
for r in markers:
    suffix=r['Name'].split(': ')[-1]
    assert suffix in {dt.datetime.strptime(c['base']['date_due'],'%d/%m/%Y %H:%M').date().isoformat() for c in cards.values() if c['base']['date_due']}
    assert r['Start']==r['Finish']
print(f"PASS: {len(cards)} exact source IDs; 13 EAP; 116 notes-only subtasks; {len(markers)} deadline markers; {len(rows)} XML tasks; all original timestamps checked")
print('Explicitly unscheduled:',sum(c['base']['date_started'] in ('Iniciar agora','') and not c['base']['date_due'] for c in cards.values()))
print('Due only:',sum(c['base']['date_started'] in ('Iniciar agora','') and bool(c['base']['date_due']) for c in cards.values()))
print('Dated ranges:',sum(c['base']['date_started'] not in ('Iniciar agora','') and bool(c['base']['date_due']) for c in cards.values()))
