import React, { useCallback, useEffect, useState } from 'react';
import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { api, ApiError, invalidateCache } from '../api/client';
import type { Cellule, Church, Partner } from '../api/types';
import {
  Badge,
  Card,
  colors,
  ConfirmModal,
  EmptyState,
  ErrorView,
  formatDate,
  formatMoney,
  LoadingView,
  PrimaryButton,
  Screen,
  SelectField,
  TextField,
} from '../components/ui';

export function PartnersScreen() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [partners, setPartners] = useState<Partner[]>([]);
  const [churches, setChurches] = useState<Church[]>([]);
  const [cellules, setCellules] = useState<Cellule[]>([]);
  const [search, setSearch] = useState('');
  const [churchFilter, setChurchFilter] = useState('');
  const [editing, setEditing] = useState<Partner | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');
  const [toDelete, setToDelete] = useState<Partner | null>(null);

  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [handleKc, setHandleKc] = useState('');
  const [address, setAddress] = useState('');
  const [birthdate, setBirthdate] = useState('');
  const [churchId, setChurchId] = useState('');
  const [celluleId, setCelluleId] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const query = new URLSearchParams();
      if (search) {
        query.set('search', search);
      }
      if (churchFilter) {
        query.set('church_id', churchFilter);
      }
      const [partnerData, churchData, celluleData] = await Promise.all([
        api<{ data: Partner[] }>(`/partners${query.toString() ? `?${query}` : ''}`, {
          cacheKey: `partners:${search}:${churchFilter}`,
        }),
        api<{ data: Church[] }>('/churches', { cacheKey: 'churches' }),
        api<{ data: Cellule[] }>('/cellules', { cacheKey: 'cellules' }),
      ]);
      setPartners(partnerData.data);
      setChurches(churchData.data);
      setCellules(celluleData.data);
      setChurchId((current) => current || churchData.data[0]?.id.toString() || '');
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Impossible de charger les partenaires.');
    } finally {
      setLoading(false);
    }
  }, [search, churchFilter]);

  useEffect(() => {
    const timer = setTimeout(() => void load(), 250);
    return () => clearTimeout(timer);
  }, [load]);

  useFocusEffect(useCallback(() => {
    void load();
  }, [load]));

  const churchOptions = churches.map((church) => ({ label: church.name, value: String(church.id) }));
  const visibleCellules = cellules.filter((cellule) => !churchId || cellule.church_id === Number(churchId));
  const celluleOptions = visibleCellules.map((cellule) => ({ label: cellule.name, value: String(cellule.id) }));

  const openForm = (partner?: Partner) => {
    setFormError('');
    setEditing(partner ?? null);
    setFirstName(partner?.first_name ?? '');
    setLastName(partner?.last_name ?? '');
    setEmail(partner?.email ?? '');
    setPhone(partner?.phone ?? '');
    setHandleKc(partner?.handle_kc ?? '');
    setAddress(partner?.address ?? '');
    setBirthdate(partner?.birthdate ?? '');
    setChurchId(partner ? String(partner.church_id) : churchId || churchOptions[0]?.value || '');
    setCelluleId(partner?.cellule_id ? String(partner.cellule_id) : '');
    setFormOpen(true);
  };

  const save = async () => {
    setSaving(true);
    setFormError('');
    const body: Record<string, unknown> = {
      first_name: firstName,
      last_name: lastName,
      email: email || null,
      phone: phone || null,
      handle_kc: handleKc || null,
      address: address || null,
      birthdate: birthdate || null,
      church_id: Number(churchId),
      cellule_id: celluleId ? Number(celluleId) : null,
    };

    try {
      if (editing) {
        await api(`/partners/${editing.id}`, { method: 'PATCH', body });
      } else {
        await api('/partners', { method: 'POST', body });
      }
      invalidateCache();
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
      await api(`/partners/${toDelete.id}`, { method: 'DELETE' });
      setToDelete(null);
      await load();
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Suppression impossible.');
      setToDelete(null);
    }
  };

  return (
    <Screen
      title="Partenaires"
      subtitle={`${partners.length} partenaire(s)`}
      actions={<PrimaryButton title="Ajouter" onPress={() => openForm()} />}
    >
      <Card>
        <TextField label="Rechercher" value={search} onChangeText={setSearch} placeholder="Nom, téléphone, handle KC" />
        <SelectField
          label="Église"
          value={churchFilter}
          options={[{ label: 'Toutes les églises', value: '' }, ...churchOptions]}
          onChange={setChurchFilter}
        />
      </Card>

      {loading ? <LoadingView /> : null}
      {!loading && error ? <ErrorView message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && partners.length === 0 ? <EmptyState message="Aucun partenaire trouvé." /> : null}

      {!loading && !error ? partners.map((partner) => (
        <Card key={partner.id}>
          <View style={styles.row}>
            <Text style={styles.name}>{partner.full_name ?? `${partner.first_name} ${partner.last_name}`}</Text>
            <Badge text={partner.church ?? '—'} />
          </View>
          <Text style={styles.meta}>{partner.phone ?? 'Téléphone non renseigné'}</Text>
          {partner.handle_kc ? <Text style={styles.meta}>KingsChat : {partner.handle_kc}</Text> : null}
          <Text style={styles.meta}>Total dons : {formatMoney(partner.total_donations ?? 0)}</Text>
          {partner.birthdate ? <Text style={styles.meta}>Naissance : {formatDate(partner.birthdate)}</Text> : null}
          <View style={styles.actions}>
            <Pressable onPress={() => openForm(partner)}><Text style={styles.edit}>Modifier</Text></Pressable>
            <Pressable onPress={() => setToDelete(partner)}><Text style={styles.delete}>Supprimer</Text></Pressable>
          </View>
        </Card>
      )) : null}

      <Modal visible={formOpen} animationType="slide" onRequestClose={() => setFormOpen(false)}>
        <Screen
          title={editing ? 'Modifier le partenaire' : 'Nouveau partenaire'}
          actions={<Pressable onPress={() => setFormOpen(false)}><Text style={styles.edit}>Fermer</Text></Pressable>}
        >
          <Card>
            <TextField label="Prénom" value={firstName} onChangeText={setFirstName} />
            <TextField label="Nom" value={lastName} onChangeText={setLastName} />
            <TextField label="Adresse email" value={email} onChangeText={setEmail} keyboardType="email-address" />
            <TextField label="Téléphone" value={phone} onChangeText={setPhone} keyboardType="phone-pad" />
            <TextField label="Identifiant KingsChat (handle KC)" value={handleKc} onChangeText={setHandleKc} />
            <TextField label="Adresse" value={address} onChangeText={setAddress} />
            <TextField label="Date de naissance (AAAA-MM-JJ)" value={birthdate} onChangeText={setBirthdate} />
            <SelectField label="Église" value={churchId} options={churchOptions} onChange={(value) => { setChurchId(value); setCelluleId(''); }} />
            <SelectField
              label="Cellule"
              value={celluleId}
              options={[{ label: 'Aucune', value: '' }, ...celluleOptions]}
              onChange={setCelluleId}
            />
            {formError ? <Text style={styles.error}>{formError}</Text> : null}
            <PrimaryButton title="Enregistrer" onPress={() => void save()} loading={saving} disabled={!firstName || !lastName || !churchId} />
          </Card>
        </Screen>
      </Modal>

      <ConfirmModal
        visible={toDelete !== null}
        title="Supprimer ce partenaire ?"
        message="Le partenaire et ses données associées seront supprimés."
        confirmLabel="Supprimer"
        onCancel={() => setToDelete(null)}
        onConfirm={() => void remove()}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', justifyContent: 'space-between', gap: 8, alignItems: 'flex-start' },
  name: { fontSize: 16, fontWeight: '700', color: colors.ink, flex: 1 },
  meta: { fontSize: 13, color: colors.muted, marginTop: 5 },
  actions: { flexDirection: 'row', gap: 18, marginTop: 12 },
  edit: { color: colors.blue, fontWeight: '700', fontSize: 13 },
  delete: { color: colors.red, fontWeight: '700', fontSize: 13 },
  error: { color: colors.red, marginBottom: 12 },
});
