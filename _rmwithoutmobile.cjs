const fs = require('fs');
const p = 'mobile/src/screens/ReportsScreen.tsx';
let s = fs.readFileSync(p, 'utf8');
const old = `          {showSection('without') && filteredReport.partnersWithoutDonation.length > 0 ? (
            <Card>
              <SectionTitle>Sans don sur la période</SectionTitle>
              {filteredReport.partnersWithoutDonation.map((partner) => (
                <Text key={partner.name} style={styles.meta}>{partner.name}</Text>
              ))}
            </Card>
          ) : null}

          {showSection('donations') ? (`;
const neu = `          {showSection('donations') ? (`;
if (!s.includes(old)) { console.error('NOT FOUND'); process.exit(1); }
s = s.replace(old, neu);
fs.writeFileSync(p, s, 'utf8');
console.log('done');
