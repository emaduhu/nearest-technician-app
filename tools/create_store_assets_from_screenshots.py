#!/usr/bin/env python3
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont, ImageFilter

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "store_assets/source_screenshots"
PLAY = ROOT / "store_assets/play_store"
APP_STORE = ROOT / "store_assets/app_store"

BG = "#F2F6FB"
SURFACE = "#FFFFFF"
TEXT = "#17202A"
MUTED = "#51616B"
BLUE = "#155AC9"
BLUE_DARK = "#0D3F98"
BLUE_SOFT = "#DCEBFF"
TEAL = "#0F766E"
YELLOW = "#F4C84A"
BORDER = "#D8E2EA"


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


def cover(image, box, anchor_y=0.5):
    w = box[2] - box[0]
    h = box[3] - box[1]
    scale = max(w / image.width, h / image.height)
    resized = image.resize((int(image.width * scale), int(image.height * scale)), Image.Resampling.LANCZOS)
    left = max(0, (resized.width - w) // 2)
    max_top = max(0, resized.height - h)
    top = int(max_top * anchor_y)
    return resized.crop((left, top, left + w, top + h)), (box[0], box[1])


def text_size(draw, value, face):
    box = draw.textbbox((0, 0), value, font=face)
    return box[2] - box[0], box[3] - box[1]


def wrap_lines(draw, value, face, max_width, max_lines=None):
    words = value.split()
    lines = []
    current = ""
    for word in words:
        candidate = word if not current else f"{current} {word}"
        if text_size(draw, candidate, face)[0] <= max_width:
            current = candidate
            continue
        if current:
            lines.append(current)
        current = word
    if current:
        lines.append(current)

    if max_lines and len(lines) > max_lines:
        lines = lines[:max_lines]
        while lines[-1] and text_size(draw, f"{lines[-1]}...", face)[0] > max_width:
            lines[-1] = lines[-1].rsplit(" ", 1)[0] if " " in lines[-1] else lines[-1][:-1]
        lines[-1] = f"{lines[-1]}..."
    return lines


def draw_wrapped_text(draw, xy, value, size, max_width, fill=TEXT, bold=False, line_gap=1.18, max_lines=None):
    face = font(size, bold)
    y = xy[1]
    line_height = int(size * line_gap)
    for line in wrap_lines(draw, value, face, max_width, max_lines):
        draw.text((xy[0], y), line, font=face, fill=fill)
        y += line_height
    return y


def background(draw, size):
    w, h = size
    rounded(draw, (0, 0, w, h), 0, BG)
    draw.ellipse((int(w * 0.44), -int(h * 0.06), int(w * 1.18), int(h * 0.52)), fill=BLUE)
    draw.ellipse((-int(w * 0.23), int(h * 0.62), int(w * 0.34), int(h * 1.12)), fill=BLUE_SOFT)
    draw.ellipse((int(w * 0.06), int(h * 0.71), int(w * 0.23), int(h * 0.81)), fill=YELLOW)
    rounded(draw, (int(w * 0.66), int(h * 0.04), int(w * 1.06), int(h * 0.26)), int(w * 0.06), "#3A7BE8")


def paste_icon(base, box):
    icon_path = PLAY / "app-icon-512.png"
    if not icon_path.exists():
        return
    icon = Image.open(icon_path).convert("RGBA")
    icon.thumbnail((box[2] - box[0], box[3] - box[1]), Image.Resampling.LANCZOS)
    x = box[0] + (box[2] - box[0] - icon.width) // 2
    y = box[1] + (box[3] - box[1] - icon.height) // 2
    base.alpha_composite(icon, (x, y))


def crop_phone_content(image):
    w, h = image.size
    return image.crop((int(w * 0.17), int(h * 0.04), int(w * 0.83), int(h * 0.94)))


def crop_tablet_content(image):
    w, h = image.size
    return image.crop((int(w * 0.28), int(h * 0.04), int(w * 0.72), int(h * 0.94)))


def crop_app_content(source_name, image):
    w, h = image.size
    name = source_name.lower()
    if "login" in name:
        crop = (0.23, 0.34, 0.77, 0.70)
        anchor_y = 0.52
    elif "documents" in name:
        crop = (0.24, 0.02, 0.76, 0.72)
        anchor_y = 0.22
    elif "technician" in name:
        crop = (0.24, 0.02, 0.76, 0.74)
        anchor_y = 0.30
    else:
        crop = (0.24, 0.26, 0.76, 0.78)
        anchor_y = 0.46
    left, top, right, bottom = crop
    return image.crop((int(w * left), int(h * top), int(w * right), int(h * bottom))), anchor_y


def frame_screenshot(base, screenshot, box, radius, shadow=True, fit="contain", anchor_y=0.5):
    if shadow:
        shadow_layer = Image.new("RGBA", base.size, (0, 0, 0, 0))
        shadow_draw = ImageDraw.Draw(shadow_layer)
        rounded(shadow_draw, box, radius, (0, 0, 0, 55))
        shadow_layer = shadow_layer.filter(ImageFilter.GaussianBlur(24))
        base.alpha_composite(shadow_layer, (0, 18))

    draw = ImageDraw.Draw(base)
    rounded(draw, box, radius, SURFACE, BORDER, 2)
    inner = (box[0] + 18, box[1] + 18, box[2] - 18, box[3] - 18)
    shot, pos = cover(screenshot, inner, anchor_y) if fit == "cover" else contain(screenshot, inner)
    mask = Image.new("L", shot.size, 0)
    mask_draw = ImageDraw.Draw(mask)
    mask_draw.rounded_rectangle((0, 0, shot.width, shot.height), radius=max(radius - 18, 12), fill=255)
    base.paste(shot.convert("RGBA"), pos, mask)


def make_store_screenshot(source_name, out_path, size, title, subtitle, tablet=False):
    out_path.parent.mkdir(parents=True, exist_ok=True)
    base = Image.new("RGBA", size, BG)
    draw = ImageDraw.Draw(base)
    background(draw, size)

    card = (
        int(size[0] * 0.055),
        int(size[1] * 0.048),
        int(size[0] * (0.74 if tablet else 0.77)),
        int(size[1] * 0.225),
    )
    rounded(draw, card, int(size[0] * 0.032), SURFACE, BORDER, 2)
    icon_size = int(size[0] * (0.078 if tablet else 0.086))
    icon_box = (card[0] + int(size[0] * 0.035), card[1] + int(size[1] * 0.032), card[0] + int(size[0] * 0.035) + icon_size, card[1] + int(size[1] * 0.032) + icon_size)
    paste_icon(base, icon_box)

    title_x = icon_box[2] + int(size[0] * 0.032)
    max_text_width = card[2] - title_x - int(size[0] * 0.04)
    title_size = int(size[0] * (0.038 if tablet else 0.048))
    subtitle_size = int(size[0] * (0.019 if tablet else 0.024))
    y = draw_wrapped_text(draw, (title_x, card[1] + int(size[1] * 0.04)), title, title_size, max_text_width, TEXT, True, 1.08, 2)
    draw_wrapped_text(draw, (title_x, y + int(size[1] * 0.012)), subtitle, subtitle_size, max_text_width, MUTED, False, 1.18, 2)

    raw = Image.open(SRC / source_name).convert("RGBA")
    crop, anchor_y = crop_app_content(source_name, raw)
    margin_x = int(size[0] * (0.13 if tablet else 0.105))
    top = int(size[1] * 0.29)
    bottom = int(size[1] * 0.875)
    frame_screenshot(base, crop, (margin_x, top, size[0] - margin_x, bottom), int(size[0] * 0.035), fit="cover", anchor_y=anchor_y)

    pill = (
        int(size[0] * 0.105),
        int(size[1] * 0.895),
        int(size[0] * 0.895),
        int(size[1] * 0.972),
    )
    rounded(draw, pill, int(size[1] * 0.025), SURFACE, None)
    dot = int(size[1] * 0.014)
    cx = pill[0] + int(size[0] * 0.055)
    cy = (pill[1] + pill[3]) // 2
    draw.ellipse((cx - dot, cy - dot, cx + dot, cy + dot), fill=BLUE)
    caption = subtitle.replace("automatic detection support", "auto detection")
    caption_size = int(size[0] * (0.018 if tablet else 0.024))
    draw_wrapped_text(
        draw,
        (cx + int(size[0] * 0.04), pill[1] + int(size[1] * 0.017)),
        caption,
        caption_size,
        pill[2] - cx - int(size[0] * 0.09),
        BLUE_DARK,
        True,
        1.1,
        2,
    )
    base.convert("RGB").save(out_path, quality=95)


def feature_graphic():
    size = (1024, 500)
    base = Image.new("RGBA", size, BG)
    draw = ImageDraw.Draw(base)
    background(draw, size)
    rounded(draw, (44, 54, 630, 438), 34, SURFACE, BORDER, 2)

    icon = Image.open(PLAY / "app-icon-512.png").convert("RGBA")
    icon.thumbnail((104, 104), Image.Resampling.LANCZOS)
    base.alpha_composite(icon, (84, 102))
    text(draw, (214, 104), "Nearest", 44, TEXT, True)
    text(draw, (214, 154), "Technician", 44, TEXT, True)
    draw_wrapped_text(draw, (88, 240), "Find nearby help and verify technicians.", 26, 470, MUTED, False, 1.2, 2)

    left, left_anchor = crop_app_content("phone-client.png", Image.open(SRC / "phone-client.png").convert("RGBA"))
    right, right_anchor = crop_app_content("phone-documents.png", Image.open(SRC / "phone-documents.png").convert("RGBA"))
    frame_screenshot(base, left, (650, 70, 792, 450), 24, shadow=True, fit="cover", anchor_y=left_anchor)
    frame_screenshot(base, right, (820, 70, 962, 450), 24, shadow=True, fit="cover", anchor_y=right_anchor)

    for i, label in enumerate(["Nearby search", "Verified profiles", "Secure requests"]):
        x = 88 + i * 166
        rounded(draw, (x, 342, x + 142, 386), 18, BLUE_SOFT, None)
        text(draw, (x + 71, 356), label, 13, BLUE_DARK, True, "ma")

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
