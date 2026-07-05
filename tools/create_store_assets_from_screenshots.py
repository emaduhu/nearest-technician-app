#!/usr/bin/env python3
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont, ImageFilter

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "store_assets/source_screenshots"
PLAY = ROOT / "store_assets/play_store"
APP_STORE = ROOT / "store_assets/app_store"

BG = "#F5F7F8"
SURFACE = "#FFFFFF"
TEXT = "#17202A"
MUTED = "#51616B"
TEAL = "#0F766E"
TEAL_DARK = "#0B5F59"
TEAL_SOFT = "#D9F1ED"
BORDER = "#DCE4E8"


def font(size, bold=False):
    candidates = [
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf" if bold else "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf" if bold else "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf",
    ]
    for item in candidates:
        if Path(item).exists():
            return ImageFont.truetype(item, size)
    return ImageFont.load_default()


def text(draw, xy, value, size, fill=TEXT, bold=False, anchor=None):
    draw.text(xy, value, font=font(size, bold), fill=fill, anchor=anchor)


def rounded(draw, box, radius, fill, outline=None, width=1):
    draw.rounded_rectangle(box, radius=radius, fill=fill, outline=outline, width=width)


def contain(image, box):
    w = box[2] - box[0]
    h = box[3] - box[1]
    copy = image.copy()
    copy.thumbnail((w, h), Image.Resampling.LANCZOS)
    x = box[0] + (w - copy.width) // 2
    y = box[1] + (h - copy.height) // 2
    return copy, (x, y)


def crop_phone_content(image):
    w, h = image.size
    return image.crop((int(w * 0.17), int(h * 0.04), int(w * 0.83), int(h * 0.94)))


def crop_tablet_content(image):
    w, h = image.size
    return image.crop((int(w * 0.28), int(h * 0.04), int(w * 0.72), int(h * 0.94)))


def frame_screenshot(base, screenshot, box, radius, shadow=True):
    if shadow:
        shadow_layer = Image.new("RGBA", base.size, (0, 0, 0, 0))
        shadow_draw = ImageDraw.Draw(shadow_layer)
        rounded(shadow_draw, box, radius, (0, 0, 0, 55))
        shadow_layer = shadow_layer.filter(ImageFilter.GaussianBlur(24))
        base.alpha_composite(shadow_layer, (0, 18))

    draw = ImageDraw.Draw(base)
    rounded(draw, box, radius, SURFACE, BORDER, 2)
    inner = (box[0] + 18, box[1] + 18, box[2] - 18, box[3] - 18)
    shot, pos = contain(screenshot, inner)
    mask = Image.new("L", shot.size, 0)
    mask_draw = ImageDraw.Draw(mask)
    mask_draw.rounded_rectangle((0, 0, shot.width, shot.height), radius=max(radius - 18, 12), fill=255)
    base.paste(shot.convert("RGBA"), pos, mask)


def make_store_screenshot(source_name, out_path, size, title, subtitle, tablet=False):
    out_path.parent.mkdir(parents=True, exist_ok=True)
    base = Image.new("RGBA", size, BG)
    draw = ImageDraw.Draw(base)

    accent_h = int(size[1] * 0.18)
    rounded(draw, (-40, -40, size[0] + 40, accent_h), 0, TEAL_SOFT)
    rounded(draw, (int(size[0] * 0.06), int(size[1] * 0.065), int(size[0] * 0.12), int(size[1] * 0.095)), 18, TEAL)
    text(draw, (int(size[0] * 0.15), int(size[1] * 0.058)), title, int(size[0] * (0.044 if tablet else 0.052)), TEXT, True)
    text(draw, (int(size[0] * 0.15), int(size[1] * 0.105)), subtitle, int(size[0] * (0.020 if tablet else 0.026)), MUTED)

    raw = Image.open(SRC / source_name).convert("RGBA")
    crop = crop_tablet_content(raw) if tablet else crop_phone_content(raw)
    margin_x = int(size[0] * (0.13 if tablet else 0.12))
    top = int(size[1] * 0.205)
    bottom = int(size[1] * 0.965)
    frame_screenshot(base, crop, (margin_x, top, size[0] - margin_x, bottom), int(size[0] * 0.035))
    base.convert("RGB").save(out_path, quality=95)


