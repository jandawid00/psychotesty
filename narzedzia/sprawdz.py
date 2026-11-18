import os, re, json, sys
from html.parser import HTMLParser
PUB = "/home/claude/strona/public"
class P(HTMLParser):
    def __init__(s):
        super().__init__(convert_charrefs=True)
        s.h1=0; s.ids=[]; s.hrefs=[]; s.srcs=[]; s.title=""; s.desc=None; s.canon=None; s.ld=[]; s._t=None; s.imgs_noalt=0; s.lang=None; s._buf=""
        s.stack=[]
    def handle_starttag(s, t, a):
        a=dict(a)
        if t=="html": s.lang=a.get("lang")
        if t=="h1": s.h1+=1
        if "id" in a: s.ids.append(a["id"])
        if t=="a" and "href" in a: s.hrefs.append(a["href"])
        if t in("img","script","source") :
            for k in("src",):
                if a.get(k): s.srcs.append(a[k])
            if a.get("srcset"):
                s.srcs += [x.strip().split(" ")[0] for x in a["srcset"].split(",")]
        if t=="link" and a.get("href") and a.get("rel") in ("stylesheet","icon","apple-touch-icon","preload"): s.srcs.append(a["href"])
        if t=="link" and a.get("rel")=="canonical": s.canon=a.get("href")
        if t=="meta" and a.get("name")=="description": s.desc=a.get("content")
        if t=="img" and "alt" not in a: s.imgs_noalt+=1
        if t=="title": s._t="title"; s._buf=""
        if t=="script" and a.get("type")=="application/ld+json": s._t="ld"; s._buf=""
    def handle_data(s, d):
        if s._t: s._buf+=d
    def handle_endtag(s, t):
        if t=="title" and s._t=="title": s.title=s._buf.strip(); s._t=None
        if t=="script" and s._t=="ld": s.ld.append(s._buf); s._t=None
bledy=[]; wiersze=[]
pliki=[]
for r,_,fs in os.walk(PUB):
    for f in fs:
        if f.endswith(".html"): pliki.append(os.path.join(r,f))
def istnieje(u):
    u=u.split("#")[0].split("?")[0]
    if u=="" : return True
    p=PUB+u
    if u.endswith("/"): p+= "index.html"
    return os.path.isfile(p)
for f in sorted(pliki):
    h=open(f,encoding="utf-8").read()
    p=P(); p.feed(h)
    rel=f[len(PUB):]
    if p.h1!=1: bledy.append(f"{rel}: h1={p.h1}")
    if p.lang!="pl": bledy.append(f"{rel}: lang")
    dup={i for i in p.ids if p.ids.count(i)>1}
    if dup: bledy.append(f"{rel}: zdublowane id {dup}")
    if not p.desc: bledy.append(f"{rel}: brak opisu")
    if "[[" in h or "]]" in h: bledy.append(f"{rel}: surowe nawiasy pola")
    if re.search(r"\{[a-z_]+\}", re.sub(r"<script.*?</script>|<style.*?</style>","",h,flags=re.S)): bledy.append(f"{rel}: niepodstawiony token")
    for u in p.hrefs+p.srcs:
        if u.startswith(("http","tel:","mailto:","data:")): continue
        if u.startswith("#"):
            if u[1:] not in p.ids: bledy.append(f"{rel}: kotwica {u}")
            continue
        if not u.startswith("/"): bledy.append(f"{rel}: adres względny {u}"); continue
        if not istnieje(u): bledy.append(f"{rel}: martwy link {u}")
        if "#" in u:
            cel=u.split("#")[0]; kot=u.split("#")[1]
            pp=P(); pp.feed(open(PUB+cel+("index.html" if cel.endswith("/") else ""),encoding="utf-8").read())
            if kot not in pp.ids: bledy.append(f"{rel}: kotwica {u}")
    typy=[]
    for l in p.ld:
        try:
            j=json.loads(l)
            for n in j.get("@graph",[]):
                t=n.get("@type"); typy.append(t if isinstance(t,str) else "+".join(t))
            if "[[" in l or "000 000" in l: bledy.append(f"{rel}: pole do uzupełnienia w danych strukturalnych")
        except Exception as ex: bledy.append(f"{rel}: JSON-LD {ex}")
    if p.imgs_noalt: bledy.append(f"{rel}: img bez alt {p.imgs_noalt}")
    wiersze.append((rel, len(p.title), len(p.desc or ""), round(len(h)/1024,1), ",".join(typy)))
print(f"{'strona':62} tyt opis   KB  dane strukturalne")
for w in wiersze: print(f"{w[0]:62} {w[1]:3} {w[2]:4} {w[3]:5} {w[4]}")
sm=open(PUB+"/sitemap.xml").read() if os.path.isfile(PUB+"/sitemap.xml") else ""
for u in re.findall(r"<loc>(.*?)</loc>", sm):
    sc=re.sub(r"^https://[^/]+","",u)
    if not istnieje(sc): bledy.append("sitemap: "+u)
print("adresów w sitemap:", len(re.findall("<loc>",sm)))
print("BŁĘDY:" if bledy else "Brak błędów.")
for b in bledy: print(" -", b)
