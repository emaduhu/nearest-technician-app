#!/usr/bin/env python3
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
TEAL = "#0F766E"
TEAL_DARK = "#0B5F59"
TEAL_LIGHT = "#14B8A6"
BLUE = "#2563EB"
BG = "#F5F7F8"
TEXT = "#17202A"
MUTED = "#697781"
WHITE = "#FFFFFF"


def font(size, bold=False):
    candidates = [
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf" if bold else "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf" if bold else "/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf",
    ]
    for item in candidates:
        if Path(item).exists():
            return ImageFont.truetype(item, size)
    return ImageFont.load_default()


def canvas(size, color=(0, 0, 0, 0)):
    return Image.new("RGBA", size, color)


def rounded(draw, box, radius, fill, outline=None, width=1):
    draw.rounded_rectangle(box, radius=radius, fill=fill, outline=outline, width=width)


def paste_center(base, overlay, center):
    base.alpha_composite(overlay, (int(center[0] - overlay.width / 2), int(center[1] - overlay.height / 2)))


def logo_mark(size, bg=True, padding=0):
    scale = 4
    large = canvas((size * scale, size * scale))
    draw = ImageDraw.Draw(large)
    s = size * scale
    p = padding * scale
    if bg:
        rounded(draw, (p, p, s - p, s - p), int(s * 0.215), TEAL)
        draw.ellipse((int(s * 0.255), int(s * 0.16), int(s * 0.745), int(s * 0.65)), fill=TEAL_LIGHT)

    # Pin
    cx, top = s * 0.5, s * 0.185
    r = s * 0.185
    draw.ellipse((cx - r, top, cx + r, top + 2 * r), fill=WHITE)
    draw.polygon([(cx - r * 0.62, top + r * 1.55), (cx + r * 0.62, top + r * 1.55), (cx, s * 0.76)], fill=WHITE)
    draw.ellipse((cx - r * 0.34, top + r * 0.66, cx + r * 0.34, top + r * 1.34), fill=TEAL)

    # Tool diagonal handle
    draw.line((s * 0.37, s * 0.61, s * 0.66, s * 0.32), fill=TEAL_DARK, width=int(s * 0.06))
    draw.line((s * 0.53, s * 0.66, s * 0.71, s * 0.84), fill=TEAL_DARK, width=int(s * 0.058))
    draw.ellipse((s * 0.66, s * 0.79, s * 0.77, s * 0.90), fill=TEAL_DARK)
    draw.ellipse((s * 0.69, s * 0.82, s * 0.74, s * 0.87), fill=WHITE)
    draw.polygon([(s * 0.35, s * 0.56), (s * 0.27, s * 0.50), (s * 0.32, s * 0.45), (s * 0.39, s * 0.52)], fill=TEAL_DARK)

    return large.resize((size, size), Image.Resampling.LANCZOS)


def save_icon(path, size, bg=True, padding=0):
    path.parent.mkdir(parents=True, exist_ok=True)
    image = logo_mark(size, bg=bg, padding=padding)
    if bg:
        image = image.convert("RGB")
    image.save(path)


def save_android_icons():
    densities = {
        "mipmap-mdpi": 48,
        "mipmap-hdpi": 72,
        "mipmap-xhdpi": 96,
        "mipmap-xxhdpi": 144,
        "mipmap-xxxhdpi": 192,
    }
    res = ROOT / "flutter_app/android/app/src/main/res"
    for folder, size in densities.items():
        save_icon(res / folder / "ic_launcher.png", size)
        save_icon(res / folder / "ic_launcher_round.png", size)
        save_icon(res / folder / "ic_launcher_foreground.png", int(size * 2.25), bg=False)


def save_ios_icons():
    icon_dir = ROOT / "flutter_app/ios/Runner/Assets.xcassets/AppIcon.appiconset"
    mapping = {
        "Icon-App-20x20@1x.png": 20,
        "Icon-App-20x20@2x.png": 40,
        "Icon-App-20x20@3x.png": 60,
        "Icon-App-29x29@1x.png": 29,
        "Icon-App-29x29@2x.png": 58,
        "Icon-App-29x29@3x.png": 87,
        "Icon-App-40x40@1x.png": 40,
        "Icon-App-40x40@2x.png": 80,
        "Icon-App-40x40@3x.png": 120,
        "Icon-App-60x60@2x.png": 120,
        "Icon-App-60x60@3x.png": 180,
        "Icon-App-76x76@1x.png": 76,
        "Icon-App-76x76@2x.png": 152,
        "Icon-App-83.5x83.5@2x.png": 167,
        "Icon-App-1024x1024@1x.png": 1024,
    }
    for filename, size in mapping.items():
        save_icon(icon_dir / filename, size)


def save_web_icons():
    web = ROOT / "flutter_app/web"
    save_icon(web / "favicon.png", 64)
    save_icon(web / "icons/Icon-192.png", 192)
    save_icon(web / "icons/Icon-512.png", 512)
    save_icon(web / "icons/Icon-maskable-192.png", 192, padding=18)
    save_icon(web / "icons/Icon-maskable-512.png", 512, padding=48)


def save_launch_images():
    launch_dir = ROOT / "flutter_app/ios/Runner/Assets.xcassets/LaunchImage.imageset"
    for filename, size, mark in [
        ("LaunchImage.png", (320, 480), 96),
        ("LaunchImage@2x.png", (640, 960), 192),
        ("LaunchImage@3x.png", (960, 1440), 288),
    ]:
        img = canvas(size, BG)
        paste_center(img, logo_mark(mark), (size[0] / 2, size[1] / 2))
        img.convert("RGB").save(launch_dir / filename)


