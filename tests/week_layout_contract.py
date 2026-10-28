#!/usr/bin/env python3
"""Regression contract: mobile portrait must show all seven days."""
from pathlib import Path
import re

js=Path("assets/portal.js").read_text(encoding="utf-8")
css=Path("assets/portal.css").read_text(encoding="utf-8")
main=Path("hangar18-madplan.php").read_text(encoding="utf-8")
assert " * Version: " in main
assert "limit=7;" in js, "Must render exactly seven days"
assert "for(var i=0;i<limit;i++)" in js
assert "i>=focus" not in js, "No mobile focus window"
assert "mp-portrait-hide" not in js, "No hidden portrait days"
assert "mp-landscape-hide" not in js, "No hidden landscape days"
assert "mp-day.mp-portrait-hide{display:none}" not in css
assert "async function load(){s.week=await api(" in js, "Only one week fetched"
assert "data-day=" in js
assert "@media(max-width:959px) and (orientation:portrait)" in css
print("Week 7-day mobile/desktop contract PASS")
