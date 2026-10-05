"""Generates the flat vector illustrations in public/assets/corporate/.

Self-made for FTS Corporate AI (no third-party artwork, no license needed).
Colors follow resources/css/theme.css (docs/UI_FOUNDATION.md) plus a few
tints. Re-run after changing the drawings:

    python scripts/generate-visual-assets.py
"""

from pathlib import Path

OUT = Path(__file__).resolve().parent.parent / 'public' / 'assets' / 'corporate'

# Theme colors and tints.
PRIMARY = '#0F766E'
PRIMARY_DARK = '#115E59'
PRIMARY_LIGHT = '#5EAAA0'
PRIMARY_PALE = '#CDE8E4'
NAVY = '#142337'
NAVY_2 = '#203551'
ACCENT = '#2563EB'
ACCENT_PALE = '#C9D9F7'
CANVAS = '#F4F7FB'
WALL = '#E4ECF4'
WALL_DARK = '#D7E0EA'
FLOOR = '#C9D4E0'
FLOOR_DARK = '#B7C4D3'
WHITE = '#FFFFFF'
MUTED = '#526579'
WOOD = '#D9C3A5'
WOOD_DARK = '#B99B76'
LEAF = '#2F8F5B'
LEAF_DARK = '#1F6B43'
POT = '#E8E1D6'
WARM = '#F2B94B'
SKIN = '#E9B48C'
SKIN_SHADE = '#D69B72'
HAIR = '#2B2F3A'

W, H = 1600, 900
HORIZON = 640


def svg(width, height, body, title):
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width} {height}" '
        f'width="{width}" height="{height}" role="img" aria-label="{title}">\n'
        f'<title>{title}</title>\n{body}\n</svg>\n'
    )


def rect(x, y, w, h, fill, rx=0, extra=''):
    return f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{rx}" fill="{fill}" {extra}/>'


def circle(cx, cy, r, fill, extra=''):
    return f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="{fill}" {extra}/>'


def path(d, fill, extra=''):
    return f'<path d="{d}" fill="{fill}" {extra}/>'


def room(wall_accent):
    """Wall, baseboard, floor with soft perspective lines, and a window."""
    parts = [
        rect(0, 0, W, HORIZON, WALL),
        rect(0, 0, W, 70, wall_accent, extra='opacity="0.9"'),
        rect(0, HORIZON - 18, W, 18, WALL_DARK),
        rect(0, HORIZON, W, H - HORIZON, FLOOR),
    ]
    for i in range(-6, 14):
        x = 800 + i * 160
        parts.append(f'<line x1="{800 + i * 60}" y1="{HORIZON}" x2="{x}" y2="{H}" stroke="{FLOOR_DARK}" stroke-width="2" opacity="0.5"/>')
    parts.append(rect(0, HORIZON + 120, W, 3, FLOOR_DARK, extra='opacity="0.5"'))
    return parts


def window(x, y, w=300, h=260):
    return [
        rect(x - 10, y - 10, w + 20, h + 20, WHITE, rx=8),
        rect(x, y, w, h, ACCENT_PALE, rx=4),
        path(f'M{x} {y + h} L{x + w * 0.35} {y + h * 0.55} L{x + w * 0.6} {y + h * 0.8} L{x + w * 0.8} {y + h * 0.45} L{x + w} {y + h * 0.7} L{x + w} {y + h} Z', PRIMARY_PALE),
        rect(x + w / 2 - 4, y, 8, h, WHITE),
        rect(x, y + h / 2 - 4, w, 8, WHITE),
        circle(x + w * 0.78, y + h * 0.25, 26, WHITE, 'opacity="0.8"'),
    ]


