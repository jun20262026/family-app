from PIL import Image, ImageDraw, ImageFont
import os

# 创建 1024x1024 主图标
size = 1024
img = Image.new('RGBA', (size, size), (0, 0, 0, 0))
draw = ImageDraw.Draw(img)

# 圆角矩形背景（紫蓝渐变模拟：纯色 #5B4DE6）
bg_color = (91, 77, 230, 255)
corner = 220

# 绘制圆角矩形
draw.rounded_rectangle([0, 0, size, size], radius=corner, fill=bg_color)

# 绘制房子图标（白色）
house_color = (255, 255, 255, 255)
cx, cy = size // 2, size // 2

# 房子主体
house_w, house_h = 420, 340
house_left = cx - house_w // 2
house_top = cy + 20
house_right = house_left + house_w
house_bottom = house_top + house_h
draw.rectangle([house_left, house_top, house_right, house_bottom], fill=house_color)

# 屋顶（三角形）
roof_top = cy - 200
roof_left = cx - house_w // 2 - 40
roof_right = cx + house_w // 2 + 40
draw.polygon([(roof_left, house_top), (cx, roof_top), (roof_right, house_top)], fill=house_color)

# 门
door_w, door_h = 140, 200
door_left = cx - door_w // 2
door_top = house_bottom - door_h
door_color = (91, 77, 230, 255)  # 和背景同色
draw.rectangle([door_left, door_top, door_left + door_w, house_bottom], fill=door_color, outline=door_color)

# 保存主图标
out_dir = os.path.join(os.path.dirname(__file__), 'android', 'app', 'src', 'main', 'res')
os.makedirs(out_dir, exist_ok=True)

# Android 图标尺寸映射
sizes = {
    'mipmap-mdpi': 48,
    'mipmap-hdpi': 72,
    'mipmap-xhdpi': 96,
    'mipmap-xxhdpi': 144,
    'mipmap-xxxhdpi': 192,
}

# 同时生成圆形图标
for name, s in sizes.items():
    d = os.path.join(out_dir, name)
    os.makedirs(d, exist_ok=True)
    # 方形图标
    icon = img.resize((s, s), Image.LANCZOS)
    icon.save(os.path.join(d, 'ic_launcher.png'))
    icon.save(os.path.join(d, 'ic_launcher_foreground.png'))
    # 圆形图标（简单做法：用方形）
    icon.save(os.path.join(d, 'ic_launcher_round.png'))

# 额外保存一个高分辨率版本到项目根目录供参考
img.save(os.path.join(os.path.dirname(__file__), 'app-icon-1024.png'))

print("图标生成完成！")
for name, s in sizes.items():
    print(f"  {name}: {s}x{s}px")
