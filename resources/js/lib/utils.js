import { clsx } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs) {
  return twMerge(clsx(inputs));
}

export function valueUpdater(updaterOrValue, ref) {
  ref.value =
    typeof updaterOrValue === "function"
      ? updaterOrValue(ref.value)
      : updaterOrValue;
}

/**
 * Konversi stok / ambang batas terkecil (modulo) menjadi format multi-satuan manusiawi.
 * @param {number|string} totalQty - Jumlah stok dalam satuan terkecil (primer)
 * @param {number|string} ratioSekunder - Rasio satuan primer ke sekunder (contoh: 10 tablet per strip)
 * @param {number|string} ratioTersier - Rasio satuan sekunder ke tersier (contoh: 6 strip per dus)
 * @param {Object} [unitNames] - Nama satuan opsional { tersier: 'Dus', sekunder: 'Strip', primer: 'Tablet' }
 * @returns {string} Contoh: "1 Dus, 4 Strip"
 */
export function formatInventoryThreshold(totalQty, ratioSekunder = 1, ratioTersier = 1, unitNames = {}) {
  const qty = Number(totalQty) || 0;
  const uTersier = unitNames?.tersier || 'Dus';
  const uSekunder = unitNames?.sekunder || 'Strip';
  const uPrimer = unitNames?.primer || 'Tablet';

  if (qty <= 0) return `0 ${uPrimer}`;

  const rSekunder = Math.max(1, Number(ratioSekunder) || 1);
  const rTersier = Math.max(1, Number(ratioTersier) || 1);
  const totalSmallInBig = rSekunder * rTersier;

  // Jika 3 tingkat kemasan aktif
  if (rTersier > 1 && rSekunder > 1) {
    const big = Math.floor(qty / totalSmallInBig);
    const remBig = qty % totalSmallInBig;
    const mid = Math.floor(remBig / rSekunder);
    const small = remBig % rSekunder;

    const parts = [];
    if (big > 0) parts.push(`${big} ${uTersier}`);
    if (mid > 0) parts.push(`${mid} ${uSekunder}`);
    if (small > 0) parts.push(`${small} ${uPrimer}`);

    return parts.length > 0 ? parts.join(', ') : `0 ${uPrimer}`;
  }

  // Jika 2 tingkat kemasan aktif
  const ratio2 = rSekunder > 1 ? rSekunder : (rTersier > 1 ? rTersier : 1);
  if (ratio2 > 1) {
    const bigUnit = (unitNames?.tersier && (!unitNames?.sekunder || unitNames.sekunder === uPrimer || rTersier > 1))
      ? uTersier
      : (unitNames?.tersier || uSekunder);
    const mid = Math.floor(qty / ratio2);
    const small = qty % ratio2;

    const parts = [];
    if (mid > 0) parts.push(`${mid} ${bigUnit}`);
    if (small > 0) parts.push(`${small} ${uPrimer}`);

    return parts.length > 0 ? parts.join(', ') : `0 ${uPrimer}`;
  }

  // Satuan tunggal
  return `${qty} ${uPrimer}`;
}