def save_store_icon():
    out = ROOT / "store_assets/play_store"
    save_icon(out / "app-icon-512.png", 512)
    save_icon(ROOT / "store_assets/app_store/app-icon-1024.png", 1024)


def text(draw, xy, value, size, fill=TEXT, bold=False, anchor=None):
    draw.text(xy, value, font=font(size, bold), fill=fill, anchor=anchor)


def feature_graphic():
    size = (1024, 500)
    img = canvas(size, BG)
    draw = ImageDraw.Draw(img)
    rounded(draw, (48, 48, 976, 452), 36, WHITE, "#DCE4E8")
    paste_center(img, logo_mark(160), (170, 250))
    text(draw, (300, 154), "Nearest Technician", 58, TEXT, True)
    text(draw, (304, 224), "Find skilled help nearby, request service, and track every job.", 28, MUTED)
    for i, label in enumerate(["Skill search", "Live location", "Request history"]):
        x = 304 + i * 205
        rounded(draw, (x, 310, x + 178, 364), 18, "#EEF5F3")
        text(draw, (x + 89, 326), label, 18, TEAL_DARK, True, "ma")
    img.convert("RGB").save(ROOT / "store_assets/play_store/feature-graphic-1024x500.png")


def phone_frame(title, subtitle, kind, filename):
    w, h = 1080, 1920
    img = canvas((w, h), BG)
    draw = ImageDraw.Draw(img)
    text(draw, (80, 112), "Nearest Technician", 54, TEXT, True)
    text(draw, (80, 184), title, 76, TEXT, True)
    text(draw, (80, 290), subtitle, 32, MUTED)
    rounded(draw, (125, 430, 955, 1770), 70, "#101827")
    rounded(draw, (155, 470, 925, 1730), 42, "#F8FAFC")
    rounded(draw, (205, 525, 875, 650), 24, WHITE, "#DCE4E8")
    paste_center(img, logo_mark(76), (250, 588))
    text(draw, (315, 548), "Find Technician" if kind == "client" else "Technician Dashboard", 30, TEXT, True)
    text(draw, (315, 594), "Live, nearby, verified", 22, MUTED)
    if kind == "client":
        rounded(draw, (205, 700, 875, 795), 20, WHITE, "#DCE4E8")
        text(draw, (245, 733), "Search: Plumbing", 28, TEXT, True)
        for i, name in enumerate(["Joseph Kimaro", "Maria Hassan", "Asha Mwinyi"]):
            y = 845 + i * 190
            rounded(draw, (205, y, 875, y + 145), 22, WHITE, "#DCE4E8")
            text(draw, (245, y + 34), name, 30, TEXT, True)
            text(draw, (245, y + 80), f"{2 + i * 3}.4 km away  |  4.{8 - i} stars", 22, MUTED)
            rounded(draw, (690, y + 43, 835, y + 94), 18, TEAL)
            text(draw, (762, y + 57), "Request", 20, WHITE, True, "ma")
    elif kind == "tech":
        rounded(draw, (205, 700, 875, 825), 22, "#EEF5F3", "#DCE4E8")
        text(draw, (245, 735), "Available for requests", 30, TEAL_DARK, True)
        text(draw, (245, 780), "Live location synced", 22, MUTED)
        for i, status in enumerate(["Pending request", "Accepted job", "Completed service"]):
            y = 890 + i * 180
            rounded(draw, (205, y, 875, y + 130), 22, WHITE, "#DCE4E8")
            text(draw, (245, y + 34), status, 30, TEXT, True)
            text(draw, (245, y + 80), "Client service log updated", 22, MUTED)
    else:
        rounded(draw, (205, 700, 875, 840), 22, WHITE, "#DCE4E8")
        text(draw, (245, 735), "Operations portal", 34, TEXT, True)
        text(draw, (245, 785), "Requests, technicians, skills", 24, MUTED)
        for i, value in enumerate(["128 Clients", "42 Technicians", "9 Pending"]):
            y = 920 + i * 160
            rounded(draw, (205, y, 875, y + 105), 22, "#EEF5F3" if i == 1 else WHITE, "#DCE4E8")
            text(draw, (245, y + 35), value, 32, TEAL_DARK if i == 1 else TEXT, True)
    (ROOT / "store_assets/play_store/screenshots").mkdir(parents=True, exist_ok=True)
    img.convert("RGB").save(ROOT / "store_assets/play_store/screenshots" / filename)


def save_store_graphics():
    save_store_icon()
    feature_graphic()
    phone_frame("Find skilled help nearby", "Search technicians by skill, rating, distance, and availability.", "client", "phone-01-client-search.png")
    phone_frame("Respond faster in the field", "Technicians receive requests, accept jobs, and update clients.", "tech", "phone-02-technician-jobs.png")
    phone_frame("Manage service operations", "Track requests, availability, and skill demand from the portal.", "portal", "phone-03-operations.png")


def main():
    save_android_icons()
    save_ios_icons()
    save_web_icons()
    save_launch_images()
    save_store_graphics()
    save_icon(ROOT / "assets/brand/logo-mark-1024.png", 1024)
    save_icon(ROOT / "assets/brand/logo-mark-transparent-1024.png", 1024, bg=False)


if __name__ == "__main__":
    main()
