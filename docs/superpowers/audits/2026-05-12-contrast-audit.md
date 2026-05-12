# WCAG AA Contrast Audit — 2026-05-12

Per the UI spec, every visible foreground / background combination in
`webroot/css/theme.css` must clear WCAG AA (4.5:1 for normal text, 3:1
for large text / UI affordances). This audit measures each documented
combination and flags failures.

Computed using the WCAG 2.1 relative-luminance formula:
https://www.w3.org/TR/WCAG21/#dfn-contrast-ratio

## Results

| #  | Use                            | Foreground | Background | Ratio    | Threshold | Verdict   |
|----|--------------------------------|------------|------------|----------|-----------|-----------|
|  1 | body text on page              | `#09090b`  | `#fafafa`  | 19.06:1  | 4.5:1     | PASS AAA  |
|  2 | body text on cards             | `#09090b`  | `#ffffff`  | 19.90:1  | 4.5:1     | PASS AAA  |
|  3 | help text on cards             | `#52525b`  | `#ffffff`  |  7.73:1  | 4.5:1     | PASS AAA  |
|  4 | placeholders / em-dashes       | `#71717a`  | `#ffffff`  |  4.83:1  | 4.5:1     | PASS      |
|  5 | em-dashes on page              | `#71717a`  | `#fafafa`  |  4.63:1  | 4.5:1     | PASS      |
|  6 | text on alt surface            | `#52525b`  | `#f4f4f5`  |  7.03:1  | 4.5:1     | PASS AAA  |
|  7 | link text                      | `#047857`  | `#ffffff`  |  5.48:1  | 4.5:1     | PASS      |
|  8 | accent text / icons            | `#059669`  | `#ffffff`  |  3.77:1  | 3:1 (UI)  | PASS      |
|  9 | white text on btn-primary      | `#ffffff`  | `#18181b`  | 17.72:1  | 4.5:1     | PASS AAA  |
| 10 | danger button text             | `#dc2626`  | `#ffffff`  |  4.83:1  | 4.5:1     | PASS      |
| 11 | badge.bg-danger                | `#b91c1c`  | `#fee2e2`  |  5.30:1  | 4.5:1     | PASS      |
| 12 | badge.bg-info                  | `#075985`  | `#e0f2fe`  |  6.59:1  | 4.5:1     | PASS      |
| 13 | badge.bg-warning               | `#92400e`  | `#fef3c7`  |  6.37:1  | 4.5:1     | PASS      |
| 14 | badge.bg-success               | `#047857`  | `#ecfdf5`  |  5.21:1  | 4.5:1     | PASS      |
| 15 | text on alert-info             | `#52525b`  | `#f0f9ff`  |  7.25:1  | 4.5:1     | PASS AAA  |
| 16 | text on alert-success          | `#52525b`  | `#ecfdf5`  |  7.34:1  | 4.5:1     | PASS AAA  |
| 17 | text on alert-warning          | `#52525b`  | `#fffbeb`  |  7.45:1  | 4.5:1     | PASS AAA  |
| 18 | text on alert-danger           | `#52525b`  | `#fef2f2`  |  7.07:1  | 4.5:1     | PASS AAA  |
| 19 | text on .btn-secondary         | `#18181b`  | `#e4e4e7`  | 13.96:1  | 4.5:1     | PASS AAA  |
| 20 | white text on btn-danger       | `#ffffff`  | `#dc2626`  |  4.83:1  | 4.5:1     | PASS      |

Legend:
- **PASS** = meets WCAG AA threshold.
- **AAA** = also meets WCAG AAA (7:1 normal text / 4.5:1 large text).
- **FAIL** = below AA; documented as a defect for follow-up.

## Failures

None — all 20 combos meet WCAG AA.

## Notes — combos that pass but sit close to the AA floor

These are not defects, but they have the smallest margin against the
4.5:1 normal-text threshold. Any future tweak (darker bg, lighter fg)
must be re-audited or it risks regressing AA.

| #  | Use                       | Ratio   | Margin over 4.5:1 |
|----|---------------------------|---------|-------------------|
|  5 | `#71717a` on `#fafafa`    | 4.63:1  | +0.13             |
|  4 | `#71717a` on `#ffffff`    | 4.83:1  | +0.33             |
| 10 | `#dc2626` on `#ffffff`    | 4.83:1  | +0.33             |
| 20 | `#ffffff` on `#dc2626`    | 4.83:1  | +0.33             |
|  7 | `#047857` on `#ffffff`    | 5.48:1  | +0.98             |

Row 8 (`#059669` on `#ffffff` = 3.77:1) is judged against the 3:1
large-text / UI threshold per the spec, since `--accent` is documented
for icons and large accent UI, not body copy. If `--accent` is ever
applied to small body text, it must be swapped for `--accent-strong`.

## Method

Ratios computed with a one-line PHP script using the WCAG 2.1
relative-luminance formula, gamma-correcting each sRGB channel
(threshold 0.03928, exponent 2.4) before applying the BT.709 weights
(0.2126 / 0.7152 / 0.0722). See the script in the audit commit.

## Verified by

QA / Testing Engineer subagent (Robbi Nespu project team), 2026-05-12.

## Caveats

- Only documented theme.css tokens were measured. Browser-default colors
  (`#000` on `#fff` for unstyled elements, link blue, etc.) are not
  audited.
- Dark mode tokens (`[data-theme="eqsl-dark"]`) are not audited here —
  they're introduced in Phase 4 of the implementation plan and will get
  their own audit at that point.
- Real on-device contrast may differ slightly due to display gamut.
