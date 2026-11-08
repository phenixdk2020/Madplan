#!/usr/bin/env python3
"""No-cost temporary flyer OCR worker for the private Madplan WordPress integration.

WordPress owns source selection, permission gate, queue progress, parsing and retention.
Never print image data or OCR text; never save long-lived images or API credentials.
"""
from __future__ import annotations

import base64
import io
import json
import os
import re
import subprocess
import sys
import tempfile
import urllib.error
import urllib.request
from pathlib import Path

from PIL import Image, UnidentifiedImageError

HOST = "https://hangar18.dk"
BASE = os.getenv("MADPLAN_SITE_URL", HOST).rstrip("/")
if BASE != HOST:
    raise SystemExit("Refusing unknown WordPress host")
USER = os.getenv("MADPLAN_OCR_USERNAME", "")
PASSWORD = os.getenv("MADPLAN_OCR_APP_PASSWORD", "")
if not USER or not PASSWORD:
    raise SystemExit("Missing GitHub Secrets for Madplan OCR")
AUTH = "Basic " + base64.b64encode(f"{USER}:{PASSWORD}".encode()).decode("ascii")
API = BASE + "/wp-json/hangar18/v1/shopping/daily-ocr/"
ALLOWED = re.compile(
    r"^https://img\.prej\.app/flyers/[a-z0-9-]+/[A-Za-z0-9_-]+/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)$"
)
MAX_IMAGE = 6 * 1024 * 1024
MAX_TEXT = 200000


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, msg, headers, newurl):
        raise RuntimeError("Redirect blocked")


OPENER = urllib.request.build_opener(NoRedirect())


def request_json(url: str, payload: dict | None = None) -> dict:
    data = json.dumps(payload, ensure_ascii=False).encode("utf-8") if payload is not None else None
    req = urllib.request.Request(
        url,
        data=data,
        headers={
            "Authorization": AUTH,
            "Accept": "application/json",
            **({"Content-Type": "application/json; charset=utf-8"} if data is not None else {}),
        },
        method="POST" if data is not None else "GET",
    )
    try:
        with OPENER.open(req, timeout=45) as response:
            raw = response.read(2 * 1024 * 1024 + 1)
            if len(raw) > 2 * 1024 * 1024:
                raise RuntimeError("Oversize WordPress response")
            parsed = json.loads(raw)
            if not isinstance(parsed, dict):
                raise RuntimeError("Unexpected WordPress response")
            return parsed
    except urllib.error.HTTPError as exc:
        # Do not expose credentials or server content in Action logs.
        raise RuntimeError(f"WordPress API returned HTTP {exc.code}") from None


def ocr_page(url: str) -> str:
    if not isinstance(url, str) or not ALLOWED.fullmatch(url):
        raise ValueError("Source host or URL is not approved")
    req = urllib.request.Request(url, headers={"Accept": "image/jpeg,image/png,image/webp"})
    with OPENER.open(req, timeout=25) as response:
        data = response.read(MAX_IMAGE + 1)
    if not data or len(data) > MAX_IMAGE:
        raise ValueError("Invalid image size")
    try:
        with Image.open(io.BytesIO(data)) as img:
            if img.format not in ("JPEG", "PNG", "WEBP"):
                raise ValueError("Unsupported image format")
            if img.width < 1 or img.height < 1 or img.width * img.height > 36_000_000:
                raise ValueError("Image dimensions out of bounds")
            img.verify()
    except (UnidentifiedImageError, OSError) as exc:
        raise ValueError("Image decoding failed") from exc
    # The file only exists during one OCR call and is always deleted.
    with tempfile.TemporaryDirectory(prefix="madplan_ocr_") as tempdir:
        path = Path(tempdir) / "page.img"
        path.write_bytes(data)
        result = subprocess.run(
            ["tesseract", str(path), "stdout", "-l", "dan+eng", "--psm", "11"],
            stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, timeout=60, check=False,
        )
        if result.returncode:
            raise RuntimeError("Tesseract could not read this page")
        if len(result.stdout) > MAX_TEXT:
            raise ValueError("OCR text exceeds maximum")
        return result.stdout.decode("utf-8", errors="replace")


def main() -> int:
    queue = request_json(API + "external-queue")
    day = queue.get("day")
    next_index = queue.get("next")
    total = queue.get("total")
    pages = queue.get("pages")
    if not isinstance(day, str) or not isinstance(next_index, int) or not isinstance(total, int) or not isinstance(pages, list):
        raise RuntimeError("Invalid queue schema")
    if len(pages) > 192 or total > 192 or next_index < 0:
        raise RuntimeError("Queue bounds exceeded")
    print(f"OCR queue: {len(pages)} remaining out of {total} total pages")
    failures = 0
    for index, page in enumerate(pages, next_index):
        if not isinstance(page, dict):
            raise RuntimeError("Invalid page in queue")
        text = ""
        error = ""
        try:
            text = ocr_page(page.get("url", ""))
        except (ValueError, RuntimeError, urllib.error.URLError, subprocess.TimeoutExpired, OSError) as exc:
            error = type(exc).__name__  # Do not forward remote URLs or untrusted details.
            failures += 1
        answer = request_json(API + "external-result", {
            "day": day, "index": index, "text": text, "error": error
        })
        if not answer.get("accepted"):
            raise RuntimeError("WordPress did not accept OCR page")
        if (index - next_index + 1) % 10 == 0 or index == total - 1:
            print(f"OCR progress: {index + 1}/{total}, failed pages: {failures}")
    print(f"Finished runner: {len(pages)} page(s), {failures} page failure(s)")
    return 0 if not failures else 1


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as exc:
        print(f"OCR worker stopped: {type(exc).__name__}: {exc}", file=sys.stderr)
        sys.exit(1)