def plant(x, base_y, scale=1.0):
    s = scale
    return [
        path(f'M{x} {base_y - 150 * s} C{x - 70 * s} {base_y - 210 * s} {x - 90 * s} {base_y - 120 * s} {x - 20 * s} {base_y - 95 * s} Z', LEAF),
        path(f'M{x} {base_y - 150 * s} C{x + 70 * s} {base_y - 230 * s} {x + 95 * s} {base_y - 130 * s} {x + 22 * s} {base_y - 95 * s} Z', LEAF_DARK),
        path(f'M{x} {base_y - 190 * s} C{x - 30 * s} {base_y - 260 * s} {x + 40 * s} {base_y - 270 * s} {x + 5 * s} {base_y - 120 * s} Z', LEAF),
        path(f'M{x - 45 * s} {base_y - 100 * s} L{x + 45 * s} {base_y - 100 * s} L{x + 34 * s} {base_y} L{x - 34 * s} {base_y} Z', POT),
        rect(x - 48 * s, base_y - 108 * s, 96 * s, 14 * s, WOOD, rx=4),
    ]


def shadow(cx, cy, rx, ry=14):
    return f'<ellipse cx="{cx}" cy="{cy}" rx="{rx}" ry="{ry}" fill="{NAVY}" opacity="0.12"/>'


def desk(x, y, w, h=26, legs=110):
    return [
        shadow(x + w / 2, y + legs + h + 6, w / 2),
        rect(x + 20, y + h, 18, legs, WOOD_DARK),
        rect(x + w - 38, y + h, 18, legs, WOOD_DARK),
        rect(x, y, w, h, WOOD, rx=6),
    ]


def chair(x, y, color=NAVY_2):
    return [
        shadow(x + 45, y + 165, 50, 10),
        rect(x + 10, y, 70, 90, color, rx=18),
        rect(x, y + 85, 90, 24, color, rx=10),
        rect(x + 40, y + 109, 10, 45, MUTED),
        rect(x + 15, y + 150, 60, 8, MUTED, rx=4),
    ]


def monitor(x, y, w=150, h=95, screen=NAVY):
    return [
        rect(x, y, w, h, NAVY_2, rx=8),
        rect(x + 8, y + 8, w - 16, h - 16, screen, rx=4),
        rect(x + w / 2 - 8, y + h, 16, 26, MUTED),
        rect(x + w / 2 - 40, y + h + 24, 80, 8, MUTED, rx=4),
    ]


def wall_frame(x, y, w, h, inner):
    return [rect(x, y, w, h, WHITE, rx=10, extra=f'stroke="{WALL_DARK}" stroke-width="4"'), *inner]


def clock(cx, cy):
    return [
        circle(cx, cy, 46, WHITE, f'stroke="{NAVY_2}" stroke-width="8"'),
        f'<line x1="{cx}" y1="{cy}" x2="{cx}" y2="{cy - 28}" stroke="{NAVY}" stroke-width="6" stroke-linecap="round"/>',
        f'<line x1="{cx}" y1="{cy}" x2="{cx + 20}" y2="{cy + 8}" stroke="{PRIMARY}" stroke-width="6" stroke-linecap="round"/>',
        circle(cx, cy, 6, NAVY),
    ]


def logo_mark(x, y, size, bg=PRIMARY, fg=WHITE):
    """Monogram: rounded square with a speech bubble that forms a building."""
    s = size / 64
    return (
        f'<g transform="translate({x} {y}) scale({s})">'
        f'<rect width="64" height="64" rx="16" fill="{bg}"/>'
        f'<path d="M18 16h28a6 6 0 0 1 6 6v18a6 6 0 0 1-6 6H30l-9 7v-7h-3a6 6 0 0 1-6-6V22a6 6 0 0 1 6-6Z" fill="{fg}"/>'
        f'<rect x="21" y="23" width="6" height="6" rx="1.5" fill="{bg}"/><rect x="29" y="23" width="6" height="6" rx="1.5" fill="{bg}"/>'
        f'<rect x="37" y="23" width="6" height="6" rx="1.5" fill="{bg}"/><rect x="21" y="32" width="6" height="6" rx="1.5" fill="{bg}"/>'
        f'<rect x="29" y="32" width="6" height="6" rx="1.5" fill="{bg}"/><rect x="37" y="32" width="6" height="6" rx="1.5" fill="{bg}"/>'
        '</g>'
    )


