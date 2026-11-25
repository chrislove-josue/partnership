import React, { useCallback, useEffect, useState } from 'react';
import { Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { api, getErrorMessage } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { defaultChurchId } from '../utils/church';
import { filterBy } from '../utils/search';
import type { Category, Cellule, Church, Partner, RoleOption, User } from '../api/types';
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
  SecondaryButton,
  SectionTitle,
  SelectField,
  TextField,
} from '../components/ui';

type Section = 'churches' | 'cellules' | 'categories' | 'users' | 'pasteurs';
type Editable = Church | Cellule | Category | User;

const ALL_SECTIONS: Array<{ key: Section; label: string }> = [
  { key: 'churches', label: 'Églises' },
  { key: 'cellules', label: 'Cellules' },
  { key: 'categories', label: 'Catégories' },
  { key: 'users', label: 'Utilisateurs' },
  { key: 'pasteurs', label: 'Pasteurs' },
];

function availableSections(user: User | null): Array<{ key: Section; label: string }> {
  if (!user) return [];
  const role = user.role ?? '';
  const roles = user.roles ?? [];
  const isSuper = role === 'SUPER_ADMIN' || roles.includes('SUPER_ADMIN');
  const isPastor = ['GROUP_PASTOR', 'SUBGROUP_PASTOR', 'LOCAL_PASTOR'].some(
    (r) => role === r || roles.includes(r));
  const isStaff = role === 'STAFF' || roles.includes('STAFF');

  if (isSuper) return ALL_SECTIONS;
  if (isPastor) return ALL_SECTIONS;
  if (isStaff) return ALL_SECTIONS.filter((s) => s.key === 'cellules' || s.key === 'categories');
  return [];
}

const PASTOR_ROLES = ['GROUP_PASTOR', 'SUBGROUP_PASTOR', 'LOCAL_PASTOR'];
const CHURCH_STATUS_MANAGERS = ['GROUP_PASTOR', 'SUBGROUP_PASTOR'];

function hasRole(user: User, role: string): boolean {
  return user.role === role || (user.roles ?? []).includes(role);
}

function isPastor(user: User): boolean {
  return PASTOR_ROLES.some((role) => hasRole(user, role));
}

