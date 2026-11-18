# Generuje og.png (1200x630), apple-touch-icon.png (180x180) i favicon.ico z tych samych motywów co strona.
from playwright.sync_api import sync_playwright
from PIL import Image
import pathlib
K = pathlib.Path("/home/claude/strona")
F = (K / "src/zasoby/fonty").as_uri()
og = f"""<!DOCTYPE html><html lang="pl"><head><meta charset="utf-8"><style>
@font-face{{font-family:B;src:url({F}/barlow-600.woff2);font-weight:600}}
@font-face{{font-family:BC;src:url({F}/barlow-condensed-600.woff2);font-weight:600}}
*{{box-sizing:border-box;margin:0}}
body{{width:1200px;height:630px;background:#eef1ee;padding:36px;font-family:B}}
.t{{height:100%;background:#08703f;border-radius:34px;padding:12px;color:#fff}}
.p{{height:100%;border:5px solid #fff;border-radius:24px;padding:44px 56px;display:flex;flex-direction:column;justify-content:space-between}}
.k{{display:flex;gap:40px;align-items:flex-start}}
svg{{width:118px;flex:none;margin-top:10px}}
h1{{font:600 124px/.95 BC;letter-spacing:.5px}}
.w{{border-top:5px solid #fff;padding-top:26px;display:flex;justify-content:space-between;align-items:flex-end;gap:40px;font:600 34px/1.2 B}}
.w b{{font:600 58px/1 BC;white-space:nowrap;text-align:right}}
.w b small{{display:block;font:600 26px/1.2 B;margin-bottom:6px}}
</style></head><body><div class="t"><div class="p">
<div class="k"><svg viewBox="0 0 60 84"><path fill="#fff" d="M30 2 57 36H40v46H20V36H3z"/></svg><h1>Psychotesty<br>w Częstochowie</h1></div>
<div class="w"><span>Badania psychologiczne kierowców i operatorów<br>Monika Dawid, psycholog transportu</span><b><small>badanie kierowcy</small>200 zł</b></div>
</div></div></body></html>"""
ikona = """<!DOCTYPE html><html><head><style>*{margin:0}body{width:180px;height:180px;background:#08703f}</style></head><body>
<svg width="180" height="180" viewBox="0 0 40 40"><rect width="40" height="40" fill="#08703f"/><rect x="4.5" y="4.5" width="31" height="31" rx="5" fill="none" stroke="#fff" stroke-width="2"/><path d="M20 10.5v19M13 12v6a7 7 0 0 0 14 0v-6M15.8 29.5h8.4" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></body></html>"""
with sync_playwright() as p:
    b = p.chromium.launch(channel="chrome", args=["--no-sandbox", "--allow-file-access-from-files"])
    pg = b.new_page(viewport={"width": 1200, "height": 630})
    (K / "_narzedzia/og.html").write_text(og, encoding="utf-8")
    pg.goto((K / "_narzedzia/og.html").as_uri(), wait_until="networkidle")
    pg.evaluate("document.fonts.ready")
    pg.wait_for_timeout(300)
    pg.screenshot(path=str(K / "src/zasoby/glowny/og.png"))
    pg2 = b.new_page(viewport={"width": 180, "height": 180})
    pg2.set_content(ikona)
    pg2.screenshot(path=str(K / "src/zasoby/glowny/apple-touch-icon.png"))
    b.close()
im = Image.open(K / "src/zasoby/glowny/og.png").convert("RGB")
im.save(K / "src/zasoby/glowny/og.png", optimize=True)
ic = Image.open(K / "src/zasoby/glowny/apple-touch-icon.png").convert("RGBA")
ic.save(K / "src/zasoby/glowny/apple-touch-icon.png", optimize=True)
ic.save(K / "src/zasoby/glowny/favicon.ico", sizes=[(16, 16), (32, 32), (48, 48)])
import os
for f in ["og.png", "apple-touch-icon.png", "favicon.ico"]:
    print(f, os.path.getsize(K / "src/zasoby/glowny" / f))