def reception():
    p = room(PRIMARY)
    p += window(980, 150)
    p += clock(560, 210)
    p += [logo_mark(700, 150, 120)]
    # Reception counter.
    p += [
        shadow(800, 830, 430, 22),
        rect(390, 600, 820, 220, PRIMARY_DARK, rx=18),
        rect(370, 580, 860, 40, WOOD, rx=12),
        rect(430, 650, 740, 12, PRIMARY_LIGHT, rx=6, extra='opacity="0.6"'),
        logo_mark(760, 680, 80, bg=WHITE, fg=PRIMARY),
    ]
    p += monitor(880, 470, 140, 90, screen=PRIMARY_PALE)
    # Bench and plants.
    p += [shadow(1420, 805, 140), rect(1290, 700, 260, 60, NAVY_2, rx=20), rect(1310, 760, 16, 40, MUTED), rect(1514, 760, 16, 40, MUTED)]
    p += plant(220, 820, 1.3)
    p += plant(1330, 690, 0.8)
    return svg(W, H, '\n'.join(p), 'Reception')


def knowledge():
    p = room(ACCENT)
    # Bookshelves.
    colors = [PRIMARY, ACCENT, NAVY_2, WARM, PRIMARY_LIGHT, MUTED]
    for sx in (120, 520):
        p += [shadow(sx + 170, HORIZON + 170, 190), rect(sx, 140, 340, 680, WOOD_DARK, rx=10), rect(sx + 14, 154, 312, 652, WOOD, rx=6)]
        for row in range(4):
            y = 170 + row * 160
            p.append(rect(sx + 14, y + 130, 312, 14, WOOD_DARK))
            bx = sx + 28
            for i in range(9):
                bw = 22 + (i * 7) % 14
                bh = 90 + (i * 13 + row * 11) % 34
                p.append(rect(bx, y + 130 - bh, bw, bh, colors[(i + row) % len(colors)], rx=3))
                bx += bw + 6
    p += window(1060, 150, 360, 250)
    # Reading table with lamp and open book.
    p += desk(1000, 640, 440, legs=150)
    p += [
        path('M1120 640 L1150 600 L1210 610 L1220 640 Z', WHITE),
        path('M1220 640 L1230 610 L1290 600 L1320 640 Z', WHITE),
        rect(1218, 600, 4, 40, WALL_DARK),
        rect(1360, 520, 10, 120, NAVY_2),
        path('M1320 520 L1410 520 L1390 470 L1340 470 Z', WARM),
        circle(1365, 640, 6, NAVY_2),
    ]
    p += chair(1150, 690, PRIMARY)
    p += plant(1500, 840, 1.0)
    return svg(W, H, '\n'.join(p), 'Knowledge Center')


def hr():
    p = room(PRIMARY_LIGHT)
    p += window(160, 150, 320, 260)
    # People board.
    board = []
    for i, (cx, color) in enumerate(((760, PRIMARY), (880, ACCENT), (1000, WARM), (1120, PRIMARY_LIGHT))):
        board += [circle(cx, 225, 30, color), path(f'M{cx - 45} 320 C{cx - 45} 265 {cx + 45} 265 {cx + 45} 320 Z', color)]
    p += wall_frame(690, 150, 500, 200, board)
    p += [rect(720, 375, 440, 14, WALL_DARK, rx=7), rect(720, 400, 300, 14, WALL_DARK, rx=7)]
    # Desk with two chairs (conversation).
    p += desk(620, 600, 520, legs=130)
    p += monitor(820, 480, 140, 90, screen=PRIMARY_PALE)
    p += [rect(680, 572, 90, 28, WHITE, rx=4), rect(690, 562, 90, 10, ACCENT_PALE, rx=3), circle(1060, 585, 16, WHITE), rect(1052, 570, 16, 6, PRIMARY)]
    p += chair(500, 600, NAVY_2)
    p += chair(1180, 600, PRIMARY)
    # Cabinet and plant.
    p += [shadow(1420, 830, 110), rect(1320, 470, 200, 350, WHITE, rx=10, extra=f'stroke="{WALL_DARK}" stroke-width="4"')]
    for i in range(3):
        p += [rect(1336, 490 + i * 108, 168, 92, CANVAS, rx=6), rect(1395, 530 + i * 108, 50, 10, MUTED, rx=5)]
    p += plant(260, 840, 1.2)
    return svg(W, H, '\n'.join(p), 'HR Desk')