export function AdminScreen() {
  const { user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [section, setSection] = useState<Section>('cellules');
  const [churches, setChurches] = useState<Church[]>([]);
  const [cellules, setCellules] = useState<Cellule[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [partners, setPartners] = useState<Partner[]>([]);
  const [roles, setRoles] = useState<RoleOption[]>([]);
  const [search, setSearch] = useState('');
  const [churchTypeFilter, setChurchTypeFilter] = useState('');
  const [celluleChurchFilter, setCelluleChurchFilter] = useState('');
  const [userRoleFilter, setUserRoleFilter] = useState('');
  const [userActiveFilter, setUserActiveFilter] = useState('');
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Editable | null>(null);
  const [toDelete, setToDelete] = useState<Editable | null>(null);
  const [toToggleStatus, setToToggleStatus] = useState<Church | null>(null);
  const [toggling, setToggling] = useState(false);
  const [toggleError, setToggleError] = useState('');
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');

  const [name, setName] = useState('');
  const [type, setType] = useState('LOCAL');
  const [parentId, setParentId] = useState('');
  const [churchId, setChurchId] = useState('');
  const [leaderId, setLeaderId] = useState('');
  const [color, setColor] = useState('#3B82F6');
  const [categoryGroup, setCategoryGroup] = useState('local_partnership');
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
      const [churchData, celluleData, categoryData, userData, partnerData, roleData] = await Promise.allSettled([
        api<{ data: Church[] }>('/churches', ),
        api<{ data: Cellule[] }>('/cellules', ),
        api<{ data: Category[] }>('/categories', ),
        api<{ data: User[] }>('/users', ),
        api<{ data: Partner[] }>('/partners', ),
        api<{ data: RoleOption[] }>('/roles', ),
      ]);

      if (churchData.status === 'fulfilled') {
        setChurches(churchData.value.data);
        setChurchId((current) => current || defaultChurchId(churchData.value.data, user?.assigned_church_id));
        setAssignedChurchId((current) => current || defaultChurchId(churchData.value.data, user?.assigned_church_id));
      }
      if (celluleData.status === 'fulfilled') setCellules(celluleData.value.data);
      if (categoryData.status === 'fulfilled') setCategories(categoryData.value.data);
      if (userData.status === 'fulfilled') setUsers(userData.value.data);
      if (partnerData.status === 'fulfilled') setPartners(partnerData.value.data);
      if (roleData.status === 'fulfilled') setRoles(roleData.value.data);
    } catch (caught) {
      setError(getErrorMessage(caught, 'Impossible de charger l’administration.'));
    } finally {
      setLoading(false);
    }
  }, [user?.assigned_church_id]);

  useEffect(() => {
    void load();
  }, [load]);

  const churchOptions = churches.map((church) => ({ label: church.name, value: String(church.id) }));
  const userOptions = users.map((user) => ({ label: user.name, value: String(user.id) }));
  const partnerOptions = partners.map((partner) => ({
    label: partner.full_name ?? `${partner.first_name} ${partner.last_name}`,
    value: String(partner.id),
  }));

  const canDeleteChurches = user ? hasRole(user, 'SUPER_ADMIN') : false;
  const canManageChurchStatus = user
    ? Boolean(user.assigned_church_id)
      && CHURCH_STATUS_MANAGERS.some((role) => hasRole(user, role))
    : false;
  const sections = availableSections(user);
  const churchesById = new Map(churches.map((church) => [church.id, church]));

  /** Vrai si l'église cible est l'église assignée ou l'une de ses descendantes. */
  const isAssignedOrDescendant = (church: Church): boolean => {
    if (!user?.assigned_church_id) {
      return false;
    }

    let current: Church | undefined = church;
    const visited = new Set<number>();
    while (current && !visited.has(current.id)) {
      if (current.id === user.assigned_church_id) {
        return true;
      }
      visited.add(current.id);
      current = current.parent_id ? churchesById.get(current.parent_id) : undefined;
    }
    return false;
  };

  const canToggleChurch = (church: Church): boolean =>
    canManageChurchStatus && isAssignedOrDescendant(church);

  const filteredChurches = filterBy(churches, search, [
    (church) => church.name,
    (church) => church.type,
    (church) => church.parent,
  ]).filter((church) => !churchTypeFilter || church.type === churchTypeFilter);

  const filteredCellules = filterBy(cellules, search, [
    (cellule) => cellule.name,
    (cellule) => cellule.church,
    (cellule) => cellule.leader,
  ]).filter((cellule) => !celluleChurchFilter || cellule.church_id === Number(celluleChurchFilter));

  const filteredCategories = filterBy(categories, search, [(category) => category.name]);

  const filteredUsers = filterBy(users, search, [
    (user) => user.name,
    (user) => user.email,
    (user) => user.phone,
    (user) => user.handle_kc,
    (user) => user.role,
    (user) => user.roles?.join(' '),
    (user) => typeof user.assigned_church === 'string'
      ? user.assigned_church
      : user.assigned_church?.name,
  ])
    .filter((user) => !userRoleFilter || hasRole(user, userRoleFilter))
    .filter((user) => !userActiveFilter || (user.is_active ? '1' : '0') === userActiveFilter);

  const filteredPastors = filteredUsers.filter(isPastor);
  const userSection = section === 'users' || section === 'pasteurs';

  const resetFilters = () => {
    setSearch('');
    setChurchTypeFilter('');
    setCelluleChurchFilter('');
    setUserRoleFilter('');
    setUserActiveFilter('');
  };

  const openForm = (record?: Editable) => {
    setFormError('');
    setEditing(record ?? null);
    setName(record ? ('name' in record ? record.name : '') : '');
    setType(record && 'type' in record ? String(record.type) : user?.role === 'GROUP_PASTOR' ? 'SUB_GROUP' : user?.role === 'SUBGROUP_PASTOR' ? 'LOCAL' : 'GROUP');
    setParentId(record && 'parent_id' in record && record.parent_id ? String(record.parent_id) : user?.role === 'SUPER_ADMIN' ? '' : user?.assigned_church_id ? String(user.assigned_church_id) : '');
    setChurchId(record && 'church_id' in record ? String(record.church_id) : defaultChurchId(churches, user?.assigned_church_id));
    setLeaderId(record && 'leader_id' in record && record.leader_id
      ? String(record.leader_id)
      : record && 'manager_id' in record && record.manager_id ? String(record.manager_id) : '');
    setColor(record && 'color' in record ? record.color : '#3B82F6');
    setCategoryGroup(record && 'group' in record && record.group ? String(record.group) : 'local_partnership');
    setEmail(record && 'email' in record ? record.email : '');
    setPhone(record && 'phone' in record ? record.phone ?? '' : '');
    setHandleKc(record && 'handle_kc' in record ? record.handle_kc ?? '' : '');
    setPassword('');
    setUserRoles(record && 'roles' in record
      ? record.roles ?? []
      : section === 'pasteurs' ? ['LOCAL_PASTOR'] : []);
    setAssignedChurchId(record && 'assigned_church_id' in record && record.assigned_church_id
      ? String(record.assigned_church_id)
      : assignedChurchId);
    setActive(record && 'is_active' in record ? Boolean(record.is_active) : true);
    setFormOpen(true);
  };

  const endpointFor = (target: Section): string =>
    target === 'pasteurs' ? '/users' : `/${target}`;

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
      return { name, color, group: categoryGroup };
    }
    // Les onglets Utilisateurs et Pasteurs utilisent la même ressource.
    return {
      name,
      email,
      phone: phone || null,
      handle_kc: handleKc || null,
      ...(password ? { password } : {}),
      roles: userRoles,
      assigned_church_id: assignedChurchId ? Number(assignedChurchId) : null,
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
      setFormError(getErrorMessage(caught, 'Enregistrement impossible.'));
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
      setError(getErrorMessage(caught, 'Suppression impossible.'));
      setToDelete(null);
    }
  };

  const toggleChurchStatus = async () => {
    if (!toToggleStatus || toggling) {
      return;
    }

    const churchId = toToggleStatus.id;
    const action = toToggleStatus.is_active === false ? 'activate' : 'deactivate';
    const fallback = action === 'activate'
      ? 'Impossible de réactiver l’église.'
      : 'Impossible de désactiver l’église.';

    setToggling(true);
    setToggleError('');
    try {
      const response = await api<{ data: Church }>(`/churches/${churchId}/${action}`, {
        method: 'POST',
      });
      setChurches((current) => current.map((church) =>
        church.id === response.data.id ? response.data : church,
      ));
      setToToggleStatus(null);
      await load();
    } catch (caught) {
      setToggleError(getErrorMessage(caught, fallback));
    } finally {
      setToggling(false);
    }
  };

  const toggleRole = (role: string, checked: boolean) => {
    setUserRoles((current) => checked ? [...current, role] : current.filter((item) => item !== role));
  };

  return (
    <Screen
      title="Administration"
      subtitle="Églises, cellules, catégories, comptes et pasteurs"
      actions={<PrimaryButton title="Ajouter" onPress={() => openForm()} />}
    >
      <ScrollView horizontal style={styles.tabs} contentContainerStyle={{ alignItems: 'center' }}>
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

      <Card>
        <SectionTitle>Recherche et filtres</SectionTitle>
        <TextField label="Rechercher" value={search} onChangeText={setSearch} placeholder="Nom, email, téléphone, église" />
        {section === 'churches' ? (
          <SelectField
            label="Type d’église"
            value={churchTypeFilter}
            options={[
              { label: 'Tous les types', value: '' },
              { label: 'Groupe', value: 'GROUP' },
              { label: 'Sous-groupe', value: 'SUB_GROUP' },
              { label: 'Locale', value: 'LOCAL' },
            ]}
            onChange={setChurchTypeFilter}
          />
        ) : null}
        {section === 'cellules' ? (
          <SelectField
            label="Église"
            value={celluleChurchFilter}
            options={[{ label: 'Toutes les églises', value: '' }, ...churchOptions]}
            onChange={setCelluleChurchFilter}
            searchable
          />
        ) : null}
        {userSection ? (
          <>
            <SelectField
              label="Rôle"
              value={userRoleFilter}
              options={[
                { label: 'Tous les rôles', value: '' },
                ...roles.map((role) => ({ label: role.label, value: role.name })),
              ]}
              onChange={setUserRoleFilter}
              searchable
            />
            <SelectField
              label="Statut"
              value={userActiveFilter}
              options={[
                { label: 'Tous les comptes', value: '' },
                { label: 'Actifs', value: '1' },
                { label: 'Inactifs', value: '0' },
              ]}
              onChange={setUserActiveFilter}
            />
          </>
        ) : null}
        <SecondaryButton title="Réinitialiser les filtres" onPress={resetFilters} />
      </Card>

      {loading ? <LoadingView /> : null}
      {!loading && error ? <ErrorView message={error} onRetry={() => void load()} /> : null}

      {!loading && !error && section === 'churches' ? (
        filteredChurches.length === 0 ? <EmptyState message="Aucune église trouvée." /> : filteredChurches.map((church) => (
          <Card key={church.id}>
            <View style={styles.row}>
              <Text style={styles.itemTitle}>{church.name}</Text>
              <View style={styles.badges}>
                <Badge text={String(church.type)} />
                {church.is_active === false ? <Badge text="Inactive" color={colors.red} /> : null}
              </View>
            </View>
            <Text style={styles.meta}>Total dons : {formatMoney(church.total_donations ?? 0)}</Text>
            {church.parent_name ? <Text style={styles.meta}>Parente : {church.parent_name}</Text> : null}
            {church.hierarchy_path ? <Text style={styles.meta}>{church.hierarchy_path}</Text> : null}
            <View style={styles.actions}>
              <Pressable onPress={() => openForm(church)}><Text style={styles.edit}>Modifier</Text></Pressable>
              {canDeleteChurches ? (
                <Pressable onPress={() => setToDelete(church)}><Text style={styles.delete}>Supprimer</Text></Pressable>
              ) : null}
              {canToggleChurch(church) ? (
                <Pressable onPress={() => {
                  setToggleError('');
                  setToToggleStatus(church);
                }}>
                  <Text style={church.is_active === false ? styles.activate : styles.delete}>
                    {church.is_active === false ? 'Réactiver' : 'Désactiver'}
                  </Text>
                </Pressable>
              ) : null}
            </View>
          </Card>
        ))
      ) : null}

      {!loading && !error && section === 'cellules' ? (
        filteredCellules.length === 0 ? <EmptyState message="Aucune cellule trouvée." /> : filteredCellules.map((cellule) => (
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
        filteredCategories.length === 0 ? <EmptyState message="Aucune catégorie trouvée." /> : filteredCategories.map((category) => (
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
        filteredUsers.length === 0 ? <EmptyState message="Aucun compte trouvé." /> : filteredUsers.map((user) => (
          <Card key={user.id}>
            <View style={styles.row}>
              <Text style={styles.itemTitle}>{user.name}</Text>
              <Badge text={user.role ?? '—'} color={user.is_active ? colors.green : colors.red} />
            </View>
            <Text style={styles.meta}>{user.email}</Text>
            <Text style={styles.meta}>
              {typeof user.assigned_church === 'string'
                ? user.assigned_church
                : user.assigned_church?.name ?? '—'}
            </Text>
            <View style={styles.actions}>
              <Pressable onPress={() => openForm(user)}><Text style={styles.edit}>Modifier</Text></Pressable>
              <Pressable onPress={() => setToDelete(user)}><Text style={styles.delete}>Supprimer</Text></Pressable>
            </View>
          </Card>
        ))
      ) : null}

      {!loading && !error && section === 'pasteurs' ? (
        filteredPastors.length === 0 ? <EmptyState message="Aucun pasteur trouvé." /> : filteredPastors.map((user) => (
          <Card key={user.id}>
            <View style={styles.row}>
              <Text style={styles.itemTitle}>{user.name}</Text>
              <Badge text={user.role ?? '—'} color={user.is_active ? colors.green : colors.red} />
            </View>
            <Text style={styles.meta}>{user.email}</Text>
            <Text style={styles.meta}>
              {typeof user.assigned_church === 'string'
                ? user.assigned_church
                : user.assigned_church?.name ?? '—'}
            </Text>
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
              <TextField label={userSection ? 'Nom complet' : 'Nom'} value={name} onChangeText={setName} />
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
                  onChange={(value) => {
                    setType(value);
                    if (value === 'GROUP') setParentId('');
                  }}
                />
                {type !== 'GROUP' ? (
                  <SelectField
                    label="Église parente (groupe ou sous-groupe)"
                    value={parentId}
                    options={[
                      { label: 'Aucune', value: '' },
                      ...churches
                        .filter((church) => church.type === 'GROUP' || church.type === 'SUB_GROUP')
                        .filter((church) => !editing || church.id !== editing.id)
                        .map((church) => ({ label: church.name, value: String(church.id) })),
                    ]}
                    onChange={setParentId}
                    searchable
                  />
                ) : null}
                <SelectField
                  label="Responsable"
                  value={leaderId}
                  options={[{ label: 'Aucun', value: '' }, ...userOptions]}
                  onChange={setLeaderId}
                  searchable
                />
              </>
            ) : null}

            {section === 'cellules' ? (
              <>
                <SelectField label="Église" value={churchId} options={churchOptions} onChange={setChurchId} searchable />
                <SelectField
                  label="Responsable (partenaire)"
                  value={leaderId}
                  options={[{ label: 'Aucun', value: '' }, ...partnerOptions]}
                  onChange={setLeaderId}
                  searchable
                />
              </>
            ) : null}

            {section === 'categories' ? (
              <>
                <TextField label="Couleur" value={color} onChangeText={setColor} placeholder="#3B82F6" />
                <SelectField
                  label="Famille"
                  value={categoryGroup}
                  options={[
                    { label: 'Finances de l\'église', value: 'church_finances' },
                    { label: 'Partenariat du ministère', value: 'ministry_partnership' },
                    { label: 'Partenariat local', value: 'local_partnership' },
                  ]}
                  onChange={setCategoryGroup}
                />
              </>
            ) : null}

            {userSection ? (
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
                <SelectField label="Église assignée" value={assignedChurchId} options={churchOptions} onChange={setAssignedChurchId} searchable />
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

      <ConfirmModal
        visible={toToggleStatus !== null}
        title={toToggleStatus?.is_active === false ? 'Réactiver l’église' : 'Désactiver l’église'}
        message={toToggleStatus?.is_active === false
          ? `L’église « ${toToggleStatus.name} » sera réactivée.`
          : `L’église « ${toToggleStatus?.name ?? ''} » sera désactivée. Seule cette église est concernée.`}
        confirmLabel={toToggleStatus?.is_active === false ? 'Réactiver' : 'Désactiver'}
        onCancel={() => {
          setToToggleStatus(null);
          setToggleError('');
        }}
        onConfirm={() => void toggleChurchStatus()}
      >
        {toggleError ? <Text style={styles.error}>{toggleError}</Text> : null}
      </ConfirmModal>
    </Screen>
  );
}

const styles = StyleSheet.create({
  tabs: { marginBottom: 12 },
  tab: { paddingVertical: 10, paddingHorizontal: 16, borderRadius: 999, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border, marginRight: 8, alignSelf: 'flex-start' },
  tabActive: { backgroundColor: colors.blue, borderColor: colors.blue },
  tabText: { color: colors.blue, fontWeight: '600' },
  tabTextActive: { color: '#FFFFFF', fontWeight: '700' },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  badges: { flexDirection: 'row', alignItems: 'center', gap: 6, flexShrink: 0 },
  itemTitle: { fontSize: 16, fontWeight: '700', color: colors.ink, flex: 1 },
  meta: { fontSize: 13, color: colors.muted, marginTop: 5 },
  actions: { flexDirection: 'row', gap: 18, marginTop: 12 },
  edit: { color: colors.blue, fontWeight: '700', fontSize: 13 },
  delete: { color: colors.red, fontWeight: '700', fontSize: 13 },
  activate: { color: colors.green, fontWeight: '700', fontSize: 13 },
  color: { width: 14, height: 14, borderRadius: 7 },
  error: { color: colors.red, marginBottom: 12 },
});
