const fs = require('fs');
const files = [
  'app/Filament/Pages/LoginHistory.php',
  'app/Filament/Pages/PartnershipReport.php',
  'app/Filament/Resources/CategoryDons/Schemas/CategoryDonForm.php',
  'app/Filament/Resources/Cellules/Schemas/CelluleForm.php',
  'app/Filament/Resources/Churches/Schemas/ChurchForm.php',
  'app/Filament/Resources/Donations/Schemas/DonationForm.php',
  'app/Filament/Resources/Notifications/Schemas/NotificationForm.php',
  'app/Filament/Resources/Partners/Schemas/PartnerForm.php',
  'app/Filament/Resources/Users/Schemas/UserForm.php',
];
let total = 0;
for (const f of files) {
  let s = fs.readFileSync(f, 'utf8');
  const before = (s.match(/Select::make\(/g) || []).length;
  s = s.replace(/(Select::make\([^)]*\))/g, '$1->selectablePlaceholder(false)');
  const after = (s.match(/selectablePlaceholder\(false\)/g) || []).length;
  fs.writeFileSync(f, s, 'utf8');
  total += after;
  console.log(f, 'selects:', before, '-> placeholders:', after);
}
console.log('total placeholders added:', total);
