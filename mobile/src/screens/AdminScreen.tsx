import React, { useCallback, useEffect, useState } from 'react';
import { Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { api, ApiError } from '../api/client';
import type { Category, Cellule, Church, Partner, Role, User } from '../api/types';
import {
  Badge,
  Card,
  CheckboxField,
  colors,
  ConfirmModal,
  EmptyState,
  ErrorView,
  formatMoney,
  LoadingView,
  PrimaryButton,
  Screen,
  SectionTitle,
  SelectField,
  TextField,
} from '../components/ui';

type Section = 'churches' | 'cellules' | 'categories' | 'users';
type Editable = Church | Cellule | Category | User;

const sections: Array<{ key: Section; label: string }> = [
  { key: 'churches', label: 'Églises' },
  { key: 'cellules', label: 'Cellules' },
  { key: 'categories', label: 'Catégories' },
  { key: 'users', label: 'Utilisateurs' },
];

export function AdminScreen() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [section, setSection] = useState<Section>('churches');
  const [churches, setChurches] = useState<Church[]>([]);
  const [cellules, setCellules] = useState<Cellule[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [partners, setPartners] = useState<Partner[]>([]);
  const [roles, setRoles] = useState<Role[]>([]);
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Editable | null>(null);
  const [toDelete, setToDelete] = useState<Editable | null>(null);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');

  const [name, setName] = useState('');
  const [type, setType] = useState('LOCAL');
  const [parentId, setParentId] = useState('');
  const [churchId, setChurchId] = useState('');
  const [leaderId, setLeaderId] = useState('');
  const [color, setColor] = useState('#3B82F6');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [handleKc, setHandleKc] = useState('');
  const [password, setPassword] = useState('');
  const [userRoles, setUserRoles] = useState<string[]>([]);
  const [assignedChurchId, setAssignedChurchId] = useState('');
  const [active, setActive] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [churchData, celluleData, categoryData, userData, partnerData, roleData] = await Promise.all([
        api<{ data: Church[] }>('/churches'),
        api<{ data: Cellule[] }>('/cellules'),
        api<{ data: Category[] }>('/categories'),
        api<{ data: User[] }>('/users'),
        api<{ data: Partner[] }>('/partners'),
        api<{ data: Role[] }>('/roles'),
      ]);
      setChurches(churchData.data);
      setCellules(celluleData.data);
      setCategories(categoryData.data);
      setUsers(userData.data);
      setPartners(partnerData.data);
      setRoles(roleData.data);
      setChurchId((current) => current || churchData.data[0]?.id.toString() || '');
      setAssignedChurchId((current) => current || churchData.data[0]?.id.toString() || '');
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Impossible de charger l’administration.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const churchOptions = churches.map((church) => ({ label: church.name, value: String(church.id) }));
  const userOptions = users.map((user) => ({ label: user.name, value: String(user.id) }));

  const openForm = (record?: Editable) => {
    setFormError('');
    setEditing(record ?? null);
    setName(record ? ('name' in record ? record.name : '') : '');
    setType(record && 'type' in record ? String(record.type) : 'LOCAL');
    setParentId(record && 'parent_id' in record && record.parent_id ? String(record.parent_id) : '');
    setChurchId(record && 'church_id' in record ? String(record.church_id) : churchId);
    setLeaderId(record && 'leader_id' in record && record.leader_id ? String(record.leader_id) : '');
    setColor(record && 'color' in record ? record.color : '#3B82F6');
    setEmail(record && 'email' in record ? record.email : '');
    setPhone(record && 'phone' in record ? record.phone ?? '' : '');
    setHandleKc(record && 'handle_kc' in record ? record.handle_kc ?? '' : '');
    setPassword('');
    setUserRoles(record && 'roles' in record ? record.roles ?? [] : []);
    setAssignedChurchId(record && 'assigned_church_id' in record && record.assigned_church_id
      ? String(record.assigned_church_id)
      : assignedChurchId);
    setActive(record && 'is_active' in record ? Boolean(record.is_active) : true);
    setFormOpen(true);
  };

  const endpointFor = (target: Section): string => `/${target}`;

  const payloadFor = (): Record<string, unknown> => {
    if (section === 'churches') {
      return {
        name,
        type,
        parent_id: parentId ? Number(parentId) : null,
        manager_id: leaderId ? Number(leaderId) : null,
      };
    }
    if (section === 'cellules') {
      return { name, church_id: Number(churchId), leader_id: leaderId ? Number(leaderId) : null };
    }
    if (section === 'categories') {
      return { name, color };
    }
    return {
      name,
      email,
      phone: phone || null,
      handle_kc: handleKc || null,
      ...(password ? { password } : {}),
      roles: userRoles,
      assigned_church_id: Number(assignedChurchId),
      is_active: active,
    };
  };

  const save = async () => {
    setSaving(true);
    setFormError('');
    try {
      const endpoint = endpointFor(section);
      const uri = editing ? `${endpoint}/${editing.id}` : endpoint;
      await api(uri, { method: editing ? 'PATCH' : 'POST', body: payloadFor() });
      setFormOpen(false);
      await load();
    } catch (caught) {
      setFormError(caught instanceof ApiError ? caught.message : 'Enregistrement impossible.');
    } finally {
      setSaving(false);
    }
  };

  const remove = async () => {
    if (!toDelete) {
      return;
    }
    try {
      await api(`${endpointFor(section)}/${toDelete.id}`, { method: 'DELETE' });
      setToDelete(null);
      await load();
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Suppression impossible.');
      setToDelete(null);
    }
  };

  const toggleRole = (role: string, checked: boolean) => {
    setUserRoles((current) => checked ? [...current, role] : current.filter((item) => item !== role));
  };

  return (
    <Screen
      title="Administration"
      subtitle="Églises, cellules, catégories et comptes"
      actions={<PrimaryButton title="Ajouter" onPress={() => openForm()} />}
    >
      <ScrollView horizontal style={styles.tabs}>
        {sections.map((item) => (
          <Pressable
            key={item.key}
            style={[styles.tab, section === item.key && styles.tabActive]}
            onPress={() => setSection(item.key)}
          >
            <Text style={section === item.key ? styles.tabTextActive : styles.tabText}>{item.label}</Text>
          </Pressable>
        ))}
      </ScrollView>

      {loading ? <LoadingView /> : null}
      {!loading && error ? <ErrorView message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && section === 'churches' ? (
        churches.length === 0 ? <EmptyState message="Aucune église." /> : churches.map((church) => (
          <Card key={church.id}>
            <View style={styles.row}>
              <Text style={styles.itemTitle}>{church.name}</Text>
              <Badge text={String(church.type)} />
            </View>
            <Text style={styles.meta}>Total dons : {formatMoney(church.total_donations ?? 0)}</Text>
            <View style={styles.actions}>
              <Pressable onPress={() => openForm(church)}><Text style={styles.edit}>Modifier</Text></Pressable>
              <Pressable onPress={() => setToDelete(church)}><Text style={styles.delete}>Supprimer</Text></Pressable>
            </View>
          </Card>
        ))
      ) : null}

      {!loading && !error && section === 'cellules' ? (
        cellules.length === 0 ? <EmptyState message="Aucune cellule." /> : cellules.map((cellule) => (
          <Card key={cellule.id}>
            <Text style={styles.itemTitle}>{cellule.name}</Text>
            <Text style={styles.meta}>{cellule.church ?? '—'}</Text>
            <Text style={styles.meta}>Responsable : {cellule.leader ?? '—'}</Text>
            <View style={styles.actions}>
              <Pressable onPress={() => openForm(cellule)}><Text style={styles.edit}>Modifier</Text></Pressable>
              <Pressable onPress={() => setToDelete(cellule)}><Text style={styles.delete}>Supprimer</Text></Pressable>
            </View>
          </Card>
        ))
      ) : null}

      {!loading && !error && section === 'categories' ? (
        categories.length === 0 ? <EmptyState message="Aucune catégorie." /> : categories.map((category) => (
          <Card key={category.id}>
            <View style={styles.row}>
              <View style={[styles.color, { backgroundColor: category.color }]} />
              <Text style={styles.itemTitle}>{category.name}</Text>
            </View>
            <View style={styles.actions}>
              <Pressable onPress={() => openForm(category)}><Text style={styles.edit}>Modifier</Text></Pressable>
              <Pressable onPress={() => setToDelete(category)}><Text style={styles.delete}>Supprimer</Text></Pressable>
            </View>
          </Card>
        ))
      ) : null}

      {!loading && !error && section === 'users' ? (
        users.length === 0 ? <EmptyState message="Aucun compte." /> : users.map((user) => (
          <Card key={user.id}>
            <View style={styles.row}>
              <Text style={styles.itemTitle}>{user.name}</Text>
              <Badge text={user.role ?? '—'} color={user.is_active ? colors.green : colors.red} />
            </View>
            <Text style={styles.meta}>{user.email}</Text>
            <Text style={styles.meta}>{user.assigned_church ?? '—'}</Text>
            <View style={styles.actions}>
              <Pressable onPress={() => openForm(user)}><Text style={styles.edit}>Modifier</Text></Pressable>
              <Pressable onPress={() => setToDelete(user)}><Text style={styles.delete}>Supprimer</Text></Pressable>
            </View>
          </Card>
        ))
      ) : null}

      <Modal visible={formOpen} animationType="slide" onRequestClose={() => setFormOpen(false)}>
        <Screen
          title={editing ? 'Modifier' : `Nouveau — ${sections.find((item) => item.key === section)?.label}`}
          actions={<Pressable onPress={() => setFormOpen(false)}><Text style={styles.edit}>Fermer</Text></Pressable>}
        >
          <Card>
            {section !== 'categories' ? (
              <TextField label={section === 'users' ? 'Nom complet' : 'Nom'} value={name} onChangeText={setName} />
            ) : (
              <TextField label="Nom de la catégorie" value={name} onChangeText={setName} />
            )}

            {section === 'churches' ? (
              <>
                <SelectField
                  label="Type"
                  value={type}
                  options={[
                    { label: 'Groupe', value: 'GROUP' },
                    { label: 'Sous-groupe', value: 'SUB_GROUP' },
                    { label: 'Locale', value: 'LOCAL' },
                  ]}
                  onChange={setType}
                />
                <SelectField
                  label="Église parente"
                  value={parentId}
                  options={[{ label: 'Aucune', value: '' }, ...churchOptions]}
                  onChange={setParentId}
                />
                <SelectField
                  label="Responsable"
                  value={leaderId}
                  options={[{ label: 'Aucun', value: '' }, ...userOptions]}
                  onChange={setLeaderId}
                />
              </>
            ) : null}

            {section === 'cellules' ? (
              <>
                <SelectField label="Église" value={churchId} options={churchOptions} onChange={setChurchId} />
                <SelectField
                  label="Responsable (partenaire)"
                  value={leaderId}
                  options={[{ label: 'Aucun', value: '' }]}
                  onChange={setLeaderId}
                />
              </>
            ) : null}

            {section === 'categories' ? (
              <TextField label="Couleur" value={color} onChangeText={setColor} placeholder="#3B82F6" />
            ) : null}

            {section === 'users' ? (
              <>
                <TextField label="Adresse email" value={email} onChangeText={setEmail} keyboardType="email-address" />
                <TextField label="Téléphone" value={phone} onChangeText={setPhone} keyboardType="phone-pad" />
                <TextField label="Identifiant KingsChat (handle KC)" value={handleKc} onChangeText={setHandleKc} />
                <TextField label="Mot de passe" value={password} onChangeText={setPassword} secureTextEntry />
                <SectionTitle>Rôles</SectionTitle>
                {roles.map((role) => (
                  <CheckboxField
                    key={role.name}
                    label={role.label}
                    checked={userRoles.includes(role.name)}
                    onChange={(checked) => toggleRole(role.name, checked)}
                  />
                ))}
                <SelectField label="Église assignée" value={assignedChurchId} options={churchOptions} onChange={setAssignedChurchId} />
                <CheckboxField label="Compte actif" checked={active} onChange={setActive} />
              </>
            ) : null}

            {formError ? <Text style={styles.error}>{formError}</Text> : null}
            <PrimaryButton title="Enregistrer" onPress={() => void save()} loading={saving} disabled={!name} />
          </Card>
        </Screen>
      </Modal>

      <ConfirmModal
        visible={toDelete !== null}
        title="Confirmer la suppression"
        message="Cet élément sera supprimé définitivement."
        confirmLabel="Supprimer"
        onCancel={() => setToDelete(null)}
        onConfirm={() => void remove()}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  tabs: { marginBottom: 12 },
  tab: { paddingVertical: 10, paddingHorizontal: 16, borderRadius: 999, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border, marginRight: 8 },
  tabActive: { backgroundColor: colors.blue, borderColor: colors.blue },
  tabText: { color: colors.blue, fontWeight: '600' },
  tabTextActive: { color: '#FFFFFF', fontWeight: '700' },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  itemTitle: { fontSize: 16, fontWeight: '700', color: colors.ink, flex: 1 },
  meta: { fontSize: 13, color: colors.muted, marginTop: 5 },
  actions: { flexDirection: 'row', gap: 18, marginTop: 12 },
  edit: { color: colors.blue, fontWeight: '700', fontSize: 13 },
  delete: { color: colors.red, fontWeight: '700', fontSize: 13 },
  color: { width: 14, height: 14, borderRadius: 7 },
  error: { color: colors.red, marginBottom: 12 },
});
