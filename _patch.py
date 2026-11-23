import re
p='mobile/src/screens/ReportsScreen.tsx'
s=open(p,encoding='utf-8').read()
old="""               {filteredReport.categories.map((category) => (
                 <View key={category.name} style={styles.listRow}>
                   <View style={[styles.color, { backgroundColor: category.color }]} />
                   <Text style={styles.listLabel}>{category.name}</Text>
                   <Text style={styles.listValue}>{formatMoney(category.amount)}</Text>
                 </View>
               ))}"""
new="""               {filteredReport.categories.map((category) => (
                 <View key={category.name} style={styles.listRow}>
                   <View style={[styles.color, { backgroundColor: category.color }]} />
                   <View style={{ flex: 1 }}>
                     <Text style={styles.listLabel}>{category.name}</Text>
                     {category.group_label ? <Text style={styles.meta}>{category.group_label}</Text> : null}
                   </View>
                   <Text style={styles.listValue}>{formatMoney(category.amount)}</Text>
                 </View>
               ))}"""
assert old in s, 'old not found'
s=s.replace(old,new)
open(p,'w',encoding='utf-8').write(s)
print('done')
