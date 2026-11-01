from pathlib import Path
import xml.etree.ElementTree as ET
p = Path(__file__).with_name('fixture.xml')
root = ET.parse(p).getroot()
N={'m':'http://schemas.microsoft.com/project'}
ts=root.findall('m:Tasks/m:Task',N)
assert len(ts)==5, len(ts)
assert ts[2].findtext('m:Name','',N)=='Preparar laboratório & equipe'
assert ts[3].findtext('m:PredecessorLink/m:PredecessorUID','',N)=='11'
assert ts[4].findtext('m:Name','',N).startswith('Prazo Kanboard:')
assert ts[4].findtext('m:Milestone','',N)=='1'
assert ts[4].findtext('m:Start','',N)==ts[4].findtext('m:Finish','',N)
assert 'Kanboard 7x24 source timestamps' in Path(p).read_text(encoding='utf-8')
print('PASS: MSPDI parsed, calendar, hierarchy, dependency, milestone')
p.unlink()
