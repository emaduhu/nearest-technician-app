# Store Asset Screenshot Workflow

Use this method when creating modern Play Store and App Store screenshots from real app screenshots. It keeps the store images polished while preserving the actual product UI.

## Goals

- Use real screenshots from the app, not generated UI mockups.
- Keep form text readable by cropping around the important app content.
- Reuse one generator so phone, tablet, Play Store, and App Store assets stay consistent.
- Use modern backgrounds, large headline cards, device-style framing, and short caption pills.

## Source Screenshots

Place raw app screenshots in:

```text
store_assets/source_screenshots/
```

Current source naming pattern:

```text
phone-client.png
phone-technician.png
phone-documents.png
phone-login.png
tablet-client.png
tablet-technician.png
tablet-documents.png
tablet-login.png
iphone_69-client.png
iphone_69-technician.png
iphone_69-documents.png
iphone_69-login.png
ipad_13-client.png
ipad_13-technician.png
ipad_13-documents.png
ipad_13-login.png
```

## Generator

The reusable generator is:

```bash
python3 tools/create_store_assets_from_screenshots.py
```

It generates:

```text
store_assets/play_store/feature-graphic-1024x500.png
store_assets/play_store/screenshots/phone/*.png
store_assets/play_store/screenshots/tablet/*.png
store_assets/app_store/screenshots/iphone-6.9/*.png
store_assets/app_store/screenshots/ipad-13/*.png
```

## Design Method

1. Start with a light blue background.
2. Add large blue geometric shapes behind the content.
3. Add a white headline card with the app icon, title, and short description.
4. Crop the real app screenshot around the important form or screen area.
5. Fill the framed screenshot area with that crop so UI text stays readable.
6. Add a bottom caption pill for the main benefit.
7. Verify the final images visually and by exact dimensions.

## Readability Rule

Do not fit the entire raw screenshot when the app content is surrounded by empty space. Crop around the real form section first, then use cover-style placement inside the store frame.

In this project, `crop_app_content()` stores the source-specific crop windows and `frame_screenshot(..., fit="cover")` enlarges the content inside the frame.

## Verification

Run:

```bash
python3 tools/create_store_assets_from_screenshots.py
python3 -m py_compile tools/create_store_assets_from_screenshots.py
git diff --check
```

Then check the dimensions:

```bash
python3 - <<'PY'
from pathlib import Path
from PIL import Image

expected = {
    "store_assets/play_store/feature-graphic-1024x500.png": (1024, 500),
}

for p in Path("store_assets/play_store/screenshots/phone").glob("*.png"):
    expected[str(p)] = (1080, 1920)
for p in Path("store_assets/play_store/screenshots/tablet").glob("*.png"):
    expected[str(p)] = (1600, 2560)
for p in Path("store_assets/app_store/screenshots/iphone-6.9").glob("*.png"):
    expected[str(p)] = (1290, 2796)
for p in Path("store_assets/app_store/screenshots/ipad-13").glob("*.png"):
    expected[str(p)] = (2048, 2732)

for path, wanted in sorted(expected.items()):
    actual = Image.open(path).size
    print(f"{path}: {actual[0]}x{actual[1]}", "OK" if actual == wanted else f"EXPECTED {wanted}")
PY
```

Visually inspect at least:

```text
store_assets/play_store/feature-graphic-1024x500.png
store_assets/play_store/screenshots/phone/phone-01-client-registration.png
store_assets/play_store/screenshots/phone/phone-03-id-face-verification.png
store_assets/play_store/screenshots/tablet/tablet-03-id-face-verification.png
```

## Reusing In Another Project

Copy these pieces:

```text
tools/create_store_assets_from_screenshots.py
store_assets/source_screenshots/
store_assets/play_store/app-icon-512.png
```

Then update:

- App name in `feature_graphic()`.
- Screenshot source filenames in `main()`.
- Titles and subtitles in `phone_items` and `tablet_items`.
- Crop windows in `crop_app_content()` for the new app screens.
- Output dimensions only if the target store requirements change.
