
const fs = require('fs');
const path = require('path');

const dir = 'resources/js/Pages/Control/Gudang';
const files = fs.readdirSync(dir).filter(f => f.endsWith('.vue'));

files.forEach(file => {
    const filePath = path.join(dir, file);
    let code = fs.readFileSync(filePath, 'utf8');
    
    // Replace all control.gudang. with control.admin.
    code = code.replace(/control\.gudang\./g, 'control.admin.');
    
    fs.writeFileSync(filePath, code);
    console.log('Fixed ' + file);
});

