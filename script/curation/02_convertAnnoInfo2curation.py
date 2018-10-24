# -*- coding: utf-8 -*-
import urllib.request, json
from bs4 import BeautifulSoup
from hashlib import md5
import csv


def make_md5(s):
    return md5(s.encode('utf-8')).hexdigest()


def get_list(path):
    data = {}
    with open(path, 'r') as f:
        reader = csv.reader(f)
        header = next(reader)  # ヘッダーを読み飛ばしたい時

        for row in reader:
            id = row[9]
            meta = {}
            data[id] = meta
            meta["和暦"] = row[0]
            meta["西暦コード"] = row[1]
            meta["タイトル"] = row[2]
            meta["版種類"] = row[3]
            meta["数量"] = row[4]
            meta["書誌事項"] = row[5]
            meta["形態分類"] = row[6]
            meta["内容分類"] = row[7]
            meta["地名"] = row[8]
            meta["所蔵機関（名称）"] = row[10]
            meta["年号"] = row[11]
            meta["閏"] = row[12]
            meta["月"] = row[13]
            meta["日"] = row[14]

    return data


def getInfoFromManifest(url):
    response = urllib.request.urlopen(url)
    response_body = response.read().decode("utf-8")
    data = json.loads(response_body.split('\n')[0])

    anno_list_url = data["sequences"][0]["canvases"][0]["otherContent"][0]["@id"];

    image_url = data["sequences"][0]["canvases"][0]["images"][0]["resource"]["service"]["@id"];
    image_url = image_url + "/full/600,/0/default.jpg"

    original_manifest = data["sequences"][0]["canvases"][0]["metadata"][0]["value"];
    source_manifest = collection_manifest.replace("http://", "https://")
    label = data["label"]

    response = urllib.request.urlopen(anno_list_url)
    response_body = response.read().decode("utf-8")
    data = json.loads(response_body.split('\n')[0])

    resources = data["resources"]

    for i in range(len(resources)):
        resource = resources[i]
        text = resource["resource"][0]["chars"]
        text = BeautifulSoup(text, "lxml").text

        o_name = text

        selector = resource["on"][0]["selector"]["default"]["value"]

        id = resource["@id"]

        canvas_id = resource["on"][0]["full"]

        manfiest = resource["on"][0]["within"]["@id"]

        n = int(o_name.split("-")[4])

        members[n] = {}
        members[n]["selection"] = canvas_id + "#" + selector
        members[n]["label"] = o_name


flg = True
page = 1

org_canvas = "https://iiif.dl.itc.u-tokyo.ac.jp/repo/iiif/25280/canvas/"
org_label = "捃拾帖 一"
org_manifest = "https://iiif.dl.itc.u-tokyo.ac.jp/repo/iiif/25280/manifest"

members = {}

prefix = "https://iiif.dl.itc.u-tokyo.ac.jp/omekac"

list = get_list("data/list.csv")

while flg:
    url = prefix + "/api/items?item_type=18&search=" + org_canvas + "&page=" + str(page)
    print(url)

    page += 1

    response = urllib.request.urlopen(url)
    response_body = response.read().decode("utf-8")
    data = json.loads(response_body.split('\n')[0])

    if len(data) > 0:
        for i in range(len(data)):

            # 各アイテム
            obj = data[i]

            # print(obj)

            element_texts = obj["element_texts"]
            for e in element_texts:

                # print(e)

                if e["element"]["name"] == "On Canvas":
                    uuid = e["text"]

                    tmp_url = prefix + "/api/items?search=" + uuid

                    response = urllib.request.urlopen(tmp_url)
                    response_body = response.read().decode("utf-8")
                    data_t = json.loads(response_body.split('\n')[0])

                    obj_t = data_t[0]

                    # print(obj_t)

                    id = obj_t["id"]
                    collection_id = obj_t["collection"]["id"]

            manifest = prefix + "/oa/items/" + str(id) + "/manifest.json"

            collection_manifest = prefix + "/oa/collections/" + str(collection_id) + "/manifest.json"

            getInfoFromManifest(manifest)

    else:
        flg = False

print(all)

with open('data/template.json') as f:
    df = json.load(f)

df["@id"] = "https://utda.github.io/text/json/" + make_md5(org_manifest) + ".json"
df["selections"] = []

selection = {}
df["selections"].append(selection)

selection["@id"] = df["@id"] + "/range" + str(1)
selection["@type"] = "sc:Range"
selection["label"] = "Manual curation by IIIF Curation Viewer"

selection["members"] = []

manifest = {}
selection["within"] = manifest
manifest["@id"] = org_manifest
manifest["@type"] = "sc:Manifest"
manifest["@label"] = org_label

count = 1

for key in sorted(members):

    member = {}

    selection["members"].append(member)

    tmp = members[key]

    member["@id"] = tmp["selection"]
    member["@type"] = "sc:Canvas"
    member["label"] = tmp["label"]

    meta = list[member["label"]]

    metadata = []
    member["metadata"] = metadata

    for key in meta:
        if meta[key] != "":
            obj = {}
            metadata.append(obj)
            obj["label"] = key
            obj["value"] = meta[key]

    count += 1

with open("../../docs/json/" + make_md5(org_manifest) + ".json", 'w') as outfile:
    json.dump(df, outfile, ensure_ascii=False, indent=4, sort_keys=True, separators=(',', ': '))
