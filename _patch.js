const fs = require('fs');
const p = 'mobile/src/screens/ReportsScreen.tsx';
let s = fs.readFileSync(p, 'utf8');
const old = `               {filteredReport.categories.map((category) => (
                 <View key={category.name} style={styles.listRow}>
                   <View style={[styles.color, { backgroundColor: category.color }]} />
                   <Text style={styles.listLabel}>{category.name}</Text>
                   <Text style={styles.listValue}>{formatMoney(category.amount)}</Text>
                 </View>
               ))}`;
const neu = `               {filteredReport.categories.map((category) => (
                 <View key={category.name} style={styles.listRow}>
                   <View style={[styles.color, { backgroundColor: category.color }]} />
                   <View style={{ flex: 1 }}>
                     <Text style={styles.listLabel}>{category.name}</Text>
                     {category.group_label ? <Text style={styles.meta}>{category.group_label}</Text> : null}
                   </View>
                   <Text style={styles.listValue}>{formatMoney(category.amount)}</Text>
                 </View>
               ))}`;
if (!s.includes(old)) { console.error('old not found'); process.exit(1); }
s = s.replace(old, neu);
fs.writeFileSync(p, s, 'utf8');
console.log('done');
