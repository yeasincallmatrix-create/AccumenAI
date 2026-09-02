# Global Mobile Phone Number Lengths

Source: Gemini conversation + ITU-T E.164 (max 15 total digits incl. country code). This doc is the **source of truth** for `app/Support/CountryCodes::NATIONAL_LENGTHS`.

> Note: Lengths are **national digits including leading trunk `0` where applicable** (e.g. Bangladesh `01XXXXXXXXX` = 11, UK `07XXX XXXXXX` = 11). This matches `CountryCodes::PHONE_EXAMPLES` and the current `partials/phone` placeholder. For ranges (e.g. Indonesia `10–12`) the hint shows `Valid 10–12 digits`.

According to ITU-T E.164, national mobile numbers range **7–12** digits; most common **9–10**.

---

## Americas

| Country / Region | National Mobile Digits |
| :--- | :---: |
| **United States** | 10 |
| **Canada** | 10 |
| **Mexico** | 10 |
| **Brazil** | 11 |
| **Argentina** | 10 |
| **Colombia** | 10 |
| **Chile** | 9 |
| **Peru** | 9 |
| **Venezuela** | 10 |
| **Ecuador** | 9 |
| **Guatemala** | 8 |
| **Cuba** | 8 |
| **Dominican Republic** | 10 |
| **Haiti** | 8 |
| **Bolivia** | 8 |
| **Uruguay** | 8 |
| **Paraguay** | 9 |
| **Costa Rica** | 8 |
| **Panama** | 8 |
| **Jamaica** | 10 |

---

## Asia & Oceania

| Country / Region | National Mobile Digits |
| :--- | :---: |
| **China** | 11 |
| **India** | 10 |
| **Indonesia** | 10–12 |
| **Pakistan** | 10 |
| **Bangladesh** | 11 |
| **Japan** | 10 |
| **Philippines** | 10 |
| **Vietnam** | 9 |
| **South Korea** | 10 |
| **Taiwan** | 9 |
| **Thailand** | 9 |
| **Malaysia** | 9–10 |
| **Singapore** | 8 |
| **Sri Lanka** | 9 |
| **Myanmar** | 8–9 |
| **Australia** | 10 |
| **New Zealand** | 8–10 |
| **Papua New Guinea** | 8 |
| **Fiji** | 7 |

---

## Europe

| Country / Region | National Mobile Digits |
| :--- | :---: |
| **United Kingdom** | 11 |
| **Germany** | 10–11 |
| **France** | 9 |
| **Italy** | 10 |
| **Spain** | 9 |
| **Russia** | 10 |
| **Ukraine** | 9 |
| **Poland** | 9 |
| **Romania** | 9 |
| **Netherlands** | 9 |
| **Belgium** | 9 |
| **Greece** | 10 |
| **Czech Republic** | 9 |
| **Portugal** | 9 |
| **Sweden** | 9 |
| **Hungary** | 9 |
| **Austria** | 10–11 |
| **Switzerland** | 9 |
| **Norway** | 8 |
| **Denmark** | 8 |
| **Finland** | 9–10 |
| **Ireland** | 9 |

---

## Middle East & Africa

| Country / Region | National Mobile Digits |
| :--- | :---: |
| **Nigeria** | 10 |
| **Egypt** | 10 |
| **Ethiopia** | 9 |
| **South Africa** | 9 |
| **Kenya** | 9 |
| **Tanzania** | 9 |
| **Algeria** | 9 |
| **Uganda** | 9 |
| **Sudan** | 9 |
| **Morocco** | 9 |
| **Saudi Arabia** | 10 |
| **United Arab Emirates (UAE)** | 10 |
| **Israel** | 9 |
| **Iraq** | 10 |
| **Iran** | 10 |
| **Qatar** | 8 |
| **Kuwait** | 8 |
| **Oman** | 8 |
| **Ghana** | 9 |
| **Ivory Coast** | 10 |

---

## Notes

- Values **include** national trunk `0` where the example shows it (Bangladesh `017...` = 11, UK `07...` = 11, Australia `04...` = 10). For hint purposes `NATIONAL_LENGTHS` stores inc-trunk lengths, matching user-visible placeholder.
- Ranges (`10–12`, `9–10`) mean any length in range is `Valid`; below `min` = `Incomplete`, above `max` = `Too long`.
- Countries not listed fall back to `7–12` generic (ITU range) via `CountryCodes::nationalLengthFor()` fallback.
- E.164 international total (code + national exc trunk `0`) must never exceed 15; `partial/phone` `maxlength` is `code.len + max + 1` for `+`.
