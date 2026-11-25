const fs = require('fs');
const files = [
  ['mobile/src/screens/DonationsScreen.tsx', true],
  ['mobile/src/screens/PartnersScreen.tsx', true],
  ['mobile/src/screens/AdminScreen.tsx', true],
  ['mobile/src/screens/ReportsScreen.tsx', false],
  ['mobile/src/screens/TopPartnersScreen.tsx', false],
];
for (const [p, local] of files) {
  let s = fs.readFileSync(p, 'utf8');
  if (local) {
    s = s.replace("import { defaultChurchId } from '../utils/church';", "import { defaultLocalChurchId } from '../utils/church';");
    s = s.replace(/defaultChurchId\(/g, 'defaultLocalChurchId(');
  }
  fs.writeFileSync(p, s, 'utf8');
  console.log((local ? 'local ' : 'kept ')+p);
}
