#!/usr/bin/env python3
"""
Skrip otomatisasi untuk menghapus latar belakang logo dan mengekspornya
sebagai WebP transparan (RGBA) lossless dengan autocrop.
"""
from pathlib import Path
from rembg import remove
from PIL import Image

def clean_image(input_path: Path, output_path: Path):
    if not input_path.exists():
        raise FileNotFoundError(f"File input tidak ditemukan: {input_path}")

    print(f"Membuka gambar dari: {input_path}")
    input_image = Image.open(input_path)
    
    print("Menghapus latar belakang menggunakan rembg...")
    output_image = remove(input_image)
    
    # Pastikan mode gambar adalah RGBA untuk transparansi penuh
    if output_image.mode != 'RGBA':
        output_image = output_image.convert('RGBA')

    # Autocrop: Pangkas area transparan/kosong berlebih
    bbox = output_image.getbbox()
    if bbox:
        print(f"Memangkas area kosong berlebih (autocrop) dari {output_image.size} ke bbox {bbox}...")
        output_image = output_image.crop(bbox)

    print(f"Menyimpan hasil ke: {output_path} (Format: WEBP, Lossless: True, Ukuran: {output_image.size})")
    output_image.save(output_path, 'WEBP', lossless=True)

def process_all_logos():
    base_dir = Path(__file__).resolve().parent
    images_dir = base_dir / 'public' / 'images'

    # 1. Logo Apotek Kinanthi
    kinanthi_in = images_dir / 'logo.webp'
    kinanthi_out = images_dir / 'logo-clean.webp'
    if kinanthi_in.exists():
        print("\n--- Memproses Logo Apotek Kinanthi ---")
        clean_image(kinanthi_in, kinanthi_out)

    # 2. Logo Kemenkes
    kemenkes_in = images_dir / 'kemenkes.webp'
    kemenkes_clean = images_dir / 'kemenkes-clean.webp'
    if kemenkes_in.exists():
        print("\n--- Memproses Logo Kemenkes ---")
        clean_image(kemenkes_in, kemenkes_clean)
        # Salin juga ke kemenkes.webp agar kedua file bersih
        clean_image(kemenkes_in, kemenkes_in)

    print("\nSelesai! Seluruh logo transparan berhasil diproses.")

if __name__ == '__main__':
    process_all_logos()

