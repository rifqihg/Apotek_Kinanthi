import { formatInventoryThreshold } from '@/lib/utils';

export function calculateItemSubtotal(item) {
    const h = Number(item.harga_beli_satuan) || 0;
    const q = Number(item.qty_masuk) || 0;
    const d = Number(item.diskon_persen) || 0;
    return (h * (1 - (d / 100))) * q;
}

export function calculateFormSubtotal(items) {
    return (items || []).reduce((sum, item) => sum + calculateItemSubtotal(item), 0);
}

export function calculateFormGrandTotal(items, ppnPercent = 11) {
    const sub = calculateFormSubtotal(items);
    const ppn = sub * (Number(ppnPercent) / 100);
    return sub + ppn;
}

export function invoiceGrandTotal(group) {
    const ppnPercent = Number(group.ppn_persen) || 0;
    const subtotal = Number(group.subtotal) || 0;
    const ppn = subtotal * (ppnPercent / 100);
    return subtotal + ppn;
}

export function getPrimaryUnit(item) {
    if (item.no_multi_unit) {
        return item.satuan_input || 'Unit';
    }
    return item.new_medicine_satuan_kecil || 'Tablet';
}

export function getItemThresholdPreview(item) {
    if (item.no_multi_unit) {
        return `${item.min_stock_threshold || 0} ${getPrimaryUnit(item)}`;
    }
    const rSekunder = item.small_unit_skipped 
        ? (Number(item.unit_conversion_factor) || 1) 
        : (Number(item.small_to_mid_ratio) || 1);
    const rTersier = item.small_unit_skipped 
        ? 1 
        : (Number(item.mid_to_big_ratio) || 1);
    const midUnit = item.small_unit_skipped ? null : item.mid_unit;

    return formatInventoryThreshold(
        item.min_stock_threshold,
        rSekunder,
        rTersier,
        {
            tersier: item.satuan_input,
            sekunder: midUnit,
            primer: getPrimaryUnit(item),
        }
    );
}

export function normalizeItemUnits(item) {
    if (item.no_multi_unit) {
        item.unit_conversion_factor = 1;
        item.mid_to_big_ratio = 1;
        item.small_to_mid_ratio = 1;
        item.mid_unit = null;
        item.new_medicine_satuan_kecil = item.satuan_input;
    } else if (item.small_unit_skipped) {
        item.small_to_mid_ratio = item.unit_conversion_factor;
        item.mid_unit = null;
        item.mid_to_big_ratio = 1;
    } else {
        item.unit_conversion_factor = (Number(item.mid_to_big_ratio) || 1) * (Number(item.small_to_mid_ratio) || 1);
    }
}