def it():
    p = room(NAVY_2)
    # Server rack.
    p += [shadow(260, 830, 130), rect(140, 220, 240, 600, NAVY, rx=12)]
    for i in range(8):
        y = 250 + i * 68
        p += [rect(165, y, 190, 52, NAVY_2, rx=6), circle(190, y + 26, 7, LEAF if i % 3 else WARM), circle(214, y + 26, 7, PRIMARY_LIGHT), rect(240, y + 20, 95, 12, MUTED, rx=6)]
    # Wi-Fi sign.
    p += wall_frame(560, 160, 180, 160, [
        path('M600 240 A80 80 0 0 1 700 240', 'none', f'stroke="{PRIMARY}" stroke-width="14" stroke-linecap="round"'),
        path('M620 262 A50 50 0 0 1 680 262', 'none', f'stroke="{PRIMARY}" stroke-width="14" stroke-linecap="round"'),
        circle(650, 290, 10, PRIMARY),
    ])
    p += window(1180, 140, 300, 240)
    # Desk with three monitors and a laptop.
    p += desk(520, 620, 640, legs=140)
    p += monitor(560, 480, 160, 100, screen=PRIMARY_DARK)
    p += monitor(760, 460, 180, 120, screen=NAVY)
    p += monitor(980, 480, 160, 100, screen=PRIMARY_DARK)
    for i in range(4):
        p.append(rect(782, 480 + i * 22, 60 + (i * 37) % 70, 8, PRIMARY_LIGHT, rx=4))
    p += [rect(800, 600, 110, 16, MUTED, rx=4), path('M1180 620 L1200 580 L1300 580 L1320 620 Z', NAVY_2)]
    p += chair(780, 660, NAVY_2)
    p += plant(1450, 840, 1.1)
    return svg(W, H, '\n'.join(p), 'IT Helpdesk')


def requests():
    p = room(WARM)
    # Kanban board.
    p += [rect(300, 130, 760, 440, WHITE, rx=14, extra=f'stroke="{WALL_DARK}" stroke-width="6"')]
    columns = ((340, ACCENT, 3), (590, WARM, 2), (840, LEAF, 2))
    for x, color, cards in columns:
        p += [rect(x, 160, 190, 26, color, rx=8)]
        for i in range(cards):
            y = 205 + i * 112
            p += [rect(x, y, 190, 96, CANVAS, rx=10, extra=f'stroke="{WALL_DARK}" stroke-width="3"'), rect(x + 16, y + 18, 120, 12, MUTED, rx=6), rect(x + 16, y + 42, 150, 10, WALL_DARK, rx=5), circle(x + 162, y + 72, 12, color)]
    # Desk with inbox tray.
    p += desk(1120, 620, 380, legs=140)
    p += [rect(1160, 570, 150, 50, PRIMARY, rx=8), rect(1170, 552, 130, 24, WHITE, rx=4), rect(1180, 538, 110, 20, ACCENT_PALE, rx=4)]
    p += monitor(1340, 500, 130, 85, screen=PRIMARY_PALE)
    p += chair(1250, 660, PRIMARY)
    p += plant(180, 840, 1.25)
    p += [shadow(700, 835, 160), rect(560, 700, 280, 110, WOOD, rx=14), rect(580, 680, 240, 30, WOOD_DARK, rx=10)]
    return svg(W, H, '\n'.join(p), 'My Requests')


