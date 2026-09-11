#!/usr/bin/env python3
"""
Skrip otomatisasi untuk menghapus latar belakang logo dan mengekspornya
sebagai WebP transparan (RGBA) lossless.
"""
from pathlib import Path
from rembg import remove
from PIL import Image

def process_logo():
    base_dir = Path(__file__).resolve().parent
    input_path = base_dir / 'public' / 'images' / 'logo.webp'
    output_path = base_dir / 'public' / 'images' / 'logo-clean.webp'

    if not input_path.exists():
        raise FileNotFoundError(f"File input tidak ditemukan: {input_path}")

    print(f"Membuka gambar dari: {input_path}")
    input_image = Image.open(input_path)
    
    print("Menghapus latar belakang menggunakan rembg...")
    output_image = remove(input_image)
    
    # Pastikan mode gambar adalah RGBA untuk transparansi penuh
    if output_image.mode != 'RGBA':
        output_image = output_image.convert('RGBA')

    print(f"Menyimpan hasil ke: {output_path} (Format: WEBP, Lossless: True)")
    output_image.save(output_path, 'WEBP', lossless=True)
    print("Selesai! Logo transparan berhasil dibuat.")

if __name__ == '__main__':
    process_logo()
