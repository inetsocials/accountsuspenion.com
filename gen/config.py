"""Site facts. The only source of truth for business details.

Rules: anything unknown stays empty and is never rendered. Do not add
claims, statistics, testimonials, prices or people that the business
cannot evidence.
"""
from __future__ import annotations

from datetime import date

BASE_URL = "https://accountsuspension.com"
BRAND = "AccountSuspension.com"
BRAND_SHORT = "Account Suspension"
LEGAL_NAME = ""            # e.g. "Account Suspension LLC" once confirmed
REGISTRATION_LINE = ""     # e.g. "Registered in Delaware, file number ..." once confirmed
TAGLINE = "Diagnose the notice. Evidence the fix. Appeal once, properly."
LANG = "en-US"
CHANNEL = "Secure Case Intake"

# Contact facts. Rendered only when non-empty.
EMAIL_USER = ""            # local part only, e.g. "cases"
EMAIL_DOMAIN = "accountsuspension.com"
PHONE = ""                 # E.164, e.g. "+18005550100"; rendered only when set
ADDRESS: dict[str, str] = {}  # {"street": "", "city": "", "region": "", "postcode": "", "country": "US"}

SOCIAL: dict[str, str] = {"linkedin": "", "x": "", "facebook": ""}

# Service levels published on the current site. Owner to confirm before launch.
REVIEW_WINDOW = "within 24 to 48 hours of intake"
REPLY_WINDOW = "within one business day"
PRIORITY_WINDOW = "the same business day"

TODAY = date.today()
TODAY_ISO = TODAY.isoformat()
TODAY_US = TODAY.strftime("%m/%d/%Y")

# Published only with written client consent and evidence on file.
TEAM: list[dict] = []
CASE_STUDIES: list[dict] = []
TESTIMONIALS: list[dict] = []
# Add ONLY genuine client reviews with written consent on file. The FTC rule on
# fake reviews and testimonials (16 CFR Part 465) prohibits invented or
# misattributed reviews. The build fails if a required field is missing.
# Example entry (do not uncomment with invented data):
# {
#     "name": "Maria G.",                # as the client agreed to be named
#     "role": "Marketplace seller",       # optional
#     "platform": "amazon",               # platform id from content_platforms.py, optional
#     "service": "plan-of-action",        # service id from content_services.py, optional
#     "rating": 5,                        # 1 to 5, as the client gave it
#     "text": "Exact words the client wrote.",
#     "date": "2026-08-14",
#     "consent_ref": "Consent form AS-1A2B3C4D, 08/14/2026",  # internal proof, never rendered
# },
# Once five or more are present, pages show the true average rating computed from them.


def email() -> str:
    return f"{EMAIL_USER}@{EMAIL_DOMAIN}" if EMAIL_USER else ""