def assistant():
    w, h = 600, 800
    p = [
        rect(0, 0, w, h, PRIMARY_PALE),
        circle(300, 330, 230, WHITE, 'opacity="0.55"'),
        circle(470, 150, 40, WHITE, 'opacity="0.6"'),
        circle(110, 560, 26, WHITE, 'opacity="0.6"'),
        # Body.
        path('M110 800 C110 600 180 520 300 520 C420 520 490 600 490 800 Z', PRIMARY),
        path('M255 520 L300 600 L345 520 Z', WHITE),
        path('M300 600 L280 640 L300 700 L320 640 Z', NAVY_2),
        path('M190 560 L255 520 L280 640 L230 800 L170 800 Z', PRIMARY_DARK, 'opacity="0.35"'),
        path('M410 560 L345 520 L320 640 L370 800 L430 800 Z', PRIMARY_DARK, 'opacity="0.35"'),
        # Name badge.
        rect(360, 640, 70, 36, WHITE, rx=6),
        rect(370, 650, 50, 6, PRIMARY, rx=3),
        rect(370, 662, 34, 5, MUTED, rx=2.5),
        # Neck and head.
        rect(268, 440, 64, 90, SKIN_SHADE, rx=24),
        circle(300, 360, 108, SKIN),
        # Hair.
        path('M192 360 C180 250 250 205 312 210 C385 214 425 270 410 360 C395 300 350 280 300 286 C250 290 215 310 192 360 Z', HAIR),
        path('M195 345 C186 400 196 440 214 460 L222 370 Z', HAIR),
        # Face.
        circle(258, 372, 10, NAVY),
        circle(342, 372, 10, NAVY),
        path('M262 418 C285 440 315 440 338 418', 'none', f'stroke="{NAVY}" stroke-width="7" stroke-linecap="round"'),
        circle(232, 405, 14, WARM, 'opacity="0.35"'),
        circle(368, 405, 14, WARM, 'opacity="0.35"'),
        # Headset.
        path('M196 360 C196 250 404 250 404 360', 'none', f'stroke="{NAVY_2}" stroke-width="12" stroke-linecap="round"'),
        rect(182, 340, 30, 60, NAVY_2, rx=12),
        rect(388, 340, 30, 60, NAVY_2, rx=12),
        path('M400 395 C400 440 370 455 335 455', 'none', f'stroke="{NAVY_2}" stroke-width="7" stroke-linecap="round"'),
        circle(332, 455, 9, NAVY_2),
        # Waving hand.
        path('M478 640 C520 600 540 520 520 470', 'none', f'stroke="{PRIMARY}" stroke-width="58" stroke-linecap="round"'),
        circle(522, 452, 34, SKIN),
        # Chat bubble.
        rect(70, 120, 150, 100, WHITE, rx=24),
        path('M150 220 L180 250 L180 218 Z', WHITE),
        circle(110, 170, 10, PRIMARY), circle(145, 170, 10, PRIMARY), circle(180, 170, 10, PRIMARY),
    ]
    return svg(w, h, '\n'.join(p), 'Office assistant')


def logo():
    return svg(64, 64, logo_mark(0, 0, 64), 'FTS Corporate AI')


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    files = {
        'reception.svg': reception(),
        'knowledge.svg': knowledge(),
        'hr.svg': hr(),
        'it.svg': it(),
        'requests.svg': requests(),
        'assistant.svg': assistant(),
        'logo.svg': logo(),
    }
    for name, content in files.items():
        (OUT / name).write_text(content, encoding='utf-8', newline='\n')
    (OUT.parent.parent / 'favicon.svg').write_text(logo(), encoding='utf-8', newline='\n')
    print('Wrote', ', '.join(files), 'and public/favicon.svg')


if __name__ == '__main__':
    main()
