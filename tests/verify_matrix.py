import sys,xml.etree.ElementTree as ET
from pathlib import Path
D=Path(sys.argv[1]);N={'m':'http://schemas.microsoft.com/project'}
def rows(name):
    root=ET.parse(D/f'{name}.xml').getroot()
    ts=root.findall('./m:Tasks/m:Task',N)
    assert len({t.findtext('m:UID','',N) for t in ts})==len(ts),name
    assert [int(t.findtext('m:ID','',N)) for t in ts]==list(range(len(ts))),name
    return ts

def f(t,k):return t.findtext('m:'+k,'',N)
T=rows('real_timestamps');assert f(T[1],'Start')=='2026-09-12T19:21:00';assert f(T[1],'Finish')=='2026-09-20T19:22:00'
assert f(T[1],'Duration')=='PT191H53M0S',f(T[1],'Duration')  # 192h01m - 8 importer-minutes
assert f(T[1],'Deadline')=='2026-09-20T19:22:00'
T=rows('due_only');assert f(T[1],'Start')==f(T[1],'Finish')==f(T[1],'Deadline');assert f(T[1],'Milestone')=='1'
T=rows('start_only');assert f(T[1],'Start')==f(T[1],'Finish');assert 'NAO CADASTRADO' in f(T[1],'Notes')
T=rows('missing_both');assert 'SEM DATAS' in f(T[1],'Name');assert 'APENAS para visualizacao' in f(T[1],'Notes')
T=rows('hierarchy_notes');assert len(T)==4;assert f(T[1],'OutlineLevel')=='1';assert f(T[2],'OutlineLevel')=='2'
assert f(T[3],'OutlineLevel')=='2';assert 'Subtarefa sem data' in f(T[1],'Notes')
assert 'CONFLITO NA ORIGEM' in f(T[1],'Notes');assert f(T[3],'Start')=='2026-10-06T19:22:00'
T=rows('predecessor');assert len(T[2].findall('m:PredecessorLink',N))==1
assert f(T[2].find('m:PredecessorLink',N),'PredecessorUID')=='1'
T=rows('unrelated_duplicates');assert not T[2].findall('m:PredecessorLink',N)
T=rows('special_text');assert f(T[1],'Name')=='Árvore <A> & "projeto"'
print('PASS: dates, timestamps, unscheduled markings, source conflict, EAP, dependency, escaping')
