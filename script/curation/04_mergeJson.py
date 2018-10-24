import glob
import json
from hashlib import md5


def make_md5(s):
    return md5(s.encode('utf-8')).hexdigest()


input_dir = "../../docs/json"
arr = glob.glob(input_dir + "/*")

with open('data/template.json') as f:
    df = json.load(f)

id = "16-A00-6010"
uuid = make_md5(id)

df["@id"] = "https://utda.github.io/text/json/" + uuid + ".json"
selections = []
df["selections"] = selections

for path in sorted(arr):

    if path.find(uuid) == -1:
        with open(path, 'r') as f:
            data = json.load(f)  # JSON形式で読み込む
            selection = data["selections"][0]
            print(selection["within"]["@label"] + "\t" + str(len(selection["members"])))
            selections.append(selection)

with open("../../docs/json/" + uuid + ".json", 'w') as outfile:
    json.dump(df, outfile, ensure_ascii=False, indent=4, sort_keys=True, separators=(',', ': '))
