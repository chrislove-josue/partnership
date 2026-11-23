const fs = require('fs');
const p = 'mobile/src/screens/ReportsScreen.tsx';
let s = fs.readFileSync(p, 'utf8');
const re = /                  <Text style=\{styles\.listLabel\}>\{category\.name\}<\/Text>\n                  <Text style=\{styles\.listValue\}>\{formatMoney\(category\.amount\)\}<\/Text>/;
const neu = `                  <View style={{ flex: 1 }}>
                    <Text style={styles.listLabel}>{category.name}</Text>
                    {category.group_label ? <Text style={styles.meta}>{category.group_label}</Text> : null}
                  </View>
                  <Text style={styles.listValue}>{formatMoney(category.amount)}</Text>`;
if (!re.test(s)) { console.error('pattern not found'); process.exit(1); }
s = s.replace(re, neu);
fs.writeFileSync(p, s, 'utf8');
console.log('done');
