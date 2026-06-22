import requests
import json
import sys

url = "https://validator.schema.org/validate"
data = {
    "url": "https://tiktak.by/ru/prokat-detskih-tovarov/begovely_velosipedy_samokaty/velosiped-detskii-prokat/velosiped_lorelli-aguar"
}
response = requests.post(url, data=data)
text = response.text
if text.startswith(")]}'"):
    text = text[4:]
res = json.loads(text)
for g in res.get("tripleGroups", []):
    for n in g.get("nodes", []):
        for t in n.get("types", []):
            for e in t.get("errors", []):
                print(e)
