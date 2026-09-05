
const fs = require('fs');
let code = fs.readFileSync('app/Http/Controllers/Control/AdminController.php', 'utf8');

// Step 2: Fix redirects
code = code.replace(/control\.gudang\.opname\.show/g, 'control.admin.stock-opname.index');
code = code.replace(/control\.gudang\.opname/g, 'control.admin.stock-opname.index');
code = code.replace(/control\.gudang\.inventory/g, 'control.admin.inventory.index');
code = code.replace(/control\.gudang\.confirmations/g, 'control.admin.confirmations.index');
code = code.replace(/control\.gudang\.destructions/g, 'control.admin.destructions.index');
code = code.replace(/control\.gudang\.returns/g, 'control.admin.returns.index');
code = code.replace(/control\.gudang\.dashboard/g, 'control.admin.dashboard');
code = code.replace(/control\.gudang\.[a-zA-Z0-9\-\.]+/g, 'control.admin.dashboard');

// Step 3: Add comments to dead code
const methods = [
    'gudangStockOpname', 'gudangCreateOpname', 'gudangShowOpname', 
    'gudangUpdateOpnameCounts', 'gudangFinalizeOpname', 'gudangCancelOpname', 
    'gudangExportOpnameSheet'
];

methods.forEach(method => {
    const search = 'public function ' + method;
    const replacement = '// NOTE: Method ini adalah hasil migrasi dari GudangController.\n    // Tidak aktif — route control.admin.stock-opname.* menggunakan stockOpnameIndex() dari Prompt 7.\n    // Dibiarkan sebagai referensi historis.\n    public function ' + method;
    code = code.replace(search, replacement);
});

fs.writeFileSync('app/Http/Controllers/Control/AdminController.php', code);
console.log('AdminController updated successfully.');

