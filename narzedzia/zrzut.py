import sys, os
from playwright.sync_api import sync_playwright
from PIL import Image
# użycie: zrzut.py <url> <szerokosc> <prefix> [wysokosc_kawalka] [skala]
url, w, prefix = sys.argv[1], int(sys.argv[2]), sys.argv[3]
kawalek = int(sys.argv[4]) if len(sys.argv) > 4 else 1500
skala = float(sys.argv[5]) if len(sys.argv) > 5 else (2 if w < 600 else 1)
with sync_playwright() as p:
    b = p.chromium.launch(channel="chrome", args=["--no-sandbox"])
    ctx = b.new_context(viewport={"width": w, "height": 844 if w < 600 else 900}, device_scale_factor=skala, is_mobile=w < 600, has_touch=w < 600)
    pg = ctx.new_page()
    bledy = []
    pg.on("console", lambda m: bledy.append(m.text) if m.type == "error" else None)
    pg.on("pageerror", lambda e: bledy.append(str(e)))
    pg.on("requestfailed", lambda r: bledy.append("FAILED " + r.url))
    pg.goto(url, wait_until="networkidle")
    # schowaj dolny pasek, żeby nie zasłaniał treści na zrzucie całej strony
    if len(sys.argv) > 6 and sys.argv[6] == "bezpaska":
        pg.add_style_tag(content=".pasek{display:none!important}")
    wys = pg.evaluate("document.documentElement.scrollHeight")
    for y in range(0, wys, 600):
        pg.evaluate(f"window.scrollTo(0,{y})"); pg.wait_for_timeout(60)
    pg.evaluate("window.scrollTo(0,0)"); pg.wait_for_timeout(200)
    pg.screenshot(path=prefix + "-cala.png", full_page=True)
    print("overflowX:", pg.evaluate("document.documentElement.scrollWidth > document.documentElement.clientWidth"), "| błędy:", bledy)
    b.close()
im = Image.open(prefix + "-cala.png")
W, H = im.size
krok = int(kawalek * skala)
n = 0
for y in range(0, H, krok):
    n += 1
    im.crop((0, y, W, min(H, y + krok))).save(f"{prefix}-{n}.png")
print("rozmiar", W, H, "kawałków", n)