def feature_graphic():
    size = (1024, 500)
    base = Image.new("RGBA", size, BG)
    draw = ImageDraw.Draw(base)
    rounded(draw, (0, 0, size[0], size[1]), 0, BG)
    rounded(draw, (38, 42, 986, 458), 28, SURFACE, BORDER, 2)

    icon = Image.open(PLAY / "app-icon-512.png").convert("RGBA")
    icon.thumbnail((116, 116), Image.Resampling.LANCZOS)
    base.alpha_composite(icon, (78, 92))
    text(draw, (220, 104), "Nearest Technician", 42, TEXT, True)
    text(draw, (224, 166), "Find nearby help and verify technicians.", 20, MUTED)

    left = crop_phone_content(Image.open(SRC / "phone-client.png").convert("RGBA"))
    right = crop_phone_content(Image.open(SRC / "phone-documents.png").convert("RGBA"))
    frame_screenshot(base, left, (690, 80, 810, 424), 22, shadow=True)
    frame_screenshot(base, right, (836, 80, 956, 424), 22, shadow=True)

    for i, label in enumerate(["Nearby search", "Verified profiles", "Secure requests"]):
        x = 224 + i * 122
        rounded(draw, (x, 292, x + 108, 334), 16, TEAL_SOFT, None)
        text(draw, (x + 54, 305), label, 12, TEAL_DARK, True, "ma")

    base.convert("RGB").save(PLAY / "feature-graphic-1024x500.png", quality=95)


def main():
    phone_items = [
        ("phone-client.png", "phone-01-client-registration.png", "Client registration", "Verify phone, email, and request service faster."),
        ("phone-technician.png", "phone-02-technician-registration.png", "Technician onboarding", "Collect skills, contact details, and service readiness."),
        ("phone-documents.png", "phone-03-id-face-verification.png", "ID and face checks", "Camera-first verification with automatic detection support."),
        ("phone-login.png", "phone-04-secure-login.png", "Secure sign in", "Return quickly with verified account credentials."),
    ]
    tablet_items = [
        ("tablet-client.png", "tablet-01-client-registration.png", "Client registration", "A spacious form for verified service requests."),
        ("tablet-technician.png", "tablet-02-technician-registration.png", "Technician onboarding", "Technicians add skills, phone, and service details."),
        ("tablet-documents.png", "tablet-03-id-face-verification.png", "Document verification", "NIDA and face capture controls for review."),
        ("tablet-login.png", "tablet-04-secure-login.png", "Secure sign in", "Fast access for clients and technicians."),
    ]
    iphone_items = [(name.replace("phone", "iphone_69"), out, title, subtitle) for name, out, title, subtitle in phone_items]
    ipad_items = [(name.replace("tablet", "ipad_13"), out, title, subtitle) for name, out, title, subtitle in tablet_items]

    for src, out, title, subtitle in phone_items:
        make_store_screenshot(src, PLAY / "screenshots/phone" / out, (1080, 1920), title, subtitle)
    for src, out, title, subtitle in tablet_items:
        make_store_screenshot(src, PLAY / "screenshots/tablet" / out, (1600, 2560), title, subtitle, tablet=True)
    for src, out, title, subtitle in iphone_items:
        make_store_screenshot(src, APP_STORE / "screenshots/iphone-6.9" / out, (1290, 2796), title, subtitle)
    for src, out, title, subtitle in ipad_items:
        make_store_screenshot(src, APP_STORE / "screenshots/ipad-13" / out, (2048, 2732), title, subtitle, tablet=True)

    feature_graphic()


if __name__ == "__main__":
    main()
