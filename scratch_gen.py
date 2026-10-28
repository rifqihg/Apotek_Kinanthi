import os
import math
from PIL import Image, ImageDraw, ImageFont

# Canvas setup
W, H = 1450, 1400
img = Image.new('RGB', (W, H), '#ffffff')
draw = ImageDraw.Draw(img)

# Fonts
try:
    font_title = ImageFont.truetype('arialbd.ttf', 15)
    font_actor = ImageFont.truetype('arialbd.ttf', 16)
    font_node = ImageFont.truetype('arial.ttf', 12)
    font_node_bd = ImageFont.truetype('arialbd.ttf', 12)
    font_rel = ImageFont.truetype('arial.ttf', 10)
except:
    font_title = font_actor = font_node = font_node_bd = font_rel = ImageFont.load_default()

# System boundary
sys_x1, sys_y1, sys_x2, sys_y2 = 280, 40, 1150, 1340

# Draw dashed system boundary
def draw_dashed_rect(draw, x1, y1, x2, y2, color='#000000', dash=(8, 6)):
    for x in range(x1, x2, dash[0] + dash[1]):
        draw.line([(x, y1), (min(x + dash[0], x2), y1)], fill=color, width=1)
        draw.line([(x, y2), (min(x + dash[0], x2), y2)], fill=color, width=1)
    for y in range(y1, y2, dash[0] + dash[1]):
        draw.line([(x1, y), (x1, min(y + dash[0], y2))], fill=color, width=1)
        draw.line([(x2, y), (x2, min(y + dash[0], y2))], fill=color, width=1)

draw_dashed_rect(draw, sys_x1, sys_y1, sys_x2, sys_y2)

# Title at top
title_text = 'RANCANG BANGUN SISTEM PENGELOLAAN STOK OBAT APOTEK KINANTHI'
bbox = draw.textbbox((0, 0), title_text, font=font_title)
tw = bbox[2] - bbox[0]
draw.text(((sys_x1 + sys_x2 - tw) // 2, sys_y1 + 10), title_text, fill='#000000', font=font_title)

# Actors
# Admin (Left): center (130, 680), radius 65
admin_c = (130, 680)
draw.ellipse([admin_c[0]-65, admin_c[1]-65, admin_c[0]+65, admin_c[1]+65], outline='#000000', fill='#ffffff', width=2)
abox = draw.textbbox((0, 0), 'Admin', font=font_actor)
draw.text((admin_c[0] - (abox[2]-abox[0])//2, admin_c[1] - (abox[3]-abox[1])//2), 'Admin', fill='#000000', font=font_actor)

# Kasir (Right Top): center (1310, 480), radius 65
kasir_c = (1310, 480)
draw.ellipse([kasir_c[0]-65, kasir_c[1]-65, kasir_c[0]+65, kasir_c[1]+65], outline='#000000', fill='#ffffff', width=2)
kbox = draw.textbbox((0, 0), 'Kasir', font=font_actor)
draw.text((kasir_c[0] - (kbox[2]-kbox[0])//2, kasir_c[1] - (kbox[3]-kbox[1])//2), 'Kasir', fill='#000000', font=font_actor)

# Apoteker (Right Bottom): center (1310, 960), radius 65
apt_c = (1310, 960)
draw.ellipse([apt_c[0]-65, apt_c[1]-65, apt_c[0]+65, apt_c[1]+65], outline='#000000', fill='#ffffff', width=2)
apbox = draw.textbbox((0, 0), 'Apoteker', font=font_actor)
draw.text((apt_c[0] - (apbox[2]-apbox[0])//2, apt_c[1] - (apbox[3]-apbox[1])//2), 'Apoteker', fill='#000000', font=font_actor)

print('Actors drawn')
